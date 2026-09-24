<?php
/**
 * Holidays / closures table access.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Repositories;

use PointlyBooking\Support\Cache;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables; availability results are cached at the service layer.

/**
 * Holiday queries.
 */
final class HolidayRepository extends Repository {

	const TABLE = 'holidays';

	/**
	 * {@inheritDoc}
	 *
	 * @param array $row Raw row.
	 * @return array
	 */
	public static function hydrate( array $row ) {
		$row['id']                  = (int) $row['id'];
		$row['agent_id']            = isset( $row['agent_id'] ) ? (int) $row['agent_id'] : 0;
		$row['is_recurring']        = ( (int) ( $row['is_recurring'] ?? 0 ) || (int) ( $row['is_recurring_yearly'] ?? 0 ) ) ? 1 : 0;
		$row['is_recurring_yearly'] = $row['is_recurring'];
		$row['is_enabled']          = (int) ( $row['is_enabled'] ?? 1 );
		return $row;
	}

	/**
	 * Holidays for the admin list.
	 *
	 * @param int $year     Year filter (0 = all).
	 * @param int $agent_id Agent filter (0 = global only, -1 = all).
	 * @return array
	 */
	public static function list_for( $year = 0, $agent_id = -1 ) {
		$db = self::db();
		$w  = new Where();
		if ( $year > 0 ) {
			$w->add( '(start_date <= %s AND end_date >= %s) OR is_recurring = 1 OR is_recurring_yearly = 1', sprintf( '%04d-12-31', $year ), sprintf( '%04d-01-01', $year ) );
		}
		if ( 0 === $agent_id ) {
			$w->add( 'agent_id IS NULL OR agent_id = 0' );
		} elseif ( $agent_id > 0 ) {
			$w->add( 'agent_id IS NULL OR agent_id = 0 OR agent_id = %d', $agent_id );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- WHERE fragments are literals with placeholders.
		$rows = $db->get_results( $db->prepare( 'SELECT * FROM %i' . $w->sql() . ' ORDER BY start_date ASC', array_merge( array( self::table() ), $w->params() ) ), ARRAY_A );
		return array_map( array( __CLASS__, 'hydrate' ), (array) $rows );
	}

	/**
	 * Every enabled holiday (small table; availability evaluates recurrence in PHP).
	 *
	 * @return array
	 */
	public static function enabled() {
		return Cache::remember(
			'availability',
			'holidays:enabled',
			static function () {
				$db   = self::db();
				$rows = $db->get_results( $db->prepare( 'SELECT * FROM %i WHERE is_enabled = 1 OR is_enabled IS NULL', self::table() ), ARRAY_A );
				return array_map( array( __CLASS__, 'hydrate' ), (array) $rows );
			}
		);
	}

	/**
	 * Creates a holiday.
	 *
	 * @param array $data Values.
	 * @return int
	 */
	public static function create( array $data ) {
		$data['created_at'] = self::now();
		$data['updated_at'] = self::now();
		$id                 = self::insert( $data );
		Cache::bump( 'availability' );
		return $id;
	}

	/**
	 * Updates a holiday.
	 *
	 * @param int   $id   ID.
	 * @param array $data Values.
	 * @return bool
	 */
	public static function save( $id, array $data ) {
		$data['updated_at'] = self::now();
		$ok                 = self::update( $id, $data );
		Cache::bump( 'availability' );
		return $ok;
	}

	/**
	 * Deletes a holiday.
	 *
	 * @param int $id ID.
	 * @return bool
	 */
	public static function remove( $id ) {
		$ok = self::delete( $id );
		Cache::bump( 'availability' );
		return $ok;
	}

	/**
	 * Whether a holiday covers a date (handles yearly recurrence across year end).
	 *
	 * @param array  $holiday Holiday row.
	 * @param string $date    Y-m-d.
	 * @return bool
	 */
	public static function covers( array $holiday, $date ) {
		$start = (string) $holiday['start_date'];
		$end   = (string) $holiday['end_date'];
		if ( $date >= $start && $date <= $end ) {
			return true;
		}
		if ( empty( $holiday['is_recurring'] ) ) {
			return false;
		}
		$md       = substr( $date, 5 );
		$start_md = substr( $start, 5 );
		$end_md   = substr( $end, 5 );
		if ( $start_md <= $end_md ) {
			return $md >= $start_md && $md <= $end_md;
		}
		// Wraps over New Year (e.g. 12-24 → 01-02).
		return $md >= $start_md || $md <= $end_md;
	}
}
