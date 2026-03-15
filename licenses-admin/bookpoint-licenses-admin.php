<?php
/**
 * Plugin Name: BookPoint Licenses admin
 * Description: License admin panel + license validation REST API for BookPoint.
 * Version: 1.0.0
 * Author: BookPoint
 */

defined('ABSPATH') || exit;

final class POINTLYBOOKING_Licenses_Admin_Plugin {
  const VERSION = '1.0.0';

  const TABLE_SUFFIX = 'pointlybooking_licenses';

  const OPTION_UPDATES = 'pointlybooking_ls_updates';

  public static function init(): void {
    add_action('admin_menu', [__CLASS__, 'admin_menu']);
    add_action('admin_post_pointlybooking_la_create', [__CLASS__, 'handle_create']);
    add_action('admin_post_pointlybooking_la_toggle', [__CLASS__, 'handle_toggle']);
    add_action('admin_post_pointlybooking_la_reset', [__CLASS__, 'handle_reset']);
    add_action('admin_post_pointlybooking_la_save_updates', [__CLASS__, 'handle_save_updates']);

    add_action('rest_api_init', [__CLASS__, 'register_rest']);
  }

  public static function activate_plugin(): void {
    self::maybe_create_table();
  }

  private static function table(): string {
    global $wpdb;
    return $wpdb->prefix . self::TABLE_SUFFIX;
  }

  private static function is_safe_sql_identifier(string $identifier): bool {
    return preg_match('/^[A-Za-z0-9_]+$/', $identifier) === 1;
  }

  private static function quote_sql_identifier(string $identifier): string {
    return '`' . $identifier . '`';
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
      KEY email (email),
      KEY activated_domain (activated_domain),
      KEY expires_at (expires_at)
    ) {$charset};";

    dbDelta($sql);
  }

  private static function now_mysql_gmt(): string {
    return gmdate('Y-m-d H:i:s', time());
  }

  private static function normalize_key(string $key): string {
    $key = strtoupper(trim($key));
    $key = preg_replace('/\\s+/', '', $key);
    return (string)$key;
  }

  private static function random_key(): string {
    $raw = strtoupper(bin2hex(random_bytes(16)));
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

  public static function admin_menu(): void {
    add_menu_page(
      'BookPoint Licenses admin',
      'BookPoint Licenses admin',
      'manage_options',
      'pointlybooking_licenses_admin',
      [__CLASS__, 'render_admin_page'],
      'dashicons-admin-network',
      58
    );

    add_submenu_page(
      'pointlybooking_licenses_admin',
      'Updates',
      'Updates',
      'manage_options',
      'pointlybooking_licenses_admin_updates',
      [__CLASS__, 'render_updates_page']
    );
  }

  private static function find_license(string $key): ?array {
    global $wpdb;
    $table = self::table();
    if (!self::is_safe_sql_identifier($table)) {
      return null;
    }
    $quoted_table = self::quote_sql_identifier($table);
    $row = $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM {$quoted_table} WHERE license_key = %s LIMIT 1", $key),
      ARRAY_A
    );
    return is_array($row) ? $row : null;
  }

  private static function get_license_by_id(int $id): ?array {
    global $wpdb;
    $table = self::table();
    if (!self::is_safe_sql_identifier($table)) {
      return null;
    }
    $quoted_table = self::quote_sql_identifier($table);
    $row = $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM {$quoted_table} WHERE id = %d LIMIT 1", $id),
      ARRAY_A
    );
    return is_array($row) ? $row : null;
  }

  private static function update_license(int $id, array $updates): void {
    global $wpdb;
    $table = self::table();
    $wpdb->update($table, $updates, ['id' => $id]);
  }

  private static function compute_status(array $r): string {
    $disabled = !empty($r['is_disabled']);
    if ($disabled) return 'disabled';
    $expiresRaw = (string)($r['expires_at'] ?? '');
    if ($expiresRaw !== '') {
      $isExpired = time() > (int)strtotime($expiresRaw . ' UTC');
      if ($isExpired) return 'expired';
    }
    return 'valid';
  }

  public static function render_admin_page(): void {
    if (!current_user_can('manage_options')) return;

    global $wpdb;
    $table = self::table();
    if (!self::is_safe_sql_identifier($table)) {
      echo '<div class="wrap"><h1>' . esc_html__('BookPoint Licenses admin', 'bookpoint-booking') . '</h1><p>' . esc_html__('No licenses found.', 'bookpoint-booking') . '</p></div>';
      return;
    }
    $quoted_table = self::quote_sql_identifier($table);

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin filter input.
    $q = sanitize_text_field((string) wp_unslash($_GET['s'] ?? ''));
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin notice flag.
    $created_notice = sanitize_text_field((string) wp_unslash($_GET['created'] ?? ''));
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin notice flag.
    $updated_notice = sanitize_text_field((string) wp_unslash($_GET['updated'] ?? ''));
    if ($q !== '') {
      $like = '%' . $wpdb->esc_like($q) . '%';
      $rows = $wpdb->get_results(
        $wpdb->prepare(
          "SELECT * FROM {$quoted_table} WHERE license_key LIKE %s OR email LIKE %s OR activated_domain LIKE %s ORDER BY id DESC LIMIT 200",
          $like,
          $like,
          $like
        ),
        ARRAY_A
      ) ?: [];
    } else {
      $rows = $wpdb->get_results("SELECT * FROM {$quoted_table} ORDER BY id DESC LIMIT 50", ARRAY_A) ?: [];
    }

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('BookPoint Licenses admin', 'bookpoint-booking') . '</h1>';

    if ($created_notice !== '') {
      echo '<div class="notice notice-success"><p>' . esc_html__('License created.', 'bookpoint-booking') . '</p></div>';
    }
    if ($updated_notice !== '') {
      echo '<div class="notice notice-success"><p>' . esc_html__('Updated.', 'bookpoint-booking') . '</p></div>';
    }

    echo '<h2>' . esc_html__('Create License', 'bookpoint-booking') . '</h2>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="pointlybooking_la_create">';
    wp_nonce_field('pointlybooking_la_create');
    echo '<table class="form-table" role="presentation">';
    echo '<tr><th scope="row"><label for="pointlybooking_la_email">' . esc_html__('Customer email', 'bookpoint-booking') . '</label></th>';
    echo '<td><input id="pointlybooking_la_email" name="email" class="regular-text" type="email" placeholder="customer@example.com"></td></tr>';
    echo '<tr><th scope="row"><label for="pointlybooking_la_plan">' . esc_html__('Plan', 'bookpoint-booking') . '</label></th>';
    echo '<td><input id="pointlybooking_la_plan" name="plan" class="regular-text" type="text" placeholder="pro"></td></tr>';
    echo '<tr><th scope="row"><label for="pointlybooking_la_expires">' . esc_html__('Expires (YYYY-MM-DD)', 'bookpoint-booking') . '</label></th>';
    echo '<td><input id="pointlybooking_la_expires" name="expires" class="regular-text" type="text" placeholder="2027-02-04"> ';
    echo '<span class="description">' . esc_html__('Leave empty for never expires.', 'bookpoint-booking') . '</span></td></tr>';
    echo '<tr><th scope="row"><label for="pointlybooking_la_limit">' . esc_html__('Activation limit', 'bookpoint-booking') . '</label></th>';
    echo '<td><input id="pointlybooking_la_limit" name="limit" class="small-text" type="number" min="1" step="1" value="1"></td></tr>';
    echo '</table>';
    submit_button(__('Create License', 'bookpoint-booking'));
    echo '</form>';

    echo '<hr>';

    echo '<form method="get" style="margin:12px 0;">';
    echo '<input type="hidden" name="page" value="pointlybooking_licenses_admin">';
    echo '<input type="search" name="s" value="' . esc_attr($q) . '" placeholder="' . esc_attr__('Search key, email, domain...', 'bookpoint-booking') . '" style="min-width:320px;"> ';
    echo '<button class="button">' . esc_html__('Search', 'bookpoint-booking') . '</button>';
    if ($q !== '') {
      echo ' <a class="button" href="' . esc_url(admin_url('admin.php?page=pointlybooking_licenses_admin')) . '">' . esc_html__('Clear', 'bookpoint-booking') . '</a>';
    }
    echo '</form>';

    echo '<h2>' . esc_html__('Recent Licenses', 'bookpoint-booking') . '</h2>';

    if (!$rows) {
      echo '<p>' . esc_html__('No licenses found.', 'bookpoint-booking') . '</p>';
      echo '</div>';
      return;
    }

    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Key', 'bookpoint-booking') . '</th>';
    echo '<th>' . esc_html__('Email', 'bookpoint-booking') . '</th>';
    echo '<th>' . esc_html__('Status', 'bookpoint-booking') . '</th>';
    echo '<th>' . esc_html__('Expires', 'bookpoint-booking') . '</th>';
    echo '<th>' . esc_html__('Activated Domain', 'bookpoint-booking') . '</th>';
    echo '<th>' . esc_html__('Activations', 'bookpoint-booking') . '</th>';
    echo '<th>' . esc_html__('Actions', 'bookpoint-booking') . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($rows as $r) {
      $id = (int)($r['id'] ?? 0);
      $status = self::compute_status($r);
      $disabled = $status === 'disabled';

      $expires = self::to_date_string((string)($r['expires_at'] ?? ''));
      $act = sprintf('%d/%d', (int)($r['activations_count'] ?? 0), (int)($r['activations_limit'] ?? 1));

      $toggleUrl = wp_nonce_url(
        admin_url('admin-post.php?action=pointlybooking_la_toggle&id=' . $id),
        'pointlybooking_la_toggle_' . $id
      );
      $resetUrl = wp_nonce_url(
        admin_url('admin-post.php?action=pointlybooking_la_reset&id=' . $id),
        'pointlybooking_la_reset_' . $id
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
      echo esc_html($disabled ? __('Enable', 'bookpoint-booking') : __('Disable', 'bookpoint-booking'));
      echo '</a> ';
      echo '<a class="button button-small" href="' . esc_url($resetUrl) . '">';
      echo esc_html__('Reset Activation', 'bookpoint-booking');
      echo '</a>';
      echo '</td>';
      echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
  }

  public static function render_updates_page(): void {
    if (!current_user_can('manage_options')) return;
    $cfg = self::get_updates_config();

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('BookPoint Licenses admin - Updates', 'bookpoint-booking') . '</h1>';
    echo '<p>' . esc_html__('Configure what the BookPoint plugin update endpoint returns. Package URL should be a direct ZIP download.', 'bookpoint-booking') . '</p>';

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin notice flag.
    $saved_notice = sanitize_text_field((string) wp_unslash($_GET['saved'] ?? ''));
    if ($saved_notice !== '') {
      echo '<div class="notice notice-success"><p>' . esc_html__('Saved.', 'bookpoint-booking') . '</p></div>';
    }

    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="pointlybooking_la_save_updates">';
    wp_nonce_field('pointlybooking_la_save_updates');

    echo '<table class="form-table" role="presentation">';
    echo '<tr><th scope="row"><label for="pointlybooking_la_latest_version">' . esc_html__('Latest version', 'bookpoint-booking') . '</label></th>';
    echo '<td><input name="latest_version" id="pointlybooking_la_latest_version" class="regular-text" value="' . esc_attr((string)$cfg['latest_version']) . '" placeholder="1.2.3"></td></tr>';

    echo '<tr><th scope="row"><label for="pointlybooking_la_package_url">' . esc_html__('Package URL (ZIP)', 'bookpoint-booking') . '</label></th>';
    echo '<td><input name="package_url" id="pointlybooking_la_package_url" class="large-text" value="' . esc_attr((string)$cfg['package_url']) . '" placeholder="https://example.com/bookpoint-v5.zip"></td></tr>';

    echo '<tr><th scope="row"><label for="pointlybooking_la_requires">' . esc_html__('Requires WP', 'bookpoint-booking') . '</label></th>';
    echo '<td><input name="requires" id="pointlybooking_la_requires" class="regular-text" value="' . esc_attr((string)$cfg['requires']) . '" placeholder="6.0"></td></tr>';

    echo '<tr><th scope="row"><label for="pointlybooking_la_tested">' . esc_html__('Tested up to', 'bookpoint-booking') . '</label></th>';
    echo '<td><input name="tested" id="pointlybooking_la_tested" class="regular-text" value="' . esc_attr((string)$cfg['tested']) . '" placeholder="6.5"></td></tr>';

    echo '<tr><th scope="row"><label for="pointlybooking_la_homepage">' . esc_html__('Homepage', 'bookpoint-booking') . '</label></th>';
    echo '<td><input name="homepage" id="pointlybooking_la_homepage" class="large-text" value="' . esc_attr((string)$cfg['homepage']) . '"></td></tr>';

    $desc = (string)($cfg['sections']['description'] ?? '');
    $changelog = (string)($cfg['sections']['changelog'] ?? '');

    echo '<tr><th scope="row"><label for="pointlybooking_la_desc">' . esc_html__('Description', 'bookpoint-booking') . '</label></th>';
    echo '<td><textarea name="desc" id="pointlybooking_la_desc" class="large-text" rows="4">' . esc_textarea($desc) . '</textarea></td></tr>';

    echo '<tr><th scope="row"><label for="pointlybooking_la_changelog">' . esc_html__('Changelog', 'bookpoint-booking') . '</label></th>';
    echo '<td><textarea name="changelog" id="pointlybooking_la_changelog" class="large-text" rows="6">' . esc_textarea($changelog) . '</textarea></td></tr>';
    echo '</table>';

    submit_button(__('Save Updates Settings', 'bookpoint-booking'));
    echo '</form>';
    echo '</div>';
  }

  public static function handle_create(): void {
    if (!current_user_can('manage_options')) wp_die('Forbidden');
    check_admin_referer('pointlybooking_la_create');

    $email = sanitize_email((string) wp_unslash($_POST['email'] ?? ''));
    $plan = sanitize_text_field((string) wp_unslash($_POST['plan'] ?? ''));
    $expires = sanitize_text_field((string) wp_unslash($_POST['expires'] ?? ''));
    $limit = isset($_POST['limit']) ? max(1, absint(wp_unslash($_POST['limit']))) : 1;

    $expiresAt = null;
    if ($expires !== '' && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $expires)) {
      $expiresAt = $expires . ' 23:59:59';
    }

    global $wpdb;
    $table = self::table();

    for ($attempt = 0; $attempt < 8; $attempt++) {
      $key = self::random_key();
      $ok = $wpdb->insert($table, [
        'license_key' => $key,
        'user_id' => 0,
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
      ]);
      if ($ok) {
        wp_safe_redirect(admin_url('admin.php?page=pointlybooking_licenses_admin&created=1'));
        exit;
      }
    }

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_licenses_admin&created=0'));
    exit;
  }

  public static function handle_toggle(): void {
    if (!current_user_can('manage_options')) wp_die('Forbidden');
    $id = isset($_GET['id']) ? (int) wp_unslash($_GET['id']) : 0;
    check_admin_referer('pointlybooking_la_toggle_' . $id);

    $lic = self::get_license_by_id($id);
    if ($lic) {
      $new = empty($lic['is_disabled']) ? 1 : 0;
      self::update_license($id, ['is_disabled' => $new]);
    }

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_licenses_admin&updated=1'));
    exit;
  }

  public static function handle_reset(): void {
    if (!current_user_can('manage_options')) wp_die('Forbidden');
    $id = isset($_GET['id']) ? (int) wp_unslash($_GET['id']) : 0;
    check_admin_referer('pointlybooking_la_reset_' . $id);

    self::update_license($id, [
      'activated_domain' => '',
      'activated_at' => null,
      'instance_id' => '',
      'activations_count' => 0,
    ]);

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_licenses_admin&updated=1'));
    exit;
  }
  public static function handle_save_updates(): void {
    if (!current_user_can('manage_options')) wp_die('Forbidden');
    check_admin_referer('pointlybooking_la_save_updates');

    $cfg = self::get_updates_config();
    $cfg['latest_version'] = sanitize_text_field((string) wp_unslash($_POST['latest_version'] ?? ''));
    $cfg['package_url'] = esc_url_raw((string) wp_unslash($_POST['package_url'] ?? ''));
    $cfg['requires'] = sanitize_text_field((string) wp_unslash($_POST['requires'] ?? ''));
    $cfg['tested'] = sanitize_text_field((string) wp_unslash($_POST['tested'] ?? ''));
    $cfg['homepage'] = esc_url_raw((string) wp_unslash($_POST['homepage'] ?? ''));
    $cfg['sections'] = [
      'description' => wp_kses_post((string) wp_unslash($_POST['desc'] ?? '')),
      'changelog' => wp_kses_post((string) wp_unslash($_POST['changelog'] ?? '')),
    ];

    update_option(self::OPTION_UPDATES, $cfg, false);

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_licenses_admin_updates&saved=1'));
    exit;
  }

  public static function register_rest(): void {
    register_rest_route('bookpoint/v1', '/validate', [
      'methods' => 'POST',
      'callback' => [__CLASS__, 'rest_validate'],
      'permission_callback' => '__return_true',
    ]);

    register_rest_route('bookpoint/v1', '/deactivate', [
      'methods' => 'POST',
      'callback' => [__CLASS__, 'rest_deactivate'],
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

  private static function validate_license_row(array $lic, string $site, string $incomingInstanceId, bool $allowActivation): array {
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
          $count = max(0, $count) + 1;
          $instanceId = $incomingInstanceId !== '' ? $incomingInstanceId : ('inst_' . bin2hex(random_bytes(8)));
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

  public static function rest_validate(WP_REST_Request $req) {
    $p = $req->get_json_params();
    if (!is_array($p)) $p = [];

    $key = self::normalize_key((string)($p['license_key'] ?? ''));
    $site = (string)($p['site'] ?? '');
    $incomingInstanceId = sanitize_text_field((string)($p['instance_id'] ?? ''));

    if ($key === '') {
      return new WP_REST_Response(['ok' => false, 'status' => 'invalid', 'message' => 'Missing license key.'], 200);
    }

    $lic = self::find_license($key);
    if (!$lic) {
      return new WP_REST_Response(['ok' => false, 'status' => 'invalid', 'message' => 'License key not found.'], 200);
    }

    $validated = self::validate_license_row($lic, $site, $incomingInstanceId, true);

    $updates = [
      'last_seen_at' => self::now_mysql_gmt(),
      'last_seen_ip' => sanitize_text_field((string) wp_unslash($_SERVER['REMOTE_ADDR'] ?? '')),
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

    $resp = [
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

    return new WP_REST_Response($resp, 200);
  }

  public static function rest_deactivate(WP_REST_Request $req) {
    $p = $req->get_json_params();
    if (!is_array($p)) $p = [];

    $key = self::normalize_key((string)($p['license_key'] ?? ''));
    $site = (string)($p['site'] ?? '');
    $incomingInstanceId = sanitize_text_field((string)($p['instance_id'] ?? ''));
    $domain = self::parse_domain($site);

    if ($key === '') {
      return new WP_REST_Response(['ok' => false, 'status' => 'invalid', 'message' => 'Missing license key.'], 200);
    }

    $lic = self::find_license($key);
    if (!$lic) {
      return new WP_REST_Response(['ok' => false, 'status' => 'invalid', 'message' => 'License key not found.'], 200);
    }

    $activatedDomain = (string)($lic['activated_domain'] ?? '');
    if ($activatedDomain === '') {
      return new WP_REST_Response(['ok' => true, 'status' => 'deactivated', 'message' => 'Already deactivated.'], 200);
    }

    if ($domain !== '' && $activatedDomain !== $domain) {
      return new WP_REST_Response(['ok' => false, 'status' => 'invalid', 'message' => 'This license is activated on a different site.'], 200);
    }

    $storedInstance = (string)($lic['instance_id'] ?? '');
    if ($incomingInstanceId !== '' && $storedInstance !== '' && $storedInstance !== $incomingInstanceId) {
      return new WP_REST_Response(['ok' => false, 'status' => 'invalid', 'message' => 'Instance mismatch.'], 200);
    }

    $newCount = max(0, (int)($lic['activations_count'] ?? 0) - 1);
    self::update_license((int)$lic['id'], [
      'activated_domain' => '',
      'activated_at' => null,
      'instance_id' => '',
      'activations_count' => $newCount,
      'last_seen_at' => self::now_mysql_gmt(),
      'last_seen_ip' => sanitize_text_field((string) wp_unslash($_SERVER['REMOTE_ADDR'] ?? '')),
    ]);

    return new WP_REST_Response(['ok' => true, 'status' => 'deactivated', 'message' => 'License deactivated for this site.'], 200);
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
    $cfg = self::get_updates_config();

    $key = sanitize_text_field((string)($req->get_param('license_key') ?? ''));
    $site = (string)($req->get_param('site') ?? '');
    $wantInfo = !empty($req->get_param('info'));

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
    $cfg = self::get_updates_config();
    $key = sanitize_text_field((string)($req->get_param('license_key') ?? ''));
    $site = (string)($req->get_param('site') ?? '');

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
}

register_activation_hook(__FILE__, ['POINTLYBOOKING_Licenses_Admin_Plugin', 'activate_plugin']);
add_action('plugins_loaded', ['POINTLYBOOKING_Licenses_Admin_Plugin', 'init']);
