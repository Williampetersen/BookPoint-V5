<?php
/**
 * Activation, deactivation, roles and capabilities.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking;

use PointlyBooking\Database\Migrator;

defined( 'ABSPATH' ) || exit;

/**
 * Lifecycle hooks registered from the main plugin file.
 */
final class Installer {

	const CRON_CLEANUP = 'pointlybooking_cleanup';

	/**
	 * Every plugin capability.
	 *
	 * @var string[]
	 */
	const CAPS = array(
		'pointlybooking_manage_bookings',
		'pointlybooking_manage_services',
		'pointlybooking_manage_customers',
		'pointlybooking_manage_agents',
		'pointlybooking_manage_settings',
		'pointlybooking_manage_tools',
	);

	/**
	 * Site administrators always hold every plugin capability, even when the role
	 * was never updated (new multisite sites, role editors).
	 *
	 * @param array $allcaps User capabilities.
	 * @return array
	 */
	public static function grant_admin_caps( $allcaps ) {
		if ( ! empty( $allcaps['manage_options'] ) ) {
			foreach ( self::CAPS as $cap ) {
				$allcaps[ $cap ] = true;
			}
		}
		return $allcaps;
	}

	/**
	 * Plugin activation.
	 *
	 * @param bool $network_wide Network activation.
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
		if ( self::other_copy_active() ) {
			deactivate_plugins( plugin_basename( POINTLYBOOKING_PLUGIN_FILE ) );
			wp_die(
				esc_html__( 'BookPoint could not be activated because another copy of BookPoint is already active. Deactivate the other copy first.', 'pointly-booking' ),
				esc_html__( 'Plugin activation', 'pointly-booking' ),
				array( 'back_link' => true )
			);
		}

		if ( is_multisite() && $network_wide ) {
			foreach ( get_sites( array( 'fields' => 'ids' ) ) as $site_id ) {
				switch_to_blog( $site_id );
				self::install_site();
				restore_current_blog();
			}
			return;
		}
		self::install_site();
	}

	/**
	 * Installs tables, roles and defaults for the current site.
	 *
	 * @return void
	 */
	public static function install_site() {
		self::add_roles();
		Migrator::upgrade( (string) get_option( Migrator::OPTION, '' ) );

		if ( ! get_option( 'pointlybooking_installed_at' ) ) {
			add_option( 'pointlybooking_installed_at', current_time( 'mysql' ), '', false );
		}
		if ( ! wp_next_scheduled( self::CRON_CLEANUP ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CRON_CLEANUP );
		}
	}

	/**
	 * Plugin deactivation (data is kept; see uninstall.php).
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_CLEANUP );
		wp_clear_scheduled_hook( 'pointlybooking_run_workflow' );
		wp_clear_scheduled_hook( 'pointlybooking_run_workflow_event' );
	}

	/**
	 * Adds capabilities to administrators and creates the plugin roles.
	 *
	 * @return void
	 */
	public static function add_roles() {
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( self::CAPS as $cap ) {
				$admin->add_cap( $cap );
			}
		}

		$manager = array_fill_keys( array( 'pointlybooking_manage_bookings', 'pointlybooking_manage_services', 'pointlybooking_manage_customers', 'pointlybooking_manage_agents', 'pointlybooking_manage_settings' ), true );
		$staff   = array_fill_keys( array( 'pointlybooking_manage_bookings', 'pointlybooking_manage_customers' ), true );

		self::ensure_role( 'pointlybooking_manager', __( 'BookPoint Manager', 'pointly-booking' ), array( 'read' => true ) + $manager );
		self::ensure_role( 'pointlybooking_staff', __( 'BookPoint Staff', 'pointly-booking' ), array( 'read' => true ) + $staff );
		update_option( 'pointlybooking_caps_seeded', '1', false );
	}

	/**
	 * Creates a role or tops up its capabilities.
	 *
	 * @param string $key   Role key.
	 * @param string $label Display name.
	 * @param array  $caps  Capabilities.
	 * @return void
	 */
	private static function ensure_role( $key, $label, array $caps ) {
		$role = get_role( $key );
		if ( ! $role ) {
			add_role( $key, $label, $caps );
			return;
		}
		foreach ( $caps as $cap => $grant ) {
			if ( $grant ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Whether a different BookPoint build is already active.
	 *
	 * @return bool
	 */
	private static function other_copy_active() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$self = plugin_basename( POINTLYBOOKING_PLUGIN_FILE );
		foreach ( get_plugins() as $file => $data ) {
			if ( $file === $self || false !== stripos( $file, 'bookpoint-pro-addon' ) || false !== stripos( $file, 'license' ) ) {
				continue;
			}
			$active = is_plugin_active( $file ) || ( is_multisite() && is_plugin_active_for_network( $file ) );
			if ( ! $active ) {
				continue;
			}
			$name   = strtolower( (string) ( $data['Name'] ?? '' ) );
			$domain = strtolower( (string) ( $data['TextDomain'] ?? '' ) );
			if ( 'pointly-booking' === $domain || ( false !== strpos( $name, 'bookpoint' ) && false === strpos( $name, 'add-on' ) && false === strpos( $name, 'addon' ) ) ) {
				return true;
			}
		}
		return false;
	}
}
