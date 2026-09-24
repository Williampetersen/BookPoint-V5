<?php
/**
 * Plugin Name:       BookPoint Booking & Appointments
 * Plugin URI:        https://wpbookpoint.com/download-for-free/
 * Description:       Appointment booking for any business: services, staff, locations, online payments and a beautiful, mobile-friendly booking experience.
 * Version:           3.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            BookPoint Team
 * Author URI:        https://wpbookpoint.com/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pointly-booking
 * Domain Path:       /languages
 *
 * @package PointlyBooking
 */

defined( 'ABSPATH' ) || exit;

// Another copy of BookPoint is already loaded (e.g. two folders active at once).
if ( defined( 'POINTLYBOOKING_VERSION' ) ) {
	add_action(
		'admin_notices',
		static function () {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Two copies of BookPoint are active. Please deactivate one of them.', 'pointly-booking' ) . '</p></div>';
			}
		}
	);
	return;
}

define( 'POINTLYBOOKING_VERSION', '3.0.0' );
define( 'POINTLYBOOKING_PLUGIN_FILE', __FILE__ );
define( 'POINTLYBOOKING_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'POINTLYBOOKING_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Constants kept from 2.x for add-ons and custom code.
define( 'POINTLYBOOKING_PLUGIN_PATH', POINTLYBOOKING_PLUGIN_DIR );
define( 'POINTLYBOOKING_LIB_PATH', POINTLYBOOKING_PLUGIN_DIR . 'includes/' );
define( 'POINTLYBOOKING_PUBLIC_PATH', POINTLYBOOKING_PLUGIN_DIR . 'build/' );
define( 'POINTLYBOOKING_VIEWS_PATH', POINTLYBOOKING_PLUGIN_DIR . 'templates/' );
define( 'POINTLYBOOKING_BLOCKS_PATH', POINTLYBOOKING_PLUGIN_DIR . 'build/blocks/' );

require_once POINTLYBOOKING_PLUGIN_DIR . 'includes/Autoloader.php';
PointlyBooking\Autoloader::register();
require_once POINTLYBOOKING_PLUGIN_DIR . 'includes/Compat/functions.php';

register_activation_hook( __FILE__, array( 'PointlyBooking\\Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'PointlyBooking\\Installer', 'deactivate' ) );

if ( did_action( 'plugins_loaded' ) ) {
	PointlyBooking\Plugin::boot();
} else {
	add_action( 'plugins_loaded', array( 'PointlyBooking\\Plugin', 'boot' ), 20 );
}
