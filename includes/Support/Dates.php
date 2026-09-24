<?php
/**
 * Date and time helpers. Every booking time in BookPoint is stored as a
 * site-local wall-clock DATETIME (the WordPress timezone from Settings → General).
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Timezone-aware date utilities built on wp_timezone().
 */
final class Dates {

	/**
	 * Site timezone.
	 *
	 * @return \DateTimeZone
	 */
	public static function tz() {
		return wp_timezone();
	}

	/**
	 * Current moment in the site timezone.
	 *
	 * @return \DateTimeImmutable
	 */
	public static function now() {
		return new \DateTimeImmutable( 'now', self::tz() );
	}

	/**
	 * Today's date (Y-m-d) in the site timezone.
	 *
	 * @return string
	 */
	public static function today() {
		return self::now()->format( 'Y-m-d' );
	}

	/**
	 * Current site-local time as a MySQL DATETIME string.
	 *
	 * @return string
	 */
	public static function now_mysql() {
		return self::now()->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Validates a Y-m-d date string.
	 *
	 * @param mixed $value Value to check.
	 * @return bool
	 */
	public static function is_ymd( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
			return false;
		}
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
	}

	/**
	 * Validates a HH:MM (24h) time string.
	 *
	 * @param mixed $value Value to check.
	 * @return bool
	 */
	public static function is_hm( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value );
	}

	/**
	 * Normalises "HH:MM" or "HH:MM:SS" to "HH:MM"; returns '' when invalid.
	 *
	 * @param mixed $value Time value.
	 * @return string
	 */
	public static function hm( $value ) {
		$value = substr( trim( (string) $value ), 0, 5 );
		return self::is_hm( $value ) ? $value : '';
	}

	/**
	 * Minutes since midnight for "HH:MM".
	 *
	 * @param string $hm Time.
	 * @return int
	 */
	public static function to_minutes( $hm ) {
		$parts = explode( ':', substr( (string) $hm, 0, 5 ) );
		return ( (int) ( $parts[0] ?? 0 ) ) * 60 + (int) ( $parts[1] ?? 0 );
	}

	/**
	 * Formats minutes since midnight as "HH:MM". 1440 becomes "24:00".
	 *
	 * @param int $minutes Minutes.
	 * @return string
	 */
	public static function from_minutes( $minutes ) {
		$minutes = max( 0, (int) $minutes );
		return sprintf( '%02d:%02d', intdiv( $minutes, 60 ), $minutes % 60 );
	}

	/**
	 * ISO weekday (1 = Monday … 7 = Sunday) for a Y-m-d date.
	 *
	 * @param string $ymd Date.
	 * @return int
	 */
	public static function iso_weekday( $ymd ) {
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $ymd, self::tz() );
		return $date ? (int) $date->format( 'N' ) : 1;
	}

	/**
	 * Adds days to a Y-m-d date.
	 *
	 * @param string $ymd  Date.
	 * @param int    $days Days to add (may be negative).
	 * @return string
	 */
	public static function add_days( $ymd, $days ) {
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $ymd, self::tz() );
		if ( ! $date ) {
			return $ymd;
		}
		return $date->modify( ( $days >= 0 ? '+' : '' ) . (int) $days . ' days' )->format( 'Y-m-d' );
	}

	/**
	 * Builds a site-local MySQL DATETIME from a date and minutes since midnight.
	 *
	 * @param string $ymd     Date.
	 * @param int    $minutes Minutes since midnight (may exceed 1440 to roll over).
	 * @return string
	 */
	public static function datetime( $ymd, $minutes ) {
		$minutes  = (int) $minutes;
		$day_off  = (int) floor( $minutes / 1440 );
		$minutes -= $day_off * 1440;
		$date     = 0 !== $day_off ? self::add_days( $ymd, $day_off ) : $ymd;
		return $date . ' ' . self::from_minutes( $minutes ) . ':00';
	}

	/**
	 * Parses a site-local DATETIME string.
	 *
	 * @param string $mysql DATETIME value.
	 * @return \DateTimeImmutable|null
	 */
	public static function parse( $mysql ) {
		$mysql = trim( (string) $mysql );
		if ( '' === $mysql ) {
			return null;
		}
		$date = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', substr( $mysql, 0, 19 ), self::tz() );
		if ( ! $date ) {
			$date = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i', substr( $mysql, 0, 16 ), self::tz() );
		}
		return $date ? $date : null;
	}

	/**
	 * Converts a site-local DATETIME to an ISO-8601 string with offset.
	 *
	 * @param string $mysql DATETIME value.
	 * @return string
	 */
	public static function iso( $mysql ) {
		$date = self::parse( $mysql );
		return $date ? $date->format( DATE_ATOM ) : '';
	}

	/**
	 * Days between two Y-m-d dates (b - a).
	 *
	 * @param string $a From.
	 * @param string $b To.
	 * @return int
	 */
	public static function diff_days( $a, $b ) {
		$da = \DateTimeImmutable::createFromFormat( '!Y-m-d', $a, self::tz() );
		$db = \DateTimeImmutable::createFromFormat( '!Y-m-d', $b, self::tz() );
		if ( ! $da || ! $db ) {
			return 0;
		}
		return (int) $da->diff( $db )->format( '%r%a' );
	}

	/**
	 * Human-readable date and time using the site's date/time formats.
	 *
	 * @param string $mysql DATETIME value.
	 * @return string
	 */
	public static function format_datetime( $mysql ) {
		$date = self::parse( $mysql );
		if ( ! $date ) {
			return '';
		}
		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $date->getTimestamp(), self::tz() );
	}

	/**
	 * Formats a site-local DATETIME with a custom PHP date format.
	 *
	 * @param string $mysql  DATETIME value.
	 * @param string $format Format string.
	 * @return string
	 */
	public static function format( $mysql, $format ) {
		$date = self::parse( $mysql );
		return $date ? wp_date( $format, $date->getTimestamp(), self::tz() ) : '';
	}
}
