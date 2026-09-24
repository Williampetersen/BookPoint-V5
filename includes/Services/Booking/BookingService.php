<?php
/**
 * The single source of truth for creating and changing bookings.
 *
 * Every entry point (booking wizard, admin, legacy endpoints, payment callbacks,
 * manage page) goes through this class, so validation, double-booking protection
 * and the public lifecycle actions always run.
 *
 * Public actions fired (all receive the booking ID first):
 *  - pointlybooking_booking_created( int $id, array $context )
 *  - pointlybooking_booking_status_changed( int $id, string $new, string $old, array $context )
 *  - pointlybooking_booking_rescheduled( int $id, array $old, array $context )
 *  - pointlybooking_booking_updated( int $id, array $changes, array $context )
 *  - pointlybooking_booking_paid( int $id, string $provider, string $reference )
 *  - pointlybooking_booking_deleted( int $id, array $booking )
 *  - pointlybooking_customer_created( int $customer_id, array $context )
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Services\Booking;

use PointlyBooking\Repositories\BookingRepository;
use PointlyBooking\Repositories\CustomerRepository;
use PointlyBooking\Repositories\FieldValueRepository;
use PointlyBooking\Repositories\FormFieldRepository;
use PointlyBooking\Repositories\LocationRepository;
use PointlyBooking\Repositories\PromoCodeRepository;
use PointlyBooking\Repositories\Relations;
use PointlyBooking\Repositories\ServiceRepository;
use PointlyBooking\Services\Availability\AvailabilityService;
use PointlyBooking\Services\Payments\PaymentMethods;
use PointlyBooking\Services\Pricing\PricingService;
use PointlyBooking\Settings\Settings;
use PointlyBooking\Support\Cache;
use PointlyBooking\Support\Dates;
use PointlyBooking\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * Booking lifecycle.
 */
final class BookingService {

	/**
	 * Creates a booking.
	 *
	 * @param array $input {
	 *     @type int    $service_id     Required.
	 *     @type int    $agent_id       0 = any available staff member.
	 *     @type int    $location_id    Optional.
	 *     @type string $date           Y-m-d.
	 *     @type string $start          HH:MM.
	 *     @type int[]  $extras         Extra IDs.
	 *     @type string $promo_code     Promo code.
	 *     @type array  $fields         ['customer' => [...], 'booking' => [...]] custom field answers.
	 *     @type string $payment_method Requested method (wizard).
	 *     @type int    $customer_id    Existing customer (admin).
	 *     @type string $status         Status (admin).
	 *     @type string $notes          Internal notes (admin).
	 *     @type bool   $force          Skip availability checks (admin).
	 * }
	 * @param array $context {
	 *     @type string $source 'wizard'|'admin'|'legacy'.
	 *     @type bool   $notify Whether notifications should be sent (default true).
	 * }
	 * @return array|\WP_Error Created booking row.
	 */
	public static function create( array $input, array $context = array() ) {
		$source  = $context['source'] ?? 'wizard';
		$admin   = 'admin' === $source;
		$context = array_merge(
			array(
				'source' => $source,
				'notify' => true,
				'actor'  => $admin ? 'admin' : 'customer',
			),
			$context
		);

		$service_id  = absint( $input['service_id'] ?? 0 );
		$location_id = absint( $input['location_id'] ?? 0 );
		$date        = Sanitize::date( $input['date'] ?? '' );
		$start       = Sanitize::time( $input['start'] ?? ( $input['start_time'] ?? '' ) );
		$agent_id    = absint( $input['agent_id'] ?? 0 );
		$force       = $admin && ! empty( $input['force'] );

		$service = ServiceRepository::find( $service_id );
		if ( ! $service || ( ! $admin && ! $service['is_active'] ) ) {
			return new \WP_Error( 'invalid_service', __( 'Please choose a service.', 'pointly-booking' ), array( 'status' => 400 ) );
		}
		if ( '' === $date || '' === $start ) {
			return new \WP_Error( 'invalid_datetime', __( 'Please choose a date and time.', 'pointly-booking' ), array( 'status' => 400 ) );
		}
		if ( $location_id && ! LocationRepository::find( $location_id ) ) {
			$location_id = 0;
		}

		// Custom fields and customer details.
		$fields = self::clean_fields( $input['fields'] ?? array(), $input );
		if ( ! $admin ) {
			$missing = self::missing_required( $fields );
			if ( $missing ) {
				return new \WP_Error(
					'missing_fields',
					/* translators: %s: field label */
					sprintf( __( '%s is required.', 'pointly-booking' ), $missing['label'] ),
					array(
						'status' => 400,
						'field'  => $missing['scope'] . '.' . $missing['key'],
					)
				);
			}
		}

		$customer_id = absint( $input['customer_id'] ?? 0 );
		$email       = Sanitize::email( $fields['customer']['email'] ?? '' );
		if ( ! $customer_id && '' === $email ) {
			return new \WP_Error(
				'invalid_email',
				__( 'Please enter a valid email address.', 'pointly-booking' ),
				array(
					'status' => 400,
					'field'  => 'customer.email',
				)
			);
		}

		// Price.
		$quote = PricingService::quote( $service_id, Sanitize::ids( $input['extras'] ?? ( $input['extra_ids'] ?? array() ) ), (string) ( $input['promo_code'] ?? '' ) );
		if ( is_wp_error( $quote ) ) {
			return $quote;
		}
		if ( ! $admin && '' !== trim( (string) ( $input['promo_code'] ?? '' ) ) && ! $quote['promo_valid'] ) {
			return new \WP_Error(
				'invalid_promo',
				$quote['promo_message'] ? $quote['promo_message'] : __( 'This code is not valid.', 'pointly-booking' ),
				array(
					'status' => 400,
					'field'  => 'promo_code',
				)
			);
		}

		// Payment method and resulting statuses.
		$payment = self::payment_plan( $input, $quote['total'], $admin );
		if ( is_wp_error( $payment ) ) {
			return $payment;
		}

		// Double-booking protection: serialise concurrent requests for the same date,
		// then re-check availability against the database.
		$lock = self::lock( $date );
		if ( ! $lock ) {
			return new \WP_Error( 'busy', __( 'We are processing another booking for this day. Please try again in a moment.', 'pointly-booking' ), array( 'status' => 503 ) );
		}

		try {
			if ( $force ) {
				$assigned = $agent_id;
			} else {
				$assigned = AvailabilityService::resolve_slot(
					array(
						'service_id'  => $service_id,
						'agent_id'    => $agent_id,
						'location_id' => $location_id,
						'date'        => $date,
						'start'       => $start,
						'admin'       => $admin,
					)
				);
				if ( is_wp_error( $assigned ) ) {
					return $assigned;
				}
			}

			$customer = self::upsert_customer( $customer_id, $fields['customer'], $context );
			if ( is_wp_error( $customer ) ) {
				return $customer;
			}

			$start_min   = Dates::to_minutes( $start );
			$now         = Dates::now_mysql();
			$categories  = Relations::ids( 'service_categories', 'service_id', $service_id );
			$status      = $admin && ! empty( $input['status'] ) ? Sanitize::one_of( $input['status'], BookingRepository::STATUSES, $payment['status'] ) : $payment['status'];
			$booking_row = array(
				'service_id'           => $service_id,
				'customer_id'          => (int) $customer['id'],
				'agent_id'             => $assigned > 0 ? (int) $assigned : null,
				'location_id'          => $location_id ? $location_id : null,
				'category_id'          => $categories ? (int) $categories[0] : ( $service['category_id'] ? (int) $service['category_id'] : null ),
				'start_datetime'       => Dates::datetime( $date, $start_min ),
				'end_datetime'         => Dates::datetime( $date, $start_min + (int) $service['duration_minutes'] ),
				'status'               => $status,
				'notes'                => $admin ? Sanitize::textarea( $input['notes'] ?? '' ) : ( isset( $fields['booking']['notes'] ) ? Sanitize::textarea( $fields['booking']['notes'] ) : null ),
				'manage_key'           => BookingRepository::new_key(),
				'extras_json'          => $quote['extras'] ? wp_json_encode( $quote['extras'] ) : null,
				'promo_code'           => '' !== $quote['promo_code'] ? $quote['promo_code'] : null,
				'discount_total'       => (float) $quote['discount'],
				'total_price'          => (float) $quote['total'],
				'currency'             => $quote['currency'],
				'payment_method'       => $payment['method'],
				'payment_status'       => $payment['payment_status'],
				'payment_amount'       => (float) $quote['total'],
				'payment_currency'     => $quote['currency'],
				'customer_fields_json' => $fields['customer'] ? wp_json_encode( $fields['customer'] ) : null,
				'booking_fields_json'  => $fields['booking'] ? wp_json_encode( $fields['booking'] ) : null,
				'custom_fields_json'   => $fields['booking'] ? wp_json_encode( $fields['booking'] ) : null,
				'created_at'           => $now,
				'updated_at'           => $now,
			);

			$booking_id = BookingRepository::insert( $booking_row );
		} finally {
			self::unlock( $date );
		}

		if ( ! $booking_id ) {
			return new \WP_Error( 'insert_failed', __( 'The booking could not be saved. Please try again.', 'pointly-booking' ), array( 'status' => 500 ) );
		}

		self::store_field_values( $booking_id, $fields );
		if ( $quote['promo_id'] ) {
			PromoCodeRepository::increment_use( $quote['promo_id'] );
		}
		Cache::bump( 'availability' );
		Cache::bump( 'dashboard' );

		$context['payment_method'] = $payment['method'];
		do_action( 'pointlybooking_booking_created', $booking_id, $context );

		return BookingRepository::find( $booking_id );
	}

	/**
	 * Decides payment method, booking status and payment status for a new booking.
	 *
	 * @param array $input Input.
	 * @param float $total Booking total.
	 * @param bool  $admin Admin context.
	 * @return array|\WP_Error {method,status,payment_status}
	 */
	private static function payment_plan( array $input, $total, $admin ) {
		$default_status = (string) Settings::get( 'pointlybooking_default_booking_status', 'pending' );
		if ( ! in_array( $default_status, array( 'pending', 'confirmed' ), true ) ) {
			$default_status = 'pending';
		}

		if ( $admin ) {
			$method = Sanitize::one_of( $input['payment_method'] ?? 'cash', PaymentMethods::ALL, 'cash' );
			return array(
				'method'         => $method,
				'status'         => $default_status,
				'payment_status' => Sanitize::one_of( $input['payment_status'] ?? ( $total > 0 ? 'unpaid' : 'paid' ), array( 'unpaid', 'paid', 'pending', 'refunded' ), 'unpaid' ),
			);
		}

		if ( $total <= 0 ) {
			return array(
				'method'         => 'free',
				'status'         => $default_status,
				'payment_status' => 'paid',
			);
		}

		if ( ! PaymentMethods::enabled() ) {
			return array(
				'method'         => 'cash',
				'status'         => $default_status,
				'payment_status' => 'unpaid',
			);
		}

		$available = PaymentMethods::available();
		$requested = Sanitize::key( $input['payment_method'] ?? '' );
		if ( '' === $requested ) {
			$requested = PaymentMethods::default_method();
		}
		if ( ! in_array( $requested, $available, true ) ) {
			return new \WP_Error(
				'payment_method_unavailable',
				__( 'Please choose one of the available payment options.', 'pointly-booking' ),
				array(
					'status' => 400,
					'field'  => 'payment_method',
				)
			);
		}

		if ( in_array( $requested, PaymentMethods::ONLINE, true ) ) {
			return array(
				'method'         => $requested,
				'status'         => Settings::bool( 'payments_require_payment_to_confirm' ) ? 'pending_payment' : $default_status,
				'payment_status' => 'unpaid',
			);
		}

		return array(
			'method'         => $requested,
			'status'         => $default_status,
			'payment_status' => 'free' === $requested ? 'paid' : 'unpaid',
		);
	}

	/**
	 * Normalises submitted custom fields into ['customer'=>[], 'booking'=>[]].
	 * Accepts the 3.0 shape, 2.x "customer_fields"/"booking_fields" and "field_values" (scope.key).
	 *
	 * @param mixed $fields Submitted fields.
	 * @param array $input  Whole input (legacy keys).
	 * @return array
	 */
	public static function clean_fields( $fields, array $input = array() ) {
		$raw = array(
			'customer' => array(),
			'booking'  => array(),
		);
		if ( is_array( $fields ) ) {
			foreach ( array( 'customer', 'booking' ) as $scope ) {
				if ( isset( $fields[ $scope ] ) && is_array( $fields[ $scope ] ) ) {
					$raw[ $scope ] = $fields[ $scope ];
				}
			}
		}
		foreach ( array( 'customer', 'booking' ) as $scope ) {
			$legacy = $input[ $scope . '_fields' ] ?? null;
			if ( is_array( $legacy ) ) {
				$raw[ $scope ] = array_merge( $legacy, $raw[ $scope ] );
			}
		}
		if ( isset( $input['field_values'] ) && is_array( $input['field_values'] ) ) {
			foreach ( $input['field_values'] as $key => $value ) {
				$parts = explode( '.', (string) $key, 2 );
				if ( 2 === count( $parts ) && isset( $raw[ $parts[0] ] ) && ! isset( $raw[ $parts[0] ][ $parts[1] ] ) ) {
					$raw[ $parts[0] ][ $parts[1] ] = $value;
				}
			}
		}
		foreach ( array( 'first_name', 'last_name', 'email', 'phone' ) as $key ) {
			if ( ! isset( $raw['customer'][ $key ] ) && isset( $input[ $key ] ) ) {
				$raw['customer'][ $key ] = $input[ $key ];
			}
		}
		if ( ! isset( $raw['booking']['notes'] ) && isset( $input['customer_notes'] ) ) {
			$raw['booking']['notes'] = $input['customer_notes'];
		}

		$defs  = FormFieldRepository::all_fields();
		$types = array();
		foreach ( $defs as $def ) {
			$types[ $def['scope'] ][ $def['field_key'] ] = $def['type'];
		}

		$out = array(
			'customer' => array(),
			'booking'  => array(),
		);
		foreach ( $raw as $scope => $values ) {
			foreach ( $values as $key => $value ) {
				$key = Sanitize::key( $key );
				if ( '' === $key ) {
					continue;
				}
				$type = $types[ $scope ][ $key ] ?? ( 'email' === $key ? 'email' : ( 'notes' === $key ? 'textarea' : 'text' ) );
				$out[ $scope ][ $key ] = Sanitize::field_value( $value, $type );
			}
		}
		return $out;
	}

	/**
	 * First required wizard field without a value.
	 *
	 * @param array $fields Clean fields.
	 * @return array|null {scope,key,label}
	 */
	private static function missing_required( array $fields ) {
		foreach ( FormFieldRepository::wizard_fields() as $scope => $defs ) {
			if ( 'form' === $scope ) {
				continue;
			}
			foreach ( $defs as $def ) {
				if ( empty( $def['is_required'] ) ) {
					continue;
				}
				$value = $fields[ $scope ][ $def['field_key'] ] ?? null;
				$empty = null === $value || ( is_string( $value ) && '' === trim( $value ) ) || ( is_array( $value ) && ! $value ) || ( 'checkbox' === $def['type'] && '1' !== (string) $value );
				if ( $empty ) {
					return array(
						'scope' => $scope,
						'key'   => $def['field_key'],
						'label' => $def['label'],
					);
				}
			}
		}
		return null;
	}

	/**
	 * Finds or creates the customer. Existing profiles are only filled in, never
	 * overwritten by a public submission (prevents profile tampering by email).
	 *
	 * @param int   $customer_id Existing ID (admin).
	 * @param array $data        Customer field answers.
	 * @param array $context     Context.
	 * @return array|\WP_Error
	 */
	private static function upsert_customer( $customer_id, array $data, array $context ) {
		$admin = 'admin' === ( $context['source'] ?? '' );
		if ( $customer_id ) {
			$customer = CustomerRepository::find( $customer_id );
			if ( ! $customer ) {
				return new \WP_Error( 'invalid_customer', __( 'That customer no longer exists.', 'pointly-booking' ), array( 'status' => 400 ) );
			}
			return $customer;
		}

		$email = Sanitize::email( $data['email'] ?? '' );
		$core  = array(
			'first_name' => Sanitize::text( $data['first_name'] ?? '', 190 ),
			'last_name'  => Sanitize::text( $data['last_name'] ?? '', 190 ),
			'phone'      => Sanitize::text( $data['phone'] ?? '', 50 ),
		);
		$extra = array_diff_key( $data, array_flip( array( 'first_name', 'last_name', 'email', 'phone' ) ) );

		$existing = CustomerRepository::find_by_email( $email );
		if ( $existing ) {
			$update = array();
			foreach ( $core as $key => $value ) {
				if ( '' !== $value && ( $admin || '' === trim( (string) ( $existing[ $key ] ?? '' ) ) ) ) {
					$update[ $key ] = $value;
				}
			}
			if ( $extra ) {
				$update['custom_fields_json'] = wp_json_encode( array_merge( $existing['custom_fields'], $extra ) );
			}
			if ( is_user_logged_in() && empty( $existing['wp_user_id'] ) && wp_get_current_user()->user_email === $email ) {
				$update['wp_user_id'] = get_current_user_id();
			}
			if ( $update ) {
				$update['updated_at'] = Dates::now_mysql();
				CustomerRepository::update( $existing['id'], $update );
			}
			return CustomerRepository::find( $existing['id'] );
		}

		$now = Dates::now_mysql();
		$id  = CustomerRepository::insert(
			array_merge(
				$core,
				array(
					'email'              => $email,
					'custom_fields_json' => $extra ? wp_json_encode( $extra ) : null,
					'wp_user_id'         => ( is_user_logged_in() && ! $admin ) ? get_current_user_id() : null,
					'created_at'         => $now,
					'updated_at'         => $now,
				)
			)
		);
		if ( ! $id ) {
			return new \WP_Error( 'customer_failed', __( 'Your details could not be saved. Please try again.', 'pointly-booking' ), array( 'status' => 500 ) );
		}
		do_action( 'pointlybooking_customer_created', $id, $context );
		return CustomerRepository::find( $id );
	}

	/**
	 * Stores answers in the per-booking field values table.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $fields     Clean fields.
	 * @return void
	 */
	private static function store_field_values( $booking_id, array $fields ) {
		foreach ( FormFieldRepository::all_fields() as $def ) {
			if ( ! isset( $fields[ $def['scope'] ] ) || ! array_key_exists( $def['field_key'], $fields[ $def['scope'] ] ) ) {
				continue;
			}
			FieldValueRepository::upsert( 'booking', $booking_id, $def, $fields[ $def['scope'] ][ $def['field_key'] ] );
		}
	}

	/**
	 * Changes the status of a booking and fires the lifecycle actions.
	 *
	 * @param int    $id      Booking ID.
	 * @param string $status  New status.
	 * @param array  $context Context (source, notify, actor).
	 * @return array|\WP_Error
	 */
	public static function set_status( $id, $status, array $context = array() ) {
		$booking = BookingRepository::find( $id );
		if ( ! $booking ) {
			return new \WP_Error( 'not_found', __( 'Booking not found.', 'pointly-booking' ), array( 'status' => 404 ) );
		}
		if ( ! in_array( $status, BookingRepository::STATUSES, true ) ) {
			return new \WP_Error( 'invalid_status', __( 'Unknown booking status.', 'pointly-booking' ), array( 'status' => 400 ) );
		}
		$old = (string) $booking['status'];
		if ( $old === $status ) {
			return $booking;
		}

		// Re-activating a cancelled booking must not create a double booking.
		if ( in_array( $old, BookingRepository::FREEING_STATUSES, true ) && ! in_array( $status, BookingRepository::FREEING_STATUSES, true ) && empty( $context['force'] ) ) {
			$check = AvailabilityService::resolve_slot(
				array(
					'service_id'  => (int) $booking['service_id'],
					'agent_id'    => (int) $booking['agent_id'],
					'location_id' => (int) $booking['location_id'],
					'date'        => substr( $booking['start_datetime'], 0, 10 ),
					'start'       => substr( $booking['start_datetime'], 11, 5 ),
					'exclude_id'  => (int) $booking['id'],
					'admin'       => true,
				)
			);
			if ( is_wp_error( $check ) ) {
				return new \WP_Error( 'slot_taken', __( 'This time is no longer free, so the booking cannot be re-activated. Reschedule it instead.', 'pointly-booking' ), array( 'status' => 409 ) );
			}
		}

		BookingRepository::update(
			$id,
			array_merge(
				array(
					'status'     => $status,
					'updated_at' => Dates::now_mysql(),
				),
				self::key_rotation( $context )
			)
		);
		Cache::bump( 'availability' );
		Cache::bump( 'dashboard' );

		$context = array_merge(
			array(
				'source' => 'admin',
				'notify' => true,
				'actor'  => 'admin',
			),
			$context
		);
		do_action( 'pointlybooking_booking_status_changed', (int) $id, $status, $old, $context );
		return BookingRepository::find( $id );
	}

	/**
	 * Columns that rotate the manage key after a customer action (context rotate_key),
	 * written together with the change so notifications already carry the new link.
	 *
	 * @param array $context Context.
	 * @return array
	 */
	private static function key_rotation( array $context ) {
		if ( empty( $context['rotate_key'] ) ) {
			return array();
		}
		return array(
			'manage_key'                => BookingRepository::new_key(),
			'manage_token_last_used_at' => Dates::now_mysql(),
		);
	}

	/**
	 * Moves a booking to a new date/time (and optionally another staff member).
	 *
	 * @param int    $id       Booking ID.
	 * @param string $date     Y-m-d.
	 * @param string $start    HH:MM.
	 * @param int    $agent_id Staff (-1 keeps the current one, 0 = any available).
	 * @param array  $context  Context (source, notify, actor, force).
	 * @return array|\WP_Error
	 */
	public static function reschedule( $id, $date, $start, $agent_id = -1, array $context = array() ) {
		$booking = BookingRepository::find( $id );
		if ( ! $booking ) {
			return new \WP_Error( 'not_found', __( 'Booking not found.', 'pointly-booking' ), array( 'status' => 404 ) );
		}
		$date  = Sanitize::date( $date );
		$start = Sanitize::time( $start );
		if ( '' === $date || '' === $start ) {
			return new \WP_Error( 'invalid_datetime', __( 'Please choose a valid date and time.', 'pointly-booking' ), array( 'status' => 400 ) );
		}
		$admin  = 'admin' === ( $context['source'] ?? 'admin' );
		$target = $agent_id < 0 ? (int) $booking['agent_id'] : (int) $agent_id;

		$service = ServiceRepository::find( (int) $booking['service_id'] );
		if ( ! $service ) {
			return new \WP_Error( 'invalid_service', __( 'The booked service no longer exists.', 'pointly-booking' ), array( 'status' => 400 ) );
		}

		$lock = self::lock( $date );
		if ( ! $lock ) {
			return new \WP_Error( 'busy', __( 'Please try again in a moment.', 'pointly-booking' ), array( 'status' => 503 ) );
		}
		try {
			if ( $admin && ! empty( $context['force'] ) ) {
				$assigned = $target;
			} else {
				$assigned = AvailabilityService::resolve_slot(
					array(
						'service_id'  => (int) $booking['service_id'],
						'agent_id'    => $target,
						'location_id' => (int) $booking['location_id'],
						'date'        => $date,
						'start'       => $start,
						'exclude_id'  => (int) $booking['id'],
						'admin'       => $admin,
					)
				);
				if ( is_wp_error( $assigned ) ) {
					return $assigned;
				}
			}

			$start_min = Dates::to_minutes( $start );
			BookingRepository::update(
				$id,
				array_merge(
					array(
						'start_datetime' => Dates::datetime( $date, $start_min ),
						'end_datetime'   => Dates::datetime( $date, $start_min + (int) $service['duration_minutes'] ),
						'agent_id'       => $assigned > 0 ? (int) $assigned : null,
						'updated_at'     => Dates::now_mysql(),
					),
					self::key_rotation( $context )
				)
			);
		} finally {
			self::unlock( $date );
		}

		Cache::bump( 'availability' );
		Cache::bump( 'dashboard' );
		$context = array_merge(
			array(
				'source' => 'admin',
				'notify' => true,
				'actor'  => $admin ? 'admin' : 'customer',
			),
			$context
		);
		do_action(
			'pointlybooking_booking_rescheduled',
			(int) $id,
			array(
				'start_datetime' => $booking['start_datetime'],
				'end_datetime'   => $booking['end_datetime'],
				'agent_id'       => $booking['agent_id'],
			),
			$context
		);
		return BookingRepository::find( $id );
	}

	/**
	 * Updates notes and custom field answers.
	 *
	 * @param int   $id      Booking ID.
	 * @param array $data    notes, fields.
	 * @param array $context Context.
	 * @return array|\WP_Error
	 */
	public static function update_details( $id, array $data, array $context = array() ) {
		$booking = BookingRepository::find( $id );
		if ( ! $booking ) {
			return new \WP_Error( 'not_found', __( 'Booking not found.', 'pointly-booking' ), array( 'status' => 404 ) );
		}
		$update  = array();
		$changes = array();
		if ( array_key_exists( 'notes', $data ) ) {
			$update['notes']  = Sanitize::textarea( $data['notes'] );
			$changes['notes'] = true;
		}
		if ( array_key_exists( 'payment_status', $data ) ) {
			$update['payment_status']  = Sanitize::one_of( $data['payment_status'], array( 'unpaid', 'paid', 'pending', 'refunded', 'cancelled' ), (string) $booking['payment_status'] );
			$changes['payment_status'] = true;
		}
		if ( isset( $data['fields'] ) && is_array( $data['fields'] ) ) {
			$fields = self::clean_fields( $data['fields'] );
			if ( $fields['booking'] ) {
				$merged                        = array_merge( BookingRepository::json( $booking['booking_fields_json'] ), $fields['booking'] );
				$update['booking_fields_json'] = wp_json_encode( $merged );
				$update['custom_fields_json']  = $update['booking_fields_json'];
			}
			if ( $fields['customer'] ) {
				$merged                         = array_merge( BookingRepository::json( $booking['customer_fields_json'] ), $fields['customer'] );
				$update['customer_fields_json'] = wp_json_encode( $merged );
			}
			self::store_field_values( (int) $id, $fields );
			$changes['fields'] = true;
		}
		if ( ! $update ) {
			return $booking;
		}
		$update['updated_at'] = Dates::now_mysql();
		BookingRepository::update( $id, $update );
		do_action( 'pointlybooking_booking_updated', (int) $id, $changes, $context );
		return BookingRepository::find( $id );
	}

	/**
	 * Deletes a booking permanently.
	 *
	 * @param int $id Booking ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		$booking = BookingRepository::find( $id );
		if ( ! $booking ) {
			return false;
		}
		FieldValueRepository::delete_for_entity( 'booking', $id );
		$ok = BookingRepository::delete( $id );
		Cache::bump( 'availability' );
		Cache::bump( 'dashboard' );
		do_action( 'pointlybooking_booking_deleted', (int) $id, $booking );
		return $ok;
	}

	/**
	 * Records a successful payment and confirms the booking.
	 *
	 * @param int    $id        Booking ID.
	 * @param string $provider  stripe|paypal|woocommerce.
	 * @param string $reference Provider reference.
	 * @return array|\WP_Error
	 */
	public static function mark_paid( $id, $provider, $reference = '' ) {
		$booking = BookingRepository::find( $id );
		if ( ! $booking ) {
			return new \WP_Error( 'not_found', __( 'Booking not found.', 'pointly-booking' ), array( 'status' => 404 ) );
		}
		if ( 'paid' === $booking['payment_status'] ) {
			return $booking;
		}
		BookingRepository::update(
			$id,
			array(
				'payment_status'       => 'paid',
				'payment_method'       => Sanitize::key( $provider ),
				'payment_provider_ref' => '' !== $reference ? Sanitize::text( $reference, 190 ) : $booking['payment_provider_ref'],
				'updated_at'           => Dates::now_mysql(),
			)
		);
		do_action( 'pointlybooking_booking_paid', (int) $id, (string) $provider, (string) $reference );

		if ( in_array( $booking['status'], array( 'pending_payment', 'pending', 'failed_payment' ), true ) ) {
			return self::set_status(
				$id,
				'confirmed',
				array(
					'source' => 'payment',
					'actor'  => 'system',
					'force'  => 'failed_payment' !== $booking['status'],
				)
			);
		}
		return BookingRepository::find( $id );
	}

	/**
	 * Marks abandoned online-payment bookings as failed so they stop holding slots.
	 *
	 * @return int Number of bookings expired.
	 */
	public static function expire_stale_payments() {
		$ids = BookingRepository::stale_pending_payment_ids( AvailabilityService::stale_pending_cutoff() );
		foreach ( $ids as $id ) {
			BookingRepository::update(
				$id,
				array(
					'status'         => 'failed_payment',
					'payment_status' => 'expired',
					'updated_at'     => Dates::now_mysql(),
				)
			);
			do_action(
				'pointlybooking_booking_status_changed',
				$id,
				'failed_payment',
				'pending_payment',
				array(
					'source' => 'system',
					'notify' => false,
					'actor'  => 'system',
				)
			);
		}
		if ( $ids ) {
			Cache::bump( 'availability' );
		}
		return count( $ids );
	}

	/**
	 * Acquires a MySQL named lock for a booking date.
	 *
	 * @param string $date Y-m-d.
	 * @return bool
	 */
	private static function lock( $date ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Named locks cannot be cached.
		return 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', self::lock_name( $date ) ) );
	}

	/**
	 * Releases the named lock.
	 *
	 * @param string $date Y-m-d.
	 * @return void
	 */
	private static function unlock( $date ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Named locks cannot be cached.
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', self::lock_name( $date ) ) );
	}

	/**
	 * Lock name scoped to site and date.
	 *
	 * @param string $date Y-m-d.
	 * @return string
	 */
	private static function lock_name( $date ) {
		global $wpdb;
		return 'pbk_' . md5( $wpdb->prefix . '|' . $date );
	}
}
