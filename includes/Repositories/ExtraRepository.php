<?php
/**
 * Service extras table access.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Repositories;

use PointlyBooking\Support\Cache;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables; list results are cached via Support\Cache.

/**
 * Extras queries. Service links live in the `extra_services` pivot; the legacy
 * `service_extras.service_id` column is kept in sync with the first linked service.
 */
final class ExtraRepository extends Repository {

	const TABLE = 'service_extras';

	/**
	 * {@inheritDoc}
	 *
	 * @param array $row Raw row.
	 * @return array
	 */
	public static function hydrate( array $row ) {
		$row['id']           = (int) $row['id'];
		$row['price']        = round( (float) ( $row['price'] ?? 0 ), 2 );
		$row['duration_min'] = isset( $row['duration_min'] ) && '' !== $row['duration_min'] ? (int) $row['duration_min'] : 0;
		$row['image_id']     = (int) ( $row['image_id'] ?? 0 );
		$row['sort_order']   = (int) ( $row['sort_order'] ?? 0 );
		$row['is_active']    = (int) ( $row['is_active'] ?? 1 );
		$row['service_id']   = (int) ( $row['service_id'] ?? 0 );
		$row['description']  = (string) ( $row['description'] ?? '' );
		return $row;
	}

	/**
	 * All extras, cached.
	 *
	 * @param bool $active_only Only active ones.
	 * @return array
	 */
	public static function all( $active_only = false ) {
		return Cache::remember(
			'catalog',
			'extras:' . ( $active_only ? 'active' : 'all' ),
			static function () use ( $active_only ) {
				$db = self::db();
				if ( $active_only ) {
					$rows = $db->get_results( $db->prepare( 'SELECT * FROM %i WHERE is_active = 1 ORDER BY sort_order ASC, name ASC', self::table() ), ARRAY_A );
				} else {
					$rows = $db->get_results( $db->prepare( 'SELECT * FROM %i ORDER BY sort_order ASC, name ASC', self::table() ), ARRAY_A );
				}
				return array_map( array( __CLASS__, 'hydrate' ), (array) $rows );
			}
		);
	}

	/**
	 * Active extras available for a service.
	 *
	 * @param int $service_id Service ID.
	 * @return array
	 */
	public static function for_service( $service_id ) {
		$linked = Relations::map( 'extra_services', 'extra_id' );
		$out    = array();
		foreach ( self::all( true ) as $extra ) {
			$services = $linked[ $extra['id'] ] ?? array();
			if ( in_array( (int) $service_id, $services, true ) || ( ! $services && (int) $extra['service_id'] === (int) $service_id ) ) {
				$out[] = $extra;
			}
		}
		return $out;
	}

	/**
	 * Creates an extra.
	 *
	 * @param array $data Values.
	 * @return int
	 */
	public static function create( array $data ) {
		$data = array_merge(
			array(
				'service_id' => 0,
				'created_at' => self::now(),
			),
			$data
		);
		$data['updated_at'] = self::now();
		$id                 = self::insert( $data );
		Cache::bump( 'catalog' );
		return $id;
	}

	/**
	 * Updates an extra.
	 *
	 * @param int   $id   ID.
	 * @param array $data Values.
	 * @return bool
	 */
	public static function save( $id, array $data ) {
		$data['updated_at'] = self::now();
		$ok                 = self::update( $id, $data );
		Cache::bump( 'catalog' );
		return $ok;
	}

	/**
	 * Sets the services an extra is offered with (and mirrors the legacy column).
	 *
	 * @param int   $id          Extra ID.
	 * @param int[] $service_ids Service IDs.
	 * @return void
	 */
	public static function set_services( $id, array $service_ids ) {
		Relations::set( 'extra_services', 'extra_id', $id, $service_ids );
		$first = $service_ids ? (int) reset( $service_ids ) : 0;
		self::update( $id, array( 'service_id' => $first ) );
		Cache::bump( 'catalog' );
	}

	/**
	 * Deletes an extra and its links.
	 *
	 * @param int $id ID.
	 * @return bool
	 */
	public static function remove( $id ) {
		Relations::purge( 'extra_services', 'extra_id', $id );
		$ok = self::delete( $id );
		Cache::bump( 'catalog' );
		return $ok;
	}
}
