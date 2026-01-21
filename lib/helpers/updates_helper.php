<?php
defined('ABSPATH') || exit;

final class BP_UpdatesHelper {

  public static function init() : void {
    add_filter('pre_set_site_transient_update_plugins', [__CLASS__, 'check_updates']);
    add_filter('plugins_api', [__CLASS__, 'plugin_info'], 20, 3);
  }

  private static function plugin_basename() : string {
    return plugin_basename(BP_PLUGIN_FILE);
  }

  public static function check_updates($transient) {
    if (!is_object($transient) || empty($transient->checked)) return $transient;

    $current = defined('BP_Plugin::VERSION') ? BP_Plugin::VERSION : '0.0.0';
    $key = BP_LicenseHelper::get_key();

    $res = wp_remote_get(BP_LicenseHelper::API_UPDATES . '?' . http_build_query([
      'plugin' => 'bookpoint',
      'version' => $current,
      'site' => home_url(),
      'license_key' => $key,
    ]), ['timeout' => 12]);

    if (is_wp_error($res)) return $transient;

    $data = json_decode(wp_remote_retrieve_body($res), true);
    if (!is_array($data)) return $transient;

    $new = (string)($data['new_version'] ?? '');
    $pkg = (string)($data['package'] ?? '');

    if ($new && version_compare($new, $current, '>') && $pkg) {
      $obj = (object)[
        'slug' => 'bookpoint-v5',
        'plugin' => self::plugin_basename(),
        'new_version' => $new,
        'package' => $pkg,
        'tested' => (string)($data['tested'] ?? ''),
        'requires' => (string)($data['requires'] ?? ''),
      ];
      $transient->response[self::plugin_basename()] = $obj;
    }

    return $transient;
  }

  public static function plugin_info($false, $action, $args) {
    if ($action !== 'plugin_information') return $false;
    if (empty($args->slug) || $args->slug !== 'bookpoint-v5') return $false;

    $current = defined('BP_Plugin::VERSION') ? BP_Plugin::VERSION : '0.0.0';
    $key = BP_LicenseHelper::get_key();

    $res = wp_remote_get(BP_LicenseHelper::API_UPDATES . '?' . http_build_query([
      'info' => 1,
      'plugin' => 'bookpoint',
      'version' => $current,
      'site' => home_url(),
      'license_key' => $key,
    ]), ['timeout' => 12]);

    if (is_wp_error($res)) return $false;
    $data = json_decode(wp_remote_retrieve_body($res), true);
    if (!is_array($data)) return $false;

    return (object)[
      'name' => 'BookPoint',
      'slug' => 'bookpoint-v5',
      'version' => (string)($data['new_version'] ?? $current),
      'author' => 'Your Company',
      'homepage' => (string)($data['homepage'] ?? ''),
      'sections' => (array)($data['sections'] ?? ['description' => '']),
      'download_link' => (string)($data['package'] ?? ''),
      'requires' => (string)($data['requires'] ?? ''),
      'tested' => (string)($data['tested'] ?? ''),
    ];
  }
}
