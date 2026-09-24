<?php
/**
 * Plugin table registry.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves logical table names to prefixed physical names.
 */
final class Tables {

	/**
	 * Every table owned by the plugin (logical names without prefix).
	 *
	 * @var string[]
	 */
	const ALL = array(
		'services',
		'categories',
		'service_categories',
		'service_extras',
		'extra_services',
		'agents',
		'agent_services',
		'service_agents',
		'agent_working_hours',
		'agent_breaks',
		'customers',
		'bookings',
		'settings',
		'form_fields',
		'field_values',
		'promo_codes',
		'holidays',
		'schedules',
		'schedule_settings',
		'workflows',
		'workflow_actions',
		'workflow_logs',
		'audit_log',
		'locations',
		'location_categories',
		'location_agents',
		'bundles',
		'bundle_items',
	);

	/**
	 * Prefixed table name.
	 *
	 * @param string $name Logical table name.
	 * @return string
	 */
	public static function name( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'pointlybooking_' . preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $name ) );
	}

	/**
	 * Whether a table exists.
	 *
	 * @param string $name Logical table name.
	 * @return bool
	 */
	public static function exists( $name ) {
		global $wpdb;
		$table = self::name( $name );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema inspection must reflect the live database.
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}

	/**
	 * Column names of a table.
	 *
	 * @param string $name Logical table name.
	 * @return string[]
	 */
	public static function columns( $name ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema inspection must reflect the live database.
		$cols = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', self::name( $name ) ), 0 );
		return is_array( $cols ) ? $cols : array();
	}
}
