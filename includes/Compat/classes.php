<?php
/**
 * Backwards-compatible classes from BookPoint 2.x (POINTLYBOOKING_Core_Plugin, pointlybooking_Plugin).
 *
 * Add-ons and site code may reference these; they now delegate to the 3.0 services.
 *
 * @package PointlyBooking
 */

defined( 'ABSPATH' ) || exit;

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
