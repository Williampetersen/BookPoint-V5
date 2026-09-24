<?php
/**
 * Bookings table access.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Repositories;

use PointlyBooking\Database\Tables;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables; caching is applied at the service layer.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Queries are assembled from literal fragments with placeholders and passed through $wpdb->prepare().

/**
 * Booking queries.
 */
final class BookingRepository extends Repository {

	const TABLE = 'bookings';

	/**
	 * Every status the plugin knows.
	 *
	 * @var string[]
	 */
	const STATUSES = array( 'pending', 'confirmed', 'completed', 'cancelled', 'pending_payment', 'failed_payment' );

	/**
	 * Statuses that do not occupy time.
	 *
	 * @var string[]
	 */
	const FREEING_STATUSES = array( 'cancelled', 'failed_payment' );

	/**
	 * {@inheritDoc}
	 *
	 * @param array $row Raw row.
	 * @return array
	 */
	public static function hydrate( array $row ) {
		foreach ( array( 'id', 'service_id', 'customer_id', 'agent_id', 'category_id', 'location_id' ) as $key ) {
			if ( array_key_exists( $key, $row ) ) {
				$row[ $key ] = null === $row[ $key ] ? null : (int) $row[ $key ];
			}
		}
		foreach ( array( 'discount_total', 'total_price', 'payment_amount' ) as $key ) {
			if ( array_key_exists( $key, $row ) && null !== $row[ $key ] ) {
				$row[ $key ] = (float) $row[ $key ];
			}
		}
		return $row;
	}

	/**
	 * Finds a booking by its customer manage key (64 hex; 40 hex issued by 2.x demos/rotation).
	 *
	 * @param string $key Manage key.
	 * @return array|null
	 */
	public static function find_by_key( $key ) {
		$key = strtolower( preg_replace( '/[^a-fA-F0-9]/', '', (string) $key ) );
		if ( 64 !== strlen( $key ) && 40 !== strlen( $key ) ) {
			return null;
		}
		$db  = self::db();
		$row = $db->get_row( $db->prepare( 'SELECT * FROM %i WHERE manage_key = %s LIMIT 1', self::table(), $key ), ARRAY_A );
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Generates a unique 64-character manage key.
	 *
	 * @return string
	 */
	public static function new_key() {
		return bin2hex( random_bytes( 32 ) );
	}

	/**
	 * SELECT … FROM … JOIN … used by list and detail queries.
	 *
	 * @return array{0:string,1:array} SQL and identifier params.
	 */
	private static function joined_select() {
		$sql = 'SELECT b.*,
				TRIM(CONCAT(IFNULL(c.first_name, \'\'), \' \', IFNULL(c.last_name, \'\'))) AS customer_name,
				c.email AS customer_email,
				c.phone AS customer_phone,
				s.name AS service_name,
				s.duration_minutes AS service_duration,
				TRIM(CONCAT(IFNULL(a.first_name, \'\'), \' \', IFNULL(a.last_name, \'\'))) AS agent_name,
				a.image_id AS agent_image_id,
				l.name AS location_name
			FROM %i b
			LEFT JOIN %i c ON c.id = b.customer_id
			LEFT JOIN %i s ON s.id = b.service_id
			LEFT JOIN %i a ON a.id = b.agent_id
			LEFT JOIN %i l ON l.id = b.location_id';
		return array(
			$sql,
			array(
				self::table(),
				Tables::name( 'customers' ),
				Tables::name( 'services' ),
				Tables::name( 'agents' ),
				Tables::name( 'locations' ),
			),
		);
	}

	/**
	 * Builds the WHERE clause for list filters.
	 *
	 * @param array $f Filters.
	 * @return Where
	 */
	private static function where( array $f ) {
		$w = new Where();

		if ( ! empty( $f['status'] ) ) {
			$statuses = array_values( array_intersect( (array) $f['status'], self::STATUSES ) );
			$w->in_strings( 'b.status', $statuses );
		}
		if ( ! empty( $f['agent_id'] ) ) {
			$w->in_ints( 'b.agent_id', (array) $f['agent_id'] );
		}
		if ( ! empty( $f['service_id'] ) ) {
			$w->in_ints( 'b.service_id', (array) $f['service_id'] );
		}
		if ( ! empty( $f['location_id'] ) ) {
			$w->in_ints( 'b.location_id', (array) $f['location_id'] );
		}
		if ( ! empty( $f['customer_id'] ) ) {
			$w->add( 'b.customer_id = %d', (int) $f['customer_id'] );
		}
		if ( ! empty( $f['date_from'] ) ) {
			$w->add( 'b.start_datetime >= %s', $f['date_from'] . ' 00:00:00' );
		}
		if ( ! empty( $f['date_to'] ) ) {
			$w->add( 'b.start_datetime <= %s', $f['date_to'] . ' 23:59:59' );
		}
		if ( ! empty( $f['created_from'] ) ) {
			$w->add( 'b.created_at >= %s', $f['created_from'] . ' 00:00:00' );
		}
		if ( ! empty( $f['created_to'] ) ) {
			$w->add( 'b.created_at <= %s', $f['created_to'] . ' 23:59:59' );
		}
		if ( isset( $f['search'] ) && '' !== $f['search'] ) {
			$db   = self::db();
			$term = trim( (string) $f['search'] );
			if ( preg_match( '/^#?(\d+)$/', $term, $m ) ) {
				$w->add( 'b.id = %d', (int) $m[1] );
			} else {
				$like = '%' . $db->esc_like( $term ) . '%';
				$w->add(
					"CONCAT_WS(' ', c.first_name, c.last_name) LIKE %s OR c.email LIKE %s OR c.phone LIKE %s OR s.name LIKE %s OR CONCAT_WS(' ', a.first_name, a.last_name) LIKE %s OR b.notes LIKE %s",
					$like,
					$like,
					$like,
					$like,
					$like,
					$like
				);
			}
		}
		return $w;
	}

	/**
	 * Paginated, filtered list with joined names.
	 *
	 * @param array $f Filters: search, status, agent_id, service_id, location_id, customer_id,
	 *                 date_from, date_to, orderby, order, page, per_page.
	 * @return array{items:array,total:int}
	 */
	public static function search( array $f ) {
		$db                      = self::db();
		list( $select, $idents ) = self::joined_select();
		$w                       = self::where( $f );

		$orderable = array(
			'start'    => 'b.start_datetime',
			'created'  => 'b.created_at',
			'id'       => 'b.id',
			'customer' => 'customer_name',
			'service'  => 's.name',
			'status'   => 'b.status',
			'total'    => 'b.total_price',
		);
		$orderby   = $orderable[ $f['orderby'] ?? 'start' ] ?? 'b.start_datetime';
		$order     = ( isset( $f['order'] ) && 'asc' === strtolower( (string) $f['order'] ) ) ? 'ASC' : 'DESC';
		$per_page  = max( 1, min( 200, (int) ( $f['per_page'] ?? 20 ) ) );
		$page      = max( 1, (int) ( $f['page'] ?? 1 ) );

		$count_sql = 'SELECT COUNT(*) FROM %i b
			LEFT JOIN %i c ON c.id = b.customer_id
			LEFT JOIN %i s ON s.id = b.service_id
			LEFT JOIN %i a ON a.id = b.agent_id' . $w->sql();
		$total     = (int) $db->get_var(
			$db->prepare(
				$count_sql,
				array_merge(
					array( self::table(), Tables::name( 'customers' ), Tables::name( 'services' ), Tables::name( 'agents' ) ),
					$w->params()
				)
			)
		);

		$sql  = $select . $w->sql() . " ORDER BY {$orderby} {$order}, b.id {$order} LIMIT %d OFFSET %d";
		$rows = $db->get_results(
			$db->prepare( $sql, array_merge( $idents, $w->params(), array( $per_page, ( $page - 1 ) * $per_page ) ) ),
			ARRAY_A
		);

		return array(
			'items' => array_map( array( __CLASS__, 'hydrate' ), (array) $rows ),
			'total' => $total,
		);
	}

	/**
	 * Unpaginated list (exports, calendars) capped at $limit rows.
	 *
	 * @param array $f     Filters.
	 * @param int   $limit Row cap.
	 * @return array
	 */
	public static function list_all( array $f, $limit = 5000 ) {
		$db                      = self::db();
		list( $select, $idents ) = self::joined_select();
		$w                       = self::where( $f );
		$order                   = ( isset( $f['order'] ) && 'desc' === strtolower( (string) $f['order'] ) ) ? 'DESC' : 'ASC';
		$sql                     = $select . $w->sql() . " ORDER BY b.start_datetime {$order}, b.id {$order} LIMIT %d";
		$rows                    = $db->get_results( $db->prepare( $sql, array_merge( $idents, $w->params(), array( max( 1, (int) $limit ) ) ) ), ARRAY_A );
		return array_map( array( __CLASS__, 'hydrate' ), (array) $rows );
	}

	/**
	 * One booking with joined names.
	 *
	 * @param int $id Booking ID.
	 * @return array|null
	 */
	public static function find_joined( $id ) {
		$db                      = self::db();
		list( $select, $idents ) = self::joined_select();
		$row                     = $db->get_row( $db->prepare( $select . ' WHERE b.id = %d', array_merge( $idents, array( absint( $id ) ) ) ), ARRAY_A );
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Counts per status for the given filters (status filter ignored).
	 *
	 * @param array $f Filters.
	 * @return array<string,int>
	 */
	public static function status_counts( array $f ) {
		unset( $f['status'] );
		$db   = self::db();
		$w    = self::where( $f );
		$sql  = 'SELECT b.status, COUNT(*) AS c FROM %i b
			LEFT JOIN %i c ON c.id = b.customer_id
			LEFT JOIN %i s ON s.id = b.service_id
			LEFT JOIN %i a ON a.id = b.agent_id' . $w->sql() . ' GROUP BY b.status';
		$rows = $db->get_results(
			$db->prepare( $sql, array_merge( array( self::table(), Tables::name( 'customers' ), Tables::name( 'services' ), Tables::name( 'agents' ) ), $w->params() ) ),
			ARRAY_A
		);
		$out  = array_fill_keys( self::STATUSES, 0 );
		$all  = 0;
		foreach ( (array) $rows as $row ) {
			$out[ (string) $row['status'] ] = (int) $row['c'];
			$all                           += (int) $row['c'];
		}
		$out['all'] = $all;
		return $out;
	}

	/**
	 * Bookings that may block time for the given agents between two local datetimes.
	 * Agent 0 means "no staff member" (bookings stored with a NULL agent).
	 *
	 * @param int[]  $agent_ids  Agent IDs (0 allowed).
	 * @param string $from       Local DATETIME lower bound.
	 * @param string $to         Local DATETIME upper bound.
	 * @param int    $exclude_id Booking to ignore.
	 * @return array
	 */
	public static function occupying( array $agent_ids, $from, $to, $exclude_id = 0 ) {
		$db        = self::db();
		$agent_ids = array_values( array_unique( array_map( 'intval', $agent_ids ) ) );
		$real      = array_values( array_filter( $agent_ids ) );
		$w         = new Where();

		$agent_parts  = array();
		$agent_params = array();
		if ( $real ) {
			$agent_parts[] = 'agent_id IN (' . self::int_placeholders( $real ) . ')';
			$agent_params  = $real;
		}
		if ( in_array( 0, $agent_ids, true ) ) {
			$agent_parts[] = 'agent_id IS NULL OR agent_id = 0';
		}
		if ( ! $agent_parts ) {
			return array();
		}
		$w->add( implode( ' OR ', $agent_parts ), ...$agent_params );
		$w->in_strings( 'status', array_values( array_diff( self::STATUSES, self::FREEING_STATUSES ) ) );
		$w->add( 'start_datetime < %s', $to );
		$w->add( 'end_datetime > %s', $from );
		if ( $exclude_id ) {
			$w->add( 'id <> %d', (int) $exclude_id );
		}

		$rows = $db->get_results(
			$db->prepare(
				'SELECT id, agent_id, service_id, location_id, start_datetime, end_datetime, status, created_at FROM %i' . $w->sql(),
				array_merge( array( self::table() ), $w->params() )
			),
			ARRAY_A
		);
		return array_map( array( __CLASS__, 'hydrate' ), (array) $rows );
	}

	/**
	 * Bookings of a customer (newest first).
	 *
	 * @param int $customer_id Customer ID.
	 * @param int $limit       Row cap.
	 * @return array
	 */
	public static function for_customer( $customer_id, $limit = 200 ) {
		return self::search(
			array(
				'customer_id' => (int) $customer_id,
				'orderby'     => 'start',
				'order'       => 'desc',
				'per_page'    => $limit,
			)
		)['items'];
	}

	/**
	 * Bookings whose customer has the given email (customer portal).
	 *
	 * @param string $email Email.
	 * @return array
	 */
	public static function for_email( $email ) {
		$db                      = self::db();
		list( $select, $idents ) = self::joined_select();
		$rows                    = $db->get_results(
			$db->prepare( $select . ' WHERE c.email = %s ORDER BY b.start_datetime DESC LIMIT 200', array_merge( $idents, array( (string) $email ) ) ),
			ARRAY_A
		);
		return array_map( array( __CLASS__, 'hydrate' ), (array) $rows );
	}

	/**
	 * Re-points bookings from one customer to another (used by anonymise/merge).
	 *
	 * @param int $from Customer ID.
	 * @param int $to   Customer ID.
	 * @return void
	 */
	public static function reassign_customer( $from, $to ) {
		$db = self::db();
		$db->update( self::table(), array( 'customer_id' => (int) $to ), array( 'customer_id' => (int) $from ), array( '%d' ), array( '%d' ) );
	}

	/**
	 * Counts bookings for a customer.
	 *
	 * @param int $customer_id Customer ID.
	 * @return int
	 */
	public static function count_for_customer( $customer_id ) {
		$db = self::db();
		return (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i WHERE customer_id = %d', self::table(), (int) $customer_id ) );
	}

	/**
	 * Pending-payment bookings created before a cutoff (abandoned checkouts).
	 *
	 * @param string $cutoff Local DATETIME.
	 * @return int[]
	 */
	public static function stale_pending_payment_ids( $cutoff ) {
		$db  = self::db();
		$ids = $db->get_col( $db->prepare( 'SELECT id FROM %i WHERE status = %s AND payment_status <> %s AND created_at < %s LIMIT 500', self::table(), 'pending_payment', 'paid', $cutoff ) );
		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Aggregate stats between two local datetimes.
	 *
	 * @param string $from   Local DATETIME.
	 * @param string $to     Local DATETIME.
	 * @param string $column Date column (start_datetime|created_at).
	 * @return array{count:int,revenue:float,by_status:array}
	 */
	public static function stats( $from, $to, $column = 'start_datetime' ) {
		$db        = self::db();
		$column    = 'created_at' === $column ? 'created_at' : 'start_datetime';
		$rows      = $db->get_results(
			$db->prepare(
				'SELECT status, COUNT(*) AS c, SUM(CASE WHEN payment_status = %s OR status IN (%s, %s) THEN total_price ELSE 0 END) AS revenue FROM %i WHERE %i >= %s AND %i <= %s GROUP BY status',
				'paid',
				'confirmed',
				'completed',
				self::table(),
				$column,
				$from,
				$column,
				$to
			),
			ARRAY_A
		);
		$count     = 0;
		$revenue   = 0.0;
		$by_status = array();
		foreach ( (array) $rows as $row ) {
			$by_status[ $row['status'] ] = (int) $row['c'];
			if ( in_array( $row['status'], self::FREEING_STATUSES, true ) ) {
				continue;
			}
			$count   += (int) $row['c'];
			$revenue += (float) $row['revenue'];
		}
		return array(
			'count'     => $count,
			'revenue'   => round( $revenue, 2 ),
			'by_status' => $by_status,
		);
	}

	/**
	 * Per-day counts and revenue (non-cancelled) between two dates.
	 *
	 * @param string $from Y-m-d.
	 * @param string $to   Y-m-d.
	 * @return array<string,array{count:int,revenue:float}>
	 */
	public static function daily_series( $from, $to ) {
		$db   = self::db();
		$rows = $db->get_results(
			$db->prepare(
				'SELECT DATE(start_datetime) AS d, COUNT(*) AS c, SUM(CASE WHEN payment_status = %s OR status IN (%s, %s) THEN total_price ELSE 0 END) AS revenue FROM %i WHERE start_datetime >= %s AND start_datetime <= %s AND status NOT IN (%s, %s) GROUP BY DATE(start_datetime)',
				'paid',
				'confirmed',
				'completed',
				self::table(),
				$from . ' 00:00:00',
				$to . ' 23:59:59',
				'cancelled',
				'failed_payment'
			),
			ARRAY_A
		);
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ (string) $row['d'] ] = array(
				'count'   => (int) $row['c'],
				'revenue' => round( (float) $row['revenue'], 2 ),
			);
		}
		return $out;
	}

	/**
	 * Top services by bookings in a period.
	 *
	 * @param string $from  Local DATETIME.
	 * @param string $to    Local DATETIME.
	 * @param int    $limit Rows.
	 * @return array
	 */
	public static function top_services( $from, $to, $limit = 5 ) {
		$db   = self::db();
		$rows = $db->get_results(
			$db->prepare(
				'SELECT b.service_id, s.name, COUNT(*) AS bookings, SUM(b.total_price) AS revenue FROM %i b LEFT JOIN %i s ON s.id = b.service_id WHERE b.start_datetime >= %s AND b.start_datetime <= %s AND b.status NOT IN (%s, %s) GROUP BY b.service_id, s.name ORDER BY bookings DESC LIMIT %d',
				self::table(),
				Tables::name( 'services' ),
				$from,
				$to,
				'cancelled',
				'failed_payment',
				(int) $limit
			),
			ARRAY_A
		);
		return array_map(
			static function ( $row ) {
				return array(
					'service_id' => (int) $row['service_id'],
					'name'       => (string) $row['name'],
					'bookings'   => (int) $row['bookings'],
					'revenue'    => round( (float) $row['revenue'], 2 ),
				);
			},
			(array) $rows
		);
	}

	/**
	 * Number of bookings per agent on a date (used to balance "any staff" assignments).
	 *
	 * @param int[]  $agent_ids Agents.
	 * @param string $date      Y-m-d.
	 * @return array<int,int>
	 */
	public static function load_per_agent( array $agent_ids, $date ) {
		$agent_ids = array_values( array_filter( array_map( 'intval', $agent_ids ) ) );
		if ( ! $agent_ids ) {
			return array();
		}
		$db   = self::db();
		$rows = $db->get_results(
			$db->prepare(
				'SELECT agent_id, COUNT(*) AS c FROM %i WHERE agent_id IN (' . self::int_placeholders( $agent_ids ) . ') AND start_datetime >= %s AND start_datetime <= %s AND status NOT IN (%s, %s) GROUP BY agent_id',
				array_merge( array( self::table() ), $agent_ids, array( $date . ' 00:00:00', $date . ' 23:59:59', 'cancelled', 'failed_payment' ) )
			),
			ARRAY_A
		);
		$out  = array_fill_keys( $agent_ids, 0 );
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['agent_id'] ] = (int) $row['c'];
		}
		return $out;
	}

	/**
	 * Most recent booking row (notification tests).
	 *
	 * @return array|null
	 */
	public static function latest() {
		$db  = self::db();
		$row = $db->get_row( $db->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT 1', self::table() ), ARRAY_A );
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Total number of bookings.
	 *
	 * @return int
	 */
	public static function count_all() {
		$db = self::db();
		return (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i', self::table() ) );
	}
}
