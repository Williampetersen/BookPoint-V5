<?php
defined('ABSPATH') || exit;

final class BP_AdminToolsController extends BP_Controller {

  public function index() : void {
    if (!current_user_can('bp_manage_tools') && !current_user_can('bp_manage_settings')) {
      wp_die(esc_html__('You do not have permission to access this page.', 'bookpoint'));
    }

    global $wpdb;

    $result = null;
    if (isset($_GET['run']) && $_GET['run'] === 'sync_relations') {
      check_admin_referer('bp_tools_sync_relations');
      $result = BP_RelationsHelper::sync_relations(true);
    }

    $tables = [
      'bp_services',
      'bp_agents',
      'bp_customers',
      'bp_bookings',
      'bp_settings',
      'bp_audit_log',
      'bp_service_agents',
    ];

    $exists = [];
    foreach ($tables as $t) {
      $full = $wpdb->prefix . $t;
      $exists[$t] = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $full)) === $full);
    }

    $db_version = (string) get_option('BP_db_version', '');
    $plugin_version = defined('BPV5_BookPoint_Core_Plugin::VERSION') ? BPV5_BookPoint_Core_Plugin::VERSION : '';

    $this->render('admin/tools_index', [
      'exists' => $exists,
      'db_version' => $db_version,
      'plugin_version' => $plugin_version,
      'result' => $result,
    ]);
  }

  public function email_test() : void {
    $this->require_cap('bp_manage_tools');
    check_admin_referer('bp_admin');

    $to = sanitize_email($_POST['to'] ?? get_option('admin_email'));
    if (!$to || !is_email($to)) {
      wp_safe_redirect(admin_url('admin.php?page=bp_tools&email_test=bad'));
      exit;
    }

    $ok = BP_EmailHelper::send($to, 'BookPoint Test Email', '<p>This is a test email from BookPoint.</p>');
    BP_AuditHelper::log('tools_email_test', ['actor_type' => 'admin', 'meta' => ['to' => $to, 'ok' => $ok]]);

    wp_safe_redirect(admin_url('admin.php?page=bp_tools&email_test=' . ($ok ? 'ok' : 'fail')));
    exit;
  }

  public function webhook_test() : void {
    $this->require_cap('bp_manage_tools');
    check_admin_referer('bp_admin');

    $event = sanitize_text_field($_POST['event'] ?? 'booking_created');
    $payload = [
      'booking_id' => 999999,
      'status' => 'test',
      'start_datetime' => current_time('mysql'),
      'end_datetime' => current_time('mysql'),
    ];

    BP_WebhookHelper::fire($event, $payload);
    BP_AuditHelper::log('tools_webhook_test', ['actor_type' => 'admin', 'meta' => ['event' => $event]]);

    wp_safe_redirect(admin_url('admin.php?page=bp_tools&webhook_test=sent'));
    exit;
  }

  public function generate_demo() : void {
    $this->require_cap('bp_manage_tools');
    check_admin_referer('bp_admin');

    $count_services = absint($_POST['services'] ?? 3);
    $count_agents = absint($_POST['agents'] ?? 3);
    $count_customers = absint($_POST['customers'] ?? 5);
    $count_bookings = absint($_POST['bookings'] ?? 10);

    $result = BP_DemoHelper::generate($count_services, $count_agents, $count_customers, $count_bookings);

    BP_AuditHelper::log('tools_demo_generated', ['actor_type' => 'admin', 'meta' => $result]);

    wp_safe_redirect(admin_url('admin.php?page=bp_tools&demo=done'));
    exit;
  }

  public function export_settings() : void {
    $this->require_cap('bp_manage_tools');
    check_admin_referer('bp_admin');

    global $wpdb;
    $table = $wpdb->prefix . 'bp_settings';

    $rows = $wpdb->get_results("SELECT setting_key, setting_value FROM {$table}", ARRAY_A) ?: [];
    $settings = [];
    foreach ($rows as $r) {
      $settings[(string)$r['setting_key']] = maybe_unserialize($r['setting_value']);
    }

    $payload = [
      'plugin' => 'bookpoint',
      'exported_at' => current_time('mysql'),
      'bp_settings' => $settings,
      'wp_options' => [
        'bp_settings' => get_option('bp_settings', []),
        'bp_booking_form_design' => get_option('bp_booking_form_design', null),
      ],
      'options' => [
        'bp_remove_data_on_uninstall' => (int)get_option('bp_remove_data_on_uninstall', 0),
      ],
    ];

    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="bookpoint-settings-' . date('Y-m-d') . '.json"');
    echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
  }

  public function import_settings() : void {
    $this->require_cap('bp_manage_tools');
    check_admin_referer('bp_admin');

    if (empty($_FILES['bp_settings_file']['tmp_name'])) {
      wp_safe_redirect(admin_url('admin.php?page=bp_tools&import=missing'));
      exit;
    }

    $raw = file_get_contents($_FILES['bp_settings_file']['tmp_name']);
    $data = json_decode($raw, true);

    if (!is_array($data) || ($data['plugin'] ?? '') !== 'bookpoint') {
      wp_safe_redirect(admin_url('admin.php?page=bp_tools&import=badfile'));
      exit;
    }

    $settings = $data['bp_settings'] ?? null;
    if (!is_array($settings)) {
      wp_safe_redirect(admin_url('admin.php?page=bp_tools&import=badsettings'));
      exit;
    }

    $allowed_prefixes = ['bp_', 'payments_', 'stripe_', 'webhooks_', 'emails_', 'tpl_', 'booking_', 'portal_'];
    foreach ($settings as $k => $v) {
      $k = (string)$k;
      $ok = false;
      foreach ($allowed_prefixes as $p) {
        if (strpos($k, $p) === 0) { $ok = true; break; }
      }
      if (!$ok) continue;

      BP_SettingsHelper::set($k, $v);
    }

    $wp_options = $data['wp_options'] ?? null;
    if (is_array($wp_options)) {
      if (isset($wp_options['bp_settings']) && is_array($wp_options['bp_settings'])) {
        BP_SettingsHelper::set_all($wp_options['bp_settings']);
      }
      if (array_key_exists('bp_booking_form_design', $wp_options) && is_array($wp_options['bp_booking_form_design'])) {
        update_option('bp_booking_form_design', $wp_options['bp_booking_form_design'], false);
      }
    }

    if (isset($data['options']['bp_remove_data_on_uninstall'])) {
      update_option('bp_remove_data_on_uninstall', (int)$data['options']['bp_remove_data_on_uninstall'], false);
    }

    BP_AuditHelper::log('tools_settings_imported', ['actor_type' => 'admin']);

    wp_safe_redirect(admin_url('admin.php?page=bp_tools&import=ok'));
    exit;
  }
}
