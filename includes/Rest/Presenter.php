<?php
/**
 * Shapes database rows for REST responses (only the fields each UI needs).
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Rest;

use PointlyBooking\Repositories\BookingRepository;
use PointlyBooking\Repositories\FieldValueRepository;
use PointlyBooking\Repositories\FormFieldRepository;
use PointlyBooking\Repositories\Repository;
use PointlyBooking\Services\Notifications\Variables;
use PointlyBooking\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Presenters for every entity.
 */
final class Presenter {

	/**
	 * Booking row for lists and calendars.
	 *
	 * @param array $b Joined booking row.
	 * @return array
	 */
	public static function booking_item( array $b ) {
		return array(
			'id'             => (int) $b['id'],
			'status'         => (string) $b['status'],
			'start'          => (string) $b['start_datetime'],
			'end'            => (string) $b['end_datetime'],
			'service_id'     => (int) $b['service_id'],
			'service_name'   => (string) ( $b['service_name'] ?? '' ),
			'agent_id'       => (int) $b['agent_id'],
			'agent_name'     => trim( (string) ( $b['agent_name'] ?? '' ) ),
			'location_id'    => (int) ( $b['location_id'] ?? 0 ),
			'location_name'  => (string) ( $b['location_name'] ?? '' ),
			'customer_id'    => (int) $b['customer_id'],
			'customer_name'  => trim( (string) ( $b['customer_name'] ?? '' ) ),
			'customer_email' => (string) ( $b['customer_email'] ?? '' ),
			'customer_phone' => (string) ( $b['customer_phone'] ?? '' ),
			'total'          => (float) $b['total_price'],
			'currency'       => (string) $b['currency'],
			'payment_method' => (string) $b['payment_method'],
			'payment_status' => (string) $b['payment_status'],
			'created_at'     => (string) $b['created_at'],
			'has_notes'      => '' !== trim( (string) ( $b['notes'] ?? '' ) ),
		);
	}

	/**
	 * Full booking detail for the admin drawer.
	 *
	 * @param array $b Joined booking row.
	 * @return array
	 */
	public static function booking_detail( array $b ) {
		$item = self::booking_item( $b );

		$answers = array();
		$values  = FieldValueRepository::for_entity( 'booking', (int) $b['id'] );
		if ( $values ) {
			foreach ( $values as $v ) {
				$answers[ $v['scope'] ][ $v['field_key'] ] = $v['value'];
			}
		} else {
			$answers['customer'] = BookingRepository::json( $b['customer_fields_json'] ?? '' );
			$answers['booking']  = array_merge( BookingRepository::json( $b['custom_fields_json'] ?? '' ), BookingRepository::json( $b['booking_fields_json'] ?? '' ) );
		}

		$labels = array();
		foreach ( FormFieldRepository::all_fields() as $field ) {
			$labels[ $field['scope'] ][ $field['field_key'] ] = array(
				'label' => $field['label'],
				'type'  => $field['type'],
			);
		}
		$responses = array();
		foreach ( array( 'customer', 'booking' ) as $scope ) {
			foreach ( (array) ( $answers[ $scope ] ?? array() ) as $key => $value ) {
				if ( 'customer' === $scope && in_array( $key, array( 'first_name', 'last_name', 'email', 'phone' ), true ) ) {
					continue;
				}
				if ( 'booking' === $scope && 'notes' === $key ) {
					continue;
				}
				$responses[] = array(
					'scope' => $scope,
					'key'   => (string) $key,
					'label' => $labels[ $scope ][ $key ]['label'] ?? ucwords( str_replace( '_', ' ', (string) $key ) ),
					'type'  => $labels[ $scope ][ $key ]['type'] ?? 'text',
					'value' => $value,
				);
			}
		}

		$extras = array();
		foreach ( BookingRepository::json( $b['extras_json'] ?? '' ) as $extra ) {
			if ( is_array( $extra ) ) {
				$extras[] = array(
					'id'    => (int) ( $extra['id'] ?? 0 ),
					'name'  => (string) ( $extra['name'] ?? '' ),
					'price' => (float) ( $extra['price'] ?? 0 ),
					'qty'   => max( 1, (int) ( $extra['qty'] ?? 1 ) ),
				);
			} elseif ( is_numeric( $extra ) ) {
				$extras[] = array(
					'id'    => (int) $extra,
					'name'  => '',
					'price' => 0.0,
					'qty'   => 1,
				);
			}
		}

		$customer_notes = (string) ( $answers['booking']['notes'] ?? '' );

		return array_merge(
			$item,
			array(
				'notes'           => (string) ( $b['notes'] ?? '' ),
				'customer_notes'  => $customer_notes !== (string) ( $b['notes'] ?? '' ) ? $customer_notes : '',
				'extras'          => $extras,
				'promo_code'      => (string) ( $b['promo_code'] ?? '' ),
				'discount'        => (float) $b['discount_total'],
				'subtotal'        => round( (float) $b['total_price'] + (float) $b['discount_total'], 2 ),
				'payment_ref'     => (string) ( $b['payment_provider_ref'] ?? '' ),
				'responses'       => $responses,
				'updated_at'      => (string) $b['updated_at'],
				'manage_url'      => Variables::manage_url( (string) $b['manage_key'] ),
				'agent_image'     => Repository::image_url( $b['agent_image_id'] ?? 0, 'thumbnail' ),
				'duration'        => max( 0, (int) ( ( strtotime( $b['end_datetime'] ) - strtotime( $b['start_datetime'] ) ) / 60 ) ),
				'start_formatted' => Dates::format_datetime( $b['start_datetime'] ),
			)
		);
	}

	/**
	 * What a customer may see about their own booking.
	 *
	 * @param array $b Joined booking row.
	 * @return array
	 */
	public static function booking_public( array $b ) {
		return array(
			'id'             => (int) $b['id'],
			'status'         => (string) $b['status'],
			'status_label'   => Variables::status_label( (string) $b['status'] ),
			'start'          => (string) $b['start_datetime'],
			'end'            => (string) $b['end_datetime'],
			'service_id'     => (int) $b['service_id'],
			'service_name'   => (string) ( $b['service_name'] ?? '' ),
			'agent_id'       => (int) $b['agent_id'],
			'agent_name'     => trim( (string) ( $b['agent_name'] ?? '' ) ),
			'location_id'    => (int) ( $b['location_id'] ?? 0 ),
			'location_name'  => (string) ( $b['location_name'] ?? '' ),
			'customer_name'  => trim( (string) ( $b['customer_name'] ?? '' ) ),
			'customer_email' => (string) ( $b['customer_email'] ?? '' ),
			'total'          => (float) $b['total_price'],
			'discount'       => (float) $b['discount_total'],
			'currency'       => (string) $b['currency'],
			'payment_method' => (string) $b['payment_method'],
			'payment_status' => (string) $b['payment_status'],
			'extras'         => array_values(
				array_map(
					static function ( $e ) {
						return array(
							'name'  => (string) ( $e['name'] ?? '' ),
							'price' => (float) ( $e['price'] ?? 0 ),
						);
					},
					array_filter( BookingRepository::json( $b['extras_json'] ?? '' ), 'is_array' )
				)
			),
			'key'            => (string) $b['manage_key'],
			'manage_url'     => Variables::manage_url( (string) $b['manage_key'] ),
			'ics_url'        => Variables::ics_url( (int) $b['id'], (string) $b['manage_key'] ),
			'timezone'       => wp_timezone_string(),
		);
	}

	/**
	 * Service for admin.
	 *
	 * @param array $s          Service row.
	 * @param array $categories Category IDs.
	 * @param array $agents     Agent IDs.
	 * @param array $extras     Extra IDs.
	 * @return array
	 */
	public static function service( array $s, array $categories = array(), array $agents = array(), array $extras = array() ) {
		return array(
			'id'                    => (int) $s['id'],
			'name'                  => (string) $s['name'],
			'description'           => (string) ( $s['description'] ?? '' ),
			'duration_minutes'      => (int) $s['duration_minutes'],
			'price'                 => (float) $s['price'],
			'price_cents'           => (int) $s['price_cents'],
			'currency'              => (string) $s['currency'],
			'is_active'             => (int) $s['is_active'],
			'image_id'              => (int) $s['image_id'],
			'image_url'             => Repository::image_url( $s['image_id'], 'medium' ),
			'sort_order'            => (int) $s['sort_order'],
			'buffer_before_minutes' => (int) $s['buffer_before_minutes'],
			'buffer_after_minutes'  => (int) $s['buffer_after_minutes'],
			'capacity'              => (int) $s['capacity'],
			'use_global_schedule'   => (int) $s['use_global_schedule'],
			'schedule_json'         => (string) ( $s['schedule_json'] ?? '' ),
			'category_ids'          => array_values( $categories ),
			'agent_ids'             => array_values( $agents ),
			'extra_ids'             => array_values( $extras ),
		);
	}

	/**
	 * Service for the booking wizard.
	 *
	 * @param array $s          Service row.
	 * @param array $categories Category IDs.
	 * @return array
	 */
	public static function service_public( array $s, array $categories = array() ) {
		return array(
			'id'           => (int) $s['id'],
			'name'         => (string) $s['name'],
			'description'  => wp_strip_all_tags( (string) ( $s['description'] ?? '' ) ),
			'duration'     => (int) $s['duration_minutes'],
			'price'        => (float) $s['price'],
			'capacity'     => (int) $s['capacity'],
			'image_url'    => Repository::image_url( $s['image_id'], 'medium' ),
			'category_ids' => array_values( $categories ),
		);
	}

	/**
	 * Category.
	 *
	 * @param array $c     Category row.
	 * @param int   $count Services in the category.
	 * @return array
	 */
	public static function category( array $c, $count = 0 ) {
		return array(
			'id'             => (int) $c['id'],
			'name'           => (string) $c['name'],
			'description'    => (string) $c['description'],
			'image_id'       => (int) $c['image_id'],
			'image_url'      => Repository::image_url( $c['image_id'], 'medium' ),
			'sort_order'     => (int) $c['sort_order'],
			'is_active'      => (int) $c['is_active'],
			'services_count' => (int) $count,
		);
	}

	/**
	 * Extra.
	 *
	 * @param array $e        Extra row.
	 * @param array $services Service IDs.
	 * @return array
	 */
	public static function extra( array $e, array $services = array() ) {
		return array(
			'id'           => (int) $e['id'],
			'name'         => (string) $e['name'],
			'description'  => (string) $e['description'],
			'price'        => (float) $e['price'],
			'duration_min' => (int) $e['duration_min'],
			'image_id'     => (int) $e['image_id'],
			'image_url'    => Repository::image_url( $e['image_id'], 'medium' ),
			'sort_order'   => (int) $e['sort_order'],
			'is_active'    => (int) $e['is_active'],
			'service_ids'  => array_values( $services ),
		);
	}

	/**
	 * Staff member for admin.
	 *
	 * @param array $a        Agent row.
	 * @param array $services Service IDs.
	 * @return array
	 */
	public static function agent( array $a, array $services = array() ) {
		return array(
			'id'            => (int) $a['id'],
			'first_name'    => (string) ( $a['first_name'] ?? '' ),
			'last_name'     => (string) ( $a['last_name'] ?? '' ),
			'name'          => (string) $a['name'],
			'email'         => (string) ( $a['email'] ?? '' ),
			'phone'         => (string) ( $a['phone'] ?? '' ),
			'is_active'     => (int) $a['is_active'],
			'image_id'      => (int) $a['image_id'],
			'image_url'     => Repository::image_url( $a['image_id'], 'thumbnail' ),
			'schedule_json' => (string) ( $a['schedule_json'] ?? '' ),
			'service_ids'   => array_values( $services ),
		);
	}

	/**
	 * Staff member for customers (no contact details).
	 *
	 * @param array $a Agent row.
	 * @return array
	 */
	public static function agent_public( array $a ) {
		return array(
			'id'        => (int) $a['id'],
			'name'      => (string) $a['name'],
			'image_url' => Repository::image_url( $a['image_id'], 'thumbnail' ),
		);
	}

	/**
	 * Location.
	 *
	 * @param array $l Location row.
	 * @return array
	 */
	public static function location( array $l ) {
		return array(
			'id'                  => (int) $l['id'],
			'name'                => (string) $l['name'],
			'address'             => (string) ( $l['address'] ?? '' ),
			'category_id'         => (int) $l['category_id'],
			'image_id'            => (int) $l['image_id'],
			'image_url'           => Repository::image_url( $l['image_id'], 'medium' ),
			'status'              => (string) $l['status'],
			'is_active'           => (int) $l['is_active'],
			'use_custom_schedule' => (int) $l['use_custom_schedule'],
			'schedule'            => array_values( (array) $l['schedule'] ),
		);
	}

	/**
	 * Customer.
	 *
	 * @param array $c Customer row.
	 * @return array
	 */
	public static function customer( array $c ) {
		return array(
			'id'             => (int) $c['id'],
			'first_name'     => (string) ( $c['first_name'] ?? '' ),
			'last_name'      => (string) ( $c['last_name'] ?? '' ),
			'name'           => (string) $c['name'],
			'email'          => (string) ( $c['email'] ?? '' ),
			'phone'          => (string) ( $c['phone'] ?? '' ),
			'wp_user_id'     => (int) $c['wp_user_id'],
			'custom_fields'  => (array) $c['custom_fields'],
			'created_at'     => (string) $c['created_at'],
			'updated_at'     => (string) $c['updated_at'],
			'bookings_count' => (int) ( $c['bookings_count'] ?? 0 ),
			'last_booking'   => (string) ( $c['last_booking'] ?? '' ),
			'total_spent'    => (float) ( $c['total_spent'] ?? 0 ),
		);
	}
}
