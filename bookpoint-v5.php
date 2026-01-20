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

    // Models (Step 5)
    require_once BP_LIB_PATH . 'models/customer_model.php';
    require_once BP_LIB_PATH . 'models/booking_model.php';

    // Models (Step 16)
    require_once BP_LIB_PATH . 'models/agent_model.php';

    // Helpers (Step 7)
    require_once BP_LIB_PATH . 'helpers/settings_helper.php';

    // Helpers (Step 14)
    require_once BP_LIB_PATH . 'helpers/schedule_helper.php';

    // Helpers (Step 10)
    require_once BP_LIB_PATH . 'helpers/email_helper.php';

    // Controllers (Step 3)
    require_once BP_LIB_PATH . 'controllers/controller.php';
    require_once BP_LIB_PATH . 'controllers/admin_dashboard_controller.php';

    // Controllers (Step 4)
    require_once BP_LIB_PATH . 'controllers/admin_services_controller.php';

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
  }

  private static function register_hooks() : void {

    // Activation / Deactivation
    register_activation_hook(BP_PLUGIN_FILE, [__CLASS__, 'on_activate']);
    register_deactivation_hook(BP_PLUGIN_FILE, [__CLASS__, 'on_deactivate']);

    // Admin menu
    add_action('admin_menu', [__CLASS__, 'register_admin_menu']);

    // Services admin-post action
    add_action('admin_post_bp_admin_services_save', [__CLASS__, 'handle_services_save']);

    // Settings admin-post action
    add_action('admin_post_bp_admin_settings_save', [__CLASS__, 'handle_settings_save']);

    // Agents admin-post action (Step 16)
    add_action('admin_post_bp_admin_agents_save', [__CLASS__, 'handle_agents_save']);

    // Shortcode
    add_shortcode('bookPoint', [__CLASS__, 'shortcode_book_form']);

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
    register_rest_route('bp/v1', '/services', [
      'methods'  => 'GET',
      'callback' => [__CLASS__, 'rest_get_services'],
      'permission_callback' => function () {
        return current_user_can('edit_posts');
      },
    ]);

    // Step 16: Agents endpoint
    register_rest_route('bp/v1', '/agents', [
      'methods' => 'GET',
      'callback' => [__CLASS__, 'rest_get_agents'],
      'permission_callback' => '__return_true',
    ]);
  }

  public static function rest_get_services(\WP_REST_Request $request) {
    $services = BP_ServiceModel::all();
    $items = [];

    foreach ($services as $s) {
      if ((int)($s['is_active'] ?? 0) !== 1) continue;

      $items[] = [
        'id' => (int)$s['id'],
        'name' => (string)$s['name'],
        'duration_minutes' => (int)$s['duration_minutes'],
      ];
    }

    return rest_ensure_response([
      'status' => 'success',
      'data' => $items,
    ]);
  }

  // Step 16: Get active agents
  public static function rest_get_agents(\WP_REST_Request $request) {
    $agents = BP_AgentModel::all(500, true);
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

  public static function register_admin_menu() : void {
    if (!current_user_can('bp_manage_bookings') &&
        !current_user_can('bp_manage_services') &&
        !current_user_can('bp_manage_customers') &&
        !current_user_can('bp_manage_settings') &&
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
      'read',
      'bp',
      [__CLASS__, 'render_dashboard']
    );

    add_submenu_page(
      'bp',
      __('Bookings', 'bookpoint'),
      __('Bookings', 'bookpoint'),
      'bp_manage_bookings',
      'bp_bookings',
      [__CLASS__, 'render_bookings_index']
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
      __('Agents', 'bookpoint'),
      __('Agents', 'bookpoint'),
      'bp_manage_settings',
      'bp_agents',
      [__CLASS__, 'render_agents']
    );

    // Hidden pages for internal use
    add_submenu_page(
      null,
      __('Edit Agent', 'bookpoint'),
      __('Edit Agent', 'bookpoint'),
      'bp_manage_settings',
      'bp_agents_edit',
      [__CLASS__, 'render_agents_edit']
    );

    add_submenu_page(
      null,
      __('Delete Agent', 'bookpoint'),
      __('Delete Agent', 'bookpoint'),
      'bp_manage_settings',
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
      __('Change Booking Status', 'bookpoint'),
      __('Change Booking Status', 'bookpoint'),
      'bp_manage_bookings',
      'bp_bookings_change_status',
      [__CLASS__, 'render_bookings_change_status']
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

  public static function render_bookings_index() : void {
    (new BP_AdminBookingsController())->index();
  }

  public static function render_bookings_change_status() : void {
    (new BP_AdminBookingsController())->change_status();
  }

  public static function render_customers() : void {
    (new BP_AdminCustomersController())->index();
  }

  public static function render_customer_view() : void {
    (new BP_AdminCustomersController())->view();
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

  public static function shortcode_book_form($atts) : string {
    $atts = shortcode_atts([
      'service_id' => 0,
      'default_date' => '',
      'hide_notes' => 0,
      'require_phone' => 0,
      'compact' => 0,
    ], $atts, 'bookPoint');

    $service_id = absint($atts['service_id']);
    if ($service_id <= 0) {
      return '<p>' . esc_html__('BookPoint: service_id is required.', 'bookpoint') . '</p>';
    }

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

  public static function ajax_slots() : void {
    (new BP_PublicBookingsController())->slots();
  }

  public static function ajax_submit_booking() : void {
    (new BP_PublicBookingsController())->submit();
  }

  public static function register_query_vars($vars) {
    $vars[] = 'BP_manage_booking';
    $vars[] = 'key';
    $vars[] = 'BP_action';
    return $vars;
  }

  public static function maybe_render_public_pages($wp) : void {
    // if not our page, ignore
    $is_manage = get_query_var('BP_manage_booking');
    if (!$is_manage) return;

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
}

BP_Plugin::init();
