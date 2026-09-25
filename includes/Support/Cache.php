<?php
/**
 * Object-cache helpers with "last changed" invalidation.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Wraps wp_cache_* so that whole groups can be invalidated cheaply.
 *
 * Groups:
 *  - catalog      services, categories, extras, agents, locations, form fields, design
 *  - availability anything that affects free time slots (bookings, schedules, holidays)
 */
final class Cache {

	const GROUP = 'pointlybooking';

	/**
	 * Builds a versioned cache key.
	 *
	 * @param string $group Logical group.
	 * @param string $key   Key within the group.
	 * @return string
	 */
	private static function versioned_key( $group, $key ) {
		return $group . ':' . wp_cache_get_last_changed( self::GROUP . '_' . $group ) . ':' . md5( $key );
	}

	/**
	 * Reads a cached value.
	 *
	 * @param string $group Logical group.
	 * @param string $key   Key.
	 * @return mixed|false
	 */
	public static function get( $group, $key ) {
		return wp_cache_get( self::versioned_key( $group, $key ), self::GROUP );
	}

	/**
	 * Stores a value.
	 *
	 * @param string $group Logical group.
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @param int    $ttl   Lifetime in seconds.
	 * @return void
	 */
	public static function set( $group, $key, $value, $ttl = 600 ) {
		wp_cache_set( self::versioned_key( $group, $key ), $value, self::GROUP, $ttl );
	}

	/**
	 * Returns a cached value or computes and stores it.
	 *
	 * @param string   $group    Logical group.
	 * @param string   $key      Key.
	 * @param callable $callback Producer.
	 * @param int      $ttl      Lifetime in seconds.
	 * @return mixed
	 */
	public static function remember( $group, $key, $callback, $ttl = 600 ) {
		$value = self::get( $group, $key );
		if ( false !== $value ) {
			return $value;
		}
		$value = call_user_func( $callback );
		self::set( $group, $key, $value, $ttl );
		return $value;
	}

	/**
	 * Invalidates a group.
	 *
	 * `wp_cache_set_last_changed()` is WP 6.3+ only (this plugin supports 6.2+), so this
	 * writes the same 'last_changed' key by hand, matching what that function and the
	 * (6.2-compatible) wp_cache_get_last_changed() above both read/write.
	 *
	 * @param string $group Logical group.
	 * @return void
	 */
	public static function bump( $group ) {
		wp_cache_set( 'last_changed', microtime(), self::GROUP . '_' . $group );
	}

	/**
	 * Invalidates every BookPoint cache group.
	 *
	 * @return void
	 */
	public static function flush_all() {
		foreach ( array( 'catalog', 'availability', 'settings', 'dashboard' ) as $group ) {
			self::bump( $group );
		}
	}
}
