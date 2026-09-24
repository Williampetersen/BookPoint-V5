/**
 * Tabs (automatic activation, arrow keys, RTL aware).
 */
import { useRef } from '@wordpress/element';
import { useControllable, useUniqueId } from '../hooks';
import { Icon } from '../icons';
import './tabs.css';

/**
 * @param {Object}   props
 * @param {Array}    props.tabs     [ { key, label, icon, count, disabled } ].
 * @param {string}   props.value    Active key (controlled).
 * @param {Function} props.onChange ( key ) => void.
 * @param {string}   props.variant  underline | pills.
 * @param {string}   props.label    Accessible name of the tab list.
 * @param {Function} props.children ( activeKey ) => panel content (optional).
 * @return {*} Tabs.
 */
export function Tabs( {
	tabs = [],
	value,
	defaultValue,
	onChange,
	variant = 'underline',
	label,
	children,
	className = '',
	idPrefix,
} ) {
	const [ active, setActive ] = useControllable(
		value,
		defaultValue !== undefined ? defaultValue : tabs[ 0 ] && tabs[ 0 ].key,
		onChange
	);
	const autoId = useUniqueId( 'pbk-tabs' );
	const base = idPrefix || autoId;
	const listRef = useRef( null );

	const onKeyDown = ( event ) => {
		const enabled = tabs.filter( ( tab ) => ! tab.disabled );
		const index = enabled.findIndex( ( tab ) => tab.key === active );
		const rtl = getComputedStyle( event.currentTarget ).direction === 'rtl';
		let next = null;
		if ( event.key === ( rtl ? 'ArrowLeft' : 'ArrowRight' ) ) {
			next = enabled[ ( index + 1 ) % enabled.length ];
		} else if ( event.key === ( rtl ? 'ArrowRight' : 'ArrowLeft' ) ) {
			next = enabled[ ( index - 1 + enabled.length ) % enabled.length ];
		} else if ( event.key === 'Home' ) {
			next = enabled[ 0 ];
		} else if ( event.key === 'End' ) {
			next = enabled[ enabled.length - 1 ];
		}
		if ( next ) {
			event.preventDefault();
			setActive( next.key );
			const button =
				listRef.current &&
				listRef.current.querySelector( `[data-key="${ next.key }"]` );
			if ( button ) {
				button.focus();
			}
		}
	};

	return (
		<div
			className={ `pbk-tabs pbk-tabs--${ variant } ${ className }`.trim() }
		>
			<div
				ref={ listRef }
				className="pbk-tabs__list"
				role="tablist"
				aria-label={ label }
			>
				{ tabs.map( ( tab ) => {
					const selected = tab.key === active;
					return (
						<button
							key={ tab.key }
							type="button"
							role="tab"
							data-key={ tab.key }
							id={ `${ base }-tab-${ tab.key }` }
							aria-selected={ selected }
							aria-controls={
								children
									? `${ base }-panel-${ tab.key }`
									: undefined
							}
							tabIndex={ selected ? 0 : -1 }
							disabled={ tab.disabled }
							className={ `pbk-tabs__tab ${
								selected ? 'is-active' : ''
							}`.trim() }
							onClick={ () => setActive( tab.key ) }
							onKeyDown={ onKeyDown }
						>
							{ tab.icon && (
								<Icon name={ tab.icon } size={ 18 } />
							) }
							<span>{ tab.label }</span>
							{ tab.count !== undefined && tab.count !== null && (
								<span className="pbk-tabs__count">
									{ tab.count }
								</span>
							) }
						</button>
					);
				} ) }
			</div>
			{ children && (
				<div
					className="pbk-tabs__panel"
					role="tabpanel"
					id={ `${ base }-panel-${ active }` }
					aria-labelledby={ `${ base }-tab-${ active }` }
					tabIndex={ 0 }
				>
					{ typeof children === 'function'
						? children( active )
						: children }
				</div>
			) }
		</div>
	);
}
