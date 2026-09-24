<?php
/**
 * 2.x admin-ajax.php endpoints (pointlybooking_slots, pointlybooking_submit_booking).
 *
 * The 3.0 front end uses the REST API; these remain for cached pages and custom
 * integrations built against 2.x.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Frontend;

use PointlyBooking\Services\Availability\AvailabilityService;
use PointlyBooking\Services\Booking\BookingService;
use PointlyBooking\Services\Notifications\Variables;
use PointlyBooking\Support\RateLimiter;
use PointlyBooking\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * Legacy AJAX handlers (nonce "pointlybooking_public").
 */
final class LegacyAjax {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register() {
		foreach ( array( 'slots', 'submit_booking' ) as $action ) {
			add_action( 'wp_ajax_pointlybooking_' . $action, array( __CLASS__, $action ) );
			add_action( 'wp_ajax_nopriv_pointlybooking_' . $action, array( __CLASS__, $action ) );
		}
	}

	/**
	 * Verifies the public nonce.
	 *
	 * @return void
	 */
	private static function verify() {
		if ( ! check_ajax_referer( 'pointlybooking_public', 'nonce', false ) && ! check_ajax_referer( 'pointlybooking_public', '_wpnonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session expired. Please reload the page.', 'pointly-booking' ) ), 403 );
		}
	}

	/**
	 * Free times of a day.
	 *
	 * @return void
	 */
	public static function slots() {
		self::verify();
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified in verify().
		$service = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;
		$agent   = isset( $_POST['agent_id'] ) ? absint( $_POST['agent_id'] ) : 0;
		$date    = isset( $_POST['date'] ) ? Sanitize::date( sanitize_text_field( wp_unslash( $_POST['date'] ) ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( ! $service || '' === $date ) {
			wp_send_json_error( array( 'message' => __( 'Please choose a service and a date.', 'pointly-booking' ) ), 400 );
		}
		$day   = AvailabilityService::day(
			array(
				'service_id' => $service,
				'agent_id'   => $agent,
				'date'       => $date,
			)
		);
		$slots = array();
		foreach ( $day['slots'] as $slot ) {
			$slots[] = array(
				'start_time' => $slot['start'],
				'end_time'   => $slot['end'],
				'label'      => $slot['start'],
			);
		}
		wp_send_json_success( array( 'slots' => $slots ) );
	}

	/**
	 * Creates a booking.
	 *
	 * @return void
	 */
	public static function submit_booking() {
		self::verify();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in verify(). Values are sanitised by BookingService::create().
		$post = wp_unslash( $_POST );
		if ( '' !== trim( (string) ( $post['pointlybooking_hp'] ?? '' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Your booking could not be submitted.', 'pointly-booking' ) ), 400 );
		}
		if ( ! RateLimiter::hit( 'wizard_create', 10, 600 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please wait a moment and try again.', 'pointly-booking' ) ), 429 );
		}
		if ( ! empty( $post['customer_name'] ) && empty( $post['first_name'] ) ) {
			$parts              = preg_split( '/\s+/', trim( (string) $post['customer_name'] ), 2 );
			$post['first_name'] = $parts[0];
			$post['last_name']  = $parts[1] ?? '';
		}
		$booking = BookingService::create(
			array(
				'service_id'     => absint( $post['service_id'] ?? 0 ),
				'agent_id'       => absint( $post['agent_id'] ?? 0 ),
				'date'           => (string) ( $post['date'] ?? '' ),
				'start'          => (string) ( $post['start_time'] ?? ( $post['time'] ?? '' ) ),
				'first_name'     => (string) ( $post['first_name'] ?? '' ),
				'last_name'      => (string) ( $post['last_name'] ?? '' ),
				'email'          => (string) ( $post['email'] ?? ( $post['customer_email'] ?? '' ) ),
				'phone'          => (string) ( $post['phone'] ?? ( $post['customer_phone'] ?? '' ) ),
				'customer_notes' => (string) ( $post['notes'] ?? '' ),
			),
			array( 'source' => 'legacy' )
		);
		if ( is_wp_error( $booking ) ) {
			$data = $booking->get_error_data();
			wp_send_json_error( array( 'message' => $booking->get_error_message() ), is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400 );
		}
		wp_send_json_success(
			array(
				'booking_id' => (int) $booking['id'],
				'manage_url' => Variables::manage_url( (string) $booking['manage_key'] ),
				'message'    => __( 'Thank you! Your booking was received.', 'pointly-booking' ),
			)
		);
	}
}
