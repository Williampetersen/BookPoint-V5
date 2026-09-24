<?php
/**
 * Customer "manage booking" API (link from the confirmation email).
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Rest\Front;

use PointlyBooking\Repositories\BookingRepository;
use PointlyBooking\Rest\Controller;
use PointlyBooking\Rest\Presenter;
use PointlyBooking\Services\Availability\AvailabilityService;
use PointlyBooking\Services\Booking\BookingService;
use PointlyBooking\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * /manage/…
 */
final class ManageController extends Controller {

	/**
	 * {@inheritDoc}
	 */
	public function register_routes() {
		$public = array( Controller::class, 'public_access' );
		$key    = array( 'key' => self::arg( 'string', true, array( 'pattern' => '^[a-fA-F0-9]{40}([a-fA-F0-9]{24})?$' ) ) );

		$this->route(
			'/manage/booking',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'show' ),
				'permission_callback' => $public,
				'args'                => $key,
			)
		);
		$this->route(
			'/manage/cancel',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'cancel' ),
				'permission_callback' => $public,
				'args'                => $key,
			)
		);
		$this->route(
			'/manage/reschedule',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reschedule' ),
				'permission_callback' => $public,
				'args'                => array_merge(
					$key,
					array(
						'date'  => self::arg( 'date', true ),
						'start' => self::arg( 'time', true ),
					)
				),
			)
		);
		$this->route(
			'/manage/availability/month',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'month' ),
				'permission_callback' => $public,
				'args'                => array_merge( $key, array( 'month' => self::arg( 'month' ) ) ),
			)
		);
		$this->route(
			'/manage/availability/day',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'day' ),
				'permission_callback' => $public,
				'args'                => array_merge( $key, array( 'date' => self::arg( 'date', true ) ) ),
			)
		);
		// 2.x: GET /manage/slots?service_id&agent_id&date&exclude_booking_id&key.
		$this->route(
			'/manage/slots',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'legacy_slots' ),
				'permission_callback' => $public,
				'args'                => array(
					'date'               => self::arg( 'date', true ),
					'key'                => self::arg( 'string' ),
					'service_id'         => self::arg( 'id' ),
					'agent_id'           => self::arg( 'id' ),
					'exclude_booking_id' => self::arg( 'id' ),
				),
			)
		);
	}

	/**
	 * Booking for a manage key.
	 *
	 * @param string $key Key.
	 * @return array|\WP_Error
	 */
	private function booking( $key ) {
		$booking = BookingRepository::find_by_key( $key );
		if ( ! $booking ) {
			return $this->error( 'not_found', __( 'This link is no longer valid. Please use the most recent email we sent you, or contact us.', 'pointly-booking' ), 404 );
		}
		return $booking;
	}

	/**
	 * What the customer may still do with a booking.
	 *
	 * @param array $booking Booking row.
	 * @return array{can_cancel:bool,can_reschedule:bool,is_past:bool}
	 */
	public static function permissions( array $booking ) {
		$past   = (string) $booking['start_datetime'] <= Dates::now_mysql();
		$active = in_array( $booking['status'], array( 'pending', 'confirmed', 'pending_payment' ), true );
		/**
		 * Filters whether customers may change a booking from the manage page.
		 *
		 * @param array $permissions can_cancel, can_reschedule.
		 * @param array $booking     Booking row.
		 */
		$perm = (array) apply_filters(
			'pointlybooking_manage_permissions',
			array(
				'can_cancel'     => $active && ! $past,
				'can_reschedule' => $active && ! $past && 'pending_payment' !== $booking['status'],
			),
			$booking
		);
		return array(
			'can_cancel'     => ! empty( $perm['can_cancel'] ),
			'can_reschedule' => ! empty( $perm['can_reschedule'] ),
			'is_past'        => $past,
		);
	}

	/**
	 * Response payload for the manage page.
	 *
	 * @param int $id Booking ID.
	 * @return array
	 */
	private function payload( $id ) {
		$joined = BookingRepository::find_joined( $id );
		return array_merge( Presenter::booking_public( $joined ), self::permissions( $joined ) );
	}

	/**
	 * Booking details.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show( \WP_REST_Request $r ) {
		$limited = WizardController::throttle( 'manage_view', 60, 600 );
		if ( $limited ) {
			return $limited;
		}
		$booking = $this->booking( (string) $r->get_param( 'key' ) );
		if ( is_wp_error( $booking ) ) {
			return $booking;
		}
		return $this->ok( $this->payload( $booking['id'] ) );
	}

	/**
	 * Customer cancellation.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function cancel( \WP_REST_Request $r ) {
		$limited = WizardController::throttle( 'manage_action', 30, 600 );
		if ( $limited ) {
			return $limited;
		}
		$booking = $this->booking( (string) $r->get_param( 'key' ) );
		if ( is_wp_error( $booking ) ) {
			return $booking;
		}
		if ( ! self::permissions( $booking )['can_cancel'] ) {
			return $this->error( 'not_allowed', __( 'This booking can no longer be cancelled online. Please contact us.', 'pointly-booking' ), 409 );
		}
		$result = BookingService::set_status(
			$booking['id'],
			'cancelled',
			array(
				'source'     => 'manage',
				'actor'      => 'customer',
				'rotate_key' => true,
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $this->payload( $booking['id'] ) );
	}

	/**
	 * Customer reschedule (availability re-checked under a lock).
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function reschedule( \WP_REST_Request $r ) {
		$limited = WizardController::throttle( 'manage_action', 30, 600 );
		if ( $limited ) {
			return $limited;
		}
		$booking = $this->booking( (string) $r->get_param( 'key' ) );
		if ( is_wp_error( $booking ) ) {
			return $booking;
		}
		if ( ! self::permissions( $booking )['can_reschedule'] ) {
			return $this->error( 'not_allowed', __( 'This booking can no longer be changed online. Please contact us.', 'pointly-booking' ), 409 );
		}
		$result = BookingService::reschedule(
			$booking['id'],
			(string) $r->get_param( 'date' ),
			(string) $r->get_param( 'start' ),
			-1,
			array(
				'source'     => 'manage',
				'actor'      => 'customer',
				'rotate_key' => true,
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $this->payload( $booking['id'] ) );
	}

	/**
	 * Month overview for rescheduling (same staff, the booking itself ignored).
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function month( \WP_REST_Request $r ) {
		$booking = $this->booking( (string) $r->get_param( 'key' ) );
		if ( is_wp_error( $booking ) ) {
			return $booking;
		}
		$month = (string) $r->get_param( 'month' );
		if ( '' === $month ) {
			$month = substr( Dates::today(), 0, 7 );
		}
		return $this->ok( AvailabilityService::month( (int) $booking['service_id'], (int) $booking['agent_id'], (int) $booking['location_id'], $month, (int) $booking['id'] ) );
	}

	/**
	 * Free times of a day for rescheduling.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function day( \WP_REST_Request $r ) {
		$booking = $this->booking( (string) $r->get_param( 'key' ) );
		if ( is_wp_error( $booking ) ) {
			return $booking;
		}
		$data = AvailabilityService::day(
			array(
				'service_id'  => (int) $booking['service_id'],
				'agent_id'    => (int) $booking['agent_id'],
				'location_id' => (int) $booking['location_id'],
				'date'        => (string) $r->get_param( 'date' ),
				'exclude_id'  => (int) $booking['id'],
			)
		);
		foreach ( $data['slots'] as &$slot ) {
			unset( $slot['agents'] );
		}
		unset( $slot );
		return $this->ok( $data );
	}

	/**
	 * 2.x slot list: { slots: [ {start_time, end_time} ] }.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function legacy_slots( \WP_REST_Request $r ) {
		$limited = WizardController::throttle( 'manage_view', 60, 600 );
		if ( $limited ) {
			return $limited;
		}
		$key     = (string) $r->get_param( 'key' );
		$booking = '' !== $key ? BookingRepository::find_by_key( $key ) : null;
		$exclude = 0;
		if ( $booking && (int) $r->get_param( 'exclude_booking_id' ) === (int) $booking['id'] ) {
			$exclude = (int) $booking['id'];
		}
		$service = $booking ? (int) $booking['service_id'] : (int) $r->get_param( 'service_id' );
		$agent   = $booking ? (int) $booking['agent_id'] : (int) $r->get_param( 'agent_id' );
		if ( ! $service ) {
			return $this->error( 'invalid_service', __( 'Invalid service.', 'pointly-booking' ) );
		}
		$data  = AvailabilityService::day(
			array(
				'service_id'  => $service,
				'agent_id'    => $agent,
				'location_id' => $booking ? (int) $booking['location_id'] : 0,
				'date'        => (string) $r->get_param( 'date' ),
				'exclude_id'  => $exclude,
			)
		);
		$slots = array();
		foreach ( $data['slots'] as $slot ) {
			$slots[] = array(
				'start_time' => $slot['start'],
				'end_time'   => $slot['end'],
			);
		}
		return new \WP_REST_Response(
			array(
				'status' => 'success',
				'slots'  => $slots,
				'data'   => $slots,
			),
			200
		);
	}
}
