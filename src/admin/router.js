/**
 * Lightweight client-side router over admin.php?page=&tab=&view=&id= (no reload).
 *
 * WordPress's own admin menu still points at real admin.php URLs (Admin\Menu), so those
 * remain valid bookmarks and no-JS fallbacks; this only intercepts in-app navigation.
 */
import { createContext, useCallback, useContext, useEffect, useMemo, useState } from '@wordpress/element';

const RouteContext = createContext( null );

function parseSearch() {
	const params = new URLSearchParams( window.location.search );
	const out = {};
	params.forEach( ( value, key ) => {
		out[ key ] = value;
	} );
	return out;
}

/**
 * Route key (config.pages key) for the current `page` query param.
 *
 * @param {Object} pages   config.pages.
 * @param {string} slug    Current `page` query value.
 * @param {string} fallback Fallback route.
 * @return {string} Route key.
 */
function routeForSlug( pages, slug, fallback ) {
	const found = Object.keys( pages ).find( ( key ) => pages[ key ].slug === slug );
	return found || fallback;
}

export function RouteProvider( { config, initialRoute, children } ) {
	const pages = useMemo( () => config.pages || {}, [ config.pages ] );
	const [ query, setQuery ] = useState( parseSearch );
	const route = routeForSlug( pages, query.page, initialRoute );

	useEffect( () => {
		const onPop = () => setQuery( parseSearch() );
		window.addEventListener( 'popstate', onPop );
		return () => window.removeEventListener( 'popstate', onPop );
	}, [] );

	/**
	 * Navigates to a screen (config.pages key) with optional extra query params.
	 * Pass `null` for a param to remove it.
	 *
	 * @param {string} nextRoute Route key.
	 * @param {Object} params    Extra query params (e.g. { tab, view, id }).
	 * @param {Object} options   { replace: boolean }.
	 */
	const navigate = useCallback(
		( nextRoute, params = {}, options = {} ) => {
			const page = pages[ nextRoute ] ? pages[ nextRoute ].slug : nextRoute;
			const next = { page };
			Object.entries( params ).forEach( ( [ key, value ] ) => {
				if ( value !== null && value !== undefined && value !== '' ) {
					next[ key ] = String( value );
				}
			} );
			const search = new URLSearchParams( next ).toString();
			const url = `${ window.location.pathname }?${ search }`;
			if ( options.replace ) {
				window.history.replaceState( null, '', url );
			} else {
				window.history.pushState( null, '', url );
			}
			setQuery( next );
			if ( ! options.keepScroll ) {
				window.scrollTo( { top: 0, behavior: 'instant' in window ? 'instant' : 'auto' } );
			}
		},
		[ pages ]
	);

	/**
	 * Updates params on the current route without changing screens.
	 *
	 * @param {Object} params  Patch ({ key: null } removes it).
	 * @param {Object} options { replace }.
	 */
	const setParams = useCallback(
		( params, options = {} ) => {
			const next = { ...query, ...params };
			Object.keys( next ).forEach( ( key ) => {
				if ( next[ key ] === null || next[ key ] === undefined || next[ key ] === '' ) {
					delete next[ key ];
				}
			} );
			const search = new URLSearchParams( next ).toString();
			const url = `${ window.location.pathname }?${ search }`;
			if ( options.replace ) {
				window.history.replaceState( null, '', url );
			} else {
				window.history.pushState( null, '', url );
			}
			setQuery( next );
		},
		[ query ]
	);

	const value = useMemo( () => ( { route, query, navigate, setParams, pages } ), [ route, query, navigate, setParams, pages ] );

	return <RouteContext.Provider value={ value }>{ children }</RouteContext.Provider>;
}

export const useRoute = () => useContext( RouteContext );

/**
 * Builds a plain href for a route (for real <a> tags; combine with a navigate() onClick).
 *
 * @param {Object} pages  config.pages.
 * @param {string} route  Route key.
 * @param {Object} params Extra params.
 * @return {string} Href.
 */
export function routeHref( pages, route, params = {} ) {
	const page = pages[ route ] ? pages[ route ].slug : route;
	const search = new URLSearchParams( { page, ...params } ).toString();
	return `admin.php?${ search }`;
}
