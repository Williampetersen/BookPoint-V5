/**
 * Admin app entry: mounts on #pbk-admin (rendered by PointlyBooking\Admin\Menu).
 */
import { createRoot } from '@wordpress/element';
import App from './App';

const mount = document.getElementById( 'pbk-admin' );

if ( mount ) {
	const config = window.pointlybooking_ADMIN || {};
	createRoot( mount ).render( <App config={ config } initialRoute={ mount.dataset.route || config.route || 'dashboard' } /> );
}
