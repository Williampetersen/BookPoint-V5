<?php
/**
 * WooCommerce checkout integration for bookings.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Integrations;

use PointlyBooking\Repositories\BookingRepository;
use PointlyBooking\Services\Booking\BookingService;
use PointlyBooking\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the booking to the WooCommerce cart at the booking's own price and
 * confirms the booking when the order is paid.
 */
final class WooCommerce {

	/**
	 * Hooks into WooCommerce (only when it is active).
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_item_meta' ), 10, 4 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_booking_price' ), 20 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'order_paid' ) );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'order_paid' ) );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'order_paid' ) );
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'order_cancelled' ) );
		add_action( 'woocommerce_order_status_failed', array( __CLASS__, 'order_cancelled' ) );
	}

	/**
	 * Puts a booking into the cart and returns the checkout URL.
	 *
	 * @param array $booking Booking row.
	 * @return array|\WP_Error {url}
	 */
	public static function start( array $booking ) {
		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) ) {
			return new \WP_Error( 'no_wc', __( 'Online checkout is not available right now.', 'pointly-booking' ), array( 'status' => 400 ) );
		}
		$product_id = (int) Settings::get( 'payments_wc_product_id', 0 );
		if ( $product_id <= 0 || ! wc_get_product( $product_id ) ) {
			return new \WP_Error( 'no_product', __( 'Online checkout is not configured yet.', 'pointly-booking' ), array( 'status' => 400 ) );
		}
		if ( null === WC()->cart && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
		if ( ! WC()->cart ) {
			return new \WP_Error( 'no_cart', __( 'Online checkout is not available right now.', 'pointly-booking' ), array( 'status' => 400 ) );
		}
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart(
			$product_id,
			1,
			0,
			array(),
			array(
				'pointlybooking_booking_id' => (int) $booking['id'],
				'pointlybooking_key_hash'   => wp_hash( $booking['manage_key'] ),
			)
		);
		BookingRepository::update(
			$booking['id'],
			array(
				'payment_method' => 'woocommerce',
				'payment_status' => 'pending',
			)
		);
		return array( 'url' => wc_get_checkout_url() );
	}

	/**
	 * Sets the cart line price to the booking total (server-side value).
	 *
	 * @param \WC_Cart $cart Cart.
	 * @return void
	 */
	public static function apply_booking_price( $cart ) {
		if ( ! is_object( $cart ) || ! method_exists( $cart, 'get_cart' ) ) {
			return;
		}
		foreach ( $cart->get_cart() as $item ) {
			$booking_id = (int) ( $item['pointlybooking_booking_id'] ?? 0 );
			if ( ! $booking_id || empty( $item['data'] ) ) {
				continue;
			}
			$booking = BookingRepository::find( $booking_id );
			if ( ! $booking || ! hash_equals( wp_hash( $booking['manage_key'] ), (string) ( $item['pointlybooking_key_hash'] ?? '' ) ) ) {
				continue;
			}
			$amount = null !== $booking['payment_amount'] ? (float) $booking['payment_amount'] : (float) $booking['total_price'];
			$item['data']->set_price( $amount );
		}
	}

	/**
	 * Shows the booking reference in the cart.
	 *
	 * @param array $data Item data.
	 * @param array $item Cart item.
	 * @return array
	 */
	public static function cart_item_data( $data, $item ) {
		if ( ! empty( $item['pointlybooking_booking_id'] ) ) {
			$booking = BookingRepository::find_joined( (int) $item['pointlybooking_booking_id'] );
			if ( $booking ) {
				$data[] = array(
					'name'  => __( 'Booking', 'pointly-booking' ),
					'value' => sprintf( '#%d · %s · %s', (int) $booking['id'], (string) $booking['service_name'], \PointlyBooking\Support\Dates::format_datetime( $booking['start_datetime'] ) ),
				);
			}
		}
		return $data;
	}

	/**
	 * Stores the booking ID on the order line.
	 *
	 * @param \WC_Order_Item_Product $item          Order item.
	 * @param string                 $cart_item_key Key.
	 * @param array                  $values        Cart item values.
	 * @param \WC_Order              $order         Order.
	 * @return void
	 */
	public static function order_item_meta( $item, $cart_item_key, $values, $order ) {
		unset( $cart_item_key, $order );
		if ( ! empty( $values['pointlybooking_booking_id'] ) ) {
			$item->add_meta_data( 'pointlybooking_booking_id', (int) $values['pointlybooking_booking_id'], true );
		}
	}

	/**
	 * Booking IDs attached to an order.
	 *
	 * @param int $order_id Order ID.
	 * @return int[]
	 */
	private static function booking_ids( $order_id ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return array();
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return array();
		}
		$ids = array();
		foreach ( $order->get_items() as $item ) {
			$id = (int) $item->get_meta( 'pointlybooking_booking_id' );
			if ( $id ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/**
	 * Confirms bookings once the order is paid (or completed by the shop owner).
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public static function order_paid( $order_id ) {
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		if ( ! $order ) {
			return;
		}
		$is_paid = $order->is_paid() || $order->has_status( 'completed' );
		if ( ! $is_paid ) {
			return;
		}
		foreach ( self::booking_ids( $order_id ) as $booking_id ) {
			BookingService::mark_paid( $booking_id, 'woocommerce', 'wc_order_' . (int) $order_id );
		}
	}

	/**
	 * Releases the slot when the order is cancelled or failed.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public static function order_cancelled( $order_id ) {
		foreach ( self::booking_ids( $order_id ) as $booking_id ) {
			$booking = BookingRepository::find( $booking_id );
			if ( $booking && 'pending_payment' === $booking['status'] ) {
				BookingService::set_status(
					$booking_id,
					'failed_payment',
					array(
						'source' => 'payment',
						'actor'  => 'system',
						'notify' => false,
					)
				);
			}
		}
	}
}
