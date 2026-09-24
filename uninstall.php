<?php
/**
 * Uninstall: removes plugin data only when "Delete all data when the plugin is deleted"
 * is enabled in Settings → General. Otherwise bookings, customers and settings are kept.
 *
 * @package PointlyBooking
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/Autoloader.php';
\PointlyBooking\Autoloader::register();

if ( ! function_exists( 'pointlybooking_uninstall_site' ) ) {
	/**
	 * Removes the data of the current site.
	 *
	 * @return void
	 */
	function pointlybooking_uninstall_site() {
		global $wpdb;

		// Scheduled events.
		foreach ( array( 'pointlybooking_cleanup', 'pointlybooking_run_workflow', 'pointlybooking_run_workflow_event' ) as $hook ) {
			wp_unschedule_hook( $hook );
		}

		if ( 1 !== (int) get_option( 'pointlybooking_remove_data_on_uninstall', 0 ) ) {
			return;
		}

		foreach ( \PointlyBooking\Database\Tables::ALL as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall-only DDL on the plugin's own tables.
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'pointlybooking_' . $table ) );
		}

		// Options (including legacy 2.x and Pro licence options) and transients.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk cleanup of the plugin's own options.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( 'pointlybooking_' ) . '%',
				$wpdb->esc_like( '_transient_pointlybooking_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_pointlybooking_' ) . '%'
			)
		);

		// Roles and capabilities.
		remove_role( 'pointlybooking_manager' );
		remove_role( 'pointlybooking_staff' );
		foreach ( wp_roles()->role_objects as $role ) {
			foreach ( \PointlyBooking\Installer::CAPS as $cap ) {
				$role->remove_cap( $cap );
			}
		}

		if ( function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'flush_group' ) ) {
			wp_cache_flush_group( 'pointlybooking' );
		}
	}
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $pointlybooking_site_id ) {
		switch_to_blog( $pointlybooking_site_id );
		pointlybooking_uninstall_site();
		restore_current_blog();
	}
} else {
	pointlybooking_uninstall_site();
}
