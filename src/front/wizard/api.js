/**
 * Wizard data layer: one shared client, a shared bootstrap promise and cached availability.
 */
import { __ } from '@wordpress/i18n';
import { createClient } from '../../shared/api';

let client = null;
let bootstrapPromise = null;
const cache = new Map();

export function getClient() {
	if ( ! client ) {
		const config = window.pointlybooking_FRONT || {};
		client = createClient( {
			restUrl: config.restUrl || '/wp-json/pointly-booking/v1/',
			nonce: config.nonce || '',
			fallbackMessage: __(
				'We could not reach the booking service. Please check your connection and try again.',
				'pointly-booking'
			),
		} );
	}
	return client;
}

/**
 * Catalogue, design, fields and settings (shared by every widget on the page).
 *
 * @return {Promise<Object>} Bootstrap data.
 */
export function loadBootstrap() {
	if ( ! bootstrapPromise ) {
		bootstrapPromise = getClient()
			.get( 'wizard/bootstrap' )
			.catch( ( error ) => {
				bootstrapPromise = null;
				throw error;
			} );
	}
	return bootstrapPromise;
}

function cached( key, loader ) {
	if ( ! cache.has( key ) ) {
		const promise = loader().catch( ( error ) => {
			cache.delete( key );
			throw error;
		} );
		cache.set( key, promise );
	}
	return cache.get( key );
}

const scopeKey = ( { serviceId, agentId, locationId } ) =>
	`${ serviceId }|${ agentId || 0 }|${ locationId || 0 }`;

/**
 * Month overview: { month, days: { date: slotCount }, first_available, window_end }.
 *
 * @param {Object} scope serviceId, agentId, locationId.
 * @param {string} month Y-m ('' = month of the first free day).
 * @return {Promise<Object>} Month.
 */
export function loadMonth( scope, month = '' ) {
	return cached( `m:${ scopeKey( scope ) }:${ month }`, () =>
		getClient().get( 'wizard/availability/month', {
			service_id: scope.serviceId,
			agent_id: scope.agentId || 0,
			location_id: scope.locationId || 0,
			month,
		} )
	);
}

/**
 * Slots of a day: { date, slots: [ { start, end, available } ], timezone }.
 *
 * @param {Object} scope serviceId, agentId, locationId.
 * @param {string} date  Y-m-d.
 * @return {Promise<Object>} Day.
 */
export function loadDay( scope, date ) {
	return cached( `d:${ scopeKey( scope ) }:${ date }`, () =>
		getClient().get( 'wizard/availability/day', {
			service_id: scope.serviceId,
			agent_id: scope.agentId || 0,
			location_id: scope.locationId || 0,
			date,
		} )
	);
}

/**
 * Warms the cache for nearby days (silently).
 *
 * @param {Object} scope Scope.
 * @param {Array}  dates Dates.
 */
export function prefetchDays( scope, dates ) {
	dates
		.filter( Boolean )
		.forEach( ( date ) => loadDay( scope, date ).catch( () => {} ) );
}

/** Drops cached availability (after a failed booking because the time was taken). */
export function clearAvailability() {
	Array.from( cache.keys() ).forEach( ( key ) => cache.delete( key ) );
}

export function quote( serviceId, extras, promoCode ) {
	return getClient().post( 'wizard/quote', {
		service_id: serviceId,
		extras,
		promo_code: promoCode || '',
	} );
}

export function createBooking( payload ) {
	return getClient().post( 'wizard/bookings', payload );
}

export function bookingStatus( id, key ) {
	return getClient().get( `wizard/bookings/${ id }`, { key } );
}

export function startPayment( id, key, returnUrl ) {
	return getClient().post( `wizard/bookings/${ id }/pay`, {
		key,
		return_url: returnUrl,
	} );
}

export function confirmStripe( id, key, paymentIntentId ) {
	return getClient().post( `wizard/bookings/${ id }/confirm-payment`, {
		key,
		payment_intent_id: paymentIntentId,
	} );
}

export function capturePaypal( id, key, orderId ) {
	return getClient().post( `wizard/bookings/${ id }/paypal-capture`, {
		key,
		order_id: orderId,
	} );
}

export function cancelPayment( id, key ) {
	return getClient().post( `wizard/bookings/${ id }/cancel-payment`, {
		key,
	} );
}
