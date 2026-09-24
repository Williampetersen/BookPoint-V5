<?php
/**
 * Service categories table access.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Repositories;

use PointlyBooking\Support\Cache;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables; list results are cached via Support\Cache.

/**
 * Category queries.
 */
final class CategoryRepository extends Repository {

	const TABLE = 'categories';

	/**
	 * {@inheritDoc}
	 *
	 * @param array $row Raw row.
	 * @return array
	 */
	public static function hydrate( array $row ) {
		$row['id']          = (int) $row['id'];
		$row['image_id']    = (int) ( $row['image_id'] ?? 0 );
		$row['sort_order']  = (int) ( $row['sort_order'] ?? 0 );
		$row['is_active']   = (int) ( $row['is_active'] ?? 1 );
		$row['description'] = (string) ( $row['description'] ?? '' );
		return $row;
	}

	/**
	 * All categories, cached.
	 *
	 * @param bool $active_only Only active ones.
	 * @return array
	 */
	public static function all( $active_only = false ) {
		return Cache::remember(
			'catalog',
			'categories:' . ( $active_only ? 'active' : 'all' ),
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
	 * Creates a category.
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
	 * Updates a category.
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
	 * Deletes a category and its service links.
	 *
	 * @param int $id ID.
	 * @return bool
	 */
	public static function remove( $id ) {
		Relations::purge( 'service_categories', 'category_id', $id );
		$ok = self::delete( $id );
		Cache::bump( 'catalog' );
		return $ok;
	}
}
