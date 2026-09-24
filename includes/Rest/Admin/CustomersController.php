<?php
/**
 * Admin customers and field values API.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Rest\Admin;

use PointlyBooking\Repositories\BookingRepository;
use PointlyBooking\Repositories\CustomerRepository;
use PointlyBooking\Repositories\FieldValueRepository;
use PointlyBooking\Repositories\FormFieldRepository;
use PointlyBooking\Rest\Controller;
use PointlyBooking\Rest\Presenter;
use PointlyBooking\Services\Audit;
use PointlyBooking\Support\Dates;
use PointlyBooking\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * /admin/customers…, /admin/field-values.
 */
final class CustomersController extends Controller {

	/**
	 * {@inheritDoc}
	 */
	public function register_routes() {
		$can = self::cap( 'pointlybooking_manage_customers' );

		$this->route(
			'/admin/customers',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => self::cap( 'pointlybooking_manage_customers', 'pointlybooking_manage_bookings' ),
					'args'                => self::paging_args(),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save' ),
					'permission_callback' => $can,
				),
			)
		);
		$this->route(
			'/admin/customers/form-fields',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'form_fields' ),
				'permission_callback' => self::cap( 'pointlybooking_manage_customers', 'pointlybooking_manage_bookings' ),
			)
		);
		$this->route(
			'/admin/customers/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => self::cap( 'pointlybooking_manage_customers', 'pointlybooking_manage_bookings' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save' ),
					'permission_callback' => $can,
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete' ),
					'permission_callback' => $can,
				),
			)
		);
		$this->route(
			'/admin/customers/(?P<id>\d+)/anonymize',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'anonymize' ),
				'permission_callback' => $can,
			)
		);
		$this->route(
			'/admin/field-values',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'field_values' ),
					'permission_callback' => self::cap( 'pointlybooking_manage_customers', 'pointlybooking_manage_bookings' ),
					'args'                => array(
						'entity_type' => self::arg( 'string', true, array( 'enum' => array( 'booking', 'customer' ) ) ),
						'entity_id'   => self::arg( 'id', true ),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'field_values_save' ),
					'permission_callback' => self::cap( 'pointlybooking_manage_customers', 'pointlybooking_manage_bookings' ),
				),
			)
		);
	}

	/**
	 * Customer list.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function index( \WP_REST_Request $r ) {
		$sort    = (string) $r->get_param( 'sort' );
		$orderby = (string) $r->get_param( 'orderby' );
		$order   = (string) $r->get_param( 'order' );
		if ( 'earliest' === $sort ) {
			$orderby = 'created';
			$order   = 'asc';
		}
		$result = CustomerRepository::search(
			array(
				'search'   => (string) ( $r->get_param( 'search' ) ?? $r->get_param( 'q' ) ?? '' ),
				'orderby'  => '' !== $orderby ? $orderby : 'created',
				'order'    => '' !== $order ? $order : 'desc',
				'page'     => (int) $r->get_param( 'page' ),
				'per_page' => (int) $r->get_param( 'per_page' ),
			)
		);
		return $this->ok(
			array(
				'items'    => array_map( array( Presenter::class, 'customer' ), $result['items'] ),
				'total'    => $result['total'],
				'page'     => max( 1, (int) $r->get_param( 'page' ) ),
				'per_page' => max( 1, (int) $r->get_param( 'per_page' ) ),
			)
		);
	}

	/**
	 * Customer detail with booking history and custom fields.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show( \WP_REST_Request $r ) {
		$customer = CustomerRepository::find( (int) $r['id'] );
		if ( ! $customer ) {
			return $this->error( 'not_found', __( 'Customer not found.', 'pointly-booking' ), 404 );
		}
		$bookings = BookingRepository::for_customer( $customer['id'] );
		$spent    = 0.0;
		foreach ( $bookings as $b ) {
			if ( ! in_array( $b['status'], BookingRepository::FREEING_STATUSES, true ) ) {
				$spent += (float) $b['total_price'];
			}
		}
		$customer['bookings_count'] = count( $bookings );
		$customer['total_spent']    = round( $spent, 2 );
		$customer['last_booking']   = $bookings ? $bookings[0]['start_datetime'] : '';

		$data             = Presenter::customer( $customer );
		$data['bookings'] = array_map( array( Presenter::class, 'booking_item' ), $bookings );
		$data['fields']   = FormFieldRepository::all_fields( 'customer' );
		return $this->ok( $data );
	}

	/**
	 * Customer-scope custom fields (for the edit form).
	 *
	 * @return \WP_REST_Response
	 */
	public function form_fields() {
		return $this->ok( FormFieldRepository::all_fields( 'customer' ) );
	}

	/**
	 * Create or update a customer.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save( \WP_REST_Request $r ) {
		$id   = (int) ( $r['id'] ?? 0 );
		$body = $this->body( $r );

		$existing = $id ? CustomerRepository::find( $id ) : null;
		if ( $id && ! $existing ) {
			return $this->error( 'not_found', __( 'Customer not found.', 'pointly-booking' ), 404 );
		}

		$data = array();
		foreach ( array( 'first_name', 'last_name' ) as $key ) {
			if ( array_key_exists( $key, $body ) ) {
				$data[ $key ] = Sanitize::text( $body[ $key ], 190 );
			}
		}
		if ( array_key_exists( 'phone', $body ) ) {
			$data['phone'] = Sanitize::text( $body['phone'], 50 );
		}
		if ( array_key_exists( 'email', $body ) || ! $id ) {
			$data['email'] = Sanitize::email( $body['email'] ?? '' );
			if ( '' === $data['email'] ) {
				return $this->error( 'invalid_email', __( 'Please enter a valid email address.', 'pointly-booking' ), 400, array( 'field' => 'email' ) );
			}
		}
		if ( ! $id && '' === trim( ( $data['first_name'] ?? '' ) . ( $data['last_name'] ?? '' ) ) ) {
			return $this->error( 'invalid_name', __( 'Please enter a name.', 'pointly-booking' ), 400, array( 'field' => 'first_name' ) );
		}

		// Create deduplicates by email (2.x behaviour): return the existing record, updated.
		if ( ! $id ) {
			$duplicate = CustomerRepository::find_by_email( $data['email'] );
			if ( $duplicate ) {
				$id       = $duplicate['id'];
				$existing = $duplicate;
			}
		} elseif ( isset( $data['email'] ) && strtolower( $data['email'] ) !== strtolower( (string) $existing['email'] ) ) {
			$other = CustomerRepository::find_by_email( $data['email'] );
			if ( $other && $other['id'] !== $id ) {
				return $this->error( 'duplicate_email', __( 'Another customer already uses this email address.', 'pointly-booking' ), 409, array( 'field' => 'email' ) );
			}
		}

		$custom = null;
		if ( isset( $body['custom_fields'] ) && is_array( $body['custom_fields'] ) ) {
			$custom = $this->clean_custom_fields( $body['custom_fields'] );
			if ( is_wp_error( $custom ) ) {
				return $custom;
			}
			$merged                     = array_merge( $existing ? (array) $existing['custom_fields'] : array(), $custom );
			$data['custom_fields_json'] = $merged ? wp_json_encode( $merged ) : null;
		}

		$data['updated_at'] = Dates::now_mysql();
		$created            = false;
		if ( $id ) {
			CustomerRepository::update( $id, $data );
		} else {
			$data['created_at'] = $data['updated_at'];
			$id                 = CustomerRepository::insert( $data );
			if ( ! $id ) {
				return $this->error( 'save_failed', __( 'The customer could not be saved.', 'pointly-booking' ), 500 );
			}
			$created = true;
			do_action( 'pointlybooking_customer_created', $id, array( 'source' => 'admin' ) );
		}

		if ( $custom ) {
			$this->store_values( 'customer', $id, $custom );
		}

		$request       = new \WP_REST_Request( 'GET' );
		$request['id'] = $id;
		$response      = $this->show( $request );
		if ( $created && ! is_wp_error( $response ) ) {
			$response->set_status( 201 );
		}
		return $response;
	}

	/**
	 * Validates custom field answers against the customer-scope definitions.
	 *
	 * @param array $values Key => value.
	 * @return array|\WP_Error
	 */
	private function clean_custom_fields( array $values ) {
		$out = array();
		foreach ( FormFieldRepository::all_fields( 'customer' ) as $field ) {
			if ( $field['is_core'] || ! array_key_exists( $field['field_key'], $values ) ) {
				continue;
			}
			$value = Sanitize::field_value( $values[ $field['field_key'] ], $field['type'] );
			if ( 'email' === $field['type'] && '' === $value && '' !== trim( (string) $values[ $field['field_key'] ] ) ) {
				return $this->error(
					'invalid_field',
					/* translators: %s: field label */
					sprintf( __( '%s must be a valid email address.', 'pointly-booking' ), $field['label'] ),
					400,
					array( 'field' => $field['field_key'] )
				);
			}
			$out[ $field['field_key'] ] = $value;
		}
		return $out;
	}

	/**
	 * Mirrors answers into the field values table.
	 *
	 * @param string $type   Entity type.
	 * @param int    $id     Entity ID.
	 * @param array  $values Key => clean value.
	 * @return void
	 */
	private function store_values( $type, $id, array $values ) {
		$scope = 'customer' === $type ? 'customer' : '';
		foreach ( FormFieldRepository::all_fields( $scope ) as $field ) {
			if ( array_key_exists( $field['field_key'], $values ) ) {
				FieldValueRepository::upsert( $type, $id, $field, $values[ $field['field_key'] ] );
			}
		}
	}

	/**
	 * Deletes a customer. Booking history is kept.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete( \WP_REST_Request $r ) {
		$id = (int) $r['id'];
		if ( ! CustomerRepository::find( $id ) ) {
			return $this->error( 'not_found', __( 'Customer not found.', 'pointly-booking' ), 404 );
		}
		CustomerRepository::delete( $id );
		FieldValueRepository::delete_for_entity( 'customer', $id );
		Audit::log( 'customer_deleted', array( 'customer_id' => $id ) );
		return $this->ok( array( 'deleted' => true ) );
	}

	/**
	 * GDPR erase: anonymises personal data and removes custom answers.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function anonymize( \WP_REST_Request $r ) {
		$id = (int) $r['id'];
		if ( ! CustomerRepository::find( $id ) ) {
			return $this->error( 'not_found', __( 'Customer not found.', 'pointly-booking' ), 404 );
		}
		CustomerRepository::anonymize( $id );
		FieldValueRepository::delete_for_entity( 'customer', $id );
		Audit::log( 'customer_gdpr_deleted', array( 'customer_id' => $id ) );
		return $this->ok( array( 'anonymized' => true ) );
	}

	/**
	 * Stored custom field values of a booking or customer.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function field_values( \WP_REST_Request $r ) {
		return $this->ok( FieldValueRepository::for_entity( (string) $r->get_param( 'entity_type' ), (int) $r->get_param( 'entity_id' ) ) );
	}

	/**
	 * Saves custom field values for a booking or customer (2.x: { entity_type, entity_id, values }).
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function field_values_save( \WP_REST_Request $r ) {
		$body   = $this->body( $r );
		$type   = Sanitize::one_of( $body['entity_type'] ?? '', array( 'booking', 'customer' ), '' );
		$id     = absint( $body['entity_id'] ?? 0 );
		$values = isset( $body['values'] ) && is_array( $body['values'] ) ? $body['values'] : array();
		if ( '' === $type || ! $id ) {
			return $this->error( 'invalid_entity', __( 'Invalid record.', 'pointly-booking' ) );
		}
		if ( 'customer' === $type && ! current_user_can( 'pointlybooking_manage_customers' ) && ! current_user_can( 'manage_options' ) ) {
			return $this->error( 'rest_forbidden', __( 'Sorry, you are not allowed to do that.', 'pointly-booking' ), 403 );
		}
		$fields = FormFieldRepository::all_fields( 'customer' === $type ? 'customer' : '' );
		foreach ( $fields as $field ) {
			if ( array_key_exists( $field['field_key'], $values ) ) {
				FieldValueRepository::upsert( $type, $id, $field, Sanitize::field_value( $values[ $field['field_key'] ], $field['type'] ) );
			}
		}
		return $this->ok( FieldValueRepository::for_entity( $type, $id ) );
	}
}
