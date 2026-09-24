<?php
/**
 * Admin menu: one React app under a single top-level menu.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the menu, maps 2.x page slugs to app screens and renders the mount point.
 */
final class Menu {

	const TOP = 'pointlybooking_dashboard';

	/**
	 * Page hook suffixes of the plugin screens.
	 *
	 * @var string[]
	 */
	private static $hooks = array();

	/**
	 * Visible screens: slug => [route, capability].
	 *
	 * @return array<string,array{0:string,1:string,2:string}>
	 */
	public static function pages() {
		return array(
			self::TOP                      => array( 'dashboard', 'pointlybooking_manage_bookings', __( 'Dashboard', 'pointly-booking' ) ),
			'pointlybooking_calendar'      => array( 'calendar', 'pointlybooking_manage_bookings', __( 'Calendar', 'pointly-booking' ) ),
			'pointlybooking_bookings'      => array( 'bookings', 'pointlybooking_manage_bookings', __( 'Bookings', 'pointly-booking' ) ),
			'pointlybooking_customers'     => array( 'customers', 'pointlybooking_manage_customers', __( 'Customers', 'pointly-booking' ) ),
			'pointlybooking_services'      => array( 'services', 'pointlybooking_manage_services', __( 'Services', 'pointly-booking' ) ),
			'pointlybooking_agents'        => array( 'staff', 'pointlybooking_manage_agents', __( 'Staff', 'pointly-booking' ) ),
			'pointlybooking_locations'     => array( 'locations', 'pointlybooking_manage_settings', __( 'Locations', 'pointly-booking' ) ),
			'pointlybooking_notifications' => array( 'notifications', 'pointlybooking_manage_settings', __( 'Notifications', 'pointly-booking' ) ),
			'pointlybooking_design_form'   => array( 'booking-form', 'pointlybooking_manage_settings', __( 'Booking form', 'pointly-booking' ) ),
			'pointlybooking_settings'      => array( 'settings', 'pointlybooking_manage_settings', __( 'Settings', 'pointly-booking' ) ),
			'pointlybooking_how_to_use'    => array( 'help', 'pointlybooking_manage_bookings', __( 'Help', 'pointly-booking' ) ),
		);
	}

	/**
	 * 2.x page slugs that no longer have their own menu entry: slug => [page, extra query args].
	 *
	 * @return array<string,array{0:string,1:array}>
	 */
	public static function legacy_pages() {
		return array(
			'pointlybooking_bookings_edit'            => array( 'pointlybooking_bookings', array( 'view' => 'edit' ) ),
			'pointlybooking_booking_confirm'          => array( 'pointlybooking_bookings', array( 'view' => 'edit' ) ),
			'pointlybooking_booking_cancel'           => array( 'pointlybooking_bookings', array( 'view' => 'edit' ) ),
			'pointlybooking_bookings_delete'          => array( 'pointlybooking_bookings', array() ),
			'pointlybooking_schedule'                 => array( 'pointlybooking_settings', array( 'tab' => 'schedule' ) ),
			'pointlybooking_holidays'                 => array( 'pointlybooking_settings', array( 'tab' => 'holidays' ) ),
			'pointlybooking_services_edit'            => array( 'pointlybooking_services', array( 'view' => 'edit' ) ),
			'pointlybooking_services_delete'          => array( 'pointlybooking_services', array() ),
			'pointlybooking_categories'               => array( 'pointlybooking_services', array( 'tab' => 'categories' ) ),
			'pointlybooking_categories_edit'          => array(
				'pointlybooking_services',
				array(
					'tab'  => 'categories',
					'view' => 'edit',
				),
			),
			'pointlybooking_categories_delete'        => array( 'pointlybooking_services', array( 'tab' => 'categories' ) ),
			'pointlybooking_extras'                   => array( 'pointlybooking_services', array( 'tab' => 'extras' ) ),
			'pointlybooking_extras_edit'              => array(
				'pointlybooking_services',
				array(
					'tab'  => 'extras',
					'view' => 'edit',
				),
			),
			'pointlybooking_extras_delete'            => array( 'pointlybooking_services', array( 'tab' => 'extras' ) ),
			'pointlybooking_locations_edit'           => array( 'pointlybooking_locations', array( 'view' => 'edit' ) ),
			'pointlybooking_location_categories_edit' => array(
				'pointlybooking_locations',
				array(
					'tab'  => 'categories',
					'view' => 'edit',
				),
			),
			'pointlybooking_promo_codes'              => array( 'pointlybooking_settings', array( 'tab' => 'promo_codes' ) ),
			'pointlybooking_promo_codes_edit'         => array( 'pointlybooking_settings', array( 'tab' => 'promo_codes' ) ),
			'pointlybooking_promo_codes_delete'       => array( 'pointlybooking_settings', array( 'tab' => 'promo_codes' ) ),
			'bp-form-fields'                          => array( 'pointlybooking_settings', array( 'tab' => 'form_fields' ) ),
			'pointlybooking_form_fields'              => array( 'pointlybooking_settings', array( 'tab' => 'form_fields' ) ),
			'pointlybooking_form_fields_edit'         => array( 'pointlybooking_settings', array( 'tab' => 'form_fields' ) ),
			'pointlybooking_form_fields_delete'       => array( 'pointlybooking_settings', array( 'tab' => 'form_fields' ) ),
			'pointlybooking_customers_edit'           => array( 'pointlybooking_customers', array( 'view' => 'edit' ) ),
			'pointlybooking_customers_view'           => array( 'pointlybooking_customers', array( 'view' => 'detail' ) ),
			'pointlybooking_customers_delete'         => array( 'pointlybooking_customers', array() ),
			'pointlybooking_audit'                    => array( 'pointlybooking_settings', array( 'tab' => 'audit_log' ) ),
			'pointlybooking_audit_log'                => array( 'pointlybooking_settings', array( 'tab' => 'audit_log' ) ),
			'pointlybooking_tools'                    => array( 'pointlybooking_settings', array( 'tab' => 'tools' ) ),
			'pointlybooking_agents_edit'              => array( 'pointlybooking_agents', array( 'view' => 'edit' ) ),
			'pointlybooking_agents_delete'            => array( 'pointlybooking_agents', array() ),
		);
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_page_access_denied', array( __CLASS__, 'redirect_legacy' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'admin_notices', array( __CLASS__, 'menu_debug' ) );
	}

	/**
	 * Whether the current request is one of our screens.
	 *
	 * @return bool
	 */
	public static function is_plugin_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && in_array( $screen->id, self::$hooks, true );
	}

	/**
	 * Page hook suffixes.
	 *
	 * @return string[]
	 */
	public static function hooks() {
		return self::$hooks;
	}

	/**
	 * Registers the top-level menu, its screens and the Tools shortcut.
	 *
	 * @return void
	 */
	public static function menu() {
		$pages = self::pages();
		$top   = $pages[ self::TOP ];

		self::$hooks[] = add_menu_page(
			__( 'BookPoint', 'pointly-booking' ),
			__( 'BookPoint', 'pointly-booking' ),
			$top[1],
			self::TOP,
			array( __CLASS__, 'render' ),
			'dashicons-calendar-alt',
			56
		);

		foreach ( $pages as $slug => $page ) {
			self::$hooks[] = add_submenu_page( self::TOP, $page[2] . ' ‹ ' . __( 'BookPoint', 'pointly-booking' ), $page[2], $page[1], $slug, array( __CLASS__, 'render' ) );
		}

		// Shortcut under Tools (2.x "BookPoint Tools").
		$tools = add_management_page(
			__( 'BookPoint Tools', 'pointly-booking' ),
			__( 'BookPoint Tools', 'pointly-booking' ),
			'pointlybooking_manage_tools',
			'pointlybooking_tools_shortcut',
			'__return_null'
		);
		if ( $tools ) {
			add_action(
				'load-' . $tools,
				static function () {
					wp_safe_redirect( self::url( 'pointlybooking_settings', array( 'tab' => 'tools' ) ) );
					exit;
				}
			);
		}
	}

	/**
	 * Admin URL of a screen.
	 *
	 * @param string $page Page slug.
	 * @param array  $args Extra query args.
	 * @return string
	 */
	public static function url( $page = self::TOP, array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Old bookmarks and deep links land on the matching 3.0 screen.
	 *
	 * @return void
	 */
	public static function redirect_legacy() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only redirect of a page slug.
		$slug = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$map  = self::legacy_pages();
		if ( ! isset( $map[ $slug ] ) ) {
			return;
		}
		list( $page, $args ) = $map[ $slug ];
		foreach ( array( 'id', 'new', 'tab', 'status' ) as $key ) {
			if ( isset( $_GET[ $key ] ) && ! isset( $args[ $key ] ) ) {
				$args[ $key ] = sanitize_key( wp_unslash( $_GET[ $key ] ) );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( isset( $args['view'] ) && 'edit' === $args['view'] && empty( $args['id'] ) ) {
			$args['view'] = 'new';
		}
		unset( $args['new'] );
		wp_safe_redirect( self::url( $page, $args ) );
		exit;
	}

	/**
	 * Body class used by the admin stylesheet.
	 *
	 * @param string $classes Classes.
	 * @return string
	 */
	public static function body_class( $classes ) {
		return self::is_plugin_screen() ? $classes . ' pbk-admin-page' : $classes;
	}

	/**
	 * Current app route.
	 *
	 * @return string
	 */
	public static function current_route() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen selection.
		$slug  = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : self::TOP;
		$pages = self::pages();
		return isset( $pages[ $slug ] ) ? $pages[ $slug ][0] : 'dashboard';
	}

	/**
	 * Mount point for the admin app.
	 *
	 * @return void
	 */
	public static function render() {
		printf(
			'<div class="wrap pbk-admin-wrap"><h1 class="screen-reader-text">%1$s</h1><div id="pbk-admin" class="pbk-admin" data-route="%2$s"><div class="pbk-admin-boot" role="status"><span class="pbk-admin-boot__spinner" aria-hidden="true"></span><span class="screen-reader-text">%3$s</span></div></div><noscript><div class="notice notice-error"><p>%4$s</p></div></noscript></div>',
			esc_html__( 'BookPoint', 'pointly-booking' ),
			esc_attr( self::current_route() ),
			esc_html__( 'Loading…', 'pointly-booking' ),
			esc_html__( 'BookPoint needs JavaScript. Please enable it in your browser.', 'pointly-booking' )
		);
	}

	/**
	 * ?pointlybooking_menu_debug=1 — lists the registered screens (administrators only).
	 *
	 * @return void
	 */
	public static function menu_debug() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only diagnostic flag.
		if ( empty( $_GET['pointlybooking_menu_debug'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$lines = array();
		foreach ( self::pages() as $slug => $page ) {
			$lines[] = sprintf( '%s → %s (%s: %s)', $slug, $page[0], $page[1], current_user_can( $page[1] ) ? 'yes' : 'no' );
		}
		printf(
			'<div class="notice notice-info"><p><strong>%s</strong></p><pre>%s</pre></div>',
			esc_html__( 'BookPoint menu diagnostics', 'pointly-booking' ),
			esc_html( implode( "\n", $lines ) . "\nhooks: " . implode( ', ', self::$hooks ) )
		);
	}
}
