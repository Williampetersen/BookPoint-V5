<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_DatabaseHelper {

  const DB_VERSION_OPTION = 'pointlybooking_db_version';

  public static function install_or_update(string $target_version) : void {
    $installed_version = get_option(self::DB_VERSION_OPTION, '');

    $needs_migration = ($installed_version !== $target_version) || self::tables_missing();

    if ($needs_migration) {
      POINTLYBOOKING_MigrationsHelper::create_tables();
      update_option(self::DB_VERSION_OPTION, $target_version, false);
    }
  }

  private static function tables_missing() : bool {
    global $wpdb;

    $tables = [
      $wpdb->prefix . 'pointlybooking_services',
      $wpdb->prefix . 'pointlybooking_agents',
      $wpdb->prefix . 'pointlybooking_bookings',
      $wpdb->prefix . 'pointlybooking_customers',
      $wpdb->prefix . 'pointlybooking_settings',
      $wpdb->prefix . 'pointlybooking_form_fields',
      $wpdb->prefix . 'pointlybooking_field_values',
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

