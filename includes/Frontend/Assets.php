<?php
/**
 * Front-end assets: loaded only where a booking form, the portal or the manage page is shown.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Frontend;

use PointlyBooking\Rest\Controller;
use PointlyBooking\Services\Payments\PaymentMethods;
use PointlyBooking\Settings\Design;
use PointlyBooking\Settings\Settings;
use PointlyBooking\Support\Assets as Bundles;
use PointlyBooking\Support\Money;

defined( 'ABSPATH' ) || exit;

/**
 * Registers build/front (booking wizard) and build/manage (manage page + customer portal).
 */
final class Assets {

	const FRONT  = 'pointlybooking-front';
	const MANAGE = 'pointlybooking-manage';

	/**
	 * Whether the boot data was attached.
	 *
	 * @var array<string,bool>
	 */
	private static $localized = array();

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_bundles' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ) );
	}

	/**
	 * Registers both bundles (enqueued on demand).
	 *
	 * @return void
	 */
	public static function register_bundles() {
		Bundles::register( self::FRONT, 'front' );
		Bundles::register( self::MANAGE, 'manage' );
	}

	/**
	 * Enqueues in the <head> when the current post contains a booking form or the portal,
	 * or when the visitor returns from a payment provider.
	 *
	 * @return void
	 */
	public static function maybe_enqueue() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only flag set by payment return URLs.
		if ( isset( $_GET['pointlybooking_payment'] ) ) {
			self::enqueue_front();
			return;
		}
		if ( ! is_singular() ) {
			return;
		}
		$post = get_post();
		if ( ! $post ) {
			return;
		}
		$content = (string) $post->post_content;
		if ( has_shortcode( $content, 'pointlybooking_booking_form' ) || has_block( 'bookpoint/booking-form', $post ) ) {
			self::enqueue_front();
		}
		if ( has_shortcode( $content, 'pointlybooking_customer_portal' ) ) {
			self::enqueue_manage();
		}
	}

	/**
	 * Booking wizard bundle (safe to call late, e.g. from a shortcode in a widget).
	 *
	 * @return void
	 */
	public static function enqueue_front() {
		if ( ! wp_script_is( self::FRONT, 'registered' ) ) {
			self::register_bundles();
		}
		if ( ! wp_script_is( self::FRONT, 'registered' ) ) {
			return;
		}
		wp_enqueue_script( self::FRONT );
		wp_enqueue_style( self::FRONT );
		self::overrides();
		if ( empty( self::$localized[ self::FRONT ] ) ) {
			wp_add_inline_script( self::FRONT, 'window.pointlybooking_FRONT = ' . wp_json_encode( self::front_data() ) . ';', 'before' );
			self::$localized[ self::FRONT ] = true;
		}
	}

	/**
	 * Manage page / portal bundle.
	 *
	 * @return void
	 */
	public static function enqueue_manage() {
		if ( ! wp_script_is( self::MANAGE, 'registered' ) ) {
			self::register_bundles();
		}
		if ( ! wp_script_is( self::MANAGE, 'registered' ) ) {
			return;
		}
		wp_enqueue_script( self::MANAGE );
		wp_enqueue_style( self::MANAGE );
		self::overrides();
		if ( empty( self::$localized[ self::MANAGE ] ) ) {
			wp_add_inline_script( self::MANAGE, 'window.pointlybooking_MANAGE = ' . wp_json_encode( self::manage_data() ) . ';', 'before' );
			self::$localized[ self::MANAGE ] = true;
		}
	}

	/**
	 * Site-level CSS overrides: public/front-overrides.css (2.x) or
	 * <theme>/bookpoint/front-overrides.css (survives plugin updates).
	 *
	 * @return void
	 */
	private static function overrides() {
		if ( wp_style_is( 'pointlybooking-overrides', 'enqueued' ) ) {
			return;
		}
		$candidates = array(
			get_stylesheet_directory() . '/bookpoint/front-overrides.css' => get_stylesheet_directory_uri() . '/bookpoint/front-overrides.css',
			POINTLYBOOKING_PLUGIN_DIR . 'public/front-overrides.css'      => POINTLYBOOKING_PLUGIN_URL . 'public/front-overrides.css',
		);
		foreach ( $candidates as $path => $url ) {
			if ( is_readable( $path ) ) {
				wp_enqueue_style( 'pointlybooking-overrides', $url, array( self::FRONT ), (string) filemtime( $path ) );
				return;
			}
		}
	}

	/**
	 * Shared REST boot values.
	 *
	 * @return array
	 */
	private static function rest() {
		return array(
			'restUrl' => esc_url_raw( rest_url( Controller::NS . '/' ) ),
			// Only logged-in visitors need a REST nonce; guests are anonymous and cache-friendly.
			'nonce'   => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
			'siteUrl' => esc_url_raw( home_url( '/' ) ),
			'locale'  => str_replace( '_', '-', determine_locale() ),
			'isRtl'   => is_rtl(),
			'tz'      => wp_timezone_string(),
		);
	}

	/**
	 * Boot data for window.pointlybooking_FRONT (2.x keys kept).
	 *
	 * @return array
	 */
	public static function front_data() {
		$design     = Design::get();
		$appearance = $design['appearance'] ?? array();
		$dark       = $appearance['darkModeDefault'] ?? false;
		$user       = array();
		if ( is_user_logged_in() ) {
			$current = wp_get_current_user();
			$user    = array(
				'first_name' => (string) $current->first_name,
				'last_name'  => (string) $current->last_name,
				'email'      => (string) $current->user_email,
			);
		}
		return array_merge(
			self::rest(),
			array(
				'businessName'   => (string) Settings::get( 'business_name', get_bloginfo( 'name' ) ),
				'appearance'     => array(
					'dark'   => null === $dark ? 'auto' : ( $dark ? 'dark' : 'light' ),
					'radius' => (string) ( $appearance['borderStyle'] ?? 'rounded' ),
					'font'   => (string) ( $appearance['font'] ?? 'system' ),
				),
				'confirmOnClose' => ! isset( $design['behavior']['confirmOnClose'] ) || (bool) $design['behavior']['confirmOnClose'],
				'user'           => $user,
				'rest'           => esc_url_raw( rest_url( Controller::NS ) ),
				'ajaxUrl'        => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
				'stripe_pk'      => PaymentMethods::public_config()['stripe']['publishableKey'],
				'currency'       => Money::currency(),
				'primary'        => (string) ( $design['appearance']['primaryColor'] ?? '#4f46e5' ),
				'settings'       => array(
					'currency'          => Money::currency(),
					'currency_symbol'   => Money::symbol(),
					'currency_position' => (string) Settings::get( 'currency_position', 'before' ),
				),
			)
		);
	}

	/**
	 * Boot data for window.pointlybooking_MANAGE.
	 *
	 * @return array
	 */
	public static function manage_data() {
		$front = self::front_data();
		return array_merge(
			self::rest(),
			array(
				'primary'      => $front['primary'],
				'appearance'   => $front['appearance'],
				'businessName' => $front['businessName'],
				'settings'     => array(
					'currency'        => Money::currency(),
					'currency_symbol' => Money::symbol(),
					'currency_pos'    => (string) Settings::get( 'currency_position', 'before' ),
					'timezone'        => wp_timezone_string(),
					'timezone_label'  => \PointlyBooking\Rest\Front\WizardController::timezone_label(),
					'today'           => current_time( 'Y-m-d' ),
					'week_starts'     => (int) get_option( 'start_of_week', 1 ),
					'time_format'     => (string) get_option( 'time_format', 'H:i' ),
					'locale'          => str_replace( '_', '-', determine_locale() ),
				),
				// 2.x key.
				'currency'     => array(
					'code'     => Money::currency(),
					'symbol'   => Money::symbol(),
					'position' => (string) Settings::get( 'currency_position', 'before' ),
				),
			)
		);
	}
}
