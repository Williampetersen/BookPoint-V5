<?php
/**
 * Customers table access.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Repositories;

use PointlyBooking\Database\Tables;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables; caching is applied at the service layer.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Queries are assembled from literal fragments with placeholders and passed through $wpdb->prepare().

/**
 * Customer queries.
 */
final class CustomerRepository extends Repository {

	const TABLE = 'customers';

	/**
	 * {@inheritDoc}
	 *
	 * @param array $row Raw row.
	 * @return array
	 */
	public static function hydrate( array $row ) {
		$row['id']            = (int) $row['id'];
		$row['wp_user_id']    = isset( $row['wp_user_id'] ) ? (int) $row['wp_user_id'] : 0;
		$row['name']          = trim( ( $row['first_name'] ?? '' ) . ' ' . ( $row['last_name'] ?? '' ) );
		$row['custom_fields'] = self::json( $row['custom_fields_json'] ?? '' );
		if ( isset( $row['bookings_count'] ) ) {
			$row['bookings_count'] = (int) $row['bookings_count'];
		}
		if ( isset( $row['total_spent'] ) ) {
			$row['total_spent'] = round( (float) $row['total_spent'], 2 );
		}
		return $row;
	}

	/**
	 * Most recent customer with an email address.
	 *
	 * @param string $email Email.
	 * @return array|null
	 */
	public static function find_by_email( $email ) {
		$email = sanitize_email( (string) $email );
		if ( '' === $email ) {
			return null;
		}
		$db  = self::db();
		$row = $db->get_row( $db->prepare( 'SELECT * FROM %i WHERE email = %s ORDER BY id DESC LIMIT 1', self::table(), $email ), ARRAY_A );
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Paginated search with booking counts (single aggregate join, no per-row subqueries).
	 *
	 * @param array $f search, orderby (created|name|email|bookings), order, page, per_page.
	 * @return array{items:array,total:int}
	 */
	public static function search( array $f ) {
		$db = self::db();
		$w  = new Where();
		if ( isset( $f['search'] ) && '' !== trim( (string) $f['search'] ) ) {
			$like = '%' . $db->esc_like( trim( (string) $f['search'] ) ) . '%';
			$w->add( "CONCAT_WS(' ', c.first_name, c.last_name) LIKE %s OR c.email LIKE %s OR c.phone LIKE %s", $like, $like, $like );
		}

		$orderable = array(
			'created'  => 'c.created_at',
			'id'       => 'c.id',
			'name'     => 'c.first_name',
			'email'    => 'c.email',
			'bookings' => 'bookings_count',
			'last'     => 'last_booking',
		);
		$orderby   = $orderable[ $f['orderby'] ?? 'created' ] ?? 'c.created_at';
		$order     = ( isset( $f['order'] ) && 'asc' === strtolower( (string) $f['order'] ) ) ? 'ASC' : 'DESC';
		$per_page  = max( 1, min( 200, (int) ( $f['per_page'] ?? 20 ) ) );
		$page      = max( 1, (int) ( $f['page'] ?? 1 ) );

		$total = (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i c' . $w->sql(), array_merge( array( self::table() ), $w->params() ) ) );

		$sql  = 'SELECT c.*, IFNULL(bs.bookings_count, 0) AS bookings_count, bs.last_booking, IFNULL(bs.total_spent, 0) AS total_spent
			FROM %i c
			LEFT JOIN (
				SELECT customer_id, COUNT(*) AS bookings_count, MAX(start_datetime) AS last_booking,
					SUM(CASE WHEN status NOT IN (%s, %s) THEN total_price ELSE 0 END) AS total_spent
				FROM %i GROUP BY customer_id
			) bs ON bs.customer_id = c.id' . $w->sql() . " ORDER BY {$orderby} {$order}, c.id {$order} LIMIT %d OFFSET %d";
		$rows = $db->get_results(
			$db->prepare(
				$sql,
				array_merge(
					array( self::table(), 'cancelled', 'failed_payment', Tables::name( 'bookings' ) ),
					$w->params(),
					array( $per_page, ( $page - 1 ) * $per_page )
				)
			),
			ARRAY_A
		);

		return array(
			'items' => array_map( array( __CLASS__, 'hydrate' ), (array) $rows ),
			'total' => $total,
		);
	}

	/**
	 * Every customer (CSV export).
	 *
	 * @return array
	 */
	public static function all_rows() {
		$db   = self::db();
		$rows = $db->get_results( $db->prepare( 'SELECT * FROM %i ORDER BY id DESC', self::table() ), ARRAY_A );
		return array_map( array( __CLASS__, 'hydrate' ), (array) $rows );
	}

	/**
	 * Replaces personal data with placeholders (GDPR erase while keeping booking history).
	 *
	 * @param int $id Customer ID.
	 * @return bool
	 */
	public static function anonymize( $id ) {
		return self::update(
			$id,
			array(
				'first_name'         => __( 'Deleted', 'pointly-booking' ),
				'last_name'          => __( 'Customer', 'pointly-booking' ),
				'email'              => 'deleted+' . absint( $id ) . '@example.invalid',
				'phone'              => '',
				'wp_user_id'         => null,
				'custom_fields_json' => null,
				'updated_at'         => self::now(),
			)
		);
	}

	/**
	 * New customers created in a period.
	 *
	 * @param string $from Local DATETIME.
	 * @param string $to   Local DATETIME.
	 * @return int
	 */
	public static function count_created( $from, $to ) {
		$db = self::db();
		return (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at <= %s', self::table(), $from, $to ) );
	}
}
