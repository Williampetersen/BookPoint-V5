<?php
/**
 * Admin bookings API.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Rest\Admin;

use PointlyBooking\Repositories\BookingRepository;
use PointlyBooking\Rest\Controller;
use PointlyBooking\Rest\Presenter;
use PointlyBooking\Services\Availability\AvailabilityService;
use PointlyBooking\Services\Booking\BookingService;
use PointlyBooking\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * /admin/bookings…
 */
final class BookingsController extends Controller {

	/**
	 * {@inheritDoc}
	 */
	public function register_routes() {
		$can = self::cap( 'pointlybooking_manage_bookings' );

		$this->route(
			'/admin/bookings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => $can,
					'args'                => array_merge(
						self::paging_args(),
						array(
							'status'      => self::arg( 'string' ),
							'agent_id'    => self::arg( 'id' ),
							'service_id'  => self::arg( 'id' ),
							'location_id' => self::arg( 'id' ),
							'customer_id' => self::arg( 'id' ),
							'date_from'   => self::arg( 'string' ),
							'date_to'     => self::arg( 'string' ),
							'q'           => self::arg( 'string' ),
							'per'         => self::arg( 'id' ),
							'sort'        => self::arg( 'string' ),
						)
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create' ),
					'permission_callback' => $can,
				),
			)
		);

		$this->route(
			'/admin/bookings/bulk',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'bulk' ),
				'permission_callback' => $can,
				'args'                => array(
					'ids'    => self::arg( 'ids', true ),
					'action' => self::arg( 'key', true, array( 'enum' => array( 'confirmed', 'pending', 'cancelled', 'completed', 'delete' ) ) ),
					'notify' => self::arg( 'bool' ),
				),
			)
		);

		$this->route(
			'/admin/bookings/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => $can,
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update' ),
					'permission_callback' => $can,
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'destroy' ),
					'permission_callback' => $can,
				),
			)
		);

		// 2.x endpoints kept for integrations.
		$this->route(
			'/admin/bookings/(?P<id>\d+)/status',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'legacy_status' ),
				'permission_callback' => $can,
				'args'                => array( 'status' => self::arg( 'key', true ) ),
			)
		);
		$this->route(
			'/admin/bookings/(?P<id>\d+)/reschedule',
			array(
				'methods'             => array( 'PATCH', 'POST', 'PUT' ),
				'callback'            => array( $this, 'legacy_reschedule' ),
				'permission_callback' => $can,
			)
		);

		$this->route(
			'/admin/availability',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'availability' ),
				'permission_callback' => $can,
				'args'                => array(
					'service_id'  => self::arg( 'id', true ),
					'agent_id'    => self::arg( 'id' ),
					'location_id' => self::arg( 'id' ),
					'date'        => self::arg( 'date', true ),
					'exclude_id'  => self::arg( 'id' ),
				),
			)
		);
		$this->route(
			'/admin/availability/slots',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'legacy_slots' ),
				'permission_callback' => $can,
			)
		);
	}

	/**
	 * Normalised list filters from a request.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return array
	 */
	public static function filters( \WP_REST_Request $r ) {
		$status = (string) $r->get_param( 'status' );
		$order  = strtolower( (string) ( $r->get_param( 'order' ) ? $r->get_param( 'order' ) : $r->get_param( 'sort' ) ) );
		return array(
			'search'      => (string) ( $r->get_param( 'search' ) ?? $r->get_param( 'q' ) ?? '' ),
			'status'      => ( '' !== $status && 'all' !== $status ) ? array_filter( explode( ',', $status ) ) : array(),
			'agent_id'    => absint( $r->get_param( 'agent_id' ) ),
			'service_id'  => absint( $r->get_param( 'service_id' ) ),
			'location_id' => absint( $r->get_param( 'location_id' ) ),
			'customer_id' => absint( $r->get_param( 'customer_id' ) ),
			'date_from'   => Sanitize::date( $r->get_param( 'date_from' ) ),
			'date_to'     => Sanitize::date( $r->get_param( 'date_to' ) ),
			'orderby'     => Sanitize::key( $r->get_param( 'orderby' ) ? $r->get_param( 'orderby' ) : 'start' ),
			'order'       => 'asc' === $order ? 'asc' : 'desc',
			'page'        => max( 1, absint( $r->get_param( 'page' ) ) ),
			'per_page'    => max( 1, min( 200, absint( $r->get_param( 'per_page' ) ? $r->get_param( 'per_page' ) : ( $r->get_param( 'per' ) ? $r->get_param( 'per' ) : 20 ) ) ) ),
		);
	}

	/**
	 * List.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function index( \WP_REST_Request $r ) {
		$f    = self::filters( $r );
		$data = BookingRepository::search( $f );
		return $this->ok(
			array(
				'items'    => array_map( array( Presenter::class, 'booking_item' ), $data['items'] ),
				'total'    => $data['total'],
				'page'     => $f['page'],
				'per_page' => $f['per_page'],
				'counts'   => BookingRepository::status_counts( $f ),
			)
		);
	}

	/**
	 * Detail.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show( \WP_REST_Request $r ) {
		$b = BookingRepository::find_joined( (int) $r['id'] );
		if ( ! $b ) {
			return $this->error( 'not_found', __( 'Booking not found.', 'pointly-booking' ), 404 );
		}
		return $this->ok( Presenter::booking_detail( $b ) );
	}

	/**
	 * Create (admin).
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create( \WP_REST_Request $r ) {
		$body = $this->body( $r );
		if ( ! isset( $body['start'] ) && isset( $body['start_time'] ) ) {
			$body['start'] = $body['start_time'];
		}
		$booking = BookingService::create(
			$body,
			array(
				'source' => 'admin',
				'notify' => ! isset( $body['notify'] ) || ! empty( $body['notify'] ),
			)
		);
		if ( is_wp_error( $booking ) ) {
			return $booking;
		}
		$joined = BookingRepository::find_joined( (int) $booking['id'] );
		$detail = Presenter::booking_detail( $joined );
		// 2.x clients read data.booking_id.
		$detail['booking_id'] = (int) $booking['id'];
		return $this->ok( $detail, 201 );
	}

	/**
	 * Update: status, reschedule (date/start/agent), notes, fields.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update( \WP_REST_Request $r ) {
		$id   = (int) $r['id'];
		$body = $this->body( $r );
		if ( ! BookingRepository::find( $id ) ) {
			return $this->error( 'not_found', __( 'Booking not found.', 'pointly-booking' ), 404 );
		}
		$context = array(
			'source' => 'admin',
			'notify' => ! isset( $body['notify'] ) || ! empty( $body['notify'] ),
			'force'  => ! empty( $body['force'] ),
		);

		$date  = $body['date'] ?? ( $body['start_date'] ?? null );
		$start = $body['start'] ?? ( $body['start_time'] ?? null );
		if ( null !== $date || null !== $start || isset( $body['agent_id'] ) ) {
			$current = BookingRepository::find( $id );
			$result  = BookingService::reschedule(
				$id,
				null !== $date ? $date : substr( $current['start_datetime'], 0, 10 ),
				null !== $start ? $start : substr( $current['start_datetime'], 11, 5 ),
				isset( $body['agent_id'] ) ? (int) $body['agent_id'] : -1,
				$context
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		if ( isset( $body['status'] ) ) {
			$result = BookingService::set_status( $id, Sanitize::key( $body['status'] ), $context );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		$details = array();
		foreach ( array( 'notes', 'fields', 'payment_status' ) as $key ) {
			if ( array_key_exists( $key, $body ) ) {
				$details[ $key ] = $body[ $key ];
			}
		}
		if ( isset( $body['admin_notes'] ) && ! isset( $details['notes'] ) ) {
			$details['notes'] = $body['admin_notes'];
		}
		if ( $details ) {
			$result = BookingService::update_details( $id, $details, $context );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return $this->ok( Presenter::booking_detail( BookingRepository::find_joined( $id ) ) );
	}

	/**
	 * Delete.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function destroy( \WP_REST_Request $r ) {
		$id = (int) $r['id'];
		if ( ! BookingService::delete( $id ) ) {
			return $this->error( 'not_found', __( 'Booking not found.', 'pointly-booking' ), 404 );
		}
		return $this->ok(
			array(
				'id'      => $id,
				'deleted' => true,
			)
		);
	}

	/**
	 * Bulk status change or delete.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function bulk( \WP_REST_Request $r ) {
		$ids    = Sanitize::ids( $r->get_param( 'ids' ) );
		$action = (string) $r->get_param( 'action' );
		$done   = 0;
		$failed = array();
		foreach ( $ids as $id ) {
			if ( 'delete' === $action ) {
				$ok = BookingService::delete( $id );
			} else {
				$ok = ! is_wp_error(
					BookingService::set_status(
						$id,
						$action,
						array(
							'source' => 'admin',
							'notify' => (bool) $r->get_param( 'notify' ),
						)
					)
				);
			}
			if ( $ok ) {
				++$done;
			} else {
				$failed[] = $id;
			}
		}
		return $this->ok(
			array(
				'updated' => $done,
				'failed'  => $failed,
			)
		);
	}

	/**
	 * 2.x: POST /admin/bookings/{id}/status.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function legacy_status( \WP_REST_Request $r ) {
		$result = BookingService::set_status( (int) $r['id'], (string) $r->get_param( 'status' ), array( 'source' => 'admin' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok(
			array(
				'id'     => (int) $r['id'],
				'status' => $result['status'],
			)
		);
	}

	/**
	 * 2.x: drag & drop reschedule with start/end datetimes.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function legacy_reschedule( \WP_REST_Request $r ) {
		$body  = $this->body( $r );
		$start = (string) ( $body['start_datetime'] ?? ( $body['start'] ?? '' ) );
		$start = str_replace( 'T', ' ', substr( $start, 0, 16 ) );
		if ( ! preg_match( '/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2})$/', $start, $m ) ) {
			return $this->error( 'invalid_datetime', __( 'Please choose a valid date and time.', 'pointly-booking' ) );
		}
		$result = BookingService::reschedule(
			(int) $r['id'],
			$m[1],
			$m[2],
			isset( $body['agent_id'] ) ? (int) $body['agent_id'] : -1,
			array(
				'source' => 'admin',
				'force'  => ! empty( $body['force'] ),
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok(
			array(
				'id'       => (int) $result['id'],
				'start'    => $result['start_datetime'],
				'end'      => $result['end_datetime'],
				'agent_id' => (int) $result['agent_id'],
			)
		);
	}

	/**
	 * Slots for the admin picker.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function availability( \WP_REST_Request $r ) {
		return $this->ok(
			AvailabilityService::day(
				array(
					'service_id'  => absint( $r->get_param( 'service_id' ) ),
					'agent_id'    => absint( $r->get_param( 'agent_id' ) ),
					'location_id' => absint( $r->get_param( 'location_id' ) ),
					'date'        => (string) $r->get_param( 'date' ),
					'exclude_id'  => absint( $r->get_param( 'exclude_id' ) ? $r->get_param( 'exclude_id' ) : $r->get_param( 'exclude_booking_id' ) ),
					'admin'       => true,
				)
			)
		);
	}

	/**
	 * 2.x slot shape: {slots:[{time,start,end,agent_id}]}.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function legacy_slots( \WP_REST_Request $r ) {
		$date = Sanitize::date( $r->get_param( 'date' ) );
		if ( '' === $date ) {
			return $this->error( 'invalid_date', __( 'Invalid date.', 'pointly-booking' ) );
		}
		$day   = AvailabilityService::day(
			array(
				'service_id' => absint( $r->get_param( 'service_id' ) ),
				'agent_id'   => absint( $r->get_param( 'agent_id' ) ),
				'date'       => $date,
				'exclude_id' => absint( $r->get_param( 'exclude_booking_id' ) ),
				'admin'      => true,
			)
		);
		$slots = array();
		foreach ( $day['slots'] as $slot ) {
			foreach ( $slot['agents'] as $agent_id ) {
				$slots[] = array(
					'time'     => $slot['start'],
					'start'    => $date . 'T' . $slot['start'] . ':00',
					'end'      => $date . 'T' . $slot['end'] . ':00',
					'agent_id' => (int) $agent_id,
				);
			}
		}
		return $this->ok(
			array(
				'date'  => $date,
				'slots' => $slots,
			)
		);
	}
}
