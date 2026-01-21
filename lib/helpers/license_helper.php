<?php
defined('ABSPATH') || exit;

final class BP_LicenseHelper {

  const API_VALIDATE = 'https://YOUR-LICENSE-SERVER.COM/wp-json/bookpoint/v1/validate';
  const API_UPDATES  = 'https://YOUR-LICENSE-SERVER.COM/wp-json/bookpoint/v1/updates';

  public static function get_key() : string {
    return (string) get_option('bp_license_key', '');
  }

  public static function status() : string {
    return (string) get_option('bp_license_status', 'unset');
  }

  public static function is_valid() : bool {
    return self::status() === 'valid';
  }

  public static function set_key(string $key) : void {
    update_option('bp_license_key', trim($key), false);
    update_option('bp_license_status', 'unset', false);
    delete_transient('bp_license_check_cache');
  }

  public static function validate(bool $force = false) : array {
    $key = self::get_key();
    if ($key === '') {
      update_option('bp_license_status', 'unset', false);
      return ['ok' => false, 'status' => 'unset', 'message' => 'No key'];
    }

    if (!$force) {
      $cached = get_transient('bp_license_check_cache');
      if (is_array($cached)) return $cached;
    }

    $payload = [
      'license_key' => $key,
      'site' => home_url(),
      'plugin' => 'bookpoint',
      'version' => defined('BP_Plugin::VERSION') ? BP_Plugin::VERSION : 'unknown',
    ];

    $res = wp_remote_post(self::API_VALIDATE, [
      'timeout' => 12,
      'headers' => ['Content-Type' => 'application/json'],
      'body' => wp_json_encode($payload),
    ]);

    if (is_wp_error($res)) {
      update_option('bp_license_last_error', $res->get_error_message(), false);
      $out = ['ok' => false, 'status' => self::status(), 'message' => $res->get_error_message()];
      set_transient('bp_license_check_cache', $out, 10 * MINUTE_IN_SECONDS);
      return $out;
    }

    $code = (int) wp_remote_retrieve_response_code($res);
    $body = wp_remote_retrieve_body($res);
    $data = json_decode($body, true);

    if ($code !== 200 || !is_array($data)) {
      update_option('bp_license_last_error', 'Bad response', false);
      $out = ['ok' => false, 'status' => self::status(), 'message' => 'Bad response'];
      set_transient('bp_license_check_cache', $out, 10 * MINUTE_IN_SECONDS);
      return $out;
    }

    $status = sanitize_text_field($data['status'] ?? 'invalid');
    $message = sanitize_text_field($data['message'] ?? '');

    update_option('bp_license_status', $status, false);
    update_option('bp_license_checked_at', time(), false);
    update_option('bp_license_last_error', $message, false);

    $out = ['ok' => ($status === 'valid'), 'status' => $status, 'message' => $message, 'raw' => $data];
    set_transient('bp_license_check_cache', $out, 6 * HOUR_IN_SECONDS);

    return $out;
  }

  public static function maybe_cron_validate() : void {
    self::validate(false);
  }
}
