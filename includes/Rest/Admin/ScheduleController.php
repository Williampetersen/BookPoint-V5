<?php
/**
 * Admin schedule and holidays API.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Rest\Admin;

use PointlyBooking\Repositories\HolidayRepository;
use PointlyBooking\Repositories\ScheduleRepository;
use PointlyBooking\Rest\Controller;
use PointlyBooking\Settings\Settings;
use PointlyBooking\Support\Dates;
use PointlyBooking\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * /admin/schedule, /admin/holidays…
 */
final class ScheduleController extends Controller {

	/**
	 * {@inheritDoc}
	 */
	public function register_routes() {
		$read   = self::cap( 'pointlybooking_manage_settings', 'pointlybooking_manage_agents', 'pointlybooking_manage_bookings' );
		$manage = self::cap( 'pointlybooking_manage_settings' );

		$this->route(
			'/admin/schedule',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => $read,
					'args'                => array( 'agent_id' => self::arg( 'id' ) ),
				),
				array(
					'methods'             => array( 'POST', 'PUT' ),
					'callback'            => array( $this, 'save' ),
					'permission_callback' => $manage,
				),
			)
		);
		$this->route(
			'/admin/holidays',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'holidays' ),
					'permission_callback' => $read,
					'args'                => array(
						'year'     => self::arg( 'id' ),
						'agent_id' => self::arg( 'int' ),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'holiday_save' ),
					'permission_callback' => self::cap( 'pointlybooking_manage_settings', 'pointlybooking_manage_bookings' ),
				),
			)
		);
		$this->route(
			'/admin/holidays/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'holiday_save' ),
					'permission_callback' => self::cap( 'pointlybooking_manage_settings', 'pointlybooking_manage_bookings' ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'holiday_delete' ),
					'permission_callback' => self::cap( 'pointlybooking_manage_settings', 'pointlybooking_manage_bookings' ),
				),
			)
		);
	}

	/**
	 * Weekly schedule (global or one staff member) and slot settings.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function show( \WP_REST_Request $r ) {
		$agent  = absint( $r->get_param( 'agent_id' ) );
		$week   = ScheduleRepository::weekly( $agent );
		$source = 'schedule';
		if ( ! $agent && ! $week ) {
			$week   = self::legacy_week();
			$source = $week ? 'legacy' : 'empty';
		}
		return $this->ok(
			array(
				'agent_id' => $agent,
				'source'   => $source,
				'schedule' => (object) $week,
				'settings' => array(
					'slot_interval_minutes' => Settings::int( 'slot_interval_minutes' ),
					'timezone'              => wp_timezone_string(),
				),
			)
		);
	}

	/**
	 * Builds a weekly schedule from the 2.x settings (pointlybooking_schedule_0..6, 0 = Sunday).
	 *
	 * @return array
	 */
	private static function legacy_week() {
		$week   = array();
		$breaks = array();
		foreach ( array_filter( array_map( 'trim', explode( ',', (string) Settings::get( 'pointlybooking_breaks', '' ) ) ) ) as $range ) {
			$parts = explode( '-', $range );
			if ( 2 === count( $parts ) && Dates::is_hm( trim( $parts[0] ) ) && Dates::is_hm( trim( $parts[1] ) ) ) {
				$breaks[] = array(
					'start' => trim( $parts[0] ),
					'end'   => trim( $parts[1] ),
				);
			}
		}
		for ( $d = 0; $d <= 6; $d++ ) {
			$value = trim( (string) Settings::get( 'pointlybooking_schedule_' . $d, '' ) );
			$parts = explode( '-', $value );
			if ( 2 !== count( $parts ) || ! Dates::is_hm( trim( $parts[0] ) ) || ! Dates::is_hm( trim( $parts[1] ) ) ) {
				continue;
			}
			$week[ 0 === $d ? 7 : $d ][] = array(
				'start'      => trim( $parts[0] ),
				'end'        => trim( $parts[1] ),
				'breaks'     => $breaks,
				'is_enabled' => true,
			);
		}
		ksort( $week );
		return $week;
	}

	/**
	 * Saves a weekly schedule and (for the global schedule) the slot interval.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save( \WP_REST_Request $r ) {
		$body  = $this->body( $r );
		$agent = absint( $body['agent_id'] ?? $r->get_param( 'agent_id' ) );
		$week  = StaffController::clean_week( (array) ( $body['schedule'] ?? array() ) );

		foreach ( $week as $day => $intervals ) {
			$ranges = array();
			foreach ( $intervals as $interval ) {
				$start = Dates::hm( $interval['start'] );
				$end   = Dates::hm( $interval['end'] );
				if ( '' === $start || '' === $end || Dates::to_minutes( $end ) <= Dates::to_minutes( $start ) ) {
					return $this->error(
						'invalid_interval',
						/* translators: %s: weekday name */
						sprintf( __( 'Check the hours on %s: the end time must be after the start time.', 'pointly-booking' ), $this->weekday( $day ) ),
						400,
						array( 'field' => 'schedule.' . $day )
					);
				}
				$ranges[] = array( Dates::to_minutes( $start ), Dates::to_minutes( $end ) );
			}
			usort(
				$ranges,
				static function ( $a, $b ) {
					return $a[0] <=> $b[0];
				}
			);
			for ( $i = 1, $n = count( $ranges ); $i < $n; $i++ ) {
				if ( $ranges[ $i ][0] < $ranges[ $i - 1 ][1] ) {
					return $this->error(
						'overlapping_intervals',
						/* translators: %s: weekday name */
						sprintf( __( 'The working periods on %s overlap.', 'pointly-booking' ), $this->weekday( $day ) ),
						400,
						array( 'field' => 'schedule.' . $day )
					);
				}
			}
		}

		if ( ! ScheduleRepository::replace_weekly( $agent, $week ) ) {
			return $this->error( 'save_failed', __( 'The schedule could not be saved. Nothing was changed.', 'pointly-booking' ), 500 );
		}

		if ( ! $agent && isset( $body['settings']['slot_interval_minutes'] ) ) {
			$result = Settings::update( array( 'slot_interval_minutes' => $body['settings']['slot_interval_minutes'] ) );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			ScheduleRepository::sync_settings_row( Settings::int( 'slot_interval_minutes' ) );
		}

		$request = new \WP_REST_Request( 'GET' );
		$request->set_param( 'agent_id', $agent );
		return $this->show( $request );
	}

	/**
	 * Localised weekday name for ISO day 1-7.
	 *
	 * @param int $day ISO weekday.
	 * @return string
	 */
	private function weekday( $day ) {
		global $wp_locale;
		return $wp_locale ? $wp_locale->get_weekday( $day % 7 ) : (string) $day;
	}

	/**
	 * Holidays list.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function holidays( \WP_REST_Request $r ) {
		$agent = $r->get_param( 'agent_id' );
		return $this->ok( HolidayRepository::list_for( absint( $r->get_param( 'year' ) ), null === $agent ? -1 : (int) $agent ) );
	}

	/**
	 * Create or update a holiday.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function holiday_save( \WP_REST_Request $r ) {
		$id       = (int) ( $r['id'] ?? 0 );
		$existing = $id ? HolidayRepository::find( $id ) : null;
		if ( $id && ! $existing ) {
			return $this->error( 'not_found', __( 'Holiday not found.', 'pointly-booking' ), 404 );
		}
		$merged = array_merge( $existing ? $existing : array(), $this->body( $r ) );

		$title = Sanitize::text( $merged['title'] ?? '', 190 );
		if ( '' === $title ) {
			return $this->error( 'invalid_title', __( 'Please enter a title.', 'pointly-booking' ), 400, array( 'field' => 'title' ) );
		}
		$start = Sanitize::date( $merged['start_date'] ?? '' );
		$end   = Sanitize::date( $merged['end_date'] ?? '' );
		if ( '' === $end ) {
			$end = $start;
		}
		if ( '' === $start ) {
			return $this->error( 'invalid_date', __( 'Please choose a start date.', 'pointly-booking' ), 400, array( 'field' => 'start_date' ) );
		}
		if ( $end < $start ) {
			return $this->error( 'invalid_range', __( 'The end date must be on or after the start date.', 'pointly-booking' ), 400, array( 'field' => 'end_date' ) );
		}
		$recurring = Sanitize::bool01( $merged['is_recurring'] ?? ( $merged['is_recurring_yearly'] ?? 0 ) );
		$agent     = absint( $merged['agent_id'] ?? 0 );
		$data      = array(
			'title'               => $title,
			'start_date'          => $start,
			'end_date'            => $end,
			'agent_id'            => $agent ? $agent : null,
			'is_recurring'        => $recurring,
			'is_recurring_yearly' => $recurring,
			'is_enabled'          => Sanitize::bool01( $merged['is_enabled'] ?? 1 ),
		);
		if ( $id ) {
			HolidayRepository::save( $id, $data );
		} else {
			$id = HolidayRepository::create( $data );
			if ( ! $id ) {
				return $this->error( 'save_failed', __( 'The holiday could not be saved.', 'pointly-booking' ), 500 );
			}
		}
		return $this->ok( HolidayRepository::find( $id ), $existing ? 200 : 201 );
	}

	/**
	 * Deletes a holiday.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function holiday_delete( \WP_REST_Request $r ) {
		HolidayRepository::remove( (int) $r['id'] );
		return $this->ok( array( 'deleted' => true ) );
	}
}
