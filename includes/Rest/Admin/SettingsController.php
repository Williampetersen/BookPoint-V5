<?php
/**
 * Admin settings, booking form design, onboarding and lookups API.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Rest\Admin;

use PointlyBooking\Repositories\AgentRepository;
use PointlyBooking\Repositories\BookingRepository;
use PointlyBooking\Repositories\CategoryRepository;
use PointlyBooking\Repositories\ExtraRepository;
use PointlyBooking\Repositories\LocationRepository;
use PointlyBooking\Repositories\ScheduleRepository;
use PointlyBooking\Repositories\ServiceRepository;
use PointlyBooking\Rest\Controller;
use PointlyBooking\Services\Notifications\Variables;
use PointlyBooking\Services\Payments\PaymentMethods;
use PointlyBooking\Services\Payments\Stripe;
use PointlyBooking\Settings\Design;
use PointlyBooking\Settings\Settings;
use PointlyBooking\Support\Money;
use PointlyBooking\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * /admin/settings…, /admin/booking-form-design…, /admin/onboarding, /admin/lookups.
 */
final class SettingsController extends Controller {

	const ONBOARDING_OPTION = 'pointlybooking_onboarding';

	/**
	 * Placeholder prefix returned instead of stored secrets.
	 */
	const MASK = '••••••••';

	/**
	 * {@inheritDoc}
	 */
	public function register_routes() {
		$can = self::cap( 'pointlybooking_manage_settings' );

		$this->route(
			'/admin/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => $can,
				),
				array(
					'methods'             => array( 'POST', 'PUT', 'PATCH' ),
					'callback'            => array( $this, 'save' ),
					'permission_callback' => $can,
				),
			)
		);
		$this->route(
			'/admin/settings/payments',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'payments_show' ),
					'permission_callback' => array( $this, 'can_manage_payments' ),
				),
				array(
					'methods'             => array( 'POST', 'PUT' ),
					'callback'            => array( $this, 'payments_save' ),
					'permission_callback' => array( $this, 'can_manage_payments' ),
				),
			)
		);
		$this->route(
			'/admin/booking-form-design',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'design_show' ),
					'permission_callback' => $can,
				),
				array(
					'methods'             => array( 'POST', 'PUT' ),
					'callback'            => array( $this, 'design_save' ),
					'permission_callback' => $can,
					'args'                => array( 'config' => self::arg( 'object', true ) ),
				),
			)
		);
		$this->route(
			'/admin/booking-form-design-reset',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'design_reset' ),
				'permission_callback' => $can,
			)
		);
		$this->route(
			'/admin/onboarding',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'onboarding' ),
					'permission_callback' => $can,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'onboarding_save' ),
					'permission_callback' => $can,
				),
			)
		);
		$this->route(
			'/admin/lookups',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'lookups' ),
				'permission_callback' => self::cap( 'pointlybooking_manage_bookings', 'pointlybooking_manage_settings', 'pointlybooking_manage_services', 'pointlybooking_manage_agents', 'pointlybooking_manage_customers' ),
			)
		);
	}

	/**
	 * Only site administrators handle payment credentials.
	 *
	 * @return bool|\WP_Error
	 */
	public function can_manage_payments() {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		return new \WP_Error( 'rest_forbidden', __( 'Only site administrators can change payment settings.', 'pointly-booking' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Masks secret values in a settings array.
	 *
	 * @param array $values Settings.
	 * @return array
	 */
	private static function mask( array $values ) {
		foreach ( Settings::SECRET_KEYS as $key ) {
			if ( isset( $values[ $key ] ) && '' !== (string) $values[ $key ] ) {
				$values[ $key ] = self::MASK . substr( (string) $values[ $key ], -4 );
			}
		}
		return $values;
	}

	/**
	 * Drops unchanged (masked) secrets from an update.
	 *
	 * @param array $values Incoming values.
	 * @return array
	 */
	private static function unmask( array $values ) {
		foreach ( Settings::SECRET_KEYS as $key ) {
			if ( isset( $values[ $key ] ) && 0 === strpos( (string) $values[ $key ], self::MASK ) ) {
				unset( $values[ $key ] );
			}
		}
		return $values;
	}

	/**
	 * All settings. Payment keys (secrets masked) are included for administrators.
	 *
	 * @return \WP_REST_Response
	 */
	public function show() {
		$admin  = current_user_can( 'manage_options' );
		$values = Settings::all( $admin );
		if ( ! $admin ) {
			foreach ( array_keys( $values ) as $key ) {
				if ( Settings::is_payment_key( $key ) ) {
					unset( $values[ $key ] );
				}
			}
		}
		return $this->ok( self::mask( $values ) );
	}

	/**
	 * Saves settings (validated against the schema; field errors returned in data.fields).
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save( \WP_REST_Request $r ) {
		$body = $this->body( $r );
		if ( isset( $body['settings'] ) && is_array( $body['settings'] ) ) {
			$body = $body['settings'];
		}
		$admin  = current_user_can( 'manage_options' );
		$result = Settings::update( self::unmask( $body ), $admin );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( array_key_exists( 'slot_interval_minutes', $body ) ) {
			ScheduleRepository::sync_settings_row( Settings::int( 'slot_interval_minutes' ) );
		}
		return $this->show();
	}

	/**
	 * 2.x nested payments shape.
	 *
	 * @return array
	 */
	private static function payments_shape() {
		$s = Settings::all( true );
		return array(
			'payments_enabled'           => (int) $s['payments_enabled'],
			'enabled_methods'            => array_values( (array) $s['payments_enabled_methods'] ),
			'default_method'             => (string) $s['payments_default_method'],
			'require_payment_to_confirm' => (int) $s['payments_require_payment_to_confirm'],
			'woocommerce'                => array(
				'product_id' => (int) $s['payments_wc_product_id'],
				'available'  => class_exists( 'WooCommerce' ),
			),
			'stripe'                     => array(
				'enabled'              => (int) $s['payments_stripe_enabled'],
				'flow'                 => Stripe::flow(),
				'mode'                 => (string) $s['stripe_mode'],
				'test_secret_key'      => (string) $s['stripe_test_secret_key'],
				'test_publishable_key' => (string) $s['stripe_test_publishable_key'],
				'live_secret_key'      => (string) $s['stripe_live_secret_key'],
				'live_publishable_key' => (string) $s['stripe_live_publishable_key'],
				'webhook_secret'       => (string) $s['stripe_webhook_secret'],
				'success_url'          => (string) $s['stripe_success_url'],
				'cancel_url'           => (string) $s['stripe_cancel_url'],
				'webhook_url'          => rest_url( self::NS . '/webhooks/stripe' ),
			),
			'paypal'                     => array(
				'enabled'    => (int) $s['payments_paypal_enabled'],
				'mode'       => (string) $s['paypal_mode'],
				'client_id'  => (string) $s['paypal_client_id'],
				'secret'     => (string) $s['paypal_secret'],
				'return_url' => (string) $s['paypal_return_url'],
				'cancel_url' => (string) $s['paypal_cancel_url'],
			),
			'configured'                 => array_combine(
				PaymentMethods::ALL,
				array_map( array( PaymentMethods::class, 'is_configured' ), PaymentMethods::ALL )
			),
		);
	}

	/**
	 * GET /admin/settings/payments.
	 *
	 * @return \WP_REST_Response
	 */
	public function payments_show() {
		$payments = self::payments_shape();
		foreach ( array( 'test_secret_key', 'live_secret_key', 'webhook_secret' ) as $key ) {
			if ( '' !== $payments['stripe'][ $key ] ) {
				$payments['stripe'][ $key ] = self::MASK . substr( $payments['stripe'][ $key ], -4 );
			}
		}
		if ( '' !== $payments['paypal']['secret'] ) {
			$payments['paypal']['secret'] = self::MASK . substr( $payments['paypal']['secret'], -4 );
		}
		return new \WP_REST_Response(
			array(
				'status'   => 'success',
				'success'  => true,
				'data'     => $payments,
				'payments' => $payments,
			),
			200
		);
	}

	/**
	 * POST /admin/settings/payments (accepts the nested 2.x shape).
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function payments_save( \WP_REST_Request $r ) {
		$body = $this->body( $r );
		if ( isset( $body['payments'] ) && is_array( $body['payments'] ) ) {
			$body = $body['payments'];
		}
		$flat = array();
		$map  = array(
			'payments_enabled'           => 'payments_enabled',
			'enabled_methods'            => 'payments_enabled_methods',
			'default_method'             => 'payments_default_method',
			'require_payment_to_confirm' => 'payments_require_payment_to_confirm',
		);
		foreach ( $map as $from => $to ) {
			if ( array_key_exists( $from, $body ) ) {
				$flat[ $to ] = $body[ $from ];
			}
		}
		if ( isset( $body['woocommerce']['product_id'] ) ) {
			$flat['payments_wc_product_id'] = $body['woocommerce']['product_id'];
		}
		$stripe = array(
			'enabled'              => 'payments_stripe_enabled',
			'flow'                 => 'payments_stripe_flow',
			'mode'                 => 'stripe_mode',
			'test_secret_key'      => 'stripe_test_secret_key',
			'test_publishable_key' => 'stripe_test_publishable_key',
			'live_secret_key'      => 'stripe_live_secret_key',
			'live_publishable_key' => 'stripe_live_publishable_key',
			'webhook_secret'       => 'stripe_webhook_secret',
			'success_url'          => 'stripe_success_url',
			'cancel_url'           => 'stripe_cancel_url',
		);
		foreach ( $stripe as $from => $to ) {
			if ( isset( $body['stripe'] ) && is_array( $body['stripe'] ) && array_key_exists( $from, $body['stripe'] ) ) {
				$flat[ $to ] = $body['stripe'][ $from ];
			}
		}
		$paypal = array(
			'enabled'    => 'payments_paypal_enabled',
			'mode'       => 'paypal_mode',
			'client_id'  => 'paypal_client_id',
			'secret'     => 'paypal_secret',
			'return_url' => 'paypal_return_url',
			'cancel_url' => 'paypal_cancel_url',
		);
		foreach ( $paypal as $from => $to ) {
			if ( isset( $body['paypal'] ) && is_array( $body['paypal'] ) && array_key_exists( $from, $body['paypal'] ) ) {
				$flat[ $to ] = $body['paypal'][ $from ];
			}
		}
		// Flat keys are accepted as well.
		foreach ( $body as $key => $value ) {
			if ( is_string( $key ) && Settings::is_payment_key( $key ) && ! isset( $flat[ $key ] ) ) {
				$flat[ $key ] = $value;
			}
		}
		$flat = self::unmask( $flat );
		foreach ( $flat as $key => $value ) {
			if ( is_string( $value ) ) {
				$flat[ $key ] = trim( $value );
			}
		}

		// Keep the method list and the per-provider switches consistent.
		if ( isset( $flat['payments_enabled_methods'] ) && is_array( $flat['payments_enabled_methods'] ) ) {
			$methods                         = array_map( 'strval', $flat['payments_enabled_methods'] );
			$flat['payments_stripe_enabled'] = in_array( 'stripe', $methods, true ) ? 1 : 0;
			$flat['payments_paypal_enabled'] = in_array( 'paypal', $methods, true ) ? 1 : 0;
			if ( isset( $flat['payments_default_method'] ) && ! in_array( (string) $flat['payments_default_method'], $methods, true ) && $methods ) {
				$flat['payments_default_method'] = $methods[0];
			}
		}

		$result = Settings::update( $flat, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->payments_show();
	}

	/**
	 * Booking form design.
	 *
	 * @return \WP_REST_Response
	 */
	public function design_show() {
		return $this->design_response( Design::get() );
	}

	/**
	 * Response carrying both envelopes (3.0 data + 2.x config).
	 *
	 * @param array $config Design.
	 * @return \WP_REST_Response
	 */
	private function design_response( array $config ) {
		return new \WP_REST_Response(
			array(
				'status'   => 'success',
				'success'  => true,
				'data'     => $config,
				'config'   => $config,
				'defaults' => Design::defaults(),
			),
			200
		);
	}

	/**
	 * Saves the design (whitelist-sanitised).
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function design_save( \WP_REST_Request $r ) {
		$config = $r->get_param( 'config' );
		if ( ! is_array( $config ) ) {
			return $this->error( 'invalid_config', __( 'Invalid design data.', 'pointly-booking' ) );
		}
		return $this->design_response( Design::save( $config ) );
	}

	/**
	 * Restores the default design.
	 *
	 * @return \WP_REST_Response
	 */
	public function design_reset() {
		return $this->design_response( Design::reset() );
	}

	/**
	 * Onboarding progress.
	 *
	 * @return \WP_REST_Response
	 */
	public function onboarding() {
		$state = (array) get_option( self::ONBOARDING_OPTION, array() );
		return $this->ok(
			array(
				'completed' => ! empty( $state['completed'] ),
				'dismissed' => ! empty( $state['dismissed'] ),
				'steps'     => array(
					'business' => ! empty( $state['business'] ),
					'service'  => ServiceRepository::count_all() > 0,
					'hours'    => (bool) ScheduleRepository::weekly( 0 ),
				),
				'business'  => array(
					'business_name'    => Settings::get( 'business_name' ),
					'business_email'   => Settings::get( 'business_email' ),
					'business_phone'   => Settings::get( 'business_phone' ),
					'business_address' => Settings::get( 'business_address' ),
					'currency'         => Settings::get( 'currency' ),
					'timezone'         => wp_timezone_string(),
				),
			)
		);
	}

	/**
	 * Saves one onboarding step: business | service | hours | complete | dismiss.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function onboarding_save( \WP_REST_Request $r ) {
		$body  = $this->body( $r );
		$step  = Sanitize::key( $body['step'] ?? '' );
		$state = (array) get_option( self::ONBOARDING_OPTION, array() );

		switch ( $step ) {
			case 'business':
				$values = array_intersect_key( (array) ( $body['values'] ?? array() ), array_flip( array( 'business_name', 'business_email', 'business_phone', 'business_address', 'currency', 'currency_position' ) ) );
				$result = Settings::update( $values );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				$state['business'] = 1;
				break;

			case 'service':
				$values = (array) ( $body['values'] ?? array() );
				$name   = Sanitize::text( $values['name'] ?? '', 190 );
				if ( '' === $name ) {
					return $this->error( 'invalid_name', __( 'Please enter a service name.', 'pointly-booking' ), 400, array( 'field' => 'name' ) );
				}
				$id = ServiceRepository::create(
					array(
						'name'             => $name,
						'duration_minutes' => Sanitize::int_range( $values['duration_minutes'] ?? 60, 5, 1440 ),
						'price_cents'      => (int) round( Sanitize::money( $values['price'] ?? 0 ) * 100 ),
						'currency'         => Settings::get( 'currency' ),
						'is_active'        => 1,
						'capacity'         => 1,
					)
				);
				if ( ! $id ) {
					return $this->error( 'save_failed', __( 'The service could not be saved.', 'pointly-booking' ), 500 );
				}
				// No staff links needed: a service without linked staff is offered by every active staff member.
				break;

			case 'hours':
				$week = StaffController::clean_week( (array) ( $body['values']['schedule'] ?? array() ) );
				if ( ! ScheduleRepository::replace_weekly( 0, $week ) ) {
					return $this->error( 'save_failed', __( 'The working hours could not be saved.', 'pointly-booking' ), 500 );
				}
				if ( isset( $body['values']['slot_interval_minutes'] ) ) {
					Settings::update( array( 'slot_interval_minutes' => $body['values']['slot_interval_minutes'] ) );
					ScheduleRepository::sync_settings_row( Settings::int( 'slot_interval_minutes' ) );
				}
				break;

			case 'complete':
				$state['completed'] = 1;
				break;

			case 'dismiss':
				$state['dismissed'] = 1;
				break;

			default:
				return $this->error( 'invalid_step', __( 'Unknown onboarding step.', 'pointly-booking' ) );
		}
		update_option( self::ONBOARDING_OPTION, $state, false );
		return $this->onboarding();
	}

	/**
	 * Compact reference data for filters and forms (one request instead of five).
	 *
	 * @return \WP_REST_Response
	 */
	public function lookups() {
		$pick = static function ( array $rows, array $keys ) {
			return array_map(
				static function ( $row ) use ( $keys ) {
					return array_intersect_key( $row, array_flip( $keys ) );
				},
				$rows
			);
		};

		$agents = array_map(
			static function ( $a ) {
				return array(
					'id'        => (int) $a['id'],
					'name'      => $a['name'],
					'is_active' => (int) $a['is_active'],
					'image_url' => AgentRepository::image_url( $a['image_id'], 'thumbnail' ),
				);
			},
			AgentRepository::all()
		);

		$statuses = array();
		foreach ( BookingRepository::STATUSES as $status ) {
			$statuses[] = array(
				'value' => $status,
				'label' => Variables::status_label( $status ),
			);
		}

		return $this->ok(
			array(
				'services'   => $pick( ServiceRepository::all(), array( 'id', 'name', 'duration_minutes', 'price', 'is_active', 'color' ) ),
				'categories' => $pick( CategoryRepository::all(), array( 'id', 'name' ) ),
				'extras'     => $pick( ExtraRepository::all(), array( 'id', 'name', 'price' ) ),
				'agents'     => $agents,
				'locations'  => $pick( LocationRepository::all(), array( 'id', 'name', 'status' ) ),
				'statuses'   => $statuses,
				'payment'    => array(
					'enabled' => PaymentMethods::enabled(),
					'methods' => PaymentMethods::labels(),
				),
				'currency'   => array(
					'code'     => Money::currency(),
					'symbol'   => Money::symbol(),
					'position' => Settings::get( 'currency_position' ),
				),
				'site'       => array(
					'timezone'    => wp_timezone_string(),
					'date_format' => get_option( 'date_format' ),
					'time_format' => get_option( 'time_format' ),
					'week_starts' => (int) get_option( 'start_of_week', 1 ),
				),
			)
		);
	}
}
