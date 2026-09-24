<?php
/**
 * Audit log table access.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Repositories;

use PointlyBooking\Database\Tables;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Queries are assembled from literal fragments with placeholders and passed through $wpdb->prepare().

/**
 * Audit queries.
 */
final class AuditRepository extends Repository {

	const TABLE = 'audit_log';

	/**
	 * {@inheritDoc}
	 *
	 * @param array $row Raw row.
	 * @return array
	 */
	public static function hydrate( array $row ) {
		$row['id']               = (int) $row['id'];
		$row['actor_wp_user_id'] = (int) ( $row['actor_wp_user_id'] ?? 0 );
		$row['booking_id']       = (int) ( $row['booking_id'] ?? 0 );
		$row['customer_id']      = (int) ( $row['customer_id'] ?? 0 );
		$row['meta_data']        = self::json( $row['meta'] ?? '' );
		return $row;
	}

	/**
	 * Builds filters.
	 *
	 * @param array $f Filters.
	 * @return Where
	 */
	private static function where( array $f ) {
		$db = self::db();
		$w  = new Where();
		if ( ! empty( $f['event'] ) ) {
			$w->add( 'l.event = %s', (string) $f['event'] );
		}
		if ( ! empty( $f['actor_type'] ) ) {
			$w->add( 'l.actor_type = %s', (string) $f['actor_type'] );
		}
		if ( ! empty( $f['actor_wp_user_id'] ) ) {
			$w->add( 'l.actor_wp_user_id = %d', (int) $f['actor_wp_user_id'] );
		}
		if ( ! empty( $f['booking_id'] ) ) {
			$w->add( 'l.booking_id = %d', (int) $f['booking_id'] );
		}
		if ( ! empty( $f['customer_id'] ) ) {
			$w->add( 'l.customer_id = %d', (int) $f['customer_id'] );
		}
		if ( ! empty( $f['date_from'] ) ) {
			$w->add( 'l.created_at >= %s', $f['date_from'] . ' 00:00:00' );
		}
		if ( ! empty( $f['date_to'] ) ) {
			$w->add( 'l.created_at <= %s', $f['date_to'] . ' 23:59:59' );
		}
		if ( isset( $f['search'] ) && '' !== trim( (string) $f['search'] ) ) {
			$like = '%' . $db->esc_like( trim( (string) $f['search'] ) ) . '%';
			$w->add( 'l.event LIKE %s OR l.actor_ip LIKE %s OR l.meta LIKE %s OR u.display_name LIKE %s OR c.email LIKE %s', $like, $like, $like, $like, $like );
		}
		return $w;
	}

	/**
	 * Paginated list with actor display names.
	 *
	 * @param array $f        Filters.
	 * @param int   $page     Page.
	 * @param int   $per_page Page size.
	 * @return array{items:array,total:int}
	 */
	public static function search( array $f, $page = 1, $per_page = 50 ) {
		global $wpdb;
		$db       = self::db();
		$w        = self::where( $f );
		$from     = ' FROM %i l LEFT JOIN %i u ON u.ID = l.actor_wp_user_id LEFT JOIN %i c ON c.id = l.customer_id';
		$idents   = array( self::table(), $wpdb->users, Tables::name( 'customers' ) );
		$per_page = max( 1, min( 500, (int) $per_page ) );
		$page     = max( 1, (int) $page );

		$total = (int) $db->get_var( $db->prepare( 'SELECT COUNT(*)' . $from . $w->sql(), array_merge( $idents, $w->params() ) ) );
		$rows  = $db->get_results(
			$db->prepare(
				'SELECT l.*, u.display_name AS actor_name, TRIM(CONCAT(IFNULL(c.first_name, \'\'), \' \', IFNULL(c.last_name, \'\'))) AS customer_name, c.email AS customer_email' . $from . $w->sql() . ' ORDER BY l.id DESC LIMIT %d OFFSET %d',
				array_merge( $idents, $w->params(), array( $per_page, ( $page - 1 ) * $per_page ) )
			),
			ARRAY_A
		);
		return array(
			'items' => array_map( array( __CLASS__, 'hydrate' ), (array) $rows ),
			'total' => $total,
		);
	}

	/**
	 * Distinct event names.
	 *
	 * @return string[]
	 */
	public static function events() {
		$db = self::db();
		return array_map( 'strval', (array) $db->get_col( $db->prepare( 'SELECT DISTINCT event FROM %i ORDER BY event ASC', self::table() ) ) );
	}

	/**
	 * Removes every entry.
	 *
	 * @return bool
	 */
	public static function clear() {
		$db = self::db();
		return false !== $db->query( $db->prepare( 'DELETE FROM %i', self::table() ) );
	}

	/**
	 * Adds an entry.
	 *
	 * @param array $data Columns.
	 * @return void
	 */
	public static function add( array $data ) {
		self::insert( $data );
	}
}
