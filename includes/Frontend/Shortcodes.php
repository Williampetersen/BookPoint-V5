<?php
/**
 * Shortcodes: [pointlybooking_booking_form] and [pointlybooking_customer_portal].
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Frontend;

use PointlyBooking\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * Renders mount points for the React front ends.
 */
final class Shortcodes {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_shortcode( 'pointlybooking_booking_form', array( __CLASS__, 'booking_form' ) );
		add_shortcode( 'pointlybooking_customer_portal', array( __CLASS__, 'customer_portal' ) );
	}

	/**
	 * Booking form.
	 *
	 * Attributes (2.x): label, service_id, default_date, hide_notes, require_phone, compact.
	 * Added in 3.0: display (button|inline), category_id, agent_id, location_id.
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function booking_form( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'label'         => __( 'Book now', 'pointly-booking' ),
				'display'       => 'button',
				'service_id'    => 0,
				'category_id'   => 0,
				'agent_id'      => 0,
				'location_id'   => 0,
				'default_date'  => '',
				'hide_notes'    => 0,
				'require_phone' => 0,
				'compact'       => 0,
			),
			$atts,
			'pointlybooking_booking_form'
		);

		Assets::enqueue_front();

		$config = array(
			'display'      => 'inline' === $atts['display'] ? 'inline' : 'button',
			'label'        => Sanitize::text( $atts['label'], 120 ),
			'serviceId'    => absint( $atts['service_id'] ),
			'categoryId'   => absint( $atts['category_id'] ),
			'agentId'      => absint( $atts['agent_id'] ),
			'locationId'   => absint( $atts['location_id'] ),
			'defaultDate'  => Sanitize::date( $atts['default_date'] ),
			'hideNotes'    => (bool) Sanitize::bool01( $atts['hide_notes'] ),
			'requirePhone' => (bool) Sanitize::bool01( $atts['require_phone'] ),
			'compact'      => (bool) Sanitize::bool01( $atts['compact'] ),
		);

		$classes = array( 'pbk-root', 'pbk-booking', 'bp-front-root', 'pbk-booking--' . $config['display'] );
		if ( $config['compact'] ) {
			$classes[] = 'pbk-booking--compact';
		}

		$inner = 'inline' === $config['display']
			? '<div class="pbk-booking__placeholder" aria-busy="true"><span class="pbk-booking__spinner" aria-hidden="true"></span><span class="pbk-sr-only">' . esc_html__( 'Loading the booking form…', 'pointly-booking' ) . '</span></div>'
			: '<button type="button" class="pbk-launch" data-bp-open="wizard" disabled>' . esc_html( $config['label'] ) . '</button>';

		return sprintf(
			'<div class="%1$s" data-pbk-widget="booking" data-pbk-config="%2$s">%3$s<noscript><p class="pbk-noscript">%4$s</p></noscript></div>',
			esc_attr( implode( ' ', $classes ) ),
			esc_attr( (string) wp_json_encode( $config ) ),
			$inner, // Escaped above.
			esc_html__( 'Please enable JavaScript to book online.', 'pointly-booking' )
		);
	}

	/**
	 * Customer portal: sign in with an emailed one-time code and see your bookings.
	 *
	 * @return string
	 */
	public static function customer_portal() {
		Assets::enqueue_manage();
		return sprintf(
			'<div class="pbk-root pbk-portal" data-pbk-widget="portal"><div class="pbk-booking__placeholder" aria-busy="true"><span class="pbk-booking__spinner" aria-hidden="true"></span><span class="pbk-sr-only">%1$s</span></div><noscript><p class="pbk-noscript">%2$s</p></noscript></div>',
			esc_html__( 'Loading…', 'pointly-booking' ),
			esc_html__( 'Please enable JavaScript to see your bookings.', 'pointly-booking' )
		);
	}
}
