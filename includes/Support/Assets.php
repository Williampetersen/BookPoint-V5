<?php
/**
 * Registers built script/style bundles (build/<entry>/index.*).
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Asset helper shared by the admin and front ends.
 */
final class Assets {

	const DOMAIN = 'pointly-booking';

	/**
	 * Handle => build sub directory (used to merge chunk translations).
	 *
	 * @var array<string,string>
	 */
	private static $entries = array();

	/**
	 * Whether the translation filter is attached.
	 *
	 * @var bool
	 */
	private static $filtered = false;

	/**
	 * Registers build/<entry>/index.js and, when present, index.css (+ RTL variant).
	 *
	 * @param string   $handle Handle.
	 * @param string   $entry  Build sub directory, e.g. "admin".
	 * @param string[] $extra  Extra script dependencies.
	 * @return bool Whether the bundle exists.
	 */
	public static function register( $handle, $entry, array $extra = array() ) {
		$dir   = POINTLYBOOKING_PLUGIN_DIR . 'build/' . $entry . '/';
		$asset = $dir . 'index.asset.php';
		if ( ! is_readable( $dir . 'index.js' ) ) {
			return false;
		}
		$meta = is_readable( $asset ) ? require $asset : array(
			'dependencies' => array(),
			'version'      => POINTLYBOOKING_VERSION,
		);

		wp_register_script(
			$handle,
			POINTLYBOOKING_PLUGIN_URL . 'build/' . $entry . '/index.js',
			array_values( array_unique( array_merge( (array) $meta['dependencies'], $extra ) ) ),
			$meta['version'],
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		if ( in_array( 'wp-i18n', (array) $meta['dependencies'], true ) ) {
			wp_set_script_translations( $handle, self::DOMAIN, POINTLYBOOKING_PLUGIN_DIR . 'languages' );
			self::$entries[ $handle ] = $entry;
			if ( ! self::$filtered ) {
				add_filter( 'pre_load_script_translations', array( __CLASS__, 'merge_chunk_translations' ), 10, 4 );
				self::$filtered = true;
			}
		}

		foreach ( array( 'index.css', 'style-index.css' ) as $css ) {
			if ( is_readable( $dir . $css ) ) {
				$style = 'index.css' === $css ? $handle : $handle . '-style';
				wp_register_style( $style, POINTLYBOOKING_PLUGIN_URL . 'build/' . $entry . '/' . $css, array(), $meta['version'] );
				wp_style_add_data( $style, 'rtl', 'replace' );
			}
		}
		return true;
	}

	/**
	 * Adds the translations of lazily loaded chunks (build/<entry>/*.js) to the entry's
	 * translations, so strings in code-split screens are translated too.
	 *
	 * @param string|false|null $translations Pre-filtered translations.
	 * @param string|false      $file         Expected JSON file of the entry script.
	 * @param string            $handle       Script handle.
	 * @param string            $domain       Text domain.
	 * @return string|false|null
	 */
	public static function merge_chunk_translations( $translations, $file, $handle, $domain ) {
		if ( null !== $translations || self::DOMAIN !== $domain || ! isset( self::$entries[ $handle ] ) || ! is_string( $file ) || '' === $file ) {
			return $translations;
		}
		$entry     = self::$entries[ $handle ];
		$main_hash = md5( 'build/' . $entry . '/index.js' );
		if ( false === strpos( $file, $main_hash ) ) {
			return $translations;
		}

		$messages = array();
		$header   = null;
		$files    = array( $file );
		foreach ( (array) glob( POINTLYBOOKING_PLUGIN_DIR . 'build/' . $entry . '/*.js' ) as $chunk ) {
			$name = basename( $chunk );
			if ( 'index.js' !== $name ) {
				$files[] = str_replace( $main_hash, md5( 'build/' . $entry . '/' . $name ), $file );
			}
		}
		foreach ( $files as $path ) {
			if ( ! is_readable( $path ) ) {
				continue;
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local language file, same as core's load_script_translations().
			$data = json_decode( (string) file_get_contents( $path ), true );
			if ( ! isset( $data['locale_data']['messages'] ) || ! is_array( $data['locale_data']['messages'] ) ) {
				continue;
			}
			$found = $data['locale_data']['messages'];
			if ( null === $header && isset( $found[''] ) ) {
				$header = $found[''];
			}
			unset( $found[''] );
			$messages = array_merge( $messages, $found );
		}
		if ( ! $messages ) {
			return null;
		}
		$messages = array( '' => $header ? $header : array( 'domain' => 'messages' ) ) + $messages;
		return (string) wp_json_encode(
			array(
				'domain'      => 'messages',
				'locale_data' => array( 'messages' => $messages ),
			)
		);
	}
}
