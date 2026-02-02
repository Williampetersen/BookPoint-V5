<?php
/**
 * Plugin Name: BookPoint
 * Description: Professional appointment booking system (MVC + Router + Blocks).
 * Version: 5.0.0
 * Author: Your Name
 * Text Domain: bookpoint
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

add_filter('admin_body_class', function($classes){
  if (!isset($_GET['page'])) return $classes;
  $page = sanitize_text_field($_GET['page']);
  if (strpos($page, 'bp') !== 0) return $classes;
  return $classes . ' bp-app-mode';
});

final class BP_Plugin {

  const VERSION    = '5.0.0';
  const DB_VERSION = '5.0.0';

  public static function init() : void {
    self::define_constants();
    self::includes();
    self::load_textdomain();
    self::register_hooks();
    BP_UpdatesHelper::init();
  }

  private static function define_constants() : void {
    if (!defined('BP_PLUGIN_FILE')) define('BP_PLUGIN_FILE', __FILE__);
    if (!defined('BP_PLUGIN_PATH')) define('BP_PLUGIN_PATH', plugin_dir_path(__FILE__));
    if (!defined('BP_PLUGIN_URL'))  define('BP_PLUGIN_URL', plugin_dir_url(__FILE__));

    if (!defined('BP_LIB_PATH'))    define('BP_LIB_PATH', BP_PLUGIN_PATH . 'lib/');
    if (!defined('BP_PUBLIC_PATH')) define('BP_PUBLIC_PATH', BP_PLUGIN_PATH . 'public/');
    if (!defined('BP_VIEWS_PATH'))  define('BP_VIEWS_PATH', BP_LIB_PATH . 'views/');
    if (!defined('BP_BLOCKS_PATH')) define('BP_BLOCKS_PATH', BP_PLUGIN_PATH . 'blocks/');
  }

  private static function load_textdomain() : void {
    add_action('init', function () {
      load_plugin_textdomain(
        'bookpoint',
        false,
        dirname(plugin_basename(BP_PLUGIN_FILE)) . '/languages'
      );
    });
  }

  private static function includes() : void {
    // Helpers (Step 2)
    require_once BP_LIB_PATH . 'helpers/roles_helper.php';
    require_once BP_LIB_PATH . 'helpers/migrations_helper.php';
    require_once BP_LIB_PATH . 'helpers/form_fields_helper.php';
    require_once BP_LIB_PATH . 'helpers/form_fields_seed_helper.php';
    require_once BP_LIB_PATH . 'helpers/field_values_helper.php';
    require_once BP_LIB_PATH . 'helpers/database_helper.php';

    // Helpers (Step 5)
    require_once BP_LIB_PATH . 'helpers/availability_helper.php';

    // Models (Step 4)
    require_once BP_LIB_PATH . 'models/model.php';
    require_once BP_LIB_PATH . 'models/service_model.php';
    require_once BP_LIB_PATH . 'models/category_model.php';

    // Models (Step 5)
    require_once BP_LIB_PATH . 'models/customer_model.php';
    require_once BP_LIB_PATH . 'models/booking_model.php';
    require_once BP_LIB_PATH . 'models/service_extra_model.php';
    require_once BP_LIB_PATH . 'models/promo_code_model.php';
    require_once BP_LIB_PATH . 'models/form_field_model.php';

    // Models (Step 16)
    require_once BP_LIB_PATH . 'models/agent_model.php';

    // Models (Step 18)
    require_once BP_LIB_PATH . 'models/service_agent_model.php';

    // Models (Audit)
    require_once BP_LIB_PATH . 'models/audit_model.php';

    // Helpers (Step 7)
    require_once BP_LIB_PATH . 'helpers/settings_helper.php';

    // Helpers (Step 14)
    require_once BP_LIB_PATH . 'helpers/schedule_helper.php';

    // Helpers (Step 10)
    require_once BP_LIB_PATH . 'helpers/email_helper.php';
    require_once BP_LIB_PATH . 'helpers/notifications_helper.php';
    require_once BP_LIB_PATH . 'helpers/locations_migrations_helper.php';

    // Helpers (Portal + Webhooks)
    require_once BP_LIB_PATH . 'helpers/portal_helper.php';
    require_once BP_LIB_PATH . 'helpers/webhook_helper.php';
    require_once BP_LIB_PATH . 'helpers/payments_booking_bridge.php';

    // Helpers (Audit)
    require_once BP_LIB_PATH . 'helpers/audit_helper.php';

    // Helpers (Relations)
    require_once BP_LIB_PATH . 'helpers/relations_helper.php';

    // Helpers (Dashboard)
    require_once BP_LIB_PATH . 'helpers/dashboard_helper.php';

    // Helpers (Demo)
    require_once BP_LIB_PATH . 'helpers/demo_helper.php';

    // Helpers (License + Updates)
    require_once BP_LIB_PATH . 'helpers/license_helper.php';
    require_once BP_LIB_PATH . 'helpers/updates_helper.php';

    // Integrations
    require_once BP_LIB_PATH . 'integrations/woocommerce-hooks.php';

    // Controllers (Step 3)
    require_once BP_LIB_PATH . 'controllers/controller.php';
    require_once BP_LIB_PATH . 'controllers/admin_dashboard_controller.php';

    // Controllers (Step 4)
    require_once BP_LIB_PATH . 'controllers/admin_services_controller.php';
    require_once BP_LIB_PATH . 'controllers/admin_categories_controller.php';
    require_once BP_LIB_PATH . 'controllers/admin_extras_controller.php';
    require_once BP_LIB_PATH . 'controllers/admin_promo_codes_controller.php';
    require_once BP_LIB_PATH . 'controllers/admin_form_fields_controller.php';

    // Controllers (Step 5)
    require_once BP_LIB_PATH . 'controllers/public_bookings_controller.php';

    // Controllers (Step 7)
    require_once BP_LIB_PATH . 'controllers/admin_settings_controller.php';

    // Controllers (Step 8)
    require_once BP_LIB_PATH . 'controllers/admin_bookings_controller.php';

    // Controllers (Step 9)
    require_once BP_LIB_PATH . 'controllers/admin_customers_controller.php';

    // Controllers (Step 16)
    require_once BP_LIB_PATH . 'controllers/admin_agents_controller.php';

    // Controllers (Audit + Tools)
    require_once BP_LIB_PATH . 'controllers/admin_audit_controller.php';
    require_once BP_LIB_PATH . 'controllers/admin_tools_controller.php';
    require_once BP_LIB_PATH . 'admin/admin-payments-settings-routes.php';

    // REST routes (Admin)
    require_once BP_LIB_PATH . 'rest/admin-calendar-routes.php';
    require_once BP_LIB_PATH . 'rest/admin-bookings-routes.php';
    require_once BP_LIB_PATH . 'rest/bookings-routes.php';
    require_once BP_LIB_PATH . 'rest/admin-catalog-routes.php';
    require_once BP_LIB_PATH . 'rest/admin-schedule-routes.php';
    require_once BP_LIB_PATH . 'rest/admin-schedule-editor-routes.php';
    require_once BP_LIB_PATH . 'rest/admin-holidays-routes.php';
    require_once BP_LIB_PATH . 'rest/calendar-routes.php';
    require_once BP_LIB_PATH . 'rest/dashboard-routes.php';
    require_once BP_LIB_PATH . 'rest/admin-catalog-manager-routes.php';
    require_once BP_LIB_PATH . 'rest/admin-misc-routes.php';
    require_once BP_LIB_PATH . 'rest/admin-notifications-routes.php';
    require_once BP_LIB_PATH . 'rest/admin-locations-routes.php';
    require_once BP_LIB_PATH . 'rest/admin-field-values-routes.php';
    require_once BP_LIB_PATH . 'rest/settings-routes.php';
    require_once BP_LIB_PATH . 'rest/public-catalog-routes.php';
    require_once BP_LIB_PATH . 'rest/public-availability-routes.php';
    require_once BP_LIB_PATH . 'rest/public-booking-routes.php';
    require_once BP_LIB_PATH . 'rest/front-wizard-routes.php';
    require_once BP_LIB_PATH . 'rest/admin-booking-form-design-routes.php';
    require_once BP_LIB_PATH . 'rest/front-booking-form-design-routes.php';
    require_once BP_LIB_PATH . 'rest/front-settings.php';
    require_once BP_LIB_PATH . 'rest/front-booking-create.php';
    require_once BP_LIB_PATH . 'rest/front-booking-status.php';
    require_once BP_LIB_PATH . 'rest/front-payments-woocommerce.php';
    require_once BP_LIB_PATH . 'rest/front-payments-stripe.php';
    require_once BP_LIB_PATH . 'rest/front-payments-paypal.php';
    require_once BP_LIB_PATH . 'front/front-stripe-routes.php';
    require_once BP_LIB_PATH . 'routes/front-availability-routes.php';
    require_once BP_LIB_PATH . 'routes/front-availability-month-slots.php';
    require_once BP_LIB_PATH . 'rest/form-fields-routes.php';
  }

  private static function register_hooks() : void {

    // Activation / Deactivation
    register_activation_hook(BP_PLUGIN_FILE, [__CLASS__, 'on_activate']);
    register_deactivation_hook(BP_PLUGIN_FILE, [__CLASS__, 'on_deactivate']);
    register_activation_hook(BP_PLUGIN_FILE, function () {
      BP_MigrationsHelper::run();
    });

    // Admin menu
    add_action('admin_menu', [__CLASS__, 'register_admin_menu']);

    // Hide WP admin bar for BookPoint admin pages to remove top gap
    add_action('admin_head', function () {
      if (!isset($_GET['page'])) return;
      $page = sanitize_text_field($_GET['page']);
      if (strpos($page, 'bp') !== 0) return;
      echo '<style>#wpadminbar{display:none!important;}html.wp-toolbar{padding-top:0!important;}</style>';
    });

    add_action('admin_init', function () {
      BP_MigrationsHelper::run();
      if (class_exists('BP_Locations_Migrations_Helper')) {
        BP_Locations_Migrations_Helper::ensure_tables();
      }
    });

    add_action('admin_init', function () {
      if (!current_user_can('administrator') && !current_user_can('bp_manage_settings')) return;
      if (!class_exists('BP_FormFieldsSeedHelper')) return;
      BP_FormFieldsSeedHelper::ensure_defaults();
    });

    add_action('admin_init', function () {
      if (!is_admin()) return;
      if (!isset($_GET['page'])) return;

      $map = [
        'bp_schedule' => 'schedule',
        'bp_holidays' => 'holidays',
        'bp_form_fields' => 'form_fields',
        'bp-form-fields' => 'form_fields',
        'bp_promo_codes' => 'promo_codes',
        'bp_notifications' => 'notifications',
        'bp_audit' => 'audit_log',
        'bp_audit_log' => 'audit_log',
        'bp_tools' => 'tools',
      ];

      $page = sanitize_text_field($_GET['page']);
      if (!isset($map[$page])) return;

      wp_safe_redirect(admin_url('admin.php?page=bp_settings&tab=' . $map[$page]));
      exit;
    });

    add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);

    // Services admin-post action
    add_action('admin_post_bp_admin_services_save', [__CLASS__, 'handle_services_save']);

    // Settings admin-post action
    add_action('admin_post_bp_admin_settings_save', [__CLASS__, 'handle_settings_save']);
    add_action('admin_post_bp_admin_settings_save_license', [__CLASS__, 'handle_settings_save_license']);
    add_action('admin_post_bp_admin_settings_validate_license', [__CLASS__, 'handle_settings_validate_license']);
    add_action('admin_post_bp_admin_settings_export_json', [__CLASS__, 'handle_settings_export_json']);
    add_action('admin_post_bp_admin_settings_import_json', [__CLASS__, 'handle_settings_import_json']);

    add_action('admin_post_bp_admin_categories_save', function () {
      (new BP_AdminCategoriesController())->save();
    });

    add_action('admin_post_bp_admin_extras_save', function () {
      (new BP_AdminExtrasController())->save();
    });

    add_action('admin_post_bp_admin_promo_codes_save', function () {
      (new BP_AdminPromoCodesController())->save();
    });

    add_action('admin_post_bp_admin_form_fields_save', function () {
      (new BP_AdminFormFieldsController())->save();
    });

    // Agents admin-post action (Step 16)
    add_action('admin_post_bp_admin_agents_save', [__CLASS__, 'handle_agents_save']);

    // Booking notes admin-post action (Step 19)
    add_action('admin_post_bp_admin_booking_notes_save', [__CLASS__, 'handle_booking_notes_save']);

    // Dashboard quick booking update
    add_action('admin_post_bp_admin_booking_quick_update', function () {
      if (!current_user_can('bp_manage_bookings')) wp_die('No permission');
      check_admin_referer('bp_admin');

      $id = (int)($_POST['id'] ?? 0);
      $status = sanitize_text_field($_POST['status'] ?? '');

      if ($id > 0 && in_array($status, ['confirmed', 'cancelled'], true)) {
        global $wpdb;
        $wpdb->update($wpdb->prefix . 'bp_bookings', ['status' => $status], ['id' => $id], ['%s'], ['%d']);

        // Optional: trigger notifications if available
        // BP_NotificationsHelper::booking_status_changed($id, $status);
      }

      wp_safe_redirect(admin_url('admin.php?page=bp_dashboard&updated=1'));
      exit;
    });

    // Bookings export CSV
    add_action('admin_post_bp_admin_bookings_export_csv', [__CLASS__, 'handle_bookings_export_csv']);
    add_action('admin_post_bp_admin_bookings_export_pdf', [__CLASS__, 'handle_bookings_export_pdf']);

    // GDPR delete customer
    add_action('admin_post_bp_admin_customer_gdpr_delete', [__CLASS__, 'handle_customer_gdpr_delete']);

    // Customers import/export CSV
    add_action('admin_post_bp_admin_customers_export_csv', [__CLASS__, 'handle_customers_export_csv']);
    add_action('admin_post_bp_admin_customers_import_csv', [__CLASS__, 'handle_customers_import_csv']);

    // Tools actions
    add_action('admin_post_bp_admin_tools_email_test', [__CLASS__, 'handle_tools_email_test']);
    add_action('admin_post_bp_admin_tools_webhook_test', [__CLASS__, 'handle_tools_webhook_test']);
    add_action('admin_post_bp_admin_tools_generate_demo', [__CLASS__, 'handle_tools_generate_demo']);

    // License actions
    add_action('admin_post_bp_admin_license_save', [__CLASS__, 'handle_license_save']);
    add_action('admin_post_bp_admin_license_validate', [__CLASS__, 'handle_license_validate']);

    // Tools settings import/export
    add_action('admin_post_bp_admin_tools_export_settings', [__CLASS__, 'handle_tools_export_settings']);
    add_action('admin_post_bp_admin_tools_import_settings', [__CLASS__, 'handle_tools_import_settings']);

    // Shortcode
    add_shortcode('bookPoint', 'bp_shortcode_booking_form');
    add_shortcode('bookpoint', 'bp_shortcode_booking_form');
    add_shortcode('BookPoint', 'bp_shortcode_booking_form');
    add_shortcode('bookPoint_portal', [__CLASS__, 'shortcode_customer_portal']);

    // Portal actions
    add_action('init', [__CLASS__, 'handle_portal_posts']);

    add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_public_assets']);

    // License cron
    add_action('bp_daily_license_check', ['BP_LicenseHelper', 'maybe_cron_validate']);
    if (!wp_next_scheduled('bp_daily_license_check')) {
      wp_schedule_event(time() + 300, 'daily', 'bp_daily_license_check');
    }

    // Gutenberg blocks (Step 11)
    add_action('init', [__CLASS__, 'register_blocks']);

    // REST API (Step 12)
    add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);

    // AJAX (public)
    add_action('wp_ajax_bp_slots', [__CLASS__, 'ajax_slots']);
    add_action('wp_ajax_nopriv_bp_slots', [__CLASS__, 'ajax_slots']);
    add_action('wp_ajax_bp_submit_booking', [__CLASS__, 'ajax_submit_booking']);
    add_action('wp_ajax_nopriv_bp_submit_booking', [__CLASS__, 'ajax_submit_booking']);

    // Public manage booking page
    add_action('parse_request', [__CLASS__, 'maybe_render_public_pages']);
    add_filter('query_vars', [__CLASS__, 'register_query_vars']);
  }

  public static function on_activate() : void {
    self::includes();

    BP_RolesHelper::add_capabilities();
    BP_DatabaseHelper::install_or_update(self::DB_VERSION);
    self::install_or_upgrade_schedule_tables();
    self::seed_default_agent_hours();
    bp_install_form_fields_table();
    bp_seed_default_form_fields();
    if (class_exists('BP_FormFieldsSeedHelper')) {
      BP_FormFieldsSeedHelper::ensure_defaults();
    }
    bp_install_field_values_table();
    if (class_exists('BP_Locations_Migrations_Helper')) {
      BP_Locations_Migrations_Helper::ensure_tables();
    }

    // Store plugin version too (optional but helpful)
    update_option('BP_version', self::VERSION, false);
  }

  private static function install_or_upgrade_schedule_tables() : void {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $t_hours  = $wpdb->prefix . 'bp_agent_working_hours';
    $t_breaks = $wpdb->prefix . 'bp_agent_breaks';
    $t_schedules = $wpdb->prefix . 'bp_schedules';
    $t_schedule_settings = $wpdb->prefix . 'bp_schedule_settings';
    $t_holidays = $wpdb->prefix . 'bp_holidays';
    $t_services = $wpdb->prefix . 'bp_services';
    $t_categories = $wpdb->prefix . 'bp_categories';
    $t_extras = $wpdb->prefix . 'bp_service_extras';
    $t_agents = $wpdb->prefix . 'bp_agents';
    $t_bookings = $wpdb->prefix . 'bp_bookings';
    $t_service_categories = $wpdb->prefix . 'bp_service_categories';
    $t_extra_services = $wpdb->prefix . 'bp_extra_services';
    $t_agent_services = $wpdb->prefix . 'bp_agent_services';

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    dbDelta("CREATE TABLE {$t_hours} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      agent_id BIGINT UNSIGNED NOT NULL,
      weekday TINYINT NOT NULL,
      start_time TIME NOT NULL,
      end_time TIME NOT NULL,
      is_enabled TINYINT NOT NULL DEFAULT 1,
      PRIMARY KEY (id),
      KEY agent_weekday (agent_id, weekday)
    ) {$charset_collate};");

    dbDelta("CREATE TABLE {$t_breaks} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      agent_id BIGINT UNSIGNED NOT NULL,
      break_date DATE NOT NULL,
      start_time TIME NOT NULL,
      end_time TIME NOT NULL,
      note VARCHAR(255) NULL,
      PRIMARY KEY (id),
      KEY agent_date (agent_id, break_date)
    ) {$charset_collate};");

    dbDelta("CREATE TABLE {$t_schedules} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      agent_id BIGINT UNSIGNED NULL,
      day_of_week TINYINT NOT NULL,
      start_time TIME NOT NULL,
      end_time TIME NOT NULL,
      breaks_json LONGTEXT NULL,
      is_enabled TINYINT NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NULL,
      PRIMARY KEY (id),
      KEY agent_day (agent_id, day_of_week)
    ) {$charset_collate};");

    dbDelta("CREATE TABLE {$t_schedule_settings} (
      id BIGINT UNSIGNED NOT NULL,
      slot_interval_minutes INT NOT NULL DEFAULT 30,
      timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Copenhagen',
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NULL,
      PRIMARY KEY (id)
    ) {$charset_collate};");

    dbDelta("CREATE TABLE {$t_holidays} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      title VARCHAR(190) NOT NULL,
      start_date DATE NOT NULL,
      end_date DATE NOT NULL,
      is_recurring_yearly TINYINT NOT NULL DEFAULT 0,
      is_enabled TINYINT NOT NULL DEFAULT 1,
      PRIMARY KEY (id),
      KEY date_range (start_date, end_date),
      KEY enabled (is_enabled)
    ) {$charset_collate};");

    dbDelta("CREATE TABLE {$t_service_categories} (
      service_id BIGINT UNSIGNED NOT NULL,
      category_id BIGINT UNSIGNED NOT NULL,
      PRIMARY KEY (service_id, category_id),
      KEY category_id (category_id)
    ) {$charset_collate};");

    dbDelta("CREATE TABLE {$t_extra_services} (
      extra_id BIGINT UNSIGNED NOT NULL,
      service_id BIGINT UNSIGNED NOT NULL,
      PRIMARY KEY (extra_id, service_id),
      KEY service_id (service_id)
    ) {$charset_collate};");

    dbDelta("CREATE TABLE {$t_agent_services} (
      agent_id BIGINT UNSIGNED NOT NULL,
      service_id BIGINT UNSIGNED NOT NULL,
      PRIMARY KEY (agent_id, service_id),
      KEY service_id (service_id)
    ) {$charset_collate};");

    // Column upgrades
    self::add_column_if_missing($t_categories, 'image_id', "ALTER TABLE {$t_categories} ADD COLUMN image_id BIGINT UNSIGNED NOT NULL DEFAULT 0");
    self::add_column_if_missing($t_categories, 'sort_order', "ALTER TABLE {$t_categories} ADD COLUMN sort_order INT NOT NULL DEFAULT 0");

    self::add_column_if_missing($t_services, 'image_id', "ALTER TABLE {$t_services} ADD COLUMN image_id BIGINT UNSIGNED NOT NULL DEFAULT 0");
    self::add_column_if_missing($t_services, 'sort_order', "ALTER TABLE {$t_services} ADD COLUMN sort_order INT NOT NULL DEFAULT 0");

    self::add_column_if_missing($t_extras, 'image_id', "ALTER TABLE {$t_extras} ADD COLUMN image_id BIGINT UNSIGNED NOT NULL DEFAULT 0");
    self::add_column_if_missing($t_extras, 'sort_order', "ALTER TABLE {$t_extras} ADD COLUMN sort_order INT NOT NULL DEFAULT 0");

    self::add_column_if_missing($t_agents, 'image_id', "ALTER TABLE {$t_agents} ADD COLUMN image_id BIGINT UNSIGNED NOT NULL DEFAULT 0");

    // Holiday extensions (agent-specific + metadata)
    self::add_column_if_missing($t_holidays, 'agent_id', "ALTER TABLE {$t_holidays} ADD COLUMN agent_id BIGINT UNSIGNED NULL");
    self::add_column_if_missing($t_holidays, 'is_recurring', "ALTER TABLE {$t_holidays} ADD COLUMN is_recurring TINYINT NOT NULL DEFAULT 0");
    self::add_column_if_missing($t_holidays, 'created_at', "ALTER TABLE {$t_holidays} ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
    self::add_column_if_missing($t_holidays, 'updated_at', "ALTER TABLE {$t_holidays} ADD COLUMN updated_at DATETIME NULL");

    // Indexes for speed
    self::add_index_if_missing($t_bookings, 'agent_start_date', "CREATE INDEX agent_start_date ON {$t_bookings} (agent_id, start_date)");
    self::add_index_if_missing($t_bookings, 'service_start_date', "CREATE INDEX service_start_date ON {$t_bookings} (service_id, start_date)");

    self::add_index_if_missing($t_categories, 'sort_order', "CREATE INDEX sort_order ON {$t_categories} (sort_order)");
    self::add_index_if_missing($t_services, 'sort_order', "CREATE INDEX sort_order ON {$t_services} (sort_order)");
    self::add_index_if_missing($t_extras, 'sort_order', "CREATE INDEX sort_order ON {$t_extras} (sort_order)");
    self::add_index_if_missing($t_holidays, 'agent_id', "CREATE INDEX agent_id ON {$t_holidays} (agent_id)");
    self::add_index_if_missing($t_schedules, 'agent_day', "CREATE INDEX agent_day ON {$t_schedules} (agent_id, day_of_week)");

    $cols = $wpdb->get_results("SHOW COLUMNS FROM {$t_services}", ARRAY_A);
    $names = array_column($cols, 'Field');

    if (!in_array('buffer_before', $names, true)) {
      $wpdb->query("ALTER TABLE {$t_services} ADD COLUMN buffer_before INT NOT NULL DEFAULT 0");
    }
    if (!in_array('buffer_after', $names, true)) {
      $wpdb->query("ALTER TABLE {$t_services} ADD COLUMN buffer_after INT NOT NULL DEFAULT 0");
    }
    if (!in_array('capacity', $names, true)) {
      $wpdb->query("ALTER TABLE {$t_services} ADD COLUMN capacity INT NOT NULL DEFAULT 1");
    }
  }

  private static function add_column_if_missing(string $table, string $column, string $sql) : void {
    global $wpdb;
    $exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
    if (!$exists) $wpdb->query($sql);
  }

  private static function add_index_if_missing(string $table, string $index, string $sql) : void {
    global $wpdb;
    $exists = $wpdb->get_var($wpdb->prepare("SHOW INDEX FROM {$table} WHERE Key_name = %s", $index));
    if (!$exists) $wpdb->query($sql);
  }

  private static function seed_default_agent_hours() : void {
    global $wpdb;
    $t_agents = $wpdb->prefix . 'bp_agents';
    $t_hours  = $wpdb->prefix . 'bp_agent_working_hours';

    $agents = $wpdb->get_results("SELECT id FROM {$t_agents}", ARRAY_A) ?: [];
    foreach ($agents as $a) {
      $aid = (int)$a['id'];

      $exists = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t_hours} WHERE agent_id=%d", $aid));
      if ($exists > 0) continue;

      for ($d = 1; $d <= 5; $d++) {
        $wpdb->insert($t_hours, [
          'agent_id' => $aid,
          'weekday' => $d,
          'start_time' => '09:00:00',
          'end_time' => '17:00:00',
          'is_enabled' => 1
        ], ['%d','%d','%s','%s','%d']);
      }
    }
  }

  public static function on_deactivate() : void {
    self::includes();
    // Usually we do not remove caps on deactivate (optional).
    // BP_RolesHelper::remove_capabilities();

    $t = wp_next_scheduled('bp_daily_license_check');
    if ($t) wp_unschedule_event($t, 'bp_daily_license_check');
  }

  public static function register_blocks() : void {
    $block_dir = BP_PLUGIN_PATH . 'blocks/build/book-form';

    if (file_exists($block_dir . '/block.json')) {
      register_block_type($block_dir, [
        'render_callback' => [__CLASS__, 'render_booking_form_block']
      ]);
      return;
    }

    $src_json = BP_PLUGIN_PATH . 'blocks/src/book-form/block.json';
    if (!file_exists($src_json)) return;

    $asset_file = BP_PLUGIN_PATH . 'blocks/build/book-form/index.asset.php';
    $deps = [];
    $ver = self::VERSION;

    if (file_exists($asset_file)) {
      $asset = include $asset_file;
      $deps = $asset['dependencies'] ?? [];
      $ver  = $asset['version'] ?? self::VERSION;
    }

    wp_register_script(
      'bp-book-form-block',
      BP_PLUGIN_URL . 'blocks/build/book-form/index.js',
      $deps,
      $ver,
      true
    );

    register_block_type($src_json, [
      'editor_script'   => 'bp-book-form-block',
      'render_callback' => [__CLASS__, 'render_booking_form_block']
    ]);
  }

  public static function render_booking_form_block(array $attributes) : string {
    $service_id = isset($attributes['serviceId']) ? absint($attributes['serviceId']) : 0;
    if ($service_id <= 0) {
      return '<p>' . esc_html__('BookPoint: Service ID is required.', 'bookpoint') . '</p>';
    }

    $default_date = isset($attributes['defaultDate']) ? sanitize_text_field($attributes['defaultDate']) : '';
    $hide_notes = !empty($attributes['hideNotes']) ? 1 : 0;
    $require_phone = !empty($attributes['requirePhone']) ? 1 : 0;
    $compact = !empty($attributes['compact']) ? 1 : 0;

    return do_shortcode(sprintf(
      '[bookPoint service_id="%d" default_date="%s" hide_notes="%d" require_phone="%d" compact="%d"]',
      $service_id,
      esc_attr($default_date),
      $hide_notes,
      $require_phone,
      $compact
    ));
  }

  public static function register_rest_routes() : void {
    register_rest_route('bp/v1', '/categories', [
      'methods' => 'GET',
      'permission_callback' => '__return_true',
      'callback' => 'bp_rest_get_categories',
    ]);

    register_rest_route('bp/v1', '/services', [
      'methods'  => 'GET',
      'callback' => 'bp_rest_get_services',
      'permission_callback' => '__return_true',
    ]);

    register_rest_route('bp/v1', '/extras', [
      'methods' => 'GET',
      'permission_callback' => '__return_true',
      'callback' => 'bp_rest_get_extras',
    ]);

    // Step 16: Agents endpoint
    register_rest_route('bp/v1', '/agents', [
      'methods' => 'GET',
      'callback' => 'bp_rest_get_agents',
      'permission_callback' => '__return_true',
    ]);

    register_rest_route('bp/v1', '/booking/create', [
      'methods' => 'POST',
      'permission_callback' => '__return_true',
      'callback' => 'bp_rest_create_booking',
    ]);

    register_rest_route('bp/v1', '/promo/validate', [
      'methods' => 'GET',
      'permission_callback' => '__return_true',
      'callback' => 'bp_rest_validate_promo',
    ]);

    register_rest_route('bp/v1', '/form-fields', [
      'methods' => 'GET',
      'permission_callback' => '__return_true',
      'callback' => 'bp_rest_get_form_fields',
    ]);

    // Step 18: Service agents endpoint
    register_rest_route('bp/v1', '/service-agents', [
      'methods' => 'GET',
      'callback' => [__CLASS__, 'rest_get_service_agents'],
      'permission_callback' => '__return_true',
    ]);

    // Step 21: Manage booking slots endpoint
    register_rest_route('bp/v1', '/manage/slots', [
      'methods' => 'GET',
      'callback' => [__CLASS__, 'rest_manage_slots'],
      'permission_callback' => '__return_true',
    ]);

    // ----------------------------
    // Admin: Agents list (React UI)
    // GET /wp-json/bp/v1/admin/agents
    // ----------------------------
    register_rest_route('bp/v1', '/admin/agents', [
      'methods'  => 'GET',
      'callback' => function(\WP_REST_Request $req){

        if (!current_user_can('administrator') && !current_user_can('bp_manage_settings') && !current_user_can('bp_manage_bookings')) {
          return new \WP_REST_Response(['status'=>'error','message'=>'Forbidden'], 403);
        }

        global $wpdb;
        $tA = $wpdb->prefix . 'bp_agents';

        $rows = $wpdb->get_results("SELECT * FROM {$tA} ORDER BY id DESC", ARRAY_A) ?: [];

        $agents = array_map(function($a){
          $first = $a['first_name'] ?? '';
          $last  = $a['last_name'] ?? '';
          $name  = trim($first . ' ' . $last);
          if(!$name) $name = $a['name'] ?? ($a['full_name'] ?? ('Agent #'.$a['id']));

          return [
            'id' => (int)$a['id'],
            'name' => $name,
          ];
        }, $rows);

        return new \WP_REST_Response(['status'=>'success','data'=>$agents], 200);
      }
    ]);

    // ----------------------------
    // Admin: Bookings list (React UI)
    // GET /wp-json/bp/v1/admin/bookings?q=&status=&sort=&date_from=&date_to=&page=&per=
    // ----------------------------
    register_rest_route('bp/v1', '/admin/bookings', [
      'methods'  => 'GET',
      'callback' => function(\WP_REST_Request $req){

        if (!current_user_can('administrator') && !current_user_can('bp_manage_bookings')) {
          return new \WP_REST_Response(['status'=>'error','message'=>'Forbidden'], 403);
        }

        global $wpdb;
        $b = $wpdb->prefix . 'bp_bookings';
        $c = $wpdb->prefix . 'bp_customers';
        $s = $wpdb->prefix . 'bp_services';
        $a = $wpdb->prefix . 'bp_agents';

        $q        = sanitize_text_field($req->get_param('q') ?? '');
        $status   = sanitize_text_field($req->get_param('status') ?? 'all');
        $sort     = sanitize_text_field($req->get_param('sort') ?? 'desc'); // desc|asc
        $dateFrom = sanitize_text_field($req->get_param('date_from') ?? ''); // YYYY-MM-DD
        $dateTo   = sanitize_text_field($req->get_param('date_to') ?? '');   // YYYY-MM-DD

        $page   = max(1, (int)($req->get_param('page') ?? 1));
        $per    = min(50, max(10, (int)($req->get_param('per') ?? 20)));
        $offset = ($page - 1) * $per;

        $where  = "WHERE 1=1 ";
        $params = [];

        if ($status && $status !== 'all') {
          $where .= " AND LOWER(b.status) = %s ";
          $params[] = strtolower($status);
        }

        // date range filter
        if ($dateFrom && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
          $where .= " AND b.start_datetime >= %s ";
          $params[] = $dateFrom . " 00:00:00";
        }
        if ($dateTo && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
          $where .= " AND b.start_datetime <= %s ";
          $params[] = $dateTo . " 23:59:59";
        }

        if ($q) {
          $like = '%' . $wpdb->esc_like($q) . '%';
          $where .= " AND (
            CONCAT(cust.first_name, ' ', cust.last_name) LIKE %s OR
            cust.email LIKE %s OR
            srv.name LIKE %s OR
            ag.first_name LIKE %s OR
            ag.last_name LIKE %s
          ) ";
          array_push($params, $like, $like, $like, $like, $like);
        }

        $order = (strtolower($sort) === 'asc') ? 'ASC' : 'DESC';

        // total count
        $sqlCount = "SELECT COUNT(*) FROM {$b} b
          LEFT JOIN {$c} cust ON b.customer_id = cust.id
          LEFT JOIN {$s} srv ON b.service_id = srv.id
          LEFT JOIN {$a} ag ON b.agent_id = ag.id
          {$where}";
        $total = (int) ($params ? $wpdb->get_var($wpdb->prepare($sqlCount, $params)) : $wpdb->get_var($sqlCount));

        // rows
        $sql = "SELECT
                  b.id,
                  b.start_datetime,
                  b.end_datetime,
                  b.status,
                  CONCAT(cust.first_name, ' ', cust.last_name) as customer_name,
                  cust.email as customer_email,
                  srv.name as service_name,
                  CONCAT(ag.first_name, ' ', ag.last_name) as agent_name
                FROM {$b} b
                LEFT JOIN {$c} cust ON b.customer_id = cust.id
                LEFT JOIN {$s} srv ON b.service_id = srv.id
                LEFT JOIN {$a} ag ON b.agent_id = ag.id
                {$where}
                ORDER BY b.start_datetime {$order}
                LIMIT {$per} OFFSET {$offset}";

        $items = $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
        if (!$items) $items = [];

        return new \WP_REST_Response([
          'status' => 'success',
          'data' => [
            'items' => $items,
            'total' => $total,
            'page'  => $page,
            'per'   => $per,
            'sort'  => $order,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
          ]
        ], 200);
      }
    ]);

    // ----------------------------
    // Admin: Booking details (drawer)
    // GET /wp-json/bp/v1/admin/bookings/{id}
    // ----------------------------
    register_rest_route('bp/v1', '/admin/bookings/(?P<id>\d+)', [
      'methods'  => 'GET',
      'callback' => function(\WP_REST_Request $req){

        if (!current_user_can('administrator') && !current_user_can('bp_manage_bookings')) {
          return new \WP_REST_Response(['status'=>'error','message'=>'Forbidden'], 403);
        }

        global $wpdb;
        $id = (int) $req['id'];

        $b = $wpdb->prefix . 'bp_bookings';
        $c = $wpdb->prefix . 'bp_customers';
        $s = $wpdb->prefix . 'bp_services';
        $a = $wpdb->prefix . 'bp_agents';
        $tFields   = $wpdb->prefix . 'bp_form_fields';

        // Get booking with all JOINs for complete data
        $sql = "SELECT
                  b.*,
                  CONCAT(cust.first_name, ' ', cust.last_name) as customer_name,
                  cust.email as customer_email,
                  cust.phone as customer_phone,
                  srv.name as service_name,
                  srv.price_cents as service_price_cents,
                  CONCAT(ag.first_name, ' ', ag.last_name) as agent_name
                FROM {$b} b
                LEFT JOIN {$c} cust ON b.customer_id = cust.id
                LEFT JOIN {$s} srv ON b.service_id = srv.id
                LEFT JOIN {$a} ag ON b.agent_id = ag.id
                WHERE b.id = %d";

        $row = $wpdb->get_row($wpdb->prepare($sql, $id), ARRAY_A);
        if (!$row) {
          return new \WP_REST_Response(['status'=>'error','message'=>'Booking not found'], 404);
        }

        // Helper to read json columns safely
        $read_json = function($val){
          if (!$val) return null;
          if (is_array($val)) return $val;
          $decoded = json_decode($val, true);
          return (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
        };

        // Extract customer info
        $customer = [
          'name'  => trim(($row['customer_name'] ?? '') . ''),
          'email' => $row['customer_email'] ?? '',
          'phone' => $row['customer_phone'] ?? '',
        ];

        // Extract service info
        $service = [
          'name' => $row['service_name'] ?? '',
          'id'   => isset($row['service_id']) ? (int)$row['service_id'] : null,
        ];

        // Extract agent info
        $agent = [
          'name' => $row['agent_name'] ?? '',
          'id'   => isset($row['agent_id']) ? (int)$row['agent_id'] : null,
        ];

        // Order items - try multiple column names
        $order_items =
          $read_json($row['order_items_json'] ?? null)
          ?? $read_json($row['order_items'] ?? null)
          ?? $read_json($row['items_json'] ?? null)
          ?? $read_json($row['extras_json'] ?? null)
          ?? [];

        // Form answers - try multiple column names
        $form_answers =
          $read_json($row['form_answers_json'] ?? null)
          ?? $read_json($row['form_data_json'] ?? null)
          ?? $read_json($row['booking_data_json'] ?? null)
          ?? $read_json($row['booking_fields_json'] ?? null)
          ?? $read_json($row['customer_fields_json'] ?? null)
          ?? $read_json($row['custom_fields_json'] ?? null)
          ?? $read_json($row['meta_json'] ?? null)
          ?? [];

        // Extract pricing info
        $pricing = [
          'currency' => $row['currency'] ?? 'USD',
          'subtotal' => isset($row['total_price']) ? (float)$row['total_price'] : null,
          'discount_total' => isset($row['discount_total']) ? (float)$row['discount_total'] : null,
          'tax_total' => isset($row['tax_total']) ? (float)$row['tax_total'] : null,
          'total' => isset($row['total_price']) ? (float)$row['total_price'] : null,
          'promo_code' => $row['promo_code'] ?? '',
        ];

        // Form field definitions
        $field_defs = [];
        $tables = $wpdb->get_col("SHOW TABLES");
        if (in_array($tFields, $tables, true)) {
          $defs = $wpdb->get_results("SELECT * FROM {$tFields} ORDER BY sort_order ASC, id ASC LIMIT 100", ARRAY_A) ?: [];
          foreach($defs as $d){
            $field_defs[] = [
              'key'   => $d['field_key'] ?? ($d['slug'] ?? ($d['name'] ?? ('field_'.$d['id']))),
              'label' => $d['label'] ?? ($d['title'] ?? ($d['name'] ?? 'Field')),
              'type'  => $d['type'] ?? 'text',
              'scope' => $d['scope'] ?? ($d['context'] ?? ''),
            ];
          }
        }

        return new \WP_REST_Response([
          'status' => 'success',
          'data' => [
            'booking' => [
              'id' => (int)$row['id'],
              'status' => $row['status'] ?? 'pending',
              'start_datetime' => $row['start_datetime'] ?? '',
              'end_datetime'   => $row['end_datetime'] ?? '',
              'created_at'     => $row['created_at'] ?? '',
              'notes'          => $row['notes'] ?? '',
            ],
            'customer' => $customer,
            'service'  => $service,
            'agent'    => $agent,
            'order_items' => $order_items,
            'pricing' => $pricing,
            'form_answers' => $form_answers,
            'form_fields' => $field_defs
          ]
        ], 200);
      }
    ]);

    // ----------------------------
    // Admin: Update booking status
    // POST /wp-json/bp/v1/admin/bookings/{id}/status
    // body: { status: pending|confirmed|cancelled }
    // ----------------------------
    register_rest_route('bp/v1', '/admin/bookings/(?P<id>\d+)/status', [
      'methods'  => 'POST',
      'callback' => function(\WP_REST_Request $req){

        if (!current_user_can('administrator') && !current_user_can('bp_manage_bookings')) {
          return new \WP_REST_Response(['status'=>'error','message'=>'Forbidden'], 403);
        }

        global $wpdb;
        $id = (int) $req['id'];
        $status = sanitize_text_field($req->get_param('status') ?? '');

        $allowed = ['pending','confirmed','cancelled'];
        if (!in_array($status, $allowed, true)) {
          return new \WP_REST_Response(['status'=>'error','message'=>'Invalid status'], 400);
        }

        $t = $wpdb->prefix . 'bp_bookings';
        $wpdb->update($t, ['status'=>$status], ['id'=>$id], ['%s'], ['%d']);

        return new \WP_REST_Response(['status'=>'success'], 200);
      }
    ]);
  }

  public static function rest_get_services(\WP_REST_Request $request) {
    return bp_rest_get_services($request);
  }

  public static function rest_get_agents(\WP_REST_Request $request) {
    return bp_rest_get_agents($request);
  }

  public static function rest_get_service_agents(\WP_REST_Request $request) {
    $service_id = absint($request->get_param('service_id'));
    if ($service_id <= 0) {
      return rest_ensure_response([
        'status' => 'success',
        'data' => [],
      ]);
    }

    $agents = BP_ServiceAgentModel::get_agents_for_service($service_id);
    $items = [];

    foreach ($agents as $a) {
      $items[] = [
        'id' => (int)$a['id'],
        'name' => BP_AgentModel::display_name($a),
      ];
    }

    return rest_ensure_response([
      'status' => 'success',
      'data' => $items,
    ]);
  }

  public static function rest_manage_slots(\WP_REST_Request $req) {
    $service_id = absint($req->get_param('service_id'));
    $agent_id   = absint($req->get_param('agent_id'));
    $date       = sanitize_text_field($req->get_param('date'));
    $exclude_id = absint($req->get_param('exclude_booking_id'));

    if ($service_id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
      return rest_ensure_response(['status' => 'success', 'data' => []]);
    }

    $service = BP_ServiceModel::find($service_id);
    if (!$service) return rest_ensure_response(['status' => 'success', 'data' => []]);

    $duration = (int)($service['duration_minutes'] ?? 60);

    $slots = BP_AvailabilityHelper::get_available_slots_for_date(
      $service_id,
      $date,
      $duration,
      $agent_id,
      $exclude_id
    );

    return rest_ensure_response(['status' => 'success', 'data' => $slots]);
  }

  public static function register_admin_menu() : void {
    if (!current_user_can('bp_manage_bookings') &&
        !current_user_can('bp_manage_services') &&
        !current_user_can('bp_manage_customers') &&
        !current_user_can('bp_manage_agents') &&
        !current_user_can('bp_manage_settings') &&
        !current_user_can('bp_manage_tools') &&
        !current_user_can('manage_options')) {
      return;
    }

    add_menu_page(
      __('BookPoint', 'bookpoint'),
      __('BookPoint', 'bookpoint'),
      'bp_manage_settings',
      'bp_dashboard',
      'bp_render_admin_app',
      'dashicons-calendar-alt',
      56
    );

    add_submenu_page(
      'bp_dashboard',
      __('Dashboard', 'bookpoint'),
      __('Dashboard', 'bookpoint'),
      'bp_manage_settings',
      'bp_dashboard',
      'bp_render_admin_app',
      0
    );

    add_submenu_page(
      'bp',
      __('Bookings', 'bookpoint'),
      __('Bookings', 'bookpoint'),
      'bp_manage_settings',
      'bp_bookings',
      'bp_render_admin_app'
    );

    add_submenu_page(
      null,
      __('Booking Edit', 'bookpoint'),
      __('Booking Edit', 'bookpoint'),
      'bp_manage_settings',
      'bp_bookings_edit',
      'bp_render_admin_app'
    );

    add_submenu_page(
      'bp',
      __('Calendar', 'bookpoint'),
      __('Calendar', 'bookpoint'),
      'bp_manage_settings',
      'bp_calendar',
      'bp_render_admin_app'
    );

    add_submenu_page(
      'bp',
      __('Schedule', 'bookpoint'),
      __('Schedule', 'bookpoint'),
      'bp_manage_settings',
      'bp_schedule',
      'bp_render_admin_app'
    );

    add_submenu_page(
      'bp',
      __('Holidays', 'bookpoint'),
      __('Holidays', 'bookpoint'),
      'bp_manage_settings',
      'bp_holidays',
      'bp_render_admin_app'
    );


    add_submenu_page(
      'bp_dashboard',
      __('Catalog', 'bookpoint'),
      __('Catalog', 'bookpoint'),
      'bp_manage_services',
      'bp_catalog',
      'bp_render_admin_app_catalog'
    );

    add_submenu_page(
      'bp',
      __('Services', 'bookpoint'),
      __('Services', 'bookpoint'),
      'bp_manage_settings',
      'bp_services',
      'bp_render_admin_app'
    );

    add_submenu_page(
      'bp',
      __('Categories', 'bookpoint'),
      __('Categories', 'bookpoint'),
      'bp_manage_settings',
      'bp_categories',
      'bp_render_admin_app'
    );

    add_submenu_page(
      'bp',
      __('Service Extras', 'bookpoint'),
      __('Service Extras', 'bookpoint'),
      'bp_manage_settings',
      'bp_extras',
      'bp_render_admin_app'
    );

    add_submenu_page(
      'bp_dashboard',
      __('Locations', 'bookpoint'),
      __('Locations', 'bookpoint'),
      'bp_manage_settings',
      'bp_locations',
      'bp_render_admin_app'
    );

    add_submenu_page(
      'bp',
      __('Promo Codes', 'bookpoint'),
      __('Promo Codes', 'bookpoint'),
      'bp_manage_settings',
      'bp_promo_codes',
      'bp_render_admin_app'
    );

    add_submenu_page(
      'bp',
      __('Form Fields', 'bookpoint'),
      __('Form Fields', 'bookpoint'),
      'bp_manage_settings',
      'bp-form-fields',
      'bp_render_admin_app'
    );
    add_submenu_page(
      'bp',
      __('Form Fields', 'bookpoint'),
      __('Form Fields', 'bookpoint'),
      'bp_manage_settings',
      'bp_form_fields',
      'bp_render_admin_app'
    );

    add_submenu_page(
      'bp',
      __('Booking Form Designer', 'bookpoint'),
      __('Booking Form Designer', 'bookpoint'),
      'bp_manage_settings',
      'bp_design_form',
      'bp_render_admin_app'
    );

    add_submenu_page(
      'bp',
      __('Form Fields', 'bookpoint'),
      __('Form Fields', 'bookpoint'),
      'bp_manage_settings',
      'bp_form_fields_edit',
      [__CLASS__, 'render_form_fields_edit']
    );

    add_submenu_page(
      'bp',
      __('Customers', 'bookpoint'),
      __('Customers', 'bookpoint'),
      'bp_manage_settings',
      'bp_customers',
      'bp_render_admin_app'
    );

    add_submenu_page(
      'bp',
      __('Settings', 'bookpoint'),
      __('Settings', 'bookpoint'),
      'bp_manage_settings',
      'bp_settings',
      'bp_render_admin_app'
    );

    add_submenu_page(
      'bp',
      __('Notifications', 'bookpoint'),
      __('Notifications', 'bookpoint'),
      'bp_manage_settings',
      'bp_notifications',
      'bp_render_admin_app'
    );

    add_submenu_page(
      'bp',
      __('Audit Log', 'bookpoint'),
      __('Audit Log', 'bookpoint'),
      'bp_manage_settings',
      'bp_audit',
      'bp_render_admin_app'
    );

    add_submenu_page(
      'bp',
      __('Tools', 'bookpoint'),
      __('Tools', 'bookpoint'),
      'bp_manage_settings',
      'bp_tools',
      'bp_render_admin_app'
    );

    add_submenu_page(
      'tools.php',
      __('BookPoint Tools', 'bookpoint'),
      __('BookPoint Tools', 'bookpoint'),
      'bp_manage_settings',
      'bp_tools',
      'bp_render_admin_app'
    );

    add_submenu_page(
      'bp',
      __('Agents', 'bookpoint'),
      __('Agents', 'bookpoint'),
      'bp_manage_settings',
      'bp_agents',
      'bp_render_admin_app'
    );

    // Hidden pages for internal use
      add_submenu_page(
        null,
        __('Edit Agent', 'bookpoint'),
        __('Edit Agent', 'bookpoint'),
        'bp_manage_agents',
        'bp_agents_edit',
        [__CLASS__, 'render_agents_edit']
      );

      add_submenu_page(
        null,
        __('Edit Location', 'bookpoint'),
        __('Edit Location', 'bookpoint'),
        'bp_manage_settings',
        'bp_locations_edit',
        'bp_render_admin_app'
      );

      add_submenu_page(
        null,
        __('Edit Location Category', 'bookpoint'),
        __('Edit Location Category', 'bookpoint'),
        'bp_manage_settings',
        'bp_location_categories_edit',
        'bp_render_admin_app'
      );

    add_submenu_page(
      null,
      __('Delete Agent', 'bookpoint'),
      __('Delete Agent', 'bookpoint'),
      'bp_manage_agents',
      'bp_agents_delete',
      [__CLASS__, 'render_agents_delete']
    );

    add_submenu_page(
      null,
      __('Edit Service', 'bookpoint'),
      __('Edit Service', 'bookpoint'),
      'bp_manage_services',
      'bp_services_edit',
      [__CLASS__, 'render_services_edit']
    );

    add_submenu_page(
      null,
      __('Edit Extra', 'bookpoint'),
      __('Edit Extra', 'bookpoint'),
      'bp_manage_services',
      'bp_extras_edit',
      [__CLASS__, 'render_extras_edit']
    );

    add_submenu_page(
      null,
      __('Delete Extra', 'bookpoint'),
      __('Delete Extra', 'bookpoint'),
      'bp_manage_services',
      'bp_extras_delete',
      [__CLASS__, 'render_extras_delete']
    );

    add_submenu_page(
      null,
      __('Edit Category', 'bookpoint'),
      __('Edit Category', 'bookpoint'),
      'bp_manage_services',
      'bp_categories_edit',
      [__CLASS__, 'render_categories_edit']
    );

    add_submenu_page(
      null,
      __('Delete Category', 'bookpoint'),
      __('Delete Category', 'bookpoint'),
      'bp_manage_services',
      'bp_categories_delete',
      [__CLASS__, 'render_categories_delete']
    );

    add_submenu_page(
      null,
      __('Delete Service', 'bookpoint'),
      __('Delete Service', 'bookpoint'),
      'bp_manage_services',
      'bp_services_delete',
      [__CLASS__, 'render_services_delete']
    );

    add_submenu_page(
      null,
      __('Edit Promo Code', 'bookpoint'),
      __('Edit Promo Code', 'bookpoint'),
      'bp_manage_settings',
      'bp_promo_codes_edit',
      [__CLASS__, 'render_promo_codes_edit']
    );

    add_submenu_page(
      null,
      __('Delete Promo Code', 'bookpoint'),
      __('Delete Promo Code', 'bookpoint'),
      'bp_manage_settings',
      'bp_promo_codes_delete',
      [__CLASS__, 'render_promo_codes_delete']
    );

    add_submenu_page(
      null,
      __('Confirm Booking', 'bookpoint'),
      __('Confirm Booking', 'bookpoint'),
      'bp_manage_bookings',
      'bp_booking_confirm',
      [__CLASS__, 'render_booking_confirm']
    );

    add_submenu_page(
      null,
      __('Cancel Booking', 'bookpoint'),
      __('Cancel Booking', 'bookpoint'),
      'bp_manage_bookings',
      'bp_booking_cancel',
      [__CLASS__, 'render_booking_cancel']
    );

    add_submenu_page(
      null,
      __('View Customer', 'bookpoint'),
      __('View Customer', 'bookpoint'),
      'bp_manage_customers',
      'bp_customers_view',
      [__CLASS__, 'render_customer_view']
    );

    // Hidden page for internal use (React)
    add_submenu_page(
      null,
      __('Edit Customer', 'bookpoint'),
      __('Edit Customer', 'bookpoint'),
      'bp_manage_customers',
      'bp_customers_edit',
      'bp_render_admin_app'
    );
  }

  public static function enqueue_admin_assets(string $hook): void {
    if (empty($_GET['page'])) return;

    $page = sanitize_text_field($_GET['page']);

    wp_enqueue_style(
      'bp-admin-ui',
      plugins_url('public/admin-ui.css', BP_PLUGIN_FILE),
      [],
      '1.0.0'
    );

    if (strpos($page, 'bp') === 0) {
      // Ensure WP Dashicons are available for sidebar icons
      wp_enqueue_style('dashicons');

      $admin_app_css = BP_PLUGIN_PATH . 'public/admin-app.css';
      $admin_app_ver = file_exists($admin_app_css) ? (string)@filemtime($admin_app_css) : self::VERSION;
      wp_enqueue_style(
        'bp-admin-app-css',
        plugin_dir_url(__FILE__) . 'public/admin-app.css',
        [],
        $admin_app_ver
      );
    }

    // React admin bundle (All admin pages)
        $admin_react_pages = [
          'bp_dashboard', 'bp_bookings', 'bp_bookings_edit', 'bp_calendar', 'bp_schedule', 'bp_holidays', 'bp_catalog',
          'bp-form-fields', 'bp_form_fields', 'bp_services', 'bp_services_edit', 'bp_categories', 'bp_categories_edit', 'bp_extras', 'bp_extras_edit', 'bp_locations', 'bp_promo_codes',
          'bp_customers', 'bp_settings', 'bp_notifications', 'bp_agents', 'bp_audit', 'bp_tools',
          'bp_locations_edit', 'bp_location_categories_edit', 'bp_design_form',
          'bp_agents_edit',
          'bp_customers_edit'
        ];
    
    if (in_array($page, $admin_react_pages, true)) {
      $asset_path = BP_PLUGIN_PATH . 'build/admin.asset.php';
      $asset = [
        'dependencies' => ['react', 'react-dom', 'react-jsx-runtime'],
        'version' => self::VERSION,
      ];

      if (file_exists($asset_path)) {
        $asset = require $asset_path;
      }

      self::ensure_react_scripts();

      $admin_js_path = BP_PLUGIN_PATH . 'build/admin.js';
      $admin_ver = file_exists($admin_js_path) ? (string)@filemtime($admin_js_path) : (string)($asset['version'] ?? self::VERSION);

      wp_enqueue_script(
        'bp-admin',
        BP_PLUGIN_URL . 'build/admin.js',
        $asset['dependencies'],
        $admin_ver,
        true
      );

        $admin_css = BP_PLUGIN_PATH . 'build/index.jsx.css';
        if (file_exists($admin_css)) {
          wp_enqueue_style(
            'bp-admin',
            BP_PLUGIN_URL . 'build/index.jsx.css',
            [],
            $admin_ver
          );

          if (function_exists('is_rtl') && is_rtl()) {
            $admin_css_rtl = BP_PLUGIN_PATH . 'build/index.jsx-rtl.css';
            if (file_exists($admin_css_rtl)) {
              wp_enqueue_style(
                'bp-admin-rtl',
                BP_PLUGIN_URL . 'build/index.jsx-rtl.css',
                ['bp-admin'],
                $admin_ver
              );
            }
          }
        }

      add_filter('script_loader_src', function ($src, $handle) use ($admin_ver) {
        if ($handle === 'bp-admin') {
          return add_query_arg('v', $admin_ver, $src);
        }
        return $src;
      }, 10, 2);
      add_filter('style_loader_src', function ($src, $handle) use ($admin_ver) {
        if ($handle === 'bp-admin') {
          return add_query_arg('v', $admin_ver, $src);
        }
        return $src;
      }, 10, 2);

      // Map page slug to route name
        $route_map = [
          'bp_dashboard' => 'dashboard',
          'bp_bookings' => 'bookings',
          'bp_bookings_edit' => 'bookings-edit',
          'bp_calendar' => 'calendar',
          'bp_schedule' => 'schedule',
          'bp_holidays' => 'holidays',
          'bp_catalog' => 'catalog',
          'bp-form-fields' => 'form-fields',
          'bp_form_fields' => 'form-fields',
          'bp_design_form' => 'design-form',
          'bp_services' => 'services',
          'bp_categories' => 'categories',
          'bp_categories_edit' => 'categories-edit',
          'bp_extras' => 'extras',
          'bp_extras_edit' => 'extras-edit',
          'bp_locations' => 'locations',
          'bp_locations_edit' => 'locations-edit',
          'bp_location_categories_edit' => 'location-categories-edit',
          'bp_promo_codes' => 'promo-codes',
          'bp_customers' => 'customers',
          'bp_customers_edit' => 'customers-edit',
        'bp_settings' => 'settings',
        'bp_notifications' => 'notifications',
        'bp_agents' => 'agents',
        'bp_agents_edit' => 'agents-edit',
        'bp_audit' => 'audit',
        'bp_tools' => 'tools',
      ];

      $route = $route_map[$page] ?? 'dashboard';

        wp_localize_script('bp-admin', 'BP_ADMIN', [
          'restUrl' => esc_url_raw(rest_url('bp/v1')),
          'nonce'   => wp_create_nonce('wp_rest'),
          'adminNonce' => wp_create_nonce('bp_admin'),
          'adminPostUrl' => admin_url('admin-post.php'),
          'pluginUrl' => BP_PLUGIN_URL,
          'publicImagesUrl' => BP_PLUGIN_URL . 'public/images',
          'publicIconsUrl' => BP_PLUGIN_URL . 'public/icons',
          'route'   => $route,
          'page'    => $page,
          'build'   => (file_exists(BP_PLUGIN_PATH . 'build/admin.js') ? (string)@filemtime(BP_PLUGIN_PATH . 'build/admin.js') : ''),
          'timezone'=> wp_timezone_string(),
        ]);

        wp_localize_script('bp-admin', 'bpAdmin', [
          'iconsUrl' => BP_PLUGIN_URL . 'public/icons',
        ]);

      wp_enqueue_media();
      return;
    }

    if ($page === 'bp_categories_edit' || ($page === 'bp_categories' && isset($_GET['action']) && $_GET['action'] === 'edit')) {
      wp_enqueue_media();
      wp_enqueue_script('bp-admin-media', BP_PLUGIN_URL . 'public/admin-media.js', ['jquery'], self::VERSION, true);
      return;
    }

    if ($page === 'bp_services_edit' || ($page === 'bp_services' && isset($_GET['action']) && $_GET['action'] === 'edit')) {
      wp_enqueue_media();
      wp_enqueue_script('bp-admin-service-media', BP_PLUGIN_URL . 'public/admin-service-media.js', ['jquery'], self::VERSION, true);
      return;
    }

    if ($page === 'bp_extras_edit' || ($page === 'bp_extras' && isset($_GET['action']) && $_GET['action'] === 'edit')) {
      wp_enqueue_media();
      return;
    }

    if ($page === 'bp_agents_edit' || ($page === 'bp_agents' && isset($_GET['action']) && $_GET['action'] === 'edit')) {
      wp_enqueue_media();
      // React edit screens use window.wp.media directly; keep only the core media library enqueued.
      return;
    }
  }

  public static function render_dashboard() : void {
    echo '<div id="bp-admin-app" data-route="dashboard"></div>';
  }

  public static function render_services_index() : void {
    (new BP_AdminServicesController())->index();
  }

  public static function render_services_edit() : void {
    echo '<div id="bp-admin-app" data-route="services-edit"></div>';
  }

  public static function render_extras_edit() : void {
    echo '<div id="bp-admin-app" data-route="extras-edit"></div>';
  }

  public static function render_extras_delete() : void {
    (new BP_AdminExtrasController())->delete();
  }

  public static function render_categories_edit() : void {
    echo '<div id="bp-admin-app" data-route="categories-edit"></div>';
  }

  public static function render_categories_delete() : void {
    (new BP_AdminCategoriesController())->delete();
  }

  public static function render_services_delete() : void {
    (new BP_AdminServicesController())->delete();
  }

  public static function render_promo_codes_edit() : void {
    (new BP_AdminPromoCodesController())->edit();
  }

  public static function render_promo_codes_delete() : void {
    (new BP_AdminPromoCodesController())->delete();
  }

  public static function handle_services_save() : void {
    (new BP_AdminServicesController())->save();
  }

  public static function render_settings() : void {
    (new BP_AdminSettingsController())->index();
  }

  public static function handle_settings_save() : void {
    (new BP_AdminSettingsController())->save();
  }

  public static function handle_settings_save_license(): void {
    (new BP_AdminSettingsController())->save_license();
  }

  public static function handle_settings_validate_license(): void {
    (new BP_AdminSettingsController())->validate_license();
  }

  public static function handle_settings_export_json(): void {
    (new BP_AdminSettingsController())->export_json();
  }

  public static function handle_settings_import_json(): void {
    (new BP_AdminSettingsController())->import_json();
  }

  public static function render_bookings() : void {
    (new BP_AdminBookingsController())->index();
  }

  public static function render_calendar() : void {
    echo '<div id="bp-admin-app" data-route="calendar"></div>';
  }

  public static function render_schedule() : void {
    echo '<div id="bp-admin-app" data-route="schedule"></div>';
  }

  public static function render_holidays() : void {
    echo '<div id="bp-admin-app" data-route="holidays"></div>';
  }

  public static function render_form_fields() : void {
    echo '<div id="bp-admin-app" data-route="form-fields"></div>';
  }

  public static function render_form_fields_edit() : void {
    (new BP_AdminFormFieldsController())->edit();
  }

  public static function render_booking_confirm() : void {
    (new BP_AdminBookingsController())->confirm();
  }

  public static function render_booking_cancel() : void {
    (new BP_AdminBookingsController())->cancel();
  }

  public static function handle_booking_notes_save() : void {
    (new BP_AdminBookingsController())->save_notes();
  }

  public static function render_customers() : void {
    (new BP_AdminCustomersController())->index();
  }

  public static function render_customer_view() : void {
    (new BP_AdminCustomersController())->view();
  }

  public static function enqueue_public_assets(bool $force = false): void {
    static $cache_bust_added = false;
    if (!$force) {
      if (!is_singular()) return;

      global $post;
      if (
        !$post ||
        (!has_shortcode($post->post_content, 'bookPoint') &&
          !has_shortcode($post->post_content, 'bookpoint') &&
          !has_shortcode($post->post_content, 'BookPoint'))
      ) return;
    }

    $front_asset_path = BP_PLUGIN_PATH . 'public/front.asset.php';
    $front_asset = null;
    if (file_exists($front_asset_path)) {
      $front_asset = require $front_asset_path;
    }

    if (!$cache_bust_added) {
      $cache_bust_added = true;
      add_filter('script_loader_src', function ($src, $handle) {
        if ($handle === 'bp-front') {
          return add_query_arg('v', time(), $src);
        }
        return $src;
      }, 10, 2);
      add_filter('style_loader_src', function ($src, $handle) {
        if ($handle === 'bp-front') {
          return add_query_arg('v', time(), $src);
        }
        return $src;
      }, 10, 2);
    }

    $front_css = BP_PLUGIN_PATH . 'public/index.jsx.css';
    if (file_exists($front_css)) {
      $css_ver = @filemtime($front_css) ?: ($front_asset['version'] ?? self::VERSION);
      wp_enqueue_style(
        'bp-front',
        BP_PLUGIN_URL . 'public/index.jsx.css',
        [],
        $css_ver
      );
    }

    self::ensure_react_scripts();

    $front_js = BP_PLUGIN_PATH . 'public/front.js';
    $js_ver = file_exists($front_js) ? (@filemtime($front_js) ?: ($front_asset['version'] ?? self::VERSION)) : ($front_asset['version'] ?? self::VERSION);
    wp_enqueue_script(
      'bp-front',
      BP_PLUGIN_URL . 'public/front.js',
      $front_asset['dependencies'] ?? [],
      $js_ver,
      true
    );

    wp_localize_script('bp-front', 'BP_FRONT', self::front_localized_data());
  }

  private static function front_localized_data(): array {
    $stripe_pk = self::front_stripe_publishable_key();
    $currency = get_option('bp_currency', '');
    if ($currency === '') {
      $currency = BP_SettingsHelper::get_with_default('bp_default_currency');
    }
    return [
      'rest' => esc_url_raw(rest_url()),
      'restUrl' => esc_url_raw(rest_url('bp/v1')),
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'siteUrl' => site_url('/'),
      'nonce' => wp_create_nonce('wp_rest'),
      'images' => BP_PLUGIN_URL . 'public/images/',
      'tz' => wp_timezone_string(),
      'stripe_pk' => $stripe_pk,
      'currency' => $currency ?: 'DKK',
      'settings' => self::front_settings_payload(),
    ];
  }

  private static function front_stripe_publishable_key(): string {
    $pk = (string)get_option('bp_stripe_publishable_key', '');
    if ($pk !== '') return $pk;

    $settings = BP_SettingsHelper::get_all();
    $mode = $settings['stripe_mode'] ?? 'test';
    if ($mode === 'live') {
      return (string)($settings['stripe_live_publishable_key'] ?? '');
    }
    return (string)($settings['stripe_test_publishable_key'] ?? '');
  }

  private static function front_settings_payload(): array {
    $enabled = BP_SettingsHelper::get_with_default('payments_enabled_methods');
    if (!is_array($enabled)) {
      $enabled = ['cash'];
    }

    return [
      'currency' => BP_SettingsHelper::get_with_default('bp_default_currency'),
      'currency_position' => BP_SettingsHelper::get_with_default('bp_currency_position'),
      'payments_enabled_methods' => $enabled,
      'payments_default_method' => BP_SettingsHelper::get_with_default('payments_default_method'),
      'payments_require_payment_to_confirm' => BP_SettingsHelper::get_with_default('payments_require_payment_to_confirm'),
    ];
  }

  public static function render_agents() : void {
    (new BP_AdminAgentsController())->index();
  }

  public static function ensure_react_scripts() : void {
    if (!wp_script_is('react', 'registered')) {
      $react_path = BP_PLUGIN_PATH . 'public/vendor/react.production.min.js';
      if (file_exists($react_path)) {
        wp_register_script(
          'react',
          BP_PLUGIN_URL . 'public/vendor/react.production.min.js',
          [],
          self::VERSION,
          true
        );
      }
    }

    if (wp_script_is('react', 'registered')) {
      wp_add_inline_script(
        'react',
        "if(!window.ReactJSXRuntime&&window.React){window.ReactJSXRuntime={jsx:function(t,p,k){var x=p||{};if(k!==undefined)x.key=k;return window.React.createElement(t,x);},jsxs:function(t,p,k){var x=p||{};if(k!==undefined)x.key=k;return window.React.createElement(t,x);},Fragment:window.React.Fragment};}",
        'after'
      );
    }

    if (!wp_script_is('react-dom', 'registered')) {
      $react_dom_path = BP_PLUGIN_PATH . 'public/vendor/react-dom.production.min.js';
      if (file_exists($react_dom_path)) {
        wp_register_script(
          'react-dom',
          BP_PLUGIN_URL . 'public/vendor/react-dom.production.min.js',
          ['react'],
          self::VERSION,
          true
        );
      }
    }

    if (!wp_script_is('react-jsx-runtime', 'registered')) {
      $jsx_runtime_path = BP_PLUGIN_PATH . 'public/vendor/react-jsx-runtime.min.js';
      if (file_exists($jsx_runtime_path)) {
        wp_register_script(
          'react-jsx-runtime',
          BP_PLUGIN_URL . 'public/vendor/react-jsx-runtime.min.js',
          ['react'],
          self::VERSION,
          true
        );
      }
    }
  }

  public static function render_agents_edit() : void {
    echo '<div id="bp-admin-app" data-route="agents-edit"></div>';
  }

  public static function render_agents_delete() : void {
    (new BP_AdminAgentsController())->delete();
  }

  public static function handle_agents_save() : void {
    (new BP_AdminAgentsController())->save();
  }

  public static function render_audit_log() : void {
    (new BP_AdminAuditController())->index();
  }

  public static function render_tools() : void {
    (new BP_AdminToolsController())->index();
  }

  public static function handle_tools_email_test() : void {
    (new BP_AdminToolsController())->email_test();
  }

  public static function handle_tools_webhook_test() : void {
    (new BP_AdminToolsController())->webhook_test();
  }

  public static function handle_tools_generate_demo() : void {
    (new BP_AdminToolsController())->generate_demo();
  }

  public static function handle_tools_export_settings() : void {
    (new BP_AdminToolsController())->export_settings();
  }

  public static function handle_tools_import_settings() : void {
    (new BP_AdminToolsController())->import_settings();
  }

  public static function handle_license_save() : void {
    (new BP_AdminSettingsController())->license_save();
  }

  public static function handle_license_validate() : void {
    (new BP_AdminSettingsController())->license_validate();
  }

  public static function handle_bookings_export_csv() : void {
    (new BP_AdminBookingsController())->export_csv();
  }

  public static function handle_bookings_export_pdf() : void {
    (new BP_AdminBookingsController())->export_pdf();
  }

  public static function handle_customers_export_csv() : void {
    if (!current_user_can('bp_manage_customers') && !current_user_can('bp_manage_bookings') && !current_user_can('bp_manage_settings') && !current_user_can('manage_options')) {
      wp_die('No permission');
    }
    check_admin_referer('bp_admin');

    global $wpdb;
    $table = $wpdb->prefix . 'bp_customers';
    $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A) ?: [];

    $filename = 'bookpoint-customers-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $out = fopen('php://output', 'w');
    fputcsv($out, ['id','first_name','last_name','email','phone','wp_user_id','created_at','updated_at']);

    foreach ($rows as $r) {
      fputcsv($out, [
        $r['id'] ?? '',
        $r['first_name'] ?? '',
        $r['last_name'] ?? '',
        $r['email'] ?? '',
        $r['phone'] ?? '',
        $r['wp_user_id'] ?? '',
        $r['created_at'] ?? '',
        $r['updated_at'] ?? '',
      ]);
    }

    fclose($out);
    exit;
  }

  public static function handle_customers_import_csv() : void {
    if (!current_user_can('bp_manage_customers') && !current_user_can('bp_manage_bookings') && !current_user_can('bp_manage_settings') && !current_user_can('manage_options')) {
      wp_die('No permission');
    }
    check_admin_referer('bp_admin');

    if (empty($_FILES['csv']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
      wp_safe_redirect(admin_url('admin.php?page=bp_customers&import=0'));
      exit;
    }

    $file = $_FILES['csv']['tmp_name'];
    $handle = fopen($file, 'r');
    if (!$handle) {
      wp_safe_redirect(admin_url('admin.php?page=bp_customers&import=0'));
      exit;
    }

    $header = fgetcsv($handle);
    $map = [];
    if (is_array($header)) {
      foreach ($header as $i => $col) {
        $key = strtolower(trim($col));
        $map[$key] = $i;
      }
    }

    $count = 0;
    global $wpdb;
    $table = $wpdb->prefix . 'bp_customers';

    while (($row = fgetcsv($handle)) !== false) {
      if (!is_array($row) || count($row) === 0) continue;

      $first = $map['first_name'] ?? null;
      $last = $map['last_name'] ?? null;
      $emailIdx = $map['email'] ?? null;
      $phoneIdx = $map['phone'] ?? null;
      $nameIdx = $map['name'] ?? null;

      $first_name = $first !== null ? sanitize_text_field($row[$first] ?? '') : '';
      $last_name  = $last !== null ? sanitize_text_field($row[$last] ?? '') : '';
      $email      = $emailIdx !== null ? sanitize_email($row[$emailIdx] ?? '') : '';
      $phone      = $phoneIdx !== null ? sanitize_text_field($row[$phoneIdx] ?? '') : '';

      if (($first_name === '' && $last_name === '') && $nameIdx !== null) {
        $name = sanitize_text_field($row[$nameIdx] ?? '');
        if ($name !== '') {
          $parts = preg_split('/\s+/', $name, 2);
          $first_name = $parts[0] ?? '';
          $last_name = $parts[1] ?? '';
        }
      }

      if ($email === '' && $first_name === '' && $last_name === '' && $phone === '') continue;

      if ($email !== '') {
        $existing = BP_CustomerModel::find_by_email($email);
        if ($existing) {
          $wpdb->update($table, [
            'first_name' => $first_name !== '' ? $first_name : ($existing['first_name'] ?? null),
            'last_name'  => $last_name !== '' ? $last_name : ($existing['last_name'] ?? null),
            'phone'      => $phone !== '' ? $phone : ($existing['phone'] ?? null),
            'updated_at' => current_time('mysql'),
          ], ['id' => (int)$existing['id']], ['%s','%s','%s','%s'], ['%d']);
          $count++;
          continue;
        }
      }

      BP_CustomerModel::create([
        'first_name' => $first_name ?: null,
        'last_name'  => $last_name ?: null,
        'email'      => $email ?: null,
        'phone'      => $phone ?: null,
      ]);
      $count++;
    }

    fclose($handle);

    wp_safe_redirect(admin_url('admin.php?page=bp_customers&import=' . $count));
    exit;
  }

  public static function handle_customer_gdpr_delete() : void {
    (new BP_AdminCustomersController())->gdpr_delete();
  }

  public static function shortcode_book_form($atts) : string {
    $atts = shortcode_atts([
      'service_id' => 0,
      'default_date' => '',
      'hide_notes' => 0,
      'require_phone' => 0,
      'compact' => 0,
    ], $atts, 'bookPoint');

    $service_id = absint($atts['service_id']);

    // enqueue assets only when shortcode is used
    $front_js = BP_PLUGIN_PATH . 'public/front.js';
    if (file_exists($front_js)) {
      wp_enqueue_script('bp-front', BP_PLUGIN_URL . 'public/front.js', [], @filemtime($front_js) ?: self::VERSION, true);
      wp_localize_script('bp-front', 'BP_FRONT', self::front_localized_data());
    }

    $front_css = BP_PLUGIN_PATH . 'public/index.jsx.css';
    if (file_exists($front_css)) {
      wp_enqueue_style('bp-front', BP_PLUGIN_URL . 'public/index.jsx.css', [], @filemtime($front_css) ?: self::VERSION);
    }

    $nonce = wp_create_nonce('bp_public');

    $options = [
      'default_date' => sanitize_text_field($atts['default_date']),
      'hide_notes' => (int)$atts['hide_notes'] === 1,
      'require_phone' => (int)$atts['require_phone'] === 1,
      'compact' => (int)$atts['compact'] === 1,
      'allow_service_select' => ($service_id <= 0),
    ];

    ob_start();
    $controller = new class extends BP_Controller {};
    $controller->render('public/booking_form', [
      'service_id' => $service_id,
      'nonce' => $nonce,
      'options' => $options,
    ]);
    return (string) ob_get_clean();
  }

  public static function shortcode_customer_portal() : string {
    wp_enqueue_style('bp-portal', BP_PLUGIN_URL . 'public/stylesheets/portal.css', [], self::VERSION);

    self::rate_limit_or_block('portal_view', 120, 600);

    $step = sanitize_text_field($_GET['step'] ?? 'email');
    $email = sanitize_email($_GET['email'] ?? '');

    ob_start();

    if ($step === 'verify' && $email) {
      include BP_LIB_PATH . 'views/public/portal_verify.php';
    } elseif ($step === 'list' && $email) {
      include BP_LIB_PATH . 'views/public/portal_list.php';
    } else {
      include BP_LIB_PATH . 'views/public/portal_email.php';
    }

    return (string) ob_get_clean();
  }

  public static function handle_portal_posts() : void {
    if (empty($_POST['bp_portal_action'])) return;

    self::rate_limit_or_block('portal_action', 20, 600);

    $action = sanitize_text_field($_POST['bp_portal_action']);

    if ($action === 'send_otp') {
      if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'bp_portal_email')) return;

      $email = sanitize_email($_POST['bp_portal_email'] ?? '');
      if ($email && BP_PortalHelper::send_otp($email)) {
        wp_safe_redirect(add_query_arg(['step' => 'verify', 'email' => $email], site_url('/my-bookings/')));
        exit;
      }

      wp_safe_redirect(add_query_arg(['error' => 'send_failed'], site_url('/my-bookings/')));
      exit;
    }

    if ($action === 'verify_otp') {
      if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'bp_portal_verify')) return;

      $email = sanitize_email($_POST['bp_portal_email'] ?? '');
      $otp = preg_replace('/\D+/', '', (string)($_POST['bp_portal_otp'] ?? ''));

      $session = ($email && strlen($otp) === 6) ? BP_PortalHelper::verify_otp($email, $otp) : null;
      if ($session) {
        wp_safe_redirect(add_query_arg(['step' => 'list', 'email' => $email, 's' => $session], site_url('/my-bookings/')));
        exit;
      }

      wp_safe_redirect(add_query_arg(['step' => 'verify', 'email' => $email, 'error' => 'bad_code'], site_url('/my-bookings/')));
      exit;
    }
  }

  public static function ajax_slots() : void {
    (new BP_PublicBookingsController())->slots();
  }

  public static function ajax_submit_booking() : void {
    (new BP_PublicBookingsController())->submit();
  }

  public static function register_query_vars($vars) {
    $vars[] = 'bp_manage_booking';
    $vars[] = 'key';
    $vars[] = 'bp_action';
    return $vars;
  }

  public static function maybe_render_public_pages($wp) : void {
    // if not our page, ignore
    $is_manage = get_query_var('bp_manage_booking');
    if (!$is_manage) return;

    self::rate_limit_or_block('manage_view', 60, 600);

    // handle cancel action first
    $controller = new BP_PublicBookingsController();
    $controller->handle_manage_actions();

    // render manage booking page
    status_header(200);
    nocache_headers();

    // Basic wrapper using WP theme content
    add_filter('the_content', function($content) use ($controller) {
      ob_start();
      $controller->render_manage_page();
      return ob_get_clean();
    });

    // Let WP continue; the_content filter will output our page UI
  }

  public static function rate_limit_or_block(string $key, int $limit = 30, int $window_sec = 600) : void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $bucket = 'bp_rl_' . md5($key . '|' . $ip);

    $data = get_transient($bucket);
    if (!is_array($data)) {
      $data = ['count' => 0, 'start' => time()];
    }

    if (time() - (int)$data['start'] > $window_sec) {
      $data = ['count' => 0, 'start' => time()];
    }

    $data['count']++;
    set_transient($bucket, $data, $window_sec);

    if ((int)$data['count'] > $limit) {
      wp_die(esc_html__('Too many requests. Please try again later.', 'bookpoint'), 429);
    }
  }
}

BP_Plugin::init();

if (!function_exists('bp_shortcode_booking_form')) {
  function bp_shortcode_booking_form($atts = []) {
    $atts = shortcode_atts([
      'label' => __('Book Now', 'bookpoint'),
    ], $atts);

    if (class_exists('BP_Plugin')) {
      BP_Plugin::enqueue_public_assets(true);
      if (did_action('wp_footer')) {
        wp_print_styles('bp-front');
        wp_print_scripts('bp-front');
      }
    }

    ob_start(); ?>
    <button type="button" class="bp-book-btn bp-fallback-btn" data-bp-open="wizard">
      <?php echo esc_html($atts['label']); ?>
    </button>
    <div class="bp-front-root" data-bp-widget="wizard" data-bp-fallback="1" data-bp-label="<?php echo esc_attr($atts['label']); ?>"></div>
    <?php
    return ob_get_clean();
  }
}

if (!function_exists('bp_rest_get_categories')) {
  function bp_rest_get_categories(\WP_REST_Request $req) {
    $rows = BP_CategoryModel::all(['is_active' => 1]);

    $data = [];
    foreach ($rows as $c) {
      $img = !empty($c['image_id']) ? wp_get_attachment_image_url((int)$c['image_id'], 'thumbnail') : '';
      $data[] = [
        'id' => (int)$c['id'],
        'name' => (string)$c['name'],
        'image' => $img ?: '',
      ];
    }
    return rest_ensure_response(['status' => 'success', 'data' => $data]);
  }
}

if (!function_exists('bp_rest_get_services')) {
  function bp_rest_get_services(\WP_REST_Request $req) {
    $category_id = (int)$req->get_param('category_id');

    global $wpdb;
    $t = $wpdb->prefix . 'bp_services';
    $map = $wpdb->prefix . 'bp_service_categories';

    $params = [];
    if ($category_id > 0) {
      $sql = "
        SELECT s.id, s.name, s.price_cents, s.duration_minutes, s.category_id, s.image_id
        FROM {$t} s
        INNER JOIN {$map} m ON m.service_id = s.id
        WHERE s.is_active = 1 AND m.category_id = %d
        ORDER BY s.id DESC
        LIMIT 500
      ";
      $params[] = $category_id;
      $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    } else {
      $sql = "SELECT id, name, price_cents, duration_minutes, category_id, image_id FROM {$t} WHERE is_active = 1 ORDER BY id DESC LIMIT 500";
      $rows = $wpdb->get_results($sql, ARRAY_A);
    }

    $data = [];
    foreach ($rows as $s) {
      $img = !empty($s['image_id']) ? wp_get_attachment_image_url((int)$s['image_id'], 'thumbnail') : '';
      $price = ((int)($s['price_cents'] ?? 0)) / 100;
      $duration = (int)($s['duration_minutes'] ?? 0);
      $data[] = [
        'id' => (int)$s['id'],
        'name' => (string)$s['name'],
        'price' => (float)$price,
        'duration' => $duration,
        'duration_minutes' => $duration,
        'category_id' => (int)($s['category_id'] ?? 0),
        'image' => $img ?: '',
      ];
    }

    return rest_ensure_response(['status' => 'success', 'data' => $data]);
  }
}

if (!function_exists('bp_rest_get_extras')) {
  function bp_rest_get_extras(\WP_REST_Request $req) {
    $service_id = (int)$req->get_param('service_id');
    if ($service_id <= 0) return rest_ensure_response(['status' => 'success', 'data' => []]);

    global $wpdb;
    $extras_table = $wpdb->prefix . 'bp_service_extras';
    $map = $wpdb->prefix . 'bp_extra_services';

    $rows = $wpdb->get_results($wpdb->prepare("
      SELECT e.*
      FROM {$extras_table} e
      INNER JOIN {$map} m ON m.extra_id = e.id
      WHERE e.is_active = 1 AND m.service_id = %d
      ORDER BY e.sort_order ASC, e.id ASC
    ", $service_id), ARRAY_A);

    $data = [];
    foreach ($rows as $e) {
      $img = !empty($e['image_id']) ? wp_get_attachment_image_url((int)$e['image_id'], 'thumbnail') : '';
      $data[] = [
        'id' => (int)$e['id'],
        'name' => (string)$e['name'],
        'price' => (float)($e['price'] ?? 0),
        'duration_min' => (int)($e['duration_min'] ?? 0),
        'image' => $img ?: '',
      ];
    }

    return rest_ensure_response(['status' => 'success', 'data' => $data]);
  }
}

if (!function_exists('bp_rest_get_agents')) {
  function bp_rest_get_agents(\WP_REST_Request $req) {
    $service_id = (int)$req->get_param('service_id');
    if ($service_id <= 0) return rest_ensure_response(['status'=>'success','data'=>[]]);

    global $wpdb;
    $map = $wpdb->prefix . 'bp_agent_services';
    $agents = $wpdb->prefix . 'bp_agents';

    $rows = $wpdb->get_results($wpdb->prepare("
      SELECT a.id, a.first_name, a.last_name, a.image_id
      FROM {$agents} a
      INNER JOIN {$map} m ON m.agent_id = a.id
      WHERE m.service_id = %d AND a.is_active = 1
      ORDER BY a.id DESC
    ", $service_id), ARRAY_A);

    $data = [];
    foreach ($rows as $a) {
      $name = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
      $img = !empty($a['image_id']) ? wp_get_attachment_image_url((int)$a['image_id'], 'thumbnail') : '';
      $data[] = [
        'id' => (int)$a['id'],
        'name' => $name ?: ('Agent #' . (int)$a['id']),
        'image' => $img ?: '',
      ];
    }

    return rest_ensure_response(['status'=>'success','data'=>$data]);
  }
}

if (!function_exists('bp_rest_create_booking')) {
  function bp_rest_create_booking(\WP_REST_Request $req) {
    $body = $req->get_json_params();
    if (!is_array($body)) $body = [];

    $category_id = (int)($body['category_id'] ?? 0);
    $service_id  = (int)($body['service_id'] ?? 0);
    $agent_id    = (int)($body['agent_id'] ?? 0);

    $date = sanitize_text_field($body['date'] ?? '');
    $time = sanitize_text_field($body['time'] ?? '');

    $customer_name  = sanitize_text_field($body['customer_name'] ?? '');
    $customer_email = sanitize_email($body['customer_email'] ?? '');

    $extras = $body['extras'] ?? [];
    $promo_code = strtoupper(sanitize_text_field($body['promo_code'] ?? ''));
    $customer_fields = $body['customer_fields'] ?? [];
    $booking_fields = $body['booking_fields'] ?? [];
    if (!is_array($customer_fields)) $customer_fields = [];
    if (!is_array($booking_fields)) $booking_fields = [];
    if (!is_array($extras)) $extras = [];

    if ($service_id <= 0) return rest_ensure_response(['status'=>'error','message'=>'Service required']);
    if (!$date || !$time) return rest_ensure_response(['status'=>'error','message'=>'Date/time required']);
    if (!$customer_name || !$customer_email) return rest_ensure_response(['status'=>'error','message'=>'Customer name/email required']);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
      return rest_ensure_response(['status'=>'error','message'=>'Invalid date']);
    }
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
      return rest_ensure_response(['status'=>'error','message'=>'Invalid time']);
    }

    global $wpdb;

    $svc = $wpdb->get_row($wpdb->prepare(
      "SELECT id, price_cents, duration_minutes, category_id FROM {$wpdb->prefix}bp_services WHERE id = %d AND is_active = 1",
      $service_id
    ), ARRAY_A);

    if (!$svc) return rest_ensure_response(['status'=>'error','message'=>'Invalid service']);

    $service_price = ((int)($svc['price_cents'] ?? 0)) / 100;
    $duration_min = (int)($svc['duration_minutes'] ?? 0);
    if ($duration_min <= 0) $duration_min = 60;

    if ($agent_id > 0) {
      $map = $wpdb->prefix . 'bp_agent_services';
      $ok = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$map} WHERE agent_id=%d AND service_id=%d",
        $agent_id, $service_id
      ));
      if ($ok <= 0) return rest_ensure_response(['status'=>'error','message'=>'Agent not allowed for this service']);
    }

    $valid_extras = $wpdb->get_results($wpdb->prepare(
      "SELECT id, price FROM {$wpdb->prefix}bp_service_extras WHERE service_id=%d AND is_active=1",
      $service_id
    ), ARRAY_A);

    $valid_map = [];
    foreach ($valid_extras as $e) $valid_map[(int)$e['id']] = (float)$e['price'];

    $extras_clean = [];
    $extras_total = 0.0;

    foreach ($extras as $ex) {
      $eid = (int)($ex['id'] ?? 0);
      if ($eid <= 0) continue;
      if (!isset($valid_map[$eid])) continue;

      $price = (float)$valid_map[$eid];
      $extras_clean[] = ['id'=>$eid, 'price'=>$price];
      $extras_total += $price;
    }

    $subtotal = $service_price + $extras_total;

    $chk1 = bp_validate_required_fields('customer', $customer_fields);
    if (!$chk1['ok']) return rest_ensure_response(['status'=>'error','message'=>$chk1['message']]);

    $chk2 = bp_validate_required_fields('booking', $booking_fields);
    if (!$chk2['ok']) return rest_ensure_response(['status'=>'error','message'=>$chk2['message']]);

    $promo_res = bp_apply_promo_to_subtotal($promo_code, $subtotal, true);
    $discount_total = 0.00;
    $final_total = $subtotal;
    if (!empty($promo_code) && !empty($promo_res['valid'])) {
      $discount_total = (float)$promo_res['discount'];
      $final_total = (float)$promo_res['total'];
    } else {
      $promo_code = null;
    }

    $start_dt = $date . ' ' . (strlen($time) === 5 ? $time . ':00' : $time);
    $start = new \DateTime($start_dt);
    $end = clone $start;
    $end->modify('+' . $duration_min . ' minutes');

    $first_name = $customer_name;
    $last_name = '';
    if (strpos($customer_name, ' ') !== false) {
      $parts = preg_split('/\s+/', $customer_name);
      $first_name = array_shift($parts);
      $last_name = trim(implode(' ', $parts));
    }

    $customer_id = BP_CustomerModel::find_or_create_by_email([
      'first_name' => $first_name,
      'last_name' => $last_name,
      'email' => $customer_email,
      'phone' => '',
    ]);

    $wpdb->update(
      $wpdb->prefix . 'bp_customers',
      ['custom_fields_json' => wp_json_encode($customer_fields)],
      ['id' => $customer_id],
      ['%s'],
      ['%d']
    );

    $manage_key = bin2hex(random_bytes(32));
    $now = current_time('mysql');

    $bookings = $wpdb->prefix . 'bp_bookings';
    $insert = [
      'category_id' => $category_id > 0 ? $category_id : (int)($svc['category_id'] ?? 0),
      'service_id' => $service_id,
      'agent_id' => $agent_id > 0 ? $agent_id : null,
      'customer_id' => $customer_id,
      'start_datetime' => $start->format('Y-m-d H:i:s'),
      'end_datetime' => $end->format('Y-m-d H:i:s'),
      'status' => 'pending',
      'notes' => null,
      'manage_key' => $manage_key,
      'extras_json' => wp_json_encode($extras_clean),
      'promo_code' => $promo_code,
      'discount_total' => $discount_total,
      'total_price' => $final_total,
      'customer_fields_json' => wp_json_encode($customer_fields),
      'booking_fields_json' => wp_json_encode($booking_fields),
      'created_at' => $now,
      'updated_at' => $now,
    ];

    $ok = $wpdb->insert($bookings, $insert, [
      '%d','%d','%d','%d','%s','%s','%s','%s','%s','%s','%s','%f','%f','%s','%s','%s','%s'
    ]);

    if (!$ok) return rest_ensure_response(['status'=>'error','message'=>'DB insert failed']);

    return rest_ensure_response([
      'status' => 'success',
      'booking_id' => (int)$wpdb->insert_id,
      'subtotal' => (float)$subtotal,
      'discount_total' => (float)$discount_total,
      'total_price' => (float)$final_total,
    ]);
  }
}

if (!function_exists('bp_rest_validate_promo')) {
  function bp_rest_validate_promo(\WP_REST_Request $req) {
    $code = strtoupper(sanitize_text_field($req->get_param('code') ?? ''));
    $subtotal = (float)($req->get_param('subtotal') ?? 0);

    $res = bp_apply_promo_to_subtotal($code, $subtotal, false);
    return rest_ensure_response($res);
  }
}

if (!function_exists('bp_render_admin_app_catalog')) {
  function bp_render_admin_app_catalog() {
    echo '<div id="bp-admin-app" data-route="catalog"></div>';
  }
}

if (!function_exists('bp_render_admin_app')) {
  function bp_render_admin_app() {
    echo '<div id="bp-admin-app"></div>';
  }
}


if (!function_exists('bp_rest_get_form_fields')) {
  function bp_rest_get_form_fields(\WP_REST_Request $req) {
    $scope = sanitize_text_field($req->get_param('scope') ?? 'form');
    if (!in_array($scope, ['form','customer','booking'], true)) $scope = 'form';

    $rows = BP_FormFieldModel::active_fields($scope);

    $data = [];
    foreach ($rows as $f) {
      $opts = [];
      if (!empty($f['options_json'])) {
        $decoded = json_decode((string)$f['options_json'], true);
        if (is_array($decoded)) $opts = $decoded;
      }

      $key = (string)($f['field_key'] ?: ($f['name_key'] ?? ''));
      $is_required = (int)($f['is_required'] ?? $f['required'] ?? 0);

      $data[] = [
        'id' => (int)$f['id'],
        'label' => (string)$f['label'],
        'key' => $key,
        'type' => (string)$f['type'],
        'required' => ($is_required === 1),
        'options' => $opts,
      ];
    }

    return rest_ensure_response(['status'=>'success','data'=>$data]);
  }
}

if (!function_exists('bp_validate_required_fields')) {
  function bp_validate_required_fields(string $scope, array $submitted): array {
    $fields = BP_FormFieldModel::active_fields($scope);
    foreach ($fields as $f) {
      $is_required = (int)($f['is_required'] ?? $f['required'] ?? 0);
      if ($is_required !== 1) continue;
      $key = (string)($f['field_key'] ?: ($f['name_key'] ?? ''));

      $val = $submitted[$key] ?? null;

      $empty = false;
      if (is_bool($val)) $empty = ($val === false);
      else $empty = (trim((string)$val) === '');

      if ($empty) {
        return ['ok'=>false, 'message'=>"Missing required field: {$f['label']}"]; 
      }
    }
    return ['ok'=>true];
  }
}

if (!function_exists('bp_apply_promo_to_subtotal')) {
  function bp_apply_promo_to_subtotal(string $code, float $subtotal, bool $increment_use): array {
    $code = strtoupper(trim($code));
    if ($code === '') {
      return ['status'=>'success','valid'=>false,'discount'=>0,'total'=>$subtotal,'message'=>''];
    }

    $promo = BP_PromoCodeModel::find_by_code($code);
    if (!$promo || (int)$promo['is_active'] !== 1) {
      return ['status'=>'success','valid'=>false,'discount'=>0,'total'=>$subtotal,'message'=>'Invalid code'];
    }

    $now = time();
    if (!empty($promo['starts_at']) && strtotime($promo['starts_at']) > $now) {
      return ['status'=>'success','valid'=>false,'discount'=>0,'total'=>$subtotal,'message'=>'Not started yet'];
    }
    if (!empty($promo['ends_at']) && strtotime($promo['ends_at']) < $now) {
      return ['status'=>'success','valid'=>false,'discount'=>0,'total'=>$subtotal,'message'=>'Expired'];
    }

    if (isset($promo['max_uses']) && $promo['max_uses'] !== null) {
      if ((int)$promo['uses_count'] >= (int)$promo['max_uses']) {
        return ['status'=>'success','valid'=>false,'discount'=>0,'total'=>$subtotal,'message'=>'Usage limit reached'];
      }
    }

    if (isset($promo['min_total']) && $promo['min_total'] !== null) {
      if ($subtotal < (float)$promo['min_total']) {
        return ['status'=>'success','valid'=>false,'discount'=>0,'total'=>$subtotal,'message'=>'Subtotal too low'];
      }
    }

    $discount = 0.0;
    if ($promo['type'] === 'percent') {
      $discount = $subtotal * ((float)$promo['amount'] / 100.0);
    } else {
      $discount = (float)$promo['amount'];
    }

    if ($discount < 0) $discount = 0;
    if ($discount > $subtotal) $discount = $subtotal;

    $total = $subtotal - $discount;

    if ($increment_use) {
      BP_PromoCodeModel::increment_use((int)$promo['id']);
    }

    return [
      'status' => 'success',
      'valid' => true,
      'code' => $code,
      'discount' => round($discount, 2),
      'total' => round($total, 2),
      'message' => 'Applied',
      'type' => $promo['type'],
      'amount' => (float)$promo['amount'],
    ];
  }
}

