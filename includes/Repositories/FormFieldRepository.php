<?php
/**
 * Custom form fields table access.
 *
 * Two generations of column names exist (field_key/is_required/is_enabled/options and
 * name_key/required/is_active/options_json). Every write keeps both in sync.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Repositories;

use PointlyBooking\Support\Cache;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables; list results are cached via Support\Cache.

/**
 * Form field queries.
 */
final class FormFieldRepository extends Repository {

	const TABLE = 'form_fields';

	/**
	 * Supported field types.
	 *
	 * @var string[]
	 */
	const TYPES = array( 'text', 'email', 'tel', 'textarea', 'number', 'date', 'select', 'radio', 'checkbox' );

	/**
	 * Supported scopes.
	 *
	 * @var string[]
	 */
	const SCOPES = array( 'customer', 'booking', 'form' );

	/**
	 * Built-in customer fields every install has.
	 *
	 * @var string[]
	 */
	const CORE_KEYS = array( 'first_name', 'last_name', 'email', 'phone' );

	/**
	 * {@inheritDoc}
	 *
	 * @param array $row Raw row.
	 * @return array
	 */
	public static function hydrate( array $row ) {
		$key     = (string) ( '' !== (string) ( $row['field_key'] ?? '' ) ? $row['field_key'] : ( $row['name_key'] ?? '' ) );
		$options = self::json( '' !== (string) ( $row['options'] ?? '' ) ? $row['options'] : ( $row['options_json'] ?? '' ) );
		$clean   = array();
		foreach ( $options as $option ) {
			if ( is_array( $option ) ) {
				$label = (string) ( $option['label'] ?? ( $option['value'] ?? '' ) );
				$value = (string) ( $option['value'] ?? $label );
			} else {
				$label = (string) $option;
				$value = (string) $option;
			}
			if ( '' !== $label ) {
				$clean[] = array(
					'label' => $label,
					'value' => $value,
				);
			}
		}

		return array(
			'id'             => (int) $row['id'],
			'field_key'      => $key,
			'label'          => (string) $row['label'],
			'type'           => in_array( $row['type'], self::TYPES, true ) ? $row['type'] : 'text',
			'scope'          => in_array( $row['scope'], self::SCOPES, true ) ? $row['scope'] : 'customer',
			'step_key'       => (string) ( $row['step_key'] ?? 'details' ),
			'placeholder'    => (string) ( $row['placeholder'] ?? '' ),
			'options'        => $clean,
			'is_required'    => ( (int) ( $row['is_required'] ?? 0 ) || (int) ( $row['required'] ?? 0 ) ) ? 1 : 0,
			'is_enabled'     => (int) ( $row['is_enabled'] ?? ( $row['is_active'] ?? 1 ) ),
			'show_in_wizard' => (int) ( $row['show_in_wizard'] ?? 1 ),
			'sort_order'     => (int) ( $row['sort_order'] ?? 0 ),
			'is_core'        => 'customer' === $row['scope'] && in_array( $key, self::CORE_KEYS, true ),
		);
	}

	/**
	 * All fields (optionally one scope), cached.
	 *
	 * @param string $scope Scope or ''.
	 * @return array
	 */
	public static function all_fields( $scope = '' ) {
		$all = Cache::remember(
			'catalog',
			'form_fields',
			static function () {
				$db   = self::db();
				$rows = $db->get_results( $db->prepare( 'SELECT * FROM %i ORDER BY scope ASC, sort_order ASC, id ASC', self::table() ), ARRAY_A );
				return array_map( array( __CLASS__, 'hydrate' ), (array) $rows );
			}
		);
		if ( '' === $scope ) {
			return $all;
		}
		return array_values(
			array_filter(
				$all,
				static function ( $field ) use ( $scope ) {
					return $field['scope'] === $scope;
				}
			)
		);
	}

	/**
	 * Fields shown in the booking wizard, grouped by scope.
	 *
	 * @return array{customer:array,booking:array,form:array}
	 */
	public static function wizard_fields() {
		$out = array(
			'customer' => array(),
			'booking'  => array(),
			'form'     => array(),
		);
		foreach ( self::all_fields() as $field ) {
			if ( $field['is_enabled'] && $field['show_in_wizard'] ) {
				$out[ $field['scope'] ][] = $field;
			}
		}
		return $out;
	}

	/**
	 * Converts API values to both generations of columns.
	 *
	 * @param array $data Clean values (field_key,label,type,scope,step_key,placeholder,options,is_required,is_enabled,show_in_wizard,sort_order).
	 * @return array
	 */
	private static function to_columns( array $data ) {
		$cols = array();
		foreach ( array( 'label', 'type', 'scope', 'step_key', 'placeholder' ) as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				$cols[ $key ] = (string) $data[ $key ];
			}
		}
		if ( array_key_exists( 'field_key', $data ) ) {
			$cols['field_key'] = (string) $data['field_key'];
			$cols['name_key']  = (string) $data['field_key'];
		}
		if ( array_key_exists( 'options', $data ) ) {
			$json                 = $data['options'] ? wp_json_encode( array_values( $data['options'] ) ) : null;
			$cols['options']      = $json;
			$cols['options_json'] = $json;
		}
		if ( array_key_exists( 'is_required', $data ) ) {
			$cols['is_required'] = (int) (bool) $data['is_required'];
			$cols['required']    = $cols['is_required'];
		}
		if ( array_key_exists( 'is_enabled', $data ) ) {
			$cols['is_enabled'] = (int) (bool) $data['is_enabled'];
			$cols['is_active']  = $cols['is_enabled'];
		}
		if ( array_key_exists( 'show_in_wizard', $data ) ) {
			$cols['show_in_wizard'] = (int) (bool) $data['show_in_wizard'];
		}
		if ( array_key_exists( 'sort_order', $data ) ) {
			$cols['sort_order'] = (int) $data['sort_order'];
		}
		return $cols;
	}

	/**
	 * Whether a key is already used in a scope.
	 *
	 * @param string $key        Field key.
	 * @param string $scope      Scope.
	 * @param int    $exclude_id Field to ignore.
	 * @return bool
	 */
	public static function key_exists( $key, $scope, $exclude_id = 0 ) {
		$db = self::db();
		return (int) $db->get_var(
			$db->prepare( 'SELECT COUNT(*) FROM %i WHERE scope = %s AND (field_key = %s OR name_key = %s) AND id <> %d', self::table(), $scope, $key, $key, (int) $exclude_id )
		) > 0;
	}

	/**
	 * Creates a field.
	 *
	 * @param array $data Clean values.
	 * @return int
	 */
	public static function create( array $data ) {
		$cols               = self::to_columns( $data );
		$cols['created_at'] = self::now();
		$cols['updated_at'] = self::now();
		$id                 = self::insert( $cols );
		Cache::bump( 'catalog' );
		return $id;
	}

	/**
	 * Updates a field.
	 *
	 * @param int   $id   ID.
	 * @param array $data Clean values.
	 * @return bool
	 */
	public static function save( $id, array $data ) {
		$cols               = self::to_columns( $data );
		$cols['updated_at'] = self::now();
		$ok                 = self::update( $id, $cols );
		Cache::bump( 'catalog' );
		return $ok;
	}

	/**
	 * Deletes a field.
	 *
	 * @param int $id ID.
	 * @return bool
	 */
	public static function remove( $id ) {
		$ok = self::delete( $id );
		Cache::bump( 'catalog' );
		return $ok;
	}

	/**
	 * Applies a new order to a list of field IDs.
	 *
	 * @param int[] $ids IDs in order.
	 * @return void
	 */
	public static function reorder( array $ids ) {
		$order = 10;
		foreach ( $ids as $id ) {
			self::update(
				absint( $id ),
				array(
					'sort_order' => $order,
					'updated_at' => self::now(),
				)
			);
			$order += 10;
		}
		Cache::bump( 'catalog' );
	}

	/**
	 * Inserts the built-in fields that are missing (never overwrites admin edits).
	 *
	 * @return int Number of fields created.
	 */
	public static function ensure_defaults() {
		$defaults = array(
			array( 'first_name', __( 'First name', 'pointly-booking' ), 'text', 'customer', 1, 10 ),
			array( 'last_name', __( 'Last name', 'pointly-booking' ), 'text', 'customer', 0, 20 ),
			array( 'email', __( 'Email', 'pointly-booking' ), 'email', 'customer', 1, 30 ),
			array( 'phone', __( 'Phone', 'pointly-booking' ), 'tel', 'customer', 0, 40 ),
			array( 'notes', __( 'Notes', 'pointly-booking' ), 'textarea', 'booking', 0, 10 ),
		);
		$created  = 0;
		foreach ( $defaults as $def ) {
			list( $key, $label, $type, $scope, $required, $order ) = $def;
			if ( self::key_exists( $key, $scope ) ) {
				continue;
			}
			self::create(
				array(
					'field_key'      => $key,
					'label'          => $label,
					'type'           => $type,
					'scope'          => $scope,
					'step_key'       => 'details',
					'placeholder'    => '',
					'options'        => array(),
					'is_required'    => $required,
					'is_enabled'     => 1,
					'show_in_wizard' => 1,
					'sort_order'     => $order,
				)
			);
			++$created;
		}

		// The email address is required to send confirmations and to identify the customer.
		$db = self::db();
		$db->query(
			$db->prepare(
				'UPDATE %i SET is_required = 1, required = 1, is_enabled = 1, is_active = 1, show_in_wizard = 1 WHERE scope = %s AND (field_key = %s OR name_key = %s)',
				self::table(),
				'customer',
				'email',
				'email'
			)
		);
		Cache::bump( 'catalog' );
		return $created;
	}

	/**
	 * Copies legacy column values into the current columns (idempotent).
	 *
	 * @return void
	 */
	public static function normalize_legacy_columns() {
		$db    = self::db();
		$table = self::table();
		// Rows written by the oldest releases only carried name_key/is_active.
		$db->query( $db->prepare( "UPDATE %i SET is_enabled = is_active WHERE (field_key IS NULL OR field_key = '') AND name_key IS NOT NULL AND name_key <> ''", $table ) );
		$db->query( $db->prepare( "UPDATE %i SET field_key = name_key WHERE (field_key IS NULL OR field_key = '') AND name_key IS NOT NULL AND name_key <> ''", $table ) );
		$db->query( $db->prepare( "UPDATE %i SET name_key = field_key WHERE (name_key IS NULL OR name_key = '') AND field_key <> ''", $table ) );
		$db->query( $db->prepare( "UPDATE %i SET options = options_json WHERE (options IS NULL OR options = '') AND options_json IS NOT NULL AND options_json <> ''", $table ) );
		$db->query( $db->prepare( "UPDATE %i SET options_json = options WHERE (options_json IS NULL OR options_json = '') AND options IS NOT NULL AND options <> ''", $table ) );
		$db->query( $db->prepare( 'UPDATE %i SET is_required = 1 WHERE required = 1 AND is_required = 0', $table ) );
		$db->query( $db->prepare( 'UPDATE %i SET required = is_required', $table ) );
		$db->query( $db->prepare( 'UPDATE %i SET is_active = is_enabled', $table ) );
		Cache::bump( 'catalog' );
	}
}
