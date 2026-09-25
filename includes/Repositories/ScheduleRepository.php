<?php
/**
 * Weekly schedules (global and per staff member), legacy working hours and dated breaks.
 *
 * Weekday numbering in this table is ISO: 1 = Monday … 7 = Sunday.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Repositories;

use PointlyBooking\Database\Tables;
use PointlyBooking\Support\Cache;
use PointlyBooking\Support\Dates;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables; availability results are cached at the service layer.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Queries are assembled from literal fragments with placeholders and passed through $wpdb->prepare().

/**
 * Schedule storage.
 */
final class ScheduleRepository extends Repository {

	const TABLE = 'schedules';

	/**
	 * Normalises a breaks list to [ ['start'=>'HH:MM','end'=>'HH:MM'], … ].
	 *
	 * @param mixed $breaks Raw breaks.
	 * @return array
	 */
	public static function clean_breaks( $breaks ) {
		if ( is_string( $breaks ) ) {
			$breaks = self::json( $breaks );
		}
		if ( ! is_array( $breaks ) ) {
			return array();
		}
		$out = array();
		foreach ( $breaks as $break ) {
			if ( ! is_array( $break ) ) {
				continue;
			}
			$start = Dates::hm( $break['start'] ?? ( $break['start_time'] ?? '' ) );
			$end   = Dates::hm( $break['end'] ?? ( $break['end_time'] ?? '' ) );
			if ( '' === $start || '' === $end || Dates::to_minutes( $end ) <= Dates::to_minutes( $start ) ) {
				continue;
			}
			$out[] = array(
				'start' => $start,
				'end'   => $end,
			);
		}
		return $out;
	}

	/**
	 * Weekly schedule rows for an agent (0 = global business hours).
	 *
	 * @param int $agent_id Agent ID or 0.
	 * @return array<int,array> Weekday (1-7) => list of intervals.
	 */
	public static function weekly( $agent_id ) {
		$db = self::db();
		if ( $agent_id > 0 ) {
			$rows = $db->get_results( $db->prepare( 'SELECT * FROM %i WHERE agent_id = %d ORDER BY day_of_week ASC, start_time ASC', self::table(), $agent_id ), ARRAY_A );
		} else {
			$rows = $db->get_results( $db->prepare( 'SELECT * FROM %i WHERE agent_id IS NULL OR agent_id = 0 ORDER BY day_of_week ASC, start_time ASC', self::table() ), ARRAY_A );
		}
		return self::group_rows( (array) $rows );
	}

	/**
	 * Loads weekly schedules for many agents plus the global schedule in one query.
	 *
	 * @param int[] $agent_ids Agent IDs.
	 * @return array{global:array,agents:array<int,array>}
	 */
	public static function weekly_many( array $agent_ids ) {
		$db        = self::db();
		$agent_ids = array_values( array_filter( array_map( 'intval', $agent_ids ) ) );
		if ( $agent_ids ) {
			$rows = $db->get_results(
				$db->prepare(
					'SELECT * FROM %i WHERE agent_id IS NULL OR agent_id = 0 OR agent_id IN (' . self::int_placeholders( $agent_ids ) . ') ORDER BY day_of_week ASC, start_time ASC',
					array_merge( array( self::table() ), $agent_ids )
				),
				ARRAY_A
			);
		} else {
			$rows = $db->get_results( $db->prepare( 'SELECT * FROM %i WHERE agent_id IS NULL OR agent_id = 0 ORDER BY day_of_week ASC, start_time ASC', self::table() ), ARRAY_A );
		}

		$global = array();
		$agents = array();
		foreach ( (array) $rows as $row ) {
			$aid = (int) $row['agent_id'];
			if ( $aid > 0 ) {
				$agents[ $aid ][] = $row;
			} else {
				$global[] = $row;
			}
		}
		$out = array(
			'global' => self::group_rows( $global ),
			'agents' => array(),
		);
		foreach ( $agents as $aid => $list ) {
			$out['agents'][ $aid ] = self::group_rows( $list );
		}
		return $out;
	}

	/**
	 * Groups raw rows by weekday.
	 *
	 * @param array $rows Rows.
	 * @return array<int,array>
	 */
	private static function group_rows( array $rows ) {
		$out = array();
		foreach ( $rows as $row ) {
			$day   = (int) $row['day_of_week'];
			$start = Dates::hm( $row['start_time'] );
			$end   = Dates::hm( $row['end_time'] );
			if ( $day < 1 || $day > 7 || '' === $start || '' === $end ) {
				continue;
			}
			$out[ $day ][] = array(
				'start'      => $start,
				'end'        => $end,
				'breaks'     => self::clean_breaks( $row['breaks_json'] ?? '' ),
				'is_enabled' => 1 === (int) ( $row['is_enabled'] ?? 1 ),
			);
		}
		return $out;
	}

	/**
	 * Replaces a weekly schedule (agent 0 = global). Runs inside a transaction so a
	 * failed insert can never leave the schedule empty.
	 *
	 * @param int   $agent_id Agent ID or 0.
	 * @param array $week     Weekday (1-7) => [ ['start','end','breaks','is_enabled'], … ].
	 * @return bool
	 */
	public static function replace_weekly( $agent_id, array $week ) {
		$db    = self::db();
		$table = self::table();
		$now   = self::now();

		$db->query( 'START TRANSACTION' );
		if ( $agent_id > 0 ) {
			$deleted = $db->query( $db->prepare( 'DELETE FROM %i WHERE agent_id = %d', $table, $agent_id ) );
		} else {
			$deleted = $db->query( $db->prepare( 'DELETE FROM %i WHERE agent_id IS NULL OR agent_id = 0', $table ) );
		}
		$ok = false !== $deleted;

		foreach ( $week as $day => $intervals ) {
			$day = (int) $day;
			if ( $day < 1 || $day > 7 || ! is_array( $intervals ) ) {
				continue;
			}
			foreach ( $intervals as $interval ) {
				$start = Dates::hm( $interval['start'] ?? ( $interval['start_time'] ?? '' ) );
				$end   = Dates::hm( $interval['end'] ?? ( $interval['end_time'] ?? '' ) );
				if ( '' === $start || '' === $end || Dates::to_minutes( $end ) <= Dates::to_minutes( $start ) ) {
					continue;
				}
				$breaks = self::clean_breaks( $interval['breaks'] ?? array() );
				$data   = array(
					'day_of_week' => $day,
					'start_time'  => $start . ':00',
					'end_time'    => $end . ':00',
					'breaks_json' => $breaks ? wp_json_encode( $breaks ) : null,
					'is_enabled'  => ( ! isset( $interval['is_enabled'] ) || $interval['is_enabled'] ) ? 1 : 0,
					'created_at'  => $now,
					'updated_at'  => $now,
				);
				if ( $agent_id > 0 ) {
					$data = array( 'agent_id' => (int) $agent_id ) + $data;
				}
				$ok = $ok && false !== $db->insert( $table, $data, self::formats( $data ) );
			}
		}

		$db->query( $ok ? 'COMMIT' : 'ROLLBACK' );
		Cache::bump( 'availability' );
		return $ok;
	}

	/**
	 * Whether an agent has their own weekly schedule rows.
	 *
	 * @param int $agent_id Agent ID.
	 * @return bool
	 */
	public static function agent_has_schedule( $agent_id ) {
		$db = self::db();
		return (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i WHERE agent_id = %d', self::table(), absint( $agent_id ) ) ) > 0;
	}

	/**
	 * IDs of every agent that has their own weekly schedule rows, in one query.
	 *
	 * @return int[]
	 */
	public static function agents_with_schedule() {
		$db  = self::db();
		$ids = $db->get_col( $db->prepare( 'SELECT DISTINCT agent_id FROM %i WHERE agent_id > 0', self::table() ) );
		return array_map( 'intval', $ids );
	}

	/**
	 * Deletes an agent's weekly schedule, legacy hours and dated breaks.
	 *
	 * @param int $agent_id Agent ID.
	 * @return void
	 */
	public static function delete_agent_schedule( $agent_id ) {
		$db = self::db();
		$db->delete( self::table(), array( 'agent_id' => absint( $agent_id ) ), array( '%d' ) );
		if ( Tables::exists( 'agent_working_hours' ) ) {
			$db->delete( Tables::name( 'agent_working_hours' ), array( 'agent_id' => absint( $agent_id ) ), array( '%d' ) );
		}
		if ( Tables::exists( 'agent_breaks' ) ) {
			$db->delete( Tables::name( 'agent_breaks' ), array( 'agent_id' => absint( $agent_id ) ), array( '%d' ) );
		}
		Cache::bump( 'availability' );
	}

	/**
	 * Legacy per-agent working hours (2.x table) grouped by agent and weekday.
	 *
	 * @param int[] $agent_ids Agent IDs.
	 * @return array<int,array<int,array>>
	 */
	public static function legacy_hours( array $agent_ids ) {
		$agent_ids = array_values( array_filter( array_map( 'intval', $agent_ids ) ) );
		if ( ! $agent_ids || ! Tables::exists( 'agent_working_hours' ) ) {
			return array();
		}
		$db   = self::db();
		$rows = $db->get_results(
			$db->prepare(
				'SELECT agent_id, weekday, start_time, end_time FROM %i WHERE is_enabled = 1 AND agent_id IN (' . self::int_placeholders( $agent_ids ) . ') ORDER BY start_time ASC',
				array_merge( array( Tables::name( 'agent_working_hours' ) ), $agent_ids )
			),
			ARRAY_A
		);
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$start = Dates::hm( $row['start_time'] );
			$end   = Dates::hm( $row['end_time'] );
			if ( '' === $start || '' === $end ) {
				continue;
			}
			$out[ (int) $row['agent_id'] ][ (int) $row['weekday'] ][] = array(
				'start'      => $start,
				'end'        => $end,
				'breaks'     => array(),
				'is_enabled' => true,
			);
		}
		return $out;
	}

	/**
	 * Dated time-off blocks for agents between two dates.
	 *
	 * @param int[]  $agent_ids Agent IDs.
	 * @param string $from      Y-m-d.
	 * @param string $to        Y-m-d.
	 * @return array<int,array<string,array>> agent => date => [ ['start','end'] ].
	 */
	public static function dated_breaks( array $agent_ids, $from, $to ) {
		$agent_ids = array_values( array_filter( array_map( 'intval', $agent_ids ) ) );
		if ( ! $agent_ids || ! Tables::exists( 'agent_breaks' ) ) {
			return array();
		}
		$db   = self::db();
		$rows = $db->get_results(
			$db->prepare(
				'SELECT agent_id, break_date, start_time, end_time FROM %i WHERE break_date >= %s AND break_date <= %s AND agent_id IN (' . self::int_placeholders( $agent_ids ) . ')',
				array_merge( array( Tables::name( 'agent_breaks' ), $from, $to ), $agent_ids )
			),
			ARRAY_A
		);
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['agent_id'] ][ (string) $row['break_date'] ][] = array(
				'start' => Dates::hm( $row['start_time'] ),
				'end'   => Dates::hm( $row['end_time'] ),
			);
		}
		return $out;
	}

	/**
	 * Dated breaks of one agent (editor).
	 *
	 * @param int $agent_id Agent ID.
	 * @return array
	 */
	public static function agent_breaks( $agent_id ) {
		if ( ! Tables::exists( 'agent_breaks' ) ) {
			return array();
		}
		$db   = self::db();
		$rows = $db->get_results( $db->prepare( 'SELECT id, break_date, start_time, end_time, note FROM %i WHERE agent_id = %d ORDER BY break_date ASC, start_time ASC', Tables::name( 'agent_breaks' ), absint( $agent_id ) ), ARRAY_A );
		return array_map(
			static function ( $row ) {
				return array(
					'id'         => (int) $row['id'],
					'break_date' => (string) $row['break_date'],
					'start_time' => Dates::hm( $row['start_time'] ),
					'end_time'   => Dates::hm( $row['end_time'] ),
					'note'       => (string) ( $row['note'] ?? '' ),
				);
			},
			(array) $rows
		);
	}

	/**
	 * Replaces an agent's dated breaks.
	 *
	 * @param int   $agent_id Agent ID.
	 * @param array $breaks   [ ['break_date','start_time','end_time','note'], … ].
	 * @return void
	 */
	public static function replace_agent_breaks( $agent_id, array $breaks ) {
		$db    = self::db();
		$table = Tables::name( 'agent_breaks' );
		$db->delete( $table, array( 'agent_id' => absint( $agent_id ) ), array( '%d' ) );
		foreach ( $breaks as $break ) {
			$date  = \PointlyBooking\Support\Sanitize::date( $break['break_date'] ?? '' );
			$start = Dates::hm( $break['start_time'] ?? '' );
			$end   = Dates::hm( $break['end_time'] ?? '' );
			if ( '' === $date || '' === $start || '' === $end || Dates::to_minutes( $end ) <= Dates::to_minutes( $start ) ) {
				continue;
			}
			$db->insert(
				$table,
				array(
					'agent_id'   => absint( $agent_id ),
					'break_date' => $date,
					'start_time' => $start . ':00',
					'end_time'   => $end . ':00',
					'note'       => \PointlyBooking\Support\Sanitize::text( $break['note'] ?? '', 255 ),
				),
				array( '%d', '%s', '%s', '%s', '%s' )
			);
		}
		Cache::bump( 'availability' );
	}

	/**
	 * Keeps the legacy schedule_settings row in sync (slot interval + timezone).
	 *
	 * @param int $slot_interval Minutes.
	 * @return void
	 */
	public static function sync_settings_row( $slot_interval ) {
		if ( ! Tables::exists( 'schedule_settings' ) ) {
			return;
		}
		$db = self::db();
		$db->query(
			$db->prepare(
				'INSERT INTO %i (id, slot_interval_minutes, timezone, created_at, updated_at) VALUES (1, %d, %s, %s, %s) ON DUPLICATE KEY UPDATE slot_interval_minutes = VALUES(slot_interval_minutes), timezone = VALUES(timezone), updated_at = VALUES(updated_at)',
				Tables::name( 'schedule_settings' ),
				(int) $slot_interval,
				wp_timezone_string(),
				self::now(),
				self::now()
			)
		);
	}
}
