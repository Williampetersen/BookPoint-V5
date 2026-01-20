<?php
defined('ABSPATH') || exit;

final class BP_AdminSettingsController extends BP_Controller {

  public function index(array $errors = []) : void {
    $this->require_cap('bp_manage_settings');

    $open = BP_SettingsHelper::get_with_default('bp_open_time');
    $close = BP_SettingsHelper::get_with_default('bp_close_time');
    $interval = (int)BP_SettingsHelper::get_with_default('bp_slot_interval_minutes');
    $currency = BP_SettingsHelper::get_with_default('bp_default_currency');
    $email_enabled = (int)BP_SettingsHelper::get_with_default('bp_email_enabled');
    $admin_email = BP_SettingsHelper::get_with_default('bp_admin_email');
    $from_name = BP_SettingsHelper::get_with_default('bp_email_from_name');
    $from_email = BP_SettingsHelper::get_with_default('bp_email_from_email');
    
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
      'email_enabled' => $email_enabled,
      'admin_email' => $admin_email,
      'from_name' => $from_name,
      'from_email' => $from_email,
      'future_days_limit' => $future_days_limit,
      'breaks' => $breaks,
      'schedule' => $schedule,
      'errors' => $errors,
    ]);
  }

  public function save() : void {
    $this->require_cap('bp_manage_settings');

    check_admin_referer('bp_admin');

    $open = sanitize_text_field($_POST['open_time'] ?? '09:00');
    $close = sanitize_text_field($_POST['close_time'] ?? '17:00');
    $interval = absint($_POST['slot_interval_minutes'] ?? 15);
    $currency = strtoupper(sanitize_key($_POST['default_currency'] ?? 'usd'));
    $email_enabled = isset($_POST['email_enabled']) ? 1 : 0;
    $admin_email = sanitize_email($_POST['admin_email'] ?? get_option('admin_email'));
    $from_name = sanitize_text_field($_POST['from_name'] ?? get_bloginfo('name'));
    $from_email = sanitize_email($_POST['from_email'] ?? get_option('admin_email'));
    
    // Step 14: Schedule & Availability
    $future_days_limit = absint($_POST['future_days_limit'] ?? 60);
    $breaks = sanitize_text_field($_POST['breaks'] ?? '12:00-13:00');
    $schedule = [];
    for ($i = 0; $i < 7; $i++) {
      $schedule[$i] = sanitize_text_field($_POST['schedule_' . $i] ?? '');
    }

    $errors = [];

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
    if ($admin_email === '') {
      $errors['admin_email'] = __('Admin email is invalid.', 'bookpoint');
    }
    if ($from_email === '') {
      $errors['from_email'] = __('From email is invalid.', 'bookpoint');
    }
    
    // Step 14: Validate future_days_limit
    if ($future_days_limit < 1 || $future_days_limit > 365) {
      $errors['future_days_limit'] = __('Future days limit must be between 1 and 365.', 'bookpoint');
    }
    
    // Step 14: Validate schedule format (each day can be empty or HH:MM-HH:MM)
    for ($i = 0; $i < 7; $i++) {
      if (!empty($schedule[$i]) && !preg_match('/^\d{2}:\d{2}-\d{2}:\d{2}$/', $schedule[$i])) {
        $errors['schedule_' . $i] = __('Schedule must be empty or HH:MM-HH:MM format', 'bookpoint');
      }
    }
    
    // Step 14: Validate breaks format (comma-separated HH:MM-HH:MM)
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
    
    BP_SettingsHelper::set('bp_email_enabled', $email_enabled);
    BP_SettingsHelper::set('bp_admin_email', $admin_email);
    BP_SettingsHelper::set('bp_email_from_name', $from_name);
    BP_SettingsHelper::set('bp_email_from_email', $from_email);

    if (!empty($errors)) {
      $this->index($errors);
      return;
    }

    BP_SettingsHelper::set('bp_open_time', $open);
    BP_SettingsHelper::set('bp_close_time', $close);
    BP_SettingsHelper::set('bp_slot_interval_minutes', $interval);
    BP_SettingsHelper::set('bp_default_currency', $currency);
    
    // Step 14: Save schedule settings
    BP_SettingsHelper::set('bp_future_days_limit', $future_days_limit);
    BP_SettingsHelper::set('bp_breaks', $breaks);
    for ($i = 0; $i < 7; $i++) {
      BP_SettingsHelper::set('bp_schedule_' . $i, $schedule[$i]);
    }

    wp_safe_redirect(admin_url('admin.php?page=bp_settings&updated=1'));
    exit;
  }
}
