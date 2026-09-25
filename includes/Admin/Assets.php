<?php
/**
 * Admin app assets (loaded on plugin screens only).
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Admin;

use PointlyBooking\Installer;
use PointlyBooking\Rest\Controller;
use PointlyBooking\Settings\Design;
use PointlyBooking\Settings\Settings;
use PointlyBooking\Support\Assets as Bundles;
use PointlyBooking\Support\Dates;
use PointlyBooking\Support\Money;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues build/admin and passes boot data to it.
 */
final class Assets {

	const HANDLE = 'pointlybooking-admin';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Enqueues the app on plugin screens.
	 *
	 * @param string $hook Page hook.
	 * @return void
	 */
	public static function enqueue( $hook ) {
		if ( ! in_array( $hook, Menu::hooks(), true ) ) {
			return;
		}
		if ( ! Bundles::register( self::HANDLE, 'admin' ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( self::HANDLE );
		wp_enqueue_style( self::HANDLE );
		wp_add_inline_script( self::HANDLE, 'window.pointlybooking_ADMIN = ' . wp_json_encode( self::data() ) . ';', 'before' );
	}

	/**
	 * Boot data (window.pointlybooking_ADMIN, 2.x keys kept).
	 *
	 * @return array
	 */
	public static function data() {
		$user  = wp_get_current_user();
		$caps  = array();
		$pages = array();
		foreach ( Installer::CAPS as $cap ) {
			$caps[ str_replace( 'pointlybooking_', '', $cap ) ] = current_user_can( $cap );
		}
		foreach ( Menu::pages() as $slug => $page ) {
			$pages[ $page[0] ] = array(
				'slug'  => $slug,
				'url'   => Menu::url( $slug ),
				'label' => $page[2],
				'can'   => current_user_can( $page[1] ),
			);
		}
		$onboarding = (array) get_option( 'pointlybooking_onboarding', array() );
		$design     = Design::get();

		return array(
			'version'           => POINTLYBOOKING_VERSION,
			'primary'           => (string) ( $design['appearance']['primaryColor'] ?? '#4f46e5' ),
			'restUrl'           => esc_url_raw( rest_url( Controller::NS . '/' ) ),
			'restRoot'          => esc_url_raw( rest_url() ),
			'nonce'             => wp_create_nonce( 'wp_rest' ),
			'adminNonce'        => wp_create_nonce( 'pointlybooking_admin' ),
			'adminPostUrl'      => esc_url_raw( admin_url( 'admin-post.php' ) ),
			'adminUrl'          => esc_url_raw( admin_url() ),
			'pluginUrl'         => esc_url_raw( POINTLYBOOKING_PLUGIN_URL ),
			'siteUrl'           => esc_url_raw( home_url( '/' ) ),
			'route'             => Menu::current_route(),
			'page'              => Menu::current_route(),
			'pages'             => $pages,
			'timezone'          => wp_timezone_string(),
			'currency'          => Money::currency(),
			'currency_symbol'   => Money::symbol(),
			'currency_position' => (string) Settings::get( 'currency_position', 'before' ),
			'dateFormat'        => (string) get_option( 'date_format', 'F j, Y' ),
			'timeFormat'        => (string) get_option( 'time_format', 'H:i' ),
			'weekStartsOn'      => (int) get_option( 'start_of_week', 1 ),
			'locale'            => str_replace( '_', '-', determine_locale() ),
			'today'             => Dates::today(),
			'isRtl'             => is_rtl(),
			'isAdmin'           => current_user_can( 'manage_options' ),
			'caps'              => $caps,
			'user'              => array(
				'id'    => (int) $user->ID,
				'name'  => (string) $user->display_name,
				'email' => (string) $user->user_email,
			),
			'onboarding'        => array(
				'show' => empty( $onboarding['completed'] ) && empty( $onboarding['dismissed'] ) && current_user_can( 'pointlybooking_manage_settings' ),
			),
			'debug'             => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'wooActive'         => class_exists( 'WooCommerce' ),
			'shortcode'         => '[pointlybooking_booking_form]',
			'portalShortcode'   => '[pointlybooking_customer_portal]',
		);
	}
}
