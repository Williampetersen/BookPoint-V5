<?php
/**
 * Request helpers.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Safe accessors for request-level data.
 */
final class Request {

	/**
	 * Client IP address (REMOTE_ADDR only; proxies can be trusted via filter).
	 *
	 * @return string
	 */
	public static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$ip = '0.0.0.0';
		}

		/**
		 * Filters the detected client IP (use this to trust a reverse proxy header).
		 *
		 * @param string $ip Detected IP address.
		 */
		return (string) apply_filters( 'pointlybooking_client_ip', $ip );
	}

	/**
	 * Reads a GET query value as sanitized text (no nonce context; callers verify).
	 *
	 * @param string $key Query key.
	 * @return string
	 */
	public static function query( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing value; sanitized here and never used to change state.
		if ( ! isset( $_GET[ $key ] ) || is_array( $_GET[ $key ] ) ) {
			return '';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing value; sanitized here and never used to change state.
		return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
	}
}
