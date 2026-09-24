<?php
/**
 * Staff (agents) table access.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Repositories;

use PointlyBooking\Support\Cache;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables; list results are cached via Support\Cache.

/**
 * Agent queries.
 */
final class AgentRepository extends Repository {

	const TABLE = 'agents';

	/**
	 * {@inheritDoc}
	 *
	 * @param array $row Raw row.
	 * @return array
	 */
	public static function hydrate( array $row ) {
		$row['id']        = (int) $row['id'];
		$row['is_active'] = (int) ( $row['is_active'] ?? 1 );
		$row['image_id']  = (int) ( $row['image_id'] ?? 0 );
		$name             = trim( ( $row['first_name'] ?? '' ) . ' ' . ( $row['last_name'] ?? '' ) );
		/* translators: %d: staff member ID */
		$row['name'] = '' !== $name ? $name : sprintf( __( 'Staff #%d', 'pointly-booking' ), $row['id'] );
		return $row;
	}

	/**
	 * All agents, cached.
	 *
	 * @param bool $active_only Only active ones.
	 * @return array
	 */
	public static function all( $active_only = false ) {
		return Cache::remember(
			'catalog',
			'agents:' . ( $active_only ? 'active' : 'all' ),
			static function () use ( $active_only ) {
				$db = self::db();
				if ( $active_only ) {
					$rows = $db->get_results( $db->prepare( 'SELECT * FROM %i WHERE is_active = 1 ORDER BY first_name ASC, last_name ASC, id ASC', self::table() ), ARRAY_A );
				} else {
					$rows = $db->get_results( $db->prepare( 'SELECT * FROM %i ORDER BY first_name ASC, last_name ASC, id ASC', self::table() ), ARRAY_A );
				}
				return array_map( array( __CLASS__, 'hydrate' ), (array) $rows );
			}
		);
	}

	/**
	 * Agents keyed by ID.
	 *
	 * @param bool $active_only Only active ones.
	 * @return array<int,array>
	 */
	public static function by_id( $active_only = false ) {
		$out = array();
		foreach ( self::all( $active_only ) as $row ) {
			$out[ $row['id'] ] = $row;
		}
		return $out;
	}

	/**
	 * Active agents that can perform a service. When no agent is linked to the
	 * service, every active agent qualifies (behaviour kept from 2.x).
	 *
	 * @param int $service_id Service ID.
	 * @return array
	 */
	public static function for_service( $service_id ) {
		$active = self::all( true );
		$linked = Relations::ids( 'agent_services', 'service_id', $service_id );
		if ( ! $linked ) {
			return $active;
		}
		return array_values(
			array_filter(
				$active,
				static function ( $agent ) use ( $linked ) {
					return in_array( $agent['id'], $linked, true );
				}
			)
		);
	}

	/**
	 * Creates an agent.
	 *
	 * @param array $data Values.
	 * @return int
	 */
	public static function create( array $data ) {
		$data['created_at'] = self::now();
		$data['updated_at'] = self::now();
		$id                 = self::insert( $data );
		Cache::bump( 'catalog' );
		Cache::bump( 'availability' );
		return $id;
	}

	/**
	 * Updates an agent.
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
	 * Deletes an agent and every relation (bookings keep the agent_id for history).
	 *
	 * @param int $id ID.
	 * @return bool
	 */
	public static function remove( $id ) {
		Relations::purge( 'agent_services', 'agent_id', $id );
		ScheduleRepository::delete_agent_schedule( $id );
		LocationRepository::remove_agent( $id );
		$ok = self::delete( $id );
		Cache::bump( 'catalog' );
		Cache::bump( 'availability' );
		return $ok;
	}

	/**
	 * Number of agents.
	 *
	 * @return int
	 */
	public static function count_all() {
		$db = self::db();
		return (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i', self::table() ) );
	}
}
