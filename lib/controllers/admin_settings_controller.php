<?php
defined('ABSPATH') || exit;

final class BP_AdminSettingsController extends BP_Controller {

  public function index(array $errors = []) : void {
    $this->require_cap('bp_manage_settings');

    $open = BP_SettingsHelper::get_with_default('bp_open_time');
    $close = BP_SettingsHelper::get_with_default('bp_close_time');
    $interval = (int)BP_SettingsHelper::get_with_default('bp_slot_interval_minutes');
    $currency = BP_SettingsHelper::get_with_default('bp_default_currency');
    $currency_position = BP_SettingsHelper::get_with_default('bp_currency_position');
    $email_enabled = (int)BP_SettingsHelper::get_with_default('bp_email_enabled');
    $admin_email = BP_SettingsHelper::get_with_default('bp_admin_email');
    $from_name = BP_SettingsHelper::get_with_default('bp_email_from_name');
    $from_email = BP_SettingsHelper::get_with_default('bp_email_from_email');
    $remove_data_on_uninstall = (int) get_option('bp_remove_data_on_uninstall', 0);
    $license_key = BP_LicenseHelper::get_key();
    $license_status = BP_LicenseHelper::status();
    $license_checked_at = (int) get_option('bp_license_checked_at', 0);
    $license_last_error = (string) get_option('bp_license_last_error', '');
    $webhooks_enabled = (int)BP_SettingsHelper::get_with_default('webhooks_enabled');
    $webhooks_secret = BP_SettingsHelper::get_with_default('webhooks_secret');
    $webhooks_url_booking_created = BP_SettingsHelper::get_with_default('webhooks_url_booking_created');
    $webhooks_url_booking_status_changed = BP_SettingsHelper::get_with_default('webhooks_url_booking_status_changed');
    $webhooks_url_booking_updated = BP_SettingsHelper::get_with_default('webhooks_url_booking_updated');
    $webhooks_url_booking_cancelled = BP_SettingsHelper::get_with_default('webhooks_url_booking_cancelled');
    
    // Step 14: Schedule & Availability
    $future_days_limit = (int)BP_SettingsHelper::get_with_default('bp_future_days_limit');
    $breaks = BP_SettingsHelper::get_with_default('bp_breaks');
    $schedule = [];
    for ($i = 0; $i < 7; $i++) {
      $schedule[$i] = BP_SettingsHelper::get_with_default('bp_schedule_' . $i);
    }

    $this->render('admin/settings', [
      'open' => $open,
      'close' => $close,
      'interval' => $interval,
      'currency' => $currency,
      'currency_position' => $currency_position,
      'email_enabled' => $email_enabled,
      'admin_email' => $admin_email,
      'from_name' => $from_name,
      'from_email' => $from_email,
      'remove_data_on_uninstall' => $remove_data_on_uninstall,
      'license_key' => $license_key,
      'license_status' => $license_status,
      'license_checked_at' => $license_checked_at,
      'license_last_error' => $license_last_error,
      'webhooks_enabled' => $webhooks_enabled,
      'webhooks_secret' => $webhooks_secret,
      'webhooks_url_booking_created' => $webhooks_url_booking_created,
      'webhooks_url_booking_status_changed' => $webhooks_url_booking_status_changed,
      'webhooks_url_booking_updated' => $webhooks_url_booking_updated,
      'webhooks_url_booking_cancelled' => $webhooks_url_booking_cancelled,
      'future_days_limit' => $future_days_limit,
      'breaks' => $breaks,
      'schedule' => $schedule,
      'errors' => $errors,
    ]);
  }

  public function save() : void {
    $this->require_cap('bp_manage_settings');

    check_admin_referer('bp_admin');

    $tab = sanitize_text_field($_POST['tab'] ?? 'general');
    $errors = [];

    if ($tab === 'general') {
      $open = sanitize_text_field($_POST['open_time'] ?? '09:00');
      $close = sanitize_text_field($_POST['close_time'] ?? '17:00');
      $interval = absint($_POST['slot_interval_minutes'] ?? 15);
      $currency = strtoupper(sanitize_key($_POST['default_currency'] ?? 'usd'));
      $currency_position = sanitize_text_field($_POST['currency_position'] ?? 'before');
      $remove_data_on_uninstall = isset($_POST['bp_remove_data_on_uninstall']) ? 1 : 0;

      $future_days_limit = absint($_POST['future_days_limit'] ?? 60);
      $breaks = sanitize_text_field($_POST['breaks'] ?? '12:00-13:00');
      $schedule = [];
      for ($i = 0; $i < 7; $i++) {
        $schedule[$i] = sanitize_text_field($_POST['schedule_' . $i] ?? '');
      }

      if (!preg_match('/^\d{2}:\d{2}$/', $open)) {
        $errors['open_time'] = __('Open time must be HH:MM', 'bookpoint');
      }
      if (!preg_match('/^\d{2}:\d{2}$/', $close)) {
        $errors['close_time'] = __('Close time must be HH:MM', 'bookpoint');
      }
      if ($interval < 5 || $interval > 120) {
        $errors['slot_interval_minutes'] = __('Slot interval must be between 5 and 120 minutes.', 'bookpoint');
      }
      if (!preg_match('/^[A-Z]{3}$/', $currency)) {
        $errors['default_currency'] = __('Currency must be a 3-letter code.', 'bookpoint');
      }
      if (!in_array($currency_position, ['before','after'], true)) {
        $errors['currency_position'] = __('Currency position must be before or after.', 'bookpoint');
      }

      if ($future_days_limit < 1 || $future_days_limit > 365) {
        $errors['future_days_limit'] = __('Future days limit must be between 1 and 365.', 'bookpoint');
      }

      for ($i = 0; $i < 7; $i++) {
        if (!empty($schedule[$i]) && !preg_match('/^\d{2}:\d{2}-\d{2}:\d{2}$/', $schedule[$i])) {
          $errors['schedule_' . $i] = __('Schedule must be empty or HH:MM-HH:MM format', 'bookpoint');
        }
      }

      if (!empty($breaks)) {
        $break_ranges = explode(',', $breaks);
        foreach ($break_ranges as $br) {
          $br = trim($br);
          if (!empty($br) && !preg_match('/^\d{2}:\d{2}-\d{2}:\d{2}$/', $br)) {
            $errors['breaks'] = __('Each break must be HH:MM-HH:MM format, comma-separated', 'bookpoint');
            break;
          }
        }
      }

      if (!empty($errors)) {
        $this->index($errors);
        return;
      }

      BP_SettingsHelper::set('bp_open_time', $open);
      BP_SettingsHelper::set('bp_close_time', $close);
      BP_SettingsHelper::set('bp_slot_interval_minutes', $interval);
      BP_SettingsHelper::set('bp_default_currency', $currency);
      BP_SettingsHelper::set('bp_currency_position', $currency_position);

      BP_SettingsHelper::set('bp_future_days_limit', $future_days_limit);
      BP_SettingsHelper::set('bp_breaks', $breaks);
      for ($i = 0; $i < 7; $i++) {
        BP_SettingsHelper::set('bp_schedule_' . $i, $schedule[$i]);
      }

      update_option('bp_remove_data_on_uninstall', $remove_data_on_uninstall, false);
    }

    if ($tab === 'emails') {
      $email_enabled = isset($_POST['email_enabled']) ? 1 : 0;
      $admin_email = sanitize_email($_POST['admin_email'] ?? get_option('admin_email'));
      $from_name = sanitize_text_field($_POST['from_name'] ?? get_bloginfo('name'));
      $from_email = sanitize_email($_POST['from_email'] ?? get_option('admin_email'));

      if ($admin_email === '') {
        $errors['admin_email'] = __('Admin email is invalid.', 'bookpoint');
      }
      if ($from_email === '') {
        $errors['from_email'] = __('From email is invalid.', 'bookpoint');
      }

      if (!empty($errors)) {
        $this->index($errors);
        return;
      }

      BP_SettingsHelper::set('bp_email_enabled', $email_enabled);
      BP_SettingsHelper::set('bp_admin_email', $admin_email);
      BP_SettingsHelper::set('bp_email_from_name', $from_name);
      BP_SettingsHelper::set('bp_email_from_email', $from_email);
    }

    if ($tab === 'webhooks') {
      $webhooks_enabled = isset($_POST['webhooks_enabled']) ? 1 : 0;
      $webhooks_secret = sanitize_text_field($_POST['webhooks_secret'] ?? '');
      $webhooks_url_booking_created = esc_url_raw($_POST['webhooks_url_booking_created'] ?? '');
      $webhooks_url_booking_status_changed = esc_url_raw($_POST['webhooks_url_booking_status_changed'] ?? '');
      $webhooks_url_booking_updated = esc_url_raw($_POST['webhooks_url_booking_updated'] ?? '');
      $webhooks_url_booking_cancelled = esc_url_raw($_POST['webhooks_url_booking_cancelled'] ?? '');

      BP_SettingsHelper::set('webhooks_enabled', $webhooks_enabled);
      BP_SettingsHelper::set('webhooks_secret', $webhooks_secret);
      BP_SettingsHelper::set('webhooks_url_booking_created', $webhooks_url_booking_created);
      BP_SettingsHelper::set('webhooks_url_booking_status_changed', $webhooks_url_booking_status_changed);
      BP_SettingsHelper::set('webhooks_url_booking_updated', $webhooks_url_booking_updated);
      BP_SettingsHelper::set('webhooks_url_booking_cancelled', $webhooks_url_booking_cancelled);
    }

    $tab = sanitize_text_field($_POST['tab'] ?? 'general');
    wp_safe_redirect(admin_url('admin.php?page=bp_settings&tab=' . $tab . '&updated=1'));
    exit;
  }

  public function save_license(): void {
    $this->require_cap('bp_manage_settings');
    check_admin_referer('bp_admin');

    $key = sanitize_text_field(wp_unslash($_POST['bp_license_key'] ?? ''));
    BP_LicenseHelper::set_key($key);

    wp_safe_redirect(admin_url('admin.php?page=bp_settings&tab=license&saved=1'));
    exit;
  }

  public function validate_license(): void {
    $this->require_cap('bp_manage_settings');
    check_admin_referer('bp_admin');

    BP_LicenseHelper::validate(true);

    wp_safe_redirect(admin_url('admin.php?page=bp_settings&tab=license&validated=1'));
    exit;
  }

  public function export_json(): void {
    $this->require_cap('bp_manage_settings');
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

  public function import_json(): void {
    $this->require_cap('bp_manage_settings');
    check_admin_referer('bp_admin');

    if (empty($_FILES['bp_settings_file']['tmp_name'])) {
      wp_safe_redirect(admin_url('admin.php?page=bp_settings&tab=import_export&import=missing'));
      exit;
    }

    $raw = file_get_contents($_FILES['bp_settings_file']['tmp_name']);
    $data = json_decode($raw, true);

    if (!is_array($data) || ($data['plugin'] ?? '') !== 'bookpoint') {
      wp_safe_redirect(admin_url('admin.php?page=bp_settings&tab=import_export&import=badfile'));
      exit;
    }

    $settings = $data['bp_settings'] ?? null;
    if (!is_array($settings)) {
      wp_safe_redirect(admin_url('admin.php?page=bp_settings&tab=import_export&import=badsettings'));
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

    if (class_exists('BP_AuditHelper')) {
      BP_AuditHelper::log('settings_imported', ['actor_type' => 'admin']);
    }

    wp_safe_redirect(admin_url('admin.php?page=bp_settings&tab=import_export&import=ok'));
    exit;
  }

  public function license_save() : void {
    $this->require_cap('bp_manage_settings');
    check_admin_referer('bp_admin');

    $key = sanitize_text_field($_POST['bp_license_key'] ?? '');
    BP_LicenseHelper::set_key($key);

    wp_safe_redirect(admin_url('admin.php?page=bp_settings&license=saved'));
    exit;
  }

  public function license_validate() : void {
    $this->require_cap('bp_manage_settings');
    check_admin_referer('bp_admin');

    BP_LicenseHelper::validate(true);

    wp_safe_redirect(admin_url('admin.php?page=bp_settings&license=checked'));
    exit;
  }
}
