<?php
/**
 * Plugin Name: BookPoint Booking & Appointments
 * Description: Lightweight appointment booking plugin for WordPress.
 * Version: 1.0.1
 * Author: BookPoint Team
 * Author URI: https://wpbookpoint.com/
 * Plugin URI: https://wpbookpoint.com/download-for-free/
 * Text Domain: pointly-booking
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined('ABSPATH') || exit;

// Guard against true class collisions, but allow same-file preload/duplicate includes.
$pointlybooking_existing_class = '';
if (class_exists('POINTLYBOOKING_Core_Plugin', false)) {
  $pointlybooking_existing_class = 'POINTLYBOOKING_Core_Plugin';
} elseif (class_exists('BPV5_BookPoint_Core_Plugin', false)) {
  $pointlybooking_existing_class = 'BPV5_BookPoint_Core_Plugin';
}
if ($pointlybooking_existing_class !== '') {
  $pointlybooking_product = 'BookPoint';
  $pointlybooking_existing_file = '';
  try {
    $pointlybooking_reflection = new ReflectionClass($pointlybooking_existing_class);
    $pointlybooking_existing_file = (string) ($pointlybooking_reflection->getFileName() ?: '');
  } catch (\Throwable $e) {
    $pointlybooking_existing_file = '';
  }

  $pointlybooking_same_file = false;
  if ($pointlybooking_existing_file !== '') {
    $pointlybooking_same_file = (wp_normalize_path($pointlybooking_existing_file) === wp_normalize_path(__FILE__));
  }

  if (!$pointlybooking_same_file) {
    $pointlybooking_details = '';
    if ($pointlybooking_existing_file !== '') {
      $pointlybooking_existing_display = $pointlybooking_existing_file;
      $pointlybooking_plugins_root = wp_normalize_path(dirname(plugin_dir_path(__FILE__)));
      if ($pointlybooking_plugins_root !== '' && strpos(wp_normalize_path($pointlybooking_existing_file), $pointlybooking_plugins_root) === 0) {
        $pointlybooking_existing_display = substr(wp_normalize_path($pointlybooking_existing_file), strlen($pointlybooking_plugins_root));
        $pointlybooking_existing_display = ltrim((string) $pointlybooking_existing_display, '/\\');
      }
      /* translators: %s: Relative path to the already-loaded plugin file. */
      $pointlybooking_loaded_from = __('Loaded from: %s', 'pointly-booking');
      $pointlybooking_details = "\n\n" . sprintf($pointlybooking_loaded_from, $pointlybooking_existing_display);
    }

    add_action('admin_notices', function () use ($pointlybooking_product, $pointlybooking_details) {
      if (!current_user_can('activate_plugins')) return;
      /* translators: 1: Product name. 2: Product name. 3: Extra details about the loaded plugin path. */
      $message_template = __('%1$s could not be loaded because another copy of BookPoint is already active. Please deactivate the other BookPoint plugin first, then activate %2$s.%3$s', 'pointly-booking');
      $message = sprintf(
        $message_template,
        $pointlybooking_product,
        $pointlybooking_product,
        $pointlybooking_details
      );
      echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
    });
    return;
  }
}

add_filter('admin_body_class', function($classes){
  $classes = is_string($classes) ? $classes : '';
  $page = sanitize_key((string) filter_input(INPUT_GET, 'page', FILTER_UNSAFE_RAW));
  if ($page === '') return $classes;
  if (strpos($page, 'pointlybooking_') !== 0) return $classes;
  return $classes . ' bp-app-mode';
});

if (!class_exists('POINTLYBOOKING_Core_Plugin', false)) {
final class POINTLYBOOKING_Core_Plugin {

  // NOTE: Keep plugin header Version in sync with this.
  const VERSION    = '1.0.1';
  const DB_VERSION = '5.0.0';
  const CAPS_SEEDED_OPTION = 'pointlybooking_caps_seeded';
  private static $booted = false;

  public static function init() : void {
    if (self::$booted) return;
    self::$booted = true;

    self::define_constants();
    self::load_textdomain();
    self::includes();
    self::maybe_seed_capabilities();
    self::register_hooks();
  }

  private static function maybe_seed_capabilities(): void {
    // Ensure roles/caps exist even if the plugin was deployed without running activation hooks (FTP/migrations).
    // This runs once per site.
    $seeded = (string) get_option(self::CAPS_SEEDED_OPTION, '');
    if ($seeded === '1') return;
    if (!class_exists('POINTLYBOOKING_RolesHelper')) return;

    POINTLYBOOKING_RolesHelper::add_capabilities();
    update_option(self::CAPS_SEEDED_OPTION, '1', false);

    // Refresh current user caps for the current request so admin menus can render immediately.
    if (function_exists('wp_get_current_user')) {
      $u = wp_get_current_user();
      if ($u && is_object($u) && method_exists($u, 'get_role_caps')) {
        $u->get_role_caps();
      }
    }
  }

  private static function define_constants() : void {
    if (!defined('POINTLYBOOKING_PLUGIN_FILE')) define('POINTLYBOOKING_PLUGIN_FILE', __FILE__);
    if (!defined('POINTLYBOOKING_PLUGIN_DIR')) define('POINTLYBOOKING_PLUGIN_DIR', plugin_dir_path(__FILE__));
    if (!defined('POINTLYBOOKING_PLUGIN_URL'))  define('POINTLYBOOKING_PLUGIN_URL', plugin_dir_url(__FILE__));
    if (!defined('POINTLYBOOKING_PLUGIN_PATH')) define('POINTLYBOOKING_PLUGIN_PATH', POINTLYBOOKING_PLUGIN_DIR);

    if (!defined('POINTLYBOOKING_LIB_PATH'))    define('POINTLYBOOKING_LIB_PATH', POINTLYBOOKING_PLUGIN_PATH . 'lib/');
    if (!defined('POINTLYBOOKING_PUBLIC_PATH')) define('POINTLYBOOKING_PUBLIC_PATH', POINTLYBOOKING_PLUGIN_PATH . 'public/');
    if (!defined('POINTLYBOOKING_VIEWS_PATH'))  define('POINTLYBOOKING_VIEWS_PATH', POINTLYBOOKING_LIB_PATH . 'views/');
    if (!defined('POINTLYBOOKING_BLOCKS_PATH')) define('POINTLYBOOKING_BLOCKS_PATH', POINTLYBOOKING_PLUGIN_PATH . 'blocks/');
  }

  private static function load_textdomain() : void {
    load_plugin_textdomain(
      'pointly-booking',
      false,
      dirname(plugin_basename(__FILE__)) . '/languages'
    );
  }

  private static function public_icons_dir_rel(): string {
    // Prefer the canonical icons folder if present; fallback for older builds.
    if (is_dir(POINTLYBOOKING_PLUGIN_PATH . 'public/icons')) return 'public/icons';
    return 'public/images/icons';
  }

  private static function safe_filemtime(string $path): int {
    if ($path === '') return 0;
    if (!is_file($path)) return 0;
    $mt = filemtime($path);
    return $mt ? (int) $mt : 0;
  }

  private static function includes() : void {
    // Helpers (Step 2)
    require_once POINTLYBOOKING_LIB_PATH . 'helpers/roles_helper.php';
    require_once POINTLYBOOKING_LIB_PATH . 'helpers/migrations_helper.php';
    require_once POINTLYBOOKING_LIB_PATH . 'helpers/form_fields_helper.php';
    require_once POINTLYBOOKING_LIB_PATH . 'helpers/form_fields_seed_helper.php';
    require_once POINTLYBOOKING_LIB_PATH . 'helpers/field_values_helper.php';
    require_once POINTLYBOOKING_LIB_PATH . 'helpers/database_helper.php';
    require_once POINTLYBOOKING_LIB_PATH . 'helpers/defaults_helper.php';

    // Helpers (Step 5)
    require_once POINTLYBOOKING_LIB_PATH . 'helpers/availability_helper.php';

    // Models (Step 4)
    require_once POINTLYBOOKING_LIB_PATH . 'models/model.php';
    require_once POINTLYBOOKING_LIB_PATH . 'models/service_model.php';
    require_once POINTLYBOOKING_LIB_PATH . 'models/category_model.php';

    // Models (Step 5)
    require_once POINTLYBOOKING_LIB_PATH . 'models/customer_model.php';
    require_once POINTLYBOOKING_LIB_PATH . 'models/booking_model.php';
    require_once POINTLYBOOKING_LIB_PATH . 'models/service_extra_model.php';
    require_once POINTLYBOOKING_LIB_PATH . 'models/promo_code_model.php';
    require_once POINTLYBOOKING_LIB_PATH . 'models/form_field_model.php';

    // Models (Step 16)
    require_once POINTLYBOOKING_LIB_PATH . 'models/agent_model.php';

    // Models (Step 18)
    require_once POINTLYBOOKING_LIB_PATH . 'models/service_agent_model.php';

    // Models (Audit)
    require_once POINTLYBOOKING_LIB_PATH . 'models/audit_model.php';

    // Helpers (Step 7)
    require_once POINTLYBOOKING_LIB_PATH . 'helpers/settings_helper.php';

    // Helpers (Step 14)
    require_once POINTLYBOOKING_LIB_PATH . 'helpers/schedule_helper.php';

    // Helpers (Step 10)
    require_once POINTLYBOOKING_LIB_PATH . 'helpers/email_helper.php';
    require_once POINTLYBOOKING_LIB_PATH . 'helpers/notifications_helper.php';
    require_once POINTLYBOOKING_LIB_PATH . 'helpers/locations_migrations_helper.php';

    // Helpers (Portal + Webhooks)
    require_once POINTLYBOOKING_LIB_PATH . 'helpers/portal_helper.php';
    require_once POINTLYBOOKING_LIB_PATH . 'helpers/webhook_helper.php';
    require_once POINTLYBOOKING_LIB_PATH . 'helpers/payments_booking_bridge.php';

    // Helpers (Audit)
    require_once POINTLYBOOKING_LIB_PATH . 'helpers/audit_helper.php';

    // Helpers (Relations)
    require_once POINTLYBOOKING_LIB_PATH . 'helpers/relations_helper.php';

    // Helpers (Dashboard)
    require_once POINTLYBOOKING_LIB_PATH . 'helpers/dashboard_helper.php';

    // Helpers (Demo)
    require_once POINTLYBOOKING_LIB_PATH . 'helpers/demo_helper.php';

    // Updates helper lives in the Pro add-on plugin (Free must not require it).

    // Integrations
    require_once POINTLYBOOKING_LIB_PATH . 'integrations/woocommerce-hooks.php';

    // Controllers (Step 3)
    require_once POINTLYBOOKING_LIB_PATH . 'controllers/controller.php';
    require_once POINTLYBOOKING_LIB_PATH . 'controllers/admin_dashboard_controller.php';

    // Controllers (Step 4)
    require_once POINTLYBOOKING_LIB_PATH . 'controllers/admin_services_controller.php';
    require_once POINTLYBOOKING_LIB_PATH . 'controllers/admin_categories_controller.php';
    require_once POINTLYBOOKING_LIB_PATH . 'controllers/admin_extras_controller.php';
    require_once POINTLYBOOKING_LIB_PATH . 'controllers/admin_promo_codes_controller.php';
    require_once POINTLYBOOKING_LIB_PATH . 'controllers/admin_form_fields_controller.php';

    // Controllers (Step 5)
    require_once POINTLYBOOKING_LIB_PATH . 'controllers/public_bookings_controller.php';

    // Controllers (Step 7)
    require_once POINTLYBOOKING_LIB_PATH . 'controllers/admin_settings_controller.php';

    // Controllers (Step 8)
    require_once POINTLYBOOKING_LIB_PATH . 'controllers/admin_bookings_controller.php';

    // Controllers (Step 9)
    require_once POINTLYBOOKING_LIB_PATH . 'controllers/admin_customers_controller.php';

    // Controllers (Step 16)
    require_once POINTLYBOOKING_LIB_PATH . 'controllers/admin_agents_controller.php';

    // Controllers (Audit + Tools)
    require_once POINTLYBOOKING_LIB_PATH . 'controllers/admin_audit_controller.php';
    require_once POINTLYBOOKING_LIB_PATH . 'controllers/admin_tools_controller.php';
    require_once POINTLYBOOKING_LIB_PATH . 'admin/admin-payments-settings-routes.php';

    // REST routes (Admin)
    require_once POINTLYBOOKING_LIB_PATH . 'rest/admin-calendar-routes.php';
    require_once POINTLYBOOKING_LIB_PATH . 'rest/admin-bookings-routes.php';
    // Duplicate legacy schedule routes file removed from load list to avoid
    // redeclaration fatals with rest/admin-schedule-routes.php.
    require_once POINTLYBOOKING_LIB_PATH . 'rest/admin-catalog-routes.php';
    require_once POINTLYBOOKING_LIB_PATH . 'rest/admin-schedule-routes.php';
    require_once POINTLYBOOKING_LIB_PATH . 'rest/admin-schedule-editor-routes.php';
    require_once POINTLYBOOKING_LIB_PATH . 'rest/admin-holidays-routes.php';
    require_once POINTLYBOOKING_LIB_PATH . 'rest/calendar-routes.php';
    require_once POINTLYBOOKING_LIB_PATH . 'rest/dashboard-routes.php';
    require_once POINTLYBOOKING_LIB_PATH . 'rest/admin-catalog-manager-routes.php';
    require_once POINTLYBOOKING_LIB_PATH . 'rest/admin-misc-routes.php';
    require_once POINTLYBOOKING_LIB_PATH . 'rest/admin-notifications-routes.php';
    require_once POINTLYBOOKING_LIB_PATH . 'rest/admin-locations-routes.php';
    require_once POINTLYBOOKING_LIB_PATH . 'rest/admin-field-values-routes.php';
    require_once POINTLYBOOKING_LIB_PATH . 'rest/settings-routes.php';
    require_once POINTLYBOOKING_LIB_PATH . 'rest/public-catalog-routes.php';
    require_once POINTLYBOOKING_LIB_PATH . 'rest/public-availability-routes.php';
    require_once POINTLYBOOKING_LIB_PATH . 'rest/public-booking-routes.php';
    require_once POINTLYBOOKING_LIB_PATH . 'rest/front-wizard-routes.php';
    require_once POINTLYBOOKING_LIB_PATH . 'rest/admin-booking-form-design-routes.php';
    require_once POINTLYBOOKING_LIB_PATH . 'rest/front-booking-form-design-routes.php';
    require_once POINTLYBOOKING_LIB_PATH . 'rest/front-settings.php';
    require_once POINTLYBOOKING_LIB_PATH . 'rest/front-booking-create.php';
    require_once POINTLYBOOKING_LIB_PATH . 'rest/front-booking-status.php';
    require_once POINTLYBOOKING_LIB_PATH . 'rest/front-payments-woocommerce.php';
    require_once POINTLYBOOKING_LIB_PATH . 'rest/front-payments-stripe.php';
    require_once POINTLYBOOKING_LIB_PATH . 'rest/front-payments-paypal.php';
    require_once POINTLYBOOKING_LIB_PATH . 'front/front-stripe-routes.php';
    require_once POINTLYBOOKING_LIB_PATH . 'routes/front-availability-routes.php';
    require_once POINTLYBOOKING_LIB_PATH . 'routes/front-availability-month-slots.php';
    require_once POINTLYBOOKING_LIB_PATH . 'rest/form-fields-routes.php';
  }

  private static function register_hooks() : void {

    // Admin menu
    // Register both early and very late for compatibility with menu editor/hardening plugins.
    add_action('admin_menu', [__CLASS__, 'register_admin_menu'], 9);
    add_action('admin_menu', [__CLASS__, 'register_admin_menu'], 100000);
    // Final fallback: if another plugin removes/hides our menu slug, add it back for admins.
    add_action('admin_menu', [__CLASS__, 'ensure_admin_menu_visible'], PHP_INT_MAX);
    add_action('network_admin_menu', [__CLASS__, 'register_admin_menu'], 9);
    add_action('network_admin_menu', [__CLASS__, 'register_admin_menu'], 100000);

    // Plugins screen quick links
    add_filter('plugin_action_links_' . plugin_basename(POINTLYBOOKING_PLUGIN_FILE), [__CLASS__, 'plugin_action_links']);
    add_action('admin_notices', [__CLASS__, 'debug_admin_menu_notice']);

    add_action('admin_init', function () {
      POINTLYBOOKING_MigrationsHelper::run();
      if (class_exists('POINTLYBOOKING_Locations_Migrations_Helper')) {
        POINTLYBOOKING_Locations_Migrations_Helper::ensure_tables();
      }
    });

    add_action('admin_init', function () {
      if (!current_user_can('administrator') && !current_user_can('pointlybooking_manage_settings')) return;
      if (!class_exists('POINTLYBOOKING_FormFieldsSeedHelper')) return;
      POINTLYBOOKING_FormFieldsSeedHelper::ensure_defaults();
    });

    add_action('admin_init', function () {
      if (!is_admin()) return;
      $page = sanitize_key((string) filter_input(INPUT_GET, 'page', FILTER_UNSAFE_RAW));
      if ($page === '') return;

      $map = [
        'pointlybooking_schedule' => 'schedule',
        'pointlybooking_holidays' => 'holidays',
        'pointlybooking_form_fields' => 'form_fields',
        'bp-form-fields' => 'form_fields',
        'pointlybooking_promo_codes' => 'promo_codes',
        'pointlybooking_notifications' => 'notifications',
        'pointlybooking_audit' => 'audit_log',
        'pointlybooking_audit_log' => 'audit_log',
        'pointlybooking_tools' => 'tools',
      ];

      if (!isset($map[$page])) return;

      wp_safe_redirect(admin_url('admin.php?page=pointlybooking_settings&tab=' . $map[$page]));
      exit;
    });

    add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);

    // Services admin-post action
    add_action('admin_post_pointlybooking_admin_services_save', [__CLASS__, 'handle_services_save']);

    // Settings admin-post action
    add_action('admin_post_pointlybooking_admin_settings_save', [__CLASS__, 'handle_settings_save']);
    add_action('admin_post_pointlybooking_admin_settings_export_json', [__CLASS__, 'handle_settings_export_json']);
    add_action('admin_post_pointlybooking_admin_settings_import_json', [__CLASS__, 'handle_settings_import_json']);

    add_action('admin_post_pointlybooking_admin_categories_save', function () {
      (new POINTLYBOOKING_AdminCategoriesController())->save();
    });

    add_action('admin_post_pointlybooking_admin_extras_save', function () {
      (new POINTLYBOOKING_AdminExtrasController())->save();
    });

    add_action('admin_post_pointlybooking_admin_promo_codes_save', function () {
      (new POINTLYBOOKING_AdminPromoCodesController())->save();
    });

    add_action('admin_post_pointlybooking_admin_form_fields_save', function () {
      (new POINTLYBOOKING_AdminFormFieldsController())->save();
    });

    // Agents admin-post action (Step 16)
    add_action('admin_post_pointlybooking_admin_agents_save', [__CLASS__, 'handle_agents_save']);

    // Booking notes admin-post action (Step 19)
    add_action('admin_post_pointlybooking_admin_booking_notes_save', [__CLASS__, 'handle_booking_notes_save']);

    // Dashboard quick booking update
    add_action('admin_post_pointlybooking_admin_booking_quick_update', function () {
      if (!current_user_can('pointlybooking_manage_bookings')) wp_die('No permission');
      check_admin_referer('pointlybooking_admin');

      $id = absint((string) filter_input(INPUT_POST, 'id', FILTER_UNSAFE_RAW));
      $status = sanitize_text_field((string) filter_input(INPUT_POST, 'status', FILTER_UNSAFE_RAW));

      if ($id > 0 && in_array($status, ['confirmed', 'cancelled'], true)) {
        global $wpdb;
        $wpdb->update($wpdb->prefix . 'pointlybooking_bookings', ['status' => $status], ['id' => $id], ['%s'], ['%d']);

        // Optional: trigger notifications if available
        // pointlybooking_NotificationsHelper::booking_status_changed($id, $status);
      }

      wp_safe_redirect(admin_url('admin.php?page=pointlybooking_dashboard&updated=1'));
      exit;
    });

    // Bookings export CSV
    add_action('admin_post_pointlybooking_admin_bookings_export_csv', [__CLASS__, 'handle_bookings_export_csv']);
    add_action('admin_post_pointlybooking_admin_bookings_export_pdf', [__CLASS__, 'handle_bookings_export_pdf']);

    // GDPR delete customer
    add_action('admin_post_pointlybooking_admin_customer_gdpr_delete', [__CLASS__, 'handle_customer_gdpr_delete']);

    // Customers import/export CSV
    add_action('admin_post_pointlybooking_admin_customers_export_csv', [__CLASS__, 'handle_customers_export_csv']);
    add_action('admin_post_pointlybooking_admin_customers_import_csv', [__CLASS__, 'handle_customers_import_csv']);

    // Tools actions
    add_action('admin_post_pointlybooking_admin_tools_email_test', [__CLASS__, 'handle_tools_email_test']);
    add_action('admin_post_pointlybooking_admin_tools_webhook_test', [__CLASS__, 'handle_tools_webhook_test']);
    add_action('admin_post_pointlybooking_admin_tools_generate_demo', [__CLASS__, 'handle_tools_generate_demo']);

    // Tools settings import/export
    add_action('admin_post_pointlybooking_admin_tools_export_settings', [__CLASS__, 'handle_tools_export_settings']);
    add_action('admin_post_pointlybooking_admin_tools_import_settings', [__CLASS__, 'handle_tools_import_settings']);

    // Shortcode
    add_shortcode('pointlybooking_booking_form', 'pointlybooking_shortcode_booking_form');
    add_shortcode('pointlybooking_customer_portal', [__CLASS__, 'shortcode_customer_portal']);

    // Portal actions
    add_action('init', [__CLASS__, 'handle_portal_posts']);

    add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_public_assets']);

    // Gutenberg blocks (Step 11)
    add_action('init', [__CLASS__, 'register_blocks']);

    // REST API (Step 12)
    add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);

    // AJAX (public)
    add_action('wp_ajax_pointlybooking_slots', [__CLASS__, 'ajax_slots']);
    add_action('wp_ajax_nopriv_pointlybooking_slots', [__CLASS__, 'ajax_slots']);
    add_action('wp_ajax_pointlybooking_submit_booking', [__CLASS__, 'ajax_submit_booking']);
    add_action('wp_ajax_nopriv_pointlybooking_submit_booking', [__CLASS__, 'ajax_submit_booking']);

    // Public manage booking page
    add_action('parse_request', [__CLASS__, 'maybe_render_public_pages']);
    add_filter('query_vars', [__CLASS__, 'register_query_vars']);
  }

  public static function plugin_action_links(array $links): array {
    if (!is_admin()) return $links;
    if (!current_user_can('manage_options') && !current_user_can('pointlybooking_manage_bookings')) return $links;

    $dash = admin_url('admin.php?page=pointlybooking_dashboard');
    $settings = admin_url('admin.php?page=pointlybooking_settings');

    $custom = [
      '<a href="' . esc_url($dash) . '">' . esc_html__('Open BookPoint', 'pointly-booking') . '</a>',
      '<a href="' . esc_url($settings) . '">' . esc_html__('Settings', 'pointly-booking') . '</a>',
    ];

    return array_merge($custom, $links);
  }

  public static function on_activate() : void {
    $level = ob_get_level();
    ob_start();
    try {
      self::define_constants();
      if (self::has_conflicting_bookpoint_plugins()) {
        if (!function_exists('deactivate_plugins')) {
          require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        deactivate_plugins(plugin_basename(POINTLYBOOKING_PLUGIN_FILE));
        while (ob_get_level() > $level) {
          ob_end_clean();
        }
        $product = 'BookPoint';
        $message = sprintf(
          /* translators: %s: Plugin name. */
          __(
            '%s cannot be activated while another BookPoint plugin is installed. Please deactivate/remove the other BookPoint plugin first.',
            'pointly-booking'
          ),
          $product
        );
        wp_die(esc_html($message));
      }

      self::includes();

      // Run migrations quietly during activation.
      if (class_exists('POINTLYBOOKING_MigrationsHelper')) {
        POINTLYBOOKING_MigrationsHelper::run();
      }

      POINTLYBOOKING_RolesHelper::add_capabilities();
      POINTLYBOOKING_DatabaseHelper::install_or_update(self::DB_VERSION);
      self::install_or_upgrade_schedule_tables();
      self::seed_default_agent_hours();
      pointlybooking_install_form_fields_table();
      pointlybooking_seed_default_form_fields();
      if (class_exists('POINTLYBOOKING_FormFieldsSeedHelper')) {
        POINTLYBOOKING_FormFieldsSeedHelper::ensure_defaults();
      }
      pointlybooking_install_field_values_table();
      if (class_exists('POINTLYBOOKING_Locations_Migrations_Helper')) {
        POINTLYBOOKING_Locations_Migrations_Helper::ensure_tables();
      }

      // Seed default settings/design on fresh installs (do not overwrite existing).
      if (class_exists('POINTLYBOOKING_SettingsHelper')) {
        $existing = get_option('pointlybooking_settings', null);
        if (!is_array($existing)) {
          POINTLYBOOKING_SettingsHelper::set_all(POINTLYBOOKING_SettingsHelper::defaults());
        }
      }
      if (get_option('pointlybooking_booking_form_design', null) === null && function_exists('pointlybooking_booking_form_design_default')) {
        update_option('pointlybooking_booking_form_design', pointlybooking_booking_form_design_default(), false);
      }

      // Store plugin version too (optional but helpful)
      update_option('pointlybooking_version', self::VERSION, false);
    } finally {
      while (ob_get_level() > $level) {
        ob_end_clean();
      }
    }
  }

  private static function has_conflicting_bookpoint_plugins() : bool {
    if (!function_exists('get_plugins')) {
      require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $plugins = get_plugins();
    $current = plugin_basename(POINTLYBOOKING_PLUGIN_FILE);

    $is_active = function (string $file): bool {
      if (function_exists('is_plugin_active') && is_plugin_active($file)) return true;
      if (function_exists('is_plugin_active_for_network') && is_multisite() && is_plugin_active_for_network($file)) return true;
      return false;
    };

    foreach ($plugins as $file => $data) {
      if ($file === $current) continue;
      if (!$is_active($file)) continue;
      if (stripos($file, 'bookpoint-pro-addon') !== false) continue;
      $name = strtolower((string)($data['Name'] ?? ''));
      $textdomain = strtolower((string)($data['TextDomain'] ?? ''));
      if (strpos($name, 'bookpoint') !== false || $textdomain === 'bookpoint') {
        return true;
      }
    }
    return false;
  }

  private static function install_or_upgrade_schedule_tables() : void {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $t_hours  = $wpdb->prefix . 'pointlybooking_agent_working_hours';
    $t_breaks = $wpdb->prefix . 'pointlybooking_agent_breaks';
    $t_schedules = $wpdb->prefix . 'pointlybooking_schedules';
    $t_schedule_settings = $wpdb->prefix . 'pointlybooking_schedule_settings';
    $t_holidays = $wpdb->prefix . 'pointlybooking_holidays';
    $t_services = $wpdb->prefix . 'pointlybooking_services';
    $t_categories = $wpdb->prefix . 'pointlybooking_categories';
    $t_extras = $wpdb->prefix . 'pointlybooking_service_extras';
    $t_agents = $wpdb->prefix . 'pointlybooking_agents';
    $t_bookings = $wpdb->prefix . 'pointlybooking_bookings';
    $t_service_categories = $wpdb->prefix . 'pointlybooking_service_categories';
    $t_extra_services = $wpdb->prefix . 'pointlybooking_extra_services';
    $t_agent_services = $wpdb->prefix . 'pointlybooking_agent_services';

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
    self::add_column_if_missing($t_categories, 'image_id', 'BIGINT UNSIGNED NOT NULL DEFAULT 0');
    self::add_column_if_missing($t_categories, 'sort_order', 'INT NOT NULL DEFAULT 0');

    self::add_column_if_missing($t_services, 'image_id', 'BIGINT UNSIGNED NOT NULL DEFAULT 0');
    self::add_column_if_missing($t_services, 'sort_order', 'INT NOT NULL DEFAULT 0');

    self::add_column_if_missing($t_extras, 'image_id', 'BIGINT UNSIGNED NOT NULL DEFAULT 0');
    self::add_column_if_missing($t_extras, 'sort_order', 'INT NOT NULL DEFAULT 0');

    self::add_column_if_missing($t_agents, 'image_id', 'BIGINT UNSIGNED NOT NULL DEFAULT 0');

    // Holiday extensions (agent-specific + metadata)
    self::add_column_if_missing($t_holidays, 'agent_id', 'BIGINT UNSIGNED NULL');
    self::add_column_if_missing($t_holidays, 'is_recurring', 'TINYINT NOT NULL DEFAULT 0');
    self::add_column_if_missing($t_holidays, 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
    self::add_column_if_missing($t_holidays, 'updated_at', 'DATETIME NULL');

    // Indexes for speed
    self::add_index_if_missing($t_bookings, 'agent_start_date', ['agent_id', 'start_date']);
    self::add_index_if_missing($t_bookings, 'service_start_date', ['service_id', 'start_date']);

    self::add_index_if_missing($t_categories, 'sort_order', ['sort_order']);
    self::add_index_if_missing($t_services, 'sort_order', ['sort_order']);
    self::add_index_if_missing($t_extras, 'sort_order', ['sort_order']);
    self::add_index_if_missing($t_holidays, 'agent_id', ['agent_id']);
    self::add_index_if_missing($t_schedules, 'agent_day', ['agent_id', 'day_of_week']);

    self::add_column_if_missing($t_services, 'buffer_before', 'INT NOT NULL DEFAULT 0');
    self::add_column_if_missing($t_services, 'buffer_after', 'INT NOT NULL DEFAULT 0');
    self::add_column_if_missing($t_services, 'capacity', 'INT NOT NULL DEFAULT 1');
  }

  private static function is_safe_sql_identifier(string $identifier): bool {
    return preg_match('/^[A-Za-z0-9_]+$/', $identifier) === 1;
  }

  private static function quote_sql_identifier(string $identifier): string {
    return '`' . $identifier . '`';
  }

  private static function is_safe_column_definition(string $definition): bool {
    return preg_match("/^[A-Za-z0-9_(),\\s'\\.-]+$/", $definition) === 1;
  }

  private static function add_column_if_missing(string $table, string $column, string $definition) : void {
    if (!self::is_safe_sql_identifier($table) || !self::is_safe_sql_identifier($column)) {
      return;
    }
    if (!self::is_safe_column_definition($definition)) {
      return;
    }
    if (!pointlybooking_db_column_exists($table, $column)) {
      self::run_add_column_query($table, $column, $definition);
    } else {
      return;
    }
  }

  private static function add_index_if_missing(string $table, string $index, array $columns) : void {
    if (!self::is_safe_sql_identifier($table) || !self::is_safe_sql_identifier($index) || empty($columns)) {
      return;
    }
    foreach ($columns as $column) {
      if (!is_string($column) || !self::is_safe_sql_identifier($column)) {
        return;
      }
    }

    if (!pointlybooking_db_index_exists($table, $index)) {
      self::run_add_index_query($table, $index, $columns);
    } else {
      return;
    }
  }

  private static function run_add_column_query(string $table, string $column, string $definition): void {
    global $wpdb;

    $categories_table = $wpdb->prefix . 'pointlybooking_categories';
    $services_table = $wpdb->prefix . 'pointlybooking_services';
    $extras_table = $wpdb->prefix . 'pointlybooking_service_extras';
    $agents_table = $wpdb->prefix . 'pointlybooking_agents';
    $holidays_table = $wpdb->prefix . 'pointlybooking_holidays';

    // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- ALTER TABLE cannot use value placeholders; each branch targets a hardcoded plugin table with a sanitized WordPress prefix.
    if ($table === $categories_table && $column === 'image_id' && $definition === 'BIGINT UNSIGNED NOT NULL DEFAULT 0') {
      $wpdb->query("ALTER TABLE {$categories_table} ADD COLUMN `image_id` BIGINT UNSIGNED NOT NULL DEFAULT 0");
      return;
    }
    if ($table === $services_table && $column === 'image_id' && $definition === 'BIGINT UNSIGNED NOT NULL DEFAULT 0') {
      $wpdb->query("ALTER TABLE {$services_table} ADD COLUMN `image_id` BIGINT UNSIGNED NOT NULL DEFAULT 0");
      return;
    }
    if ($table === $extras_table && $column === 'image_id' && $definition === 'BIGINT UNSIGNED NOT NULL DEFAULT 0') {
      $wpdb->query("ALTER TABLE {$extras_table} ADD COLUMN `image_id` BIGINT UNSIGNED NOT NULL DEFAULT 0");
      return;
    }
    if ($table === $agents_table && $column === 'image_id' && $definition === 'BIGINT UNSIGNED NOT NULL DEFAULT 0') {
      $wpdb->query("ALTER TABLE {$agents_table} ADD COLUMN `image_id` BIGINT UNSIGNED NOT NULL DEFAULT 0");
      return;
    }
    if ($table === $categories_table && $column === 'sort_order' && $definition === 'INT NOT NULL DEFAULT 0') {
      $wpdb->query("ALTER TABLE {$categories_table} ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0");
      return;
    }
    if ($table === $services_table && $column === 'sort_order' && $definition === 'INT NOT NULL DEFAULT 0') {
      $wpdb->query("ALTER TABLE {$services_table} ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0");
      return;
    }
    if ($table === $extras_table && $column === 'sort_order' && $definition === 'INT NOT NULL DEFAULT 0') {
      $wpdb->query("ALTER TABLE {$extras_table} ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0");
      return;
    }
    if ($table === $holidays_table && $column === 'agent_id' && $definition === 'BIGINT UNSIGNED NULL') {
      $wpdb->query("ALTER TABLE {$holidays_table} ADD COLUMN `agent_id` BIGINT UNSIGNED NULL");
      return;
    }
    if ($table === $holidays_table && $column === 'is_recurring' && $definition === 'TINYINT NOT NULL DEFAULT 0') {
      $wpdb->query("ALTER TABLE {$holidays_table} ADD COLUMN `is_recurring` TINYINT NOT NULL DEFAULT 0");
      return;
    }
    if ($table === $holidays_table && $column === 'created_at' && $definition === 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP') {
      $wpdb->query("ALTER TABLE {$holidays_table} ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
      return;
    }
    if ($table === $holidays_table && $column === 'updated_at' && $definition === 'DATETIME NULL') {
      $wpdb->query("ALTER TABLE {$holidays_table} ADD COLUMN `updated_at` DATETIME NULL");
      return;
    }
    if ($table === $services_table && $column === 'buffer_before' && $definition === 'INT NOT NULL DEFAULT 0') {
      $wpdb->query("ALTER TABLE {$services_table} ADD COLUMN `buffer_before` INT NOT NULL DEFAULT 0");
      return;
    }
    if ($table === $services_table && $column === 'buffer_after' && $definition === 'INT NOT NULL DEFAULT 0') {
      $wpdb->query("ALTER TABLE {$services_table} ADD COLUMN `buffer_after` INT NOT NULL DEFAULT 0");
      return;
    }
    if ($table === $services_table && $column === 'capacity' && $definition === 'INT NOT NULL DEFAULT 1') {
      $wpdb->query("ALTER TABLE {$services_table} ADD COLUMN `capacity` INT NOT NULL DEFAULT 1");
    }
    // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
  }

  private static function run_add_index_query(string $table, string $index, array $columns): void {
    global $wpdb;

    $bookings_table = $wpdb->prefix . 'pointlybooking_bookings';
    $categories_table = $wpdb->prefix . 'pointlybooking_categories';
    $services_table = $wpdb->prefix . 'pointlybooking_services';
    $extras_table = $wpdb->prefix . 'pointlybooking_service_extras';
    $holidays_table = $wpdb->prefix . 'pointlybooking_holidays';
    $schedules_table = $wpdb->prefix . 'pointlybooking_schedules';

    // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- ALTER TABLE cannot use value placeholders; each branch targets a hardcoded plugin table with a sanitized WordPress prefix.
    if ($table === $bookings_table && $index === 'agent_start_date' && $columns === ['agent_id', 'start_date']) {
      $wpdb->query("ALTER TABLE {$bookings_table} ADD INDEX `agent_start_date` (`agent_id`, `start_date`)");
      return;
    }
    if ($table === $bookings_table && $index === 'service_start_date' && $columns === ['service_id', 'start_date']) {
      $wpdb->query("ALTER TABLE {$bookings_table} ADD INDEX `service_start_date` (`service_id`, `start_date`)");
      return;
    }
    if ($table === $categories_table && $index === 'sort_order' && $columns === ['sort_order']) {
      $wpdb->query("ALTER TABLE {$categories_table} ADD INDEX `sort_order` (`sort_order`)");
      return;
    }
    if ($table === $services_table && $index === 'sort_order' && $columns === ['sort_order']) {
      $wpdb->query("ALTER TABLE {$services_table} ADD INDEX `sort_order` (`sort_order`)");
      return;
    }
    if ($table === $extras_table && $index === 'sort_order' && $columns === ['sort_order']) {
      $wpdb->query("ALTER TABLE {$extras_table} ADD INDEX `sort_order` (`sort_order`)");
      return;
    }
    if ($table === $holidays_table && $index === 'agent_id' && $columns === ['agent_id']) {
      $wpdb->query("ALTER TABLE {$holidays_table} ADD INDEX `agent_id` (`agent_id`)");
      return;
    }
    if ($table === $schedules_table && $index === 'agent_day' && $columns === ['agent_id', 'day_of_week']) {
      $wpdb->query("ALTER TABLE {$schedules_table} ADD INDEX `agent_day` (`agent_id`, `day_of_week`)");
    }
    // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
  }

  private static function seed_default_agent_hours() : void {
    global $wpdb;
    $t_agents = $wpdb->prefix . 'pointlybooking_agents';
    $t_hours  = $wpdb->prefix . 'pointlybooking_agent_working_hours';
    if (!self::is_safe_sql_identifier($t_agents) || !self::is_safe_sql_identifier($t_hours)) {
      return;
    }
    $agents_table = $t_agents;
    $hours_table = $t_hours;

    $agents = $wpdb->get_results(
      "SELECT id FROM {$agents_table} ORDER BY id ASC",
      ARRAY_A
    ) ?: [];
    foreach ($agents as $a) {
      $aid = (int)$a['id'];

        $exists = (int) $wpdb->get_var(
        $wpdb->prepare(
          "SELECT COUNT(*) FROM {$hours_table} WHERE agent_id=%d",
          $aid
        )
      );
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
    self::define_constants();
    self::includes();
    // Usually we do not remove caps on deactivate (optional).
    // POINTLYBOOKING_RolesHelper::remove_capabilities();
  }

  public static function register_blocks() : void {
    $block_dir = POINTLYBOOKING_PLUGIN_PATH . 'blocks/build/book-form';

    if (file_exists($block_dir . '/block.json')) {
      register_block_type($block_dir, [
        'render_callback' => [__CLASS__, 'render_booking_form_block']
      ]);
      return;
    }

    $src_json = POINTLYBOOKING_PLUGIN_PATH . 'blocks/src/book-form/block.json';
    if (!file_exists($src_json)) return;

    $asset_file = POINTLYBOOKING_PLUGIN_PATH . 'blocks/build/book-form/index.asset.php';
    $deps = [];
    $ver = self::VERSION;

    if (file_exists($asset_file)) {
      $asset = include $asset_file;
      $deps = $asset['dependencies'] ?? [];
      $ver  = $asset['version'] ?? self::VERSION;
    }

    wp_register_script(
      'pointlybooking-book-form-block',
      POINTLYBOOKING_PLUGIN_URL . 'blocks/build/book-form/index.js',
      $deps,
      $ver,
      true
    );

    register_block_type($src_json, [
      'editor_script'   => 'pointlybooking-book-form-block',
      'render_callback' => [__CLASS__, 'render_booking_form_block']
    ]);
  }

  public static function render_booking_form_block(array $attributes) : string {
    $service_id = isset($attributes['serviceId']) ? absint($attributes['serviceId']) : 0;
    if ($service_id <= 0) {
      return '<p>' . esc_html__('BookPoint: Service ID is required.', 'pointly-booking') . '</p>';
    }

    $default_date = isset($attributes['defaultDate']) ? sanitize_text_field($attributes['defaultDate']) : '';
    $hide_notes = !empty($attributes['hideNotes']) ? 1 : 0;
    $require_phone = !empty($attributes['requirePhone']) ? 1 : 0;
    $compact = !empty($attributes['compact']) ? 1 : 0;

    return do_shortcode(sprintf(
      '[pointlybooking_booking_form service_id="%d" default_date="%s" hide_notes="%d" require_phone="%d" compact="%d"]',
      $service_id,
      esc_attr($default_date),
      $hide_notes,
      $require_phone,
      $compact
    ));
  }

  public static function register_rest_routes() : void {
    register_rest_route('pointly-booking/v1', '/categories', [
      'methods' => 'GET',
      'permission_callback' => '__return_true',
      'callback' => 'pointlybooking_rest_get_categories',
    ]);

    register_rest_route('pointly-booking/v1', '/services', [
      'methods'  => 'GET',
      'callback' => 'pointlybooking_rest_get_services',
      'permission_callback' => '__return_true',
    ]);

    register_rest_route('pointly-booking/v1', '/extras', [
      'methods' => 'GET',
      'permission_callback' => '__return_true',
      'callback' => 'pointlybooking_rest_get_extras',
    ]);

    // Step 16: Agents endpoint
    register_rest_route('pointly-booking/v1', '/agents', [
      'methods' => 'GET',
      'callback' => 'pointlybooking_rest_get_agents',
      'permission_callback' => '__return_true',
    ]);

    register_rest_route('pointly-booking/v1', '/booking/create', [
      'methods' => 'POST',
      'permission_callback' => '__return_true',
      'callback' => 'pointlybooking_rest_create_booking',
    ]);

    register_rest_route('pointly-booking/v1', '/promo/validate', [
      'methods' => 'GET',
      'permission_callback' => '__return_true',
      'callback' => 'pointlybooking_rest_validate_promo',
    ]);

    register_rest_route('pointly-booking/v1', '/form-fields', [
      'methods' => 'GET',
      'permission_callback' => '__return_true',
      'callback' => 'pointlybooking_rest_get_form_fields',
    ]);

    // Step 18: Service agents endpoint
    register_rest_route('pointly-booking/v1', '/service-agents', [
      'methods' => 'GET',
      'callback' => [__CLASS__, 'rest_get_service_agents'],
      'permission_callback' => '__return_true',
    ]);

    // Step 21: Manage booking slots endpoint
    register_rest_route('pointly-booking/v1', '/manage/slots', [
      'methods' => 'GET',
      'callback' => [__CLASS__, 'rest_manage_slots'],
      'permission_callback' => '__return_true',
    ]);

    // ----------------------------
    // Admin: Agents list (React UI)
    // GET /wp-json/pointly-booking/v1/admin/agents
    // ----------------------------
    register_rest_route('pointly-booking/v1', '/admin/agents', [
      'methods'  => 'GET',
      'permission_callback' => [__CLASS__, 'rest_can_manage_agents'],
        'callback' => function(\WP_REST_Request $req){

        if (!current_user_can('administrator') && !current_user_can('pointlybooking_manage_settings') && !current_user_can('pointlybooking_manage_bookings')) {
          return new \WP_REST_Response(['status'=>'error','message'=>'Forbidden'], 403);
        }

        global $wpdb;
        $tA = $wpdb->prefix . 'pointlybooking_agents';
        if (!self::is_safe_sql_identifier($tA)) {
          return new \WP_REST_Response(['status' => 'success', 'data' => []], 200);
        }
        $agents_table = $tA;

        $rows = $wpdb->get_results(
          "SELECT * FROM {$agents_table} ORDER BY id DESC",
          ARRAY_A
        ) ?: [];

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
    // GET /wp-json/pointly-booking/v1/admin/bookings?q=&status=&sort=&date_from=&date_to=&page=&per=
    // ----------------------------
    register_rest_route('pointly-booking/v1', '/admin/bookings', [
      'methods'  => 'GET',
      'permission_callback' => [__CLASS__, 'rest_can_manage_bookings'],
      'callback' => function(\WP_REST_Request $req){

        if (!current_user_can('administrator') && !current_user_can('pointlybooking_manage_bookings')) {
          return new \WP_REST_Response(['status'=>'error','message'=>'Forbidden'], 403);
        }

        global $wpdb;
        $b = $wpdb->prefix . 'pointlybooking_bookings';
        $c = $wpdb->prefix . 'pointlybooking_customers';
        $s = $wpdb->prefix . 'pointlybooking_services';
        $a = $wpdb->prefix . 'pointlybooking_agents';
        if (
          !self::is_safe_sql_identifier($b)
          || !self::is_safe_sql_identifier($c)
          || !self::is_safe_sql_identifier($s)
          || !self::is_safe_sql_identifier($a)
        ) {
          return new \WP_REST_Response(['status' => 'error', 'message' => 'Invalid table configuration'], 500);
        }
        $bookings_table = $b;
        $customers_table = $c;
        $services_table = $s;
        $agents_table = $a;

        $q        = sanitize_text_field($req->get_param('q') ?? '');
        $status   = sanitize_text_field($req->get_param('status') ?? 'all');
        $sort     = sanitize_text_field($req->get_param('sort') ?? 'desc'); // desc|asc
        $dateFrom = sanitize_text_field($req->get_param('date_from') ?? ''); // YYYY-MM-DD
        $dateTo   = sanitize_text_field($req->get_param('date_to') ?? '');   // YYYY-MM-DD

        $page   = max(1, (int)($req->get_param('page') ?? 1));
        $per    = min(50, max(10, (int)($req->get_param('per') ?? 20)));
        $offset = ($page - 1) * $per;
        $order = (strtolower($sort) === 'asc') ? 'ASC' : 'DESC';
        $status_value = ($status && $status !== 'all') ? strtolower($status) : '';
        $date_from_value = ($dateFrom && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) ? ($dateFrom . ' 00:00:00') : '';
        $date_to_value = ($dateTo && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) ? ($dateTo . ' 23:59:59') : '';
        $like = $q ? ('%' . $wpdb->esc_like($q) . '%') : '';

        $total = (int) $wpdb->get_var(
          $wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$bookings_table} b
             LEFT JOIN {$customers_table} cust ON b.customer_id = cust.id
             LEFT JOIN {$services_table} srv ON b.service_id = srv.id
             LEFT JOIN {$agents_table} ag ON b.agent_id = ag.id
             WHERE (%d = 0 OR LOWER(b.status) = %s)
               AND (%d = 0 OR b.start_datetime >= %s)
               AND (%d = 0 OR b.start_datetime <= %s)
               AND (%d = 0 OR (
                 CONCAT(cust.first_name, ' ', cust.last_name) LIKE %s OR
                 cust.email LIKE %s OR
                 srv.name LIKE %s OR
                 ag.first_name LIKE %s OR
                 ag.last_name LIKE %s
               ))",
            $status_value !== '' ? 1 : 0,
            $status_value,
            $date_from_value !== '' ? 1 : 0,
            $date_from_value,
            $date_to_value !== '' ? 1 : 0,
            $date_to_value,
            $like !== '' ? 1 : 0,
            $like,
            $like,
            $like,
            $like,
            $like
          )
        );

        if ($order === 'ASC') {
          $items = $wpdb->get_results(
            $wpdb->prepare(
              "SELECT
                 b.id,
                 b.start_datetime,
                 b.end_datetime,
                 b.status,
                 CONCAT(cust.first_name, ' ', cust.last_name) as customer_name,
                 cust.email as customer_email,
                 srv.name as service_name,
                 CONCAT(ag.first_name, ' ', ag.last_name) as agent_name
               FROM {$bookings_table} b
               LEFT JOIN {$customers_table} cust ON b.customer_id = cust.id
               LEFT JOIN {$services_table} srv ON b.service_id = srv.id
               LEFT JOIN {$agents_table} ag ON b.agent_id = ag.id
               WHERE (%d = 0 OR LOWER(b.status) = %s)
                 AND (%d = 0 OR b.start_datetime >= %s)
                 AND (%d = 0 OR b.start_datetime <= %s)
                 AND (%d = 0 OR (
                   CONCAT(cust.first_name, ' ', cust.last_name) LIKE %s OR
                   cust.email LIKE %s OR
                   srv.name LIKE %s OR
                   ag.first_name LIKE %s OR
                   ag.last_name LIKE %s
                 ))
               ORDER BY b.start_datetime ASC
               LIMIT %d OFFSET %d",
              $status_value !== '' ? 1 : 0,
              $status_value,
              $date_from_value !== '' ? 1 : 0,
              $date_from_value,
              $date_to_value !== '' ? 1 : 0,
              $date_to_value,
              $like !== '' ? 1 : 0,
              $like,
              $like,
              $like,
              $like,
              $like,
              $per,
              $offset
            ),
            ARRAY_A
          );
        } else {
          $items = $wpdb->get_results(
            $wpdb->prepare(
              "SELECT
                 b.id,
                 b.start_datetime,
                 b.end_datetime,
                 b.status,
                 CONCAT(cust.first_name, ' ', cust.last_name) as customer_name,
                 cust.email as customer_email,
                 srv.name as service_name,
                 CONCAT(ag.first_name, ' ', ag.last_name) as agent_name
               FROM {$bookings_table} b
               LEFT JOIN {$customers_table} cust ON b.customer_id = cust.id
               LEFT JOIN {$services_table} srv ON b.service_id = srv.id
               LEFT JOIN {$agents_table} ag ON b.agent_id = ag.id
               WHERE (%d = 0 OR LOWER(b.status) = %s)
                 AND (%d = 0 OR b.start_datetime >= %s)
                 AND (%d = 0 OR b.start_datetime <= %s)
                 AND (%d = 0 OR (
                   CONCAT(cust.first_name, ' ', cust.last_name) LIKE %s OR
                   cust.email LIKE %s OR
                   srv.name LIKE %s OR
                   ag.first_name LIKE %s OR
                   ag.last_name LIKE %s
                 ))
               ORDER BY b.start_datetime DESC
               LIMIT %d OFFSET %d",
              $status_value !== '' ? 1 : 0,
              $status_value,
              $date_from_value !== '' ? 1 : 0,
              $date_from_value,
              $date_to_value !== '' ? 1 : 0,
              $date_to_value,
              $like !== '' ? 1 : 0,
              $like,
              $like,
              $like,
              $like,
              $like,
              $per,
              $offset
            ),
            ARRAY_A
          );
        }
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
    // GET /wp-json/pointly-booking/v1/admin/bookings/{id}
    // ----------------------------
    register_rest_route('pointly-booking/v1', '/admin/bookings/(?P<id>\d+)', [
      'methods'  => 'GET',
      'permission_callback' => [__CLASS__, 'rest_can_manage_bookings'],
      'callback' => function(\WP_REST_Request $req){

        if (!current_user_can('administrator') && !current_user_can('pointlybooking_manage_bookings')) {
          return new \WP_REST_Response(['status'=>'error','message'=>'Forbidden'], 403);
        }

        global $wpdb;
        $id = (int) $req['id'];

        $b = $wpdb->prefix . 'pointlybooking_bookings';
        $c = $wpdb->prefix . 'pointlybooking_customers';
        $s = $wpdb->prefix . 'pointlybooking_services';
        $a = $wpdb->prefix . 'pointlybooking_agents';
        $tFields   = $wpdb->prefix . 'pointlybooking_form_fields';
        if (
          !self::is_safe_sql_identifier($b)
          || !self::is_safe_sql_identifier($c)
          || !self::is_safe_sql_identifier($s)
          || !self::is_safe_sql_identifier($a)
          || !self::is_safe_sql_identifier($tFields)
        ) {
          return new \WP_REST_Response(['status' => 'error', 'message' => 'Invalid table configuration'], 500);
        }
        $bookings_table = $b;
        $customers_table = $c;
        $services_table = $s;
        $agents_table = $a;
        $fields_table = $tFields;

        // Get booking with all JOINs for complete data
        $row = $wpdb->get_row(
          $wpdb->prepare(
            "SELECT
               b.*,
               CONCAT(cust.first_name, ' ', cust.last_name) as customer_name,
               cust.email as customer_email,
               cust.phone as customer_phone,
               srv.name as service_name,
               srv.price_cents as service_price_cents,
               CONCAT(ag.first_name, ' ', ag.last_name) as agent_name
             FROM {$bookings_table} b
             LEFT JOIN {$customers_table} cust ON b.customer_id = cust.id
             LEFT JOIN {$services_table} srv ON b.service_id = srv.id
             LEFT JOIN {$agents_table} ag ON b.agent_id = ag.id
             WHERE b.id = %d",
            $id
          ),
          ARRAY_A
        );
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
        $t_fields_raw = $wpdb->prefix . 'pointlybooking_form_fields';
        $has_form_fields = pointlybooking_db_table_exists($t_fields_raw);
        if ($has_form_fields) {
          $defs = $wpdb->get_results(
            $wpdb->prepare(
              "SELECT * FROM {$fields_table} ORDER BY sort_order ASC, id ASC LIMIT %d",
              100
            ),
            ARRAY_A
          ) ?: [];
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
    // POST /wp-json/pointly-booking/v1/admin/bookings/{id}/status
    // body: { status: pending|confirmed|cancelled }
    // ----------------------------
    register_rest_route('pointly-booking/v1', '/admin/bookings/(?P<id>\d+)/status', [
      'methods'  => 'POST',
      'permission_callback' => [__CLASS__, 'rest_can_manage_bookings'],
      'callback' => function(\WP_REST_Request $req){

        if (!current_user_can('administrator') && !current_user_can('pointlybooking_manage_bookings')) {
          return new \WP_REST_Response(['status'=>'error','message'=>'Forbidden'], 403);
        }

        global $wpdb;
        $id = (int) $req['id'];
        $status = sanitize_text_field($req->get_param('status') ?? '');

        $allowed = ['pending','confirmed','cancelled'];
        if (!in_array($status, $allowed, true)) {
          return new \WP_REST_Response(['status'=>'error','message'=>'Invalid status'], 400);
        }

        $t = $wpdb->prefix . 'pointlybooking_bookings';
        $wpdb->update($t, ['status'=>$status], ['id'=>$id], ['%s'], ['%d']);

        return new \WP_REST_Response(['status'=>'success'], 200);
      }
    ]);
  }

  public static function rest_get_services(\WP_REST_Request $request) {
    return pointlybooking_rest_get_services($request);
  }

  public static function rest_can_manage_agents() : bool {
    return current_user_can('administrator') || current_user_can('pointlybooking_manage_settings') || current_user_can('pointlybooking_manage_bookings');
  }

  public static function rest_can_manage_bookings() : bool {
    return current_user_can('administrator') || current_user_can('pointlybooking_manage_bookings');
  }

  public static function rest_get_agents(\WP_REST_Request $request) {
    return pointlybooking_rest_get_agents($request);
  }

  public static function rest_get_service_agents(\WP_REST_Request $request) {
    $service_id = absint($request->get_param('service_id'));
    if ($service_id <= 0) {
      return rest_ensure_response([
        'status' => 'success',
        'data' => [],
      ]);
    }

    $agents = POINTLYBOOKING_ServiceAgentModel::get_agents_for_service($service_id);
    $items = [];

    foreach ($agents as $a) {
      $items[] = [
        'id' => (int)$a['id'],
        'name' => POINTLYBOOKING_AgentModel::display_name($a),
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

    if ($service_id <= 0 || !pointlybooking_is_valid_ymd($date)) {
      return rest_ensure_response(['status' => 'success', 'data' => []]);
    }

    $service = POINTLYBOOKING_ServiceModel::find($service_id);
    if (!$service) return rest_ensure_response(['status' => 'success', 'data' => []]);

    $duration = (int)($service['duration_minutes'] ?? 60);

    $slots = POINTLYBOOKING_AvailabilityHelper::get_available_slots_for_date(
      $service_id,
      $date,
      $duration,
      $agent_id,
      $exclude_id
    );

    return rest_ensure_response(['status' => 'success', 'data' => $slots]);
  }

  public static function register_admin_menu() : void {
    static $did_register = false;
    if ($did_register) return;
    $did_register = true;

    $cap = function (string $pointlybooking_cap): string {
      // If the user has the plugin cap, use it. Otherwise fall back to admin capability so admins always see the menu.
      if (current_user_can($pointlybooking_cap)) return $pointlybooking_cap;
      if (current_user_can('manage_options')) return 'manage_options';
      if (current_user_can('activate_plugins')) return 'activate_plugins';
      if (function_exists('is_network_admin') && is_network_admin() && current_user_can('manage_network_options')) return 'manage_network_options';
      return $pointlybooking_cap;
    };

    if (
      !current_user_can('pointlybooking_manage_bookings') &&
      !current_user_can('pointlybooking_manage_services') &&
      !current_user_can('pointlybooking_manage_customers') &&
      !current_user_can('pointlybooking_manage_agents') &&
      !current_user_can('pointlybooking_manage_settings') &&
      !current_user_can('pointlybooking_manage_tools') &&
      !current_user_can('manage_options') &&
      !current_user_can('activate_plugins') &&
      !(function_exists('is_network_admin') && is_network_admin() && current_user_can('manage_network_options'))
    ) {
      return;
    }

    // Free distribution exposes all built-in features directly in the admin app.
    $admin_app_cb = 'pointlybooking_render_admin_app';

    add_menu_page(
      __('BookPoint', 'pointly-booking'),
      __('BookPoint', 'pointly-booking'),
      $cap('pointlybooking_manage_bookings'),
      'pointlybooking_dashboard',
      'pointlybooking_render_admin_app',
      'dashicons-calendar-alt',
      56
    );

    // Fallback access point: if a theme/plugin hides custom top-level menus, keep an entry under Settings for admins.
    // This does not replace the main BookPoint menu.
    if (current_user_can('manage_options')) {
      add_submenu_page(
        'options-general.php',
        __('BookPoint', 'pointly-booking'),
        __('BookPoint', 'pointly-booking'),
        'manage_options',
        'pointlybooking_dashboard',
        'pointlybooking_render_admin_app'
      );
      // Guaranteed fallback access from Plugins screen in restrictive admin-menu environments.
      add_submenu_page(
        'plugins.php',
        __('BookPoint', 'pointly-booking'),
        __('BookPoint', 'pointly-booking'),
        'manage_options',
        'pointlybooking_dashboard',
        'pointlybooking_render_admin_app'
      );
    }
    if (function_exists('is_network_admin') && is_network_admin() && current_user_can('manage_network_options')) {
      add_submenu_page(
        'settings.php',
        __('BookPoint', 'pointly-booking'),
        __('BookPoint', 'pointly-booking'),
        'manage_network_options',
        'pointlybooking_dashboard',
        'pointlybooking_render_admin_app'
      );
      add_submenu_page(
        'plugins.php',
        __('BookPoint', 'pointly-booking'),
        __('BookPoint', 'pointly-booking'),
        'manage_network_options',
        'pointlybooking_dashboard',
        'pointlybooking_render_admin_app'
      );
    }

    add_submenu_page(
      'pointlybooking_dashboard',
      __('Dashboard', 'pointly-booking'),
      __('Dashboard', 'pointly-booking'),
      $cap('pointlybooking_manage_bookings'),
      'pointlybooking_dashboard',
      'pointlybooking_render_admin_app',
      0
    );

    add_submenu_page(
      'pointlybooking_dashboard',
      __('How to Use', 'pointly-booking'),
      __('How to Use', 'pointly-booking'),
      $cap('pointlybooking_manage_bookings'),
      'pointlybooking_how_to_use',
      'pointlybooking_render_admin_app'
    );

    add_submenu_page(
      'pointlybooking_dashboard',
      __('Bookings', 'pointly-booking'),
      __('Bookings', 'pointly-booking'),
      $cap('pointlybooking_manage_bookings'),
      'pointlybooking_bookings',
      'pointlybooking_render_admin_app'
    );

    add_submenu_page(
      null,
      __('Booking Edit', 'pointly-booking'),
      __('Booking Edit', 'pointly-booking'),
      $cap('pointlybooking_manage_bookings'),
      'pointlybooking_bookings_edit',
      'pointlybooking_render_admin_app'
    );

    add_submenu_page(
      'pointlybooking_dashboard',
      __('Calendar', 'pointly-booking'),
      __('Calendar', 'pointly-booking'),
      $cap('pointlybooking_manage_bookings'),
      'pointlybooking_calendar',
      'pointlybooking_render_admin_app'
    );

    add_submenu_page(
      'pointlybooking_dashboard',
      __('Schedule', 'pointly-booking'),
      __('Schedule', 'pointly-booking'),
      $cap('pointlybooking_manage_settings'),
      'pointlybooking_schedule',
      'pointlybooking_render_admin_app'
    );

    add_submenu_page(
      'pointlybooking_dashboard',
      __('Holidays', 'pointly-booking'),
      __('Holidays', 'pointly-booking'),
      $cap('pointlybooking_manage_settings'),
      'pointlybooking_holidays',
      $admin_app_cb
    );


    add_submenu_page(
      'pointlybooking_dashboard',
      __('Catalog', 'pointly-booking'),
      __('Catalog', 'pointly-booking'),
      $cap('pointlybooking_manage_services'),
      'pointlybooking_catalog',
      'pointlybooking_render_admin_app_catalog'
    );

    add_submenu_page(
      'pointlybooking_dashboard',
      __('Services', 'pointly-booking'),
      __('Services', 'pointly-booking'),
      $cap('pointlybooking_manage_services'),
      'pointlybooking_services',
      'pointlybooking_render_admin_app'
    );

    add_submenu_page(
      'pointlybooking_dashboard',
      __('Categories', 'pointly-booking'),
      __('Categories', 'pointly-booking'),
      $cap('pointlybooking_manage_services'),
      'pointlybooking_categories',
      'pointlybooking_render_admin_app'
    );

    add_submenu_page(
      'pointlybooking_dashboard',
      __('Service Extras', 'pointly-booking'),
      __('Service Extras', 'pointly-booking'),
      $cap('pointlybooking_manage_services'),
      'pointlybooking_extras',
      $admin_app_cb
    );

    add_submenu_page(
      'pointlybooking_dashboard',
      __('Locations', 'pointly-booking'),
      __('Locations', 'pointly-booking'),
      $cap('pointlybooking_manage_settings'),
      'pointlybooking_locations',
      $admin_app_cb
    );

    add_submenu_page(
      'pointlybooking_dashboard',
      __('Promo Codes', 'pointly-booking'),
      __('Promo Codes', 'pointly-booking'),
      $cap('pointlybooking_manage_settings'),
      'pointlybooking_promo_codes',
      $admin_app_cb
    );

    add_submenu_page(
      'pointlybooking_dashboard',
      __('Form Fields', 'pointly-booking'),
      __('Form Fields', 'pointly-booking'),
      $cap('pointlybooking_manage_settings'),
      'bp-form-fields',
      'pointlybooking_render_admin_app'
    );
    add_submenu_page(
      'pointlybooking_dashboard',
      __('Form Fields', 'pointly-booking'),
      __('Form Fields', 'pointly-booking'),
      $cap('pointlybooking_manage_settings'),
      'pointlybooking_form_fields',
      'pointlybooking_render_admin_app'
    );

    add_submenu_page(
      'pointlybooking_dashboard',
      __('Booking Form Designer', 'pointly-booking'),
      __('Booking Form Designer', 'pointly-booking'),
      $cap('pointlybooking_manage_settings'),
      'pointlybooking_design_form',
      'pointlybooking_render_admin_app'
    );

    add_submenu_page(
      'pointlybooking_dashboard',
      __('Form Fields', 'pointly-booking'),
      __('Form Fields', 'pointly-booking'),
      $cap('pointlybooking_manage_settings'),
      'pointlybooking_form_fields_edit',
      [__CLASS__, 'render_form_fields_edit']
    );

    add_submenu_page(
      'pointlybooking_dashboard',
      __('Customers', 'pointly-booking'),
      __('Customers', 'pointly-booking'),
      $cap('pointlybooking_manage_customers'),
      'pointlybooking_customers',
      'pointlybooking_render_admin_app'
    );

    add_submenu_page(
      'pointlybooking_dashboard',
      __('Settings', 'pointly-booking'),
      __('Settings', 'pointly-booking'),
      $cap('pointlybooking_manage_settings'),
      'pointlybooking_settings',
      'pointlybooking_render_admin_app'
    );

    add_submenu_page(
      'pointlybooking_dashboard',
      __('Notifications', 'pointly-booking'),
      __('Notifications', 'pointly-booking'),
      $cap('pointlybooking_manage_settings'),
      'pointlybooking_notifications',
      'pointlybooking_render_admin_app'
    );

    add_submenu_page(
      'pointlybooking_dashboard',
      __('Audit Log', 'pointly-booking'),
      __('Audit Log', 'pointly-booking'),
      $cap('pointlybooking_manage_tools'),
      'pointlybooking_audit',
      'pointlybooking_render_admin_app'
    );

    add_submenu_page(
      'pointlybooking_dashboard',
      __('Tools', 'pointly-booking'),
      __('Tools', 'pointly-booking'),
      $cap('pointlybooking_manage_tools'),
      'pointlybooking_tools',
      'pointlybooking_render_admin_app'
    );

    add_submenu_page(
      'tools.php',
      __('BookPoint Tools', 'pointly-booking'),
      __('BookPoint Tools', 'pointly-booking'),
      $cap('pointlybooking_manage_tools'),
      'pointlybooking_tools',
      'pointlybooking_render_admin_app'
    );

    add_submenu_page(
      'pointlybooking_dashboard',
      __('Agents', 'pointly-booking'),
      __('Agents', 'pointly-booking'),
      $cap('pointlybooking_manage_agents'),
      'pointlybooking_agents',
      'pointlybooking_render_admin_app'
    );

    // Hidden pages for internal use
      add_submenu_page(
        null,
        __('Edit Agent', 'pointly-booking'),
        __('Edit Agent', 'pointly-booking'),
        'pointlybooking_manage_agents',
        'pointlybooking_agents_edit',
        [__CLASS__, 'render_agents_edit']
      );

      add_submenu_page(
        null,
        __('Edit Location', 'pointly-booking'),
        __('Edit Location', 'pointly-booking'),
        'pointlybooking_manage_settings',
        'pointlybooking_locations_edit',
        $admin_app_cb
      );

      add_submenu_page(
        null,
        __('Edit Location Category', 'pointly-booking'),
        __('Edit Location Category', 'pointly-booking'),
        'pointlybooking_manage_settings',
        'pointlybooking_location_categories_edit',
        $admin_app_cb
      );

    add_submenu_page(
      null,
      __('Delete Agent', 'pointly-booking'),
      __('Delete Agent', 'pointly-booking'),
      'pointlybooking_manage_agents',
      'pointlybooking_agents_delete',
      [__CLASS__, 'render_agents_delete']
    );

    add_submenu_page(
      null,
      __('Edit Service', 'pointly-booking'),
      __('Edit Service', 'pointly-booking'),
      'pointlybooking_manage_services',
      'pointlybooking_services_edit',
      [__CLASS__, 'render_services_edit']
    );

    add_submenu_page(
      null,
      __('Edit Extra', 'pointly-booking'),
      __('Edit Extra', 'pointly-booking'),
      'pointlybooking_manage_services',
      'pointlybooking_extras_edit',
      $admin_app_cb
    );

    add_submenu_page(
      null,
      __('Delete Extra', 'pointly-booking'),
      __('Delete Extra', 'pointly-booking'),
      'pointlybooking_manage_services',
      'pointlybooking_extras_delete',
      [__CLASS__, 'render_extras_delete']
    );

    add_submenu_page(
      null,
      __('Edit Category', 'pointly-booking'),
      __('Edit Category', 'pointly-booking'),
      'pointlybooking_manage_services',
      'pointlybooking_categories_edit',
      [__CLASS__, 'render_categories_edit']
    );

    add_submenu_page(
      null,
      __('Delete Category', 'pointly-booking'),
      __('Delete Category', 'pointly-booking'),
      'pointlybooking_manage_services',
      'pointlybooking_categories_delete',
      [__CLASS__, 'render_categories_delete']
    );

    add_submenu_page(
      null,
      __('Delete Service', 'pointly-booking'),
      __('Delete Service', 'pointly-booking'),
      'pointlybooking_manage_services',
      'pointlybooking_services_delete',
      [__CLASS__, 'render_services_delete']
    );

    add_submenu_page(
      null,
      __('Edit Promo Code', 'pointly-booking'),
      __('Edit Promo Code', 'pointly-booking'),
      'pointlybooking_manage_settings',
      'pointlybooking_promo_codes_edit',
      [__CLASS__, 'render_promo_codes_edit']
    );

    add_submenu_page(
      null,
      __('Delete Promo Code', 'pointly-booking'),
      __('Delete Promo Code', 'pointly-booking'),
      'pointlybooking_manage_settings',
      'pointlybooking_promo_codes_delete',
      [__CLASS__, 'render_promo_codes_delete']
    );

    add_submenu_page(
      null,
      __('Confirm Booking', 'pointly-booking'),
      __('Confirm Booking', 'pointly-booking'),
      'pointlybooking_manage_bookings',
      'pointlybooking_booking_confirm',
      [__CLASS__, 'render_booking_confirm']
    );

    add_submenu_page(
      null,
      __('Cancel Booking', 'pointly-booking'),
      __('Cancel Booking', 'pointly-booking'),
      'pointlybooking_manage_bookings',
      'pointlybooking_booking_cancel',
      [__CLASS__, 'render_booking_cancel']
    );

    add_submenu_page(
      null,
      __('View Customer', 'pointly-booking'),
      __('View Customer', 'pointly-booking'),
      'pointlybooking_manage_customers',
      'pointlybooking_customers_view',
      [__CLASS__, 'render_customer_view']
    );

    // Hidden page for internal use (React)
    add_submenu_page(
      null,
      __('Edit Customer', 'pointly-booking'),
      __('Edit Customer', 'pointly-booking'),
      'pointlybooking_manage_customers',
      'pointlybooking_customers_edit',
      'pointlybooking_render_admin_app'
    );

  }

  public static function ensure_admin_menu_visible(): void {
    if (!current_user_can('manage_options') && !current_user_can('activate_plugins')) return;

    global $menu;
    if (is_array($menu)) {
      foreach ($menu as $item) {
        if (!is_array($item)) continue;
        if (($item[2] ?? '') === 'pointlybooking_dashboard') {
          return;
        }
      }
    }

    // Re-add the top-level entry if a plugin/theme removed it.
    add_menu_page(
      __('BookPoint', 'pointly-booking'),
      __('BookPoint', 'pointly-booking'),
      current_user_can('manage_options') ? 'manage_options' : 'activate_plugins',
      'pointlybooking_dashboard',
      'pointlybooking_render_admin_app',
      'dashicons-calendar-alt',
      56
    );
  }

  private static function is_menu_debug_enabled(): bool {
    if (!is_admin()) return false;
    if (!current_user_can('manage_options') && !current_user_can('activate_plugins')) return false;
    return sanitize_text_field((string) filter_input(INPUT_GET, 'pointlybooking_menu_debug', FILTER_UNSAFE_RAW)) === '1';
  }

  private static function menu_has_slug(string $slug): bool {
    global $menu;
    if (!is_array($menu)) return false;
    foreach ($menu as $item) {
      if (!is_array($item)) continue;
      if (($item[2] ?? '') === $slug) return true;
    }
    return false;
  }

  private static function callback_label($callback): string {
    if (is_string($callback)) return $callback;
    if (is_array($callback) && isset($callback[0], $callback[1])) {
      $left = is_object($callback[0]) ? get_class($callback[0]) : (string) $callback[0];
      return $left . '::' . (string) $callback[1];
    }
    if ($callback instanceof Closure) return 'Closure';
    return 'Unknown callback';
  }

  private static function callback_file_and_line($callback): array {
    try {
      if (is_string($callback) && function_exists($callback)) {
        $ref = new ReflectionFunction($callback);
        return ['file' => (string) $ref->getFileName(), 'line' => (int) $ref->getStartLine()];
      }
      if ($callback instanceof Closure) {
        $ref = new ReflectionFunction($callback);
        return ['file' => (string) $ref->getFileName(), 'line' => (int) $ref->getStartLine()];
      }
      if (is_array($callback) && isset($callback[0], $callback[1])) {
        $ref = new ReflectionMethod($callback[0], (string) $callback[1]);
        return ['file' => (string) $ref->getFileName(), 'line' => (int) $ref->getStartLine()];
      }
    } catch (\Throwable $e) {
      // Ignore reflection failures for non-standard callbacks.
    }
    return ['file' => '', 'line' => 0];
  }

  private static function callback_mentions_menu_removal(string $file, int $line): bool {
    if ($file === '' || !is_readable($file)) return false;
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines) || empty($lines)) return false;

    $start = max(1, $line - 30);
    $end = min(count($lines), $line + 180);
    $chunk = implode("\n", array_slice($lines, $start - 1, $end - $start + 1));
    if ($chunk === '') return false;

    if (strpos($chunk, 'remove_menu_page') === false && strpos($chunk, 'remove_submenu_page') === false) {
      return false;
    }

    if (strpos($chunk, 'pointlybooking_dashboard') !== false || strpos($chunk, 'toplevel_page_pointlybooking_dashboard') !== false) {
      return true;
    }

    // Generic menu removals are still suspicious in this context.
    return true;
  }

  public static function debug_admin_menu_notice(): void {
    if (!self::is_menu_debug_enabled()) return;

    global $wp_filter;
    $exists = self::menu_has_slug('pointlybooking_dashboard');
    $rows = [];

    $hook = $wp_filter['admin_menu'] ?? null;
    if ($hook instanceof WP_Hook) {
      foreach ((array) $hook->callbacks as $priority => $callbacks) {
        foreach ((array) $callbacks as $entry) {
          $cb = $entry['function'] ?? null;
          if ($cb === null) continue;

          $info = self::callback_file_and_line($cb);
          $file = (string) ($info['file'] ?? '');
          $line = (int) ($info['line'] ?? 0);
          if ($file === '') continue;

          $norm = wp_normalize_path($file);
          $pluginRoot = trailingslashit(wp_normalize_path(dirname(rtrim(POINTLYBOOKING_PLUGIN_PATH, '/\\'))));
          $ourRoot = wp_normalize_path(POINTLYBOOKING_PLUGIN_PATH);
          if ($pluginRoot === '' || strpos($norm, $pluginRoot) !== 0) continue;
          if (strpos($norm, $ourRoot) === 0) continue;

          $isLate = ((int) $priority >= 100000);
          $mentionsRemoval = self::callback_mentions_menu_removal($file, $line);
          if (!$isLate && !$mentionsRemoval) continue;

          $rel = ltrim(substr($norm, strlen($pluginRoot)), '/\\');
          $pluginSlug = strtok($rel, '/\\');

          $rows[] = [
            'priority' => (int) $priority,
            'callback' => self::callback_label($cb),
            'plugin' => (string) ($pluginSlug ?: ''),
            'file' => $rel,
            'line' => $line,
            'remover' => $mentionsRemoval ? 1 : 0,
            'late' => $isLate ? 1 : 0,
          ];
        }
      }
    }

    usort($rows, static function ($a, $b) {
      if ((int) $a['remover'] !== (int) $b['remover']) {
        return (int) $b['remover'] - (int) $a['remover'];
      }
      if ((int) $a['priority'] !== (int) $b['priority']) {
        return (int) $b['priority'] - (int) $a['priority'];
      }
      return strcmp((string) $a['callback'], (string) $b['callback']);
    });

    $likely = null;
    foreach ($rows as $row) {
      if ((int) $row['remover'] === 1) {
        $likely = $row;
        break;
      }
    }

    echo '<div class="notice notice-warning"><p><strong>BookPoint menu debug is ON</strong> ';
    echo '(disable by removing <code>pointlybooking_menu_debug=1</code> from URL).</p>';
    echo '<p><strong>pointlybooking_dashboard in $menu:</strong> ' . ($exists ? 'YES' : 'NO') . '</p>';
    if ($likely) {
      echo '<p><strong>Likely remover:</strong> ';
      echo esc_html((string) $likely['plugin']) . ' | ';
      echo esc_html((string) $likely['callback']) . ' | ';
      echo esc_html((string) $likely['file']) . ':' . (int) $likely['line'];
      echo '</p>';
    } else {
      echo '<p><strong>Likely remover:</strong> not detected from callback source scan.</p>';
    }

    if (!empty($rows)) {
      echo '<p><strong>Relevant admin_menu callbacks (other plugins):</strong></p>';
      echo '<table class="widefat striped" style="max-width:100%;margin:8px 0;"><thead><tr>';
      echo '<th>Priority</th><th>Plugin</th><th>Callback</th><th>File</th><th>Flags</th>';
      echo '</tr></thead><tbody>';
      $max = min(30, count($rows));
      for ($index = 0; $index < $max; $index++) {
        $row = $rows[$index];
        $flags = [];
        if ((int) $row['remover'] === 1) $flags[] = 'menu-remove';
        if ((int) $row['late'] === 1) $flags[] = 'late';
        echo '<tr>';
        echo '<td>' . (int) $row['priority'] . '</td>';
        echo '<td>' . esc_html((string) $row['plugin']) . '</td>';
        echo '<td><code>' . esc_html((string) $row['callback']) . '</code></td>';
        echo '<td><code>' . esc_html((string) $row['file']) . ':' . (int) $row['line'] . '</code></td>';
        echo '<td>' . esc_html(implode(', ', $flags)) . '</td>';
        echo '</tr>';
      }
      echo '</tbody></table>';
    }
    echo '</div>';
  }

  public static function enqueue_admin_assets(string $hook): void {
    $page = sanitize_key((string) filter_input(INPUT_GET, 'page', FILTER_UNSAFE_RAW));
    if ($page === '') return;

    // React admin bundle (All admin pages)
        $admin_react_pages = [
          'pointlybooking_dashboard', 'pointlybooking_bookings', 'pointlybooking_bookings_edit', 'pointlybooking_calendar', 'pointlybooking_schedule', 'pointlybooking_holidays', 'pointlybooking_catalog',
          'bp-form-fields', 'pointlybooking_form_fields', 'pointlybooking_services', 'pointlybooking_services_edit', 'pointlybooking_categories', 'pointlybooking_categories_edit', 'pointlybooking_extras', 'pointlybooking_extras_edit', 'pointlybooking_locations', 'pointlybooking_promo_codes',
          'pointlybooking_customers', 'pointlybooking_settings', 'pointlybooking_notifications', 'pointlybooking_agents', 'pointlybooking_audit', 'pointlybooking_tools',
          'pointlybooking_locations_edit', 'pointlybooking_location_categories_edit', 'pointlybooking_design_form',
          'pointlybooking_how_to_use',
          'pointlybooking_agents_edit',
          'pointlybooking_customers_edit'
        ];
    
    if (in_array($page, $admin_react_pages, true)) {
      // Ensure WP Dashicons are available if the admin UI references them.
      wp_enqueue_style('dashicons');

      $asset_path = POINTLYBOOKING_PLUGIN_PATH . 'build/admin.asset.php';
      $asset = [
        'dependencies' => ['react', 'react-dom', 'react-jsx-runtime'],
        'version' => self::VERSION,
      ];

      if (file_exists($asset_path)) {
        $asset = require $asset_path;
      }

      self::ensure_react_scripts();

      $admin_js_path = POINTLYBOOKING_PLUGIN_PATH . 'build/admin.js';
      $admin_js_mtime = self::safe_filemtime($admin_js_path);
      $admin_js_ver = (string) ($admin_js_mtime ?: (string)($asset['version'] ?? self::VERSION));

      wp_enqueue_script(
        'pointlybooking-admin',
        POINTLYBOOKING_PLUGIN_URL . 'build/admin.js',
        $asset['dependencies'],
        $admin_js_ver,
        true
      );

        $admin_css = POINTLYBOOKING_PLUGIN_PATH . 'build/index.jsx.css';
        $admin_css_ver = (string) (self::safe_filemtime($admin_css) ?: $admin_js_ver);
        if (file_exists($admin_css)) {
          wp_enqueue_style(
            'pointlybooking-admin',
            POINTLYBOOKING_PLUGIN_URL . 'build/index.jsx.css',
            [],
            $admin_css_ver
          );

          if (function_exists('is_rtl') && is_rtl()) {
            $admin_css_rtl = POINTLYBOOKING_PLUGIN_PATH . 'build/index.jsx-rtl.css';
            if (file_exists($admin_css_rtl)) {
              wp_enqueue_style(
                'pointlybooking-admin-rtl',
                POINTLYBOOKING_PLUGIN_URL . 'build/index.jsx-rtl.css',
                ['pointlybooking-admin'],
                $admin_css_ver
              );
            }
          }
        }

      add_filter('script_loader_src', function ($src, $handle) use ($admin_js_ver) {
        $src = is_string($src) ? $src : '';
        if ($src === '') {
          return $src;
        }
        if ($handle === 'pointlybooking-admin') {
          return add_query_arg('v', $admin_js_ver, $src);
        }
        return $src;
      }, 10, 2);
      add_filter('style_loader_src', function ($src, $handle) use ($admin_css_ver) {
        $src = is_string($src) ? $src : '';
        if ($src === '') {
          return $src;
        }
        if ($handle === 'pointlybooking-admin' || $handle === 'pointlybooking-admin-rtl') {
          return add_query_arg('v', $admin_css_ver, $src);
        }
        return $src;
      }, 10, 2);

      // Map page slug to route name
        $route_map = [
          'pointlybooking_dashboard' => 'dashboard',
          'pointlybooking_bookings' => 'bookings',
          'pointlybooking_bookings_edit' => 'bookings-edit',
          'pointlybooking_calendar' => 'calendar',
          'pointlybooking_schedule' => 'schedule',
          'pointlybooking_holidays' => 'holidays',
          'pointlybooking_catalog' => 'catalog',
          'bp-form-fields' => 'form-fields',
          'pointlybooking_form_fields' => 'form-fields',
          'pointlybooking_design_form' => 'design-form',
          'pointlybooking_how_to_use' => 'how-to',
          'pointlybooking_services' => 'services',
          'pointlybooking_categories' => 'categories',
          'pointlybooking_categories_edit' => 'categories-edit',
          'pointlybooking_extras' => 'extras',
          'pointlybooking_extras_edit' => 'extras-edit',
          'pointlybooking_locations' => 'locations',
          'pointlybooking_locations_edit' => 'locations-edit',
          'pointlybooking_location_categories_edit' => 'location-categories-edit',
          'pointlybooking_promo_codes' => 'promo-codes',
          'pointlybooking_customers' => 'customers',
          'pointlybooking_customers_edit' => 'customers-edit',
        'pointlybooking_settings' => 'settings',
        'pointlybooking_notifications' => 'notifications',
        'pointlybooking_agents' => 'agents',
        'pointlybooking_agents_edit' => 'agents-edit',
        'pointlybooking_audit' => 'audit',
        'pointlybooking_tools' => 'tools',
      ];

      $route = $route_map[$page] ?? 'dashboard';

      $icons_dir_rel = self::public_icons_dir_rel();

      $icons_build = '';
      $icons_max = 0;
      $icon_files = glob(POINTLYBOOKING_PLUGIN_PATH . $icons_dir_rel . '/*.svg');
      if (is_array($icon_files)) {
        foreach ($icon_files as $f) {
          $mt = self::safe_filemtime($f);
          if ($mt && $mt > $icons_max) $icons_max = $mt;
        }
      }
      if ($icons_max > 0) $icons_build = (string)$icons_max;

      $images_build = '';
      $images_max = 0;
      $images_dir = POINTLYBOOKING_PLUGIN_PATH . 'public/images';
      if (is_dir($images_dir)) {
        try {
          $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($images_dir, FilesystemIterator::SKIP_DOTS)
          );
          foreach ($it as $file) {
            if ($file->isFile()) {
              $mt = $file->getMTime();
              if ($mt && $mt > $images_max) $images_max = $mt;
            }
          }
        } catch (\Throwable $e) {
          // ignore
        }
      }
      if ($images_max > 0) $images_build = (string)$images_max;

        wp_localize_script('pointlybooking-admin', 'pointlybooking_ADMIN', [
          'restUrl' => esc_url_raw(rest_url('pointly-booking/v1')),
          'nonce'   => wp_create_nonce('wp_rest'),
          'adminNonce' => wp_create_nonce('pointlybooking_admin'),
          'adminPostUrl' => admin_url('admin-post.php'),
          'pluginUrl' => POINTLYBOOKING_PLUGIN_URL,
          'publicImagesUrl' => POINTLYBOOKING_PLUGIN_URL . 'public/images',
          'publicIconsUrl' => POINTLYBOOKING_PLUGIN_URL . $icons_dir_rel,
          'iconsProxyUrl' => POINTLYBOOKING_PLUGIN_URL . 'public/icon.php?file=',
          'route'   => $route,
          'page'    => $page,
          'build'   => ($admin_js_mtime > 0 ? (string) $admin_js_mtime : ''),
          'iconsBuild' => $icons_build,
          'imagesBuild' => $images_build,
          'timezone'=> wp_timezone_string(),
          'currency'=> (string)POINTLYBOOKING_SettingsHelper::get('currency', 'USD'),
          'currency_position'=> (string)POINTLYBOOKING_SettingsHelper::get('currency_position', 'before'),
        ]);

      wp_enqueue_media();
      return;
    }

    $action_param = sanitize_key((string) filter_input(INPUT_GET, 'action', FILTER_UNSAFE_RAW));

    if ($page === 'pointlybooking_categories_edit' || ($page === 'pointlybooking_categories' && $action_param === 'edit')) {
      wp_enqueue_media();
      return;
    }

    if ($page === 'pointlybooking_services_edit' || ($page === 'pointlybooking_services' && $action_param === 'edit')) {
      wp_enqueue_media();
      return;
    }

    if ($page === 'pointlybooking_extras_edit' || ($page === 'pointlybooking_extras' && $action_param === 'edit')) {
      wp_enqueue_media();
      return;
    }

    if ($page === 'pointlybooking_agents_edit' || ($page === 'pointlybooking_agents' && $action_param === 'edit')) {
      wp_enqueue_media();
      // React edit screens use window.wp.media directly; keep only the core media library enqueued.
      return;
    }
  }

  public static function render_dashboard() : void {
    echo '<div id="bp-admin-app" data-route="dashboard"></div>';
  }

  public static function render_services_index() : void {
    // React admin screen (keeps UI consistent + full-width layout)
    pointlybooking_render_admin_app();
  }

  public static function render_services_edit() : void {
    echo '<div id="bp-admin-app" data-route="services-edit"></div>';
  }

  public static function render_extras_edit() : void {
    echo '<div id="bp-admin-app" data-route="extras-edit"></div>';
  }

  public static function render_extras_delete() : void {
    (new POINTLYBOOKING_AdminExtrasController())->delete();
  }

  public static function render_categories_edit() : void {
    echo '<div id="bp-admin-app" data-route="categories-edit"></div>';
  }

  public static function render_categories_delete() : void {
    (new POINTLYBOOKING_AdminCategoriesController())->delete();
  }

  public static function render_services_delete() : void {
    (new POINTLYBOOKING_AdminServicesController())->delete();
  }

  public static function render_promo_codes_edit() : void {
    (new POINTLYBOOKING_AdminPromoCodesController())->edit();
  }

  public static function render_promo_codes_delete() : void {
    (new POINTLYBOOKING_AdminPromoCodesController())->delete();
  }

  public static function handle_services_save() : void {
    (new POINTLYBOOKING_AdminServicesController())->save();
  }

  public static function render_settings() : void {
    // Render React admin app so the Settings UI matches other screens (full-width + modern buttons).
    pointlybooking_render_admin_app();
  }

  public static function handle_settings_save() : void {
    (new POINTLYBOOKING_AdminSettingsController())->save();
  }

  public static function handle_settings_export_json(): void {
    (new POINTLYBOOKING_AdminSettingsController())->export_json();
  }

  public static function handle_settings_import_json(): void {
    (new POINTLYBOOKING_AdminSettingsController())->import_json();
  }

  public static function render_bookings() : void {
    // React admin screen (keeps UI consistent + full-width layout)
    pointlybooking_render_admin_app();
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
    (new POINTLYBOOKING_AdminFormFieldsController())->edit();
  }

  public static function render_booking_confirm() : void {
    (new POINTLYBOOKING_AdminBookingsController())->confirm();
  }

  public static function render_booking_cancel() : void {
    (new POINTLYBOOKING_AdminBookingsController())->cancel();
  }

  public static function handle_booking_notes_save() : void {
    (new POINTLYBOOKING_AdminBookingsController())->save_notes();
  }

  public static function render_customers() : void {
    // React admin screen (keeps UI consistent + full-width layout)
    pointlybooking_render_admin_app();
  }

  public static function render_customer_view() : void {
    (new POINTLYBOOKING_AdminCustomersController())->view();
  }

  public static function enqueue_public_styles_only(): void {
    $front_dir_rel = file_exists(POINTLYBOOKING_PLUGIN_PATH . 'public/build/front.js') ? 'public/build' : 'public';

    $front_css = POINTLYBOOKING_PLUGIN_PATH . $front_dir_rel . '/index.jsx.css';
    if (file_exists($front_css)) {
      $css_ver = (string) (self::safe_filemtime($front_css) ?: self::VERSION);
      wp_enqueue_style(
        'pointlybooking-front',
        POINTLYBOOKING_PLUGIN_URL . $front_dir_rel . '/index.jsx.css',
        [],
        $css_ver
      );
    }

    $front_css_overrides = POINTLYBOOKING_PLUGIN_PATH . 'public/front-overrides.css';
    if (file_exists($front_css_overrides)) {
      $css_overrides_ver = (string) (self::safe_filemtime($front_css_overrides) ?: self::VERSION);
      wp_enqueue_style(
        'pointlybooking-front-overrides',
        POINTLYBOOKING_PLUGIN_URL . 'public/front-overrides.css',
        ['pointlybooking-front'],
        $css_overrides_ver
      );
    }
  }

  public static function enqueue_public_assets(bool $force = false): void {
    if (!$force) {
      if (!is_singular()) return;

      global $post;
      if (
        !$post ||
        (!has_shortcode($post->post_content, 'pointlybooking_booking_form'))
      ) return;
    }

    $front_dir_rel = file_exists(POINTLYBOOKING_PLUGIN_PATH . 'public/build/front.js') ? 'public/build' : 'public';

    $front_asset_path = POINTLYBOOKING_PLUGIN_PATH . $front_dir_rel . '/front.asset.php';
    $front_asset = null;
    if (file_exists($front_asset_path)) {
      $front_asset = require $front_asset_path;
    }

    self::enqueue_public_styles_only();

    self::ensure_react_scripts();

    $front_js = POINTLYBOOKING_PLUGIN_PATH . $front_dir_rel . '/front.js';
    $js_ver = (string) (self::safe_filemtime($front_js) ?: ($front_asset['version'] ?? self::VERSION));
    wp_enqueue_script(
      'pointlybooking-front',
      POINTLYBOOKING_PLUGIN_URL . $front_dir_rel . '/front.js',
      $front_asset['dependencies'] ?? [],
      $js_ver,
      true
    );

    wp_localize_script('pointlybooking-front', 'pointlybooking_FRONT', self::front_localized_data());
  }

  private static function front_localized_data(): array {
    $stripe_pk = self::front_stripe_publishable_key();
    $currency = POINTLYBOOKING_SettingsHelper::get('currency', '');
    if ($currency === '') {
      $currency = get_option('pointlybooking_currency', '');
    }
    if ($currency === '') {
      $currency = 'USD';
    }

    $images_build = '';
    $images_max = 0;
    $images_dir = POINTLYBOOKING_PLUGIN_PATH . 'public/images';
    if (is_dir($images_dir)) {
      try {
        $it = new RecursiveIteratorIterator(
          new RecursiveDirectoryIterator($images_dir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
          if ($file->isFile()) {
            $mt = $file->getMTime();
            if ($mt && $mt > $images_max) $images_max = $mt;
          }
        }
      } catch (\Throwable $e) {
        // ignore
      }
    }
    if ($images_max > 0) $images_build = (string)$images_max;

    $icons_dir_rel = self::public_icons_dir_rel();

    $icons_build = '';
    $icons_max = 0;
    $icon_files = glob(POINTLYBOOKING_PLUGIN_PATH . $icons_dir_rel . '/*.svg');
    if (is_array($icon_files)) {
      foreach ($icon_files as $f) {
        $mt = self::safe_filemtime($f);
        if ($mt && $mt > $icons_max) $icons_max = $mt;
      }
    }
    if ($icons_max > 0) $icons_build = (string)$icons_max;

    return [
      'rest' => esc_url_raw(rest_url()),
      'restUrl' => esc_url_raw(rest_url('pointly-booking/v1')),
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'siteUrl' => site_url('/'),
      'nonce' => wp_create_nonce('wp_rest'),
      'images' => POINTLYBOOKING_PLUGIN_URL . 'public/images/',
      'icons' => POINTLYBOOKING_PLUGIN_URL . $icons_dir_rel . '/',
      'imagesBuild' => $images_build,
      'iconsBuild' => $icons_build,
      'tz' => wp_timezone_string(),
      'stripe_pk' => $stripe_pk,
      'currency' => $currency ?: 'USD',
      'settings' => self::front_settings_payload(),
    ];
  }

  private static function front_stripe_publishable_key(): string {
    $pk = (string)get_option('pointlybooking_stripe_publishable_key', '');
    if ($pk !== '') return $pk;

    $settings = POINTLYBOOKING_SettingsHelper::get_all();
    $mode = $settings['stripe_mode'] ?? 'test';
    if ($mode === 'live') {
      return (string)($settings['stripe_live_publishable_key'] ?? '');
    }
    return (string)($settings['stripe_test_publishable_key'] ?? '');
  }

  private static function front_settings_payload(): array {
    $base = [
      'currency' => (string)POINTLYBOOKING_SettingsHelper::get('currency', 'USD'),
      'currency_position' => (string)POINTLYBOOKING_SettingsHelper::get('currency_position', 'before'),
    ];

    $enabled = POINTLYBOOKING_SettingsHelper::get_with_default('payments_enabled_methods');
    if (!is_array($enabled)) $enabled = [];

    return $base + [
      'payments_enabled' => (int)POINTLYBOOKING_SettingsHelper::get_with_default('payments_enabled'),
      'payments_enabled_methods' => $enabled,
      'payments_default_method' => POINTLYBOOKING_SettingsHelper::get_with_default('payments_default_method'),
      'payments_require_payment_to_confirm' => POINTLYBOOKING_SettingsHelper::get_with_default('payments_require_payment_to_confirm'),
    ];
  }

  public static function render_agents() : void {
    (new POINTLYBOOKING_AdminAgentsController())->index();
  }

  public static function ensure_react_scripts() : void {
    if (!wp_script_is('react', 'registered')) {
      $react_path = POINTLYBOOKING_PLUGIN_PATH . 'public/vendor/react.production.min.js';
      if (file_exists($react_path)) {
        wp_register_script(
          'react',
          POINTLYBOOKING_PLUGIN_URL . 'public/vendor/react.production.min.js',
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
      $react_dom_path = POINTLYBOOKING_PLUGIN_PATH . 'public/vendor/react-dom.production.min.js';
      if (file_exists($react_dom_path)) {
        wp_register_script(
          'react-dom',
          POINTLYBOOKING_PLUGIN_URL . 'public/vendor/react-dom.production.min.js',
          ['react'],
          self::VERSION,
          true
        );
      }
    }

    if (!wp_script_is('react-jsx-runtime', 'registered')) {
      $jsx_runtime_path = POINTLYBOOKING_PLUGIN_PATH . 'public/vendor/react-jsx-runtime.min.js';
      if (file_exists($jsx_runtime_path)) {
        wp_register_script(
          'react-jsx-runtime',
          POINTLYBOOKING_PLUGIN_URL . 'public/vendor/react-jsx-runtime.min.js',
          ['react'],
          self::VERSION,
          true
        );
      } else {
        // WordPress core doesn't always register this handle. Provide an inline-only fallback so our bundles can
        // depend on `react-jsx-runtime` without shipping React binaries in the plugin.
        wp_register_script('react-jsx-runtime', false, ['react'], self::VERSION, true);
        wp_add_inline_script(
          'react-jsx-runtime',
          "if(!window.ReactJSXRuntime&&window.React){window.ReactJSXRuntime={jsx:function(t,p,k){var x=p||{};if(k!==undefined)x.key=k;return window.React.createElement(t,x);},jsxs:function(t,p,k){var x=p||{};if(k!==undefined)x.key=k;return window.React.createElement(t,x);},Fragment:window.React.Fragment};}",
          'before'
        );
      }
    }
  }

  public static function render_agents_edit() : void {
    echo '<div id="bp-admin-app" data-route="agents-edit"></div>';
  }

  public static function render_agents_delete() : void {
    (new POINTLYBOOKING_AdminAgentsController())->delete();
  }

  public static function handle_agents_save() : void {
    (new POINTLYBOOKING_AdminAgentsController())->save();
  }

  public static function render_audit_log() : void {
    // React admin screen (keeps UI consistent + full-width layout)
    pointlybooking_render_admin_app();
  }

  public static function render_tools() : void {
    // React admin screen (keeps UI consistent + full-width layout)
    pointlybooking_render_admin_app();
  }

  public static function handle_tools_email_test() : void {
    (new POINTLYBOOKING_AdminToolsController())->email_test();
  }

  public static function handle_tools_webhook_test() : void {
    (new POINTLYBOOKING_AdminToolsController())->webhook_test();
  }

  public static function handle_tools_generate_demo() : void {
    (new POINTLYBOOKING_AdminToolsController())->generate_demo();
  }

  public static function handle_tools_export_settings() : void {
    (new POINTLYBOOKING_AdminToolsController())->export_settings();
  }

  public static function handle_tools_import_settings() : void {
    (new POINTLYBOOKING_AdminToolsController())->import_settings();
  }

  public static function handle_bookings_export_csv() : void {
    (new POINTLYBOOKING_AdminBookingsController())->export_csv();
  }

  public static function handle_bookings_export_pdf() : void {
    (new POINTLYBOOKING_AdminBookingsController())->export_pdf();
  }

  public static function handle_customers_export_csv() : void {
    if (!current_user_can('pointlybooking_manage_customers') && !current_user_can('pointlybooking_manage_bookings') && !current_user_can('pointlybooking_manage_settings') && !current_user_can('manage_options')) {
      wp_die('No permission');
    }
    check_admin_referer('pointlybooking_admin');

    global $wpdb;
    $table = $wpdb->prefix . 'pointlybooking_customers';
    if (!pointlybooking_is_safe_sql_identifier($table)) {
      wp_die('Invalid table configuration');
    }
    $customers_table = $table;
    $rows = $wpdb->get_results(
      "SELECT * FROM {$customers_table} ORDER BY id DESC",
      ARRAY_A
    ) ?: [];

    $filename = 'bookpoint-customers-' . gmdate('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    $csv_rows = [];
    foreach ($rows as $r) {
      $csv_rows[] = [
        $r['id'] ?? '',
        $r['first_name'] ?? '',
        $r['last_name'] ?? '',
        $r['email'] ?? '',
        $r['phone'] ?? '',
        $r['wp_user_id'] ?? '',
        $r['created_at'] ?? '',
        $r['updated_at'] ?? '',
      ];
    }
    $csv = pointlybooking_build_csv(['id','first_name','last_name','email','phone','wp_user_id','created_at','updated_at'], $csv_rows);
    echo "\xEF\xBB\xBF";
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV output, not HTML.
    echo $csv;
    exit;
  }

  public static function handle_customers_import_csv() : void {
    if (!current_user_can('pointlybooking_manage_customers') && !current_user_can('pointlybooking_manage_bookings') && !current_user_can('pointlybooking_manage_settings') && !current_user_can('manage_options')) {
      wp_die('No permission');
    }
    check_admin_referer('pointlybooking_admin');

    $raw_csv = pointlybooking_get_uploaded_file_contents('csv', ['csv'], 5 * MB_IN_BYTES);
    if ($raw_csv === null) {
      wp_safe_redirect(admin_url('admin.php?page=pointlybooking_customers&import=0'));
      exit;
    }

    $lines = preg_split('/\r\n|\n|\r/', $raw_csv);
    $parsed_rows = [];
    foreach ($lines as $line) {
      if (trim($line) === '') {
        continue;
      }
      $parsed_rows[] = str_getcsv($line);
    }
    if (empty($parsed_rows)) {
      wp_safe_redirect(admin_url('admin.php?page=pointlybooking_customers&import=0'));
      exit;
    }

    $header = array_shift($parsed_rows);
    $map = [];
    if (is_array($header)) {
      foreach ($header as $i => $col) {
        $key = strtolower(trim($col));
        $map[$key] = $i;
      }
    }

    $count = 0;
    global $wpdb;
    $table = $wpdb->prefix . 'pointlybooking_customers';

    foreach ($parsed_rows as $row) {
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
        $existing = POINTLYBOOKING_CustomerModel::find_by_email($email);
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

      POINTLYBOOKING_CustomerModel::create([
        'first_name' => $first_name ?: null,
        'last_name'  => $last_name ?: null,
        'email'      => $email ?: null,
        'phone'      => $phone ?: null,
      ]);
      $count++;
    }

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_customers&import=' . $count));
    exit;
  }

  public static function handle_customer_gdpr_delete() : void {
    (new POINTLYBOOKING_AdminCustomersController())->gdpr_delete();
  }

  public static function shortcode_book_form($atts) : string {
    $atts = shortcode_atts([
      'service_id' => 0,
      'default_date' => '',
      'hide_notes' => 0,
      'require_phone' => 0,
      'compact' => 0,
    ], $atts, 'pointlybooking_booking_form');

    $service_id = absint($atts['service_id']);

    // Ensure public assets are enqueued when shortcode is rendered (widgets, builders, etc).
    self::enqueue_public_assets(true);

    $nonce = wp_create_nonce('pointlybooking_public');

    $options = [
      'default_date' => sanitize_text_field($atts['default_date']),
      'hide_notes' => (int)$atts['hide_notes'] === 1,
      'require_phone' => (int)$atts['require_phone'] === 1,
      'compact' => (int)$atts['compact'] === 1,
      'allow_service_select' => ($service_id <= 0),
    ];

    ob_start();
    $controller = new class extends POINTLYBOOKING_Controller {};
    $controller->render('public/booking_form', [
      'service_id' => $service_id,
      'nonce' => $nonce,
      'options' => $options,
    ]);
    return (string) ob_get_clean();
  }

  public static function shortcode_customer_portal() : string {
    self::enqueue_public_styles_only();

    self::rate_limit_or_block('portal_view', 120, 600);

    $step = sanitize_key((string) filter_input(INPUT_GET, 'step', FILTER_UNSAFE_RAW));
    if ($step === '') {
      $step = 'email';
    }
    $email = sanitize_email((string) filter_input(INPUT_GET, 'email', FILTER_UNSAFE_RAW));
    $session = sanitize_text_field((string) filter_input(INPUT_GET, 's', FILTER_UNSAFE_RAW));
    $view_nonce = sanitize_text_field((string) filter_input(INPUT_GET, 'bpv', FILTER_UNSAFE_RAW));
    if (!in_array($step, ['email', 'verify', 'list'], true)) {
      $step = 'email';
    }
    if (in_array($step, ['verify', 'list'], true)) {
      if ($email === '' || $view_nonce === '' || !wp_verify_nonce($view_nonce, self::portal_view_nonce_action($step, $email))) {
        $step = 'email';
        $email = '';
        $session = '';
      }
    }

    ob_start();

    if ($step === 'verify' && $email) {
      include POINTLYBOOKING_LIB_PATH . 'views/public/portal_verify.php';
    } elseif ($step === 'list' && $email) {
      if ($session !== '' && POINTLYBOOKING_PortalHelper::is_session_valid($email, $session)) {
        include POINTLYBOOKING_LIB_PATH . 'views/public/portal_list.php';
      } else {
        include POINTLYBOOKING_LIB_PATH . 'views/public/portal_verify.php';
      }
    } else {
      include POINTLYBOOKING_LIB_PATH . 'views/public/portal_email.php';
    }

    return (string) ob_get_clean();
  }

  private static function portal_view_nonce_action(string $step, string $email): string {
    return 'pointlybooking_portal_view|' . $step . '|' . md5(strtolower(trim($email)));
  }

  private static function portal_base_url(): string {
    $base = wp_get_referer();
    $base = $base ? wp_validate_redirect($base, '') : '';
    if (!$base) {
      $base = home_url('/');
    }

    // Drop portal args from the base so redirects don't accumulate query strings.
    return remove_query_arg(['step', 'email', 's', 'bpv', 'error'], $base);
  }

  public static function handle_portal_posts() : void {
    $action = sanitize_text_field((string) filter_input(INPUT_POST, 'pointlybooking_portal_action', FILTER_UNSAFE_RAW));
    if ($action === '') return;

    self::rate_limit_or_block('portal_action', 20, 600);

    if ($action === 'send_otp') {
      $nonce = sanitize_text_field((string) filter_input(INPUT_POST, '_wpnonce', FILTER_UNSAFE_RAW));
      if (!wp_verify_nonce($nonce, 'pointlybooking_portal_email')) return;

      $base = self::portal_base_url();
      $email = sanitize_email((string) filter_input(INPUT_POST, 'pointlybooking_portal_email', FILTER_UNSAFE_RAW));
      if ($email !== '' && is_email($email) && POINTLYBOOKING_PortalHelper::send_otp($email)) {
        wp_safe_redirect(add_query_arg([
          'step' => 'verify',
          'email' => $email,
          'bpv' => wp_create_nonce(self::portal_view_nonce_action('verify', $email)),
        ], $base));
        exit;
      }

      wp_safe_redirect(add_query_arg(['error' => 'send_failed'], $base));
      exit;
    }

    if ($action === 'verify_otp') {
      $nonce = sanitize_text_field((string) filter_input(INPUT_POST, '_wpnonce', FILTER_UNSAFE_RAW));
      if (!wp_verify_nonce($nonce, 'pointlybooking_portal_verify')) return;

      $base = self::portal_base_url();
      $email = sanitize_email((string) filter_input(INPUT_POST, 'pointlybooking_portal_email', FILTER_UNSAFE_RAW));
      if ($email === '' || !is_email($email)) {
        wp_safe_redirect(add_query_arg([
          'step' => 'verify',
          'error' => 'bad_code',
        ], $base));
        exit;
      }
      $otp_raw = sanitize_text_field((string) filter_input(INPUT_POST, 'pointlybooking_portal_otp', FILTER_UNSAFE_RAW));
      $otp = preg_replace('/\D+/', '', $otp_raw);
      if (!preg_match('/^\d{6}$/', $otp)) {
        wp_safe_redirect(add_query_arg([
          'step' => 'verify',
          'email' => $email,
          'error' => 'bad_code',
          'bpv' => wp_create_nonce(self::portal_view_nonce_action('verify', $email)),
        ], $base));
        exit;
      }

      $session = ($email !== '') ? POINTLYBOOKING_PortalHelper::verify_otp($email, $otp) : null;
      if ($session) {
        wp_safe_redirect(add_query_arg([
          'step' => 'list',
          'email' => $email,
          's' => $session,
          'bpv' => wp_create_nonce(self::portal_view_nonce_action('list', $email)),
        ], $base));
        exit;
      }

      wp_safe_redirect(add_query_arg([
        'step' => 'verify',
        'email' => $email,
        'error' => 'bad_code',
        'bpv' => wp_create_nonce(self::portal_view_nonce_action('verify', $email)),
      ], $base));
      exit;
    }
  }

  public static function ajax_slots() : void {
    (new POINTLYBOOKING_PublicBookingsController())->slots();
  }

  public static function ajax_submit_booking() : void {
    (new POINTLYBOOKING_PublicBookingsController())->submit();
  }

  public static function register_query_vars($vars) {
    if (!is_array($vars)) {
      $vars = [];
    }
    $vars[] = 'pointlybooking_manage_booking';
    $vars[] = 'key';
    $vars[] = 'pointlybooking_action';
    return $vars;
  }

  public static function maybe_render_public_pages($wp) : void {
    // if not our page, ignore
    $is_manage = get_query_var('pointlybooking_manage_booking');
    if (!$is_manage) return;

    self::rate_limit_or_block('manage_view', 60, 600);

    // handle cancel action first
    $controller = new POINTLYBOOKING_PublicBookingsController();
    $controller->handle_manage_actions();

    // render manage booking page
    status_header(200);
    nocache_headers();

    // Basic wrapper using WP theme content
    add_filter('the_content', function($content) use ($controller) {
      ob_start();
      $controller->render_manage_page();
      $rendered = ob_get_clean();
      return is_string($rendered) ? $rendered : '';
    });

    // Let WP continue; the_content filter will output our page UI
  }

  public static function rate_limit_or_block(string $key, int $limit = 30, int $window_sec = 600) : void {
    $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
    $bucket = 'pointlybooking_rl_' . md5($key . '|' . $ip);

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
      wp_die(esc_html__('Too many requests. Please try again later.', 'pointly-booking'), 429);
    }
  }
}
}

// Backwards compatibility for old integrations expecting pointlybooking_Plugin class.
if (!class_exists('pointlybooking_Plugin', false)) {
  class_alias('POINTLYBOOKING_Core_Plugin', 'pointlybooking_Plugin');
}

// Boot on plugins_loaded in normal flow, with immediate fallback for environments that load plugins late.
if (did_action('plugins_loaded')) {
  POINTLYBOOKING_Core_Plugin::init();
} else {
  add_action('plugins_loaded', ['POINTLYBOOKING_Core_Plugin', 'init'], 20);
}

// Activation/deactivation hooks must be registered at file load time (not delayed inside init()).
register_activation_hook(__FILE__, ['POINTLYBOOKING_Core_Plugin', 'on_activate']);
register_deactivation_hook(__FILE__, ['POINTLYBOOKING_Core_Plugin', 'on_deactivate']);

if (!function_exists('pointlybooking_shortcode_booking_form')) {
  function pointlybooking_shortcode_booking_form($atts = []) {
    $atts = shortcode_atts([
      'label' => __('Book Now', 'pointly-booking'),
    ], $atts);

    if (class_exists('POINTLYBOOKING_Core_Plugin')) {
      POINTLYBOOKING_Core_Plugin::enqueue_public_assets(true);
      if (did_action('wp_footer')) {
        wp_print_styles('pointlybooking-front');
        wp_print_scripts('pointlybooking-front');
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

if (!function_exists('pointlybooking_rest_get_categories')) {
  function pointlybooking_rest_get_categories(\WP_REST_Request $req) {
    $rows = POINTLYBOOKING_CategoryModel::all(['is_active' => 1]);

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

if (!function_exists('pointlybooking_rest_get_services')) {
  function pointlybooking_rest_get_services(\WP_REST_Request $req) {
    $category_id = (int)$req->get_param('category_id');

    global $wpdb;
    $t = $wpdb->prefix . 'pointlybooking_services';
    $map = $wpdb->prefix . 'pointlybooking_service_categories';
    if (!pointlybooking_is_safe_sql_identifier($t) || !pointlybooking_is_safe_sql_identifier($map)) {
      return rest_ensure_response(['status' => 'success', 'data' => []]);
    }
    $services_table = $t;
    $service_categories_table = $map;

    if ($category_id > 0) {
      $rows = $wpdb->get_results(
        $wpdb->prepare(
          "SELECT s.id, s.name, s.price_cents, s.duration_minutes, s.category_id, s.image_id
           FROM {$services_table} s
           INNER JOIN {$service_categories_table} m ON m.service_id = s.id
           WHERE s.is_active = 1 AND m.category_id = %d
           ORDER BY s.id DESC
           LIMIT 500",
          $category_id
        ),
        ARRAY_A
      );
    } else {
      $rows = $wpdb->get_results(
        $wpdb->prepare(
          "SELECT id, name, price_cents, duration_minutes, category_id, image_id FROM {$services_table} WHERE is_active = %d ORDER BY id DESC LIMIT 500",
          1
        ),
        ARRAY_A
      );
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

if (!function_exists('pointlybooking_rest_get_extras')) {
  function pointlybooking_rest_get_extras(\WP_REST_Request $req) {
    $service_id = (int)$req->get_param('service_id');
    if ($service_id <= 0) return rest_ensure_response(['status' => 'success', 'data' => []]);

    global $wpdb;
    $extras_table = $wpdb->prefix . 'pointlybooking_service_extras';
    $map = $wpdb->prefix . 'pointlybooking_extra_services';
    if (!pointlybooking_is_safe_sql_identifier($extras_table) || !pointlybooking_is_safe_sql_identifier($map)) {
      return rest_ensure_response(['status' => 'success', 'data' => []]);
    }
    $extras_sql = $extras_table;
    $extra_services_table = $map;

    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT e.id, e.name, e.price, e.duration_min, e.image_id, e.sort_order
         FROM {$extras_sql} e
         INNER JOIN {$extra_services_table} m ON m.extra_id = e.id
         WHERE e.is_active = 1 AND m.service_id = %d
         ORDER BY e.sort_order ASC, e.id ASC",
        $service_id
      ),
      ARRAY_A
    );

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

if (!function_exists('pointlybooking_rest_get_agents')) {
  function pointlybooking_rest_get_agents(\WP_REST_Request $req) {
    $service_id = (int)$req->get_param('service_id');
    if ($service_id <= 0) return rest_ensure_response(['status'=>'success','data'=>[]]);

    global $wpdb;
    $map = $wpdb->prefix . 'pointlybooking_agent_services';
    $agents = $wpdb->prefix . 'pointlybooking_agents';
    if (!pointlybooking_is_safe_sql_identifier($map) || !pointlybooking_is_safe_sql_identifier($agents)) {
      return rest_ensure_response(['status'=>'success','data'=>[]]);
    }
    $agents_table = $agents;
    $agent_services_table = $map;

    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT a.id, a.first_name, a.last_name, a.image_id
         FROM {$agents_table} a
         INNER JOIN {$agent_services_table} m ON m.agent_id = a.id
         WHERE m.service_id = %d AND a.is_active = 1
         ORDER BY a.id DESC",
        $service_id
      ),
      ARRAY_A
    );

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

if (!function_exists('pointlybooking_prepare_query_with_identifiers')) {
  function pointlybooking_prepare_query_with_identifiers(string $query, $identifiers = [], array $args = []): string {
    global $wpdb;

    if (!is_array($identifiers)) {
      $identifiers = [$identifiers];
    }
    $identifiers = array_values(array_map('strval', $identifiers));
    $identifier_placeholder = '%' . 'i';

    foreach ($identifiers as $identifier) {
      $safe_identifier = preg_replace('/[^A-Za-z0-9_]/', '', $identifier);
      $pos = strpos($query, $identifier_placeholder);
      if ($pos === false) {
        continue;
      }

      $query = substr_replace($query, '`' . $safe_identifier . '`', $pos, strlen($identifier_placeholder));
    }

    if (empty($args)) {
      return (string)$query;
    }

    return (string) call_user_func_array([$wpdb, 'prepare'], array_merge([$query], $args));
  }
}

if (!function_exists('pointlybooking_is_safe_sql_identifier')) {
  function pointlybooking_is_safe_sql_identifier(string $identifier): bool {
    return preg_match('/^[A-Za-z0-9_]+$/', $identifier) === 1;
  }
}

if (!function_exists('pointlybooking_quote_sql_identifier')) {
  function pointlybooking_quote_sql_identifier(string $identifier): string {
    $safe_identifier = preg_replace('/[^A-Za-z0-9_]/', '', $identifier);
    return '`' . $safe_identifier . '`';
  }
}

if (!function_exists('pointlybooking_is_valid_ymd')) {
  function pointlybooking_is_valid_ymd(string $value): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
    [$year, $month, $day] = array_map('intval', explode('-', $value));
    return checkdate($month, $day, $year);
  }
}

if (!function_exists('pointlybooking_is_valid_time_hm_or_hms')) {
  function pointlybooking_is_valid_time_hm_or_hms(string $value): bool {
    return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value) === 1;
  }
}

if (!function_exists('pointlybooking_rest_create_booking')) {
  function pointlybooking_rest_create_booking(\WP_REST_Request $req) {
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

    if (!pointlybooking_is_valid_ymd($date)) {
      return rest_ensure_response(['status'=>'error','message'=>'Invalid date']);
    }
    if (!pointlybooking_is_valid_time_hm_or_hms($time)) {
      return rest_ensure_response(['status'=>'error','message'=>'Invalid time']);
    }

    global $wpdb;

    $t_services = $wpdb->prefix . 'pointlybooking_services';
    if (!pointlybooking_is_safe_sql_identifier($t_services)) {
      return rest_ensure_response(['status'=>'error','message'=>'Invalid service configuration']);
    }
    $services_table = $t_services;

    $svc = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT id, price_cents, duration_minutes, category_id FROM {$services_table} WHERE id = %d AND is_active = 1",
        $service_id
      ),
      ARRAY_A
    );

    if (!$svc) return rest_ensure_response(['status'=>'error','message'=>'Invalid service']);

    $service_price = ((int)($svc['price_cents'] ?? 0)) / 100;
    $duration_min = (int)($svc['duration_minutes'] ?? 0);
    if ($duration_min <= 0) $duration_min = 60;

    if ($agent_id > 0) {
      $map = $wpdb->prefix . 'pointlybooking_agent_services';
      if (!pointlybooking_is_safe_sql_identifier($map)) {
        return rest_ensure_response(['status'=>'error','message'=>'Invalid service configuration']);
      }
      $agent_services_table = $map;
      $ok = (int) $wpdb->get_var(
        $wpdb->prepare(
          "SELECT COUNT(*) FROM {$agent_services_table} WHERE agent_id=%d AND service_id=%d",
          $agent_id,
          $service_id
        )
      );
      if ($ok <= 0) return rest_ensure_response(['status'=>'error','message'=>'Agent not allowed for this service']);
    }

    $t_extras = $wpdb->prefix . 'pointlybooking_service_extras';
    if (!pointlybooking_is_safe_sql_identifier($t_extras)) {
      return rest_ensure_response(['status'=>'error','message'=>'Invalid service configuration']);
    }
    $extras_table = $t_extras;
    $valid_extras = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT id, price FROM {$extras_table} WHERE service_id=%d AND is_active=1",
        $service_id
      ),
      ARRAY_A
    );

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

    $chk1 = pointlybooking_validate_required_fields('customer', $customer_fields);
    if (!$chk1['ok']) return rest_ensure_response(['status'=>'error','message'=>$chk1['message']]);

    $chk2 = pointlybooking_validate_required_fields('booking', $booking_fields);
    if (!$chk2['ok']) return rest_ensure_response(['status'=>'error','message'=>$chk2['message']]);

    $promo_res = pointlybooking_apply_promo_to_subtotal($promo_code, $subtotal, true);
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

    $customer_id = POINTLYBOOKING_CustomerModel::find_or_create_by_email([
      'first_name' => $first_name,
      'last_name' => $last_name,
      'email' => $customer_email,
      'phone' => '',
    ]);

    $wpdb->update(
      $wpdb->prefix . 'pointlybooking_customers',
      ['custom_fields_json' => wp_json_encode($customer_fields)],
      ['id' => $customer_id],
      ['%s'],
      ['%d']
    );

    $manage_key = bin2hex(random_bytes(32));
    $now = current_time('mysql');

    $bookings = $wpdb->prefix . 'pointlybooking_bookings';
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

if (!function_exists('pointlybooking_rest_validate_promo')) {
  function pointlybooking_rest_validate_promo(\WP_REST_Request $req) {
    $code = strtoupper(sanitize_text_field($req->get_param('code') ?? ''));
    $subtotal = (float)($req->get_param('subtotal') ?? 0);

    $res = pointlybooking_apply_promo_to_subtotal($code, $subtotal, false);
    return rest_ensure_response($res);
  }
}

if (!function_exists('pointlybooking_render_admin_app_catalog')) {
  function pointlybooking_render_admin_app_catalog() {
    echo '<div id="bp-admin-app" data-route="catalog"></div>';
  }
}

if (!function_exists('pointlybooking_render_admin_app')) {
  function pointlybooking_render_admin_app() {
    echo '<div id="bp-admin-app"></div>';
  }
}


if (!function_exists('pointlybooking_rest_get_form_fields')) {
  function pointlybooking_rest_get_form_fields(\WP_REST_Request $req) {
    $scope = sanitize_text_field($req->get_param('scope') ?? 'form');
    if (!in_array($scope, ['form','customer','booking'], true)) $scope = 'form';

    $rows = POINTLYBOOKING_FormFieldModel::active_fields($scope);

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

if (!function_exists('pointlybooking_validate_required_fields')) {
  function pointlybooking_validate_required_fields(string $scope, array $submitted): array {
    $fields = POINTLYBOOKING_FormFieldModel::active_fields($scope);
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

if (!function_exists('pointlybooking_apply_promo_to_subtotal')) {
  function pointlybooking_apply_promo_to_subtotal(string $code, float $subtotal, bool $increment_use): array {
    $code = strtoupper(trim($code));
    if ($code === '') {
      return ['status'=>'success','valid'=>false,'discount'=>0,'total'=>$subtotal,'message'=>''];
    }

    $promo = POINTLYBOOKING_PromoCodeModel::find_by_code($code);
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
      POINTLYBOOKING_PromoCodeModel::increment_use((int)$promo['id']);
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
