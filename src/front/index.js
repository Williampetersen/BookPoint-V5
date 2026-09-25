/**
 * Booking wizard entry: enhances every [data-pbk-widget="booking"] mount point,
 * supports the 2.x triggers and finishes payments after a provider redirect.
 */
import { createRoot } from '@wordpress/element';
import BookingWidget from './BookingWidget';
import './front.css';

const openers = [];

function readPaymentReturn() {
	const params = new URLSearchParams( window.location.search );
	const type = params.get( 'pointlybooking_payment' );
	const bookingId = Number( params.get( 'booking_id' ) );
	const key = params.get( 'key' ) || '';
	if (
		! type ||
		! bookingId ||
		! /^[a-f0-9]{40}([a-f0-9]{24})?$/i.test( key )
	) {
		return null;
	}
	const info = {
		type,
		bookingId,
		key,
		token: params.get( 'token' ) || '',
		paymentIntent: params.get( 'payment_intent' ) || '',
	};
	// Remove the payment parameters so a reload does not repeat the check.
	[
		'pointlybooking_payment',
		'booking_id',
		'key',
		'token',
		'PayerID',
		'payment_intent',
		'payment_intent_client_secret',
		'redirect_status',
	].forEach( ( name ) => params.delete( name ) );
	const query = params.toString();
	window.history.replaceState(
		null,
		'',
		window.location.pathname +
			( query ? `?${ query }` : '' ) +
			window.location.hash
	);
	return info;
}

function mount( node, config, options, paymentReturn, hideButton = false ) {
	node.dataset.pbkMounted = '1';
	node.innerHTML = '';
	createRoot( node ).render(
		<BookingWidget
			config={ config }
			options={ options }
			paymentReturn={ paymentReturn }
			hideButton={ hideButton }
			registerOpener={
				options.display === 'inline'
					? undefined
					: ( open ) => openers.push( open )
			}
		/>
	);
}

function boot() {
	const config = window.pointlybooking_FRONT || {};
	let paymentReturn = readPaymentReturn();
	const nodes = Array.from(
		document.querySelectorAll( '[data-pbk-widget="booking"]' )
	).filter( ( node ) => ! node.dataset.pbkMounted );

	nodes.forEach( ( node ) => {
		let options = {};
		try {
			options = JSON.parse( node.dataset.pbkConfig || '{}' );
		} catch ( e ) {
			options = {};
		}
		mount( node, config, options, paymentReturn );
		paymentReturn = null;
	} );

	// Returned from a payment page that has no booking form: show the result in a modal.
	if ( paymentReturn ) {
		const node = document.createElement( 'div' );
		node.className = 'pbk-root pbk-booking pbk-booking--return';
		document.body.appendChild( node );
		mount( node, config, { display: 'button' }, paymentReturn, true );
	}
}

// 2.x triggers: any [data-bp-open="wizard"], .bp-open-wizard or [data-pbk-open] element opens the first booking form.
document.addEventListener( 'click', ( event ) => {
	const trigger =
		event.target.closest &&
		event.target.closest(
			'[data-bp-open="wizard"], .bp-open-wizard, [data-pbk-open]'
		);
	if ( ! trigger || trigger.closest( '.pbk-root' ) || ! openers.length ) {
		return;
	}
	event.preventDefault();
	openers[ 0 ]();
} );

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', boot );
} else {
	boot();
}
