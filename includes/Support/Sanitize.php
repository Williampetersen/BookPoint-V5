<?php
/**
 * Input sanitisation helpers shared by REST controllers and services.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Small, typed sanitisers. All return safe values; none echo anything.
 */
final class Sanitize {

	/**
	 * Single-line text.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $max   Maximum length (0 = unlimited).
	 * @return string
	 */
	public static function text( $value, $max = 0 ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}
		$value = sanitize_text_field( (string) $value );
		return $max > 0 ? mb_substr( $value, 0, $max ) : $value;
	}

	/**
	 * Multi-line text.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $max   Maximum length (0 = unlimited).
	 * @return string
	 */
	public static function textarea( $value, $max = 0 ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}
		$value = sanitize_textarea_field( (string) $value );
		return $max > 0 ? mb_substr( $value, 0, $max ) : $value;
	}

	/**
	 * Limited rich text (post-like HTML).
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function html( $value ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}
		return wp_kses_post( (string) $value );
	}

	/**
	 * Email address ('' when invalid).
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function email( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$email = sanitize_email( (string) $value );
		return is_email( $email ) ? $email : '';
	}

	/**
	 * URL ('' when invalid).
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function url( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		return esc_url_raw( trim( (string) $value ) );
	}

	/**
	 * Boolean as 0/1.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public static function bool01( $value ) {
		if ( is_string( $value ) ) {
			$value = strtolower( trim( $value ) );
			return in_array( $value, array( '1', 'true', 'yes', 'on' ), true ) ? 1 : 0;
		}
		return ! empty( $value ) ? 1 : 0;
	}

	/**
	 * Integer clamped to a range.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $min   Minimum.
	 * @param int   $max   Maximum.
	 * @return int
	 */
	public static function int_range( $value, $min, $max ) {
		$value = is_numeric( $value ) ? (int) $value : $min;
		return max( $min, min( $max, $value ) );
	}

	/**
	 * Non-negative decimal rounded to 2 places.
	 *
	 * @param mixed $value Raw value.
	 * @return float
	 */
	public static function money( $value ) {
		$value = is_numeric( $value ) ? (float) $value : 0.0;
		return round( max( 0, $value ), 2 );
	}

	/**
	 * Value restricted to an allow-list.
	 *
	 * @param mixed  $value    Raw value.
	 * @param array  $allowed  Allowed values.
	 * @param string $fallback Fallback value.
	 * @return string
	 */
	public static function one_of( $value, array $allowed, $fallback ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Positive integer ID list.
	 *
	 * @param mixed $value Raw value (array or comma list).
	 * @return int[]
	 */
	public static function ids( $value ) {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}
		if ( ! is_array( $value ) ) {
			return array();
		}
		$ids = array();
		foreach ( $value as $item ) {
			if ( is_array( $item ) ) {
				$item = $item['id'] ?? ( $item['extra_id'] ?? 0 );
			}
			$id = absint( $item );
			if ( $id > 0 ) {
				$ids[ $id ] = $id;
			}
		}
		return array_values( $ids );
	}

	/**
	 * Hex colour (#rgb / #rrggbb) or ''.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function color( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$color = sanitize_hex_color( trim( $value ) );
		return $color ? strtolower( $color ) : '';
	}

	/**
	 * Time "HH:MM" or ''.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function time( $value ) {
		return is_scalar( $value ) ? Dates::hm( (string) $value ) : '';
	}

	/**
	 * Date "Y-m-d" or ''.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function date( $value ) {
		$value = is_scalar( $value ) ? substr( trim( (string) $value ), 0, 10 ) : '';
		return Dates::is_ymd( $value ) ? $value : '';
	}

	/**
	 * Optional local datetime "Y-m-d H:i:s" (accepts "Y-m-d", "Y-m-dTH:i") or null.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null
	 */
	public static function datetime_or_null( $value ) {
		if ( ! is_scalar( $value ) ) {
			return null;
		}
		$value = trim( str_replace( 'T', ' ', (string) $value ) );
		if ( '' === $value ) {
			return null;
		}
		if ( Dates::is_ymd( $value ) ) {
			return $value . ' 00:00:00';
		}
		if ( preg_match( '/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2})(:\d{2})?$/', $value, $m ) && Dates::is_ymd( $m[1] ) && Dates::is_hm( $m[2] ) ) {
			return $m[1] . ' ' . $m[2] . ':00';
		}
		return null;
	}

	/**
	 * Machine key (lowercase a-z0-9_).
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function key( $value ) {
		return is_scalar( $value ) ? sanitize_key( (string) $value ) : '';
	}

	/**
	 * Custom field answer: scalars become text, lists become text arrays, booleans 0/1.
	 *
	 * @param mixed  $value Raw value.
	 * @param string $type  Field type.
	 * @return mixed
	 */
	public static function field_value( $value, $type = 'text' ) {
		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $item ) {
				if ( is_scalar( $item ) ) {
					$out[] = self::text( $item, 500 );
				}
			}
			return $out;
		}
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		switch ( $type ) {
			case 'email':
				return sanitize_email( (string) $value );
			case 'textarea':
				return self::textarea( $value, 5000 );
			case 'number':
				return is_numeric( $value ) ? (string) ( 0 + $value ) : '';
			case 'checkbox':
				return self::bool01( $value ) ? '1' : '0';
			case 'date':
				return self::date( $value );
			default:
				return self::text( $value, 1000 );
		}
	}
}
