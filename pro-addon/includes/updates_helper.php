<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_UpdatesHelper {

  public static function init() : void {
    // Disabled in the WordPress.org build.
  }

  private static function plugin_basename() : string {
    return plugin_basename(POINTLYBOOKING_PLUGIN_FILE);
  }

  public static function check_updates($transient) {
    return $transient;
  }

  public static function plugin_info($false, $action, $args) {
    return $false;
  }
}
