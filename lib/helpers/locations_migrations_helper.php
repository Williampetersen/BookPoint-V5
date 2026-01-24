<?php
defined('ABSPATH') || exit;

final class BP_Locations_Migrations_Helper {

  public static function ensure_tables(): void {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset = $wpdb->get_charset_collate();

    $locations = $wpdb->prefix . 'bp_locations';
    $categories = $wpdb->prefix . 'bp_location_categories';
    $map = $wpdb->prefix . 'bp_location_agents';

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

    dbDelta($sql_locations);
    dbDelta($sql_categories);
    dbDelta($sql_map);
  }
}
