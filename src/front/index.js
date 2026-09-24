/**
 * Booking wizard entry: enhances every [data-pbk-widget="booking"] mount point.
 */
import { createRoot } from '@wordpress/element';
import BookingWidget from './BookingWidget';

function boot() {
	const config = window.pointlybooking_FRONT || {};
	document.querySelectorAll( '[data-pbk-widget="booking"]' ).forEach( ( node ) => {
		if ( node.dataset.pbkMounted ) {
			return;
		}
		node.dataset.pbkMounted = '1';
		let options = {};
		try {
			options = JSON.parse( node.dataset.pbkConfig || '{}' );
		} catch ( e ) {
			options = {};
		}
		createRoot( node ).render( <BookingWidget config={ config } options={ options } /> );
	} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', boot );
} else {
	boot();
}
