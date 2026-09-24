<?php
/**
 * Links on the Plugins screen.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Adds "Dashboard" and "Settings" links to the plugin row.
 */
final class PluginLinks {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( 'plugin_action_links_' . plugin_basename( POINTLYBOOKING_PLUGIN_FILE ), array( __CLASS__, 'links' ) );
	}

	/**
	 * Prepends plugin links.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function links( $links ) {
		$own = array();
		if ( current_user_can( 'pointlybooking_manage_bookings' ) ) {
			$own[] = '<a href="' . esc_url( Menu::url() ) . '">' . esc_html__( 'Dashboard', 'pointly-booking' ) . '</a>';
		}
		if ( current_user_can( 'pointlybooking_manage_settings' ) ) {
			$own[] = '<a href="' . esc_url( Menu::url( 'pointlybooking_settings' ) ) . '">' . esc_html__( 'Settings', 'pointly-booking' ) . '</a>';
		}
		return array_merge( $own, (array) $links );
	}
}
