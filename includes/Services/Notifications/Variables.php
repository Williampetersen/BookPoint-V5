<?php
/**
 * Smart variables ({{variable}}) available in notification templates.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Services\Notifications;

use PointlyBooking\Repositories\AgentRepository;
use PointlyBooking\Repositories\BookingRepository;
use PointlyBooking\Repositories\CustomerRepository;
use PointlyBooking\Repositories\FormFieldRepository;
use PointlyBooking\Repositories\LocationRepository;
use PointlyBooking\Repositories\ServiceRepository;
use PointlyBooking\Services\Payments\PaymentMethods;
use PointlyBooking\Settings\Settings;
use PointlyBooking\Support\Dates;
use PointlyBooking\Support\Money;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the variable map for a booking and describes every variable for the editor.
 */
final class Variables {

	/**
	 * Customer-facing manage link.
	 *
	 * @param string $key Manage key.
	 * @return string
	 */
	public static function manage_url( $key ) {
		return $key ? add_query_arg(
			array(
				'pointlybooking_manage_booking' => 1,
				'key'                           => $key,
			),
			home_url( '/' )
		) : '';
	}

	/**
	 * Calendar (.ics) download link for a booking.
	 *
	 * @param int    $id  Booking ID.
	 * @param string $key Manage key.
	 * @return string
	 */
	public static function ics_url( $id, $key ) {
		return add_query_arg( array( 'key' => $key ), rest_url( 'pointly-booking/v1/wizard/bookings/' . (int) $id . '/ics' ) );
	}

	/**
	 * Variables for a booking.
	 *
	 * @param array $booking Booking row.
	 * @return array<string,string>
	 */
	public static function for_booking( array $booking ) {
		$service  = ServiceRepository::find( (int) $booking['service_id'] );
		$customer = $booking['customer_id'] ? CustomerRepository::find( (int) $booking['customer_id'] ) : null;
		$agent    = $booking['agent_id'] ? AgentRepository::find( (int) $booking['agent_id'] ) : null;
		$location = ! empty( $booking['location_id'] ) ? LocationRepository::find( (int) $booking['location_id'] ) : null;
		$extras   = BookingRepository::json( $booking['extras_json'] ?? '' );
		$labels   = PaymentMethods::labels();

		$start = (string) $booking['start_datetime'];
		$end   = (string) $booking['end_datetime'];
		$dur   = ( Dates::parse( $end ) && Dates::parse( $start ) ) ? (int) ( ( Dates::parse( $end )->getTimestamp() - Dates::parse( $start )->getTimestamp() ) / 60 ) : 0;

		$subtotal = (float) $booking['total_price'] + (float) $booking['discount_total'];
		$vars     = array(
			'site_name'                   => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
			'site_url'                    => home_url( '/' ),
			'admin_email'                 => (string) Settings::get( 'pointlybooking_admin_email', get_option( 'admin_email' ) ),
			'business_name'               => (string) Settings::get( 'business_name', '' ),
			'business_email'              => (string) Settings::get( 'business_email', '' ),
			'business_phone'              => (string) Settings::get( 'business_phone', '' ),
			'business_address'            => (string) Settings::get( 'business_address', '' ),
			'booking_id'                  => (string) (int) $booking['id'],
			'booking_status'              => self::status_label( (string) $booking['status'] ),
			'booking_status_key'          => (string) $booking['status'],
			'booking_notes'               => (string) ( $booking['notes'] ?? '' ),
			'start_datetime'              => $start,
			'end_datetime'                => $end,
			'start_date'                  => substr( $start, 0, 10 ),
			'start_time'                  => substr( $start, 11, 5 ),
			'end_date'                    => substr( $end, 0, 10 ),
			'end_time'                    => substr( $end, 11, 5 ),
			'booking_date'                => Dates::format( $start, get_option( 'date_format' ) ),
			'booking_time'                => Dates::format( $start, get_option( 'time_format' ) ),
			'booking_end_time'            => Dates::format( $end, get_option( 'time_format' ) ),
			'booking_duration'            => (string) max( 0, $dur ),
			'timezone'                    => wp_timezone_string(),
			'customer_name'               => $customer ? $customer['name'] : '',
			'customer_first_name'         => $customer ? (string) $customer['first_name'] : '',
			'customer_last_name'          => $customer ? (string) $customer['last_name'] : '',
			'customer_email'              => $customer ? (string) $customer['email'] : '',
			'customer_phone'              => $customer ? (string) $customer['phone'] : '',
			'agent_name'                  => $agent ? $agent['name'] : '',
			'agent_email'                 => $agent ? (string) $agent['email'] : '',
			'agent_phone'                 => $agent ? (string) $agent['phone'] : '',
			'service_name'                => $service ? $service['name'] : '',
			'service_duration'            => $service ? (string) $service['duration_minutes'] : '',
			'location_name'               => $location ? (string) $location['name'] : '',
			'location_address'            => $location ? (string) $location['address'] : '',
			'extras_list'                 => implode( ', ', wp_list_pluck( $extras, 'name' ) ),
			'subtotal'                    => number_format( $subtotal, 2, '.', '' ),
			'discount'                    => number_format( (float) $booking['discount_total'], 2, '.', '' ),
			'tax'                         => '0.00',
			'total'                       => number_format( (float) $booking['total_price'], 2, '.', '' ),
			'total_formatted'             => Money::format( (float) $booking['total_price'], (string) $booking['currency'] ),
			'currency'                    => (string) $booking['currency'],
			'promo_code'                  => (string) ( $booking['promo_code'] ?? '' ),
			'payment_method'              => isset( $labels[ $booking['payment_method'] ] ) ? $labels[ $booking['payment_method'] ]['label'] : (string) $booking['payment_method'],
			'payment_status'              => (string) $booking['payment_status'],
			'manage_booking_url_customer' => self::manage_url( (string) $booking['manage_key'] ),
			'manage_booking_url_agent'    => admin_url( 'admin.php?page=pointlybooking_bookings&booking=' . (int) $booking['id'] ),
			'add_to_calendar_url'         => self::ics_url( (int) $booking['id'], (string) $booking['manage_key'] ),
		);

		$answers = array_merge(
			BookingRepository::json( $booking['customer_fields_json'] ?? '' ),
			BookingRepository::json( $booking['booking_fields_json'] ?? '' ),
			BookingRepository::json( $booking['custom_fields_json'] ?? '' )
		);
		foreach ( $answers as $key => $value ) {
			$vars[ 'field_' . sanitize_key( (string) $key ) ] = is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value;
		}

		/**
		 * Filters the smart variables available to notification templates.
		 *
		 * @param array $vars    Variables.
		 * @param array $booking Booking row.
		 */
		return (array) apply_filters( 'pointlybooking_template_variables', $vars, $booking );
	}

	/**
	 * Translated status label.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = array(
			'pending'         => __( 'Pending', 'pointly-booking' ),
			'confirmed'       => __( 'Confirmed', 'pointly-booking' ),
			'completed'       => __( 'Completed', 'pointly-booking' ),
			'cancelled'       => __( 'Cancelled', 'pointly-booking' ),
			'pending_payment' => __( 'Awaiting payment', 'pointly-booking' ),
			'failed_payment'  => __( 'Payment failed', 'pointly-booking' ),
		);
		return $labels[ $status ] ?? $status;
	}

	/**
	 * Groups of variables for the editor sidebar.
	 *
	 * @return array
	 */
	public static function catalog() {
		$groups = array(
			array(
				'label'     => __( 'Booking', 'pointly-booking' ),
				'variables' => array(
					array( 'booking_id', __( 'Booking number', 'pointly-booking' ) ),
					array( 'booking_status', __( 'Status', 'pointly-booking' ) ),
					array( 'booking_date', __( 'Date (formatted)', 'pointly-booking' ) ),
					array( 'booking_time', __( 'Start time (formatted)', 'pointly-booking' ) ),
					array( 'booking_end_time', __( 'End time (formatted)', 'pointly-booking' ) ),
					array( 'start_date', __( 'Start date (YYYY-MM-DD)', 'pointly-booking' ) ),
					array( 'start_time', __( 'Start time (HH:MM)', 'pointly-booking' ) ),
					array( 'end_date', __( 'End date (YYYY-MM-DD)', 'pointly-booking' ) ),
					array( 'end_time', __( 'End time (HH:MM)', 'pointly-booking' ) ),
					array( 'booking_duration', __( 'Duration in minutes', 'pointly-booking' ) ),
					array( 'booking_notes', __( 'Notes', 'pointly-booking' ) ),
					array( 'timezone', __( 'Timezone', 'pointly-booking' ) ),
				),
			),
			array(
				'label'     => __( 'Customer', 'pointly-booking' ),
				'variables' => array(
					array( 'customer_name', __( 'Full name', 'pointly-booking' ) ),
					array( 'customer_first_name', __( 'First name', 'pointly-booking' ) ),
					array( 'customer_email', __( 'Email', 'pointly-booking' ) ),
					array( 'customer_phone', __( 'Phone', 'pointly-booking' ) ),
				),
			),
			array(
				'label'     => __( 'Service & staff', 'pointly-booking' ),
				'variables' => array(
					array( 'service_name', __( 'Service', 'pointly-booking' ) ),
					array( 'service_duration', __( 'Service duration', 'pointly-booking' ) ),
					array( 'extras_list', __( 'Extras', 'pointly-booking' ) ),
					array( 'agent_name', __( 'Staff member', 'pointly-booking' ) ),
					array( 'agent_email', __( 'Staff email', 'pointly-booking' ) ),
					array( 'agent_phone', __( 'Staff phone', 'pointly-booking' ) ),
					array( 'location_name', __( 'Location', 'pointly-booking' ) ),
					array( 'location_address', __( 'Location address', 'pointly-booking' ) ),
				),
			),
			array(
				'label'     => __( 'Payment', 'pointly-booking' ),
				'variables' => array(
					array( 'total_formatted', __( 'Total (formatted)', 'pointly-booking' ) ),
					array( 'total', __( 'Total', 'pointly-booking' ) ),
					array( 'subtotal', __( 'Subtotal', 'pointly-booking' ) ),
					array( 'discount', __( 'Discount', 'pointly-booking' ) ),
					array( 'tax', __( 'Tax', 'pointly-booking' ) ),
					array( 'promo_code', __( 'Promo code', 'pointly-booking' ) ),
					array( 'payment_method', __( 'Payment method', 'pointly-booking' ) ),
					array( 'payment_status', __( 'Payment status', 'pointly-booking' ) ),
				),
			),
			array(
				'label'     => __( 'Links', 'pointly-booking' ),
				'variables' => array(
					array( 'manage_booking_url_customer', __( 'Manage booking (customer)', 'pointly-booking' ) ),
					array( 'add_to_calendar_url', __( 'Add to calendar (.ics)', 'pointly-booking' ) ),
					array( 'manage_booking_url_agent', __( 'Open booking in admin', 'pointly-booking' ) ),
				),
			),
			array(
				'label'     => __( 'Business', 'pointly-booking' ),
				'variables' => array(
					array( 'business_name', __( 'Business name', 'pointly-booking' ) ),
					array( 'business_phone', __( 'Business phone', 'pointly-booking' ) ),
					array( 'business_email', __( 'Business email', 'pointly-booking' ) ),
					array( 'business_address', __( 'Business address', 'pointly-booking' ) ),
					array( 'site_name', __( 'Site name', 'pointly-booking' ) ),
					array( 'site_url', __( 'Site URL', 'pointly-booking' ) ),
					array( 'admin_email', __( 'Notification email', 'pointly-booking' ) ),
				),
			),
		);

		$custom = array();
		foreach ( FormFieldRepository::all_fields() as $field ) {
			if ( in_array( $field['field_key'], array( 'first_name', 'last_name', 'email', 'phone' ), true ) && 'customer' === $field['scope'] ) {
				continue;
			}
			$custom[] = array( 'field_' . sanitize_key( $field['field_key'] ), $field['label'] );
		}
		if ( $custom ) {
			$groups[] = array(
				'label'     => __( 'Custom fields', 'pointly-booking' ),
				'variables' => $custom,
			);
		}

		foreach ( $groups as &$group ) {
			$group['variables'] = array_map(
				static function ( $pair ) {
					return array(
						'key'   => $pair[0],
						'label' => $pair[1],
					);
				},
				$group['variables']
			);
		}
		return $groups;
	}

	/**
	 * Replaces {{variables}} in a template.
	 *
	 * @param string $template Template.
	 * @param array  $vars     Variables.
	 * @param bool   $html     Escape values for HTML output.
	 * @return string
	 */
	public static function render( $template, array $vars, $html = false ) {
		return (string) preg_replace_callback(
			'/{{\s*([a-zA-Z0-9_]+)\s*}}/',
			static function ( $m ) use ( $vars, $html ) {
				if ( ! array_key_exists( $m[1], $vars ) ) {
					return '';
				}
				$value = (string) $vars[ $m[1] ];
				if ( ! $html ) {
					return $value;
				}
				// URLs stay usable inside href attributes; everything else is escaped.
				return preg_match( '#^https?://#', $value ) ? esc_url( $value ) : nl2br( esc_html( $value ) );
			},
			(string) $template
		);
	}
}
