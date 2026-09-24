<?php
/**
 * Admin calendar and dashboard API.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Rest\Admin;

use PointlyBooking\Repositories\AgentRepository;
use PointlyBooking\Repositories\BookingRepository;
use PointlyBooking\Repositories\CustomerRepository;
use PointlyBooking\Repositories\ServiceRepository;
use PointlyBooking\Rest\Controller;
use PointlyBooking\Rest\Presenter;
use PointlyBooking\Services\Availability\AvailabilityService;
use PointlyBooking\Support\Cache;
use PointlyBooking\Support\Dates;
use PointlyBooking\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * /admin/calendar…, /admin/dashboard.
 */
final class CalendarController extends Controller {

	/**
	 * {@inheritDoc}
	 */
	public function register_routes() {
		$can = self::cap( 'pointlybooking_manage_bookings' );

		$range = array(
			'start'       => self::arg( 'string', true ),
			'end'         => self::arg( 'string', true ),
			'agent_id'    => self::arg( 'string' ),
			'service_id'  => self::arg( 'string' ),
			'location_id' => self::arg( 'id' ),
			'status'      => self::arg( 'string' ),
			'q'           => self::arg( 'string' ),
			'search'      => self::arg( 'string' ),
		);

		$this->route(
			'/admin/calendar',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'events' ),
				'permission_callback' => $can,
				'args'                => $range,
			)
		);
		$this->route(
			'/admin/calendar/bookings',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'events' ),
				'permission_callback' => $can,
				'args'                => $range,
			)
		);
		$this->route(
			'/admin/calendar-legacy',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'events' ),
				'permission_callback' => $can,
				'args'                => $range,
			)
		);
		$this->route(
			'/admin/schedule/unavailable',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'unavailable' ),
				'permission_callback' => $can,
				'args'                => array(
					'start'    => self::arg( 'string', true ),
					'end'      => self::arg( 'string', true ),
					'agent_id' => self::arg( 'id' ),
				),
			)
		);
		$this->route(
			'/admin/dashboard',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'dashboard' ),
				'permission_callback' => self::cap( 'pointlybooking_manage_bookings', 'pointlybooking_manage_settings' ),
				'args'                => array(
					'range' => self::arg( 'string' ),
					'from'  => self::arg( 'string' ),
					'to'    => self::arg( 'string' ),
				),
			)
		);
	}

	/**
	 * Bookings in a date range for the calendar views.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function events( \WP_REST_Request $r ) {
		$from = Sanitize::date( substr( (string) $r->get_param( 'start' ), 0, 10 ) );
		$to   = Sanitize::date( substr( (string) $r->get_param( 'end' ), 0, 10 ) );
		if ( '' === $from || '' === $to || $to < $from ) {
			return $this->error( 'invalid_range', __( 'Invalid date range.', 'pointly-booking' ) );
		}
		if ( Dates::diff_days( $from, $to ) > 400 ) {
			$to = Dates::add_days( $from, 400 );
		}
		$status = (string) $r->get_param( 'status' );
		$items  = BookingRepository::list_all(
			array(
				'date_from'   => $from,
				'date_to'     => $to,
				'agent_id'    => absint( $r->get_param( 'agent_id' ) ),
				'service_id'  => absint( $r->get_param( 'service_id' ) ),
				'location_id' => absint( $r->get_param( 'location_id' ) ),
				'status'      => ( '' !== $status && 'all' !== $status ) ? explode( ',', $status ) : array(),
				'search'      => (string) ( $r->get_param( 'search' ) ?? $r->get_param( 'q' ) ?? '' ),
			),
			3000
		);
		return $this->ok( array_map( array( Presenter::class, 'booking_item' ), $items ) );
	}

	/**
	 * Closed/break background blocks for a staff member.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function unavailable( \WP_REST_Request $r ) {
		$from = Sanitize::date( substr( (string) $r->get_param( 'start' ), 0, 10 ) );
		$to   = Sanitize::date( substr( (string) $r->get_param( 'end' ), 0, 10 ) );
		if ( '' === $from || '' === $to || $to < $from || Dates::diff_days( $from, $to ) > 62 ) {
			return $this->ok( array() );
		}
		$agent = absint( $r->get_param( 'agent_id' ) );
		if ( ! $agent && AgentRepository::all( true ) ) {
			return $this->ok( array() );
		}
		return $this->ok( AvailabilityService::unavailable_blocks( $agent, $from, $to ) );
	}

	/**
	 * Dashboard KPIs, chart, upcoming and recent bookings.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function dashboard( \WP_REST_Request $r ) {
		$today = Dates::today();
		$range = Sanitize::one_of( $r->get_param( 'range' ), array( '7', '30', '90', 'ytd', 'custom' ), '30' );
		if ( 'custom' === $range ) {
			$from = Sanitize::date( $r->get_param( 'from' ) );
			$to   = Sanitize::date( $r->get_param( 'to' ) );
			if ( '' === $from || '' === $to || $to < $from ) {
				$range = '30';
			}
		}
		if ( 'ytd' === $range ) {
			$from = substr( $today, 0, 4 ) . '-01-01';
			$to   = $today;
		} elseif ( 'custom' !== $range ) {
			$from = Dates::add_days( $today, -( (int) $range - 1 ) );
			$to   = $today;
		}
		if ( Dates::diff_days( $from, $to ) > 366 ) {
			$from = Dates::add_days( $to, -366 );
		}

		$data = Cache::remember(
			'dashboard',
			'kpis:' . $from . ':' . $to . ':' . $today,
			static function () use ( $from, $to, $today ) {
				$period      = BookingRepository::stats( $from . ' 00:00:00', $to . ' 23:59:59' );
				$span        = Dates::diff_days( $from, $to ) + 1;
				$prev_to     = Dates::add_days( $from, -1 );
				$prev_from   = Dates::add_days( $prev_to, -( $span - 1 ) );
				$previous    = BookingRepository::stats( $prev_from . ' 00:00:00', $prev_to . ' 23:59:59' );
				$today_stats = BookingRepository::stats( $today . ' 00:00:00', $today . ' 23:59:59' );
				$week        = BookingRepository::stats( Dates::now_mysql(), Dates::add_days( $today, 7 ) . ' 23:59:59' );
				$series      = BookingRepository::daily_series( $from, $to );

				$chart = array();
				for ( $d = $from; $d <= $to; $d = Dates::add_days( $d, 1 ) ) {
					$chart[] = array(
						'date'    => $d,
						'count'   => $series[ $d ]['count'] ?? 0,
						'revenue' => $series[ $d ]['revenue'] ?? 0,
					);
				}

				return array(
					'kpis'         => array(
						'today'             => $today_stats['count'],
						'upcoming_7d'       => $week['count'],
						'pending'           => BookingRepository::status_counts( array() )['pending'],
						'bookings'          => $period['count'],
						'bookings_previous' => $previous['count'],
						'revenue'           => $period['revenue'],
						'revenue_previous'  => $previous['revenue'],
						'new_customers'     => CustomerRepository::count_created( $from . ' 00:00:00', $to . ' 23:59:59' ),
						'services'          => ServiceRepository::count_all(),
						'agents'            => AgentRepository::count_all(),
						'total_bookings'    => BookingRepository::count_all(),
					),
					'chart'        => $chart,
					'top_services' => BookingRepository::top_services( $from . ' 00:00:00', $to . ' 23:59:59' ),
				);
			},
			120
		);

		$upcoming = BookingRepository::search(
			array(
				'date_from' => $today,
				'status'    => array( 'pending', 'confirmed', 'pending_payment' ),
				'orderby'   => 'start',
				'order'     => 'asc',
				'per_page'  => 8,
			)
		)['items'];
		$upcoming = array_values(
			array_filter(
				$upcoming,
				static function ( $b ) {
					return $b['end_datetime'] >= Dates::now_mysql();
				}
			)
		);
		$recent   = BookingRepository::search(
			array(
				'orderby'  => 'created',
				'order'    => 'desc',
				'per_page' => 6,
			)
		)['items'];

		$month_start = substr( $today, 0, 7 ) . '-01';
		$month_end   = gmdate( 'Y-m-t', strtotime( $month_start . ' 12:00:00 UTC' ) );
		$mini        = array();
		foreach ( BookingRepository::daily_series( $month_start, $month_end ) as $date => $row ) {
			$mini[ $date ] = $row['count'];
		}

		return $this->ok(
			array_merge(
				$data,
				array(
					'range'    => array(
						'key'  => $range,
						'from' => $from,
						'to'   => $to,
					),
					'upcoming' => array_map( array( Presenter::class, 'booking_item' ), $upcoming ),
					'recent'   => array_map( array( Presenter::class, 'booking_item' ), $recent ),
					'month'    => array(
						'month' => substr( $today, 0, 7 ),
						'days'  => $mini,
					),
					'today'    => $today,
					// 2.x shape.
					'kpi'      => array(
						'bookings_today' => $data['kpis']['today'],
						'upcoming_7d'    => $data['kpis']['upcoming_7d'],
						'pending'        => $data['kpis']['pending'],
						'services'       => $data['kpis']['services'],
						'agents'         => $data['kpis']['agents'],
					),
				)
			)
		);
	}
}
