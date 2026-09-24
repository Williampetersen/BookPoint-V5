/**
 * Theme root: applies brand tokens + light/dark theme and provides a matching portal container
 * so modals, drawers and toasts rendered into <body> keep the .pbk-root scope and colours.
 */
import {
	createContext,
	useContext,
	useEffect,
	useMemo,
	useRef,
	createPortal,
} from '@wordpress/element';
import { brandTokens, DEFAULT_BRAND } from './brand';
import { useMediaQuery } from '../hooks';
import '../tokens.css';
import '../base.css';

const ThemeContext = createContext( {
	theme: 'light',
	brand: DEFAULT_BRAND,
	getPortal: () => document.body,
	isAdmin: false,
} );

export const useTheme = () => useContext( ThemeContext );

/**
 * Resolves "auto" against the OS preference.
 *
 * @param {string} mode light | dark | auto.
 * @return {string} light | dark.
 */
export function useResolvedTheme( mode ) {
	const prefersDark = useMediaQuery( '(prefers-color-scheme: dark)' );
	if ( mode === 'dark' ) {
		return 'dark';
	}
	if ( mode === 'auto' ) {
		return prefersDark ? 'dark' : 'light';
	}
	return 'light';
}

export function ThemeRoot( {
	as: Tag = 'div',
	brand = DEFAULT_BRAND,
	mode = 'light',
	admin = false,
	className = '',
	style,
	children,
	...rest
} ) {
	const theme = useResolvedTheme( mode );
	const tokens = useMemo( () => brandTokens( brand ), [ brand ] );
	const portalRef = useRef( null );

	const classes = [ 'pbk-root', admin ? 'pbk-admin' : '', className ]
		.filter( Boolean )
		.join( ' ' );

	// Keep the portal container in sync with the theme.
	useEffect( () => {
		const node = portalRef.current;
		if ( ! node ) {
			return;
		}
		node.className = [ 'pbk-root', 'pbk-portal', admin ? 'pbk-admin' : '' ]
			.filter( Boolean )
			.join( ' ' );
		node.setAttribute( 'data-pbk-theme', theme );
		node.setAttribute( 'dir', document.documentElement.dir || 'ltr' );
		Object.entries( tokens ).forEach( ( [ key, value ] ) =>
			node.style.setProperty( key, value )
		);
	} );

	useEffect(
		() => () => {
			if ( portalRef.current && portalRef.current.parentNode ) {
				portalRef.current.parentNode.removeChild( portalRef.current );
			}
		},
		[]
	);

	const value = useMemo(
		() => ( {
			theme,
			brand,
			isAdmin: admin,
			getPortal: () => {
				if ( ! portalRef.current ) {
					const node = document.createElement( 'div' );
					node.className = [
						'pbk-root',
						'pbk-portal',
						admin ? 'pbk-admin' : '',
					]
						.filter( Boolean )
						.join( ' ' );
					node.setAttribute( 'data-pbk-theme', theme );
					Object.entries( tokens ).forEach( ( [ key, val ] ) =>
						node.style.setProperty( key, val )
					);
					document.body.appendChild( node );
					portalRef.current = node;
				}
				return portalRef.current;
			},
		} ),
		[ theme, brand, admin, tokens ]
	);

	return (
		<ThemeContext.Provider value={ value }>
			<Tag
				className={ classes }
				data-pbk-theme={ theme }
				style={ { ...tokens, ...style } }
				{ ...rest }
			>
				{ children }
			</Tag>
		</ThemeContext.Provider>
	);
}

/**
 * Renders children into the theme's portal container.
 *
 * @param {Object} props          Props.
 * @param {*}      props.children Content.
 * @return {*} Portal.
 */
export function Portal( { children } ) {
	const { getPortal } = useTheme();
	// Resolved during render so refs inside the portal exist in the same commit
	// (focus traps and positioning run in effects right after).
	const node = typeof document !== 'undefined' ? getPortal() : null;
	return node ? createPortal( children, node ) : null;
}
