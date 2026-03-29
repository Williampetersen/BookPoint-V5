<?php
defined('ABSPATH') || exit;
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- This file's wpdb SQL paths interpolate only hardcoded plugin table names with a sanitized WordPress prefix; dynamic values remain prepared or static by design.

final class POINTLYBOOKING_AdminToolsController extends POINTLYBOOKING_Controller {

  public function index() : void {
    if (!current_user_can('pointlybooking_manage_tools') && !current_user_can('pointlybooking_manage_settings')) {
      wp_die(esc_html__('You do not have permission to access this page.', 'bookpoint-booking'));
    }

    global $wpdb;

    $result = null;
    if ($this->query_key('run') === 'sync_relations') {
      check_admin_referer('pointlybooking_tools_sync_relations');
      $result = POINTLYBOOKING_RelationsHelper::sync_relations(true);
    }

    $tables = [
      'pointlybooking_services',
      'pointlybooking_agents',
      'pointlybooking_customers',
      'pointlybooking_bookings',
      'pointlybooking_settings',
      'pointlybooking_audit_log',
      'pointlybooking_service_agents',
    ];

    $exists = [];
    foreach ($tables as $t) {
      $full = $wpdb->prefix . $t;
      $exists[$t] = pointlybooking_db_table_exists($full);
    }

    $db_version = (string) get_option('pointlybooking_db_version', '');
    $plugin_version = defined('POINTLYBOOKING_Core_Plugin::VERSION') ? POINTLYBOOKING_Core_Plugin::VERSION : '';

    $this->render('admin/tools_index', [
      'exists' => $exists,
      'db_version' => $db_version,
      'plugin_version' => $plugin_version,
      'result' => $result,
    ]);
  }

  public function email_test() : void {
    $this->require_cap('pointlybooking_manage_tools');
    check_admin_referer('pointlybooking_admin');

    $to = sanitize_email($this->post_raw('to'));
    if ($to === '') {
      $to = sanitize_email((string) get_option('admin_email'));
    }
    if (!$to || !is_email($to)) {
      wp_safe_redirect(admin_url('admin.php?page=pointlybooking_tools&email_test=bad'));
      exit;
    }

    $ok = POINTLYBOOKING_EmailHelper::send($to, 'BookPoint Test Email', '<p>This is a test email from BookPoint.</p>');
    POINTLYBOOKING_AuditHelper::log('tools_email_test', ['actor_type' => 'admin', 'meta' => ['to' => $to, 'ok' => $ok]]);

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_tools&email_test=' . ($ok ? 'ok' : 'fail')));
    exit;
  }

  public function webhook_test() : void {
    $this->require_cap('pointlybooking_manage_tools');
    check_admin_referer('pointlybooking_admin');

    $event = $this->post_text('event');
    if ($event === '') {
      $event = 'booking_created';
    }
    $payload = [
      'booking_id' => 999999,
      'status' => 'test',
      'start_datetime' => current_time('mysql'),
      'end_datetime' => current_time('mysql'),
    ];

    POINTLYBOOKING_WebhookHelper::fire($event, $payload);
    POINTLYBOOKING_AuditHelper::log('tools_webhook_test', ['actor_type' => 'admin', 'meta' => ['event' => $event]]);

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_tools&webhook_test=sent'));
    exit;
  }

  public function generate_demo() : void {
    $this->require_cap('pointlybooking_manage_tools');
    check_admin_referer('pointlybooking_admin');

    $count_services = $this->post_absint('services') ?: 3;
    $count_agents = $this->post_absint('agents') ?: 3;
    $count_customers = $this->post_absint('customers') ?: 5;
    $count_bookings = $this->post_absint('bookings') ?: 10;

    $result = POINTLYBOOKING_DemoHelper::generate($count_services, $count_agents, $count_customers, $count_bookings);

    POINTLYBOOKING_AuditHelper::log('tools_demo_generated', ['actor_type' => 'admin', 'meta' => $result]);

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_tools&demo=done'));
    exit;
  }

  public function export_settings() : void {
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names in this function are validated local plugin table names built from hardcoded plugin suffixes.
    $this->require_cap('pointlybooking_manage_tools');
    check_admin_referer('pointlybooking_admin');

    global $wpdb;
    $settings_table = $wpdb->prefix . 'pointlybooking_settings';
    if (preg_match('/^[A-Za-z0-9_]+$/', $settings_table) !== 1) {
      wp_die(esc_html__('Invalid settings table.', 'bookpoint-booking'));
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $rows = $wpdb->get_results(
      "SELECT setting_key, setting_value FROM {$settings_table}",
      ARRAY_A
    ) ?: [];
    $settings = [];
    foreach ($rows as $r) {
      $settings[(string)$r['setting_key']] = maybe_unserialize($r['setting_value']);
    }

    $payload = [
      'plugin' => 'bookpoint-booking',
      'exported_at' => current_time('mysql'),
      'pointlybooking_settings' => $settings,
      'wp_options' => [
        'pointlybooking_settings' => get_option('pointlybooking_settings', []),
        'pointlybooking_booking_form_design' => get_option('pointlybooking_booking_form_design', null),
      ],
      'options' => [
        'pointlybooking_remove_data_on_uninstall' => (int)get_option('pointlybooking_remove_data_on_uninstall', 0),
      ],
    ];

    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="bookpoint-settings-' . gmdate('Y-m-d') . '.json"');
    echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
  }

  public function import_settings() : void {
    $this->require_cap('pointlybooking_manage_tools');
    check_admin_referer('pointlybooking_admin');

    $raw = pointlybooking_get_uploaded_file_contents('pointlybooking_settings_file', ['json'], 5 * MB_IN_BYTES);
    if ($raw === null) {
      wp_safe_redirect(admin_url('admin.php?page=pointlybooking_tools&import=missing'));
      exit;
    }
    $data = json_decode($raw, true);

    $plugin_id = is_array($data) ? (string)($data['plugin'] ?? '') : '';
    if (!is_array($data) || !in_array($plugin_id, ['bookpoint-booking', 'pointly-booking', 'bookpoint'], true)) {
      wp_safe_redirect(admin_url('admin.php?page=pointlybooking_tools&import=badfile'));
      exit;
    }

    $settings = $data['pointlybooking_settings'] ?? null;
    if (!is_array($settings)) {
      wp_safe_redirect(admin_url('admin.php?page=pointlybooking_tools&import=badsettings'));
      exit;
    }

    $allowed_prefixes = ['pointlybooking_', 'payments_', 'stripe_', 'webhooks_', 'emails_', 'tpl_', 'booking_', 'portal_'];
    foreach ($settings as $k => $v) {
      $k = (string)$k;
      $ok = false;
      foreach ($allowed_prefixes as $p) {
        if (strpos($k, $p) === 0) { $ok = true; break; }
      }
      if (!$ok) continue;

      POINTLYBOOKING_SettingsHelper::set($k, $v);
    }

    $wp_options = $data['wp_options'] ?? null;
    if (is_array($wp_options)) {
      if (isset($wp_options['pointlybooking_settings']) && is_array($wp_options['pointlybooking_settings'])) {
        POINTLYBOOKING_SettingsHelper::set_all($wp_options['pointlybooking_settings']);
      }
      if (array_key_exists('pointlybooking_booking_form_design', $wp_options) && is_array($wp_options['pointlybooking_booking_form_design'])) {
        update_option('pointlybooking_booking_form_design', $wp_options['pointlybooking_booking_form_design'], false);
      }
    }

    if (isset($data['options']['pointlybooking_remove_data_on_uninstall'])) {
      update_option('pointlybooking_remove_data_on_uninstall', (int)$data['options']['pointlybooking_remove_data_on_uninstall'], false);
    }

    POINTLYBOOKING_AuditHelper::log('tools_settings_imported', ['actor_type' => 'admin']);

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_tools&import=ok'));
    exit;
  }
}
