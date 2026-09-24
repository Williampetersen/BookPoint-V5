<?php
/**
 * Locations, location categories and location ↔ staff assignments.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Repositories;

use PointlyBooking\Database\Tables;
use PointlyBooking\Support\Cache;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables; list results are cached via Support\Cache.

/**
 * Location queries.
 */
final class LocationRepository extends Repository {

	const TABLE = 'locations';

	/**
	 * {@inheritDoc}
	 *
	 * @param array $row Raw row.
	 * @return array
	 */
	public static function hydrate( array $row ) {
		$row['id']                  = (int) $row['id'];
		$row['category_id']         = (int) ( $row['category_id'] ?? 0 );
		$row['image_id']            = (int) ( $row['image_id'] ?? 0 );
		$row['use_custom_schedule'] = (int) ( $row['use_custom_schedule'] ?? 0 );
		$row['status']              = 'inactive' === ( $row['status'] ?? 'active' ) ? 'inactive' : 'active';
		$row['is_active']           = 'active' === $row['status'] ? 1 : 0;
		$row['schedule']            = self::json( $row['schedule_json'] ?? '' );
		return $row;
	}

	/**
	 * All locations, cached.
	 *
	 * @param bool $active_only Only active ones.
	 * @return array
	 */
	public static function all( $active_only = false ) {
		if ( ! Tables::exists( 'locations' ) ) {
			return array();
		}
		return Cache::remember(
			'catalog',
			'locations:' . ( $active_only ? 'active' : 'all' ),
			static function () use ( $active_only ) {
				$db = self::db();
				if ( $active_only ) {
					$rows = $db->get_results( $db->prepare( 'SELECT * FROM %i WHERE status = %s ORDER BY name ASC', self::table(), 'active' ), ARRAY_A );
				} else {
					$rows = $db->get_results( $db->prepare( 'SELECT * FROM %i ORDER BY name ASC', self::table() ), ARRAY_A );
				}
				return array_map( array( __CLASS__, 'hydrate' ), (array) $rows );
			}
		);
	}

	/**
	 * Creates a location.
	 *
	 * @param array $data Values.
	 * @return int
	 */
	public static function create( array $data ) {
		$data['created_at'] = self::now();
		$data['updated_at'] = self::now();
		$id                 = self::insert( $data );
		Cache::bump( 'catalog' );
		return $id;
	}

	/**
	 * Updates a location.
	 *
	 * @param int   $id   ID.
	 * @param array $data Values.
	 * @return bool
	 */
	public static function save( $id, array $data ) {
		$data['updated_at'] = self::now();
		$ok                 = self::update( $id, $data );
		Cache::bump( 'catalog' );
		Cache::bump( 'availability' );
		return $ok;
	}

	/**
	 * Deletes a location and its staff assignments.
	 *
	 * @param int $id ID.
	 * @return bool
	 */
	public static function remove( $id ) {
		$db = self::db();
		$db->delete( Tables::name( 'location_agents' ), array( 'location_id' => absint( $id ) ), array( '%d' ) );
		$ok = self::delete( $id );
		Cache::bump( 'catalog' );
		return $ok;
	}

	/**
	 * Staff assignments of a location: [ ['agent_id'=>1,'services'=>[..]|null], … ].
	 *
	 * @param int $location_id Location ID.
	 * @return array
	 */
	public static function agents( $location_id ) {
		$db   = self::db();
		$rows = $db->get_results( $db->prepare( 'SELECT agent_id, services_json FROM %i WHERE location_id = %d ORDER BY id ASC', Tables::name( 'location_agents' ), absint( $location_id ) ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$services = self::json( $row['services_json'] ?? '' );
			$out[]    = array(
				'agent_id' => (int) $row['agent_id'],
				'services' => $services ? array_values( array_map( 'intval', $services ) ) : array(),
			);
		}
		return $out;
	}

	/**
	 * Every assignment grouped by location (avoids per-location queries).
	 *
	 * @return array<int,array>
	 */
	public static function all_assignments() {
		if ( ! Tables::exists( 'location_agents' ) ) {
			return array();
		}
		return Cache::remember(
			'catalog',
			'location_agents',
			static function () {
				$db   = self::db();
				$rows = $db->get_results( $db->prepare( 'SELECT location_id, agent_id, services_json FROM %i', Tables::name( 'location_agents' ) ), ARRAY_A );
				$out  = array();
				foreach ( (array) $rows as $row ) {
					$services                              = self::json( $row['services_json'] ?? '' );
					$out[ (int) $row['location_id'] ][] = array(
						'agent_id' => (int) $row['agent_id'],
						'services' => $services ? array_values( array_map( 'intval', $services ) ) : array(),
					);
				}
				return $out;
			}
		);
	}

	/**
	 * Replaces the staff assignments of a location.
	 *
	 * @param int   $location_id Location ID.
	 * @param array $assignments [ ['agent_id'=>1,'services'=>[..]], … ].
	 * @return void
	 */
	public static function set_agents( $location_id, array $assignments ) {
		$db    = self::db();
		$table = Tables::name( 'location_agents' );
		$db->delete( $table, array( 'location_id' => absint( $location_id ) ), array( '%d' ) );
		$now = self::now();
		foreach ( $assignments as $assignment ) {
			$agent_id = absint( $assignment['agent_id'] ?? 0 );
			if ( ! $agent_id ) {
				continue;
			}
			$services = isset( $assignment['services'] ) && is_array( $assignment['services'] ) ? array_values( array_unique( array_filter( array_map( 'absint', $assignment['services'] ) ) ) ) : array();
			$db->insert(
				$table,
				array(
					'location_id'   => absint( $location_id ),
					'agent_id'      => $agent_id,
					'services_json' => $services ? wp_json_encode( $services ) : null,
					'created_at'    => $now,
					'updated_at'    => $now,
				),
				array( '%d', '%d', '%s', '%s', '%s' )
			);
		}
		Cache::bump( 'catalog' );
		Cache::bump( 'availability' );
	}

	/**
	 * Removes an agent from every location.
	 *
	 * @param int $agent_id Agent ID.
	 * @return void
	 */
	public static function remove_agent( $agent_id ) {
		if ( ! Tables::exists( 'location_agents' ) ) {
			return;
		}
		$db = self::db();
		$db->delete( Tables::name( 'location_agents' ), array( 'agent_id' => absint( $agent_id ) ), array( '%d' ) );
	}

	/**
	 * Location categories.
	 *
	 * @return array
	 */
	public static function categories() {
		if ( ! Tables::exists( 'location_categories' ) ) {
			return array();
		}
		$db   = self::db();
		$rows = $db->get_results( $db->prepare( 'SELECT * FROM %i ORDER BY name ASC', Tables::name( 'location_categories' ) ), ARRAY_A );
		return array_map(
			static function ( $row ) {
				$row['id']        = (int) $row['id'];
				$row['image_id']  = (int) ( $row['image_id'] ?? 0 );
				$row['image_url'] = self::image_url( $row['image_id'], 'thumbnail' );
				return $row;
			},
			(array) $rows
		);
	}

	/**
	 * Creates or updates a location category.
	 *
	 * @param int   $id   ID (0 creates).
	 * @param array $data Values.
	 * @return int
	 */
	public static function save_category( $id, array $data ) {
		$db                 = self::db();
		$table              = Tables::name( 'location_categories' );
		$data['updated_at'] = self::now();
		if ( $id ) {
			$db->update( $table, $data, array( 'id' => absint( $id ) ), null, array( '%d' ) );
		} else {
			$data['created_at'] = self::now();
			$db->insert( $table, $data );
			$id = (int) $db->insert_id;
		}
		Cache::bump( 'catalog' );
		return (int) $id;
	}

	/**
	 * Deletes a location category and detaches its locations.
	 *
	 * @param int $id ID.
	 * @return void
	 */
	public static function delete_category( $id ) {
		$db = self::db();
		$db->update( self::table(), array( 'category_id' => null ), array( 'category_id' => absint( $id ) ), null, array( '%d' ) );
		$db->delete( Tables::name( 'location_categories' ), array( 'id' => absint( $id ) ), array( '%d' ) );
		Cache::bump( 'catalog' );
	}
}
