/**
 * Popover (anchored floating panel), Dropdown menu.
 */
import {
	useCallback,
	useEffect,
	useLayoutEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { Portal } from '../theme/ThemeRoot';
import { useOutsideClick, useUniqueId } from '../hooks';
import { Icon } from '../icons';
import './popover.css';

/**
 * Computes a fixed position next to an anchor, flipping to stay inside the viewport.
 *
 * @param {Element} anchor    Anchor element.
 * @param {Element} floating  Floating element.
 * @param {string}  placement bottom-start | bottom-end | top | bottom.
 * @param {number}  offset    Gap in px.
 * @return {Object} { top, left, placement }.
 */
export function computePosition(
	anchor,
	floating,
	placement = 'bottom-start',
	offset = 6
) {
	const a = anchor.getBoundingClientRect();
	const f = floating.getBoundingClientRect();
	const vw = document.documentElement.clientWidth;
	const vh = document.documentElement.clientHeight;
	const rtl =
		document.documentElement.dir === 'rtl' ||
		getComputedStyle( anchor ).direction === 'rtl';
	let [ side, align = 'center' ] = placement.split( '-' );

	if (
		side === 'bottom' &&
		a.bottom + offset + f.height > vh &&
		a.top - offset - f.height > 0
	) {
		side = 'top';
	} else if (
		side === 'top' &&
		a.top - offset - f.height < 0 &&
		a.bottom + offset + f.height < vh
	) {
		side = 'bottom';
	}
	const top =
		side === 'bottom' ? a.bottom + offset : a.top - offset - f.height;

	if ( rtl && align !== 'center' ) {
		align = align === 'start' ? 'end' : 'start';
	}
	let left;
	if ( align === 'start' ) {
		left = a.left;
	} else if ( align === 'end' ) {
		left = a.right - f.width;
	} else {
		left = a.left + a.width / 2 - f.width / 2;
	}
	left = Math.max( 8, Math.min( left, vw - f.width - 8 ) );
	return { top: Math.max( 8, top ), left, side };
}

export function Popover( {
	open,
	anchorRef,
	onClose,
	placement = 'bottom-start',
	className = '',
	children,
	role = 'dialog',
	label,
	matchWidth = false,
	...rest
} ) {
	const ref = useRef( null );
	const [ position, setPosition ] = useState( null );

	const update = useCallback( () => {
		if ( ! ref.current || ! anchorRef.current ) {
			return;
		}
		if ( matchWidth ) {
			ref.current.style.minWidth = `${
				anchorRef.current.getBoundingClientRect().width
			}px`;
		}
		setPosition(
			computePosition( anchorRef.current, ref.current, placement )
		);
	}, [ anchorRef, placement, matchWidth ] );

	useLayoutEffect( () => {
		if ( open ) {
			update();
		} else {
			setPosition( null );
		}
	}, [ open, update ] );

	useEffect( () => {
		if ( ! open ) {
			return undefined;
		}
		window.addEventListener( 'resize', update );
		window.addEventListener( 'scroll', update, true );
		const onKey = ( event ) => {
			if ( event.key === 'Escape' ) {
				event.stopPropagation();
				onClose();
				if ( anchorRef.current ) {
					anchorRef.current.focus();
				}
			}
		};
		document.addEventListener( 'keydown', onKey );
		return () => {
			window.removeEventListener( 'resize', update );
			window.removeEventListener( 'scroll', update, true );
			document.removeEventListener( 'keydown', onKey );
		};
	}, [ open, update, onClose, anchorRef ] );

	useOutsideClick( [ ref, anchorRef ], onClose, open );

	if ( ! open ) {
		return null;
	}
	return (
		<Portal>
			<div
				ref={ ref }
				role={ role }
				aria-label={ label }
				className={ `pbk-popover ${ position ? 'is-positioned' : '' } ${
					position ? `is-${ position.side }` : ''
				} ${ className }`.trim() }
				style={
					position
						? { top: position.top, left: position.left }
						: { top: -9999, left: -9999 }
				}
				{ ...rest }
			>
				{ children }
			</div>
		</Portal>
	);
}

/**
 * Dropdown menu.
 *
 * @param {Object}   props
 * @param {Function} props.trigger  ( triggerProps ) => element; spread triggerProps onto a button.
 * @param {Array}    props.items    [ { label, icon, onClick, href, danger, disabled, divider } ].
 * @param {string}   props.label    Accessible menu label.
 * @param {string}   props.placement Placement.
 * @return {*} Dropdown.
 */
export function Dropdown( {
	trigger,
	items = [],
	label,
	placement = 'bottom-end',
} ) {
	const [ open, setOpen ] = useState( false );
	const anchorRef = useRef( null );
	const menuRef = useRef( null );
	const id = useUniqueId( 'pbk-menu' );
	const typed = useRef( { text: '', timer: 0 } );

	const enabled = () =>
		menuRef.current
			? Array.from(
					menuRef.current.querySelectorAll(
						'[role="menuitem"]:not([aria-disabled="true"])'
					)
			  )
			: [];

	const close = useCallback( () => setOpen( false ), [] );

	useEffect( () => {
		if ( open ) {
			const frame = window.requestAnimationFrame( () => {
				const first = enabled()[ 0 ];
				if ( first ) {
					first.focus();
				}
			} );
			return () => window.cancelAnimationFrame( frame );
		}
		return undefined;
	}, [ open ] );

	const onMenuKeyDown = ( event ) => {
		const list = enabled();
		const index = list.indexOf(
			event.currentTarget.ownerDocument.activeElement
		);
		if ( event.key === 'ArrowDown' ) {
			event.preventDefault();
			list[ ( index + 1 ) % list.length ]?.focus();
		} else if ( event.key === 'ArrowUp' ) {
			event.preventDefault();
			list[ ( index - 1 + list.length ) % list.length ]?.focus();
		} else if ( event.key === 'Home' ) {
			event.preventDefault();
			list[ 0 ]?.focus();
		} else if ( event.key === 'End' ) {
			event.preventDefault();
			list[ list.length - 1 ]?.focus();
		} else if ( event.key === 'Tab' ) {
			close();
		} else if ( event.key.length === 1 && /\S/.test( event.key ) ) {
			window.clearTimeout( typed.current.timer );
			typed.current.text += event.key.toLowerCase();
			typed.current.timer = window.setTimeout(
				() => ( typed.current.text = '' ),
				500
			);
			const match = list.find( ( el ) =>
				el.textContent
					.trim()
					.toLowerCase()
					.startsWith( typed.current.text )
			);
			if ( match ) {
				match.focus();
			}
		}
	};

	const triggerProps = {
		ref: anchorRef,
		'aria-haspopup': 'menu',
		'aria-expanded': open,
		'aria-controls': open ? id : undefined,
		onClick: () => setOpen( ( value ) => ! value ),
		onKeyDown: ( event ) => {
			if ( event.key === 'ArrowDown' ) {
				event.preventDefault();
				setOpen( true );
			}
		},
	};

	return (
		<>
			{ trigger( triggerProps ) }
			<Popover
				open={ open }
				anchorRef={ anchorRef }
				onClose={ close }
				placement={ placement }
				role="presentation"
				className="pbk-menu-popover"
			>
				<div
					ref={ menuRef }
					id={ id }
					role="menu"
					aria-label={ label }
					className="pbk-menu"
					onKeyDown={ onMenuKeyDown }
					tabIndex={ -1 }
				>
					{ items.map( ( item, index ) => {
						if ( item.divider ) {
							return (
								<div
									key={ `d${ index }` }
									className="pbk-menu__divider"
									role="separator"
								/>
							);
						}
						const className = `pbk-menu__item ${
							item.danger ? 'is-danger' : ''
						}`.trim();
						const inner = (
							<>
								{ item.icon && (
									<Icon name={ item.icon } size={ 18 } />
								) }
								<span>{ item.label }</span>
							</>
						);
						const activate = ( event ) => {
							if ( item.disabled ) {
								event.preventDefault();
								return;
							}
							close();
							if ( item.onClick ) {
								item.onClick( event );
							}
							if ( ! item.href && anchorRef.current ) {
								anchorRef.current.focus();
							}
						};
						return item.href ? (
							<a
								key={ index }
								role="menuitem"
								tabIndex={ -1 }
								href={ item.href }
								className={ className }
								aria-disabled={ item.disabled || undefined }
								onClick={ activate }
								target={ item.target }
								rel={
									item.target
										? 'noopener noreferrer'
										: undefined
								}
							>
								{ inner }
							</a>
						) : (
							<button
								key={ index }
								type="button"
								role="menuitem"
								tabIndex={ -1 }
								className={ className }
								aria-disabled={ item.disabled || undefined }
								onClick={ activate }
							>
								{ inner }
							</button>
						);
					} ) }
				</div>
			</Popover>
		</>
	);
}
