/**
 * Minimal REST client shared by the admin, wizard and manage apps.
 *
 * - Sends the wp_rest nonce when one is configured (logged-in users).
 * - Retries once without the nonce when a cached page carried an expired one.
 * - Throws ApiError with the server message, code, HTTP status and field (if any).
 */

export class ApiError extends Error {
	constructor(
		message,
		{ code = 'error', status = 0, field = '', data = null } = {}
	) {
		super( message );
		this.name = 'ApiError';
		this.code = code;
		this.status = status;
		this.field = field;
		this.data = data;
	}
}

const DEFAULT_ERROR = 'Something went wrong. Please try again.';

function buildUrl( base, path, query ) {
	const url = new URL(
		base.replace( /\/+$/, '' ) + '/' + String( path ).replace( /^\/+/, '' ),
		window.location.href
	);
	if ( query ) {
		Object.entries( query ).forEach( ( [ key, value ] ) => {
			if ( value === undefined || value === null || value === '' ) {
				return;
			}
			if ( Array.isArray( value ) ) {
				value.forEach( ( item ) =>
					url.searchParams.append( `${ key }[]`, item )
				);
			} else {
				url.searchParams.set( key, value );
			}
		} );
	}
	return url.toString();
}

/**
 * Creates a client bound to a REST base URL.
 *
 * @param {Object}   config
 * @param {string}   config.restUrl   Base URL, e.g. https://site/wp-json/pointly-booking/v1/
 * @param {string}   [config.nonce]   wp_rest nonce.
 * @param {string}   [config.fallbackMessage] Localised generic error.
 * @return {{get: Function, post: Function, put: Function, patch: Function, del: Function, request: Function, url: Function}} Client.
 */
export function createClient( {
	restUrl,
	nonce = '',
	fallbackMessage = DEFAULT_ERROR,
} ) {
	let currentNonce = nonce;

	async function request(
		method,
		path,
		{ query, body, signal, raw = false } = {}
	) {
		const send = async ( withNonce ) => {
			const headers = { Accept: 'application/json' };
			if ( body !== undefined ) {
				headers[ 'Content-Type' ] = 'application/json';
			}
			if ( withNonce && currentNonce ) {
				headers[ 'X-WP-Nonce' ] = currentNonce;
			}
			return window.fetch( buildUrl( restUrl, path, query ), {
				method,
				headers,
				body: body !== undefined ? JSON.stringify( body ) : undefined,
				credentials: 'same-origin',
				signal,
			} );
		};

		let response;
		try {
			response = await send( true );
			if ( response.status === 403 && currentNonce ) {
				const peek = await response
					.clone()
					.json()
					.catch( () => null );
				if ( peek && peek.code === 'rest_cookie_invalid_nonce' ) {
					currentNonce = '';
					response = await send( false );
				}
			}
		} catch ( error ) {
			if ( error && error.name === 'AbortError' ) {
				throw error;
			}
			throw new ApiError( fallbackMessage, { code: 'network_error' } );
		}

		const nextNonce = response.headers.get( 'X-WP-Nonce' );
		if ( nextNonce ) {
			currentNonce = nextNonce;
		}

		if ( raw ) {
			return response;
		}

		const json = await response.json().catch( () => null );
		if ( ! response.ok ) {
			const data = json && json.data ? json.data : {};
			throw new ApiError( ( json && json.message ) || fallbackMessage, {
				code: ( json && json.code ) || 'http_' + response.status,
				status: response.status,
				field: data.field || '',
				data,
			} );
		}
		// 3.0 envelope: { status: 'success', data }.
		if (
			json &&
			typeof json === 'object' &&
			'data' in json &&
			json.status === 'success'
		) {
			return json.data;
		}
		return json;
	}

	return {
		request,
		get: ( path, query, options = {} ) =>
			request( 'GET', path, { ...options, query } ),
		post: ( path, body = {}, options = {} ) =>
			request( 'POST', path, { ...options, body } ),
		put: ( path, body = {}, options = {} ) =>
			request( 'PUT', path, { ...options, body } ),
		patch: ( path, body = {}, options = {} ) =>
			request( 'PATCH', path, { ...options, body } ),
		del: ( path, options = {} ) => request( 'DELETE', path, options ),
		url: ( path, query ) => buildUrl( restUrl, path, query ),
	};
}
