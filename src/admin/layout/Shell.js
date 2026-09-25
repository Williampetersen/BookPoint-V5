/**
 * Admin app shell: collapsible sidebar, top bar, content canvas.
 */
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Icon, IconButton, Dropdown, Avatar, Tooltip } from '../../ui';
import { useRoute } from '../router';
import './shell.css';

const NAV = [
	{ route: 'dashboard', icon: 'home' },
	{ route: 'calendar', icon: 'calendar' },
	{ route: 'bookings', icon: 'calendar-check' },
	{ route: 'customers', icon: 'users' },
	{ route: 'services', icon: 'sparkles' },
	{ route: 'staff', icon: 'user-plus' },
	{ route: 'locations', icon: 'map-pin' },
	{ route: 'notifications', icon: 'bell' },
	{ route: 'booking-form', icon: 'palette' },
];
const NAV_FOOTER = [
	{ route: 'settings', icon: 'settings' },
	{ route: 'help', icon: 'help-circle' },
];

const THEME_KEY = 'pointlybooking_theme';
const THEME_ICONS = { dark: 'moon', system: 'monitor', light: 'sun' };

function readTheme() {
	try {
		return window.localStorage.getItem( THEME_KEY ) || 'light';
	} catch ( e ) {
		return 'light';
	}
}

function writeTheme( value ) {
	try {
		window.localStorage.setItem( THEME_KEY, value );
	} catch ( e ) {
		// Private mode: the choice just does not persist across visits.
	}
}

/**
 * Admin theme (light/dark/system), remembered per browser.
 *
 * @return {[string, Function]} [ theme, setTheme ].
 */
export function useAdminTheme() {
	const [ theme, setTheme ] = useState( readTheme );
	useEffect( () => writeTheme( theme ), [ theme ] );
	return [ theme, setTheme ];
}

function NavLink( { item, active, collapsed, pages, navigate } ) {
	const page = pages[ item.route ];
	if ( ! page || ! page.can ) {
		return null;
	}
	const link = (
		<a
			href={ page.url }
			className={ `pbk-nav__item ${ active ? 'is-active' : '' }` }
			onClick={ ( event ) => {
				event.preventDefault();
				navigate( item.route );
			} }
		>
			<Icon name={ item.icon } size={ 20 } />
			<span className="pbk-nav__label">{ page.label }</span>
		</a>
	);
	return collapsed ? <Tooltip text={ page.label } placement="right">{ link }</Tooltip> : link;
}

export function Shell( { config, children } ) {
	const { route, navigate, pages } = useRoute();
	const title = pages[ route ] ? pages[ route ].label : '';
	const [ collapsed, setCollapsed ] = useState( false );
	const [ mobileOpen, setMobileOpen ] = useState( false );
	const [ theme, setTheme ] = useAdminTheme();

	useEffect( () => setMobileOpen( false ), [ route ] );

	const bookingsPage = pages.bookings;

	return (
		<div className={ `pbk-shell ${ collapsed ? 'is-collapsed' : '' } ${ mobileOpen ? 'is-mobile-open' : '' }` }>
			<button type="button" className="pbk-shell__scrim" aria-hidden="true" tabIndex={ -1 } onClick={ () => setMobileOpen( false ) } />
			<nav className="pbk-nav" aria-label={ __( 'BookPoint', 'pointly-booking' ) }>
				<div className="pbk-nav__brand">
					<span className="pbk-nav__mark" aria-hidden="true">
						<Icon name="calendar-check" size={ 20 } />
					</span>
					<span className="pbk-nav__brand-text">{ __( 'BookPoint', 'pointly-booking' ) }</span>
				</div>
				<div className="pbk-nav__list">
					{ NAV.map( ( item ) => (
						<NavLink key={ item.route } item={ item } active={ route === item.route } collapsed={ collapsed } pages={ pages } navigate={ navigate } />
					) ) }
				</div>
				<div className="pbk-nav__spacer" />
				<div className="pbk-nav__list">
					{ NAV_FOOTER.map( ( item ) => (
						<NavLink key={ item.route } item={ item } active={ route === item.route } collapsed={ collapsed } pages={ pages } navigate={ navigate } />
					) ) }
				</div>
				<button type="button" className="pbk-nav__collapse" onClick={ () => setCollapsed( ( v ) => ! v ) }>
					<Icon name={ collapsed ? 'chevron-right' : 'chevron-left' } size={ 18 } />
					{ ! collapsed && <span>{ __( 'Collapse', 'pointly-booking' ) }</span> }
				</button>
			</nav>
			<div className="pbk-shell__main">
				<header className="pbk-topbar">
					<button type="button" className="pbk-topbar__menu" onClick={ () => setMobileOpen( true ) } aria-label={ __( 'Open menu', 'pointly-booking' ) }>
						<Icon name="menu" size={ 22 } />
					</button>
					<div className="pbk-topbar__crumb">
						<span className="pbk-topbar__title">{ title }</span>
					</div>
					<div className="pbk-spacer" />
					{ bookingsPage && bookingsPage.can && (
						<a
							className="pbk-btn pbk-btn--primary pbk-btn--sm pbk-topbar__add"
							href={ bookingsPage.url }
							onClick={ ( event ) => {
								event.preventDefault();
								navigate( 'bookings', { view: 'new' } );
							} }
						>
							<Icon name="plus" size={ 16 } />
							<span className="pbk-btn__label">{ __( 'Booking', 'pointly-booking' ) }</span>
						</a>
					) }
					<Dropdown
						label={ __( 'Theme', 'pointly-booking' ) }
						placement="bottom-end"
						trigger={ ( props ) => <IconButton { ...props } icon={ THEME_ICONS[ theme ] || 'sun' } label={ __( 'Theme', 'pointly-booking' ) } /> }
						items={ [
							{ label: __( 'Light', 'pointly-booking' ), icon: 'sun', onClick: () => setTheme( 'light' ) },
							{ label: __( 'Dark', 'pointly-booking' ), icon: 'moon', onClick: () => setTheme( 'dark' ) },
							{ label: __( 'Match system', 'pointly-booking' ), icon: 'monitor', onClick: () => setTheme( 'system' ) },
						] }
					/>
					<Dropdown
						label={ config.user ? config.user.name : __( 'Account', 'pointly-booking' ) }
						placement="bottom-end"
						trigger={ ( props ) => (
							<button type="button" { ...props } className="pbk-topbar__user">
								<Avatar name={ config.user ? config.user.name : '' } size={ 30 } />
							</button>
						) }
						items={ [
							{ label: __( 'WordPress dashboard', 'pointly-booking' ), icon: 'grid', href: config.adminUrl },
							{ label: __( 'View site', 'pointly-booking' ), icon: 'external-link', href: config.siteUrl, target: '_blank' },
						] }
					/>
				</header>
				<main className="pbk-canvas">{ children }</main>
			</div>
		</div>
	);
}
