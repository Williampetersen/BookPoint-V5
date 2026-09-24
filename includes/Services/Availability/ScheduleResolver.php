<?php
/**
 * Resolves the effective working intervals of staff members for given dates.
 *
 * Resolution order for a staff member on a date (first match wins):
 *   1. Holidays (global or for this staff member) → closed.
 *   2. The staff member's own weekly schedule (schedules table, agent rows).
 *   3. The staff member's "override" weekly JSON (agents.schedule_json).
 *   4. Global business hours (schedules table, rows without agent).
 *   5. Legacy per-staff working hours (agent_working_hours, from 2.x).
 *   6. Legacy business hours stored as settings strings (pointlybooking_schedule_0..6).
 * Dated time-off (agent_breaks) is always subtracted.
 *
 * Agent ID 0 represents the business itself (installs without staff).
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Services\Availability;

use PointlyBooking\Repositories\AgentRepository;
use PointlyBooking\Repositories\HolidayRepository;
use PointlyBooking\Repositories\ScheduleRepository;
use PointlyBooking\Settings\Settings;
use PointlyBooking\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Bulk-loads schedule data once and answers "when is X open on day Y" in memory.
 */
final class ScheduleResolver {

	/**
	 * Global weekly schedule from the schedules table (ISO weekday => intervals).
	 *
	 * @var array
	 */
	private $global = array();

	/**
	 * Agent weekly schedules from the schedules table.
	 *
	 * @var array<int,array>
	 */
	private $agent_weekly = array();

	/**
	 * Agent override JSON schedules (ISO weekday => intervals).
	 *
	 * @var array<int,array>
	 */
	private $agent_json = array();

	/**
	 * Legacy agent working hours.
	 *
	 * @var array<int,array>
	 */
	private $legacy = array();

	/**
	 * Dated breaks: agent => date => blocks.
	 *
	 * @var array
	 */
	private $dated = array();

	/**
	 * Enabled holidays.
	 *
	 * @var array
	 */
	private $holidays = array();

	/**
	 * Settings-string fallback schedule (ISO weekday => intervals).
	 *
	 * @var array
	 */
	private $settings_week = array();

	/**
	 * Loads everything needed for the agents and date range.
	 *
	 * @param int[]  $agent_ids Agents (0 = business).
	 * @param string $from      Y-m-d.
	 * @param string $to        Y-m-d.
	 */
	public function __construct( array $agent_ids, $from, $to ) {
		$real = array_values( array_filter( array_map( 'intval', $agent_ids ) ) );

		$weekly             = ScheduleRepository::weekly_many( $real );
		$this->global       = $weekly['global'];
		$this->agent_weekly = $weekly['agents'];
		$this->legacy       = ScheduleRepository::legacy_hours( $real );
		$this->dated        = ScheduleRepository::dated_breaks( $real, $from, $to );
		$this->holidays     = HolidayRepository::enabled();

		$agents = AgentRepository::by_id();
		foreach ( $real as $id ) {
			if ( isset( $agents[ $id ] ) && ! empty( $agents[ $id ]['schedule_json'] ) ) {
				$parsed = self::parse_weekday_json( $agents[ $id ]['schedule_json'] );
				if ( null !== $parsed ) {
					$this->agent_json[ $id ] = $parsed;
				}
			}
		}

		$this->settings_week = self::settings_schedule();
	}

	/**
	 * Parses {"0":"09:00-17:00","1":"",…} (0 = Sunday) into ISO weekday intervals.
	 * Returns null when the JSON holds no usable information.
	 *
	 * @param string $json JSON.
	 * @return array|null
	 */
	public static function parse_weekday_json( $json ) {
		$data = json_decode( (string) $json, true );
		if ( ! is_array( $data ) || ! $data ) {
			return null;
		}
		$out = array();
		foreach ( $data as $day => $range ) {
			$w = (int) $day;
			if ( $w < 0 || $w > 6 || is_array( $range ) ) {
				continue;
			}
			$iso = 0 === $w ? 7 : $w;
			if ( preg_match( '/^(\d{2}:\d{2})-(\d{2}:\d{2})$/', trim( (string) $range ), $m ) && Settings::valid_range( trim( (string) $range ) ) ) {
				$out[ $iso ][] = array(
					'start'      => $m[1],
					'end'        => $m[2],
					'breaks'     => array(),
					'is_enabled' => true,
				);
			} else {
				$out[ $iso ] = $out[ $iso ] ?? array();
			}
		}
		return $out;
	}

	/**
	 * Schedule from the historical settings strings.
	 *
	 * @return array
	 */
	private static function settings_schedule() {
		$breaks = array();
		foreach ( array_filter( array_map( 'trim', explode( ',', (string) Settings::get( 'pointlybooking_breaks', '' ) ) ) ) as $range ) {
			if ( Settings::valid_range( $range ) ) {
				list( $start, $end ) = explode( '-', $range );
				$breaks[]            = array(
					'start' => $start,
					'end'   => $end,
				);
			}
		}
		$out = array();
		for ( $w = 0; $w <= 6; $w++ ) {
			$range = trim( (string) Settings::get( 'pointlybooking_schedule_' . $w, '' ) );
			if ( '' === $range || ! Settings::valid_range( $range ) ) {
				continue;
			}
			list( $start, $end )        = explode( '-', $range );
			$out[ 0 === $w ? 7 : $w ][] = array(
				'start'      => $start,
				'end'        => $end,
				'breaks'     => $breaks,
				'is_enabled' => true,
			);
		}
		return $out;
	}

	/**
	 * Whether a holiday closes the date for the agent.
	 *
	 * @param int    $agent_id Agent (0 = business).
	 * @param string $date     Y-m-d.
	 * @return bool
	 */
	public function is_closed( $agent_id, $date ) {
		foreach ( $this->holidays as $holiday ) {
			$applies = empty( $holiday['agent_id'] ) || (int) $holiday['agent_id'] === (int) $agent_id;
			if ( $applies && HolidayRepository::covers( $holiday, $date ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Weekly definition that applies to an agent.
	 *
	 * @param int $agent_id Agent (0 = business).
	 * @return array ISO weekday => intervals.
	 */
	public function week_for( $agent_id ) {
		if ( $agent_id > 0 ) {
			if ( ! empty( $this->agent_weekly[ $agent_id ] ) ) {
				return $this->agent_weekly[ $agent_id ];
			}
			if ( isset( $this->agent_json[ $agent_id ] ) ) {
				return $this->agent_json[ $agent_id ];
			}
		}
		if ( $this->global ) {
			return $this->global;
		}
		if ( $agent_id > 0 && ! empty( $this->legacy[ $agent_id ] ) ) {
			return $this->legacy[ $agent_id ];
		}
		return $this->settings_week;
	}

	/**
	 * Open (bookable) intervals for an agent on a date, as [start_min, end_min] pairs.
	 *
	 * @param int    $agent_id Agent (0 = business).
	 * @param string $date     Y-m-d.
	 * @return array<int,int[]>
	 */
	public function open_intervals( $agent_id, $date ) {
		if ( $this->is_closed( $agent_id, $date ) ) {
			return array();
		}
		$week = $this->week_for( $agent_id );
		$day  = Dates::iso_weekday( $date );
		$open = array();
		$cuts = array();

		foreach ( $week[ $day ] ?? array() as $interval ) {
			if ( empty( $interval['is_enabled'] ) ) {
				continue;
			}
			$open[] = array( Dates::to_minutes( $interval['start'] ), Dates::to_minutes( $interval['end'] ) );
			foreach ( $interval['breaks'] ?? array() as $break ) {
				$cuts[] = array( Dates::to_minutes( $break['start'] ), Dates::to_minutes( $break['end'] ) );
			}
		}
		foreach ( $this->dated[ $agent_id ][ $date ] ?? array() as $break ) {
			if ( '' !== $break['start'] && '' !== $break['end'] ) {
				$cuts[] = array( Dates::to_minutes( $break['start'] ), Dates::to_minutes( $break['end'] ) );
			}
		}

		return Intervals::subtract( Intervals::normalize( $open ), $cuts );
	}
}
