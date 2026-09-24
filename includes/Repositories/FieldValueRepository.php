<?php
/**
 * Per-entity custom field answers.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Repositories;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables.

/**
 * Field value queries.
 */
final class FieldValueRepository extends Repository {

	const TABLE = 'field_values';

	/**
	 * Values of an entity.
	 *
	 * @param string $entity_type booking|customer.
	 * @param int    $entity_id   Entity ID.
	 * @return array
	 */
	public static function for_entity( $entity_type, $entity_id ) {
		$db   = self::db();
		$rows = $db->get_results(
			$db->prepare( 'SELECT field_id, field_key, scope, value_long FROM %i WHERE entity_type = %s AND entity_id = %d ORDER BY id ASC', self::table(), (string) $entity_type, absint( $entity_id ) ),
			ARRAY_A
		);
		return array_map(
			static function ( $row ) {
				$value = $row['value_long'];
				if ( is_string( $value ) && '' !== $value && ( '[' === $value[0] || '{' === $value[0] ) ) {
					$decoded = json_decode( $value, true );
					if ( is_array( $decoded ) ) {
						$value = $decoded;
					}
				}
				return array(
					'field_id'   => (int) $row['field_id'],
					'field_key'  => (string) $row['field_key'],
					'scope'      => (string) $row['scope'],
					'value_long' => $row['value_long'],
					'value'      => $value,
				);
			},
			(array) $rows
		);
	}

	/**
	 * Inserts or updates one value.
	 *
	 * @param string $entity_type Entity type.
	 * @param int    $entity_id   Entity ID.
	 * @param array  $field       Hydrated field definition.
	 * @param mixed  $value       Clean value.
	 * @return void
	 */
	public static function upsert( $entity_type, $entity_id, array $field, $value ) {
		$db    = self::db();
		$value = is_array( $value ) ? wp_json_encode( $value ) : (string) $value;
		$now   = self::now();
		$id    = (int) $db->get_var(
			$db->prepare( 'SELECT id FROM %i WHERE entity_type = %s AND entity_id = %d AND field_id = %d LIMIT 1', self::table(), $entity_type, absint( $entity_id ), (int) $field['id'] )
		);
		if ( $id ) {
			self::update(
				$id,
				array(
					'value_long' => $value,
					'updated_at' => $now,
				)
			);
			return;
		}
		self::insert(
			array(
				'entity_type' => (string) $entity_type,
				'entity_id'   => absint( $entity_id ),
				'field_id'    => (int) $field['id'],
				'field_key'   => (string) $field['field_key'],
				'scope'       => (string) $field['scope'],
				'value_long'  => $value,
				'created_at'  => $now,
				'updated_at'  => $now,
			)
		);
	}

	/**
	 * Deletes every value of an entity.
	 *
	 * @param string $entity_type Entity type.
	 * @param int    $entity_id   Entity ID.
	 * @return void
	 */
	public static function delete_for_entity( $entity_type, $entity_id ) {
		$db = self::db();
		$db->delete(
			self::table(),
			array(
				'entity_type' => (string) $entity_type,
				'entity_id'   => absint( $entity_id ),
			),
			array( '%s', '%d' )
		);
	}
}
