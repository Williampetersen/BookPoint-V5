<?php
/**
 * Money formatting for server-rendered output (emails, exports).
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Support;

use PointlyBooking\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Formats amounts using the configured currency and symbol position.
 */
final class Money {

	/**
	 * Common currency symbols; unknown codes fall back to the ISO code.
	 *
	 * @var array<string,string>
	 */
	private static $symbols = array(
		'USD' => '$',
		'EUR' => '€',
		'GBP' => '£',
		'JPY' => '¥',
		'CNY' => '¥',
		'INR' => '₹',
		'AUD' => 'A$',
		'CAD' => 'C$',
		'NZD' => 'NZ$',
		'CHF' => 'CHF',
		'SEK' => 'kr',
		'NOK' => 'kr',
		'DKK' => 'kr',
		'ISK' => 'kr',
		'PLN' => 'zł',
		'BRL' => 'R$',
		'ZAR' => 'R',
		'MXN' => 'MX$',
		'TRY' => '₺',
		'KRW' => '₩',
		'ILS' => '₪',
		'AED' => 'AED',
		'SAR' => 'SAR',
		'SGD' => 'S$',
		'HKD' => 'HK$',
		'THB' => '฿',
		'PHP' => '₱',
		'NGN' => '₦',
		'UAH' => '₴',
		'RUB' => '₽',
		'CZK' => 'Kč',
		'HUF' => 'Ft',
		'RON' => 'lei',
	);

	/**
	 * Configured currency code.
	 *
	 * @return string
	 */
	public static function currency() {
		$code = strtoupper( (string) Settings::get( 'currency', 'USD' ) );
		return preg_match( '/^[A-Z]{3}$/', $code ) ? $code : 'USD';
	}

	/**
	 * Symbol for a currency.
	 *
	 * @param string $code ISO code.
	 * @return string
	 */
	public static function symbol( $code ) {
		$code = strtoupper( (string) $code );
		return self::$symbols[ $code ] ?? $code;
	}

	/**
	 * Formats an amount, e.g. "$12.50" or "12,50 kr".
	 *
	 * @param float       $amount   Amount.
	 * @param string|null $currency ISO code (defaults to settings).
	 * @return string
	 */
	public static function format( $amount, $currency = null ) {
		$currency = $currency ? strtoupper( $currency ) : self::currency();
		$amount   = (float) $amount;
		$decimals = ( floor( $amount ) === $amount ) ? 0 : 2;
		$number   = number_format_i18n( $amount, $decimals );
		$symbol   = self::symbol( $currency );
		$spaced   = (bool) preg_match( '/^[A-Za-z]/', $symbol );

		if ( 'after' === Settings::get( 'currency_position', 'before' ) ) {
			return $number . ( $spaced || strlen( $symbol ) > 1 ? ' ' : '' ) . $symbol;
		}
		return $symbol . ( $spaced ? ' ' : '' ) . $number;
	}
}
