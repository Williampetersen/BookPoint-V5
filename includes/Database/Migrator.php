<?php
/**
 * Versioned, idempotent database migrations.
 *
 * Runs only when the stored DB version is older than DB_VERSION (one cheap
 * autoloaded option read per request); never on every admin page load.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Database;

use PointlyBooking\Repositories\FormFieldRepository;
use PointlyBooking\Repositories\ScheduleRepository;
use PointlyBooking\Services\Notifications\DefaultWorkflows;
use PointlyBooking\Settings\Settings;
use PointlyBooking\Support\Cache;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- One-off schema and data migrations.

/**
 * Upgrades existing installs to the current schema and data model.
 */
final class Migrator {

	/**
	 * Current schema version. 2.x stored "1.6.0" or "5.0.0" in the same option.
	 */
	const DB_VERSION = '6.0.0';

	const OPTION = 'pointlybooking_db_version';

	/**
	 * Runs migrations when needed.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$installed = (string) get_option( self::OPTION, '' );
		if ( '' !== $installed && version_compare( $installed, self::DB_VERSION, '>=' ) ) {
			return;
		}
		self::upgrade( $installed );
	}

	/**
	 * Runs every step. Safe to repeat.
	 *
	 * @param string $from Previously installed version ('' on fresh installs).
	 * @return void
	 */
	public static function upgrade( $from = '' ) {
		// Avoid two requests migrating at the same time.
		if ( get_transient( 'pointlybooking_migrating' ) ) {
			return;
		}
		set_transient( 'pointlybooking_migrating', 1, 5 * MINUTE_IN_SECONDS );

		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}

		Schema::install();

		// The legacy link columns are imported once; afterwards the canonical pivots are authoritative.
		if ( '' === $from || version_compare( $from, '6.0.0', '<' ) ) {
			self::step_pivots();
		}
		self::step_extras_legacy_column();
		self::step_agent_override_schedules();
		self::step_settings( '' === $from );
		FormFieldRepository::normalize_legacy_columns();
		FormFieldRepository::ensure_defaults();
		ScheduleRepository::sync_settings_row( (int) Settings::get( 'slot_interval_minutes', 30 ) );
		DefaultWorkflows::maybe_seed();

		update_option( self::OPTION, self::DB_VERSION, true );
		update_option( 'pointlybooking_version', POINTLYBOOKING_VERSION, false );
		if ( '' !== $from && ! get_option( 'pointlybooking_upgraded_from' ) ) {
			update_option( 'pointlybooking_upgraded_from', $from, false );
		}

		Cache::flush_all();
		delete_transient( 'pointlybooking_migrating' );
	}

	/**
	 * Unifies pivot tables:
	 *  - service_agents (legacy, 2.x demo tool) → agent_services (used everywhere)
	 *  - services.category_id (legacy)          → service_categories
	 *  - service_extras.service_id (legacy)      → extra_services
	 *
	 * @return void
	 */
	private static function step_pivots() {
		global $wpdb;
		if ( Tables::exists( 'service_agents' ) ) {
			$wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (agent_id, service_id) SELECT agent_id, service_id FROM %i', Tables::name( 'agent_services' ), Tables::name( 'service_agents' ) ) );
		}
		$wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (service_id, category_id) SELECT id, category_id FROM %i WHERE category_id IS NOT NULL AND category_id > 0', Tables::name( 'service_categories' ), Tables::name( 'services' ) ) );
		$wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (extra_id, service_id) SELECT id, service_id FROM %i WHERE service_id > 0', Tables::name( 'extra_services' ), Tables::name( 'service_extras' ) ) );
	}

	/**
	 * Rebuilds the legacy link columns/tables from the canonical pivots (Tools → Sync relations).
	 *
	 * @return array<string,int> Rows written per target.
	 */
	public static function sync_relations() {
		global $wpdb;
		$out = array();

		$out['service_categories'] = (int) $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i s LEFT JOIN (SELECT service_id, MIN(category_id) AS cid FROM %i GROUP BY service_id) m ON m.service_id = s.id SET s.category_id = m.cid',
				Tables::name( 'services' ),
				Tables::name( 'service_categories' )
			)
		);
		$out['extra_services'] = (int) $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i e LEFT JOIN (SELECT extra_id, MIN(service_id) AS sid FROM %i GROUP BY extra_id) m ON m.extra_id = e.id SET e.service_id = IFNULL(m.sid, 0)',
				Tables::name( 'service_extras' ),
				Tables::name( 'extra_services' )
			)
		);
		if ( Tables::exists( 'service_agents' ) ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', Tables::name( 'service_agents' ) ) );
			$out['agent_services'] = (int) $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (service_id, agent_id) SELECT service_id, agent_id FROM %i', Tables::name( 'service_agents' ), Tables::name( 'agent_services' ) ) );
		}
		Cache::flush_all();
		return $out;
	}

	/**
	 * Fills the legacy extras.service_id column from the pivot (keeps 2.x readers working).
	 *
	 * @return void
	 */
	private static function step_extras_legacy_column() {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i e JOIN (SELECT extra_id, MIN(service_id) AS sid FROM %i GROUP BY extra_id) m ON m.extra_id = e.id SET e.service_id = m.sid WHERE e.service_id = 0',
				Tables::name( 'service_extras' ),
				Tables::name( 'extra_services' )
			)
		);
	}

	/**
	 * 2.x let admins save an "override schedule" per staff member (agents.schedule_json)
	 * but never applied it. The resolver now reads it directly; nothing to copy. We only
	 * normalise empty strings to NULL so "no override" is unambiguous.
	 *
	 * @return void
	 */
	private static function step_agent_override_schedules() {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "UPDATE %i SET schedule_json = NULL WHERE schedule_json = '' OR schedule_json = '[]' OR schedule_json = '{}'", Tables::name( 'agents' ) ) );
	}

	/**
	 * Settings clean-up:
	 *  - one slot interval (option wins; legacy table value used when the option has none)
	 *  - payments stay off for sites that never configured them (matches 2.x behaviour,
	 *    where customers could not pick a method anyway)
	 *
	 * @param bool $fresh Fresh install.
	 * @return void
	 */
	private static function step_settings( $fresh ) {
		$stored = get_option( Settings::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		if ( ! array_key_exists( 'slot_interval_minutes', $stored ) ) {
			$stored['slot_interval_minutes'] = max( 5, min( 120, (int) Settings::get( 'slot_interval_minutes', 30 ) ) );
		}
		if ( ! array_key_exists( 'currency', $stored ) ) {
			$stored['currency'] = (string) Settings::get( 'currency', 'USD' );
		}
		if ( ! array_key_exists( 'payments_enabled', $stored ) ) {
			$stored['payments_enabled'] = 0;
		}
		if ( ! $fresh && ! array_key_exists( 'pointlybooking_default_booking_status', $stored ) ) {
			$stored['pointlybooking_default_booking_status'] = (string) Settings::get( 'pointlybooking_default_booking_status', 'pending' );
		}
		update_option( Settings::OPTION, $stored, true );
		Settings::reset_cache();
	}
}
