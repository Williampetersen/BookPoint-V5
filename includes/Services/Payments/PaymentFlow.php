<?php
/**
 * Starts, resumes and finalises online payments for a booking.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Services\Payments;

use PointlyBooking\Integrations\WooCommerce;
use PointlyBooking\Repositories\BookingRepository;
use PointlyBooking\Services\Booking\BookingService;
use PointlyBooking\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Provider-neutral payment steps used by the wizard, the manage page and the 2.x routes.
 */
final class PaymentFlow {

	/**
	 * What the customer has to do next for a booking.
	 *
	 * @param array  $booking    Booking row.
	 * @param string $return_url Page the customer should come back to.
	 * @return array|\WP_Error {type: none|stripe_elements|redirect, …}
	 */
	public static function start( array $booking, $return_url = '' ) {
		$method = (string) $booking['payment_method'];
		if ( 'paid' === $booking['payment_status'] || ! in_array( $method, PaymentMethods::ONLINE, true ) || (float) $booking['total_price'] <= 0 ) {
			return array( 'type' => 'none' );
		}
		if ( in_array( $booking['status'], BookingRepository::FREEING_STATUSES, true ) ) {
			return new \WP_Error( 'booking_closed', __( 'This booking was cancelled, so it can no longer be paid.', 'pointly-booking' ), array( 'status' => 409 ) );
		}
		if ( ! PaymentMethods::is_configured( $method ) ) {
			return new \WP_Error( 'payment_unavailable', __( 'Online payment is not available right now. Please contact us to complete your booking.', 'pointly-booking' ), array( 'status' => 400 ) );
		}

		$return_url = self::safe_return_url( $return_url );

		switch ( $method ) {
			case 'stripe':
				if ( 'checkout' === Stripe::flow() ) {
					$session = Stripe::create_checkout( $booking, $return_url );
					if ( is_wp_error( $session ) ) {
						return $session;
					}
					return array(
						'type' => 'redirect',
						'url'  => $session['url'],
					);
				}
				$intent = Stripe::create_intent( $booking );
				if ( is_wp_error( $intent ) ) {
					return $intent;
				}
				return array(
					'type'              => 'stripe_elements',
					'client_secret'     => $intent['client_secret'],
					'payment_intent_id' => $intent['payment_intent_id'],
					'publishable_key'   => Stripe::publishable_key(),
				);

			case 'paypal':
				$order = PayPal::create_order( $booking, $return_url );
				if ( is_wp_error( $order ) ) {
					return $order;
				}
				return array(
					'type' => 'redirect',
					'url'  => $order['url'],
				);

			case 'woocommerce':
				$checkout = WooCommerce::start( $booking );
				if ( is_wp_error( $checkout ) ) {
					return $checkout;
				}
				return array(
					'type' => 'redirect',
					'url'  => $checkout['url'],
				);
		}
		return array( 'type' => 'none' );
	}

	/**
	 * Only same-site pages may be used as payment return targets.
	 *
	 * @param string $url Requested URL.
	 * @return string
	 */
	public static function safe_return_url( $url ) {
		$url = esc_url_raw( (string) $url );
		if ( '' === $url ) {
			return home_url( '/' );
		}
		$url = remove_query_arg( array( 'pointlybooking_payment', 'booking_id', 'key', 'token', 'PayerID', 'session_id' ), $url );
		return wp_validate_redirect( $url, home_url( '/' ) );
	}

	/**
	 * Re-checks the provider for the latest payment state (used when the customer returns).
	 *
	 * @param array $booking Booking row.
	 * @return array Fresh booking row.
	 */
	public static function refresh( array $booking ) {
		if ( 'paid' !== $booking['payment_status'] && 'stripe' === $booking['payment_method'] && ! empty( $booking['payment_provider_ref'] ) && 0 === strpos( (string) $booking['payment_provider_ref'], 'cs_' ) ) {
			Stripe::sync_checkout( $booking );
			$booking = BookingRepository::find( $booking['id'] );
		}
		return $booking;
	}

	/**
	 * Customer abandoned the online payment: frees the time slot.
	 *
	 * @param array $booking Booking row.
	 * @return array|\WP_Error
	 */
	public static function cancel( array $booking ) {
		if ( 'paid' === $booking['payment_status'] ) {
			return new \WP_Error( 'already_paid', __( 'This booking is already paid.', 'pointly-booking' ), array( 'status' => 409 ) );
		}
		if ( 'pending_payment' !== $booking['status'] ) {
			return $booking;
		}
		BookingRepository::update(
			$booking['id'],
			array(
				'payment_status' => 'failed',
				'updated_at'     => Dates::now_mysql(),
			)
		);
		return BookingService::set_status(
			$booking['id'],
			'cancelled',
			array(
				'source' => 'payment',
				'actor'  => 'customer',
			)
		);
	}
}
