<?php
/**
 * Admin form fields API.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Rest\Admin;

use PointlyBooking\Repositories\FormFieldRepository;
use PointlyBooking\Rest\Controller;
use PointlyBooking\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * /admin/form-fields…
 */
final class FormFieldsController extends Controller {

	/**
	 * {@inheritDoc}
	 */
	public function register_routes() {
		$read   = self::cap( 'pointlybooking_manage_settings', 'pointlybooking_manage_bookings', 'pointlybooking_manage_customers' );
		$manage = self::cap( 'pointlybooking_manage_settings' );

		$this->route(
			'/admin/form-fields',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => $read,
					'args'                => array( 'scope' => self::arg( 'key' ) ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save' ),
					'permission_callback' => $manage,
				),
			)
		);
		$this->route(
			'/admin/form-fields/all',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'all' ),
				'permission_callback' => $read,
			)
		);
		$this->route(
			'/admin/form-fields/reorder',
			array(
				'methods'             => array( 'POST', 'PUT' ),
				'callback'            => array( $this, 'reorder' ),
				'permission_callback' => $manage,
			)
		);
		$this->route(
			'/admin/form-fields/reseed',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reseed' ),
				'permission_callback' => $manage,
			)
		);
		$this->route(
			'/admin/form-fields/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => $read,
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete' ),
					'permission_callback' => $manage,
				),
			)
		);
	}

	/**
	 * Fields, optionally for one scope.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function index( \WP_REST_Request $r ) {
		$scope = (string) $r->get_param( 'scope' );
		return $this->ok( FormFieldRepository::all_fields( in_array( $scope, FormFieldRepository::SCOPES, true ) ? $scope : '' ) );
	}

	/**
	 * Fields grouped by scope.
	 *
	 * @return \WP_REST_Response
	 */
	public function all() {
		$out = array_fill_keys( FormFieldRepository::SCOPES, array() );
		foreach ( FormFieldRepository::all_fields() as $field ) {
			$out[ $field['scope'] ][] = $field;
		}
		return $this->ok( $out );
	}

	/**
	 * One field.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show( \WP_REST_Request $r ) {
		$field = $this->find( (int) $r['id'] );
		return $field ? $this->ok( $field ) : $this->error( 'not_found', __( 'Field not found.', 'pointly-booking' ), 404 );
	}

	/**
	 * Hydrated field by ID.
	 *
	 * @param int $id Field ID.
	 * @return array|null
	 */
	private function find( $id ) {
		foreach ( FormFieldRepository::all_fields() as $field ) {
			if ( $field['id'] === $id ) {
				return $field;
			}
		}
		return null;
	}

	/**
	 * Create or update a field.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save( \WP_REST_Request $r ) {
		$id       = (int) ( $r['id'] ?? 0 );
		$body     = $this->body( $r );
		$existing = $id ? $this->find( $id ) : null;
		if ( $id && ! $existing ) {
			return $this->error( 'not_found', __( 'Field not found.', 'pointly-booking' ), 404 );
		}
		$merged = array_merge( $existing ? $existing : array(), $body );

		$label = Sanitize::text( $merged['label'] ?? '', 190 );
		if ( '' === $label ) {
			return $this->error( 'invalid_label', __( 'Please enter a label.', 'pointly-booking' ), 400, array( 'field' => 'label' ) );
		}

		$scope = Sanitize::one_of( $merged['scope'] ?? 'booking', FormFieldRepository::SCOPES, 'booking' );
		$type  = Sanitize::one_of( $merged['type'] ?? 'text', FormFieldRepository::TYPES, 'text' );
		$key   = Sanitize::key( $merged['field_key'] ?? ( $merged['name_key'] ?? '' ) );
		if ( '' === $key ) {
			$key = Sanitize::key( str_replace( array( ' ', '-' ), '_', remove_accents( $label ) ) );
		}
		$key = substr( $key, 0, 80 );

		if ( $existing && $existing['is_core'] ) {
			// Built-in customer fields keep their identity.
			$scope = $existing['scope'];
			$key   = $existing['field_key'];
			$type  = $existing['type'];
		}
		if ( '' === $key ) {
			return $this->error( 'invalid_key', __( 'Please enter a key using letters, numbers and underscores.', 'pointly-booking' ), 400, array( 'field' => 'field_key' ) );
		}
		if ( FormFieldRepository::key_exists( $key, $scope, $id ) ) {
			return $this->error( 'duplicate_key', __( 'Another field already uses this key.', 'pointly-booking' ), 409, array( 'field' => 'field_key' ) );
		}

		$options = array();
		foreach ( (array) ( $merged['options'] ?? array() ) as $option ) {
			if ( is_array( $option ) ) {
				$opt_label = Sanitize::text( $option['label'] ?? ( $option['value'] ?? '' ), 190 );
				$opt_value = Sanitize::text( $option['value'] ?? $opt_label, 190 );
			} else {
				$opt_label = Sanitize::text( $option, 190 );
				$opt_value = $opt_label;
			}
			if ( '' !== $opt_label ) {
				$options[] = array(
					'label' => $opt_label,
					'value' => '' !== $opt_value ? $opt_value : $opt_label,
				);
			}
		}
		if ( in_array( $type, array( 'select', 'radio' ), true ) && ! $options ) {
			return $this->error( 'invalid_options', __( 'Add at least one option.', 'pointly-booking' ), 400, array( 'field' => 'options' ) );
		}

		$data = array(
			'field_key'      => $key,
			'label'          => $label,
			'type'           => $type,
			'scope'          => $scope,
			'step_key'       => Sanitize::one_of( $merged['step_key'] ?? 'details', array( 'details', 'payment', 'summary' ), 'details' ),
			'placeholder'    => Sanitize::text( $merged['placeholder'] ?? '', 190 ),
			'options'        => in_array( $type, array( 'select', 'radio', 'checkbox' ), true ) ? $options : array(),
			'is_required'    => Sanitize::bool01( $merged['is_required'] ?? ( $merged['required'] ?? 0 ) ),
			'is_enabled'     => Sanitize::bool01( $merged['is_enabled'] ?? ( $merged['is_active'] ?? 1 ) ),
			'show_in_wizard' => Sanitize::bool01( $merged['show_in_wizard'] ?? 1 ),
			'sort_order'     => (int) ( $merged['sort_order'] ?? 0 ),
		);
		if ( 'customer' === $scope && 'email' === $key ) {
			// Confirmations and the customer record depend on the email address.
			$data['is_required']    = 1;
			$data['is_enabled']     = 1;
			$data['show_in_wizard'] = 1;
		}
		if ( ! $id && ! array_key_exists( 'sort_order', $body ) ) {
			$max = 0;
			foreach ( FormFieldRepository::all_fields( $scope ) as $field ) {
				$max = max( $max, $field['sort_order'] );
			}
			$data['sort_order'] = $max + 10;
		}

		if ( $id ) {
			FormFieldRepository::save( $id, $data );
		} else {
			$id = FormFieldRepository::create( $data );
			if ( ! $id ) {
				return $this->error( 'save_failed', __( 'The field could not be saved.', 'pointly-booking' ), 500 );
			}
		}
		return $this->ok( $this->find( $id ), $existing ? 200 : 201 );
	}

	/**
	 * Deletes a field. The email field cannot be deleted.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete( \WP_REST_Request $r ) {
		$field = $this->find( (int) $r['id'] );
		if ( ! $field ) {
			return $this->error( 'not_found', __( 'Field not found.', 'pointly-booking' ), 404 );
		}
		if ( 'customer' === $field['scope'] && 'email' === $field['field_key'] ) {
			return $this->error( 'protected_field', __( 'The email field is required for confirmations and cannot be deleted.', 'pointly-booking' ), 400 );
		}
		FormFieldRepository::remove( $field['id'] );
		return $this->ok( array( 'deleted' => true ) );
	}

	/**
	 * Applies an order ({ ids: [] } or 2.x { order: [ {id, sort_order} ] }).
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function reorder( \WP_REST_Request $r ) {
		$body = $this->body( $r );
		$ids  = array();
		if ( isset( $body['ids'] ) ) {
			$ids = Sanitize::ids( $body['ids'] );
		} elseif ( isset( $body['order'] ) && is_array( $body['order'] ) ) {
			$rows = array_filter( $body['order'], 'is_array' );
			usort(
				$rows,
				static function ( $a, $b ) {
					return (int) ( $a['sort_order'] ?? 0 ) <=> (int) ( $b['sort_order'] ?? 0 );
				}
			);
			$ids = Sanitize::ids( wp_list_pluck( $rows, 'id' ) );
		}
		FormFieldRepository::reorder( $ids );
		return $this->ok( array( 'saved' => true ) );
	}

	/**
	 * Restores missing built-in fields.
	 *
	 * @return \WP_REST_Response
	 */
	public function reseed() {
		$created = FormFieldRepository::ensure_defaults();
		return $this->ok(
			array(
				'created' => $created,
				'fields'  => FormFieldRepository::all_fields(),
			)
		);
	}
}
