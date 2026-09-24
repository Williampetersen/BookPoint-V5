<?php
/**
 * Audit trail of important actions.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Services;

use PointlyBooking\Repositories\AuditRepository;
use PointlyBooking\Repositories\BookingRepository;
use PointlyBooking\Support\Dates;
use PointlyBooking\Support\Request;

defined( 'ABSPATH' ) || exit;

/**
 * Writes audit entries for booking lifecycle events and admin actions.
 */
final class Audit {

	/**
	 * Hooks audit logging to lifecycle actions.
	 *
	 * @return void
	 */
	public static function register() {
		add_action(
			'pointlybooking_booking_created',
			static function ( $id, $context ) {
				$b = BookingRepository::find( $id );
				self::log(
					'booking_created',
					array(
						'actor_type'  => $context['actor'] ?? 'customer',
						'booking_id'  => $id,
						'customer_id' => $b ? (int) $b['customer_id'] : 0,
						'meta'        => array(
							'source'         => $context['source'] ?? '',
							'service_id'     => $b ? (int) $b['service_id'] : 0,
							'agent_id'       => $b ? (int) $b['agent_id'] : 0,
							'payment_method' => $context['payment_method'] ?? '',
						),
					)
				);
			},
			30,
			2
		);
		add_action(
			'pointlybooking_booking_status_changed',
			static function ( $id, $new_status, $old_status, $context ) {
				$event = 'booking_status_changed';
				if ( 'customer' === ( $context['actor'] ?? '' ) && 'cancelled' === $new_status ) {
					$event = 'customer_cancelled';
				}
				$b = BookingRepository::find( $id );
				self::log(
					$event,
					array(
						'actor_type'  => $context['actor'] ?? 'admin',
						'booking_id'  => $id,
						'customer_id' => $b ? (int) $b['customer_id'] : 0,
						'meta'        => array(
							'old' => $old_status,
							'new' => $new_status,
						),
					)
				);
			},
			30,
			4
		);
		add_action(
			'pointlybooking_booking_rescheduled',
			static function ( $id, $old, $context ) {
				$b = BookingRepository::find( $id );
				self::log(
					'customer' === ( $context['actor'] ?? '' ) ? 'customer_rescheduled' : 'booking_rescheduled',
					array(
						'actor_type'  => $context['actor'] ?? 'admin',
						'booking_id'  => $id,
						'customer_id' => $b ? (int) $b['customer_id'] : 0,
						'meta'        => array(
							'old_start' => $old['start_datetime'] ?? '',
							'new_start' => $b ? $b['start_datetime'] : '',
						),
					)
				);
			},
			30,
			3
		);
		add_action(
			'pointlybooking_booking_paid',
			static function ( $id, $provider, $reference ) {
				self::log(
					'payment_received',
					array(
						'actor_type' => 'system',
						'booking_id' => $id,
						'meta'       => array(
							'provider'  => $provider,
							'reference' => $reference,
						),
					)
				);
			},
			30,
			3
		);
		add_action(
			'pointlybooking_booking_deleted',
			static function ( $id, $booking ) {
				self::log(
					'booking_deleted',
					array(
						'actor_type'  => 'admin',
						'booking_id'  => $id,
						'customer_id' => (int) ( $booking['customer_id'] ?? 0 ),
					)
				);
			},
			30,
			2
		);
	}

	/**
	 * Writes one entry.
	 *
	 * @param string $event Event name.
	 * @param array  $data  actor_type, booking_id, customer_id, meta.
	 * @return void
	 */
	public static function log( $event, array $data = array() ) {
		$actor = $data['actor_type'] ?? ( is_user_logged_in() ? 'admin' : 'customer' );
		AuditRepository::add(
			array(
				'event'            => substr( sanitize_key( $event ), 0, 60 ),
				'actor_type'       => in_array( $actor, array( 'admin', 'customer', 'system' ), true ) ? $actor : 'system',
				'actor_wp_user_id' => get_current_user_id() ? get_current_user_id() : null,
				'actor_ip'         => Request::client_ip(),
				'booking_id'       => ! empty( $data['booking_id'] ) ? (int) $data['booking_id'] : null,
				'customer_id'      => ! empty( $data['customer_id'] ) ? (int) $data['customer_id'] : null,
				'meta'             => isset( $data['meta'] ) ? wp_json_encode( $data['meta'] ) : null,
				'created_at'       => Dates::now_mysql(),
			)
		);
	}
}
