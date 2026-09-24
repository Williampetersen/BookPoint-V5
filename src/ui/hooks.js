/**
 * Small accessibility and state hooks shared by the components.
 */
import {
	useCallback,
	useEffect,
	useLayoutEffect,
	useRef,
	useState,
} from '@wordpress/element';

let idCounter = 0;

/**
 * Stable unique id (React 18's useId is not exported by every wp-element version we support).
 *
 * @param {string} prefix Prefix.
 * @return {string} Id.
 */
export function useUniqueId( prefix = 'pbk' ) {
	const ref = useRef( null );
	if ( ref.current === null ) {
		idCounter += 1;
		ref.current = `${ prefix }-${ idCounter }`;
	}
	return ref.current;
}

/**
 * Controlled/uncontrolled value.
 *
 * @param {*}        value        Controlled value (undefined = uncontrolled).
 * @param {*}        defaultValue Initial uncontrolled value.
 * @param {Function} onChange     Change callback.
 * @return {Array} [ value, setValue ].
 */
export function useControllable( value, defaultValue, onChange ) {
	const [ inner, setInner ] = useState( defaultValue );
	const controlled = value !== undefined;
	const current = controlled ? value : inner;
	const set = useCallback(
		( next ) => {
			if ( ! controlled ) {
				setInner( next );
			}
			if ( onChange ) {
				onChange( next );
			}
		},
		[ controlled, onChange ]
	);
	return [ current, set ];
}

/**
 * Media query match (e.g. "(max-width: 599px)").
 *
 * @param {string} query Query.
 * @return {boolean} Matches.
 */
export function useMediaQuery( query ) {
	const get = () =>
		typeof window !== 'undefined' &&
		!! window.matchMedia &&
		window.matchMedia( query ).matches;
	const [ matches, setMatches ] = useState( get );
	useEffect( () => {
		if ( ! window.matchMedia ) {
			return undefined;
		}
		const list = window.matchMedia( query );
		const update = () => setMatches( list.matches );
		update();
		if ( list.addEventListener ) {
			list.addEventListener( 'change', update );
			return () => list.removeEventListener( 'change', update );
		}
		list.addListener( update );
		return () => list.removeListener( update );
	}, [ query ] );
	return matches;
}

export const useIsMobile = () => useMediaQuery( '(max-width: 599px)' );
export const usePrefersReducedMotion = () =>
	useMediaQuery( '(prefers-reduced-motion: reduce)' );

// Body scroll lock shared by every open overlay (reference counted).
let lockCount = 0;
let savedOverflow = '';
let savedPadding = '';

export function useScrollLock( active ) {
	useLayoutEffect( () => {
		if ( ! active ) {
			return undefined;
		}
		const body = document.body;
		if ( lockCount === 0 ) {
			const scrollbar =
				window.innerWidth - document.documentElement.clientWidth;
			savedOverflow = body.style.overflow;
			savedPadding = body.style.paddingInlineEnd;
			body.style.overflow = 'hidden';
			if ( scrollbar > 0 ) {
				body.style.paddingInlineEnd = `${ scrollbar }px`;
			}
		}
		lockCount += 1;
		return () => {
			lockCount -= 1;
			if ( lockCount === 0 ) {
				body.style.overflow = savedOverflow;
				body.style.paddingInlineEnd = savedPadding;
			}
		};
	}, [ active ] );
}

const FOCUSABLE =
	'a[href], area[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), iframe, [tabindex]:not([tabindex="-1"]), [contenteditable="true"]';

export function getFocusable( container ) {
	if ( ! container ) {
		return [];
	}
	return Array.from( container.querySelectorAll( FOCUSABLE ) ).filter(
		( el ) =>
			! el.hasAttribute( 'inert' ) &&
			( el.offsetWidth > 0 ||
				el.offsetHeight > 0 ||
				el === el.ownerDocument.activeElement )
	);
}

// Stack of open dialogs so Escape and focus trapping only affect the top one.
const dialogStack = [];

/**
 * Traps focus inside a dialog, restores focus on close and handles Escape.
 *
 * @param {Object}   ref          Container ref.
 * @param {boolean}  active       Whether the dialog is open.
 * @param {Object}   options      Options.
 * @param {Function} options.onEscape      Escape handler.
 * @param {Object}   options.initialFocus Ref of the element to focus first.
 */
export function useFocusTrap( ref, active, { onEscape, initialFocus } = {} ) {
	const escapeRef = useRef( onEscape );
	escapeRef.current = onEscape;

	useEffect( () => {
		if ( ! active || ! ref.current ) {
			return undefined;
		}
		const node = ref.current;
		const doc = node.ownerDocument;
		const previous = doc.activeElement;
		dialogStack.push( node );

		const focusFirst = () => {
			const target =
				( initialFocus && initialFocus.current ) ||
				getFocusable( node )[ 0 ] ||
				node;
			target.focus( { preventScroll: true } );
		};
		const frame = window.requestAnimationFrame( focusFirst );

		const onKeyDown = ( event ) => {
			if ( dialogStack[ dialogStack.length - 1 ] !== node ) {
				return;
			}
			if ( event.key === 'Escape' ) {
				if ( escapeRef.current ) {
					event.stopPropagation();
					escapeRef.current( event );
				}
				return;
			}
			if ( event.key !== 'Tab' ) {
				return;
			}
			const items = getFocusable( node );
			if ( ! items.length ) {
				event.preventDefault();
				node.focus();
				return;
			}
			const first = items[ 0 ];
			const last = items[ items.length - 1 ];
			if (
				event.shiftKey &&
				( doc.activeElement === first || doc.activeElement === node )
			) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && doc.activeElement === last ) {
				event.preventDefault();
				first.focus();
			}
		};
		const onFocusIn = ( event ) => {
			if (
				dialogStack[ dialogStack.length - 1 ] === node &&
				! node.contains( event.target )
			) {
				focusFirst();
			}
		};

		document.addEventListener( 'keydown', onKeyDown, true );
		document.addEventListener( 'focusin', onFocusIn );
		return () => {
			window.cancelAnimationFrame( frame );
			document.removeEventListener( 'keydown', onKeyDown, true );
			document.removeEventListener( 'focusin', onFocusIn );
			const index = dialogStack.indexOf( node );
			if ( index !== -1 ) {
				dialogStack.splice( index, 1 );
			}
			if (
				previous &&
				typeof previous.focus === 'function' &&
				document.contains( previous )
			) {
				previous.focus( { preventScroll: true } );
			}
		};
	}, [ active, ref, initialFocus ] );
}

/**
 * Calls handler on pointer down outside all given refs.
 *
 * @param {Array}    refs    Refs.
 * @param {Function} handler Handler.
 * @param {boolean}  active  Active.
 */
export function useOutsideClick( refs, handler, active = true ) {
	const handlerRef = useRef( handler );
	handlerRef.current = handler;
	useEffect( () => {
		if ( ! active ) {
			return undefined;
		}
		const onDown = ( event ) => {
			const inside = refs.some(
				( ref ) => ref.current && ref.current.contains( event.target )
			);
			if ( ! inside ) {
				handlerRef.current( event );
			}
		};
		document.addEventListener( 'pointerdown', onDown );
		return () => document.removeEventListener( 'pointerdown', onDown );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ active ] );
}

/**
 * Keeps an element mounted during its exit animation.
 *
 * @param {boolean} open     Open state.
 * @param {number}  duration Exit duration in ms.
 * @return {Array} [ mounted, visible ].
 */
export function usePresence( open, duration = 200 ) {
	const [ mounted, setMounted ] = useState( open );
	const [ visible, setVisible ] = useState( open );
	useEffect( () => {
		if ( open ) {
			setMounted( true );
			const frame = window.requestAnimationFrame( () =>
				setVisible( true )
			);
			return () => window.cancelAnimationFrame( frame );
		}
		setVisible( false );
		const timer = window.setTimeout( () => setMounted( false ), duration );
		return () => window.clearTimeout( timer );
	}, [ open, duration ] );
	return [ mounted, visible ];
}

/**
 * Debounced value.
 *
 * @param {*}      value Value.
 * @param {number} delay Delay in ms.
 * @return {*} Debounced value.
 */
export function useDebounced( value, delay = 250 ) {
	const [ current, setCurrent ] = useState( value );
	useEffect( () => {
		const timer = window.setTimeout( () => setCurrent( value ), delay );
		return () => window.clearTimeout( timer );
	}, [ value, delay ] );
	return current;
}
