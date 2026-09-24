<?php
/**
 * Promo codes table access.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Repositories;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables.

/**
 * Promo code queries.
 */
final class PromoCodeRepository extends Repository {

	const TABLE = 'promo_codes';

	/**
	 * {@inheritDoc}
	 *
	 * @param array $row Raw row.
	 * @return array
	 */
	public static function hydrate( array $row ) {
		$row['id']         = (int) $row['id'];
		$row['amount']     = round( (float) $row['amount'], 2 );
		$row['uses_count'] = (int) ( $row['uses_count'] ?? 0 );
		$row['max_uses']   = ( null === $row['max_uses'] || '' === $row['max_uses'] ) ? null : (int) $row['max_uses'];
		$row['min_total']  = ( null === $row['min_total'] || '' === $row['min_total'] ) ? null : round( (float) $row['min_total'], 2 );
		$row['is_active']  = (int) ( $row['is_active'] ?? 1 );
		$row['type']       = 'fixed' === $row['type'] ? 'fixed' : 'percent';
		$row['state']      = self::state( $row );
		return $row;
	}

	/**
	 * Lifecycle state: active, scheduled, expired, exhausted, disabled.
	 *
	 * @param array $row Hydrated row.
	 * @return string
	 */
	public static function state( array $row ) {
		if ( empty( $row['is_active'] ) ) {
			return 'disabled';
		}
		$now = current_time( 'mysql' );
		if ( ! empty( $row['starts_at'] ) && $row['starts_at'] > $now ) {
			return 'scheduled';
		}
		if ( ! empty( $row['ends_at'] ) && $row['ends_at'] < $now ) {
			return 'expired';
		}
		if ( null !== $row['max_uses'] && $row['max_uses'] > 0 && $row['uses_count'] >= $row['max_uses'] ) {
			return 'exhausted';
		}
		return 'active';
	}

	/**
	 * All codes, newest first, optionally filtered.
	 *
	 * @param string $search Code search.
	 * @return array
	 */
	public static function all_codes( $search = '' ) {
		$db = self::db();
		if ( '' !== $search ) {
			$rows = $db->get_results( $db->prepare( 'SELECT * FROM %i WHERE code LIKE %s ORDER BY id DESC', self::table(), '%' . $db->esc_like( strtoupper( $search ) ) . '%' ), ARRAY_A );
		} else {
			$rows = $db->get_results( $db->prepare( 'SELECT * FROM %i ORDER BY id DESC', self::table() ), ARRAY_A );
		}
		return array_map( array( __CLASS__, 'hydrate' ), (array) $rows );
	}

	/**
	 * Finds a code (case-insensitive).
	 *
	 * @param string $code Code.
	 * @return array|null
	 */
	public static function find_by_code( $code ) {
		$code = strtoupper( trim( (string) $code ) );
		if ( '' === $code ) {
			return null;
		}
		$db  = self::db();
		$row = $db->get_row( $db->prepare( 'SELECT * FROM %i WHERE code = %s LIMIT 1', self::table(), $code ), ARRAY_A );
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Atomically increments usage.
	 *
	 * @param int $id Promo ID.
	 * @return void
	 */
	public static function increment_use( $id ) {
		$db = self::db();
		$db->query( $db->prepare( 'UPDATE %i SET uses_count = uses_count + 1 WHERE id = %d', self::table(), absint( $id ) ) );
	}

	/**
	 * Creates a code.
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
	 * Updates a code.
	 *
	 * @param int   $id   ID.
	 * @param array $data Values.
	 * @return bool
	 */
	public static function save( $id, array $data ) {
		$data['updated_at'] = self::now();
		return self::update( $id, $data );
	}
}
