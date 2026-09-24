<?php
/**
 * Plugin bootstrap: wires every module into WordPress.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking;

defined( 'ABSPATH' ) || exit;

/**
 * Composition root.
 */
final class Plugin {

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Boots the plugin (plugins_loaded).
	 *
	 * @return void
	 */
	public static function boot() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		Database\Migrator::maybe_upgrade();

		// Domain listeners (notifications, webhooks, audit trail).
		Services\Notifications\WorkflowEngine::register();
		Services\Webhooks::register();
		Services\Audit::register();

		// Background jobs.
		add_action( Installer::CRON_CLEANUP, array( Services\Booking\BookingService::class, 'expire_stale_payments' ) );
		add_action( 'init', array( __CLASS__, 'ensure_cron' ) );

		// Integrations.
		Integrations\WooCommerce::register();

		// REST API.
		add_action( 'rest_api_init', array( Rest\Routes::class, 'register' ) );

		// Front end.
		Frontend\Assets::register();
		Frontend\Shortcodes::register();
		Frontend\Block::register();
		Frontend\ManagePage::register();
		Frontend\Portal::register();
		Frontend\LegacyAjax::register();

		// Admin.
		if ( is_admin() ) {
			Admin\Menu::register();
			Admin\Assets::register();
			Admin\Actions::register();
			Admin\PluginLinks::register();
		}

		/**
		 * Fires after BookPoint has loaded all modules.
		 */
		do_action( 'pointlybooking_loaded' );
	}

	/**
	 * Schedules the hourly clean-up job (for sites updated without re-activation).
	 *
	 * @return void
	 */
	public static function ensure_cron() {
		if ( ! wp_next_scheduled( Installer::CRON_CLEANUP ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', Installer::CRON_CLEANUP );
		}
	}

	/**
	 * Absolute URL of a file inside the plugin.
	 *
	 * @param string $path Relative path.
	 * @return string
	 */
	public static function url( $path = '' ) {
		return POINTLYBOOKING_PLUGIN_URL . ltrim( $path, '/' );
	}

	/**
	 * Absolute path of a file inside the plugin.
	 *
	 * @param string $path Relative path.
	 * @return string
	 */
	public static function path( $path = '' ) {
		return POINTLYBOOKING_PLUGIN_DIR . ltrim( $path, '/' );
	}
}
