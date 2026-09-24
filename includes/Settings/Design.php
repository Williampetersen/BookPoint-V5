<?php
/**
 * Booking form design (option `pointlybooking_booking_form_design`).
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Settings;

use PointlyBooking\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * Reads, sanitises and stores the booking wizard design configuration.
 */
final class Design {

	const OPTION = 'pointlybooking_booking_form_design';

	/**
	 * Step keys in their canonical order.
	 *
	 * @var string[]
	 */
	const STEPS = array( 'location', 'category', 'service', 'extras', 'agents', 'datetime', 'customer', 'payment', 'review', 'confirm' );

	/**
	 * Steps that can never be switched off.
	 *
	 * @var string[]
	 */
	const REQUIRED_STEPS = array( 'service', 'agents', 'datetime', 'customer', 'review', 'confirm' );

	/**
	 * English defaults written by 2.x. When a stored text still equals one of
	 * these, it is treated as "not customised" so the translated default is shown.
	 *
	 * @var string[]
	 */
	const LEGACY_DEFAULT_TEXTS = array(
		'Select Location',
		'Location Selection',
		'Please select a location',
		'Choose Category',
		'Select a category',
		'Choose Service',
		'Select a service',
		'Service Extras',
		'Pick extras',
		'Choose Agent',
		'Pick your agent',
		'Date & Time',
		'Choose Date & Time',
		'Pick an available slot',
		'Customer Info',
		'Customer Information',
		'Enter your details',
		'Payment',
		'Choose a payment method',
		'Review Order',
		'Confirm everything',
		'Confirmation',
		'Confirm',
		'Done',
		'<- Back',
		'Next ->',
		'Need help?',
		'+1 234 567 89',
	);

	/**
	 * Default configuration. Texts are empty so the UI falls back to translated defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		$steps = self::defaults_steps();

		$defaults = array(
			'version'      => 3,
			'appearance'   => array(
				'primaryColor'    => '#4f46e5',
				'borderStyle'     => 'rounded',
				'darkModeDefault' => null,
				'font'            => 'system',
			),
			'steps'        => $steps,
			'texts'        => array(
				'helpTitle'      => '',
				'helpPhone'      => '',
				'nextLabel'      => '',
				'backLabel'      => '',
				'successTitle'   => '',
				'successMessage' => '',
			),
			'fieldsLayout' => array(
				'customer' => array(
					'fields' => array(
						array(
							'id'       => 'first_name',
							'required' => true,
							'width'    => 'half',
						),
						array(
							'id'       => 'last_name',
							'required' => false,
							'width'    => 'half',
						),
						array(
							'id'       => 'email',
							'required' => true,
							'width'    => 'full',
						),
						array(
							'id'       => 'phone',
							'required' => false,
							'width'    => 'full',
						),
					),
				),
				'booking'  => array(
					'fields' => array(
						array(
							'id'       => 'notes',
							'required' => false,
							'width'    => 'full',
						),
					),
				),
			),
			'behavior'     => array(
				'showSummary'      => true,
				'autoSelectDate'   => true,
				'showTimezone'     => true,
				'confirmOnClose'   => true,
				'showPromoCode'    => true,
				'serviceSearchMin' => 6,
			),
		);

		$file = self::defaults_file();
		return $file ? self::merge( $defaults, $file ) : $defaults;
	}

	/**
	 * Optional white-label design defaults shipped as public/default-design.json.
	 *
	 * @return array
	 */
	private static function defaults_file() {
		$path = POINTLYBOOKING_PLUGIN_DIR . 'public/default-design.json';
		if ( ! is_readable( $path ) ) {
			return array();
		}
		$data = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local bundled file.
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Custom step image URL. Links to the 2.x bundled images (removed in 3.0) are dropped so
	 * the step falls back to the built-in illustration.
	 *
	 * @param mixed $url URL.
	 * @return string
	 */
	private static function image_url( $url ) {
		$url = Sanitize::url( $url );
		if ( '' !== $url && preg_match( '#/wp-content/plugins/[^/]+/public/(images|icons)/#', $url ) ) {
			return '';
		}
		return $url;
	}

	/**
	 * Effective design: defaults merged with the stored configuration.
	 *
	 * @return array
	 */
	public static function get() {
		$stored = get_option( self::OPTION, null );
		$config = self::defaults();
		if ( is_array( $stored ) ) {
			$config = self::merge( $config, self::sanitize( $stored ) );
		}
		$config['steps'] = self::normalize_steps( $config['steps'] ?? array() );
		return $config;
	}

	/**
	 * Stores a design after sanitising it.
	 *
	 * @param array $config Raw configuration.
	 * @return array Stored configuration.
	 */
	public static function save( array $config ) {
		$clean          = self::sanitize( $config );
		$clean['steps'] = self::normalize_steps( $clean['steps'] ?? array() );
		update_option( self::OPTION, $clean, false );
		\PointlyBooking\Support\Cache::bump( 'catalog' );
		return self::get();
	}

	/**
	 * Resets to defaults.
	 *
	 * @return array
	 */
	public static function reset() {
		update_option( self::OPTION, self::defaults(), false );
		\PointlyBooking\Support\Cache::bump( 'catalog' );
		return self::get();
	}

	/**
	 * Recursively merges $overrides into $base (lists are replaced, maps merged).
	 *
	 * @param array $base      Base array.
	 * @param array $overrides Overrides.
	 * @return array
	 */
	private static function merge( array $base, array $overrides ) {
		foreach ( $overrides as $key => $value ) {
			if ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) && ! wp_is_numeric_array( $value ) && ! wp_is_numeric_array( $base[ $key ] ) ) {
				$base[ $key ] = self::merge( $base[ $key ], $value );
			} else {
				$base[ $key ] = $value;
			}
		}
		return $base;
	}

	/**
	 * Text value, blanking legacy English defaults.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $max   Max length.
	 * @return string
	 */
	private static function text( $value, $max = 200 ) {
		$text = Sanitize::text( $value, $max );
		return in_array( $text, self::LEGACY_DEFAULT_TEXTS, true ) ? '' : $text;
	}

	/**
	 * Whitelist-based sanitiser.
	 *
	 * @param array $config Raw configuration.
	 * @return array
	 */
	public static function sanitize( array $config ) {
		$out = array( 'version' => 3 );

		$appearance        = is_array( $config['appearance'] ?? null ) ? $config['appearance'] : array();
		$out['appearance'] = array();
		if ( isset( $appearance['primaryColor'] ) ) {
			$color = Sanitize::color( $appearance['primaryColor'] );
			if ( '' !== $color ) {
				$out['appearance']['primaryColor'] = $color;
			}
		}
		if ( isset( $appearance['borderStyle'] ) ) {
			$out['appearance']['borderStyle'] = Sanitize::one_of( $appearance['borderStyle'], array( 'rounded', 'flat', 'square', 'pill' ), 'rounded' );
		}
		if ( array_key_exists( 'darkModeDefault', $appearance ) ) {
			$out['appearance']['darkModeDefault'] = null === $appearance['darkModeDefault'] || 'auto' === $appearance['darkModeDefault'] ? null : (bool) $appearance['darkModeDefault'];
		}
		if ( isset( $appearance['font'] ) ) {
			$out['appearance']['font'] = Sanitize::one_of( $appearance['font'], array( 'system', 'inherit' ), 'system' );
		}

		if ( isset( $config['steps'] ) && is_array( $config['steps'] ) ) {
			$out['steps'] = array();
			foreach ( $config['steps'] as $step ) {
				if ( ! is_array( $step ) ) {
					continue;
				}
				$key = self::normalize_step_key( $step['key'] ?? '' );
				if ( '' === $key ) {
					continue;
				}
				$out['steps'][] = array(
					'key'             => $key,
					'enabled'         => in_array( $key, self::REQUIRED_STEPS, true ) ? true : ( ! isset( $step['enabled'] ) || ( false !== $step['enabled'] && 0 !== $step['enabled'] && '0' !== $step['enabled'] ) ),
					'title'           => self::text( $step['title'] ?? '' ),
					'subtitle'        => self::text( $step['subtitle'] ?? '', 400 ),
					'image'           => Sanitize::key( $step['image'] ?? '' ),
					'imageUrl'        => self::image_url( $step['imageUrl'] ?? '' ),
					'imageId'         => absint( $step['imageId'] ?? 0 ),
					'buttonBackLabel' => self::text( $step['buttonBackLabel'] ?? '', 60 ),
					'buttonNextLabel' => self::text( $step['buttonNextLabel'] ?? '', 60 ),
					'accentOverride'  => Sanitize::color( $step['accentOverride'] ?? '' ),
					'showLeftPanel'   => ! isset( $step['showLeftPanel'] ) || (bool) $step['showLeftPanel'],
					'showHelpBox'     => ! isset( $step['showHelpBox'] ) || (bool) $step['showHelpBox'],
				);
			}
		}

		$texts = is_array( $config['texts'] ?? null ) ? $config['texts'] : array();
		if ( ! empty( $config['layout']['helpPhone'] ) && empty( $texts['helpPhone'] ) ) {
			$texts['helpPhone'] = $config['layout']['helpPhone'];
		}
		$out['texts'] = array();
		foreach ( array( 'helpTitle', 'helpPhone', 'nextLabel', 'backLabel', 'successTitle' ) as $key ) {
			if ( isset( $texts[ $key ] ) ) {
				$out['texts'][ $key ] = self::text( $texts[ $key ], 120 );
			}
		}
		if ( isset( $texts['successMessage'] ) ) {
			$out['texts']['successMessage'] = Sanitize::textarea( $texts['successMessage'], 1000 );
		}

		if ( isset( $config['fieldsLayout'] ) && is_array( $config['fieldsLayout'] ) ) {
			$out['fieldsLayout'] = array();
			foreach ( array( 'customer', 'booking' ) as $scope ) {
				$fields = $config['fieldsLayout'][ $scope ]['fields'] ?? null;
				if ( ! is_array( $fields ) ) {
					continue;
				}
				$clean = array();
				foreach ( $fields as $field ) {
					if ( ! is_array( $field ) ) {
						continue;
					}
					$id = Sanitize::key( $field['id'] ?? '' );
					if ( '' === $id ) {
						continue;
					}
					$entry = array(
						'id'    => $id,
						'width' => Sanitize::one_of( $field['width'] ?? 'full', array( 'half', 'full' ), 'full' ),
					);
					if ( isset( $field['required'] ) ) {
						$entry['required'] = (bool) $field['required'];
					}
					$clean[] = $entry;
				}
				$out['fieldsLayout'][ $scope ] = array( 'fields' => $clean );
			}
		}

		$behavior = is_array( $config['behavior'] ?? null ) ? $config['behavior'] : array();
		if ( $behavior ) {
			$out['behavior'] = array();
			foreach ( array( 'showSummary', 'autoSelectDate', 'showTimezone', 'confirmOnClose', 'showPromoCode' ) as $flag ) {
				if ( isset( $behavior[ $flag ] ) ) {
					$out['behavior'][ $flag ] = (bool) $behavior[ $flag ];
				}
			}
			if ( isset( $behavior['serviceSearchMin'] ) ) {
				$out['behavior']['serviceSearchMin'] = Sanitize::int_range( $behavior['serviceSearchMin'], 0, 100 );
			}
		}

		return $out;
	}

	/**
	 * Maps historical step keys to the canonical ones.
	 *
	 * @param mixed $key Raw key.
	 * @return string
	 */
	public static function normalize_step_key( $key ) {
		$key = Sanitize::key( $key );
		$map = array(
			'agent'        => 'agents',
			'staff'        => 'agents',
			'confirmation' => 'confirm',
			'done'         => 'confirm',
			'details'      => 'customer',
		);
		$key = $map[ $key ] ?? $key;
		return in_array( $key, self::STEPS, true ) ? $key : '';
	}

	/**
	 * De-duplicates steps and appends any missing ones.
	 *
	 * @param array $steps Steps.
	 * @return array
	 */
	private static function normalize_steps( array $steps ) {
		$defaults = array();
		foreach ( self::defaults_steps() as $step ) {
			$defaults[ $step['key'] ] = $step;
		}
		$out  = array();
		$seen = array();
		foreach ( $steps as $step ) {
			$key = self::normalize_step_key( $step['key'] ?? '' );
			if ( '' === $key || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = array_merge( $defaults[ $key ], $step, array( 'key' => $key ) );
		}
		foreach ( $defaults as $key => $step ) {
			if ( ! isset( $seen[ $key ] ) ) {
				$out[] = $step;
			}
		}
		return $out;
	}

	/**
	 * Default step list without the file override.
	 *
	 * @return array
	 */
	private static function defaults_steps() {
		$steps = array();
		foreach ( self::STEPS as $key ) {
			$steps[] = array(
				'key'             => $key,
				'enabled'         => ! in_array( $key, array( 'location', 'category' ), true ),
				'title'           => '',
				'subtitle'        => '',
				'image'           => '',
				'imageUrl'        => '',
				'imageId'         => 0,
				'buttonBackLabel' => '',
				'buttonNextLabel' => '',
				'accentOverride'  => '',
				'showLeftPanel'   => true,
				'showHelpBox'     => true,
			);
		}
		return $steps;
	}
}
