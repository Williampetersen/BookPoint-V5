<?php
/**
 * Backwards-compatible global functions and classes from BookPoint 2.x.
 *
 * Add-ons and site code may call these; they now delegate to the 3.0 services.
 *
 * @package PointlyBooking
 */

defined( 'ABSPATH' ) || exit;

use PointlyBooking\Database\Tables;
use PointlyBooking\Repositories\BookingRepository;
use PointlyBooking\Services\Booking\BookingService;
use PointlyBooking\Services\Notifications\Variables;
use PointlyBooking\Services\Pricing\PricingService;

if ( ! class_exists( 'POINTLYBOOKING_Core_Plugin', false ) ) {
	/**
	 * 2.x core class shim (the Pro add-on reads VERSION).
	 */
	final class POINTLYBOOKING_Core_Plugin {

		const VERSION    = POINTLYBOOKING_VERSION;
		const DB_VERSION = PointlyBooking\Database\Migrator::DB_VERSION;

		/**
		 * No-op: the plugin boots itself.
		 *
		 * @return void
		 */
		public static function init() {}

		/**
		 * Enqueues the booking form assets.
		 *
		 * @param bool $force Unused (kept for signature compatibility).
		 * @return void
		 */
		public static function enqueue_public_assets( $force = false ) {
			unset( $force );
			PointlyBooking\Frontend\Assets::enqueue_front();
		}

		/**
		 * Enqueues only the front styles.
		 *
		 * @return void
		 */
		public static function enqueue_public_styles_only() {
			wp_enqueue_style( 'pointlybooking-front' );
		}
	}
}
if ( ! class_exists( 'pointlybooking_Plugin', false ) ) {
	class_alias( 'POINTLYBOOKING_Core_Plugin', 'pointlybooking_Plugin' );
}

if ( ! function_exists( 'pointlybooking_table' ) ) {
	/**
	 * Prefixed plugin table name.
	 *
	 * @param string $suffix Logical name.
	 * @return string
	 */
	function pointlybooking_table( $suffix ) {
		return Tables::name( $suffix );
	}
}

if ( ! function_exists( 'pointlybooking_db_table_exists' ) ) {
	/**
	 * Whether a (full) table name exists.
	 *
	 * @param string $table Full table name.
	 * @return bool
	 */
	function pointlybooking_db_table_exists( $table ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema inspection.
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}
}

if ( ! function_exists( 'pointlybooking_request_scalar' ) ) {
	/**
	 * Sanitised scalar request value (2.x helper; callers must verify nonces).
	 *
	 * @param string $method get|post.
	 * @param string $key    Key.
	 * @return string
	 */
	function pointlybooking_request_scalar( $method, $key ) {
		// phpcs:disable WordPress.Security.NonceVerification -- Low-level accessor kept for 2.x callers, which verify their own nonces.
		$source = 'post' === $method ? $_POST : $_GET;
		if ( ! isset( $source[ $key ] ) || ! is_scalar( $source[ $key ] ) ) {
			return '';
		}
		return sanitize_text_field( wp_unslash( (string) $source[ $key ] ) );
		// phpcs:enable WordPress.Security.NonceVerification
	}
}

if ( ! function_exists( 'pointlybooking_render_template' ) ) {
	/**
	 * Replaces {{variables}} in a string.
	 *
	 * @param string $text    Template.
	 * @param array  $context Variables.
	 * @return string
	 */
	function pointlybooking_render_template( $text, $context ) {
		return Variables::render( (string) $text, (array) $context );
	}
}

if ( ! function_exists( 'pointlybooking_run_workflows' ) ) {
	/**
	 * Runs workflows for an event (2.x signature: payload with the booking).
	 *
	 * @param string $event_key Event.
	 * @param array  $payload   Payload containing booking.id or entity_id.
	 * @return void
	 */
	function pointlybooking_run_workflows( $event_key, $payload ) {
		$id = (int) ( $payload['entity_id'] ?? ( $payload['booking']['id'] ?? 0 ) );
		if ( $id ) {
			PointlyBooking\Services\Notifications\WorkflowEngine::dispatch( (string) $event_key, $id );
		}
	}
}

if ( ! function_exists( 'pointlybooking_compute_authoritative_pricing' ) ) {
	/**
	 * Server-side price for a booking payload.
	 *
	 * @param array $payload service_id, extras|extra_ids, promo_code.
	 * @return array|WP_Error
	 */
	function pointlybooking_compute_authoritative_pricing( array $payload ) {
		$quote = PricingService::quote( (int) ( $payload['service_id'] ?? 0 ), PointlyBooking\Support\Sanitize::ids( $payload['extras'] ?? ( $payload['extra_ids'] ?? array() ) ), (string) ( $payload['promo_code'] ?? '' ) );
		if ( is_wp_error( $quote ) ) {
			return $quote;
		}
		return array(
			'subtotal'   => $quote['subtotal'],
			'discount'   => $quote['discount'],
			'total'      => $quote['total'],
			'currency'   => $quote['currency'],
			'promo_id'   => $quote['promo_id'],
			'promo_code' => $quote['promo_code'],
		);
	}
}

if ( ! function_exists( 'pointlybooking_insert_booking_from_payload' ) ) {
	/**
	 * Creates a booking from a 2.x style payload.
	 *
	 * @param array $p         Payload (service_id, agent_id, date, start_time, customer_fields, booking_fields, field_values, extras, promo_code).
	 * @param array $overrides status, payment_method, payment_status.
	 * @return array|WP_Error {booking_id, manage_url}
	 */
	function pointlybooking_insert_booking_from_payload( array $p, array $overrides = array() ) {
		$admin   = isset( $overrides['status'] ) && current_user_can( 'pointlybooking_manage_bookings' );
		$input   = array_merge(
			$p,
			array(
				'start'          => $p['start_time'] ?? ( $p['start'] ?? '' ),
				'payment_method' => $overrides['payment_method'] ?? ( $p['payment_method'] ?? '' ),
				'status'         => $overrides['status'] ?? '',
			)
		);
		$booking = BookingService::create( $input, array( 'source' => $admin ? 'admin' : 'legacy' ) );
		if ( is_wp_error( $booking ) ) {
			return $booking;
		}
		return array(
			'booking_id' => (int) $booking['id'],
			'manage_url' => Variables::manage_url( $booking['manage_key'] ),
		);
	}
}

if ( ! function_exists( 'pointlybooking_create_pending_payment_booking_from_payload' ) ) {
	/**
	 * Creates a booking that waits for online payment.
	 *
	 * @param array  $payload Payload.
	 * @param string $method  Payment method.
	 * @return array|WP_Error
	 */
	function pointlybooking_create_pending_payment_booking_from_payload( array $payload, $method ) {
		$payload['payment_method'] = $method;
		return pointlybooking_insert_booking_from_payload( $payload );
	}
}

if ( ! function_exists( 'pointlybooking_confirm_booking_paid' ) ) {
	/**
	 * Marks a booking paid and confirmed.
	 *
	 * @param int    $booking_id   Booking ID.
	 * @param string $provider_ref Provider reference.
	 * @return void
	 */
	function pointlybooking_confirm_booking_paid( $booking_id, $provider_ref = '' ) {
		$booking = BookingRepository::find( $booking_id );
		BookingService::mark_paid( (int) $booking_id, $booking ? (string) $booking['payment_method'] : 'online', (string) $provider_ref );
	}
}

if ( ! function_exists( 'pointlybooking_mark_booking_paid_and_confirmed' ) ) {
	/**
	 * WooCommerce 2.x helper.
	 *
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	function pointlybooking_mark_booking_paid_and_confirmed( $booking_id ) {
		BookingService::mark_paid( (int) $booking_id, 'woocommerce' );
	}
}

if ( ! function_exists( 'pointlybooking_shortcode_booking_form' ) ) {
	/**
	 * Renders the booking form (shortcode/block callback kept for 2.x callers).
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	function pointlybooking_shortcode_booking_form( $atts = array() ) {
		return PointlyBooking\Frontend\Shortcodes::booking_form( (array) $atts );
	}
}

if ( ! function_exists( 'pointlybooking_render_admin_app' ) ) {
	/**
	 * Prints the admin app mount point.
	 *
	 * @return void
	 */
	function pointlybooking_render_admin_app() {
		PointlyBooking\Admin\Menu::render();
	}
}
