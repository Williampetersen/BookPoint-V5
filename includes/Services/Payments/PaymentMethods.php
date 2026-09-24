<?php
/**
 * Payment method configuration.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Services\Payments;

use PointlyBooking\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Knows which methods exist, which are enabled and which are fully configured.
 */
final class PaymentMethods {

	/**
	 * Every method.
	 *
	 * @var string[]
	 */
	const ALL = array( 'cash', 'free', 'woocommerce', 'stripe', 'paypal' );

	/**
	 * Methods that take payment online.
	 *
	 * @var string[]
	 */
	const ONLINE = array( 'woocommerce', 'stripe', 'paypal' );

	/**
	 * Whether online/offline payment options are shown to customers.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return Settings::bool( 'payments_enabled' );
	}

	/**
	 * Methods enabled by the admin.
	 *
	 * @return string[]
	 */
	public static function selected() {
		$methods = Settings::get( 'payments_enabled_methods', array( 'cash' ) );
		$methods = is_array( $methods ) ? array_values( array_intersect( self::ALL, array_map( 'strval', $methods ) ) ) : array();
		return $methods ? $methods : array( 'cash' );
	}

	/**
	 * Whether a method has everything it needs to work.
	 *
	 * @param string $method Method.
	 * @return bool
	 */
	public static function is_configured( $method ) {
		switch ( $method ) {
			case 'stripe':
				return '' !== Stripe::secret_key() && ( 'checkout' === Stripe::flow() || '' !== Stripe::publishable_key() );
			case 'paypal':
				return '' !== PayPal::client_id() && '' !== PayPal::secret();
			case 'woocommerce':
				return class_exists( 'WooCommerce' ) && (int) Settings::get( 'payments_wc_product_id', 0 ) > 0;
			default:
				return true;
		}
	}

	/**
	 * Methods customers can actually use (enabled and configured).
	 *
	 * @return string[]
	 */
	public static function available() {
		if ( ! self::enabled() ) {
			return array( 'cash' );
		}
		$out = array_values( array_filter( self::selected(), array( __CLASS__, 'is_configured' ) ) );
		return $out ? $out : array( 'cash' );
	}

	/**
	 * Default method (falls back to the first available one).
	 *
	 * @return string
	 */
	public static function default_method() {
		$default   = (string) Settings::get( 'payments_default_method', 'cash' );
		$available = self::available();
		return in_array( $default, $available, true ) ? $default : $available[0];
	}

	/**
	 * Customer-facing labels.
	 *
	 * @return array<string,array{label:string,description:string}>
	 */
	public static function labels() {
		return array(
			'cash'        => array(
				'label'       => __( 'Pay later', 'pointly-booking' ),
				'description' => __( 'Pay in person at your appointment.', 'pointly-booking' ),
			),
			'free'        => array(
				'label'       => __( 'No payment needed', 'pointly-booking' ),
				'description' => __( 'Confirm your booking without paying now.', 'pointly-booking' ),
			),
			'stripe'      => array(
				'label'       => __( 'Card', 'pointly-booking' ),
				'description' => __( 'Pay securely by card.', 'pointly-booking' ),
			),
			'paypal'      => array(
				'label'       => __( 'PayPal', 'pointly-booking' ),
				'description' => __( 'You will be redirected to PayPal to pay.', 'pointly-booking' ),
			),
			'woocommerce' => array(
				'label'       => __( 'Online checkout', 'pointly-booking' ),
				'description' => __( 'Complete payment in our shop checkout.', 'pointly-booking' ),
			),
		);
	}

	/**
	 * Public configuration for the wizard.
	 *
	 * @return array
	 */
	public static function public_config() {
		$labels  = self::labels();
		$methods = array();
		foreach ( self::available() as $method ) {
			$methods[] = array(
				'id'          => $method,
				'label'       => $labels[ $method ]['label'],
				'description' => $labels[ $method ]['description'],
				'online'      => in_array( $method, self::ONLINE, true ),
			);
		}
		return array(
			'enabled'        => self::enabled(),
			'methods'        => $methods,
			'default'        => self::default_method(),
			'requirePayment' => Settings::bool( 'payments_require_payment_to_confirm' ),
			'stripe'         => array(
				'publishableKey' => in_array( 'stripe', self::available(), true ) ? Stripe::publishable_key() : '',
				'flow'           => Stripe::flow(),
			),
		);
	}
}
