<?php
/**
 * Slot engine: computes bookable start times and validates a requested slot.
 *
 * All data for a request (schedules, holidays, dated breaks, existing bookings,
 * service buffers) is loaded in a handful of queries, then evaluated in memory.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Services\Availability;

use PointlyBooking\Repositories\AgentRepository;
use PointlyBooking\Repositories\BookingRepository;
use PointlyBooking\Repositories\LocationRepository;
use PointlyBooking\Repositories\ServiceRepository;
use PointlyBooking\Settings\Settings;
use PointlyBooking\Support\Cache;
use PointlyBooking\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Availability calculations.
 */
final class AvailabilityService {

	/**
	 * Booking window in days from today (setting + public filter).
	 *
	 * @return int
	 */
	public static function window_days() {
		$days = (int) Settings::get( 'pointlybooking_future_days_limit', 60 );

		/**
		 * Filters how many days ahead customers may book (defaults to the "Booking window" setting).
		 *
		 * @param int $days Days.
		 */
		return max( 1, min( 730, (int) apply_filters( 'pointlybooking_public_max_booking_days', $days ) ) );
	}

	/**
	 * Last bookable date for customers.
	 *
	 * @return string
	 */
	public static function last_date() {
		return Dates::add_days( Dates::today(), self::window_days() );
	}

	/**
	 * Slot step in minutes.
	 *
	 * @return int
	 */
	public static function step() {
		return max( 5, min( 120, (int) Settings::get( 'slot_interval_minutes', 30 ) ) );
	}

	/**
	 * Staff members who can deliver a service at a location.
	 * Returns [0] when the site has no active staff (the business itself takes bookings).
	 *
	 * @param int $service_id  Service ID.
	 * @param int $location_id Location ID (0 = any).
	 * @return int[]
	 */
	public static function candidate_agents( $service_id, $location_id = 0 ) {
		if ( ! AgentRepository::all( true ) ) {
			return array( 0 );
		}
		$ids = wp_list_pluck( AgentRepository::for_service( $service_id ), 'id' );

		if ( $location_id > 0 ) {
			$assignments = LocationRepository::all_assignments()[ $location_id ] ?? array();
			if ( $assignments ) {
				$allowed = array();
				foreach ( $assignments as $assignment ) {
					if ( ! $assignment['services'] || in_array( (int) $service_id, $assignment['services'], true ) ) {
						$allowed[] = (int) $assignment['agent_id'];
					}
				}
				$ids = array_values( array_intersect( $ids, $allowed ) );
			}
		}

		return array_values( array_map( 'intval', $ids ) );
	}

	/**
	 * Location intervals for a date when the location uses a custom schedule (null = no restriction).
	 *
	 * @param array|null $location Location row.
	 * @param string     $date     Y-m-d.
	 * @return array|null
	 */
	private static function location_intervals( $location, $date ) {
		if ( ! $location || empty( $location['use_custom_schedule'] ) ) {
			return null;
		}
		$weekday = (int) gmdate( 'w', strtotime( $date . ' 12:00:00 UTC' ) );
		$out     = array();
		foreach ( (array) $location['schedule'] as $row ) {
			if ( ! is_array( $row ) || (int) ( $row['day'] ?? -1 ) !== $weekday ) {
				continue;
			}
			$start = Dates::hm( $row['start'] ?? '' );
			$end   = Dates::hm( $row['end'] ?? '' );
			if ( '' !== $start && '' !== $end ) {
				$out[] = array( Dates::to_minutes( $start ), Dates::to_minutes( $end ) );
			}
		}
		return Intervals::normalize( $out );
	}

	/**
	 * Service-specific weekly hours when "use global schedule" is off (null = no restriction).
	 *
	 * @param array  $service Service row.
	 * @param string $date    Y-m-d.
	 * @return array|null
	 */
	private static function service_intervals( array $service, $date ) {
		if ( ! empty( $service['use_global_schedule'] ) || empty( $service['schedule_json'] ) ) {
			return null;
		}
		$week = ScheduleResolver::parse_weekday_json( $service['schedule_json'] );
		if ( null === $week ) {
			return null;
		}
		$out = array();
		foreach ( $week[ Dates::iso_weekday( $date ) ] ?? array() as $interval ) {
			$out[] = array( Dates::to_minutes( $interval['start'] ), Dates::to_minutes( $interval['end'] ) );
		}
		return Intervals::normalize( $out );
	}

	/**
	 * Core computation: bookable start minutes per date and agent.
	 *
	 * @param array $args {
	 *     Arguments.
	 *
	 *     @type int    $service_id  Service ID (required).
	 *     @type int    $agent_id    Agent ID (0 = any eligible staff).
	 *     @type int    $location_id Location ID (0 = none).
	 *     @type string $from        First date (Y-m-d).
	 *     @type string $to          Last date (Y-m-d).
	 *     @type int    $exclude_id  Booking to ignore (reschedules).
	 *     @type bool   $admin       Skip customer-only limits (past, booking window).
	 * }
	 * @return array{dates:array<string,array<int,int[]>>,service:array,agents:int[]} date => start minute => agent IDs.
	 */
	public static function compute( array $args ) {
		$service_id  = absint( $args['service_id'] ?? 0 );
		$location_id = absint( $args['location_id'] ?? 0 );
		$exclude_id  = absint( $args['exclude_id'] ?? 0 );
		$admin       = ! empty( $args['admin'] );
		$from        = (string) ( $args['from'] ?? Dates::today() );
		$to          = (string) ( $args['to'] ?? $from );

		$empty   = array(
			'dates'   => array(),
			'service' => array(),
			'agents'  => array(),
		);
		$service = ServiceRepository::find( $service_id );
		if ( ! $service || ( ! $admin && ! $service['is_active'] ) ) {
			return $empty;
		}

		$today = Dates::today();
		if ( ! $admin ) {
			$from = max( $from, $today );
			$to   = min( $to, self::last_date() );
		}
		if ( $to < $from ) {
			$empty['service'] = $service;
			return $empty;
		}

		$candidates = self::candidate_agents( $service_id, $location_id );
		$agent_id   = absint( $args['agent_id'] ?? 0 );
		if ( $agent_id > 0 ) {
			if ( ! $admin && ! in_array( $agent_id, $candidates, true ) ) {
				$empty['service'] = $service;
				return $empty;
			}
			$candidates = array( $agent_id );
		}
		if ( ! $candidates ) {
			$empty['service'] = $service;
			return $empty;
		}

		$cache_key = wp_json_encode( array( $service_id, $candidates, $location_id, $from, $to, $exclude_id, $admin, $today ) );
		$cached    = Cache::get( 'availability', $cache_key );
		if ( is_array( $cached ) && ! $admin && $from > $today ) {
			return $cached;
		}

		$resolver = new ScheduleResolver( $candidates, $from, $to );
		$location = $location_id ? LocationRepository::find( $location_id ) : null;

		$duration   = (int) $service['duration_minutes'];
		$buf_before = (int) $service['buffer_before_minutes'];
		$buf_after  = (int) $service['buffer_after_minutes'];
		$capacity   = (int) $service['capacity'];
		$step       = self::step();

		// Existing bookings that can collide (padded by a day and the largest buffer).
		$occupied    = BookingRepository::occupying( $candidates, Dates::add_days( $from, -1 ) . ' 00:00:00', Dates::add_days( $to, 2 ) . ' 00:00:00', $exclude_id );
		$services    = ServiceRepository::by_id();
		$stale_limit = self::stale_pending_cutoff();
		$blocks      = array();
		foreach ( $occupied as $booking ) {
			if ( 'pending_payment' === $booking['status'] && $booking['created_at'] < $stale_limit ) {
				continue; // Abandoned checkout; no longer holds the slot.
			}
			$other                  = $services[ (int) $booking['service_id'] ] ?? array(
				'buffer_before_minutes' => 0,
				'buffer_after_minutes'  => 0,
			);
			$start                  = self::abs_minutes( $booking['start_datetime'], $from );
			$end                    = self::abs_minutes( $booking['end_datetime'], $from );
			$agent_key              = (int) $booking['agent_id'];
			$blocks[ $agent_key ][] = array(
				'from'    => $start - (int) $other['buffer_before_minutes'],
				'to'      => $end + (int) $other['buffer_after_minutes'],
				'start'   => $start,
				'service' => (int) $booking['service_id'],
			);
		}

		$now_minutes = self::abs_minutes( Dates::now_mysql(), $from );
		$dates       = array();
		for ( $date = $from; $date <= $to; $date = Dates::add_days( $date, 1 ) ) {
			$offset     = Dates::diff_days( $from, $date ) * 1440;
			$restrict   = self::location_intervals( $location, $date );
			$service_hr = self::service_intervals( $service, $date );
			$day_slots  = array();

			foreach ( $candidates as $candidate ) {
				$open = $resolver->open_intervals( $candidate, $date );
				if ( null !== $restrict ) {
					$open = Intervals::intersect( $open, $restrict );
				}
				if ( null !== $service_hr ) {
					$open = Intervals::intersect( $open, $service_hr );
				}
				foreach ( $open as $interval ) {
					for ( $minute = $interval[0]; $minute + $duration <= $interval[1]; $minute += $step ) {
						$abs_start = $offset + $minute;
						if ( ! $admin && $abs_start <= $now_minutes ) {
							continue;
						}
						if ( self::fits( $blocks[ $candidate ] ?? array(), $abs_start, $abs_start + $duration, $buf_before, $buf_after, $service_id, $capacity ) ) {
							$day_slots[ $minute ][] = $candidate;
						}
					}
				}
			}

			if ( $day_slots ) {
				ksort( $day_slots );
				$dates[ $date ] = $day_slots;
			}
		}

		$result = array(
			'dates'   => $dates,
			'service' => $service,
			'agents'  => $candidates,
		);
		if ( ! $admin ) {
			Cache::set( 'availability', $cache_key, $result, 300 );
		}
		return $result;
	}

	/**
	 * Whether a new appointment fits between existing bookings of one agent.
	 *
	 * @param array $blocks     Existing blocks of the agent.
	 * @param int   $start      New start (absolute minutes).
	 * @param int   $end        New end (absolute minutes).
	 * @param int   $buf_before New service buffer before.
	 * @param int   $buf_after  New service buffer after.
	 * @param int   $service_id New service ID.
	 * @param int   $capacity   New service capacity.
	 * @return bool
	 */
	private static function fits( array $blocks, $start, $end, $buf_before, $buf_after, $service_id, $capacity ) {
		$seats = 0;
		$from  = $start - $buf_before;
		$to    = $end + $buf_after;
		foreach ( $blocks as $block ) {
			if ( $block['from'] >= $to || $block['to'] <= $from ) {
				continue;
			}
			// Group sessions: same service at the same start time share the capacity.
			if ( $capacity > 1 && $block['service'] === (int) $service_id && $block['start'] === $start ) {
				++$seats;
				if ( $seats >= $capacity ) {
					return false;
				}
				continue;
			}
			return false;
		}
		return true;
	}

	/**
	 * Minutes between $base (Y-m-d 00:00) and a local DATETIME.
	 *
	 * @param string $datetime Local DATETIME.
	 * @param string $base     Y-m-d.
	 * @return int
	 */
	private static function abs_minutes( $datetime, $base ) {
		$date = substr( (string) $datetime, 0, 10 );
		$time = substr( (string) $datetime, 11, 5 );
		return Dates::diff_days( $base, $date ) * 1440 + Dates::to_minutes( $time );
	}

	/**
	 * Local DATETIME before which unpaid online-payment bookings stop holding their slot.
	 *
	 * @return string
	 */
	public static function stale_pending_cutoff() {
		$minutes = max( 5, (int) Settings::get( 'pending_payment_timeout_minutes', 30 ) );
		return Dates::now()->modify( '-' . $minutes . ' minutes' )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Month overview for the booking calendar.
	 *
	 * @param int    $service_id  Service ID.
	 * @param int    $agent_id    Agent (0 = any).
	 * @param int    $location_id Location.
	 * @param string $month       Y-m.
	 * @param int    $exclude_id  Booking to ignore.
	 * @return array{month:string,days:array,first_available:string|null,window_end:string}
	 */
	public static function month( $service_id, $agent_id, $location_id, $month, $exclude_id = 0 ) {
		$from = $month . '-01';
		$to   = gmdate( 'Y-m-t', strtotime( $from . ' 12:00:00 UTC' ) );
		$data = self::compute(
			array(
				'service_id'  => $service_id,
				'agent_id'    => $agent_id,
				'location_id' => $location_id,
				'from'        => $from,
				'to'          => $to,
				'exclude_id'  => $exclude_id,
			)
		);

		$days  = array();
		$first = null;
		foreach ( $data['dates'] as $date => $slots ) {
			$days[ $date ] = count( $slots );
			if ( null === $first ) {
				$first = $date;
			}
		}
		return array(
			'month'           => $month,
			'days'            => $days,
			'first_available' => $first,
			'window_end'      => self::last_date(),
		);
	}

	/**
	 * First date with availability on or after a date (scans the booking window).
	 *
	 * @param int    $service_id  Service ID.
	 * @param int    $agent_id    Agent (0 = any).
	 * @param int    $location_id Location.
	 * @param string $from        Y-m-d.
	 * @return string|null
	 */
	public static function first_available( $service_id, $agent_id, $location_id, $from = '' ) {
		$from = $from ? max( $from, Dates::today() ) : Dates::today();
		$last = self::last_date();
		while ( $from <= $last ) {
			$to   = min( Dates::add_days( $from, 30 ), $last );
			$data = self::compute(
				array(
					'service_id'  => $service_id,
					'agent_id'    => $agent_id,
					'location_id' => $location_id,
					'from'        => $from,
					'to'          => $to,
				)
			);
			if ( $data['dates'] ) {
				return (string) array_key_first( $data['dates'] );
			}
			$from = Dates::add_days( $to, 1 );
		}
		return null;
	}

	/**
	 * Slots of one day.
	 *
	 * @param array $args service_id, agent_id, location_id, date, exclude_id, admin.
	 * @return array{date:string,slots:array,timezone:string}
	 */
	public static function day( array $args ) {
		$date = (string) $args['date'];
		$data = self::compute(
			array_merge(
				$args,
				array(
					'from' => $date,
					'to'   => $date,
				)
			)
		);

		$duration = (int) ( $data['service']['duration_minutes'] ?? 0 );
		$slots    = array();
		foreach ( $data['dates'][ $date ] ?? array() as $minute => $agents ) {
			$slots[] = array(
				'start'  => Dates::from_minutes( $minute ),
				'end'    => Dates::from_minutes( $minute + $duration ),
				'agents' => array_values( array_unique( $agents ) ),
			);
		}
		return array(
			'date'     => $date,
			'slots'    => $slots,
			'timezone' => wp_timezone_string(),
		);
	}

	/**
	 * Validates a requested slot and returns the staff member to assign.
	 *
	 * @param array $args service_id, agent_id (0 = any), location_id, date, start, exclude_id, admin.
	 * @return int|\WP_Error Agent ID (0 = business) or error.
	 */
	public static function resolve_slot( array $args ) {
		$date  = (string) $args['date'];
		$start = Dates::to_minutes( (string) $args['start'] );
		$data  = self::compute(
			array_merge(
				$args,
				array(
					'from' => $date,
					'to'   => $date,
				)
			)
		);

		$agents = $data['dates'][ $date ][ $start ] ?? array();
		if ( ! $agents ) {
			return new \WP_Error( 'slot_unavailable', __( 'Sorry, that time was just taken. Please choose another time.', 'pointly-booking' ), array( 'status' => 409 ) );
		}
		if ( 1 === count( $agents ) ) {
			return (int) $agents[0];
		}

		// "Any staff": spread the load — pick whoever has the fewest bookings that day.
		$load = BookingRepository::load_per_agent( $agents, $date );
		usort(
			$agents,
			static function ( $a, $b ) use ( $load ) {
				$la = $load[ $a ] ?? 0;
				$lb = $load[ $b ] ?? 0;
				return $la === $lb ? $a <=> $b : $la <=> $lb;
			}
		);
		return (int) $agents[0];
	}

	/**
	 * Busy/closed background blocks for the admin calendar.
	 *
	 * @param int    $agent_id Agent (0 = business).
	 * @param string $from     Y-m-d.
	 * @param string $to       Y-m-d.
	 * @return array
	 */
	public static function unavailable_blocks( $agent_id, $from, $to ) {
		$resolver = new ScheduleResolver( array( $agent_id ), $from, $to );
		$blocks   = array();
		for ( $date = $from; $date <= $to; $date = Dates::add_days( $date, 1 ) ) {
			$closed = Intervals::subtract( array( array( 0, 1440 ) ), $resolver->open_intervals( $agent_id, $date ) );
			foreach ( $closed as $interval ) {
				$blocks[] = array(
					'start'   => Dates::datetime( $date, $interval[0] ),
					'end'     => Dates::datetime( $date, $interval[1] ),
					'display' => 'background',
				);
			}
		}
		return $blocks;
	}
}
