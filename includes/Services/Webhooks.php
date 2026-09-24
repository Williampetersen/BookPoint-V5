<?php
/**
 * Outgoing webhooks for booking events.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Services;

use PointlyBooking\Repositories\BookingRepository;
use PointlyBooking\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Posts signed JSON to admin-configured URLs.
 */
final class Webhooks {

	/**
	 * Hooks webhooks to lifecycle actions.
	 *
	 * @return void
	 */
	public static function register() {
		add_action(
			'pointlybooking_booking_created',
			static function ( $id ) {
				self::fire_for_booking( 'booking_created', (int) $id );
			},
			20
		);
		add_action(
			'pointlybooking_booking_status_changed',
			static function ( $id, $new_status, $old_status ) {
				self::fire_for_booking( 'booking_status_changed', (int) $id, (string) $old_status );
				if ( 'cancelled' === $new_status ) {
					self::fire_for_booking( 'booking_cancelled', (int) $id, (string) $old_status );
				}
			},
			20,
			3
		);
		add_action(
			'pointlybooking_booking_rescheduled',
			static function ( $id ) {
				self::fire_for_booking( 'booking_updated', (int) $id );
			},
			20
		);
		add_action(
			'pointlybooking_booking_updated',
			static function ( $id ) {
				self::fire_for_booking( 'booking_updated', (int) $id );
			},
			20
		);
	}

	/**
	 * Builds the payload for a booking and sends it.
	 *
	 * @param string $event      Event.
	 * @param int    $booking_id Booking ID.
	 * @param string $old_status Previous status.
	 * @return void
	 */
	public static function fire_for_booking( $event, $booking_id, $old_status = '' ) {
		if ( ! Settings::bool( 'webhooks_enabled' ) || '' === (string) Settings::get( 'webhooks_url_' . $event, '' ) ) {
			return;
		}
		$b = BookingRepository::find( $booking_id );
		if ( ! $b ) {
			return;
		}
		self::fire(
			$event,
			array(
				'booking_id'     => (int) $b['id'],
				'status'         => (string) $b['status'],
				'old_status'     => $old_status,
				'service_id'     => (int) $b['service_id'],
				'customer_id'    => (int) $b['customer_id'],
				'agent_id'       => (int) $b['agent_id'],
				'location_id'    => (int) $b['location_id'],
				'start_datetime' => (string) $b['start_datetime'],
				'end_datetime'   => (string) $b['end_datetime'],
				'total_price'    => (float) $b['total_price'],
				'currency'       => (string) $b['currency'],
				'payment_method' => (string) $b['payment_method'],
				'payment_status' => (string) $b['payment_status'],
			)
		);
	}

	/**
	 * Sends a webhook.
	 *
	 * @param string $event    Event name.
	 * @param array  $data     Payload data.
	 * @param bool   $blocking Wait for the response (tests).
	 * @return array|\WP_Error|null Response (blocking) or null.
	 */
	public static function fire( $event, array $data, $blocking = false ) {
		if ( ! Settings::bool( 'webhooks_enabled' ) && ! $blocking ) {
			return null;
		}
		$url = (string) Settings::get( 'webhooks_url_' . $event, '' );
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return $blocking ? new \WP_Error( 'no_url', __( 'No webhook URL is set for this event.', 'pointly-booking' ) ) : null;
		}
		$body   = wp_json_encode(
			array(
				'event'     => $event,
				'site'      => home_url(),
				'timestamp' => time(),
				'data'      => $data,
			)
		);
		$secret = (string) Settings::get( 'webhooks_secret', '' );

		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout'  => $blocking ? 10 : 3,
				'blocking' => $blocking,
				'headers'  => array(
					'Content-Type'   => 'application/json',
					'X-BP-Event'     => $event,
					'X-BP-Signature' => '' !== $secret ? hash_hmac( 'sha256', (string) $body, $secret ) : '',
				),
				'body'     => $body,
			)
		);
		return $blocking ? $response : null;
	}
}
