<?php
/**
 * Plugin settings: schema, defaults, validation and storage.
 *
 * Storage (unchanged since 2.x for backwards compatibility):
 *  - canonical: option `pointlybooking_settings` (array)
 *  - legacy:    table `{prefix}pointlybooking_settings` (key/value), read as a fallback
 *               and written through for the three historical keys.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Settings;

use PointlyBooking\Database\Tables;
use PointlyBooking\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * Settings repository.
 */
final class Settings {

	const OPTION = 'pointlybooking_settings';

	/**
	 * Legacy table values (loaded once per request).
	 *
	 * @var array|null
	 */
	private static $legacy = null;

	/**
	 * Memoised defaults.
	 *
	 * @var array|null
	 */
	private static $defaults = null;

	/**
	 * Keys whose legacy-table name differs from the option key.
	 *
	 * @var array<string,string>
	 */
	const LEGACY_MAP = array(
		'slot_interval_minutes' => 'pointlybooking_slot_interval_minutes',
		'currency'              => 'pointlybooking_default_currency',
		'currency_position'     => 'pointlybooking_currency_position',
	);

	/**
	 * Keys that only administrators with manage_options may read or change.
	 *
	 * @var string[]
	 */
	const SECRET_KEYS = array(
		'stripe_test_secret_key',
		'stripe_live_secret_key',
		'stripe_webhook_secret',
		'payments_stripe_test_secret_key',
		'payments_stripe_live_secret_key',
		'payments_stripe_webhook_secret',
		'paypal_secret',
		'payments_paypal_secret',
	);

	/**
	 * Setting schema: key => [type, default, extra].
	 *
	 * Types: text, textarea, email, url, bool, int, enum, color, time, range, schedule, list, money.
	 *
	 * @return array<string,array>
	 */
	public static function schema() {
		$admin_email = (string) get_option( 'admin_email' );
		$site_name   = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );

		$schema = array(
			// Business profile (onboarding).
			'business_name'                         => array( 'text', $site_name ),
			'business_email'                        => array( 'email', $admin_email ),
			'business_phone'                        => array( 'text', '' ),
			'business_address'                      => array( 'text', '' ),

			// Money.
			'currency'                              => array( 'currency', 'USD' ),
			'currency_position'                     => array( 'enum', 'before', array( 'before', 'after' ) ),

			// Scheduling.
			'slot_interval_minutes'                 => array( 'int', 30, array( 5, 120 ) ),
			'pointlybooking_future_days_limit'      => array( 'int', 60, array( 1, 365 ) ),
			'pointlybooking_default_booking_status' => array( 'enum', 'pending', array( 'pending', 'confirmed', 'cancelled', 'completed' ) ),
			'pointlybooking_open_time'              => array( 'time', '09:00' ),
			'pointlybooking_close_time'             => array( 'time', '17:00' ),
			'pointlybooking_breaks'                 => array( 'ranges', '12:00-13:00' ),
			'pointlybooking_schedule_0'             => array( 'range', '' ),
			'pointlybooking_schedule_1'             => array( 'range', '09:00-17:00' ),
			'pointlybooking_schedule_2'             => array( 'range', '09:00-17:00' ),
			'pointlybooking_schedule_3'             => array( 'range', '09:00-17:00' ),
			'pointlybooking_schedule_4'             => array( 'range', '09:00-17:00' ),
			'pointlybooking_schedule_5'             => array( 'range', '09:00-17:00' ),
			'pointlybooking_schedule_6'             => array( 'range', '' ),
			'pending_payment_timeout_minutes'       => array( 'int', 30, array( 5, 1440 ) ),

			// Emails.
			'pointlybooking_email_enabled'          => array( 'bool', 1 ),
			'pointlybooking_admin_email'            => array( 'email', $admin_email ),
			'pointlybooking_email_from_name'        => array( 'text', $site_name ),
			'pointlybooking_email_from_email'       => array( 'email', $admin_email ),

			// Webhooks.
			'webhooks_enabled'                      => array( 'bool', 0 ),
			'webhooks_secret'                       => array( 'text', '' ),
			'webhooks_url_booking_created'          => array( 'url', '' ),
			'webhooks_url_booking_status_changed'   => array( 'url', '' ),
			'webhooks_url_booking_updated'          => array( 'url', '' ),
			'webhooks_url_booking_cancelled'        => array( 'url', '' ),

			// Payments (manage_options only; see is_payment_key()).
			'payments_enabled'                      => array( 'bool', 0 ),
			'payments_enabled_methods'              => array( 'list', array( 'cash' ), array( 'cash', 'free', 'woocommerce', 'stripe', 'paypal' ) ),
			'payments_default_method'               => array( 'enum', 'cash', array( 'cash', 'free', 'woocommerce', 'stripe', 'paypal' ) ),
			'payments_require_payment_to_confirm'   => array( 'bool', 1 ),
			'payments_wc_product_id'                => array( 'int', 0, array( 0, PHP_INT_MAX ) ),
			'payments_stripe_enabled'               => array( 'bool', 0 ),
			'payments_stripe_flow'                  => array( 'enum', 'elements', array( 'elements', 'checkout' ) ),
			'stripe_mode'                           => array( 'enum', 'test', array( 'test', 'live' ) ),
			'stripe_test_secret_key'                => array( 'text', '' ),
			'stripe_test_publishable_key'           => array( 'text', '' ),
			'stripe_live_secret_key'                => array( 'text', '' ),
			'stripe_live_publishable_key'           => array( 'text', '' ),
			'stripe_webhook_secret'                 => array( 'text', '' ),
			'stripe_success_url'                    => array( 'url', '' ),
			'stripe_cancel_url'                     => array( 'url', '' ),
			'payments_paypal_enabled'               => array( 'bool', 0 ),
			'paypal_mode'                           => array( 'enum', 'test', array( 'test', 'live' ) ),
			'paypal_client_id'                      => array( 'text', '' ),
			'paypal_secret'                         => array( 'text', '' ),
			'paypal_return_url'                     => array( 'url', '' ),
			'paypal_cancel_url'                     => array( 'url', '' ),
		);

		/**
		 * Filters the settings schema (add-ons may register their own keys).
		 *
		 * @param array $schema Schema definition.
		 */
		return (array) apply_filters( 'pointlybooking_settings_schema', $schema );
	}

	/**
	 * Default values (optionally overridden by public/defaults.json for white-label installs).
	 *
	 * @return array
	 */
	public static function defaults() {
		if ( null !== self::$defaults ) {
			return self::$defaults;
		}
		$defaults = array();
		foreach ( self::schema() as $key => $def ) {
			$defaults[ $key ] = $def[1];
		}
		$file = self::defaults_file();
		if ( $file ) {
			$defaults = array_merge( $defaults, $file );
		}
		self::$defaults = $defaults;
		return $defaults;
	}

	/**
	 * Reads optional default overrides shipped by a site builder.
	 *
	 * @return array
	 */
	private static function defaults_file() {
		foreach ( array( 'public/defaults.json', 'public/default-settings.json' ) as $relative ) {
			$path = POINTLYBOOKING_PLUGIN_DIR . $relative;
			if ( ! is_readable( $path ) ) {
				continue;
			}
			$data = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local bundled file.
			if ( is_array( $data ) ) {
				return isset( $data['pointlybooking_settings'] ) && is_array( $data['pointlybooking_settings'] ) ? $data['pointlybooking_settings'] : $data;
			}
		}
		return array();
	}

	/**
	 * Stored option array.
	 *
	 * @return array
	 */
	private static function stored() {
		$value = get_option( self::OPTION, array() );
		return is_array( $value ) ? $value : array();
	}

	/**
	 * Legacy key/value table contents.
	 *
	 * @return array
	 */
	private static function legacy() {
		if ( null !== self::$legacy ) {
			return self::$legacy;
		}
		self::$legacy = array();
		if ( ! Tables::exists( 'settings' ) ) {
			return self::$legacy;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Loaded once per request and memoised in a static.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT setting_key, setting_value FROM %i', Tables::name( 'settings' ) ), ARRAY_A );
		foreach ( (array) $rows as $row ) {
			self::$legacy[ (string) $row['setting_key'] ] = maybe_unserialize( $row['setting_value'] );
		}
		return self::$legacy;
	}

	/**
	 * Reads one setting.
	 *
	 * @param string $key      Setting key.
	 * @param mixed  $fallback Value used when neither stored nor defaulted.
	 * @return mixed
	 */
	public static function get( $key, $fallback = null ) {
		$stored = self::stored();
		if ( array_key_exists( $key, $stored ) ) {
			return $stored[ $key ];
		}

		// Historical aliases written by 2.x screens.
		$aliases = self::aliases();
		if ( isset( $aliases[ $key ] ) && array_key_exists( $aliases[ $key ], $stored ) ) {
			return $stored[ $aliases[ $key ] ];
		}

		$legacy     = self::legacy();
		$legacy_key = self::LEGACY_MAP[ $key ] ?? $key;
		if ( array_key_exists( $legacy_key, $legacy ) ) {
			return $legacy[ $legacy_key ];
		}

		$defaults = self::defaults();
		if ( array_key_exists( $key, $defaults ) ) {
			return $defaults[ $key ];
		}
		return $fallback;
	}

	/**
	 * Alternative option keys that older releases wrote.
	 *
	 * @return array<string,string>
	 */
	private static function aliases() {
		return array(
			'stripe_test_secret_key' => 'payments_stripe_test_secret_key',
			'stripe_live_secret_key' => 'payments_stripe_live_secret_key',
			'stripe_webhook_secret'  => 'payments_stripe_webhook_secret',
			'stripe_success_url'     => 'payments_stripe_success_url',
			'stripe_cancel_url'      => 'payments_stripe_cancel_url',
			'paypal_mode'            => 'payments_paypal_mode',
			'paypal_client_id'       => 'payments_paypal_client_id',
			'paypal_secret'          => 'payments_paypal_secret',
			'paypal_return_url'      => 'payments_paypal_return_url',
			'paypal_cancel_url'      => 'payments_paypal_cancel_url',
		);
	}

	/**
	 * Integer setting helper.
	 *
	 * @param string $key Key.
	 * @return int
	 */
	public static function int( $key ) {
		return (int) self::get( $key, 0 );
	}

	/**
	 * Boolean setting helper.
	 *
	 * @param string $key Key.
	 * @return bool
	 */
	public static function bool( $key ) {
		return 1 === Sanitize::bool01( self::get( $key, 0 ) );
	}

	/**
	 * Every known setting with its effective value.
	 *
	 * @param bool $include_secrets Whether secret keys may be included.
	 * @return array
	 */
	public static function all( $include_secrets = false ) {
		$out = array();
		foreach ( array_keys( self::schema() ) as $key ) {
			if ( ! $include_secrets && in_array( $key, self::SECRET_KEYS, true ) ) {
				continue;
			}
			$out[ $key ] = self::get( $key );
		}
		$out['pointlybooking_remove_data_on_uninstall'] = (int) get_option( 'pointlybooking_remove_data_on_uninstall', 0 );
		return $out;
	}

	/**
	 * Whether a key belongs to the payments group (manage_options only).
	 *
	 * @param string $key Key.
	 * @return bool
	 */
	public static function is_payment_key( $key ) {
		return 0 === strpos( $key, 'payments_' ) || 0 === strpos( $key, 'stripe_' ) || 0 === strpos( $key, 'paypal_' );
	}

	/**
	 * Sanitises one value according to the schema.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Raw value.
	 * @return mixed|\WP_Error Clean value or error.
	 */
	public static function sanitize( $key, $value ) {
		$schema = self::schema();
		if ( ! isset( $schema[ $key ] ) ) {
			return new \WP_Error( 'unknown_setting', sprintf( /* translators: %s: setting key */ __( 'Unknown setting: %s', 'pointly-booking' ), $key ) );
		}
		list( $type, $default ) = $schema[ $key ];
		$extra                  = $schema[ $key ][2] ?? null;

		switch ( $type ) {
			case 'bool':
				return Sanitize::bool01( $value );
			case 'int':
				$range = is_array( $extra ) ? $extra : array( 0, PHP_INT_MAX );
				return Sanitize::int_range( $value, (int) $range[0], (int) $range[1] );
			case 'enum':
				return Sanitize::one_of( $value, (array) $extra, (string) $default );
			case 'list':
				$items = is_array( $value ) ? array_values( array_intersect( array_map( 'strval', $value ), (array) $extra ) ) : array();
				return $items ? $items : (array) $default;
			case 'email':
				$email = Sanitize::email( $value );
				if ( '' === $email && '' !== trim( (string) $value ) ) {
					return new \WP_Error( 'invalid_email', __( 'Please enter a valid email address.', 'pointly-booking' ) );
				}
				return $email;
			case 'url':
				return Sanitize::url( $value );
			case 'time':
				$time = Sanitize::time( $value );
				return '' !== $time ? $time : (string) $default;
			case 'currency':
				$code = strtoupper( Sanitize::text( $value, 3 ) );
				return preg_match( '/^[A-Z]{3}$/', $code ) ? $code : (string) $default;
			case 'range':
				$range = trim( Sanitize::text( $value ) );
				if ( '' === $range ) {
					return '';
				}
				if ( ! self::valid_range( $range ) ) {
					return new \WP_Error( 'invalid_range', __( 'Use the format HH:MM-HH:MM, for example 09:00-17:00.', 'pointly-booking' ) );
				}
				return $range;
			case 'ranges':
				$raw = trim( Sanitize::text( $value ) );
				if ( '' === $raw ) {
					return '';
				}
				$parts = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
				foreach ( $parts as $part ) {
					if ( ! self::valid_range( $part ) ) {
						return new \WP_Error( 'invalid_range', __( 'Each break must use HH:MM-HH:MM and be separated by commas.', 'pointly-booking' ) );
					}
				}
				return implode( ',', $parts );
			case 'textarea':
				return Sanitize::textarea( $value );
			default:
				return Sanitize::text( $value );
		}
	}

	/**
	 * Checks "HH:MM-HH:MM" with end after start.
	 *
	 * @param string $range Range.
	 * @return bool
	 */
	public static function valid_range( $range ) {
		if ( ! preg_match( '/^((?:[01]\d|2[0-3]):[0-5]\d)-((?:[01]\d|2[0-3]):[0-5]\d|24:00)$/', $range, $m ) ) {
			return false;
		}
		return \PointlyBooking\Support\Dates::to_minutes( $m[2] ) > \PointlyBooking\Support\Dates::to_minutes( $m[1] );
	}

	/**
	 * Validates and stores a batch of settings.
	 *
	 * @param array $values         Raw key => value pairs.
	 * @param bool  $allow_payments Whether payment keys may be written.
	 * @return array|\WP_Error Saved values or an error with field messages.
	 */
	public static function update( array $values, $allow_payments = false ) {
		$stored = self::stored();
		$errors = array();

		if ( array_key_exists( 'pointlybooking_remove_data_on_uninstall', $values ) ) {
			update_option( 'pointlybooking_remove_data_on_uninstall', Sanitize::bool01( $values['pointlybooking_remove_data_on_uninstall'] ), false );
			unset( $values['pointlybooking_remove_data_on_uninstall'] );
		}

		foreach ( $values as $key => $raw ) {
			$key = (string) $key;
			if ( self::is_payment_key( $key ) && ! $allow_payments ) {
				continue;
			}
			$clean = self::sanitize( $key, $raw );
			if ( is_wp_error( $clean ) ) {
				if ( 'unknown_setting' === $clean->get_error_code() ) {
					continue;
				}
				$errors[ $key ] = $clean->get_error_message();
				continue;
			}
			$stored[ $key ] = $clean;
		}

		if ( $errors ) {
			return new \WP_Error(
				'invalid_settings',
				__( 'Some settings need your attention.', 'pointly-booking' ),
				array(
					'status' => 400,
					'fields' => $errors,
				)
			);
		}

		// Keep the historical alias keys in sync for code that still reads them.
		foreach ( self::aliases() as $canonical => $alias ) {
			if ( array_key_exists( $canonical, $stored ) ) {
				$stored[ $alias ] = $stored[ $canonical ];
			}
		}
		if ( isset( $stored['stripe_mode'] ) ) {
			$stored['payments_stripe_mode'] = $stored['stripe_mode'];
		}

		update_option( self::OPTION, $stored, true );
		self::write_legacy( $stored );
		self::$defaults = null;
		\PointlyBooking\Support\Cache::bump( 'settings' );
		\PointlyBooking\Support\Cache::bump( 'availability' );

		return self::all( $allow_payments );
	}

	/**
	 * Replaces the whole option array (settings import).
	 *
	 * @param array $values Values.
	 * @return void
	 */
	public static function replace( array $values ) {
		update_option( self::OPTION, $values, true );
		self::write_legacy( $values );
		\PointlyBooking\Support\Cache::flush_all();
	}

	/**
	 * Writes the historical keys through to the legacy table.
	 *
	 * @param array $stored Option values.
	 * @return void
	 */
	private static function write_legacy( array $stored ) {
		if ( ! Tables::exists( 'settings' ) ) {
			return;
		}
		foreach ( self::LEGACY_MAP as $key => $legacy_key ) {
			if ( array_key_exists( $key, $stored ) ) {
				self::set_legacy( $legacy_key, $stored[ $key ] );
			}
		}
		self::$legacy = null;
	}

	/**
	 * Upserts a legacy table row.
	 *
	 * @param string $key   Legacy key.
	 * @param mixed  $value Value.
	 * @return void
	 */
	public static function set_legacy( $key, $value ) {
		global $wpdb;
		$table = Tables::name( 'settings' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom settings table upsert.
		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (setting_key, setting_value, updated_at) VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)',
				$table,
				sanitize_key( $key ),
				maybe_serialize( $value ),
				current_time( 'mysql' )
			)
		);
	}

	/**
	 * Legacy table rows (used by export).
	 *
	 * @return array
	 */
	public static function legacy_rows() {
		self::$legacy = null;
		return self::legacy();
	}

	/**
	 * Clears memoised state (tests, imports).
	 *
	 * @return void
	 */
	public static function reset_cache() {
		self::$legacy   = null;
		self::$defaults = null;
	}
}
