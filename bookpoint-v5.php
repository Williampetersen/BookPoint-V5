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

    // Helpers (Portal + Webhooks)
    require_once BP_LIB_PATH . 'helpers/portal_helper.php';
    require_once BP_LIB_PATH . 'helpers/webhook_helper.php';

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

    add_action('admin_init', function () {
      BP_MigrationsHelper::run();
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

    // GDPR delete customer
    add_action('admin_post_bp_admin_customer_gdpr_delete', [__CLASS__, 'handle_customer_gdpr_delete']);

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

    // Store plugin version too (optional but helpful)
    update_option('BP_version', self::VERSION, false);
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
      'read',
      'bp',
      [__CLASS__, 'render_dashboard'],
      'dashicons-calendar-alt',
      56
    );

    add_submenu_page(
      'bp',
      __('Dashboard', 'bookpoint'),
      __('Dashboard', 'bookpoint'),
      'bp_manage_bookings',
      'bp_dashboard',
      function () {
        (new BP_AdminDashboardController())->index();
      },
      0
    );

    add_submenu_page(
      'bp',
      __('Bookings', 'bookpoint'),
      __('Bookings', 'bookpoint'),
      'bp_manage_bookings',
      'bp_bookings',
      [__CLASS__, 'render_bookings']
    );

    add_submenu_page(
      'bp',
      __('Services', 'bookpoint'),
      __('Services', 'bookpoint'),
      'bp_manage_services',
      'bp_services',
      [__CLASS__, 'render_services_index']
    );

    add_submenu_page(
      'bp',
      __('Categories', 'bookpoint'),
      __('Categories', 'bookpoint'),
      'bp_manage_services',
      'bp_categories',
      function () {
        $ctrl = new BP_AdminCategoriesController();
        $action = sanitize_text_field($_GET['action'] ?? 'index');
        if (!method_exists($ctrl, $action)) $action = 'index';
        $ctrl->$action();
      }
    );

    add_submenu_page(
      'bp',
      __('Service Extras', 'bookpoint'),
      __('Service Extras', 'bookpoint'),
      'bp_manage_services',
      'bp_extras',
      function () {
        $ctrl = new BP_AdminExtrasController();
        $action = sanitize_text_field($_GET['action'] ?? 'index');
        if (!method_exists($ctrl, $action)) $action = 'index';
        $ctrl->$action();
      }
    );

    add_submenu_page(
      'bp',
      __('Promo Codes', 'bookpoint'),
      __('Promo Codes', 'bookpoint'),
      'bp_manage_settings',
      'bp_promo_codes',
      function () {
        $ctrl = new BP_AdminPromoCodesController();
        $action = sanitize_text_field($_GET['action'] ?? 'index');
        if (!method_exists($ctrl, $action)) $action = 'index';
        $ctrl->$action();
      }
    );

    add_submenu_page(
      'bp',
      __('Form Fields', 'bookpoint'),
      __('Form Fields', 'bookpoint'),
      'bp_manage_settings',
      'bp_form_fields',
      function () {
        $ctrl = new BP_AdminFormFieldsController();
        $action = sanitize_text_field($_GET['action'] ?? 'index');
        if (!method_exists($ctrl, $action)) $action = 'index';
        $ctrl->$action();
      }
    );

    add_submenu_page(
      'bp',
      __('Customers', 'bookpoint'),
      __('Customers', 'bookpoint'),
      'bp_manage_customers',
      'bp_customers',
      [__CLASS__, 'render_customers']
    );

    add_submenu_page(
      'bp',
      __('Settings', 'bookpoint'),
      __('Settings', 'bookpoint'),
      'bp_manage_settings',
      'bp_settings',
      [__CLASS__, 'render_settings']
    );

    add_submenu_page(
      'bp',
      __('Audit Log', 'bookpoint'),
      __('Audit Log', 'bookpoint'),
      'bp_manage_settings',
      'bp_audit',
      [__CLASS__, 'render_audit_log']
    );

    add_submenu_page(
      'bp',
      __('Tools', 'bookpoint'),
      __('Tools', 'bookpoint'),
      'bp_manage_tools',
      'bp_tools',
      [__CLASS__, 'render_tools']
    );

    add_submenu_page(
      'tools.php',
      __('BookPoint Tools', 'bookpoint'),
      __('BookPoint Tools', 'bookpoint'),
      'bp_manage_settings',
      'bp_tools',
      [__CLASS__, 'render_tools']
    );

    add_submenu_page(
      'bp',
      __('Agents', 'bookpoint'),
      __('Agents', 'bookpoint'),
      'bp_manage_agents',
      'bp_agents',
      [__CLASS__, 'render_agents']
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
      __('Delete Service', 'bookpoint'),
      __('Delete Service', 'bookpoint'),
      'bp_manage_services',
      'bp_services_delete',
      [__CLASS__, 'render_services_delete']
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
  }

  public static function enqueue_admin_assets(string $hook): void {
    if (empty($_GET['page'])) return;

    if ($_GET['page'] === 'bp_dashboard') {
      wp_enqueue_style('bp-admin-dashboard', BP_PLUGIN_URL . 'public/admin-dashboard.css', [], self::VERSION);
      wp_enqueue_script('bp-admin-dashboard', BP_PLUGIN_URL . 'public/admin-dashboard.js', [], self::VERSION, true);
      return;
    }

    if ($_GET['page'] === 'bp_categories' && isset($_GET['action']) && $_GET['action'] === 'edit') {
      wp_enqueue_media();
      wp_enqueue_script('bp-admin-media', BP_PLUGIN_URL . 'public/admin-media.js', ['jquery'], self::VERSION, true);
      return;
    }

    if ($_GET['page'] === 'bp_services_edit' || ($_GET['page'] === 'bp_services' && isset($_GET['action']) && $_GET['action'] === 'edit')) {
      wp_enqueue_media();
      wp_enqueue_script('bp-admin-service-media', BP_PLUGIN_URL . 'public/admin-service-media.js', ['jquery'], self::VERSION, true);
      return;
    }

    if ($_GET['page'] === 'bp_extras' && isset($_GET['action']) && $_GET['action'] === 'edit') {
      wp_enqueue_media();
      wp_enqueue_script('bp-admin-extra-media', BP_PLUGIN_URL . 'public/admin-extra-media.js', ['jquery'], self::VERSION, true);
      return;
    }

    if ($_GET['page'] === 'bp_agents_edit' || ($_GET['page'] === 'bp_agents' && isset($_GET['action']) && $_GET['action'] === 'edit')) {
      wp_enqueue_media();
      wp_enqueue_script('bp-admin-agent-media', BP_PLUGIN_URL . 'public/admin-agent-media.js', ['jquery'], self::VERSION, true);
    }
  }

  public static function render_dashboard() : void {
    $controller = new BP_AdminDashboardController();
    $controller->index();
  }

  public static function render_services_index() : void {
    (new BP_AdminServicesController())->index();
  }

  public static function render_services_edit() : void {
    (new BP_AdminServicesController())->edit();
  }

  public static function render_services_delete() : void {
    (new BP_AdminServicesController())->delete();
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

  public static function enqueue_public_assets(): void {
    wp_enqueue_script('bp-booking-ui', BP_PLUGIN_URL . 'public/booking-ui.js', [], self::VERSION, true);
    wp_enqueue_style('bp-booking-ui', BP_PLUGIN_URL . 'public/booking-ui.css', [], self::VERSION);
  }

  public static function render_agents() : void {
    (new BP_AdminAgentsController())->index();
  }

  public static function render_agents_edit() : void {
    (new BP_AdminAgentsController())->edit();
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
    wp_enqueue_script('bp-front', BP_PLUGIN_URL . 'public/javascripts/front.js', ['jquery'], self::VERSION, true);
    wp_enqueue_style('bp-front', BP_PLUGIN_URL . 'public/stylesheets/front.css', [], self::VERSION);
    wp_localize_script('bp-front', 'bp', [
      'ajax_url' => admin_url('admin-ajax.php'),
    ]);

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
  function bp_shortcode_booking_form() {
    $nonce = wp_create_nonce('bp_booking');
    ob_start(); ?>
    <div class="bp-wrap">
      <div class="bp-booking" data-nonce="<?php echo esc_attr($nonce); ?>">
        <div class="bp-grid">
          <div class="bp-panel">
            <div class="bp-title">Category</div>
            <div class="bp-subtitle">Select a category</div>
            <div class="bp-cards bp-categories"></div>

            <div class="bp-title" style="margin-top:16px;">Service</div>
            <div class="bp-subtitle">Select a service</div>
            <div class="bp-cards bp-services"></div>

            <div class="bp-title" style="margin-top:16px;">Extras</div>
            <div class="bp-subtitle">Optional add-ons</div>
            <div class="bp-cards bp-extras"></div>

            <div class="bp-title" style="margin-top:16px;">Agent</div>
            <div class="bp-subtitle">Choose a specialist</div>
            <div class="bp-cards bp-agents"></div>
          </div>

          <div class="bp-panel">
            <div class="bp-title">Your Booking <span class="bp-pill">Summary</span></div>

            <div class="bp-summary-line"><span class="bp-muted">Category</span><strong class="bp-sum-category">—</strong></div>
            <div class="bp-summary-line"><span class="bp-muted">Service</span><strong class="bp-sum-service">—</strong></div>
            <div class="bp-summary-line"><span class="bp-muted">Extras</span><strong class="bp-sum-extras">—</strong></div>
            <div class="bp-summary-line"><span class="bp-muted">Agent</span><strong class="bp-sum-agent">—</strong></div>

            <div class="bp-summary-line" style="margin-top:12px;"><span class="bp-muted">Subtotal</span><strong>€ <span class="bp-sum-subtotal">0.00</span></strong></div>
            <div class="bp-summary-line"><span class="bp-muted">Discount</span><strong>€ <span class="bp-sum-discount">0.00</span></strong></div>
            <div class="bp-summary-line"><span class="bp-muted">Total</span><strong>€ <span class="bp-sum-total">0.00</span></strong></div>

            <div class="bp-row">
              <label>Date</label>
              <input type="date" class="bp-input bp-date">
            </div>

            <div class="bp-row">
              <label>Time</label>
              <input type="time" class="bp-input bp-time">
            </div>

            <div class="bp-row">
              <label>Your Name</label>
              <input type="text" class="bp-input bp-customer-name" placeholder="Jane Doe">
            </div>

            <div class="bp-row">
              <label>Email</label>
              <input type="email" class="bp-input bp-customer-email" placeholder="jane@email.com">
            </div>

            <div class="bp-row">
              <div class="bp-title" style="margin:10px 0 0;">Customer Details</div>
              <div class="bp-dynamic-customer"></div>
            </div>

            <div class="bp-row">
              <div class="bp-title" style="margin:10px 0 0;">Booking Details</div>
              <div class="bp-dynamic-booking"></div>
            </div>

            <div class="bp-row">
              <label>Promo Code</label>
              <div style="display:flex;gap:8px;">
                <input type="text" class="bp-input bp-promo" placeholder="SAVE10" style="flex:1;">
                <button type="button" class="bp-btn secondary bp-apply-promo">Apply</button>
              </div>
              <div class="bp-muted bp-promo-msg" style="margin-top:6px;"></div>
            </div>

            <div class="bp-row">
              <button type="button" class="bp-btn bp-submit">Book Now</button>
              <div class="bp-alert bp-msg" style="display:none;"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
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

      $data[] = [
        'id' => (int)$f['id'],
        'label' => (string)$f['label'],
        'key' => (string)$f['name_key'],
        'type' => (string)$f['type'],
        'required' => ((int)$f['required'] === 1),
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
      if ((int)$f['required'] !== 1) continue;
      $key = (string)$f['name_key'];

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
