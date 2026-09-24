<?php
/**
 * Base repository for the plugin's custom tables.
 *
 * All SQL in repositories is prepared; table names are passed as %i identifiers.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Repositories;

use PointlyBooking\Database\Tables;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables; caching is applied at the service layer.

/**
 * Shared CRUD helpers.
 */
abstract class Repository {

	/**
	 * Logical table name (set by subclasses).
	 *
	 * @var string
	 */
	const TABLE = '';

	/**
	 * Prefixed table name.
	 *
	 * @return string
	 */
	public static function table() {
		return Tables::name( static::TABLE );
	}

	/**
	 * WordPress database object.
	 *
	 * @return \wpdb
	 */
	protected static function db() {
		global $wpdb;
		return $wpdb;
	}

	/**
	 * Current site-local timestamp.
	 *
	 * @return string
	 */
	protected static function now() {
		return current_time( 'mysql' );
	}

	/**
	 * Placeholder formats inferred from PHP value types.
	 *
	 * @param array $data Column => value.
	 * @return string[]
	 */
	protected static function formats( array $data ) {
		$formats = array();
		foreach ( $data as $value ) {
			if ( is_int( $value ) || is_bool( $value ) ) {
				$formats[] = '%d';
			} elseif ( is_float( $value ) ) {
				$formats[] = '%f';
			} else {
				$formats[] = '%s';
			}
		}
		return $formats;
	}

	/**
	 * Finds a row by primary key.
	 *
	 * @param int $id Row ID.
	 * @return array|null
	 */
	public static function find( $id ) {
		$id = absint( $id );
		if ( ! $id ) {
			return null;
		}
		$db  = self::db();
		$row = $db->get_row( $db->prepare( 'SELECT * FROM %i WHERE id = %d', static::table(), $id ), ARRAY_A );
		return $row ? static::hydrate( $row ) : null;
	}

	/**
	 * Casts raw DB values; subclasses override for typed fields.
	 *
	 * @param array $row Raw row.
	 * @return array
	 */
	public static function hydrate( array $row ) {
		if ( isset( $row['id'] ) ) {
			$row['id'] = (int) $row['id'];
		}
		return $row;
	}

	/**
	 * Inserts a row.
	 *
	 * @param array $data Column => value.
	 * @return int New ID (0 on failure).
	 */
	public static function insert( array $data ) {
		$db = self::db();
		$ok = $db->insert( static::table(), $data, self::formats( $data ) );
		return $ok ? (int) $db->insert_id : 0;
	}

	/**
	 * Updates a row.
	 *
	 * @param int   $id   Row ID.
	 * @param array $data Column => value.
	 * @return bool
	 */
	public static function update( $id, array $data ) {
		if ( ! $data ) {
			return true;
		}
		$db = self::db();
		return false !== $db->update( static::table(), $data, array( 'id' => absint( $id ) ), self::formats( $data ), array( '%d' ) );
	}

	/**
	 * Deletes a row.
	 *
	 * @param int $id Row ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		$db = self::db();
		return false !== $db->delete( static::table(), array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	/**
	 * Deletes rows matching a single column value.
	 *
	 * @param string $column Column.
	 * @param int    $value  Value.
	 * @return void
	 */
	protected static function delete_where( $column, $value ) {
		$db = self::db();
		$db->delete( static::table(), array( $column => absint( $value ) ), array( '%d' ) );
	}

	/**
	 * Comma separated %d placeholders.
	 *
	 * @param array $values Values.
	 * @return string
	 */
	protected static function int_placeholders( array $values ) {
		return implode( ',', array_fill( 0, max( 1, count( $values ) ), '%d' ) );
	}

	/**
	 * Decodes a JSON column into an array.
	 *
	 * @param mixed $value Column value.
	 * @return array
	 */
	public static function json( $value ) {
		if ( is_array( $value ) ) {
			return $value;
		}
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return array();
		}
		$decoded = json_decode( $value, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Admin/list image URL for an attachment.
	 *
	 * @param mixed  $image_id Attachment ID.
	 * @param string $size     Image size.
	 * @return string
	 */
	public static function image_url( $image_id, $size = 'medium' ) {
		$image_id = absint( $image_id );
		if ( ! $image_id ) {
			return '';
		}
		$url = wp_get_attachment_image_url( $image_id, $size );
		return $url ? $url : '';
	}
}
