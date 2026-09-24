<?php
/**
 * Services table access.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Repositories;

use PointlyBooking\Database\Tables;
use PointlyBooking\Support\Cache;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables; list results are cached via Support\Cache.

/**
 * Service queries.
 */
final class ServiceRepository extends Repository {

	const TABLE = 'services';

	/**
	 * {@inheritDoc}
	 *
	 * @param array $row Raw row.
	 * @return array
	 */
	public static function hydrate( array $row ) {
		$row['id']               = (int) $row['id'];
		$row['duration_minutes'] = max( 5, (int) ( $row['duration_minutes'] ?? 60 ) );
		$row['price_cents']      = (int) ( $row['price_cents'] ?? 0 );
		$row['price']            = $row['price_cents'] / 100;
		$row['is_active']        = (int) ( $row['is_active'] ?? 1 );
		$row['capacity']         = max( 1, (int) ( $row['capacity'] ?? 1 ) );
		$row['sort_order']       = (int) ( $row['sort_order'] ?? 0 );
		$row['image_id']         = (int) ( $row['image_id'] ?? 0 );
		$row['category_id']      = (int) ( $row['category_id'] ?? 0 );

		// Two historical buffer column pairs exist; the *_minutes pair is canonical.
		$before                       = (int) ( $row['buffer_before_minutes'] ?? 0 );
		$after                        = (int) ( $row['buffer_after_minutes'] ?? 0 );
		$row['buffer_before_minutes'] = $before > 0 ? $before : (int) ( $row['buffer_before'] ?? 0 );
		$row['buffer_after_minutes']  = $after > 0 ? $after : (int) ( $row['buffer_after'] ?? 0 );
		$row['use_global_schedule']   = (int) ( $row['use_global_schedule'] ?? 1 );
		return $row;
	}

	/**
	 * All services, cached.
	 *
	 * @param bool $active_only Only active services.
	 * @return array
	 */
	public static function all( $active_only = false ) {
		return Cache::remember(
			'catalog',
			'services:' . ( $active_only ? 'active' : 'all' ),
			static function () use ( $active_only ) {
				$db = self::db();
				if ( $active_only ) {
					$rows = $db->get_results( $db->prepare( 'SELECT * FROM %i WHERE is_active = 1 ORDER BY sort_order ASC, name ASC, id ASC', self::table() ), ARRAY_A );
				} else {
					$rows = $db->get_results( $db->prepare( 'SELECT * FROM %i ORDER BY sort_order ASC, name ASC, id ASC', self::table() ), ARRAY_A );
				}
				return array_map( array( __CLASS__, 'hydrate' ), (array) $rows );
			}
		);
	}

	/**
	 * Services keyed by ID.
	 *
	 * @return array<int,array>
	 */
	public static function by_id() {
		$out = array();
		foreach ( self::all() as $row ) {
			$out[ $row['id'] ] = $row;
		}
		return $out;
	}

	/**
	 * Creates a service.
	 *
	 * @param array $data Column values.
	 * @return int
	 */
	public static function create( array $data ) {
		$now  = self::now();
		$data = array_merge(
			array(
				'currency'   => \PointlyBooking\Support\Money::currency(),
				'is_active'  => 1,
				'created_at' => $now,
				'updated_at' => $now,
			),
			$data
		);
		$id   = self::insert( self::mirror_buffers( $data ) );
		Cache::bump( 'catalog' );
		Cache::bump( 'availability' );
		return $id;
	}

	/**
	 * Updates a service.
	 *
	 * @param int   $id   Service ID.
	 * @param array $data Column values.
	 * @return bool
	 */
	public static function save( $id, array $data ) {
		$data['updated_at'] = self::now();
		$ok                 = self::update( $id, self::mirror_buffers( $data ) );
		Cache::bump( 'catalog' );
		Cache::bump( 'availability' );
		return $ok;
	}

	/**
	 * Keeps the legacy buffer columns in sync with the canonical ones.
	 *
	 * @param array $data Column values.
	 * @return array
	 */
	private static function mirror_buffers( array $data ) {
		if ( array_key_exists( 'buffer_before_minutes', $data ) ) {
			$data['buffer_before'] = (int) $data['buffer_before_minutes'];
		}
		if ( array_key_exists( 'buffer_after_minutes', $data ) ) {
			$data['buffer_after'] = (int) $data['buffer_after_minutes'];
		}
		return $data;
	}

	/**
	 * Deletes a service and its relations (bookings keep their service_id for history).
	 *
	 * @param int $id Service ID.
	 * @return bool
	 */
	public static function remove( $id ) {
		Relations::purge( 'service_categories', 'service_id', $id );
		Relations::purge( 'agent_services', 'service_id', $id );
		Relations::purge( 'extra_services', 'service_id', $id );
		$ok = self::delete( $id );
		Cache::bump( 'catalog' );
		Cache::bump( 'availability' );
		return $ok;
	}

	/**
	 * Updates sort order for many services.
	 *
	 * @param int[] $ids Ordered IDs.
	 * @return void
	 */
	public static function reorder( array $ids ) {
		$position = 0;
		foreach ( $ids as $id ) {
			self::update( (int) $id, array( 'sort_order' => ++$position ) );
		}
		Cache::bump( 'catalog' );
	}

	/**
	 * Number of services.
	 *
	 * @return int
	 */
	public static function count_all() {
		$db = self::db();
		return (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i', self::table() ) );
	}

	/**
	 * Booking counts per service (for list screens).
	 *
	 * @return array<int,int>
	 */
	public static function booking_counts() {
		$db   = self::db();
		$rows = $db->get_results( $db->prepare( 'SELECT service_id, COUNT(*) AS c FROM %i GROUP BY service_id', Tables::name( 'bookings' ) ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['service_id'] ] = (int) $row['c'];
		}
		return $out;
	}
}
