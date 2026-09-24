<?php
/**
 * Customer "manage booking" page at /?pointlybooking_manage_booking=1&key={manage key}.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the manage app inside the active theme (classic or block theme).
 */
final class ManagePage {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ), 1 );
	}

	/**
	 * Public query vars (2.x names).
	 *
	 * @param string[] $vars Vars.
	 * @return string[]
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'pointlybooking_manage_booking';
		$vars[] = 'pointlybooking_action';
		return $vars;
	}

	/**
	 * Manage key from the URL (40 or 64 hex characters), or ''.
	 *
	 * @return string
	 */
	public static function key() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The key itself is the capability; it is validated by the REST API.
		$key = isset( $_GET['key'] ) ? strtolower( sanitize_text_field( wp_unslash( $_GET['key'] ) ) ) : '';
		return preg_match( '/^[a-f0-9]{40}([a-f0-9]{24})?$/', $key ) ? $key : '';
	}

	/**
	 * Takes over the request when the manage query var is present.
	 *
	 * @return void
	 */
	public static function maybe_render() {
		if ( ! get_query_var( 'pointlybooking_manage_booking' ) ) {
			return;
		}

		// Private page: never index it and never leak the key through the Referer header.
		add_filter( 'wp_robots', 'wp_robots_no_robots' );
		header( 'Referrer-Policy: same-origin' );
		nocache_headers();
		status_header( 200 );

		Assets::enqueue_manage();
		add_filter(
			'document_title_parts',
			static function ( $parts ) {
				$parts['title'] = __( 'Your booking', 'pointly-booking' );
				return $parts;
			}
		);

		$key = self::key();
		include POINTLYBOOKING_PLUGIN_DIR . 'templates/front/manage-page.php';
		exit;
	}

	/**
	 * Mount point markup.
	 *
	 * @param string $key Manage key.
	 * @return string
	 */
	public static function mount( $key ) {
		return sprintf(
			'<div class="pbk-root pbk-manage" data-pbk-widget="manage" data-pbk-key="%1$s"><div class="pbk-booking__placeholder" aria-busy="true"><span class="pbk-booking__spinner" aria-hidden="true"></span><span class="pbk-sr-only">%2$s</span></div><noscript><p class="pbk-noscript">%3$s</p></noscript></div>',
			esc_attr( $key ),
			esc_html__( 'Loading your booking…', 'pointly-booking' ),
			esc_html__( 'Please enable JavaScript to manage your booking.', 'pointly-booking' )
		);
	}
}
