/**
 * Admin REST client and data hooks.
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { createClient } from '../shared/api';

let instance = null;

/**
 * Shared client bound to window.pointlybooking_ADMIN.
 *
 * @return {Object} Client.
 */
export function client() {
	if ( ! instance ) {
		const config = window.pointlybooking_ADMIN || {};
		instance = createClient( {
			restUrl: config.restUrl || '/wp-json/pointly-booking/v1/',
			nonce: config.nonce || '',
			fallbackMessage: __( 'Something went wrong. Please try again.', 'pointly-booking' ),
		} );
	}
	return instance;
}

/**
 * GET a resource, refetching when the path or params change.
 *
 * @param {string|null} path   Route below the namespace, or null/false to skip.
 * @param {Object}      params Query params.
 * @return {{data:*, loading:boolean, error:string, reload:Function, setData:Function}} State.
 */
export function useResource( path, params = {} ) {
	const paramsKey = JSON.stringify( params );
	const [ nonce, setNonce ] = useState( 0 );
	const [ state, setState ] = useState( { data: null, loading: !! path, error: '' } );

	useEffect( () => {
		if ( ! path ) {
			setState( { data: null, loading: false, error: '' } );
			return undefined;
		}
		let alive = true;
		setState( ( s ) => ( { ...s, loading: true, error: '' } ) );
		client()
			.get( path, JSON.parse( paramsKey ) )
			.then( ( data ) => alive && setState( { data, loading: false, error: '' } ) )
			.catch( ( error ) => alive && setState( { data: null, loading: false, error: error.message } ) );
		return () => {
			alive = false;
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ path, paramsKey, nonce ] );

	const reload = useCallback( () => setNonce( ( n ) => n + 1 ), [] );
	const setData = useCallback( ( data ) => setState( ( s ) => ( { ...s, data } ) ), [] );
	return { ...state, reload, setData };
}

/**
 * Debounced text value (for search boxes bound to a list query).
 *
 * @param {string} value Value.
 * @param {number} delay Delay in ms.
 * @return {string} Debounced value.
 */
export function useDebouncedValue( value, delay = 300 ) {
	const [ debounced, setDebounced ] = useState( value );
	useEffect( () => {
		const timer = window.setTimeout( () => setDebounced( value ), delay );
		return () => window.clearTimeout( timer );
	}, [ value, delay ] );
	return debounced;
}

/**
 * Tracks an in-flight mutation (create/update/delete) with a loading flag.
 *
 * @return {{run:Function, busy:boolean}} Runner.
 */
export function useAction() {
	const [ busy, setBusy ] = useState( false );
	const mounted = useRef( true );
	useEffect(
		() => () => {
			mounted.current = false;
		},
		[]
	);
	const run = useCallback( ( promise ) => {
		setBusy( true );
		return promise.finally( () => mounted.current && setBusy( false ) );
	}, [] );
	return { run, busy };
}

/**
 * Extracts a field-level error message, if the API returned one for `field`.
 *
 * @param {Object} error Error from the API client.
 * @param {string} field Field name.
 * @return {string} Message or ''.
 */
export function fieldError( error, field ) {
	return error && error.field === field ? error.message : '';
}
