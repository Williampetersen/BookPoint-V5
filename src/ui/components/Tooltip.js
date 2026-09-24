/**
 * Tooltip: short supplementary text on hover and keyboard focus.
 */
import {
	useEffect,
	useLayoutEffect,
	useRef,
	useState,
	cloneElement,
	forwardRef,
} from '@wordpress/element';
import { Portal } from '../theme/ThemeRoot';
import { useUniqueId } from '../hooks';
import { computePosition } from './Popover';

export const Tooltip = forwardRef( function Tooltip(
	{ text, children, placement = 'top', delay = 300 },
	forwardedRef
) {
	const [ open, setOpen ] = useState( false );
	const [ position, setPosition ] = useState( null );
	const anchorRef = useRef( null );
	const tipRef = useRef( null );
	const timer = useRef( 0 );
	const id = useUniqueId( 'pbk-tip' );

	const show = ( immediate ) => {
		window.clearTimeout( timer.current );
		timer.current = window.setTimeout(
			() => setOpen( true ),
			immediate ? 0 : delay
		);
	};
	const hide = () => {
		window.clearTimeout( timer.current );
		setOpen( false );
	};

	useLayoutEffect( () => {
		if ( open && anchorRef.current && tipRef.current ) {
			setPosition(
				computePosition(
					anchorRef.current,
					tipRef.current,
					placement,
					8
				)
			);
		} else {
			setPosition( null );
		}
	}, [ open, placement ] );

	useEffect( () => {
		if ( ! open ) {
			return undefined;
		}
		const onKey = ( event ) => event.key === 'Escape' && hide();
		document.addEventListener( 'keydown', onKey );
		return () => document.removeEventListener( 'keydown', onKey );
	}, [ open ] );

	useEffect( () => () => window.clearTimeout( timer.current ), [] );

	if ( ! text ) {
		return children;
	}

	const setRefs = ( node ) => {
		anchorRef.current = node;
		const childRef = children.ref;
		[ childRef, forwardedRef ].forEach( ( ref ) => {
			if ( typeof ref === 'function' ) {
				ref( node );
			} else if ( ref ) {
				ref.current = node;
			}
		} );
	};

	const call = ( name, event ) =>
		typeof children.props[ name ] === 'function' &&
		children.props[ name ]( event );

	return (
		<>
			{ cloneElement( children, {
				ref: setRefs,
				'aria-describedby': open
					? id
					: children.props[ 'aria-describedby' ],
				onMouseEnter: ( event ) => {
					call( 'onMouseEnter', event );
					show( false );
				},
				onMouseLeave: ( event ) => {
					call( 'onMouseLeave', event );
					hide();
				},
				onFocus: ( event ) => {
					call( 'onFocus', event );
					if ( event.target.matches( ':focus-visible' ) ) {
						show( true );
					}
				},
				onBlur: ( event ) => {
					call( 'onBlur', event );
					hide();
				},
			} ) }
			{ open && (
				<Portal>
					<div
						ref={ tipRef }
						id={ id }
						role="tooltip"
						className={ `pbk-tooltip ${
							position ? 'is-positioned' : ''
						}` }
						style={
							position
								? { top: position.top, left: position.left }
								: { top: -9999, left: -9999 }
						}
					>
						{ text }
					</div>
				</Portal>
			) }
		</>
	);
} );
