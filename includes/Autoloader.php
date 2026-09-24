<?php
/**
 * PSR-4 style autoloader for the PointlyBooking namespace.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking;

defined( 'ABSPATH' ) || exit;

/**
 * Maps `PointlyBooking\Foo\Bar` to `includes/Foo/Bar.php`.
 */
final class Autoloader {

	/**
	 * Registers the autoloader with SPL.
	 *
	 * @return void
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Loads a class file when the class belongs to this plugin's namespace.
	 *
	 * @param string $class_name Fully qualified class name.
	 * @return void
	 */
	public static function autoload( $class_name ) {
		$prefix = __NAMESPACE__ . '\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = __DIR__ . '/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
