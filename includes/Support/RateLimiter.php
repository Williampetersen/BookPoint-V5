<?php
/**
 * Simple fixed-window rate limiter backed by transients.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Counts hits per (bucket, client IP) and reports when a limit is exceeded.
 */
final class RateLimiter {

	/**
	 * Registers a hit and returns whether the caller is still within the limit.
	 *
	 * @param string $bucket Logical action name.
	 * @param int    $limit  Allowed hits per window.
	 * @param int    $window Window length in seconds.
	 * @return bool True when allowed.
	 */
	public static function hit( $bucket, $limit, $window ) {
		/**
		 * Filters whether rate limiting is active (e.g. disable on staging).
		 *
		 * @param bool   $enabled Whether to rate limit.
		 * @param string $bucket  Bucket name.
		 */
		if ( ! apply_filters( 'pointlybooking_rate_limit_enabled', true, $bucket ) ) {
			return true;
		}

		$key  = 'pointlybooking_rl_' . md5( $bucket . '|' . Request::client_ip() );
		$data = get_transient( $key );
		$now  = time();

		if ( ! is_array( $data ) || ! isset( $data['count'], $data['start'] ) || ( $now - (int) $data['start'] ) > $window ) {
			$data = array(
				'count' => 0,
				'start' => $now,
			);
		}

		++$data['count'];
		set_transient( $key, $data, $window );

		return (int) $data['count'] <= $limit;
	}
}
