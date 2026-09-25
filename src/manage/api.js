/**
 * REST client for the manage page and portal.
 */
import { __ } from '@wordpress/i18n';
import { createClient } from '../shared/api';

let instance = null;

export function client() {
	if ( ! instance ) {
		const config = window.pointlybooking_MANAGE || {};
		instance = createClient( {
			restUrl: config.restUrl || '/wp-json/pointly-booking/v1/',
			nonce: config.nonce || '',
			fallbackMessage: __(
				'We could not reach the booking service. Please check your connection and try again.',
				'pointly-booking'
			),
		} );
	}
	return instance;
}
