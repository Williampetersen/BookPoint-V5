/**
 * Admin app root: theme, providers, router and screen switch. Screens are lazy chunks.
 */
import { lazy, Suspense } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { ThemeRoot, ToastProvider, ConfirmProvider, Spinner } from '../ui';
import { RouteProvider, useRoute } from './router';
import { AdminConfigProvider } from './context';
import { Shell, useAdminTheme } from './layout/Shell';
import './admin.css';

const DashboardScreen = lazy( () => import( /* webpackChunkName: "admin/dashboard" */ './screens/DashboardScreen' ) );
const CalendarScreen = lazy( () => import( /* webpackChunkName: "admin/calendar" */ './screens/CalendarScreen' ) );
const BookingsScreen = lazy( () => import( /* webpackChunkName: "admin/bookings" */ './screens/BookingsScreen' ) );
const CustomersScreen = lazy( () => import( /* webpackChunkName: "admin/customers" */ './screens/CustomersScreen' ) );
const ServicesScreen = lazy( () => import( /* webpackChunkName: "admin/services" */ './screens/ServicesScreen' ) );
const StaffScreen = lazy( () => import( /* webpackChunkName: "admin/staff" */ './screens/StaffScreen' ) );
const LocationsScreen = lazy( () => import( /* webpackChunkName: "admin/locations" */ './screens/LocationsScreen' ) );
const NotificationsScreen = lazy( () => import( /* webpackChunkName: "admin/notifications" */ './screens/NotificationsScreen' ) );
const BookingFormDesignScreen = lazy( () => import( /* webpackChunkName: "admin/design" */ './screens/BookingFormDesignScreen' ) );
const SettingsScreen = lazy( () => import( /* webpackChunkName: "admin/settings" */ './screens/SettingsScreen' ) );
const HelpScreen = lazy( () => import( /* webpackChunkName: "admin/help" */ './screens/HelpScreen' ) );
const UiKit = lazy( () => import( /* webpackChunkName: "admin/ui-kit" */ './dev/UiKit' ) );

const SCREENS = {
	dashboard: DashboardScreen,
	calendar: CalendarScreen,
	bookings: BookingsScreen,
	customers: CustomersScreen,
	services: ServicesScreen,
	staff: StaffScreen,
	locations: LocationsScreen,
	notifications: NotificationsScreen,
	'booking-form': BookingFormDesignScreen,
	settings: SettingsScreen,
	help: HelpScreen,
};

function ScreenFallback() {
	return (
		<div className="pbk-screen-loading" role="status">
			<Spinner size={ 24 } />
			<span className="pbk-sr-only">{ __( 'Loading…', 'pointly-booking' ) }</span>
		</div>
	);
}

function ScreenSwitch() {
	const { route } = useRoute();
	const Screen = SCREENS[ route ] || DashboardScreen;
	return (
		<Suspense fallback={ <ScreenFallback /> }>
			<Screen />
		</Suspense>
	);
}

export default function App( { config, initialRoute } ) {
	const [ theme ] = useAdminTheme();
	const params = new URLSearchParams( window.location.search );

	if ( config.debug && params.get( 'pbk_ui_kit' ) ) {
		return (
			<Suspense fallback={ <ScreenFallback /> }>
				<UiKit config={ config } />
			</Suspense>
		);
	}

	return (
		<ThemeRoot brand={ config.primary || '#4f46e5' } mode={ theme } admin>
			<AdminConfigProvider config={ config }>
				<ToastProvider>
					<ConfirmProvider>
						<RouteProvider config={ config } initialRoute={ initialRoute }>
							<Shell config={ config }>
								<ScreenSwitch />
							</Shell>
						</RouteProvider>
					</ConfirmProvider>
				</ToastProvider>
			</AdminConfigProvider>
		</ThemeRoot>
	);
}
