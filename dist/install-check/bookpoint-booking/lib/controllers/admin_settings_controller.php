<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_AdminSettingsController extends POINTLYBOOKING_Controller {

  public function index(array $errors = []) : void {
    $this->require_cap('pointlybooking_manage_settings');

    $open = POINTLYBOOKING_SettingsHelper::get_with_default('pointlybooking_open_time');
    $close = POINTLYBOOKING_SettingsHelper::get_with_default('pointlybooking_close_time');
    $interval = (int)POINTLYBOOKING_SettingsHelper::get_with_default('pointlybooking_slot_interval_minutes');
    $currency = POINTLYBOOKING_SettingsHelper::get_with_default('pointlybooking_default_currency');
    $currency_position = POINTLYBOOKING_SettingsHelper::get_with_default('pointlybooking_currency_position');
    $email_enabled = (int)POINTLYBOOKING_SettingsHelper::get_with_default('pointlybooking_email_enabled');
    $admin_email = POINTLYBOOKING_SettingsHelper::get_with_default('pointlybooking_admin_email');
    $from_name = POINTLYBOOKING_SettingsHelper::get_with_default('pointlybooking_email_from_name');
    $from_email = POINTLYBOOKING_SettingsHelper::get_with_default('pointlybooking_email_from_email');
    $remove_data_on_uninstall = (int) get_option('pointlybooking_remove_data_on_uninstall', 0);
    $webhooks_enabled = (int)POINTLYBOOKING_SettingsHelper::get_with_default('webhooks_enabled');
    $webhooks_secret = POINTLYBOOKING_SettingsHelper::get_with_default('webhooks_secret');
    $webhooks_url_booking_created = POINTLYBOOKING_SettingsHelper::get_with_default('webhooks_url_booking_created');
    $webhooks_url_booking_status_changed = POINTLYBOOKING_SettingsHelper::get_with_default('webhooks_url_booking_status_changed');
    $webhooks_url_booking_updated = POINTLYBOOKING_SettingsHelper::get_with_default('webhooks_url_booking_updated');
    $webhooks_url_booking_cancelled = POINTLYBOOKING_SettingsHelper::get_with_default('webhooks_url_booking_cancelled');
    
    // Step 14: Schedule & Availability
    $future_days_limit = (int)POINTLYBOOKING_SettingsHelper::get_with_default('pointlybooking_future_days_limit');
    $breaks = POINTLYBOOKING_SettingsHelper::get_with_default('pointlybooking_breaks');
    $schedule = [];
    for ($i = 0; $i < 7; $i++) {
      $schedule[$i] = POINTLYBOOKING_SettingsHelper::get_with_default('pointlybooking_schedule_' . $i);
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
    $this->require_cap('pointlybooking_manage_settings');

    check_admin_referer('pointlybooking_admin');

    $tab = sanitize_text_field(wp_unslash($_POST['tab'] ?? 'general'));
    $errors = [];

    if ($tab === 'general') {
      $open = sanitize_text_field(wp_unslash($_POST['open_time'] ?? '09:00'));
      $close = sanitize_text_field(wp_unslash($_POST['close_time'] ?? '17:00'));
      $interval = absint(wp_unslash($_POST['slot_interval_minutes'] ?? 15));
      $currency = strtoupper(sanitize_key(wp_unslash($_POST['default_currency'] ?? 'usd')));
      $currency_position = sanitize_text_field(wp_unslash($_POST['currency_position'] ?? 'before'));
      $remove_data_on_uninstall = isset($_POST['pointlybooking_remove_data_on_uninstall']) ? 1 : 0;

      $future_days_limit = absint(wp_unslash($_POST['future_days_limit'] ?? 60));
      $breaks = sanitize_text_field(wp_unslash($_POST['breaks'] ?? '12:00-13:00'));
      $schedule = [];
      for ($i = 0; $i < 7; $i++) {
        $schedule[$i] = sanitize_text_field(wp_unslash($_POST['schedule_' . $i] ?? ''));
      }

      if (!preg_match('/^\d{2}:\d{2}$/', $open)) {
        $errors['open_time'] = __('Open time must be HH:MM', 'bookpoint-booking');
      }
      if (!preg_match('/^\d{2}:\d{2}$/', $close)) {
        $errors['close_time'] = __('Close time must be HH:MM', 'bookpoint-booking');
      }
      if ($interval < 5 || $interval > 120) {
        $errors['slot_interval_minutes'] = __('Slot interval must be between 5 and 120 minutes.', 'bookpoint-booking');
      }
      if (!preg_match('/^[A-Z]{3}$/', $currency)) {
        $errors['default_currency'] = __('Currency must be a 3-letter code.', 'bookpoint-booking');
      }
      if (!in_array($currency_position, ['before','after'], true)) {
        $errors['currency_position'] = __('Currency position must be before or after.', 'bookpoint-booking');
      }

      if ($future_days_limit < 1 || $future_days_limit > 365) {
        $errors['future_days_limit'] = __('Future days limit must be between 1 and 365.', 'bookpoint-booking');
      }

      for ($i = 0; $i < 7; $i++) {
        if (!empty($schedule[$i]) && !preg_match('/^\d{2}:\d{2}-\d{2}:\d{2}$/', $schedule[$i])) {
          $errors['schedule_' . $i] = __('Schedule must be empty or HH:MM-HH:MM format', 'bookpoint-booking');
        }
      }

      if (!empty($breaks)) {
        $break_ranges = explode(',', $breaks);
        foreach ($break_ranges as $br) {
          $br = trim($br);
          if (!empty($br) && !preg_match('/^\d{2}:\d{2}-\d{2}:\d{2}$/', $br)) {
            $errors['breaks'] = __('Each break must be HH:MM-HH:MM format, comma-separated', 'bookpoint-booking');
            break;
          }
        }
      }

      if (!empty($errors)) {
        $this->index($errors);
        return;
      }

      POINTLYBOOKING_SettingsHelper::set('pointlybooking_open_time', $open);
      POINTLYBOOKING_SettingsHelper::set('pointlybooking_close_time', $close);
      POINTLYBOOKING_SettingsHelper::set('pointlybooking_slot_interval_minutes', $interval);
      POINTLYBOOKING_SettingsHelper::set('pointlybooking_default_currency', $currency);
      POINTLYBOOKING_SettingsHelper::set('pointlybooking_currency_position', $currency_position);

      POINTLYBOOKING_SettingsHelper::set('pointlybooking_future_days_limit', $future_days_limit);
      POINTLYBOOKING_SettingsHelper::set('pointlybooking_breaks', $breaks);
      for ($i = 0; $i < 7; $i++) {
        POINTLYBOOKING_SettingsHelper::set('pointlybooking_schedule_' . $i, $schedule[$i]);
      }

      update_option('pointlybooking_remove_data_on_uninstall', $remove_data_on_uninstall, false);
    }

    if ($tab === 'emails') {
      $email_enabled = isset($_POST['email_enabled']) ? 1 : 0;
      $admin_email = sanitize_email(wp_unslash($_POST['admin_email'] ?? get_option('admin_email')));
      $from_name = sanitize_text_field(wp_unslash($_POST['from_name'] ?? get_bloginfo('name')));
      $from_email = sanitize_email(wp_unslash($_POST['from_email'] ?? get_option('admin_email')));

      if ($admin_email === '') {
        $errors['admin_email'] = __('Admin email is invalid.', 'bookpoint-booking');
      }
      if ($from_email === '') {
        $errors['from_email'] = __('From email is invalid.', 'bookpoint-booking');
      }

      if (!empty($errors)) {
        $this->index($errors);
        return;
      }

      POINTLYBOOKING_SettingsHelper::set('pointlybooking_email_enabled', $email_enabled);
      POINTLYBOOKING_SettingsHelper::set('pointlybooking_admin_email', $admin_email);
      POINTLYBOOKING_SettingsHelper::set('pointlybooking_email_from_name', $from_name);
      POINTLYBOOKING_SettingsHelper::set('pointlybooking_email_from_email', $from_email);
    }

    if ($tab === 'webhooks') {
      $webhooks_enabled = isset($_POST['webhooks_enabled']) ? 1 : 0;
      $webhooks_secret = sanitize_text_field(wp_unslash($_POST['webhooks_secret'] ?? ''));
      $webhooks_url_booking_created = esc_url_raw(wp_unslash($_POST['webhooks_url_booking_created'] ?? ''));
      $webhooks_url_booking_status_changed = esc_url_raw(wp_unslash($_POST['webhooks_url_booking_status_changed'] ?? ''));
      $webhooks_url_booking_updated = esc_url_raw(wp_unslash($_POST['webhooks_url_booking_updated'] ?? ''));
      $webhooks_url_booking_cancelled = esc_url_raw(wp_unslash($_POST['webhooks_url_booking_cancelled'] ?? ''));

      POINTLYBOOKING_SettingsHelper::set('webhooks_enabled', $webhooks_enabled);
      POINTLYBOOKING_SettingsHelper::set('webhooks_secret', $webhooks_secret);
      POINTLYBOOKING_SettingsHelper::set('webhooks_url_booking_created', $webhooks_url_booking_created);
      POINTLYBOOKING_SettingsHelper::set('webhooks_url_booking_status_changed', $webhooks_url_booking_status_changed);
      POINTLYBOOKING_SettingsHelper::set('webhooks_url_booking_updated', $webhooks_url_booking_updated);
      POINTLYBOOKING_SettingsHelper::set('webhooks_url_booking_cancelled', $webhooks_url_booking_cancelled);
    }

    $tab = sanitize_text_field(wp_unslash($_POST['tab'] ?? 'general'));
    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_settings&tab=' . $tab . '&updated=1'));
    exit;
  }

  public function export_json(): void {
    $this->require_cap('pointlybooking_manage_settings');
    check_admin_referer('pointlybooking_admin');

    global $wpdb;
    $table = pointlybooking_table('settings');
    $rows = $wpdb->get_results(
      pointlybooking_prepare_query_with_identifiers(
        "SELECT setting_key, setting_value FROM %i",
        [$table]
      ),
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

  public function import_json(): void {
    $this->require_cap('pointlybooking_manage_settings');
    check_admin_referer('pointlybooking_admin');

    $import_redirect = static function (string $state): string {
      $url = admin_url('admin.php?page=pointlybooking_settings&tab=import_export&import=' . rawurlencode($state));
      return wp_nonce_url($url, 'pointlybooking_import_result', 'pointlybooking_import_nonce');
    };

    $raw = pointlybooking_get_uploaded_file_contents('pointlybooking_settings_file', ['json'], 5 * MB_IN_BYTES);
    if ($raw === null) {
      wp_safe_redirect($import_redirect('missing'));
      exit;
    }

    $data = json_decode($raw, true);

    $plugin_id = is_array($data) ? (string)($data['plugin'] ?? '') : '';
    if (!is_array($data) || !in_array($plugin_id, ['bookpoint-booking', 'pointly-booking', 'bookpoint'], true)) {
      wp_safe_redirect($import_redirect('badfile'));
      exit;
    }

    $settings = $data['pointlybooking_settings'] ?? null;
    if (!is_array($settings)) {
      wp_safe_redirect($import_redirect('badsettings'));
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

    if (class_exists('POINTLYBOOKING_AuditHelper')) {
      POINTLYBOOKING_AuditHelper::log('settings_imported', ['actor_type' => 'admin']);
    }

    wp_safe_redirect($import_redirect('ok'));
    exit;
  }

}
