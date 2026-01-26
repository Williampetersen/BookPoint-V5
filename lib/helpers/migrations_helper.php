<?php
defined('ABSPATH') || exit;

final class BP_MigrationsHelper {

  const DB_VERSION = '1.6.0';
  const OPT_DB_VERSION = 'bp_db_version';
  const DB_VERSION_OPTION = 'BP_db_version';

  public static function run(): void {
    $installed = (string) get_option(self::OPT_DB_VERSION, '0.0.0');
    if (version_compare($installed, self::DB_VERSION, '>=') && !self::needs_run()) {
      return;
    }

    self::create_tables();

    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset = $wpdb->get_charset_collate();
    $prefix  = $wpdb->prefix . 'bp_';

    dbDelta("
      CREATE TABLE {$prefix}categories (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(190) NOT NULL,
        description TEXT NULL,
        image_id BIGINT UNSIGNED NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        PRIMARY KEY (id),
        KEY is_active (is_active),
        KEY sort_order (sort_order)
      ) {$charset};
    ");

    dbDelta("
      CREATE TABLE {$prefix}service_extras (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        service_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(190) NOT NULL,
        description TEXT NULL,
        price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        duration_min INT NULL,
        image_id BIGINT UNSIGNED NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        PRIMARY KEY (id),
        KEY service_id (service_id),
        KEY is_active (is_active),
        KEY sort_order (sort_order)
      ) {$charset};
    ");

    dbDelta("
      CREATE TABLE {$prefix}bundles (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(190) NOT NULL,
        description TEXT NULL,
        price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        image_id BIGINT UNSIGNED NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        PRIMARY KEY (id),
        KEY is_active (is_active)
      ) {$charset};
    ");

    dbDelta("
      CREATE TABLE {$prefix}bundle_items (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        bundle_id BIGINT UNSIGNED NOT NULL,
        item_type VARCHAR(20) NOT NULL,
        item_id BIGINT UNSIGNED NOT NULL,
        qty INT NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY bundle_id (bundle_id),
        KEY item_type (item_type),
        KEY item_id (item_id)
      ) {$charset};
    ");

    if (function_exists('bp_install_form_fields_table')) {
      bp_install_form_fields_table();
    }

    dbDelta("
      CREATE TABLE {$prefix}promo_codes (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        code VARCHAR(60) NOT NULL,
        type VARCHAR(10) NOT NULL,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        starts_at DATETIME NULL,
        ends_at DATETIME NULL,
        max_uses INT NULL,
        uses_count INT NOT NULL DEFAULT 0,
        min_total DECIMAL(10,2) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
      PRIMARY KEY (id),
      UNIQUE KEY code (code),
      KEY is_active (is_active)
    ) {$charset};
  ");

    dbDelta("
      CREATE TABLE {$prefix}workflows (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        event_key VARCHAR(80) NOT NULL,
        is_conditional TINYINT(1) NOT NULL DEFAULT 0,
        conditions_json LONGTEXT NULL,
        has_time_offset TINYINT(1) NOT NULL DEFAULT 0,
        time_offset_minutes INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        PRIMARY KEY (id),
        KEY event_key (event_key),
        KEY status (status)
      ) {$charset};
    ");

    dbDelta("
      CREATE TABLE {$prefix}workflow_actions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        workflow_id BIGINT UNSIGNED NOT NULL,
        type VARCHAR(40) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        config_json LONGTEXT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        PRIMARY KEY (id),
        KEY workflow_id (workflow_id),
        KEY sort_order (sort_order)
      ) {$charset};
    ");

    dbDelta("
      CREATE TABLE {$prefix}workflow_logs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        workflow_id BIGINT UNSIGNED NOT NULL,
        event_key VARCHAR(80) NOT NULL,
        entity_type VARCHAR(40) NOT NULL,
        entity_id BIGINT UNSIGNED NULL,
        status VARCHAR(20) NOT NULL,
        message LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY workflow_id (workflow_id),
        KEY event_key (event_key),
        KEY entity_type (entity_type),
        KEY entity_id (entity_id)
      ) {$charset};
    ");

    dbDelta("
      CREATE TABLE {$prefix}holidays (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        agent_id BIGINT UNSIGNED NULL,
        title VARCHAR(190) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        is_recurring TINYINT(1) NOT NULL DEFAULT 0,
        is_recurring_yearly TINYINT(1) NOT NULL DEFAULT 0,
        is_enabled TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        PRIMARY KEY (id),
        KEY date_range (start_date, end_date),
        KEY agent_id (agent_id)
      ) {$charset};
    ");

    dbDelta("
      CREATE TABLE {$prefix}schedules (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        agent_id BIGINT UNSIGNED NULL,
        day_of_week TINYINT NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        breaks_json LONGTEXT NULL,
        is_enabled TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        PRIMARY KEY (id),
        KEY agent_day (agent_id, day_of_week)
      ) {$charset};
    ");

    dbDelta("
      CREATE TABLE {$prefix}schedule_settings (
        id BIGINT UNSIGNED NOT NULL,
        slot_interval_minutes INT NOT NULL DEFAULT 30,
        timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Copenhagen',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        PRIMARY KEY (id)
      ) {$charset};
    ");

    self::maybe_add_column($wpdb->prefix . 'bp_services', 'category_id', 'BIGINT UNSIGNED NULL');
    self::maybe_add_column($wpdb->prefix . 'bp_services', 'image_id', 'BIGINT UNSIGNED NULL');

    self::maybe_add_column($wpdb->prefix . 'bp_bookings', 'category_id', 'BIGINT UNSIGNED NULL');
    self::maybe_add_column($wpdb->prefix . 'bp_bookings', 'service_id', 'BIGINT UNSIGNED NULL');
    self::maybe_add_column($wpdb->prefix . 'bp_bookings', 'extras_json', 'LONGTEXT NULL');
    self::maybe_add_column($wpdb->prefix . 'bp_bookings', 'promo_code', 'VARCHAR(60) NULL');
    self::maybe_add_column($wpdb->prefix . 'bp_bookings', 'discount_total', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
    self::maybe_add_column($wpdb->prefix . 'bp_bookings', 'total_price', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
    self::maybe_add_column($wpdb->prefix . 'bp_bookings', 'currency', "CHAR(3) NOT NULL DEFAULT 'USD'");
    self::maybe_add_column($wpdb->prefix . 'bp_bookings', 'payment_method', "VARCHAR(30) NOT NULL DEFAULT 'cash'");
    self::maybe_add_column($wpdb->prefix . 'bp_bookings', 'payment_status', "VARCHAR(30) NOT NULL DEFAULT 'unpaid'");
    self::maybe_add_column($wpdb->prefix . 'bp_bookings', 'payment_provider_ref', 'VARCHAR(190) NULL');
    self::maybe_add_column($wpdb->prefix . 'bp_bookings', 'payment_amount', 'DECIMAL(10,2) NULL');
    self::maybe_add_column($wpdb->prefix . 'bp_bookings', 'payment_currency', 'CHAR(3) NULL');
    self::maybe_add_column($wpdb->prefix . 'bp_bookings', 'customer_fields_json', 'LONGTEXT NULL');
    self::maybe_add_column($wpdb->prefix . 'bp_bookings', 'booking_fields_json', 'LONGTEXT NULL');
    self::maybe_add_column($wpdb->prefix . 'bp_bookings', 'custom_fields_json', 'LONGTEXT NULL');

    self::maybe_add_column($wpdb->prefix . 'bp_customers', 'custom_fields_json', 'LONGTEXT NULL');

    self::maybe_add_index($wpdb->prefix . 'bp_bookings', 'service_id', 'service_id');
    self::maybe_add_index($wpdb->prefix . 'bp_bookings', 'category_id', 'category_id');

    self::maybe_add_column($wpdb->prefix . 'bp_agents', 'image_id', 'BIGINT UNSIGNED NULL');

    self::maybe_add_column($wpdb->prefix . 'bp_holidays', 'agent_id', 'BIGINT UNSIGNED NULL');
    self::maybe_add_column($wpdb->prefix . 'bp_holidays', 'is_recurring', 'TINYINT(1) NOT NULL DEFAULT 0');
    self::maybe_add_column($wpdb->prefix . 'bp_holidays', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
    self::maybe_add_column($wpdb->prefix . 'bp_holidays', 'updated_at', 'DATETIME NULL');

    dbDelta("
      CREATE TABLE {$prefix}agent_services (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        agent_id BIGINT UNSIGNED NOT NULL,
        service_id BIGINT UNSIGNED NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY agent_service (agent_id, service_id),
        KEY agent_id (agent_id),
        KEY service_id (service_id)
      ) {$charset};
    ");

    dbDelta("
      CREATE TABLE {$prefix}service_categories (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        service_id BIGINT UNSIGNED NOT NULL,
        category_id BIGINT UNSIGNED NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq (service_id, category_id),
        KEY service_id (service_id),
        KEY category_id (category_id)
      ) {$charset};
    ");

    dbDelta("
      CREATE TABLE {$prefix}extra_services (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        extra_id BIGINT UNSIGNED NOT NULL,
        service_id BIGINT UNSIGNED NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq (extra_id, service_id),
        KEY extra_id (extra_id),
        KEY service_id (service_id)
      ) {$charset};
    ");

    if (!get_option('bp_relations_migrated_1_4')) {
      if (class_exists('BP_RelationsHelper')) {
        BP_RelationsHelper::migrate_legacy_relations();
      }
      update_option('bp_relations_migrated_1_4', 1, false);
    }

    update_option(self::OPT_DB_VERSION, self::DB_VERSION, false);
  }

  private static function needs_run(): bool {
    global $wpdb;

    $tables = [
      $wpdb->prefix . 'bp_categories',
      $wpdb->prefix . 'bp_service_extras',
      $wpdb->prefix . 'bp_bundles',
      $wpdb->prefix . 'bp_bundle_items',
      $wpdb->prefix . 'bp_form_fields',
      $wpdb->prefix . 'bp_field_values',
      $wpdb->prefix . 'bp_promo_codes',
      $wpdb->prefix . 'bp_holidays',
      $wpdb->prefix . 'bp_schedules',
      $wpdb->prefix . 'bp_schedule_settings',
      $wpdb->prefix . 'bp_agent_services',
      $wpdb->prefix . 'bp_service_categories',
      $wpdb->prefix . 'bp_extra_services',
      $wpdb->prefix . 'bp_workflows',
      $wpdb->prefix . 'bp_workflow_actions',
      $wpdb->prefix . 'bp_workflow_logs',
    ];

    foreach ($tables as $table) {
      $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
      if ($found !== $table) {
        return true;
      }
    }

    $service_table = $wpdb->prefix . 'bp_services';
    $booking_table = $wpdb->prefix . 'bp_bookings';
    $agent_table = $wpdb->prefix . 'bp_agents';

    $service_columns = ['category_id', 'image_id'];
    foreach ($service_columns as $col) {
      if (!self::column_exists($service_table, $col)) return true;
    }

    $booking_columns = [
      'category_id',
      'service_id',
      'extras_json',
      'promo_code',
      'discount_total',
      'total_price',
      'currency',
      'payment_method',
      'payment_status',
      'payment_provider_ref',
      'payment_amount',
      'payment_currency',
      'customer_fields_json',
      'booking_fields_json',
    ];
    foreach ($booking_columns as $col) {
      if (!self::column_exists($booking_table, $col)) return true;
    }

      if (!self::column_exists($wpdb->prefix . 'bp_customers', 'custom_fields_json')) return true;
      if (!self::column_exists($booking_table, 'custom_fields_json')) return true;

    if (!self::column_exists($agent_table, 'image_id')) return true;

    return false;
  }

  private static function column_exists(string $table, string $column): bool {
    global $wpdb;
    $exists = $wpdb->get_var($wpdb->prepare(
      "SHOW COLUMNS FROM {$table} LIKE %s",
      $column
    ));
    return !empty($exists);
  }

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
      PRIMARY KEY (id),
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

    // Step 19: Ensure bookings columns exist
    self::migrate_booking_notes_status();

    // Step 21: Manage token last-used timestamp
    self::migrate_booking_token_last_used();

    // Audit log
    self::migrate_audit_table();

    // Indexes
    self::migrate_indexes();

    // Step 15: Service-based availability migration
    self::migrate_add_service_availability_fields();

    // Step 16: Agent management
    self::migrate_create_agents_table();
    self::migrate_add_agent_id_to_bookings();

    // Step 18: Service-Agent pivot
    self::migrate_create_service_agents_table();

    if (function_exists('bp_install_form_fields_table')) {
      bp_install_form_fields_table();
    }
    if (function_exists('bp_seed_default_form_fields')) {
      bp_seed_default_form_fields();
    }
    if (function_exists('bp_install_field_values_table')) {
      bp_install_field_values_table();
    }
  }

  // Step 19: Ensure bookings have status + notes columns
  private static function migrate_booking_notes_status() : void {
    global $wpdb;
    $table = $wpdb->prefix . 'bp_bookings';

    $columns = $wpdb->get_col("DESC {$table}", 0);

    if (!in_array('status', $columns, true)) {
      $wpdb->query("ALTER TABLE {$table} ADD COLUMN status VARCHAR(30) NOT NULL DEFAULT 'pending'");
    }
    if (!in_array('notes', $columns, true)) {
      $wpdb->query("ALTER TABLE {$table} ADD COLUMN notes LONGTEXT NULL");
    }
  }

  // Step 21: Track manage token last used
  private static function migrate_booking_token_last_used() : void {
    global $wpdb;
    $table = $wpdb->prefix . 'bp_bookings';

    $columns = $wpdb->get_col("DESC {$table}", 0);

    if (!in_array('manage_token_last_used_at', $columns, true)) {
      $wpdb->query("ALTER TABLE {$table} ADD COLUMN manage_token_last_used_at DATETIME NULL");
    }
  }

  private static function migrate_audit_table() : void {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table = $wpdb->prefix . 'bp_audit_log';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      event VARCHAR(60) NOT NULL,
      actor_type VARCHAR(20) NOT NULL,
      actor_wp_user_id BIGINT UNSIGNED NULL,
      actor_ip VARCHAR(60) NULL,
      booking_id BIGINT UNSIGNED NULL,
      customer_id BIGINT UNSIGNED NULL,
      meta LONGTEXT NULL,
      created_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY event (event),
      KEY booking_id (booking_id),
      KEY customer_id (customer_id),
      KEY created_at (created_at)
    ) {$charset};";

    dbDelta($sql);
  }

  private static function migrate_indexes() : void {
    global $wpdb;

    $b = $wpdb->prefix . 'bp_bookings';
    self::ensure_index($b, 'status');
    self::ensure_index($b, 'customer_id');
    self::ensure_index($b, 'agent_id');
    self::ensure_index($b, 'service_id');
    self::ensure_index($b, 'start_datetime');

    $a = $wpdb->prefix . 'bp_audit_log';
    self::ensure_index($a, 'event');
    self::ensure_index($a, 'created_at');
    self::ensure_index($a, 'booking_id');
    self::ensure_index($a, 'customer_id');
  }

  private static function ensure_index(string $table, string $column) : void {
    global $wpdb;

    $exists = $wpdb->get_var($wpdb->prepare(
      'SHOW INDEX FROM ' . $table . ' WHERE Column_name = %s',
      $column
    ));

    if ($exists) return;

    $wpdb->query("ALTER TABLE {$table} ADD INDEX {$column} ({$column})");
  }

  private static function maybe_add_column(string $table, string $column, string $definition): void {
    global $wpdb;
    $exists = $wpdb->get_var($wpdb->prepare(
      "SHOW COLUMNS FROM {$table} LIKE %s",
      $column
    ));
    if (!$exists) {
      $wpdb->query("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }
  }

  private static function maybe_add_index(string $table, string $index_name, string $column): void {
    global $wpdb;
    $exists = $wpdb->get_var($wpdb->prepare(
      "SHOW INDEX FROM {$table} WHERE Key_name = %s",
      $index_name
    ));
    if (!$exists) {
      $wpdb->query("ALTER TABLE {$table} ADD INDEX {$index_name} ({$column})");
    }
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

  // Step 18: Create service_agents pivot table
  private static function migrate_create_service_agents_table() : void {
    global $wpdb;
    $table = $wpdb->prefix . 'bp_service_agents';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      service_id BIGINT UNSIGNED NOT NULL,
      agent_id BIGINT UNSIGNED NOT NULL,
      created_at DATETIME NULL,
      PRIMARY KEY (id),
      UNIQUE KEY service_agent (service_id, agent_id),
      KEY service_id (service_id),
      KEY agent_id (agent_id)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
  }
}

