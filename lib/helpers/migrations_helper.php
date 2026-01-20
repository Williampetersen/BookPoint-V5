<?php
defined('ABSPATH') || exit;

final class BP_MigrationsHelper {

  const DB_VERSION_OPTION = 'BP_db_version';

  public static function create_tables() : void {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();

    $services = $wpdb->prefix . 'bp_services';
    $customers = $wpdb->prefix . 'bp_customers';
    $bookings = $wpdb->prefix . 'bp_bookings';
    $settings = $wpdb->prefix . 'bp_settings';

    $sql_services = "CREATE TABLE $services (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      name VARCHAR(190) NOT NULL,
      description LONGTEXT NULL,
      duration_minutes INT UNSIGNED NOT NULL DEFAULT 60,
      price_cents INT UNSIGNED NOT NULL DEFAULT 0,
      currency CHAR(3) NOT NULL DEFAULT 'USD',
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY  (id),
      KEY is_active (is_active)
    ) $charset_collate;";

    $sql_customers = "CREATE TABLE $customers (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      first_name VARCHAR(190) NULL,
      last_name VARCHAR(190) NULL,
      email VARCHAR(190) NULL,
      phone VARCHAR(50) NULL,
      wp_user_id BIGINT UNSIGNED NULL,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY wp_user_id (wp_user_id),
      KEY email (email)
    ) $charset_collate;";

    $sql_bookings = "CREATE TABLE $bookings (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      service_id BIGINT UNSIGNED NOT NULL,
      customer_id BIGINT UNSIGNED NOT NULL,
      start_datetime DATETIME NOT NULL,
      end_datetime DATETIME NOT NULL,
      status VARCHAR(30) NOT NULL DEFAULT 'pending',
      notes LONGTEXT NULL,
      manage_key CHAR(64) NOT NULL,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY manage_key (manage_key),
      KEY service_id (service_id),
      KEY customer_id (customer_id),
      KEY start_datetime (start_datetime)
    ) $charset_collate;";

    $sql_settings = "CREATE TABLE $settings (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      setting_key VARCHAR(190) NOT NULL,
      setting_value LONGTEXT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY setting_key (setting_key)
    ) $charset_collate;";

    dbDelta($sql_services);
    dbDelta($sql_customers);
    dbDelta($sql_bookings);
    dbDelta($sql_settings);

    // Step 15: Service-based availability migration
    self::migrate_add_service_availability_fields();

    // Step 16: Agent management
    self::migrate_create_agents_table();
    self::migrate_add_agent_id_to_bookings();
  }

  private static function migrate_add_service_availability_fields() : void {
    global $wpdb;
    $table = $wpdb->prefix . 'bp_services';

    $columns = $wpdb->get_col("DESC {$table}", 0);

    if (!in_array('use_global_schedule', $columns, true)) {
      $wpdb->query("ALTER TABLE {$table} ADD COLUMN use_global_schedule TINYINT(1) NOT NULL DEFAULT 1");
    }
    if (!in_array('schedule_json', $columns, true)) {
      $wpdb->query("ALTER TABLE {$table} ADD COLUMN schedule_json LONGTEXT NULL");
    }
    if (!in_array('buffer_before_minutes', $columns, true)) {
      $wpdb->query("ALTER TABLE {$table} ADD COLUMN buffer_before_minutes INT NOT NULL DEFAULT 0");
    }
    if (!in_array('buffer_after_minutes', $columns, true)) {
      $wpdb->query("ALTER TABLE {$table} ADD COLUMN buffer_after_minutes INT NOT NULL DEFAULT 0");
    }
    if (!in_array('capacity', $columns, true)) {
      $wpdb->query("ALTER TABLE {$table} ADD COLUMN capacity INT NOT NULL DEFAULT 1");
    }
  }

  // Step 16: Create agents table
  private static function migrate_create_agents_table() : void {
    global $wpdb;
    $table = $wpdb->prefix . 'bp_agents';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      first_name VARCHAR(191) NULL,
      last_name VARCHAR(191) NULL,
      email VARCHAR(191) NULL,
      phone VARCHAR(50) NULL,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      schedule_json LONGTEXT NULL,
      created_at DATETIME NULL,
      updated_at DATETIME NULL,
      PRIMARY KEY (id),
      KEY is_active (is_active)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
  }

  // Step 16: Add agent_id to bookings
  private static function migrate_add_agent_id_to_bookings() : void {
    global $wpdb;
    $table = $wpdb->prefix . 'bp_bookings';

    $columns = $wpdb->get_col("DESC {$table}", 0);

    if (!in_array('agent_id', $columns, true)) {
      $wpdb->query("ALTER TABLE {$table} ADD COLUMN agent_id BIGINT UNSIGNED NULL AFTER customer_id");
      $wpdb->query("ALTER TABLE {$table} ADD INDEX agent_id (agent_id)");
    }
  }
}

