<?php
/**
 * Minute-interval arithmetic.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Services\Availability;

defined( 'ABSPATH' ) || exit;

/**
 * Pure helpers on lists of [start, end) minute pairs.
 */
final class Intervals {

	/**
	 * Sorts and merges overlapping/adjacent intervals; drops empty ones.
	 *
	 * @param array $list Intervals.
	 * @return array
	 */
	public static function normalize( array $list ) {
		$list = array_values(
			array_filter(
				$list,
				static function ( $i ) {
					return is_array( $i ) && isset( $i[0], $i[1] ) && $i[1] > $i[0];
				}
			)
		);
		usort(
			$list,
			static function ( $a, $b ) {
				return $a[0] <=> $b[0];
			}
		);
		$out = array();
		foreach ( $list as $interval ) {
			$last = count( $out ) - 1;
			if ( $last >= 0 && $interval[0] <= $out[ $last ][1] ) {
				$out[ $last ][1] = max( $out[ $last ][1], $interval[1] );
			} else {
				$out[] = array( (int) $interval[0], (int) $interval[1] );
			}
		}
		return $out;
	}

	/**
	 * Removes $cuts from $open.
	 *
	 * @param array $open Normalised intervals.
	 * @param array $cuts Intervals to remove.
	 * @return array
	 */
	public static function subtract( array $open, array $cuts ) {
		$cuts = self::normalize( $cuts );
		foreach ( $cuts as $cut ) {
			$next = array();
			foreach ( $open as $interval ) {
				if ( $cut[1] <= $interval[0] || $cut[0] >= $interval[1] ) {
					$next[] = $interval;
					continue;
				}
				if ( $cut[0] > $interval[0] ) {
					$next[] = array( $interval[0], $cut[0] );
				}
				if ( $cut[1] < $interval[1] ) {
					$next[] = array( $cut[1], $interval[1] );
				}
			}
			$open = $next;
		}
		return $open;
	}

	/**
	 * Intersection of two interval lists.
	 *
	 * @param array $a Normalised intervals.
	 * @param array $b Normalised intervals.
	 * @return array
	 */
	public static function intersect( array $a, array $b ) {
		$out = array();
		foreach ( $a as $x ) {
			foreach ( $b as $y ) {
				$start = max( $x[0], $y[0] );
				$end   = min( $x[1], $y[1] );
				if ( $end > $start ) {
					$out[] = array( $start, $end );
				}
			}
		}
		return self::normalize( $out );
	}

	/**
	 * Whether [start, end) lies fully inside one of the intervals.
	 *
	 * @param array $list  Intervals.
	 * @param int   $start Start minute.
	 * @param int   $end   End minute.
	 * @return bool
	 */
	public static function contains( array $list, $start, $end ) {
		foreach ( $list as $interval ) {
			if ( $start >= $interval[0] && $end <= $interval[1] ) {
				return true;
			}
		}
		return false;
	}
}
