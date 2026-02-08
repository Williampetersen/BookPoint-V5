<?php
/**
 * Plugin Name: BookPoint License Server
 * Description: WooCommerce license key generator + license validation server for BookPoint Pro.
 * Version: 1.0.13
 * Author: BookPoint
 */

defined('ABSPATH') || exit;

 final class BP_License_Server {
  const VERSION = '1.0.13';
  const DB_VERSION = '1';
  const OPTION_DB_VERSION = 'bp_ls_db_version';
  const PUBLIC_QUERY_VAR = 'bp_ls_public';
  const PUBLIC_PATH_BASE = 'bookpoint-license';

  const TABLE_SUFFIX = 'bp_licenses';
  const OPTION_UPDATES = 'bp_ls_updates';
  const OPTION_DEBUG_LOG = 'bp_ls_debug_log';
  const DEBUG_LOG_LIMIT = 80;

  const COL_LICENSE_KEY_HASH = 'license_key_hash';

  const ORDER_META_GENERATED = '_bp_ls_generated';
  const ORDER_META_EMAIL_SENT = '_bp_ls_email_sent';

  const META_ENABLE = '_bp_ls_enable';
  const META_EXPIRY_DAYS = '_bp_ls_expiry_days';
  const META_ACTIVATIONS_LIMIT = '_bp_ls_activations_limit';
  const META_PLAN = '_bp_ls_plan';

  private static function base64url_decode(string $s): string {
    $s = trim($s);
    if ($s === '') return '';
    $s = strtr($s, '-_', '+/');
    $pad = strlen($s) % 4;
    if ($pad) $s .= str_repeat('=', 4 - $pad);
    $out = base64_decode($s, true);
    return is_string($out) ? $out : '';
  }

  private static function unpack_payload(array $p): array {
    // Some WAFs block bodies containing common API field names like `license_key`.
    // Support a compact, encoded payload: `p` = base64url(json).
    if ((!isset($p['p']) || !is_string($p['p']) || $p['p'] === '') && isset($_SERVER['HTTP_X_BP_PAYLOAD'])) {
      $hdr = (string) $_SERVER['HTTP_X_BP_PAYLOAD'];
      if ($hdr !== '') $p['p'] = $hdr;
    }
    if ((!isset($p['p']) || !is_string($p['p']) || $p['p'] === '') && isset($_SERVER['HTTP_X_BOOKPOINT_PAYLOAD'])) {
      $hdr = (string) $_SERVER['HTTP_X_BOOKPOINT_PAYLOAD'];
      if ($hdr !== '') $p['p'] = $hdr;
    }
    if (isset($p['p']) && is_string($p['p']) && $p['p'] !== '') {
      $raw = self::base64url_decode((string) $p['p']);
      if ($raw !== '') {
        $j = json_decode($raw, true);
        if (is_array($j) && $j) {
          // Merge decoded values, decoded wins.
          $p = array_merge($p, $j);
        }
      }
    }

    // Aliases (short keys) to avoid WAF keyword matches.
    if (!isset($p['license_key']) && isset($p['k'])) $p['license_key'] = $p['k'];
    if (!isset($p['site']) && isset($p['s'])) $p['site'] = $p['s'];
    if (!isset($p['instance_id']) && isset($p['i'])) $p['instance_id'] = $p['i'];
    if (!isset($p['version']) && isset($p['v'])) $p['version'] = $p['v'];
    if (!isset($p['plugin']) && isset($p['pl'])) $p['plugin'] = $p['pl'];

    return $p;
  }

  public static function init(): void {
    self::maybe_upgrade_db();
    self::maybe_admin_table_notice();

    add_action('init', [__CLASS__, 'add_account_endpoint']);
    add_action('init', [__CLASS__, 'register_public_endpoints'], 1);
    add_action('admin_menu', [__CLASS__, 'admin_menu']);
    add_action('admin_notices', [__CLASS__, 'admin_notices']);
    add_action('rest_api_init', [__CLASS__, 'register_rest']);
    add_filter('query_vars', [__CLASS__, 'public_query_vars']);
    add_action('template_redirect', [__CLASS__, 'maybe_handle_public_endpoint'], 0);

    // Public AJAX endpoints as a WAF-friendly fallback for some hosts that block /wp-json/*.
    add_action('wp_ajax_nopriv_bp_ls_validate', [__CLASS__, 'ajax_validate']);
    add_action('wp_ajax_bp_ls_validate', [__CLASS__, 'ajax_validate']);
    add_action('wp_ajax_nopriv_bp_ls_deactivate', [__CLASS__, 'ajax_deactivate']);
    add_action('wp_ajax_bp_ls_deactivate', [__CLASS__, 'ajax_deactivate']);

    add_action('admin_post_bp_ls_create', [__CLASS__, 'handle_create']);
    add_action('admin_post_bp_ls_generate_order', [__CLASS__, 'handle_generate_order']);
    add_action('admin_post_bp_ls_debug_clear', [__CLASS__, 'handle_debug_clear']);
    add_action('admin_post_bp_ls_toggle', [__CLASS__, 'handle_toggle']);
    add_action('admin_post_bp_ls_reset', [__CLASS__, 'handle_reset']);
    add_action('admin_post_bp_ls_save_updates', [__CLASS__, 'handle_save_updates']);

    // Optional customer self-serve claim (disabled by default; use filter bp_ls_show_claim_form).
    add_action('admin_post_bp_ls_claim', [__CLASS__, 'handle_claim']);

    if (class_exists('WooCommerce')) {
      add_action('woocommerce_product_options_general_product_data', [__CLASS__, 'product_fields']);
      add_action('woocommerce_process_product_meta', [__CLASS__, 'save_product_fields']);
      add_action('woocommerce_order_status_processing', [__CLASS__, 'maybe_generate_for_order'], 5);
      add_action('woocommerce_order_status_completed', [__CLASS__, 'maybe_generate_for_order'], 5);
      add_action('woocommerce_payment_complete', [__CLASS__, 'maybe_generate_for_order'], 5);

      add_filter('woocommerce_account_menu_items', [__CLASS__, 'account_menu_items']);
      add_action('woocommerce_account_bookpoint-licenses_endpoint', [__CLASS__, 'render_account_page']);
      add_action('woocommerce_account_dashboard', [__CLASS__, 'render_account_dashboard_licenses']);

      add_action('woocommerce_email_order_meta', [__CLASS__, 'email_order_license_keys'], 20, 4);

      // Ensure licensed products always map to a customer account.
      add_filter('woocommerce_checkout_registration_required', [__CLASS__, 'maybe_require_registration'], 20, 1);
      add_filter('woocommerce_checkout_registration_enabled', [__CLASS__, 'maybe_enable_registration'], 20, 1);
      add_filter('woocommerce_checkout_guest_checkout', [__CLASS__, 'maybe_disable_guest_checkout'], 20, 1);
    }
  }

  public static function activate_plugin(): void {
    self::maybe_upgrade_db(true);
    self::add_account_endpoint();
    self::register_public_endpoints();
    flush_rewrite_rules();
  }

  public static function deactivate_plugin(): void {
    flush_rewrite_rules();
  }

  public static function register_public_endpoints(): void {
    add_rewrite_tag('%' . self::PUBLIC_QUERY_VAR . '%', '([^&]+)');
    add_rewrite_rule(
      '^' . self::PUBLIC_PATH_BASE . '/(validate|deactivate|ping)/?$',
      'index.php?' . self::PUBLIC_QUERY_VAR . '=$matches[1]',
      'top'
    );
  }

  public static function public_query_vars(array $vars): array {
    $vars[] = self::PUBLIC_QUERY_VAR;
    return $vars;
  }

  private static function public_payload(): array {
    $p = self::ajax_payload();
    if (isset($p[self::PUBLIC_QUERY_VAR])) unset($p[self::PUBLIC_QUERY_VAR]);
    return $p;
  }

  public static function maybe_handle_public_endpoint(): void {
    $action = '';
    if (isset($_GET[self::PUBLIC_QUERY_VAR])) {
      $action = sanitize_key((string) $_GET[self::PUBLIC_QUERY_VAR]);
    }
    if ($action === '' && function_exists('get_query_var')) {
      $action = sanitize_key((string) get_query_var(self::PUBLIC_QUERY_VAR));
    }

    if (!in_array($action, ['validate', 'deactivate', 'ping'], true)) return;

    // Basic support for preflight requests.
    if (isset($_SERVER['REQUEST_METHOD']) && strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'OPTIONS') {
      status_header(200);
      exit;
    }

    nocache_headers();

    // Keep these endpoints safe even if the hosting WAF is bypassed/allowlisted.
    self::enforce_rate_limit_or_send_json('public_' . $action . '_ip', 120, 300);

    $p = self::public_payload();
    $keyExtra = self::key_bucket_extra((string) ($p['license_key'] ?? ''));
    if ($keyExtra !== '') {
      self::enforce_rate_limit_or_send_json('public_' . $action . '_key', 60, 300, $keyExtra);
    }

    if ($action === 'ping') {
      $resp = self::ping_payload();
    } else {
      $resp = $action === 'deactivate'
        ? self::deactivate_from_payload($p)
        : self::validate_from_payload($p);
    }
    wp_send_json($resp, 200);
  }

  private static function table(): string {
    global $wpdb;
    return $wpdb->prefix . self::TABLE_SUFFIX;
  }

  private static function table_exists(): bool {
    global $wpdb;
    $table = self::table();
    $pattern = $wpdb->esc_like($table);
    $found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $pattern));
    return is_string($found) && $found === $table;
  }

  private static function maybe_admin_table_notice(): void {
    if (!is_admin()) return;
    if (!current_user_can('manage_options')) return;
    if (self::table_exists()) return;

    // Attempt to create once more (silent). If still missing, show notice.
    self::maybe_upgrade_db(true);

    if (!self::table_exists()) {
      add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('BookPoint License Server: license table was not created. Please deactivate/activate the plugin again and check database permissions.', 'bookpoint');
        echo '</p></div>';
      });
    }
  }

  private static function debug_log(string $event, array $data = []): void {
    if (!is_admin()) return;
    if (!current_user_can('manage_options')) return;

    $row = [
      't' => gmdate('c'),
      'event' => sanitize_text_field($event),
      'data' => $data,
    ];

    $log = get_option(self::OPTION_DEBUG_LOG, []);
    if (!is_array($log)) $log = [];
    array_unshift($log, $row);
    if (count($log) > self::DEBUG_LOG_LIMIT) {
      $log = array_slice($log, 0, self::DEBUG_LOG_LIMIT);
    }
    update_option(self::OPTION_DEBUG_LOG, $log, false);
  }

  private static function debug_log_clear(): void {
    if (!is_admin()) return;
    if (!current_user_can('manage_options')) return;
    delete_option(self::OPTION_DEBUG_LOG);
  }

  private static function show_claim_form(): bool {
    return (bool) apply_filters('bp_ls_show_claim_form', false);
  }

  private static function client_ip(): string {
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $ip = trim($ip);
    return $ip !== '' ? $ip : '0.0.0.0';
  }

  private static function rate_limit_exceeded(string $scope, int $limit, int $windowSec, string $extra = ''): bool {
    $ip = self::client_ip();
    $bucket = 'bp_ls_rl_' . md5($scope . '|' . $ip . '|' . $extra);

    $data = get_transient($bucket);
    if (!is_array($data)) {
      $data = ['count' => 0, 'start' => time()];
    }

    if (time() - (int) ($data['start'] ?? 0) > $windowSec) {
      $data = ['count' => 0, 'start' => time()];
    }

    $data['count'] = (int) ($data['count'] ?? 0) + 1;
    set_transient($bucket, $data, $windowSec);

    return (int) $data['count'] > $limit;
  }

  private static function rate_limited_payload(): array {
    return [
      'ok' => false,
      'status' => 'rate_limited',
      'message' => 'Too many requests. Please try again later.',
      'server_time' => gmdate('c'),
    ];
  }

  private static function enforce_rate_limit_or_send_json(string $scope, int $limit, int $windowSec, string $extra = ''): void {
    if (!self::rate_limit_exceeded($scope, $limit, $windowSec, $extra)) return;
    wp_send_json(self::rate_limited_payload(), 429);
  }

  private static function enforce_rate_limit_or_rest_response(string $scope, int $limit, int $windowSec, string $extra = ''): ?WP_REST_Response {
    if (!self::rate_limit_exceeded($scope, $limit, $windowSec, $extra)) return null;
    return new WP_REST_Response(self::rate_limited_payload(), 429);
  }

  private static function key_bucket_extra(string $key): string {
    $key = self::normalize_key($key);
    if ($key === '') return '';
    return hash('sha256', $key);
  }

  private static function table_has_column(string $column): bool {
    static $cache = [];

    $table = self::table();
    $key = $table . '|' . $column;
    if (array_key_exists($key, $cache)) {
      return (bool) $cache[$key];
    }

    if (!self::table_exists()) {
      $cache[$key] = false;
      return false;
    }

    global $wpdb;
    $found = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
    $cache[$key] = is_string($found) && $found !== '';
    return (bool) $cache[$key];
  }

  private static function license_key_hash(string $key): string {
    $key = self::normalize_key($key);
    if ($key === '') return '';
    return hash('sha256', $key);
  }

  private static function resolve_order_user_id(WC_Order $order, string $billingEmail): int {
    $userId = (int) $order->get_user_id();
    if ($userId > 0) return $userId;

    $billingEmail = sanitize_email($billingEmail);
    if ($billingEmail === '' || !function_exists('get_user_by')) return 0;

    $u = get_user_by('email', $billingEmail);
    if ($u && isset($u->ID)) {
      return (int) $u->ID;
    }

    return 0;
  }

  private static function maybe_link_order_licenses_to_user(WC_Order $order): void {
    if (!self::table_exists()) return;

    $orderId = (int) $order->get_id();
    if ($orderId <= 0) return;

    $email = sanitize_email((string) $order->get_billing_email());
    $userId = self::resolve_order_user_id($order, $email);
    if ($userId <= 0) return;

    global $wpdb;
    $table = self::table();

    // Link any generated license rows (including guest orders) to this WP user.
    $wpdb->update($table, ['user_id' => $userId], ['order_id' => $orderId, 'user_id' => 0]);
  }

  private static function maybe_create_table(): void {
    global $wpdb;
    $table = self::table();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (
      id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      license_key VARCHAR(64) NOT NULL,
      user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
      email VARCHAR(190) NOT NULL DEFAULT '',
      product_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
      order_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
      plan VARCHAR(60) NOT NULL DEFAULT '',
      is_disabled TINYINT(1) NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL,
      expires_at DATETIME NULL,
      activations_limit INT(11) NOT NULL DEFAULT 1,
      activations_count INT(11) NOT NULL DEFAULT 0,
      activated_domain VARCHAR(190) NOT NULL DEFAULT '',
      activated_at DATETIME NULL,
      instance_id VARCHAR(64) NOT NULL DEFAULT '',
      last_seen_at DATETIME NULL,
      last_seen_ip VARCHAR(45) NOT NULL DEFAULT '',
      PRIMARY KEY  (id),
      UNIQUE KEY license_key (license_key),
      KEY user_id (user_id),
      KEY email (email),
      KEY order_id (order_id),
      KEY activated_domain (activated_domain),
      KEY expires_at (expires_at)
    ) {$charset};";

    dbDelta($sql);
  }

  private static function maybe_upgrade_db(bool $force = false): void {
    $current = (string) get_option(self::OPTION_DB_VERSION, '');
    if (!$force && $current === self::DB_VERSION) return;

    self::maybe_create_table();
    update_option(self::OPTION_DB_VERSION, self::DB_VERSION, false);
  }

  private static function normalize_key(string $key): string {
    $key = strtoupper(trim($key));
    $key = preg_replace('/\\s+/', '', $key);
    return (string)$key;
  }

  private static function random_key(): string {
    $raw = bin2hex(random_bytes(16));
    $raw = strtoupper($raw);
    $parts = str_split($raw, 4);
    return 'BP5-' . implode('-', array_slice($parts, 0, 4));
  }

  private static function parse_domain(string $site): string {
    $p = wp_parse_url($site);
    $host = '';
    if (is_array($p) && !empty($p['host'])) $host = (string)$p['host'];
    $host = strtolower($host);
    $host = preg_replace('/^www\\./', '', $host);
    return (string)$host;
  }

  private static function now_mysql_gmt(): string {
    return gmdate('Y-m-d H:i:s', time());
  }

  private static function to_date_string(?string $mysqlDate): string {
    if (!$mysqlDate) return '';
    $ts = strtotime($mysqlDate . ' UTC');
    if (!$ts) return (string)$mysqlDate;
    return gmdate('Y-m-d', $ts);
  }

  private static function get_updates_config(): array {
    $cfg = get_option(self::OPTION_UPDATES, []);
    if (!is_array($cfg)) $cfg = [];

    return array_merge([
      'latest_version' => '',
      'package_url' => '',
      'requires' => '',
      'tested' => '',
      'homepage' => '',
      'sections' => [
        'description' => '',
        'changelog' => '',
      ],
    ], $cfg);
  }

  public static function add_account_endpoint(): void {
    if (!function_exists('add_rewrite_endpoint')) return;
    add_rewrite_endpoint('bookpoint-licenses', EP_ROOT | EP_PAGES);
  }
  public static function admin_menu(): void {
    add_menu_page(
      'BookPoint Licenses',
      'BookPoint Licenses',
      'manage_options',
      'bp_license_server',
      [__CLASS__, 'render_admin_page'],
      'dashicons-admin-network',
      58
    );

    add_submenu_page(
      'bp_license_server',
      'Updates',
      'Updates',
      'manage_options',
      'bp_license_server_updates',
      [__CLASS__, 'render_updates_page']
    );
  }

  public static function admin_notices(): void {
    if (!current_user_can('manage_options')) return;
    if (!class_exists('WooCommerce')) {
      echo '<div class="notice notice-warning"><p>';
      echo esc_html__('BookPoint License Server: WooCommerce is not active. License generation + customer account views require WooCommerce.', 'bookpoint');
      echo '</p></div>';
    }
  }

  private static function ping_payload(): array {
    return [
      'ok' => true,
      'status' => 'ok',
      'message' => 'pong',
      'server_time' => gmdate('c'),
      'version' => self::VERSION,
    ];
  }

  public static function register_rest(): void {
    register_rest_route('bookpoint/v1', '/validate', [
      'methods' => ['POST', 'GET'],
      'callback' => [__CLASS__, 'rest_validate'],
      'permission_callback' => '__return_true',
    ]);

    register_rest_route('bookpoint/v1', '/deactivate', [
      'methods' => ['POST', 'GET'],
      'callback' => [__CLASS__, 'rest_deactivate'],
      'permission_callback' => '__return_true',
    ]);

    register_rest_route('bookpoint/v1', '/ping', [
      'methods' => 'GET',
      'callback' => function () {
        return new WP_REST_Response(self::ping_payload(), 200);
      },
      'permission_callback' => '__return_true',
    ]);

    register_rest_route('bookpoint/v1', '/updates', [
      'methods' => 'GET',
      'callback' => [__CLASS__, 'rest_updates'],
      'permission_callback' => '__return_true',
    ]);

    register_rest_route('bookpoint/v1', '/download', [
      'methods' => 'GET',
      'callback' => [__CLASS__, 'rest_download'],
      'permission_callback' => '__return_true',
    ]);
  }

  private static function find_license(string $key): ?array {
    global $wpdb;
    $table = self::table();
    $key = self::normalize_key($key);
    if ($key === '') return null;

    if (self::table_has_column(self::COL_LICENSE_KEY_HASH)) {
      $hash = self::license_key_hash($key);
      $row = $wpdb->get_row(
        $wpdb->prepare(
          "SELECT * FROM {$table} WHERE license_key = %s OR " . self::COL_LICENSE_KEY_HASH . " = %s LIMIT 1",
          $key,
          $hash
        ),
        ARRAY_A
      );
    } else {
      $row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$table} WHERE license_key = %s LIMIT 1", $key),
        ARRAY_A
      );
    }
    return is_array($row) ? $row : null;
  }

  private static function get_license_by_id(int $id): ?array {
    global $wpdb;
    $table = self::table();
    $row = $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id),
      ARRAY_A
    );
    return is_array($row) ? $row : null;
  }

  private static function update_license(int $id, array $updates): void {
    global $wpdb;
    $table = self::table();
    $wpdb->update($table, $updates, ['id' => $id]);
  }

  private static function validate_license_row(
    array $lic,
    string $site,
    string $incomingInstanceId,
    bool $allowActivation
  ): array {
    $domain = self::parse_domain($site);
    $nowTs = time();

    $expiresAt = isset($lic['expires_at']) ? (string)$lic['expires_at'] : '';
    $isExpired = false;
    if ($expiresAt !== '') {
      $expTs = strtotime($expiresAt . ' UTC');
      if ($expTs && $nowTs > $expTs) $isExpired = true;
    }

    if (!empty($lic['is_disabled'])) {
      $status = 'disabled';
      $message = 'This license has been disabled.';
    } elseif ($isExpired) {
      $status = 'expired';
      $message = 'This license is expired.';
    } else {
      $status = 'valid';
      $message = 'License is valid.';
    }

    $activatedDomain = (string)($lic['activated_domain'] ?? '');
    $instanceId = (string)($lic['instance_id'] ?? '');
    $limit = (int)($lic['activations_limit'] ?? 1);
    $count = (int)($lic['activations_count'] ?? 0);

    if ($status === 'valid') {
      if ($domain === '') {
        $status = 'invalid';
        $message = 'Could not determine site domain.';
      } elseif ($activatedDomain !== '' && $activatedDomain !== $domain) {
        $status = 'invalid';
        $message = 'This license is already activated on: ' . $activatedDomain;
      } elseif ($activatedDomain === '' && $allowActivation) {
        if ($count >= $limit) {
          $status = 'invalid';
          $message = 'No activations remaining for this license.';
        } else {
          $activatedDomain = $domain;
          $count = max($count, 0) + 1;

          if ($incomingInstanceId !== '') {
            $instanceId = $incomingInstanceId;
          } else {
            $instanceId = 'inst_' . bin2hex(random_bytes(8));
          }
        }
      } else {
        if ($incomingInstanceId !== '' && $instanceId === '') {
          $instanceId = $incomingInstanceId;
        }
      }
    }

    return [
      'status' => $status,
      'message' => $message,
      'domain' => $domain,
      'activated_domain' => $activatedDomain,
      'instance_id' => $instanceId,
      'activations_limit' => $limit,
      'activations_count' => $count,
    ];
  }

  private static function validate_from_payload(array $p): array {
    $p = self::unpack_payload($p);
    $key = self::normalize_key((string)($p['license_key'] ?? ''));
    $site = (string)($p['site'] ?? '');
    $incomingInstanceId = sanitize_text_field((string)($p['instance_id'] ?? ''));

    if ($key === '') {
      return ['ok' => false, 'status' => 'invalid', 'message' => 'Missing license key.'];
    }

    $lic = self::find_license($key);
    if (!$lic) {
      return ['ok' => false, 'status' => 'invalid', 'message' => 'License key not found.'];
    }

    $validated = self::validate_license_row($lic, $site, $incomingInstanceId, true);

    $updates = [
      'last_seen_at' => self::now_mysql_gmt(),
      'last_seen_ip' => sanitize_text_field((string)($_SERVER['REMOTE_ADDR'] ?? '')),
    ];

    if ($validated['activated_domain'] !== (string)($lic['activated_domain'] ?? '')) {
      $updates['activated_domain'] = $validated['activated_domain'];
      $updates['activated_at'] = self::now_mysql_gmt();
      $updates['activations_count'] = (int)$validated['activations_count'];
    }
    if ($validated['instance_id'] !== (string)($lic['instance_id'] ?? '')) {
      $updates['instance_id'] = $validated['instance_id'];
    }

    if ($updates) {
      self::update_license((int)$lic['id'], $updates);
      $lic = array_merge($lic, $updates);
    }

    return [
      'ok' => ($validated['status'] === 'valid'),
      'status' => $validated['status'],
      'message' => $validated['message'],
      'plan' => (string)($lic['plan'] ?? ''),
      'expires_at' => self::to_date_string((string)($lic['expires_at'] ?? '')),
      'licensed_domain' => (string)($lic['activated_domain'] ?? ''),
      'instance_id' => (string)($lic['instance_id'] ?? ''),
      'activations' => [
        'count' => (int)($lic['activations_count'] ?? 0),
        'limit' => (int)($lic['activations_limit'] ?? 1),
      ],
      'server_time' => gmdate('c'),
    ];
  }

  private static function deactivate_from_payload(array $p): array {
    $p = self::unpack_payload($p);
    $key = self::normalize_key((string)($p['license_key'] ?? ''));
    $site = (string)($p['site'] ?? '');
    $incomingInstanceId = sanitize_text_field((string)($p['instance_id'] ?? ''));
    $domain = self::parse_domain($site);

    if ($key === '') {
      return ['ok' => false, 'status' => 'invalid', 'message' => 'Missing license key.'];
    }

    $lic = self::find_license($key);
    if (!$lic) {
      return ['ok' => false, 'status' => 'invalid', 'message' => 'License key not found.'];
    }

    $activatedDomain = (string)($lic['activated_domain'] ?? '');
    if ($activatedDomain === '') {
      return ['ok' => true, 'status' => 'deactivated', 'message' => 'Already deactivated.'];
    }

    if ($domain !== '' && $activatedDomain !== $domain) {
      return ['ok' => false, 'status' => 'invalid', 'message' => 'This license is activated on a different site.'];
    }

    $storedInstance = (string)($lic['instance_id'] ?? '');
    if ($incomingInstanceId !== '' && $storedInstance !== '' && $storedInstance !== $incomingInstanceId) {
      return ['ok' => false, 'status' => 'invalid', 'message' => 'Instance mismatch.'];
    }

    $newCount = max(0, (int)($lic['activations_count'] ?? 0) - 1);
    self::update_license((int)$lic['id'], [
      'activated_domain' => '',
      'activated_at' => null,
      'instance_id' => '',
      'activations_count' => $newCount,
      'last_seen_at' => self::now_mysql_gmt(),
      'last_seen_ip' => sanitize_text_field((string)($_SERVER['REMOTE_ADDR'] ?? '')),
    ]);

    return ['ok' => true, 'status' => 'deactivated', 'message' => 'License deactivated for this site.'];
  }

  private static function ajax_payload(): array {
    // admin-ajax does not natively parse JSON bodies.
    $p = [];

    if (!empty($_POST) && is_array($_POST)) {
      $p = wp_unslash($_POST);
    } elseif (!empty($_GET) && is_array($_GET)) {
      $p = wp_unslash($_GET);
    }

    if (!$p) {
      $raw = (string) file_get_contents('php://input');
      if ($raw !== '') {
        $j = json_decode($raw, true);
        if (is_array($j)) $p = $j;
      }
    }

    $p = is_array($p) ? $p : [];
    return self::unpack_payload($p);
  }

  public static function ajax_validate(): void {
    $p = self::ajax_payload();
    self::enforce_rate_limit_or_send_json('ajax_validate_ip', 120, 300);
    $keyExtra = self::key_bucket_extra((string) ($p['license_key'] ?? ''));
    if ($keyExtra !== '') {
      self::enforce_rate_limit_or_send_json('ajax_validate_key', 60, 300, $keyExtra);
    }
    $resp = self::validate_from_payload($p);
    wp_send_json($resp, 200);
  }

  public static function ajax_deactivate(): void {
    $p = self::ajax_payload();
    self::enforce_rate_limit_or_send_json('ajax_deactivate_ip', 120, 300);
    $keyExtra = self::key_bucket_extra((string) ($p['license_key'] ?? ''));
    if ($keyExtra !== '') {
      self::enforce_rate_limit_or_send_json('ajax_deactivate_key', 60, 300, $keyExtra);
    }
    $resp = self::deactivate_from_payload($p);
    wp_send_json($resp, 200);
  }

  public static function rest_validate(WP_REST_Request $req) {
    $p = $req->get_json_params();
    if (!is_array($p) || !$p) $p = $req->get_body_params();
    if (!is_array($p) || !$p) $p = $req->get_params();
    if (!is_array($p)) $p = [];

    // Optional header-packed payload to avoid WAFs that inspect POST bodies.
    $hdr = (string) $req->get_header('x-bp-payload');
    if ($hdr === '') $hdr = (string) $req->get_header('x-bookpoint-payload');
    if ($hdr !== '' && (!isset($p['p']) || !is_string($p['p']) || (string)$p['p'] === '')) {
      $p['p'] = $hdr;
    }

    $limited = self::enforce_rate_limit_or_rest_response('rest_validate_ip', 120, 300);
    if ($limited) return $limited;

    // Decode packed payload (if used) before key-scoped rate limiting.
    $pUnpacked = self::unpack_payload($p);
    $keyExtra = self::key_bucket_extra((string) ($pUnpacked['license_key'] ?? ''));
    if ($keyExtra !== '') {
      $limited2 = self::enforce_rate_limit_or_rest_response('rest_validate_key', 60, 300, $keyExtra);
      if ($limited2) return $limited2;
    }

    return new WP_REST_Response(self::validate_from_payload($p), 200);
  }

  public static function rest_deactivate(WP_REST_Request $req) {
    $p = $req->get_json_params();
    if (!is_array($p) || !$p) $p = $req->get_body_params();
    if (!is_array($p) || !$p) $p = $req->get_params();
    if (!is_array($p)) $p = [];

    $hdr = (string) $req->get_header('x-bp-payload');
    if ($hdr === '') $hdr = (string) $req->get_header('x-bookpoint-payload');
    if ($hdr !== '' && (!isset($p['p']) || !is_string($p['p']) || (string)$p['p'] === '')) {
      $p['p'] = $hdr;
    }

    $limited = self::enforce_rate_limit_or_rest_response('rest_deactivate_ip', 120, 300);
    if ($limited) return $limited;

    $pUnpacked = self::unpack_payload($p);
    $keyExtra = self::key_bucket_extra((string) ($pUnpacked['license_key'] ?? ''));
    if ($keyExtra !== '') {
      $limited2 = self::enforce_rate_limit_or_rest_response('rest_deactivate_key', 60, 300, $keyExtra);
      if ($limited2) return $limited2;
    }

    return new WP_REST_Response(self::deactivate_from_payload($p), 200);
  }

  private static function can_update_for_site(string $key, string $site): bool {
    $key = self::normalize_key($key);
    if ($key === '') return false;

    $lic = self::find_license($key);
    if (!$lic) return false;
    if (!empty($lic['is_disabled'])) return false;

    $expiresAt = (string)($lic['expires_at'] ?? '');
    if ($expiresAt !== '') {
      $expTs = strtotime($expiresAt . ' UTC');
      if ($expTs && time() > $expTs) return false;
    }

    $domain = self::parse_domain($site);
    $activated = (string)($lic['activated_domain'] ?? '');
    if ($activated !== '' && $domain !== '' && $activated !== $domain) return false;

    return true;
  }

  public static function rest_updates(WP_REST_Request $req) {
    $limited = self::enforce_rate_limit_or_rest_response('rest_updates_ip', 120, 300);
    if ($limited) return $limited;

    $cfg = self::get_updates_config();

    $key = sanitize_text_field((string)($req->get_param('license_key') ?? ''));
    $site = (string)($req->get_param('site') ?? '');
    $wantInfo = !empty($req->get_param('info'));

    $keyExtra = self::key_bucket_extra($key);
    if ($keyExtra !== '') {
      $limited2 = self::enforce_rate_limit_or_rest_response('rest_updates_key', 60, 300, $keyExtra);
      if ($limited2) return $limited2;
    }

    $allowed = self::can_update_for_site($key, $site);
    $latest = (string)($cfg['latest_version'] ?? '');

    $out = [
      'new_version' => $latest,
      'tested' => (string)($cfg['tested'] ?? ''),
      'requires' => (string)($cfg['requires'] ?? ''),
      'homepage' => (string)($cfg['homepage'] ?? ''),
      'package' => '',
    ];

    if ($wantInfo) {
      $out['sections'] = is_array($cfg['sections'] ?? null) ? $cfg['sections'] : [];
    }

    if ($allowed && $latest !== '' && !empty($cfg['package_url'])) {
      $out['package'] = add_query_arg(
        [
          'license_key' => $key,
          'site' => $site,
        ],
        home_url('/wp-json/bookpoint/v1/download')
      );
    }

    return new WP_REST_Response($out, 200);
  }

  public static function rest_download(WP_REST_Request $req) {
    $limited = self::enforce_rate_limit_or_rest_response('rest_download_ip', 120, 300);
    if ($limited) return $limited;

    $cfg = self::get_updates_config();

    $key = sanitize_text_field((string)($req->get_param('license_key') ?? ''));
    $site = (string)($req->get_param('site') ?? '');

    $keyExtra = self::key_bucket_extra($key);
    if ($keyExtra !== '') {
      $limited2 = self::enforce_rate_limit_or_rest_response('rest_download_key', 60, 300, $keyExtra);
      if ($limited2) return $limited2;
    }

    if (!self::can_update_for_site($key, $site)) {
      return new WP_REST_Response(['ok' => false, 'message' => 'License invalid.'], 403);
    }

    $pkg = (string)($cfg['package_url'] ?? '');
    if ($pkg === '') {
      return new WP_REST_Response(['ok' => false, 'message' => 'Package URL not configured.'], 404);
    }

    wp_safe_redirect($pkg);
    exit;
  }

  public static function product_fields(): void {
    echo '<div class="options_group">';

    woocommerce_wp_checkbox([
      'id' => self::META_ENABLE,
      'label' => __('Generate BookPoint license', 'bookpoint'),
      'description' => __('Create a license key when this product is purchased.', 'bookpoint'),
    ]);

    woocommerce_wp_text_input([
      'id' => self::META_PLAN,
      'label' => __('BookPoint plan name', 'bookpoint'),
      'type' => 'text',
      'desc_tip' => true,
      'description' => __('Shown to customer/plugin as the plan name (optional).', 'bookpoint'),
    ]);

    woocommerce_wp_text_input([
      'id' => self::META_EXPIRY_DAYS,
      'label' => __('License duration (days)', 'bookpoint'),
      'type' => 'number',
      'custom_attributes' => [
        'min' => '0',
        'step' => '1',
      ],
      'desc_tip' => true,
      'description' => __('0 = never expires.', 'bookpoint'),
    ]);

    woocommerce_wp_text_input([
      'id' => self::META_ACTIVATIONS_LIMIT,
      'label' => __('Activation limit', 'bookpoint'),
      'type' => 'number',
      'custom_attributes' => [
        'min' => '1',
        'step' => '1',
      ],
      'desc_tip' => true,
      'description' => __('How many sites can activate this license (recommended: 1).', 'bookpoint'),
    ]);

    echo '</div>';
  }

  public static function save_product_fields(int $postId): void {
    $enable = isset($_POST[self::META_ENABLE]) ? 'yes' : 'no';
    update_post_meta($postId, self::META_ENABLE, $enable);

    $plan = sanitize_text_field((string)($_POST[self::META_PLAN] ?? ''));
    update_post_meta($postId, self::META_PLAN, $plan);

    $days = isset($_POST[self::META_EXPIRY_DAYS]) ? absint($_POST[self::META_EXPIRY_DAYS]) : 0;
    update_post_meta($postId, self::META_EXPIRY_DAYS, $days);

    $limit = isset($_POST[self::META_ACTIVATIONS_LIMIT])
      ? max(1, absint($_POST[self::META_ACTIVATIONS_LIMIT]))
      : 1;
    update_post_meta($postId, self::META_ACTIVATIONS_LIMIT, $limit);
  }

  private static function effective_product_settings(WC_Product $product): array {
    $parent = null;
    if (method_exists($product, 'is_type') && $product->is_type('variation') && method_exists($product, 'get_parent_id')) {
      $pid = (int)$product->get_parent_id();
      if ($pid > 0 && function_exists('wc_get_product')) {
        $parent = wc_get_product($pid);
      }
    }

    $meta = function(string $key) use ($product, $parent) {
      $v = $product->get_meta($key, true);
      if (($v === '' || $v === null) && $parent instanceof WC_Product) {
        $v = $parent->get_meta($key, true);
      }
      return $v;
    };

    $enabled = $meta(self::META_ENABLE);
    $enabledOk = ($enabled === 'yes' || $enabled === '1' || $enabled === 1 || $enabled === true);

    $plan = (string)$meta(self::META_PLAN);
    $daysRaw = $meta(self::META_EXPIRY_DAYS);
    $days = is_numeric($daysRaw) ? (int)$daysRaw : 0;

    $limitRaw = $meta(self::META_ACTIVATIONS_LIMIT);
    $limit = is_numeric($limitRaw) ? (int)$limitRaw : 1;
    if ($limit < 1) $limit = 1;

    return [
      'enabled' => $enabledOk,
      'plan' => $plan,
      'days' => $days,
      'limit' => $limit,
    ];
  }

  private static function cart_has_licensed_product(): bool {
    if (!function_exists('WC')) return false;
    $wc = WC();
    if (!$wc || empty($wc->cart) || !method_exists($wc->cart, 'get_cart')) return false;

    foreach ($wc->cart->get_cart() as $item) {
      $product = null;
      if (!empty($item['data']) && $item['data'] instanceof WC_Product) {
        $product = $item['data'];
      } else {
        $pid = isset($item['variation_id']) && (int)$item['variation_id'] > 0 ? (int)$item['variation_id'] : (int)($item['product_id'] ?? 0);
        if ($pid > 0 && function_exists('wc_get_product')) {
          $product = wc_get_product($pid);
        }
      }
      if (!$product instanceof WC_Product) continue;

      $settings = self::effective_product_settings($product);
      if (!empty($settings['enabled'])) return true;
    }

    return false;
  }

  public static function maybe_require_registration($required) {
    if (is_user_logged_in()) return $required;
    return self::cart_has_licensed_product() ? true : $required;
  }

  public static function maybe_enable_registration($enabled) {
    if (is_user_logged_in()) return $enabled;
    return self::cart_has_licensed_product() ? true : $enabled;
  }

  public static function maybe_disable_guest_checkout($guestEnabled) {
    if (is_user_logged_in()) return $guestEnabled;
    return self::cart_has_licensed_product() ? false : $guestEnabled;
  }

  public static function maybe_generate_for_order(int $orderId): void {
    if (!class_exists('WC_Order')) return;
    $order = wc_get_order($orderId);
    if (!$order) return;

    if ((int)$order->get_meta(self::ORDER_META_GENERATED, true) === 1) {
      return;
    }

    $generatedKeys = [];
    $anyEnabled = false;

    if (!self::table_exists()) {
      self::debug_log('generate_skip_no_table', ['order_id' => $orderId]);
      return;
    }

    foreach ($order->get_items() as $item) {
      $product = $item->get_product();
      if (!$product) continue;

      $settings = self::effective_product_settings($product);
      if (empty($settings['enabled'])) continue;
      $anyEnabled = true;

      $qty = max(1, (int)$item->get_quantity());
      for ($i = 0; $i < $qty; $i++) {
        $key = self::create_license_for_order($order, $product);
        if ($key !== '') $generatedKeys[] = $key;
      }
    }

    if ($generatedKeys) {
      $order->update_meta_data(self::ORDER_META_GENERATED, 1);
      $order->save();

      // Ensure licenses are linked to the customer account so they appear in My Account.
      self::maybe_link_order_licenses_to_user($order);

      // Ensure the customer gets a license email even if the Woo email template doesn't include order meta.
      if ((int)$order->get_meta(self::ORDER_META_EMAIL_SENT, true) !== 1) {
        self::send_license_email($order, $generatedKeys);
        $order->update_meta_data(self::ORDER_META_EMAIL_SENT, 1);
        $order->save();
      }

      self::debug_log('generate_ok', [
        'order_id' => (int)$orderId,
        'count' => count($generatedKeys),
        'keys' => $generatedKeys,
        'status' => (string)$order->get_status(),
        'email' => (string)$order->get_billing_email(),
      ]);
    } else {
      self::debug_log('generate_none', [
        'order_id' => (int)$orderId,
        'status' => (string)$order->get_status(),
        'email' => (string)$order->get_billing_email(),
        'any_enabled_product' => $anyEnabled ? 1 : 0,
      ]);
    }
  }

  private static function create_license_for_order(WC_Order $order, WC_Product $product): string {
    global $wpdb;
    $table = self::table();

    $email = sanitize_email((string)$order->get_billing_email());
    $userId = self::resolve_order_user_id($order, $email);
    $productId = (int)$product->get_id();
    $orderId = (int)$order->get_id();

    $settings = self::effective_product_settings($product);
    $plan = (string)($settings['plan'] ?? '');
    $days = (int)($settings['days'] ?? 0);
    $limit = (int)($settings['limit'] ?? 1);
    if ($limit < 1) $limit = 1;

    $expiresAt = null;
    if ($days > 0) {
      $expiresAt = gmdate('Y-m-d H:i:s', time() + ($days * DAY_IN_SECONDS));
    }

    for ($attempt = 0; $attempt < 8; $attempt++) {
      $key = self::random_key();
      $data = [
        'license_key' => $key,
        'user_id' => $userId,
        'email' => $email,
        'product_id' => $productId,
        'order_id' => $orderId,
        'plan' => $plan,
        'is_disabled' => 0,
        'created_at' => self::now_mysql_gmt(),
        'expires_at' => $expiresAt,
        'activations_limit' => $limit,
        'activations_count' => 0,
        'activated_domain' => '',
        'activated_at' => null,
        'instance_id' => '',
        'last_seen_at' => null,
        'last_seen_ip' => '',
      ];

      // Support legacy schemas that have a required unique `license_key_hash` column.
      if (self::table_has_column(self::COL_LICENSE_KEY_HASH)) {
        $data[self::COL_LICENSE_KEY_HASH] = self::license_key_hash($key);
      }

      $ok = $wpdb->insert($table, $data);

      if ($ok) return $key;
    }

    $err = (string)($wpdb->last_error ?? '');
    if ($err !== '') {
      self::debug_log('db_insert_failed', [
        'order_id' => (int)$orderId,
        'product_id' => (int)$productId,
        'email' => (string)$email,
        'error' => $err,
      ]);
    }

    return '';
  }

  private static function order_ids_by_billing_email(string $email): array {
    $email = sanitize_email($email);
    if ($email === '' || !function_exists('wc_get_orders')) return [];

    // Use WooCommerce data store (HPOS-safe).
    $ids = wc_get_orders([
      'limit' => 10,
      'status' => ['processing', 'completed'],
      'orderby' => 'date',
      'order' => 'DESC',
      'billing_email' => $email,
      'return' => 'ids',
    ]);

    if (is_array($ids) && $ids) {
      return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    // Fallback for older stores: query legacy postmeta.
    global $wpdb;
    $posts = $wpdb->posts;
    $postmeta = $wpdb->postmeta;
    $st1 = 'wc-processing';
    $st2 = 'wc-completed';

    $sql = $wpdb->prepare(
      "SELECT p.ID
       FROM {$posts} p
       INNER JOIN {$postmeta} pm ON pm.post_id = p.ID
       WHERE p.post_type = 'shop_order'
         AND p.post_status IN (%s, %s)
         AND pm.meta_key = '_billing_email'
         AND pm.meta_value = %s
       ORDER BY p.post_date_gmt DESC
       LIMIT 10",
      $st1,
      $st2,
      $email
    );

    $ids2 = $wpdb->get_col($sql) ?: [];
    return array_values(array_unique(array_filter(array_map('intval', $ids2))));
  }

  private static function create_manual_license(string $email, string $plan, int $days, int $limit): string {
    global $wpdb;
    $table = self::table();

    $email = sanitize_email($email);
    $plan = sanitize_text_field($plan);
    if ($limit < 1) $limit = 1;

    $userId = 0;
    if ($email !== '') {
      $u = get_user_by('email', $email);
      if ($u && isset($u->ID)) {
        $userId = (int)$u->ID;
      }
    }

    $expiresAt = null;
    if ($days > 0) {
      $expiresAt = gmdate('Y-m-d H:i:s', time() + ($days * DAY_IN_SECONDS));
    }

    for ($attempt = 0; $attempt < 8; $attempt++) {
      $key = self::random_key();
      $data = [
        'license_key' => $key,
        'user_id' => $userId,
        'email' => $email,
        'product_id' => 0,
        'order_id' => 0,
        'plan' => $plan,
        'is_disabled' => 0,
        'created_at' => self::now_mysql_gmt(),
        'expires_at' => $expiresAt,
        'activations_limit' => $limit,
        'activations_count' => 0,
        'activated_domain' => '',
        'activated_at' => null,
        'instance_id' => '',
        'last_seen_at' => null,
        'last_seen_ip' => '',
      ];

      // Support legacy schemas that have a required unique `license_key_hash` column.
      if (self::table_has_column(self::COL_LICENSE_KEY_HASH)) {
        $data[self::COL_LICENSE_KEY_HASH] = self::license_key_hash($key);
      }

      $ok = $wpdb->insert($table, $data);

      if ($ok) return $key;
    }

    return '';
  }

  private static function send_manual_license_email(string $to, string $key, string $plan, string $expiresAt, int $limit): void {
    if ($to === '' || $key === '') return;

    $subject = __('Your BookPoint license key', 'bookpoint');

    $expires = self::to_date_string($expiresAt);
    $expires = $expires !== '' ? $expires : __('Never', 'bookpoint');

    $lines = [];
    $lines[] = __('Thanks for your purchase!', 'bookpoint');
    $lines[] = '';
    $lines[] = __('License key:', 'bookpoint') . ' ' . $key;
    if ($plan !== '') $lines[] = __('Plan:', 'bookpoint') . ' ' . $plan;
    $lines[] = __('Expires:', 'bookpoint') . ' ' . $expires;
    $lines[] = __('Sites:', 'bookpoint') . ' ' . (string)$limit;
    $lines[] = '';
    $lines[] = __('Activate in WordPress:', 'bookpoint') . ' BookPoint -> Settings -> License';

    $headers = ['Content-Type: text/plain; charset=UTF-8'];
    wp_mail($to, $subject, implode("\n", $lines), $headers);
  }

  private static function send_license_email(WC_Order $order, array $keys): void {
    $to = sanitize_email((string)$order->get_billing_email());
    if ($to === '') return;

    $subject = sprintf(
      __('Your BookPoint license key(s) for Order #%d', 'bookpoint'),
      (int)$order->get_id()
    );

    $lines = [];
    $lines[] = 'Thanks for your purchase!';
    $lines[] = '';
    $lines[] = 'Your license key(s):';
    foreach ($keys as $k) {
      $lines[] = ' - ' . (string)$k;
    }
    $lines[] = '';
    $lines[] = 'To activate: In WordPress go to BookPoint -> Settings -> License, paste the key, then Validate/Activate.';
    $lines[] = 'Order: #' . (int)$order->get_id();

    $headers = ['Content-Type: text/plain; charset=UTF-8'];
    wp_mail($to, $subject, implode("\n", $lines), $headers);
  }

  private static function licenses_for_order_id(int $orderId): array {
    global $wpdb;
    $table = self::table();
    if ($orderId <= 0) return [];

    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT license_key, expires_at, plan, activations_limit FROM {$table} WHERE order_id = %d ORDER BY id ASC",
        $orderId
      ),
      ARRAY_A
    );
    return is_array($rows) ? $rows : [];
  }

  public static function email_order_license_keys($order, $sent_to_admin, $plain_text, $email): void {
    if ($sent_to_admin) return;
    if (!class_exists('WC_Order') || !$order instanceof WC_Order) return;

    $orderId = (int)$order->get_id();
    if ($orderId <= 0) return;

    // Ensure the keys exist before printing inside the email.
    if ((int)$order->get_meta('_bp_ls_generated', true) !== 1) {
      self::maybe_generate_for_order($orderId);
      $order = wc_get_order($orderId) ?: $order;
    }

    $rows = self::licenses_for_order_id($orderId);
    if (!$rows) return;

    $orderDate = $order->get_date_created();
    $orderDateStr = $orderDate ? $orderDate->date_i18n('Y-m-d') : '';

    if ($plain_text) {
      echo "\n";
      echo "BookPoint License Key(s):\n";
      foreach ($rows as $r) {
        $k = (string)($r['license_key'] ?? '');
        if ($k === '') continue;
        $plan = (string)($r['plan'] ?? '');
        $expires = self::to_date_string(isset($r['expires_at']) ? (string)$r['expires_at'] : '');
        $expires = $expires !== '' ? $expires : 'Never';
        $limit = (int)($r['activations_limit'] ?? 1);
        $suffixParts = [];
        if ($plan !== '') $suffixParts[] = "Plan: {$plan}";
        $suffixParts[] = "Expires: {$expires}";
        $suffixParts[] = "Sites: {$limit}";
        echo "- {$k} (" . implode(', ', $suffixParts) . ")\n";
      }
      echo "\n";
      if ($orderDateStr !== '') {
        echo "Order date: {$orderDateStr}\n";
      }
      echo "Activate in WordPress: BookPoint -> Settings -> License\n\n";
      return;
    }

    echo '<h3>' . esc_html__('BookPoint License Key(s)', 'bookpoint') . '</h3>';
    echo '<ul>';
    foreach ($rows as $r) {
      $k = (string)($r['license_key'] ?? '');
      if ($k === '') continue;
      $plan = (string)($r['plan'] ?? '');
      $expires = self::to_date_string(isset($r['expires_at']) ? (string)$r['expires_at'] : '');
      $expiresLabel = $expires !== '' ? $expires : esc_html__('Never', 'bookpoint');
      $limit = (int)($r['activations_limit'] ?? 1);

      echo '<li>';
      echo '<code>' . esc_html($k) . '</code>';
      echo '<div style="opacity:.8;font-size:12px;margin-top:4px;">';
      if ($plan !== '') {
        echo esc_html__('Plan:', 'bookpoint') . ' ' . esc_html($plan) . ' &middot; ';
      }
      echo esc_html__('Expires:', 'bookpoint') . ' ' . esc_html($expiresLabel) . ' &middot; ';
      echo esc_html__('Sites:', 'bookpoint') . ' ' . esc_html((string)$limit);
      echo '</div>';
      echo '</li>';
    }
    echo '</ul>';
    if ($orderDateStr !== '') {
      echo '<p style="opacity:.8;">' . esc_html__('Order date:', 'bookpoint') . ' ' . esc_html($orderDateStr) . '</p>';
    }
    echo '<p>' . esc_html__('Activate in WordPress: BookPoint -> Settings -> License', 'bookpoint') . '</p>';
  }

  public static function render_account_dashboard_licenses(): void {
    if (!is_user_logged_in()) return;
    $userId = get_current_user_id();
    $user = wp_get_current_user();
    $email = (string)($user ? $user->user_email : '');

    global $wpdb;
    $table = self::table();
    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT * FROM {$table} WHERE (user_id = %d OR (email <> '' AND email = %s)) ORDER BY id DESC LIMIT 5",
        $userId,
        $email
      ),
      ARRAY_A
    ) ?: [];
    if (!$rows) {
      self::sync_licenses_for_user($userId, $email);
      $rows = $wpdb->get_results(
        $wpdb->prepare(
          "SELECT * FROM {$table} WHERE (user_id = %d OR (email <> '' AND email = %s)) ORDER BY id DESC LIMIT 5",
          $userId,
          $email
        ),
        ARRAY_A
      ) ?: [];
    }
    if (!$rows) return;

    $myAccountUrl = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : '';
    $licensesUrl = ($myAccountUrl && function_exists('wc_get_endpoint_url'))
      ? wc_get_endpoint_url('bookpoint-licenses', '', $myAccountUrl)
      : '';

    echo '<h3>' . esc_html__('BookPoint Licenses', 'bookpoint') . '</h3>';
    echo '<table class="shop_table shop_table_responsive">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('License Key', 'bookpoint') . '</th>';
    echo '<th>' . esc_html__('Status', 'bookpoint') . '</th>';
    echo '<th>' . esc_html__('Expires', 'bookpoint') . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($rows as $r) {
      $disabled = !empty($r['is_disabled']);
      $expiresRaw = (string)($r['expires_at'] ?? '');
      $expires = self::to_date_string($expiresRaw);
      $isExpired = $expiresRaw !== '' && (time() > (int)strtotime($expiresRaw . ' UTC'));
      $status = $disabled ? 'disabled' : ($isExpired ? 'expired' : 'valid');

      echo '<tr>';
      echo '<td data-title="' . esc_attr__('License Key', 'bookpoint') . '"><code>' . esc_html((string)($r['license_key'] ?? '')) . '</code></td>';
      echo '<td data-title="' . esc_attr__('Status', 'bookpoint') . '">' . esc_html($status) . '</td>';
      echo '<td data-title="' . esc_attr__('Expires', 'bookpoint') . '">' . esc_html($expires ?: '-') . '</td>';
      echo '</tr>';
    }
    echo '</tbody></table>';

    if ($licensesUrl !== '') {
      echo '<p><a class="button" href="' . esc_url($licensesUrl) . '">' . esc_html__('View all licenses', 'bookpoint') . '</a></p>';
    }
  }

  public static function account_menu_items(array $items): array {
    $out = [];
    foreach ($items as $k => $label) {
      $out[$k] = $label;
      if ($k === 'downloads') {
        $out['bookpoint-licenses'] = __('BookPoint Licenses', 'bookpoint');
      }
    }
    if (!isset($out['bookpoint-licenses'])) {
      $out['bookpoint-licenses'] = __('BookPoint Licenses', 'bookpoint');
    }
    return $out;
  }

  private static function paid_order_ids_for_user(int $userId): array {
    if ($userId <= 0 || !function_exists('wc_get_orders')) return [];
    $ids = wc_get_orders([
      'limit' => 20,
      'status' => ['processing', 'completed'],
      'orderby' => 'date',
      'order' => 'DESC',
      'customer_id' => $userId,
      'return' => 'ids',
    ]);
    if (!is_array($ids)) return [];
    return array_values(array_unique(array_filter(array_map('intval', $ids))));
  }

  private static function sync_licenses_for_user(int $userId, string $userEmail = ''): void {
    if ($userId <= 0) return;
    if (!class_exists('WC_Order')) return;
    if (!self::table_exists()) return;

    $orderIds = self::paid_order_ids_for_user($userId);

    $userEmail = sanitize_email($userEmail);
    if ($userEmail === '' && function_exists('get_user_by')) {
      $u = get_user_by('id', $userId);
      if ($u && isset($u->user_email)) {
        $userEmail = sanitize_email((string) $u->user_email);
      }
    }

    // Include guest/legacy orders placed with the same email (covers block-checkout and older purchases).
    if ($userEmail !== '') {
      $orderIds = array_merge($orderIds, self::order_ids_by_billing_email($userEmail));
    }

    $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
    if (!$orderIds) return;

    foreach ($orderIds as $oid) {
      $order = wc_get_order($oid);
      if (!$order instanceof WC_Order) continue;
      self::maybe_generate_for_order((int)$oid);
    }

    global $wpdb;
    $table = self::table();

    // Link by order_id (best): ensures keys show even if billing email differs from account email.
    $inOrder = implode(',', array_fill(0, count($orderIds), '%d'));
    $sql1 = $wpdb->prepare(
      "UPDATE {$table} SET user_id = %d WHERE user_id = 0 AND order_id IN ({$inOrder})",
      array_merge([$userId], $orderIds)
    );
    $wpdb->query($sql1);

    // Also link rows that match the WP user email directly.
    if ($userEmail !== '') {
      $wpdb->update($table, ['user_id' => $userId], ['user_id' => 0, 'email' => $userEmail]);
    }
  }

  public static function render_account_page(): void {
    if (!is_user_logged_in()) {
      echo '<p>' . esc_html__('Please log in to see your licenses.', 'bookpoint') . '</p>';
      return;
    }

    $userId = get_current_user_id();
    $user = wp_get_current_user();
    $email = (string)($user ? $user->user_email : '');

    global $wpdb;
    $table = self::table();

    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT * FROM {$table} WHERE (user_id = %d OR (email <> '' AND email = %s)) ORDER BY id DESC LIMIT 200",
        $userId,
        $email
      ),
      ARRAY_A
    ) ?: [];

    // Auto-sync: ensure paid orders generate keys and licenses are linked to this account.
    if (!$rows) {
      self::sync_licenses_for_user($userId, $email);
      $rows = $wpdb->get_results(
        $wpdb->prepare(
          "SELECT * FROM {$table} WHERE (user_id = %d OR (email <> '' AND email = %s)) ORDER BY id DESC LIMIT 200",
          $userId,
          $email
        ),
        ARRAY_A
      ) ?: [];
    }

    echo '<h3>' . esc_html__('Your BookPoint Licenses', 'bookpoint') . '</h3>';

    if (!$rows) {
      echo '<p>' . esc_html__('No licenses found for your account.', 'bookpoint') . '</p>';

      if (self::show_claim_form()) {
        echo '<div style="max-width:720px;border:1px solid rgba(15,23,42,.08);border-radius:12px;padding:12px;margin-top:12px;">';
        echo '<p style="margin:0 0 10px;">' . esc_html__('If you purchased as a guest or used a different billing email, you can claim your license by entering the Order ID and billing email below.', 'bookpoint') . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="bp_ls_claim">';
        wp_nonce_field('bp_ls_claim');
        echo '<p style="margin:0 0 10px;">';
        echo '<label style="display:block;margin-bottom:6px;">' . esc_html__('Order ID', 'bookpoint') . '</label>';
        echo '<input type="number" name="order_id" min="1" step="1" required style="width:100%;max-width:280px;">';
        echo '</p>';
        echo '<p style="margin:0 0 10px;">';
        echo '<label style="display:block;margin-bottom:6px;">' . esc_html__('Billing email', 'bookpoint') . '</label>';
        echo '<input type="email" name="billing_email" required style="width:100%;max-width:420px;">';
        echo '</p>';
        echo '<button class="button" type="submit">' . esc_html__('Find my license', 'bookpoint') . '</button>';
        echo '</form>';
        echo '</div>';
      }
      return;
    }

    echo '<table class="shop_table shop_table_responsive my_account_orders">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('License Key', 'bookpoint') . '</th>';
    echo '<th>' . esc_html__('Status', 'bookpoint') . '</th>';
    echo '<th>' . esc_html__('Expires', 'bookpoint') . '</th>';
    echo '<th>' . esc_html__('Activated Domain', 'bookpoint') . '</th>';
    echo '<th>' . esc_html__('Activations', 'bookpoint') . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($rows as $r) {
      $disabled = !empty($r['is_disabled']);
      $expiresRaw = (string)($r['expires_at'] ?? '');
      $expires = self::to_date_string($expiresRaw);
      $isExpired = $expiresRaw !== '' && (time() > (int)strtotime($expiresRaw . ' UTC'));
      $status = $disabled ? 'disabled' : ($isExpired ? 'expired' : 'valid');
      $domain = (string)($r['activated_domain'] ?? '');
      $act = sprintf('%d/%d', (int)($r['activations_count'] ?? 0), (int)($r['activations_limit'] ?? 1));

      echo '<tr>';
      echo '<td data-title="' . esc_attr__('License Key', 'bookpoint') . '"><code>' . esc_html((string)$r['license_key']) . '</code></td>';
      echo '<td data-title="' . esc_attr__('Status', 'bookpoint') . '">' . esc_html($status) . '</td>';
      echo '<td data-title="' . esc_attr__('Expires', 'bookpoint') . '">' . esc_html($expires ?: '-') . '</td>';
      echo '<td data-title="' . esc_attr__('Activated Domain', 'bookpoint') . '">' . esc_html($domain ?: '-') . '</td>';
      echo '<td data-title="' . esc_attr__('Activations', 'bookpoint') . '">' . esc_html($act) . '</td>';
      echo '</tr>';
    }

    echo '</tbody></table>';
  }

  private static function maybe_generate_for_recent_orders(int $userId, string $email = ''): void {
    if ($userId <= 0 && trim($email) === '') return;
    if (!function_exists('wc_get_orders')) return;

    $orderIds = [];

    if ($userId > 0) {
      $ids = wc_get_orders([
        'limit' => 10,
        'status' => ['processing', 'completed'],
        'orderby' => 'date',
        'order' => 'DESC',
        'customer_id' => $userId,
        'return' => 'ids',
      ]);
      if (is_array($ids)) $orderIds = array_merge($orderIds, $ids);
    }

    $email = sanitize_email($email);
    if ($email !== '') {
      $orderIds = array_merge($orderIds, self::order_ids_by_billing_email($email));
    }

    $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
    if (!$orderIds) return;

    foreach ($orderIds as $oid) {
      if ($oid <= 0) continue;
      $order = wc_get_order($oid);
      if (!$order instanceof WC_Order) continue;
      if ((int)$order->get_meta(self::ORDER_META_GENERATED, true) === 1) continue;
      self::maybe_generate_for_order($oid);
    }
  }

  public static function handle_claim(): void {
    if (!is_user_logged_in()) {
      wp_safe_redirect(wp_login_url());
      exit;
    }
    check_admin_referer('bp_ls_claim');

    if (!class_exists('WC_Order')) {
      wp_safe_redirect(wc_get_account_endpoint_url('bookpoint-licenses'));
      exit;
    }

    $orderId = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    $billingEmail = sanitize_email((string)($_POST['billing_email'] ?? ''));

    if ($orderId <= 0 || $billingEmail === '') {
      wp_safe_redirect(wc_get_account_endpoint_url('bookpoint-licenses'));
      exit;
    }

    $order = wc_get_order($orderId);
    if (!$order instanceof WC_Order) {
      wp_safe_redirect(wc_get_account_endpoint_url('bookpoint-licenses'));
      exit;
    }

    $orderEmail = sanitize_email((string)$order->get_billing_email());
    if ($orderEmail === '' || strcasecmp($orderEmail, $billingEmail) !== 0) {
      wp_safe_redirect(wc_get_account_endpoint_url('bookpoint-licenses'));
      exit;
    }

    $status = (string)$order->get_status();
    if (!in_array($status, ['processing', 'completed'], true)) {
      wp_safe_redirect(wc_get_account_endpoint_url('bookpoint-licenses'));
      exit;
    }

    // Generate keys if missing.
    self::maybe_generate_for_order($orderId);

    // Link generated licenses to the currently logged-in user for future lookups.
    $userId = get_current_user_id();
    if ($userId > 0) {
      global $wpdb;
      $table = self::table();
      $wpdb->update(
        $table,
        ['user_id' => $userId],
        ['order_id' => $orderId, 'email' => $orderEmail]
      );
    }

    wp_safe_redirect(wc_get_account_endpoint_url('bookpoint-licenses'));
    exit;
  }

  public static function render_admin_page(): void {
    if (!current_user_can('manage_options')) return;

    global $wpdb;
    $table = self::table();
    $q = sanitize_text_field((string)($_GET['s'] ?? ''));

    if ($q !== '') {
      $like = '%' . $wpdb->esc_like($q) . '%';
      $rows = $wpdb->get_results(
        $wpdb->prepare(
          "SELECT * FROM {$table} WHERE license_key LIKE %s OR email LIKE %s OR activated_domain LIKE %s ORDER BY id DESC LIMIT 200",
          $like,
          $like,
          $like
        ),
        ARRAY_A
      ) ?: [];
    } else {
      $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT 50", ARRAY_A) ?: [];
    }

    $base = admin_url('admin.php?page=bp_license_server');

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('BookPoint Licenses', 'bookpoint') . '</h1>';

    if (!empty($_GET['generated'])) {
      $tKey = 'bp_ls_last_generated_' . (int)get_current_user_id();
      $msg = (string)get_transient($tKey);
      if ($msg !== '') {
        delete_transient($tKey);
        echo '<div class="notice notice-success"><p>' . esc_html($msg) . '</p></div>';
      } else {
        echo '<div class="notice notice-success"><p>' . esc_html__('Generation attempted.', 'bookpoint') . '</p></div>';
      }
    }

    if (!empty($_GET['created'])) {
      $tKey = 'bp_ls_last_created_' . (int)get_current_user_id();
      $createdKey = (string)get_transient($tKey);
      if ($createdKey !== '') {
        delete_transient($tKey);
        echo '<div class="notice notice-success"><p>';
        echo esc_html__('License created:', 'bookpoint') . ' <code>' . esc_html($createdKey) . '</code>';
        echo '</p></div>';
      } else {
        echo '<div class="notice notice-success"><p>' . esc_html__('License created.', 'bookpoint') . '</p></div>';
      }
    }

    echo '<div class="postbox" style="max-width:900px;padding:14px 14px 10px;margin-top:16px;">';
    echo '<h2 style="margin:0 0 10px;">' . esc_html__('Create License (manual)', 'bookpoint') . '</h2>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="bp_ls_create">';
    wp_nonce_field('bp_ls_create');

    echo '<table class="form-table" role="presentation">';
    echo '<tr><th scope="row"><label for="bp_ls_email">' . esc_html__('Customer email', 'bookpoint') . '</label></th>';
    echo '<td><input name="email" id="bp_ls_email" class="regular-text" type="email" value="" placeholder="customer@example.com"></td></tr>';

    echo '<tr><th scope="row"><label for="bp_ls_plan">' . esc_html__('Plan (optional)', 'bookpoint') . '</label></th>';
    echo '<td><input name="plan" id="bp_ls_plan" class="regular-text" type="text" value="" placeholder="Pro / Lifetime / etc"></td></tr>';

    echo '<tr><th scope="row"><label for="bp_ls_days">' . esc_html__('Duration (days)', 'bookpoint') . '</label></th>';
    echo '<td><input name="days" id="bp_ls_days" class="small-text" type="number" min="0" step="1" value="0"> ';
    echo '<span class="description">' . esc_html__('0 = never expires.', 'bookpoint') . '</span></td></tr>';

    echo '<tr><th scope="row"><label for="bp_ls_limit">' . esc_html__('Activation limit', 'bookpoint') . '</label></th>';
    echo '<td><input name="limit" id="bp_ls_limit" class="small-text" type="number" min="1" step="1" value="1"></td></tr>';

    echo '<tr><th scope="row">' . esc_html__('Email customer', 'bookpoint') . '</th>';
    echo '<td><label><input type="checkbox" name="send_email" value="1" checked> ' . esc_html__('Send the key to the email above', 'bookpoint') . '</label></td></tr>';

    echo '</table>';

    submit_button(__('Create License', 'bookpoint'));
    echo '</form>';
    echo '</div>';

    echo '<div class="postbox" style="max-width:900px;padding:14px 14px 10px;margin-top:16px;">';
    echo '<h2 style="margin:0 0 10px;">' . esc_html__('Generate for Order ID (debug)', 'bookpoint') . '</h2>';
    echo '<p class="description">' . esc_html__('If you installed the license server after an order was paid, use this to generate the missing keys.', 'bookpoint') . '</p>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="bp_ls_generate_order">';
    wp_nonce_field('bp_ls_generate_order');
    echo '<label for="bp_ls_order_id" style="display:inline-block;min-width:120px;">' . esc_html__('Order ID', 'bookpoint') . '</label> ';
    echo '<input name="order_id" id="bp_ls_order_id" class="small-text" type="number" min="1" step="1" value=""> ';
    submit_button(__('Generate', 'bookpoint'), 'secondary', 'submit', false);
    echo '</form>';
    echo '</div>';

    echo '<form method="get" style="margin:12px 0;">';
    echo '<input type="hidden" name="page" value="bp_license_server">';
    echo '<input type="search" name="s" value="' . esc_attr($q) . '" placeholder="' . esc_attr__('Search key, email, domain...', 'bookpoint') . '" style="min-width:320px;"> ';
    echo '<button class="button">' . esc_html__('Search', 'bookpoint') . '</button>';
    if ($q !== '') {
      echo ' <a class="button" href="' . esc_url($base) . '">' . esc_html__('Clear', 'bookpoint') . '</a>';
    }
    echo '</form>';

    // Debug log
    $debugLog = get_option(self::OPTION_DEBUG_LOG, []);
    if (!is_array($debugLog)) $debugLog = [];

    echo '<details style="max-width:900px;margin:14px 0;">';
    echo '<summary style="cursor:pointer;">' . esc_html__('Debug log (last events)', 'bookpoint') . '</summary>';
    echo '<div style="padding:10px 0;">';

    echo '<p class="description">';
    echo esc_html__('If keys are not generating, this log shows why (e.g. product not enabled, table missing, DB insert error).', 'bookpoint');
    echo '</p>';

    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:0 0 10px;">';
    echo '<input type="hidden" name="action" value="bp_ls_debug_clear">';
    wp_nonce_field('bp_ls_debug_clear');
    submit_button(__('Clear debug log', 'bookpoint'), 'secondary', 'submit', false);
    echo '</form>';

    if (!$debugLog) {
      echo '<p>' . esc_html__('No debug events yet.', 'bookpoint') . '</p>';
    } else {
      echo '<pre style="background:#0b1220;color:#e5e7eb;border-radius:12px;padding:12px;overflow:auto;max-height:360px;font-size:12px;line-height:1.45;">';
      echo esc_html(wp_json_encode($debugLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
      echo '</pre>';
    }
    echo '</div>';
    echo '</details>';

    if (!$rows) {
      echo '<p>' . esc_html__('No licenses found.', 'bookpoint') . '</p>';
      echo '</div>';
      return;
    }

    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Key', 'bookpoint') . '</th>';
    echo '<th>' . esc_html__('Email', 'bookpoint') . '</th>';
    echo '<th>' . esc_html__('Status', 'bookpoint') . '</th>';
    echo '<th>' . esc_html__('Expires', 'bookpoint') . '</th>';
    echo '<th>' . esc_html__('Activated Domain', 'bookpoint') . '</th>';
    echo '<th>' . esc_html__('Activations', 'bookpoint') . '</th>';
    echo '<th>' . esc_html__('Actions', 'bookpoint') . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($rows as $r) {
      $id = (int)($r['id'] ?? 0);
      $disabled = !empty($r['is_disabled']);

      $expiresRaw = (string)($r['expires_at'] ?? '');
      $expires = self::to_date_string($expiresRaw);
      $isExpired = $expiresRaw !== '' && (time() > (int)strtotime($expiresRaw . ' UTC'));

      $status = $disabled ? 'disabled' : ($isExpired ? 'expired' : 'valid');
      $act = sprintf('%d/%d', (int)($r['activations_count'] ?? 0), (int)($r['activations_limit'] ?? 1));

      $toggleUrl = wp_nonce_url(
        admin_url('admin-post.php?action=bp_ls_toggle&id=' . $id),
        'bp_ls_toggle_' . $id
      );
      $resetUrl = wp_nonce_url(
        admin_url('admin-post.php?action=bp_ls_reset&id=' . $id),
        'bp_ls_reset_' . $id
      );

      echo '<tr>';
      echo '<td><code>' . esc_html((string)($r['license_key'] ?? '')) . '</code></td>';
      echo '<td>' . esc_html((string)($r['email'] ?? '')) . '</td>';
      echo '<td>' . esc_html($status) . '</td>';
      echo '<td>' . esc_html($expires ?: '-') . '</td>';
      echo '<td>' . esc_html((string)($r['activated_domain'] ?: '-')) . '</td>';
      echo '<td>' . esc_html($act) . '</td>';
      echo '<td>';
      echo '<a class="button button-small" href="' . esc_url($toggleUrl) . '">';
      echo esc_html($disabled ? __('Enable', 'bookpoint') : __('Disable', 'bookpoint'));
      echo '</a> ';
      echo '<a class="button button-small" href="' . esc_url($resetUrl) . '">';
      echo esc_html__('Reset Activation', 'bookpoint');
      echo '</a>';
      echo '</td>';
      echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
  }

  public static function handle_create(): void {
    if (!current_user_can('manage_options')) wp_die('Forbidden');
    check_admin_referer('bp_ls_create');

    $email = sanitize_email((string)($_POST['email'] ?? ''));
    $plan = sanitize_text_field((string)($_POST['plan'] ?? ''));
    $days = isset($_POST['days']) ? absint($_POST['days']) : 0;
    $limit = isset($_POST['limit']) ? max(1, absint($_POST['limit'])) : 1;
    $sendEmail = !empty($_POST['send_email']);

    $key = self::create_manual_license($email, $plan, $days, $limit);
    if ($key === '') {
      wp_safe_redirect(admin_url('admin.php?page=bp_license_server&created=0'));
      exit;
    }

    if ($sendEmail && $email !== '') {
      $expiresAt = '';
      if ($days > 0) {
        $expiresAt = gmdate('Y-m-d H:i:s', time() + ($days * DAY_IN_SECONDS));
      }
      self::send_manual_license_email($email, $key, $plan, $expiresAt, $limit);
    }

    set_transient('bp_ls_last_created_' . (int)get_current_user_id(), $key, 60);
    wp_safe_redirect(admin_url('admin.php?page=bp_license_server&created=1'));
    exit;
  }

  public static function handle_generate_order(): void {
    if (!current_user_can('manage_options')) wp_die('Forbidden');
    check_admin_referer('bp_ls_generate_order');

    $orderId = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    if ($orderId <= 0) {
      set_transient('bp_ls_last_generated_' . (int)get_current_user_id(), __('Missing order ID.', 'bookpoint'), 60);
      wp_safe_redirect(admin_url('admin.php?page=bp_license_server&generated=1'));
      exit;
    }

    self::maybe_generate_for_order($orderId);
    $rows = self::licenses_for_order_id($orderId);
    if ($rows) {
      $keys = [];
      foreach ($rows as $r) {
        $k = (string)($r['license_key'] ?? '');
        if ($k !== '') $keys[] = $k;
      }
      $msg = $keys
        ? sprintf(__('Generated / found %d key(s) for order #%d: %s', 'bookpoint'), count($keys), $orderId, implode(', ', $keys))
        : sprintf(__('No keys generated for order #%d (check product setting: "Generate BookPoint license").', 'bookpoint'), $orderId);
      set_transient('bp_ls_last_generated_' . (int)get_current_user_id(), $msg, 60);
    } else {
      set_transient(
        'bp_ls_last_generated_' . (int)get_current_user_id(),
        sprintf(__('No keys generated for order #%d (check product setting: "Generate BookPoint license").', 'bookpoint'), $orderId),
        60
      );
    }

    wp_safe_redirect(admin_url('admin.php?page=bp_license_server&generated=1'));
    exit;
  }

  public static function handle_debug_clear(): void {
    if (!current_user_can('manage_options')) wp_die('Forbidden');
    check_admin_referer('bp_ls_debug_clear');
    self::debug_log_clear();
    wp_safe_redirect(admin_url('admin.php?page=bp_license_server'));
    exit;
  }

  public static function render_updates_page(): void {
    if (!current_user_can('manage_options')) return;
    $cfg = self::get_updates_config();

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('BookPoint Updates', 'bookpoint') . '</h1>';
    echo '<p>' . esc_html__('Configure what the BookPoint plugin update endpoint returns. Package URL should be a direct ZIP download.', 'bookpoint') . '</p>';

    if (!empty($_GET['saved'])) {
      echo '<div class="notice notice-success"><p>' . esc_html__('Saved.', 'bookpoint') . '</p></div>';
    }

    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="bp_ls_save_updates">';
    wp_nonce_field('bp_ls_save_updates');

    echo '<table class="form-table" role="presentation">';

    echo '<tr><th scope="row"><label for="bp_ls_latest_version">' . esc_html__('Latest version', 'bookpoint') . '</label></th>';
    echo '<td><input name="latest_version" id="bp_ls_latest_version" class="regular-text" value="' . esc_attr((string)$cfg['latest_version']) . '" placeholder="1.2.3"></td></tr>';

    echo '<tr><th scope="row"><label for="bp_ls_package_url">' . esc_html__('Package URL (ZIP)', 'bookpoint') . '</label></th>';
    echo '<td><input name="package_url" id="bp_ls_package_url" class="large-text" value="' . esc_attr((string)$cfg['package_url']) . '" placeholder="https://example.com/bookpoint-v5.zip"></td></tr>';

    echo '<tr><th scope="row"><label for="bp_ls_requires">' . esc_html__('Requires WP', 'bookpoint') . '</label></th>';
    echo '<td><input name="requires" id="bp_ls_requires" class="regular-text" value="' . esc_attr((string)$cfg['requires']) . '" placeholder="6.0"></td></tr>';

    echo '<tr><th scope="row"><label for="bp_ls_tested">' . esc_html__('Tested up to', 'bookpoint') . '</label></th>';
    echo '<td><input name="tested" id="bp_ls_tested" class="regular-text" value="' . esc_attr((string)$cfg['tested']) . '" placeholder="6.5"></td></tr>';

    echo '<tr><th scope="row"><label for="bp_ls_homepage">' . esc_html__('Homepage', 'bookpoint') . '</label></th>';
    echo '<td><input name="homepage" id="bp_ls_homepage" class="large-text" value="' . esc_attr((string)$cfg['homepage']) . '"></td></tr>';

    $desc = (string)($cfg['sections']['description'] ?? '');
    $changelog = (string)($cfg['sections']['changelog'] ?? '');

    echo '<tr><th scope="row"><label for="bp_ls_desc">' . esc_html__('Description', 'bookpoint') . '</label></th>';
    echo '<td><textarea name="desc" id="bp_ls_desc" class="large-text" rows="4">' . esc_textarea($desc) . '</textarea></td></tr>';

    echo '<tr><th scope="row"><label for="bp_ls_changelog">' . esc_html__('Changelog', 'bookpoint') . '</label></th>';
    echo '<td><textarea name="changelog" id="bp_ls_changelog" class="large-text" rows="6">' . esc_textarea($changelog) . '</textarea></td></tr>';

    echo '</table>';

    submit_button(__('Save Updates Settings', 'bookpoint'));
    echo '</form>';
    echo '</div>';
  }

  public static function handle_save_updates(): void {
    if (!current_user_can('manage_options')) wp_die('Forbidden');
    check_admin_referer('bp_ls_save_updates');

    $cfg = self::get_updates_config();
    $cfg['latest_version'] = sanitize_text_field((string)($_POST['latest_version'] ?? ''));
    $cfg['package_url'] = esc_url_raw((string)($_POST['package_url'] ?? ''));
    $cfg['requires'] = sanitize_text_field((string)($_POST['requires'] ?? ''));
    $cfg['tested'] = sanitize_text_field((string)($_POST['tested'] ?? ''));
    $cfg['homepage'] = esc_url_raw((string)($_POST['homepage'] ?? ''));
    $cfg['sections'] = [
      'description' => wp_kses_post((string)($_POST['desc'] ?? '')),
      'changelog' => wp_kses_post((string)($_POST['changelog'] ?? '')),
    ];

    update_option(self::OPTION_UPDATES, $cfg, false);

    wp_safe_redirect(admin_url('admin.php?page=bp_license_server_updates&saved=1'));
    exit;
  }
  public static function handle_toggle(): void {
    if (!current_user_can('manage_options')) wp_die('Forbidden');

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    check_admin_referer('bp_ls_toggle_' . $id);

    $lic = self::get_license_by_id($id);
    if ($lic) {
      $new = empty($lic['is_disabled']) ? 1 : 0;
      self::update_license($id, ['is_disabled' => $new]);
    }

    wp_safe_redirect(admin_url('admin.php?page=bp_license_server'));
    exit;
  }

  public static function handle_reset(): void {
    if (!current_user_can('manage_options')) wp_die('Forbidden');

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    check_admin_referer('bp_ls_reset_' . $id);

    self::update_license($id, [
      'activated_domain' => '',
      'activated_at' => null,
      'instance_id' => '',
      'activations_count' => 0,
    ]);

    wp_safe_redirect(admin_url('admin.php?page=bp_license_server'));
    exit;
  }
}

register_activation_hook(__FILE__, ['BP_License_Server', 'activate_plugin']);
register_deactivation_hook(__FILE__, ['BP_License_Server', 'deactivate_plugin']);
add_action('plugins_loaded', ['BP_License_Server', 'init']);
