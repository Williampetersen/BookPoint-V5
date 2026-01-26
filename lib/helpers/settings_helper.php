<?php
defined('ABSPATH') || exit;

final class BP_SettingsHelper {

  const OPTION_KEY = 'bp_settings';

  private static function get_option_all(): array {
    $s = get_option(self::OPTION_KEY, []);
    return is_array($s) ? $s : [];
  }

  private static function get_legacy(string $key, $default = null) {
    global $wpdb;
    $table = self::table();

    $key = sanitize_key($key);
    if ($key === '') return $default;

    $val = $wpdb->get_var($wpdb->prepare("SELECT setting_value FROM {$table} WHERE setting_key = %s LIMIT 1", $key));
    if ($val === null) return $default;

    return maybe_unserialize($val);
  }

  public static function table() : string {
    global $wpdb;
    return $wpdb->prefix . 'bp_settings';
  }

  public static function get(string $key, $default = null) {
    $key = sanitize_key($key);
    if ($key === '') return $default;

    $s = self::get_option_all();
    if (array_key_exists($key, $s)) return $s[$key];

    $legacy_map = [
      'slot_interval_minutes' => 'bp_slot_interval_minutes',
      'currency' => 'bp_default_currency',
      'currency_position' => 'bp_currency_position',
    ];

    if (isset($legacy_map[$key])) {
      return self::get_legacy($legacy_map[$key], $default);
    }

    return self::get_legacy($key, $default);
  }

  public static function get_all(): array {
    $s = self::get_option_all();

    if (!array_key_exists('slot_interval_minutes', $s)) {
      $s['slot_interval_minutes'] = (int)self::get_legacy('bp_slot_interval_minutes', 15);
    }
    if (!array_key_exists('currency', $s)) {
      $s['currency'] = (string)self::get_legacy('bp_default_currency', 'USD');
    }
    if (!array_key_exists('currency_position', $s)) {
      $s['currency_position'] = (string)self::get_legacy('bp_currency_position', 'before');
    }

    return $s;
  }

  public static function set_all($settings): void {
    if (!is_array($settings)) $settings = [];
    update_option(self::OPTION_KEY, $settings, false);
  }

  public static function merge($updates): array {
    $s = self::get_option_all();
    $merged = array_merge($s, is_array($updates) ? $updates : []);
    update_option(self::OPTION_KEY, $merged, false);

    if (isset($merged['slot_interval_minutes'])) {
      self::set('bp_slot_interval_minutes', (int)$merged['slot_interval_minutes']);
    }
    if (isset($merged['currency'])) {
      self::set('bp_default_currency', $merged['currency']);
    }
    if (isset($merged['currency_position'])) {
      self::set('bp_currency_position', $merged['currency_position']);
    }

    return $merged;
  }

  public static function set(string $key, $value) : bool {
    global $wpdb;
    $table = self::table();

    $key = sanitize_key($key);
    if ($key === '') return false;

    $value = maybe_serialize($value);
    $now = current_time('mysql');

    // Upsert
    $exists = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE setting_key = %s", $key));
    if ($exists > 0) {
      $updated = $wpdb->update($table, [
        'setting_value' => $value,
        'updated_at' => $now,
      ], [
        'setting_key' => $key,
      ], ['%s','%s'], ['%s']);
      return ($updated !== false);
    }

    $inserted = $wpdb->insert($table, [
      'setting_key' => $key,
      'setting_value' => $value,
      'updated_at' => $now,
    ], ['%s','%s','%s']);

    return ($inserted !== false);
  }

  public static function defaults() : array {
    return [
      'bp_open_time' => '09:00',
      'bp_close_time' => '17:00',
      'bp_slot_interval_minutes' => 15,
      'bp_default_currency' => 'USD',
      'bp_currency_position' => 'before',
      'payments_enabled_methods' => ['cash','woocommerce','stripe','paypal','free'],
      'payments_default_method' => 'cash',
      'payments_require_payment_to_confirm' => 1,
      'payments_wc_product_id' => '',
      'payments_stripe_mode' => 'checkout',
      'payments_stripe_publishable_key' => '',
      'payments_stripe_test_secret_key' => '',
      'payments_stripe_live_secret_key' => '',
      'payments_stripe_webhook_secret' => '',
      'payments_stripe_success_url' => '',
      'payments_stripe_cancel_url' => '',
      'payments_paypal_client_id' => '',
      'payments_paypal_secret' => '',
      'payments_paypal_mode' => 'test',
      'payments_paypal_return_url' => '',
      'payments_paypal_cancel_url' => '',
      'bp_email_enabled' => 1,
      'bp_admin_email' => get_option('admin_email'),
      'bp_email_from_name' => get_bloginfo('name'),
      'bp_email_from_email' => get_option('admin_email'),
      'bp_future_days_limit' => 60,
      'bp_default_booking_status' => 'pending',
      'bp_schedule_0' => '',
      'bp_schedule_1' => '09:00-17:00',
      'bp_schedule_2' => '09:00-17:00',
      'bp_schedule_3' => '09:00-17:00',
      'bp_schedule_4' => '09:00-17:00',
      'bp_schedule_5' => '09:00-17:00',
      'bp_schedule_6' => '',
      'bp_breaks' => '12:00-13:00',
      'webhooks_enabled' => 0,
      'webhooks_secret' => '',
      'webhooks_url_booking_created' => '',
      'webhooks_url_booking_status_changed' => '',
      'webhooks_url_booking_updated' => '',
      'webhooks_url_booking_cancelled' => '',
    ];
  }

  public static function get_with_default(string $key) {
    $defaults = self::defaults();
    return self::get($key, $defaults[$key] ?? null);
  }
}
