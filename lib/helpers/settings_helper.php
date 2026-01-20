<?php
defined('ABSPATH') || exit;

final class BP_SettingsHelper {

  public static function table() : string {
    global $wpdb;
    return $wpdb->prefix . 'bp_settings';
  }

  public static function get(string $key, $default = null) {
    global $wpdb;
    $table = self::table();

    $key = sanitize_key($key);
    if ($key === '') return $default;

    $val = $wpdb->get_var($wpdb->prepare("SELECT setting_value FROM {$table} WHERE setting_key = %s LIMIT 1", $key));
    if ($val === null) return $default;

    // store as plain string; allow json for arrays later
    return maybe_unserialize($val);
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
      'bp_email_enabled' => 1,
      'bp_admin_email' => get_option('admin_email'),
      'bp_email_from_name' => get_bloginfo('name'),
      'bp_email_from_email' => get_option('admin_email'),
      'bp_future_days_limit' => 60,
      'bp_schedule_0' => '',
      'bp_schedule_1' => '09:00-17:00',
      'bp_schedule_2' => '09:00-17:00',
      'bp_schedule_3' => '09:00-17:00',
      'bp_schedule_4' => '09:00-17:00',
      'bp_schedule_5' => '09:00-17:00',
      'bp_schedule_6' => '',
      'bp_breaks' => '12:00-13:00',
    ];
  }

  public static function get_with_default(string $key) {
    $defaults = self::defaults();
    return self::get($key, $defaults[$key] ?? null);
  }
}
