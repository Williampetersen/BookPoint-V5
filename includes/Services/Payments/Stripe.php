<?php
/**
 * Stripe integration (Checkout Sessions, PaymentIntents and webhooks).
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Services\Payments;

use PointlyBooking\Repositories\BookingRepository;
use PointlyBooking\Services\Booking\BookingService;
use PointlyBooking\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Talks to the Stripe API with amounts taken from the stored booking only.
 */
final class Stripe {

	const API = 'https://api.stripe.com/v1/';

	/**
	 * Currencies Stripe charges without a decimal part.
	 *
	 * @var string[]
	 */
	const ZERO_DECIMAL = array( 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' );

	/**
	 * Current mode (test|live).
	 *
	 * @return string
	 */
	public static function mode() {
		return 'live' === Settings::get( 'stripe_mode', 'test' ) ? 'live' : 'test';
	}

	/**
	 * Checkout experience: inline card form (elements) or hosted page (checkout).
	 *
	 * @return string
	 */
	public static function flow() {
		return 'checkout' === Settings::get( 'payments_stripe_flow', 'elements' ) ? 'checkout' : 'elements';
	}

	/**
	 * Secret key for the current mode.
	 *
	 * @return string
	 */
	public static function secret_key() {
		$legacy = (string) get_option( 'pointlybooking_stripe_secret_key', '' );
		if ( '' !== $legacy ) {
			return $legacy;
		}
		return trim( (string) Settings::get( 'live' === self::mode() ? 'stripe_live_secret_key' : 'stripe_test_secret_key', '' ) );
	}

	/**
	 * Publishable key for the current mode.
	 *
	 * @return string
	 */
	public static function publishable_key() {
		$legacy = (string) get_option( 'pointlybooking_stripe_publishable_key', '' );
		if ( '' !== $legacy ) {
			return $legacy;
		}
		return trim( (string) Settings::get( 'live' === self::mode() ? 'stripe_live_publishable_key' : 'stripe_test_publishable_key', '' ) );
	}

	/**
	 * Amount in the smallest currency unit.
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency ISO code.
	 * @return int
	 */
	public static function minor_units( $amount, $currency ) {
		return in_array( strtoupper( $currency ), self::ZERO_DECIMAL, true ) ? (int) round( $amount ) : (int) round( $amount * 100 );
	}

	/**
	 * Calls the Stripe API.
	 *
	 * @param string $method   HTTP method.
	 * @param string $path     Path below /v1/.
	 * @param array  $body     Form body.
	 * @param string $idem_key Optional idempotency key.
	 * @return array|\WP_Error Decoded body.
	 */
	private static function request( $method, $path, array $body = array(), $idem_key = '' ) {
		$secret = self::secret_key();
		if ( '' === $secret ) {
			return new \WP_Error( 'stripe_not_configured', __( 'Card payments are not configured yet.', 'pointly-booking' ), array( 'status' => 400 ) );
		}
		$headers = array(
			'Authorization' => 'Bearer ' . $secret,
			'Content-Type'  => 'application/x-www-form-urlencoded',
		);
		if ( '' !== $idem_key ) {
			$headers['Idempotency-Key'] = $idem_key;
		}
		$args     = array(
			'method'  => $method,
			'timeout' => 25,
			'headers' => $headers,
		);
		$response = 'GET' === $method
			? wp_remote_get( self::API . $path, $args )
			: wp_remote_post( self::API . $path, array_merge( $args, array( 'body' => http_build_query( $body, '', '&' ) ) ) );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'stripe_http', __( 'Could not reach the payment provider. Please try again.', 'pointly-booking' ), array( 'status' => 502 ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$json = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $json ) ) {
			$message = is_array( $json ) && isset( $json['error']['message'] ) ? (string) $json['error']['message'] : __( 'The payment provider returned an error.', 'pointly-booking' );
			return new \WP_Error( 'stripe_error', $message, array( 'status' => 502 ) );
		}
		return $json;
	}

	/**
	 * Amount and currency owed for a booking (always from the database).
	 *
	 * @param array $booking Booking row.
	 * @return array{amount:float,currency:string}
	 */
	private static function due( array $booking ) {
		$amount   = null !== $booking['payment_amount'] ? (float) $booking['payment_amount'] : (float) $booking['total_price'];
		$currency = strtoupper( (string) ( $booking['payment_currency'] ? $booking['payment_currency'] : $booking['currency'] ) );
		if ( ! preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			$currency = 'USD';
		}
		return array(
			'amount'   => $amount,
			'currency' => $currency,
		);
	}

	/**
	 * Creates a PaymentIntent for the inline card form.
	 *
	 * @param array $booking Booking row.
	 * @return array|\WP_Error {client_secret, payment_intent_id}
	 */
	public static function create_intent( array $booking ) {
		$due = self::due( $booking );
		if ( $due['amount'] <= 0 ) {
			return new \WP_Error( 'bad_amount', __( 'Nothing to pay for this booking.', 'pointly-booking' ), array( 'status' => 400 ) );
		}
		$json = self::request(
			'POST',
			'payment_intents',
			array(
				'amount'                             => self::minor_units( $due['amount'], $due['currency'] ),
				'currency'                           => strtolower( $due['currency'] ),
				'automatic_payment_methods[enabled]' => 'true',
				'description'                        => self::description( $booking ),
				'metadata[booking_id]'               => (string) $booking['id'],
				'metadata[site]'                     => home_url( '/' ),
			),
			'pbk-pi-' . $booking['id'] . '-' . substr( $booking['manage_key'], 0, 12 )
		);
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		BookingRepository::update(
			$booking['id'],
			array(
				'payment_method'       => 'stripe',
				'payment_provider_ref' => (string) $json['id'],
			)
		);
		return array(
			'client_secret'     => (string) $json['client_secret'],
			'payment_intent_id' => (string) $json['id'],
		);
	}

	/**
	 * Confirms a PaymentIntent server-side and marks the booking paid.
	 *
	 * @param array  $booking   Booking row.
	 * @param string $intent_id PaymentIntent ID sent by the browser.
	 * @return array|\WP_Error {paid:bool,status:string}
	 */
	public static function confirm_intent( array $booking, $intent_id ) {
		$intent_id = (string) $intent_id;
		$stored    = (string) $booking['payment_provider_ref'];
		if ( '' === $stored || ! hash_equals( $stored, $intent_id ) ) {
			return new \WP_Error( 'payment_mismatch', __( 'This payment does not belong to the booking.', 'pointly-booking' ), array( 'status' => 400 ) );
		}
		$json = self::request( 'GET', 'payment_intents/' . rawurlencode( $intent_id ) );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		return self::apply_intent( $booking, $json );
	}

	/**
	 * Marks paid when an intent succeeded for the full amount.
	 *
	 * @param array $booking Booking row.
	 * @param array $intent  Intent object.
	 * @return array
	 */
	private static function apply_intent( array $booking, array $intent ) {
		$due    = self::due( $booking );
		$status = (string) ( $intent['status'] ?? '' );
		$paid   = 'succeeded' === $status
			&& (int) ( $intent['amount_received'] ?? $intent['amount'] ?? 0 ) >= self::minor_units( $due['amount'], $due['currency'] )
			&& strtolower( (string) ( $intent['currency'] ?? '' ) ) === strtolower( $due['currency'] );
		if ( $paid ) {
			BookingService::mark_paid( (int) $booking['id'], 'stripe', (string) $intent['id'] );
		}
		return array(
			'paid'   => $paid,
			'status' => $status,
		);
	}

	/**
	 * Creates a hosted Checkout Session.
	 *
	 * @param array  $booking    Booking row.
	 * @param string $return_url Page to come back to (the wizard page).
	 * @return array|\WP_Error {url, session_id}
	 */
	public static function create_checkout( array $booking, $return_url ) {
		$due = self::due( $booking );
		if ( $due['amount'] <= 0 ) {
			return new \WP_Error( 'bad_amount', __( 'Nothing to pay for this booking.', 'pointly-booking' ), array( 'status' => 400 ) );
		}
		$success = (string) Settings::get( 'stripe_success_url', '' );
		$cancel  = (string) Settings::get( 'stripe_cancel_url', '' );
		$base    = $return_url ? $return_url : home_url( '/' );
		$args    = array(
			'booking_id' => (int) $booking['id'],
			'key'        => $booking['manage_key'],
		);

		$json = self::request(
			'POST',
			'checkout/sessions',
			array(
				'mode'                                   => 'payment',
				'success_url'                            => add_query_arg( array_merge( $args, array( 'pointlybooking_payment' => 'stripe_success' ) ), $success ? $success : $base ),
				'cancel_url'                             => add_query_arg( array_merge( $args, array( 'pointlybooking_payment' => 'stripe_cancel' ) ), $cancel ? $cancel : $base ),
				'client_reference_id'                    => (string) $booking['id'],
				'metadata[booking_id]'                   => (string) $booking['id'],
				'payment_intent_data[metadata][booking_id]' => (string) $booking['id'],
				'line_items[0][quantity]'                => 1,
				'line_items[0][price_data][currency]'    => strtolower( $due['currency'] ),
				'line_items[0][price_data][unit_amount]' => self::minor_units( $due['amount'], $due['currency'] ),
				'line_items[0][price_data][product_data][name]' => self::description( $booking ),
			),
			'pbk-cs-' . $booking['id'] . '-' . substr( $booking['manage_key'], 0, 12 ) . '-' . md5( $base )
		);
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		BookingRepository::update(
			$booking['id'],
			array(
				'payment_method'       => 'stripe',
				'payment_provider_ref' => (string) $json['id'],
			)
		);
		return array(
			'url'        => (string) ( $json['url'] ?? '' ),
			'session_id' => (string) $json['id'],
		);
	}

	/**
	 * Re-checks a Checkout Session after the customer returns (webhooks may be delayed or absent).
	 *
	 * @param array $booking Booking row.
	 * @return array{paid:bool,status:string}
	 */
	public static function sync_checkout( array $booking ) {
		$ref = (string) $booking['payment_provider_ref'];
		if ( 0 !== strpos( $ref, 'cs_' ) ) {
			return array(
				'paid'   => 'paid' === $booking['payment_status'],
				'status' => (string) $booking['payment_status'],
			);
		}
		$json = self::request( 'GET', 'checkout/sessions/' . rawurlencode( $ref ) );
		if ( is_wp_error( $json ) ) {
			return array(
				'paid'   => false,
				'status' => 'unknown',
			);
		}
		$due  = self::due( $booking );
		$paid = 'paid' === ( $json['payment_status'] ?? '' ) && (int) ( $json['amount_total'] ?? 0 ) >= self::minor_units( $due['amount'], $due['currency'] );
		if ( $paid ) {
			BookingService::mark_paid( (int) $booking['id'], 'stripe', $ref );
		}
		return array(
			'paid'   => $paid,
			'status' => (string) ( $json['payment_status'] ?? '' ),
		);
	}

	/**
	 * Verifies a webhook signature (Stripe-Signature header).
	 *
	 * @param string $payload Raw body.
	 * @param string $header  Signature header.
	 * @param string $secret  Endpoint secret.
	 * @return bool
	 */
	public static function verify_signature( $payload, $header, $secret ) {
		if ( '' === $secret || '' === $header ) {
			return false;
		}
		$timestamp  = 0;
		$signatures = array();
		foreach ( explode( ',', $header ) as $part ) {
			$pair = explode( '=', trim( $part ), 2 );
			if ( 2 !== count( $pair ) ) {
				continue;
			}
			if ( 't' === $pair[0] ) {
				$timestamp = (int) $pair[1];
			} elseif ( 'v1' === $pair[0] ) {
				$signatures[] = $pair[1];
			}
		}
		if ( $timestamp <= 0 || ! $signatures || abs( time() - $timestamp ) > 300 ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );
		foreach ( $signatures as $signature ) {
			if ( hash_equals( $expected, $signature ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Handles a verified webhook event.
	 *
	 * @param array $event Event object.
	 * @return void
	 */
	public static function handle_event( array $event ) {
		$type   = (string) ( $event['type'] ?? '' );
		$object = is_array( $event['data']['object'] ?? null ) ? $event['data']['object'] : array();

		if ( 'checkout.session.completed' === $type || 'checkout.session.async_payment_succeeded' === $type ) {
			$booking = BookingRepository::find( (int) ( $object['client_reference_id'] ?? ( $object['metadata']['booking_id'] ?? 0 ) ) );
			if ( ! $booking || 'paid' !== ( $object['payment_status'] ?? '' ) ) {
				return;
			}
			$due = self::due( $booking );
			if ( (int) ( $object['amount_total'] ?? 0 ) >= self::minor_units( $due['amount'], $due['currency'] ) ) {
				BookingService::mark_paid( (int) $booking['id'], 'stripe', (string) ( $object['id'] ?? '' ) );
			}
			return;
		}

		if ( 'payment_intent.succeeded' === $type ) {
			$booking = BookingRepository::find( (int) ( $object['metadata']['booking_id'] ?? 0 ) );
			if ( $booking ) {
				self::apply_intent( $booking, $object );
			}
		}
	}

	/**
	 * Line item / intent description.
	 *
	 * @param array $booking Booking row.
	 * @return string
	 */
	private static function description( array $booking ) {
		$service = \PointlyBooking\Repositories\ServiceRepository::find( (int) $booking['service_id'] );
		$name    = $service ? $service['name'] : __( 'Booking', 'pointly-booking' );
		/* translators: 1: service name, 2: booking number */
		return sprintf( __( '%1$s (booking #%2$d)', 'pointly-booking' ), $name, (int) $booking['id'] );
	}
}
