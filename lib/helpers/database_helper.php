<?php
defined('ABSPATH') || exit;

final class BP_DatabaseHelper {

  const DB_VERSION_OPTION = 'BP_db_version';

  public static function install_or_update(string $target_version) : void {
    $installed_version = get_option(self::DB_VERSION_OPTION, '');

    $needs_migration = ($installed_version !== $target_version) || self::tables_missing();

    if ($needs_migration) {
      BP_MigrationsHelper::create_tables();
      update_option(self::DB_VERSION_OPTION, $target_version, false);
    }
  }

  private static function tables_missing() : bool {
    global $wpdb;

    $tables = [
      $wpdb->prefix . 'bp_services',
      $wpdb->prefix . 'bp_agents',
      $wpdb->prefix . 'bp_bookings',
      $wpdb->prefix . 'bp_customers',
      $wpdb->prefix . 'bp_settings',
      $wpdb->prefix . 'bp_form_fields',
      $wpdb->prefix . 'bp_field_values',
    ];

    foreach ($tables as $table) {
      $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
      if ($found !== $table) {
        return true;
      }
    }

    return false;
  }
}

