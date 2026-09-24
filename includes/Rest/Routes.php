<?php
/**
 * REST route registry.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Instantiates every controller on rest_api_init.
 */
final class Routes {

	/**
	 * Controller classes.
	 *
	 * @var string[]
	 */
	const CONTROLLERS = array(
		Admin\BookingsController::class,
		Admin\CalendarController::class,
		Admin\CatalogController::class,
		Admin\StaffController::class,
		Admin\CustomersController::class,
		Admin\PromoCodesController::class,
		Admin\FormFieldsController::class,
		Admin\ScheduleController::class,
		Admin\SettingsController::class,
		Admin\NotificationsController::class,
		Admin\SystemController::class,
		Front\WizardController::class,
		Front\ManageController::class,
		Front\PortalController::class,
		Front\StripeWebhookController::class,
		Front\LegacyController::class,
	);

	/**
	 * Registers all routes.
	 *
	 * @return void
	 */
	public static function register() {
		foreach ( self::CONTROLLERS as $class ) {
			( new $class() )->register_routes();
		}
		add_filter( 'rest_pre_serve_request', array( Front\WizardController::class, 'serve_ics' ), 10, 3 );
	}
}
