<?php
/**
 * Many-to-many pivot table helpers.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Repositories;

use PointlyBooking\Database\Tables;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin pivot tables.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Queries are assembled from literal fragments with placeholders and passed through $wpdb->prepare().

/**
 * Canonical pivot tables:
 *  - service_categories (service_id, category_id)
 *  - extra_services     (extra_id, service_id)
 *  - agent_services     (agent_id, service_id)
 */
final class Relations {

	/**
	 * Known pivots: name => [owner column, other column].
	 *
	 * @var array<string,string[]>
	 */
	const PIVOTS = array(
		'service_categories' => array( 'service_id', 'category_id' ),
		'extra_services'     => array( 'extra_id', 'service_id' ),
		'agent_services'     => array( 'agent_id', 'service_id' ),
	);

	/**
	 * IDs related to an owner.
	 *
	 * @param string $pivot    Pivot name.
	 * @param string $by       Column to filter on.
	 * @param int    $id       Value.
	 * @return int[]
	 */
	public static function ids( $pivot, $by, $id ) {
		global $wpdb;
		list( $a, $b ) = self::PIVOTS[ $pivot ];
		$select        = $by === $a ? $b : $a;
		$ids           = $wpdb->get_col( $wpdb->prepare( 'SELECT %i FROM %i WHERE %i = %d', $select, Tables::name( $pivot ), $by, absint( $id ) ) );
		return array_values( array_unique( array_map( 'intval', (array) $ids ) ) );
	}

	/**
	 * Map owner ID => related IDs for many owners at once (avoids N+1).
	 *
	 * @param string $pivot Pivot name.
	 * @param string $by    Column the map is keyed by.
	 * @param int[]  $ids   Owner IDs (empty = all rows).
	 * @return array<int,int[]>
	 */
	public static function map( $pivot, $by, array $ids = array() ) {
		global $wpdb;
		list( $a, $b ) = self::PIVOTS[ $pivot ];
		$other         = $by === $a ? $b : $a;
		$ids           = array_values( array_filter( array_map( 'intval', $ids ) ) );

		if ( $ids ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT %i AS k, %i AS v FROM %i WHERE %i IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')',
					array_merge( array( $by, $other, Tables::name( $pivot ), $by ), $ids )
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT %i AS k, %i AS v FROM %i', $by, $other, Tables::name( $pivot ) ), ARRAY_A );
		}

		$map = array();
		foreach ( (array) $rows as $row ) {
			$map[ (int) $row['k'] ][] = (int) $row['v'];
		}
		return $map;
	}

	/**
	 * Replaces the related IDs of an owner.
	 *
	 * @param string $pivot Pivot name.
	 * @param string $by    Owner column.
	 * @param int    $id    Owner ID.
	 * @param int[]  $ids   Related IDs.
	 * @return void
	 */
	public static function set( $pivot, $by, $id, array $ids ) {
		global $wpdb;
		list( $a, $b ) = self::PIVOTS[ $pivot ];
		$other         = $by === $a ? $b : $a;
		$table         = Tables::name( $pivot );
		$id            = absint( $id );
		$ids           = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

		$wpdb->delete( $table, array( $by => $id ), array( '%d' ) );
		foreach ( $ids as $other_id ) {
			$wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (%i, %i) VALUES (%d, %d)', $table, $by, $other, $id, $other_id ) );
		}
	}

	/**
	 * Removes every row that references an ID in a column.
	 *
	 * @param string $pivot  Pivot name.
	 * @param string $column Column.
	 * @param int    $id     Value.
	 * @return void
	 */
	public static function purge( $pivot, $column, $id ) {
		global $wpdb;
		$wpdb->delete( Tables::name( $pivot ), array( $column => absint( $id ) ), array( '%d' ) );
	}
}
