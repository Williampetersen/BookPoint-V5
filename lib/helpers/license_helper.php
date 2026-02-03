<?php
defined('ABSPATH') || exit;

final class BP_LicenseHelper {

  // Default license server used in your distributed plugin build.
  // Override for development by defining BP_LICENSE_SERVER_BASE in wp-config.php
  // or using the `bp_license_server_base_url` filter.
  const API_BASE_DEFAULT = 'https://wpbookpoint.com';

  public static function api_base() : string {
    $base = self::API_BASE_DEFAULT;
    if (defined('BP_LICENSE_SERVER_BASE') && is_string(BP_LICENSE_SERVER_BASE) && BP_LICENSE_SERVER_BASE !== '') {
      $base = BP_LICENSE_SERVER_BASE;
    }
    $base = apply_filters('bp_license_server_base_url', $base);
    $base = untrailingslashit((string) $base);

    // If the license server plugin is installed on the same site, auto-target it.
    // This avoids the default placeholder host causing confusing "could not resolve host" errors.
    if ($base === untrailingslashit(self::API_BASE_DEFAULT) && class_exists('BP_License_Server')) {
      $base = untrailingslashit(home_url());
    }

    return $base;
  }

  public static function api_validate_url() : string {
    return self::api_base() . '/wp-json/bookpoint/v1/validate';
  }

  public static function api_deactivate_url() : string {
    return self::api_base() . '/wp-json/bookpoint/v1/deactivate';
  }

  public static function api_updates_url() : string {
    return self::api_base() . '/wp-json/bookpoint/v1/updates';
  }

  public static function get_key() : string {
    return (string) get_option('bp_license_key', '');
  }

  public static function get_instance_id() : string {
    return (string) get_option('bp_license_instance_id', '');
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
    update_option('bp_license_instance_id', '', false);
    update_option('bp_license_licensed_domain', '', false);
    update_option('bp_license_expires_at', '', false);
    update_option('bp_license_plan', '', false);
    delete_transient('bp_license_check_cache');
  }

  public static function activate() : array {
    // Server-side: /validate performs activation when a slot is available.
    return self::validate(true);
  }

  public static function deactivate() : array {
    $key = self::get_key();
    if ($key === '') {
      update_option('bp_license_status', 'unset', false);
      return ['ok' => false, 'status' => 'unset', 'message' => 'No key'];
    }

    $payload = [
      'license_key' => $key,
      'site' => home_url(),
      'plugin' => 'bookpoint',
      'version' => defined('BP_Plugin::VERSION') ? BP_Plugin::VERSION : 'unknown',
      'instance_id' => self::get_instance_id(),
    ];

    $res = wp_remote_post(self::api_deactivate_url(), [
      'timeout' => 12,
      'headers' => ['Content-Type' => 'application/json'],
      'body' => wp_json_encode($payload),
    ]);

    if (is_wp_error($res)) {
      $out = ['ok' => false, 'status' => self::status(), 'message' => $res->get_error_message()];
      return $out;
    }

    $code = (int) wp_remote_retrieve_response_code($res);
    $body = wp_remote_retrieve_body($res);
    $data = json_decode($body, true);
    if ($code !== 200 || !is_array($data)) {
      return ['ok' => false, 'status' => self::status(), 'message' => 'Bad response'];
    }

    // Local state: mark unset so user must activate again.
    update_option('bp_license_status', 'unset', false);
    delete_transient('bp_license_check_cache');

    return [
      'ok' => (bool)($data['ok'] ?? false),
      'status' => sanitize_text_field((string)($data['status'] ?? 'deactivated')),
      'message' => sanitize_text_field((string)($data['message'] ?? '')),
      'raw' => $data,
    ];
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

    $validate_url = self::api_validate_url();
    update_option('bp_license_last_request_url', $validate_url, false);
    update_option('bp_license_last_error', '', false);

    $payload = [
      'license_key' => $key,
      'site' => home_url(),
      'plugin' => 'bookpoint',
      'version' => defined('BP_Plugin::VERSION') ? BP_Plugin::VERSION : 'unknown',
      'instance_id' => self::get_instance_id(),
    ];

    $res = wp_remote_post($validate_url, [
      'timeout' => 12,
      'headers' => ['Content-Type' => 'application/json'],
      'body' => wp_json_encode($payload),
    ]);

    if (is_wp_error($res)) {
      $msg = $res->get_error_message();
      update_option('bp_license_last_error', $msg, false);
      $out = [
        'ok' => false,
        'status' => self::status(),
        'message' => $msg,
        'debug' => [
          'validate_url' => $validate_url,
          'api_base' => self::api_base(),
          'site' => home_url(),
        ],
      ];
      update_option('bp_license_data_json', wp_json_encode($out), false);
      set_transient('bp_license_check_cache', $out, 10 * MINUTE_IN_SECONDS);
      return $out;
    }

    $code = (int) wp_remote_retrieve_response_code($res);
    $body = wp_remote_retrieve_body($res);
    $data = json_decode($body, true);

    if ($code !== 200 || !is_array($data)) {
      update_option('bp_license_last_error', 'Bad response', false);
      $out = [
        'ok' => false,
        'status' => self::status(),
        'message' => 'Bad response',
        'debug' => [
          'validate_url' => $validate_url,
          'http_code' => $code,
        ],
      ];
      update_option('bp_license_data_json', wp_json_encode($out), false);
      set_transient('bp_license_check_cache', $out, 10 * MINUTE_IN_SECONDS);
      return $out;
    }

    $status = sanitize_text_field($data['status'] ?? 'invalid');
    $message = sanitize_text_field($data['message'] ?? '');

    update_option('bp_license_status', $status, false);
    update_option('bp_license_checked_at', time(), false);
    update_option('bp_license_last_error', $message, false);
    update_option('bp_license_data_json', wp_json_encode($data), false);

    // Optional metadata if the license server provides it.
    $plan = sanitize_text_field($data['plan'] ?? '');
    $expires_at = sanitize_text_field($data['expires_at'] ?? ($data['expires'] ?? ''));
    $licensed_domain = sanitize_text_field($data['licensed_domain'] ?? ($data['domain'] ?? ''));
    $instance_id = sanitize_text_field($data['instance_id'] ?? '');
    update_option('bp_license_plan', $plan, false);
    update_option('bp_license_expires_at', $expires_at, false);
    update_option('bp_license_licensed_domain', $licensed_domain, false);
    update_option('bp_license_instance_id', $instance_id, false);

    $out = ['ok' => ($status === 'valid'), 'status' => $status, 'message' => $message, 'raw' => $data];
    set_transient('bp_license_check_cache', $out, 6 * HOUR_IN_SECONDS);

    return $out;
  }

  public static function maybe_cron_validate() : void {
    self::validate(false);
  }
}
