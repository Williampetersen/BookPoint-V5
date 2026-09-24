<?php
/**
 * PayPal Orders v2 integration.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Services\Payments;

use PointlyBooking\Repositories\BookingRepository;
use PointlyBooking\Services\Booking\BookingService;
use PointlyBooking\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and captures PayPal orders for bookings.
 */
final class PayPal {

	/**
	 * API base for the current mode.
	 *
	 * @return string
	 */
	private static function base() {
		return 'live' === Settings::get( 'paypal_mode', 'test' ) ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
	}

	/**
	 * Client ID.
	 *
	 * @return string
	 */
	public static function client_id() {
		return trim( (string) Settings::get( 'paypal_client_id', '' ) );
	}

	/**
	 * Client secret.
	 *
	 * @return string
	 */
	public static function secret() {
		return trim( (string) Settings::get( 'paypal_secret', '' ) );
	}

	/**
	 * OAuth access token (cached for its lifetime).
	 *
	 * @return string|\WP_Error
	 */
	private static function token() {
		$cache_key = 'pointlybooking_pp_token_' . md5( self::base() . self::client_id() );
		$cached    = get_transient( $cache_key );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}
		if ( '' === self::client_id() || '' === self::secret() ) {
			return new \WP_Error( 'paypal_not_configured', __( 'PayPal is not configured yet.', 'pointly-booking' ), array( 'status' => 400 ) );
		}
		$response = wp_remote_post(
			self::base() . '/v1/oauth2/token',
			array(
				'timeout' => 25,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( self::client_id() . ':' . self::secret() ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic auth header required by PayPal.
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => 'grant_type=client_credentials',
			)
		);
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'paypal_http', __( 'Could not reach PayPal. Please try again.', 'pointly-booking' ), array( 'status' => 502 ) );
		}
		$json  = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$token = is_array( $json ) ? (string) ( $json['access_token'] ?? '' ) : '';
		if ( '' === $token ) {
			return new \WP_Error( 'paypal_auth', __( 'PayPal rejected the credentials.', 'pointly-booking' ), array( 'status' => 502 ) );
		}
		set_transient( $cache_key, $token, max( 60, (int) ( $json['expires_in'] ?? 3000 ) - 120 ) );
		return $token;
	}

	/**
	 * Calls the Orders API.
	 *
	 * @param string     $path Path.
	 * @param array|null $body JSON body (null = GET).
	 * @return array|\WP_Error
	 */
	private static function request( $path, $body = null ) {
		$token = self::token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$args = array(
			'timeout' => 25,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
		);
		if ( null === $body ) {
			$response = wp_remote_get( self::base() . $path, $args );
		} else {
			$args['body'] = wp_json_encode( $body );
			$response     = wp_remote_post( self::base() . $path, $args );
		}
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'paypal_http', __( 'Could not reach PayPal. Please try again.', 'pointly-booking' ), array( 'status' => 502 ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$json = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $json ) ) {
			$message = is_array( $json ) && ! empty( $json['message'] ) ? (string) $json['message'] : __( 'PayPal returned an error.', 'pointly-booking' );
			return new \WP_Error( 'paypal_error', $message, array( 'status' => 502 ) );
		}
		return $json;
	}

	/**
	 * Creates an order and returns the approval URL.
	 *
	 * @param array  $booking    Booking row.
	 * @param string $return_url Page to come back to.
	 * @return array|\WP_Error {url, order_id}
	 */
	public static function create_order( array $booking, $return_url ) {
		$amount   = null !== $booking['payment_amount'] ? (float) $booking['payment_amount'] : (float) $booking['total_price'];
		$currency = strtoupper( (string) ( $booking['payment_currency'] ? $booking['payment_currency'] : $booking['currency'] ) );
		if ( $amount <= 0 ) {
			return new \WP_Error( 'bad_amount', __( 'Nothing to pay for this booking.', 'pointly-booking' ), array( 'status' => 400 ) );
		}
		$base   = (string) Settings::get( 'paypal_return_url', '' );
		$cancel = (string) Settings::get( 'paypal_cancel_url', '' );
		$page   = $return_url ? $return_url : home_url( '/' );
		$args   = array(
			'booking_id' => (int) $booking['id'],
			'key'        => $booking['manage_key'],
		);

		$json = self::request(
			'/v2/checkout/orders',
			array(
				'intent'              => 'CAPTURE',
				'purchase_units'      => array(
					array(
						'reference_id' => (string) $booking['id'],
						'custom_id'    => (string) $booking['id'],
						'amount'       => array(
							'currency_code' => $currency,
							'value'         => number_format( $amount, in_array( $currency, array( 'JPY', 'HUF', 'TWD' ), true ) ? 0 : 2, '.', '' ),
						),
					),
				),
				'application_context' => array(
					'return_url'  => add_query_arg( array_merge( $args, array( 'pointlybooking_payment' => 'paypal_return' ) ), $base ? $base : $page ),
					'cancel_url'  => add_query_arg( array_merge( $args, array( 'pointlybooking_payment' => 'paypal_cancel' ) ), $cancel ? $cancel : $page ),
					'brand_name'  => wp_specialchars_decode( (string) Settings::get( 'business_name', get_bloginfo( 'name' ) ), ENT_QUOTES ),
					'user_action' => 'PAY_NOW',
				),
			)
		);
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$approve = '';
		foreach ( (array) ( $json['links'] ?? array() ) as $link ) {
			if ( in_array( $link['rel'] ?? '', array( 'approve', 'payer-action' ), true ) ) {
				$approve = (string) $link['href'];
				break;
			}
		}
		BookingRepository::update(
			$booking['id'],
			array(
				'payment_method'       => 'paypal',
				'payment_provider_ref' => (string) $json['id'],
			)
		);
		return array(
			'url'      => $approve,
			'order_id' => (string) $json['id'],
		);
	}

	/**
	 * Captures an approved order and verifies booking, amount and currency.
	 *
	 * @param array  $booking  Booking row.
	 * @param string $order_id Order ID from the return URL (token).
	 * @return array|\WP_Error {paid:bool}
	 */
	public static function capture( array $booking, $order_id ) {
		$order_id = (string) $order_id;
		$stored   = (string) $booking['payment_provider_ref'];
		if ( '' === $stored || ! hash_equals( $stored, $order_id ) ) {
			return new \WP_Error( 'payment_mismatch', __( 'This payment does not belong to the booking.', 'pointly-booking' ), array( 'status' => 400 ) );
		}
		if ( 'paid' === $booking['payment_status'] ) {
			return array( 'paid' => true );
		}
		$json = self::request( '/v2/checkout/orders/' . rawurlencode( $order_id ) . '/capture', new \stdClass() );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$capture  = $json['purchase_units'][0]['payments']['captures'][0] ?? array();
		$custom   = (string) ( $capture['custom_id'] ?? ( $json['purchase_units'][0]['custom_id'] ?? '' ) );
		$amount   = isset( $capture['amount']['value'] ) ? (float) $capture['amount']['value'] : -1.0;
		$currency = strtoupper( (string) ( $capture['amount']['currency_code'] ?? '' ) );
		$expected = null !== $booking['payment_amount'] ? (float) $booking['payment_amount'] : (float) $booking['total_price'];
		$want_cur = strtoupper( (string) ( $booking['payment_currency'] ? $booking['payment_currency'] : $booking['currency'] ) );

		$paid = 'COMPLETED' === ( $json['status'] ?? '' ) && (string) $booking['id'] === $custom && abs( $amount - $expected ) < 0.01 && $currency === $want_cur;
		if ( $paid ) {
			BookingService::mark_paid( (int) $booking['id'], 'paypal', $order_id );
		}
		return array( 'paid' => $paid );
	}
}
