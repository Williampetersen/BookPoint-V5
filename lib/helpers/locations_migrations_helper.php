<?php
defined('ABSPATH') || exit;
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- This file's wpdb SQL paths interpolate only hardcoded plugin table names with a sanitized WordPress prefix; dynamic values remain prepared or static by design.

final class POINTLYBOOKING_Locations_Migrations_Helper {

  public static function ensure_tables(): void {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset = $wpdb->get_charset_collate();

    $locations = $wpdb->prefix . 'pointlybooking_locations';
    $categories = $wpdb->prefix . 'pointlybooking_location_categories';
    $map = $wpdb->prefix . 'pointlybooking_location_agents';

    $sql_locations = "CREATE TABLE {$locations} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      status VARCHAR(20) NOT NULL DEFAULT 'active',
      name VARCHAR(190) NOT NULL,
      address VARCHAR(255) NULL,
      category_id BIGINT UNSIGNED NULL,
      image_id BIGINT UNSIGNED NULL,
      use_custom_schedule TINYINT(1) NOT NULL DEFAULT 0,
      schedule_json LONGTEXT NULL,
      created_at DATETIME NULL,
      updated_at DATETIME NULL,
      PRIMARY KEY (id),
      KEY status (status),
      KEY category_id (category_id)
    ) {$charset};";

    $sql_categories = "CREATE TABLE {$categories} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      status VARCHAR(20) NOT NULL DEFAULT 'active',
      name VARCHAR(190) NOT NULL,
      image_id BIGINT UNSIGNED NULL,
      created_at DATETIME NULL,
      updated_at DATETIME NULL,
      PRIMARY KEY (id),
      KEY status (status)
    ) {$charset};";

    $sql_map = "CREATE TABLE {$map} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      location_id BIGINT UNSIGNED NOT NULL,
      agent_id BIGINT UNSIGNED NOT NULL,
      services_json LONGTEXT NULL,
      created_at DATETIME NULL,
      updated_at DATETIME NULL,
      PRIMARY KEY (id),
      UNIQUE KEY loc_agent (location_id, agent_id),
      KEY location_id (location_id),
      KEY agent_id (agent_id)
    ) {$charset};";

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema management or uninstall cleanup is intentional here and cannot be cached.
    dbDelta($sql_locations);
    dbDelta($sql_categories);
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema management or uninstall cleanup is intentional here and cannot be cached.
    dbDelta($sql_map);
  }
}
