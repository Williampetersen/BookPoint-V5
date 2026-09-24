<?php
/**
 * Notification workflows, actions and run logs.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Repositories;

use PointlyBooking\Database\Tables;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Queries are assembled from literal fragments with placeholders and passed through $wpdb->prepare().

/**
 * Workflow queries.
 */
final class WorkflowRepository extends Repository {

	const TABLE = 'workflows';

	/**
	 * Supported trigger events.
	 *
	 * @var string[]
	 */
	const EVENTS = array( 'booking_created', 'booking_updated', 'booking_confirmed', 'booking_cancelled', 'customer_created' );

	/**
	 * {@inheritDoc}
	 *
	 * @param array $row Raw row.
	 * @return array
	 */
	public static function hydrate( array $row ) {
		$row['id']                  = (int) $row['id'];
		$row['is_conditional']      = (int) ( $row['is_conditional'] ?? 0 );
		$row['has_time_offset']     = (int) ( $row['has_time_offset'] ?? 0 );
		$row['time_offset_minutes'] = (int) ( $row['time_offset_minutes'] ?? 0 );
		$row['conditions']          = self::json( $row['conditions_json'] ?? '' );
		foreach ( array( 'actions_count', 'runs_count' ) as $key ) {
			if ( isset( $row[ $key ] ) ) {
				$row[ $key ] = (int) $row[ $key ];
			}
		}
		return $row;
	}

	/**
	 * Hydrates an action row.
	 *
	 * @param array $row Raw row.
	 * @return array
	 */
	public static function hydrate_action( array $row ) {
		$row['id']          = (int) $row['id'];
		$row['workflow_id'] = (int) $row['workflow_id'];
		$row['sort_order']  = (int) $row['sort_order'];
		$row['config']      = self::json( $row['config_json'] ?? '' );
		return $row;
	}

	/**
	 * Filtered list with action counts and last run info.
	 *
	 * @param array $f search, status, event, page, per_page.
	 * @return array{items:array,total:int}
	 */
	public static function search( array $f ) {
		$db = self::db();
		$w  = new Where();
		if ( ! empty( $f['status'] ) && in_array( $f['status'], array( 'active', 'disabled' ), true ) ) {
			$w->add( 'w.status = %s', $f['status'] );
		}
		if ( ! empty( $f['event'] ) && in_array( $f['event'], self::EVENTS, true ) ) {
			$w->add( 'w.event_key = %s', $f['event'] );
		}
		if ( isset( $f['search'] ) && '' !== trim( (string) $f['search'] ) ) {
			$w->add( 'w.name LIKE %s', '%' . $db->esc_like( trim( (string) $f['search'] ) ) . '%' );
		}
		$per_page = max( 1, min( 100, (int) ( $f['per_page'] ?? 50 ) ) );
		$page     = max( 1, (int) ( $f['page'] ?? 1 ) );

		$total = (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i w' . $w->sql(), array_merge( array( self::table() ), $w->params() ) ) );
		$sql   = 'SELECT w.*, IFNULL(a.c, 0) AS actions_count, l.last_run_at, l.runs_count,
				(SELECT l2.status FROM %i l2 WHERE l2.workflow_id = w.id ORDER BY l2.id DESC LIMIT 1) AS last_run_status
			FROM %i w
			LEFT JOIN (SELECT workflow_id, COUNT(*) AS c FROM %i GROUP BY workflow_id) a ON a.workflow_id = w.id
			LEFT JOIN (SELECT workflow_id, MAX(created_at) AS last_run_at, COUNT(*) AS runs_count FROM %i GROUP BY workflow_id) l ON l.workflow_id = w.id'
			. $w->sql() . ' ORDER BY w.event_key ASC, w.id ASC LIMIT %d OFFSET %d';
		$rows  = $db->get_results(
			$db->prepare(
				$sql,
				array_merge(
					array( Tables::name( 'workflow_logs' ), self::table(), Tables::name( 'workflow_actions' ), Tables::name( 'workflow_logs' ) ),
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
	 * Active workflows for an event.
	 *
	 * @param string $event Event key.
	 * @return array
	 */
	public static function active_for( $event ) {
		$db   = self::db();
		$rows = $db->get_results( $db->prepare( 'SELECT * FROM %i WHERE event_key = %s AND status = %s ORDER BY id ASC', self::table(), $event, 'active' ), ARRAY_A );
		return array_map( array( __CLASS__, 'hydrate' ), (array) $rows );
	}

	/**
	 * Workflow counts per event and status.
	 *
	 * @return array
	 */
	public static function counts() {
		$db   = self::db();
		$rows = $db->get_results( $db->prepare( 'SELECT event_key, status, COUNT(*) AS c FROM %i GROUP BY event_key, status', self::table() ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$event = (string) $row['event_key'];
			if ( ! isset( $out[ $event ] ) ) {
				$out[ $event ] = array(
					'active'   => 0,
					'disabled' => 0,
					'total'    => 0,
				);
			}
			$status = 'active' === $row['status'] ? 'active' : 'disabled';
			$out[ $event ][ $status ] += (int) $row['c'];
			$out[ $event ]['total']   += (int) $row['c'];
		}
		return $out;
	}

	/**
	 * Number of workflows.
	 *
	 * @return int
	 */
	public static function count_all() {
		$db = self::db();
		return (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i', self::table() ) );
	}

	/**
	 * Creates a workflow.
	 *
	 * @param array $data Values.
	 * @return int
	 */
	public static function create( array $data ) {
		$data['created_at'] = self::now();
		$data['updated_at'] = self::now();
		return self::insert( $data );
	}

	/**
	 * Updates a workflow.
	 *
	 * @param int   $id   ID.
	 * @param array $data Values.
	 * @return bool
	 */
	public static function save( $id, array $data ) {
		$data['updated_at'] = self::now();
		return self::update( $id, $data );
	}

	/**
	 * Deletes a workflow with its actions and logs.
	 *
	 * @param int $id ID.
	 * @return bool
	 */
	public static function remove( $id ) {
		$db = self::db();
		$db->delete( Tables::name( 'workflow_actions' ), array( 'workflow_id' => absint( $id ) ), array( '%d' ) );
		$db->delete( Tables::name( 'workflow_logs' ), array( 'workflow_id' => absint( $id ) ), array( '%d' ) );
		return self::delete( $id );
	}

	/**
	 * Actions of a workflow.
	 *
	 * @param int $workflow_id Workflow ID.
	 * @return array
	 */
	public static function actions( $workflow_id ) {
		$db   = self::db();
		$rows = $db->get_results( $db->prepare( 'SELECT * FROM %i WHERE workflow_id = %d ORDER BY sort_order ASC, id ASC', Tables::name( 'workflow_actions' ), absint( $workflow_id ) ), ARRAY_A );
		return array_map( array( __CLASS__, 'hydrate_action' ), (array) $rows );
	}

	/**
	 * One action.
	 *
	 * @param int $id Action ID.
	 * @return array|null
	 */
	public static function action( $id ) {
		$db  = self::db();
		$row = $db->get_row( $db->prepare( 'SELECT * FROM %i WHERE id = %d', Tables::name( 'workflow_actions' ), absint( $id ) ), ARRAY_A );
		return $row ? self::hydrate_action( $row ) : null;
	}

	/**
	 * Creates an action.
	 *
	 * @param int   $workflow_id Workflow ID.
	 * @param array $data        type, status, config.
	 * @return int
	 */
	public static function create_action( $workflow_id, array $data ) {
		$db    = self::db();
		$table = Tables::name( 'workflow_actions' );
		$next  = (int) $db->get_var( $db->prepare( 'SELECT IFNULL(MAX(sort_order), 0) + 1 FROM %i WHERE workflow_id = %d', $table, absint( $workflow_id ) ) );
		$db->insert(
			$table,
			array(
				'workflow_id' => absint( $workflow_id ),
				'type'        => (string) ( $data['type'] ?? 'send_email' ),
				'status'      => 'disabled' === ( $data['status'] ?? 'active' ) ? 'disabled' : 'active',
				'config_json' => wp_json_encode( is_array( $data['config'] ?? null ) ? $data['config'] : array() ),
				'sort_order'  => $next,
				'created_at'  => self::now(),
				'updated_at'  => self::now(),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		return (int) $db->insert_id;
	}

	/**
	 * Updates an action.
	 *
	 * @param int   $id   Action ID.
	 * @param array $data Values (type, status, config, sort_order).
	 * @return bool
	 */
	public static function save_action( $id, array $data ) {
		$cols = array( 'updated_at' => self::now() );
		if ( isset( $data['type'] ) ) {
			$cols['type'] = (string) $data['type'];
		}
		if ( isset( $data['status'] ) ) {
			$cols['status'] = 'disabled' === $data['status'] ? 'disabled' : 'active';
		}
		if ( isset( $data['config'] ) && is_array( $data['config'] ) ) {
			$cols['config_json'] = wp_json_encode( $data['config'] );
		}
		if ( isset( $data['sort_order'] ) ) {
			$cols['sort_order'] = (int) $data['sort_order'];
		}
		$db = self::db();
		return false !== $db->update( Tables::name( 'workflow_actions' ), $cols, array( 'id' => absint( $id ) ), null, array( '%d' ) );
	}

	/**
	 * Deletes an action.
	 *
	 * @param int $id Action ID.
	 * @return bool
	 */
	public static function delete_action( $id ) {
		$db = self::db();
		return (bool) $db->delete( Tables::name( 'workflow_actions' ), array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	/**
	 * Writes a run log line.
	 *
	 * @param int    $workflow_id Workflow ID.
	 * @param string $event       Event key.
	 * @param int    $entity_id   Booking ID.
	 * @param string $status      success|failed|skipped.
	 * @param string $message     Message.
	 * @return void
	 */
	public static function log( $workflow_id, $event, $entity_id, $status, $message ) {
		$db = self::db();
		$db->insert(
			Tables::name( 'workflow_logs' ),
			array(
				'workflow_id' => absint( $workflow_id ),
				'event_key'   => (string) $event,
				'entity_type' => 'booking',
				'entity_id'   => absint( $entity_id ),
				'status'      => (string) $status,
				'message'     => (string) $message,
				'created_at'  => self::now(),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Recent log lines of a workflow.
	 *
	 * @param int $workflow_id Workflow ID.
	 * @param int $limit       Rows.
	 * @return array
	 */
	public static function logs( $workflow_id, $limit = 20 ) {
		$db = self::db();
		return (array) $db->get_results( $db->prepare( 'SELECT id, event_key, entity_id, status, message, created_at FROM %i WHERE workflow_id = %d ORDER BY id DESC LIMIT %d', Tables::name( 'workflow_logs' ), absint( $workflow_id ), (int) $limit ), ARRAY_A );
	}
}
