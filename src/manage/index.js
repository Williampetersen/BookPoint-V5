/**
 * Manage-booking page and customer portal entry.
 */
import { createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

function Placeholder() {
	return <p>{ __( 'Loading…', 'pointly-booking' ) }</p>;
}

function boot() {
	document.querySelectorAll( '[data-pbk-widget="manage"], [data-pbk-widget="portal"]' ).forEach( ( node ) => {
		if ( node.dataset.pbkMounted ) {
			return;
		}
		node.dataset.pbkMounted = '1';
		createRoot( node ).render( <Placeholder /> );
	} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', boot );
} else {
	boot();
}
