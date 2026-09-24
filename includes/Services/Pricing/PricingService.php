<?php
/**
 * Server-authoritative pricing and promo codes.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Services\Pricing;

use PointlyBooking\Repositories\ExtraRepository;
use PointlyBooking\Repositories\PromoCodeRepository;
use PointlyBooking\Repositories\ServiceRepository;
use PointlyBooking\Support\Money;

defined( 'ABSPATH' ) || exit;

/**
 * Computes booking totals from database prices only; client totals are never trusted.
 */
final class PricingService {

	/**
	 * Validates a promo code against a subtotal.
	 *
	 * @param string $code     Code.
	 * @param float  $subtotal Subtotal.
	 * @return array{valid:bool,code:string,discount:float,message:string,promo:array|null}
	 */
	public static function check_promo( $code, $subtotal ) {
		$code = strtoupper( trim( (string) $code ) );
		$fail = static function ( $message ) use ( $code ) {
			return array(
				'valid'    => false,
				'code'     => $code,
				'discount' => 0.0,
				'message'  => $message,
				'promo'    => null,
			);
		};
		if ( '' === $code ) {
			return $fail( '' );
		}

		$promo = PromoCodeRepository::find_by_code( $code );
		if ( ! $promo ) {
			return $fail( __( 'This code is not valid.', 'pointly-booking' ) );
		}
		switch ( $promo['state'] ) {
			case 'disabled':
				return $fail( __( 'This code is not valid.', 'pointly-booking' ) );
			case 'scheduled':
				return $fail( __( 'This code is not active yet.', 'pointly-booking' ) );
			case 'expired':
				return $fail( __( 'This code has expired.', 'pointly-booking' ) );
			case 'exhausted':
				return $fail( __( 'This code has reached its usage limit.', 'pointly-booking' ) );
		}
		if ( null !== $promo['min_total'] && $subtotal < (float) $promo['min_total'] ) {
			return $fail(
				sprintf(
					/* translators: %s: minimum order amount */
					__( 'This code needs an order of at least %s.', 'pointly-booking' ),
					Money::format( (float) $promo['min_total'] )
				)
			);
		}

		$discount = 'fixed' === $promo['type'] ? (float) $promo['amount'] : $subtotal * (float) $promo['amount'] / 100;
		$discount = round( max( 0.0, min( $discount, $subtotal ) ), 2 );

		return array(
			'valid'    => true,
			'code'     => $code,
			'discount' => $discount,
			'message'  => __( 'Code applied.', 'pointly-booking' ),
			'promo'    => $promo,
		);
	}

	/**
	 * Quote for a service with extras and an optional promo code.
	 *
	 * @param int    $service_id Service ID.
	 * @param int[]  $extra_ids  Requested extra IDs.
	 * @param string $promo_code Promo code.
	 * @return array|\WP_Error {service_price, extras:[{id,name,price,duration_min}], extras_total,
	 *                         subtotal, discount, total, currency, promo_code, promo_id, promo_message, duration}
	 */
	public static function quote( $service_id, array $extra_ids = array(), $promo_code = '' ) {
		$service = ServiceRepository::find( $service_id );
		if ( ! $service ) {
			return new \WP_Error( 'service_not_found', __( 'This service is no longer available.', 'pointly-booking' ), array( 'status' => 404 ) );
		}

		$currency = Money::currency();
		$base     = round( $service['price_cents'] / 100, 2 );

		$allowed = array();
		foreach ( ExtraRepository::for_service( $service_id ) as $extra ) {
			$allowed[ $extra['id'] ] = $extra;
		}
		$extras       = array();
		$extras_total = 0.0;
		$extra_time   = 0;
		foreach ( array_unique( array_map( 'intval', $extra_ids ) ) as $extra_id ) {
			if ( ! isset( $allowed[ $extra_id ] ) ) {
				continue;
			}
			$extra         = $allowed[ $extra_id ];
			$extras[]      = array(
				'id'           => $extra['id'],
				'name'         => $extra['name'],
				'price'        => $extra['price'],
				'duration_min' => $extra['duration_min'],
				'qty'          => 1,
			);
			$extras_total += $extra['price'];
			$extra_time   += max( 0, (int) $extra['duration_min'] );
		}

		$subtotal = round( $base + $extras_total, 2 );
		$promo    = self::check_promo( $promo_code, $subtotal );
		$discount = $promo['valid'] ? $promo['discount'] : 0.0;

		return array(
			'service_price' => $base,
			'extras'        => $extras,
			'extras_total'  => round( $extras_total, 2 ),
			'subtotal'      => $subtotal,
			'discount'      => $discount,
			'total'         => round( max( 0, $subtotal - $discount ), 2 ),
			'currency'      => $currency,
			'promo_code'    => $promo['valid'] ? $promo['code'] : '',
			'promo_id'      => $promo['valid'] ? (int) $promo['promo']['id'] : 0,
			'promo_valid'   => $promo['valid'],
			'promo_message' => $promo['message'],
			'duration'      => (int) $service['duration_minutes'],
			'extras_time'   => $extra_time,
		);
	}
}
