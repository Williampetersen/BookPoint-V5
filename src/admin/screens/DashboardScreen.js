/**
 * Dashboard: KPIs, chart, upcoming/recent bookings, mini calendar, quick actions.
 */
import { useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Card, StatusBadge, Icon, Button, Tabs, Calendar, Skeleton, ErrorState, EmptyState, Avatar, availabilityLabel } from '../../ui';
import { useResource } from '../api';
import { useAdminConfig, useAdminFormat } from '../context';
import { useRoute } from '../router';
import { PageHeader } from '../layout/PageHeader';
import { MiniChart } from '../components/MiniChart';
import OnboardingWizard from '../onboarding/OnboardingWizard';
import './dashboard.css';

const RANGES = [
	{ key: '7', label: __( '7 days', 'pointly-booking' ) },
	{ key: '30', label: __( '30 days', 'pointly-booking' ) },
	{ key: '90', label: __( '90 days', 'pointly-booking' ) },
	{ key: 'ytd', label: __( 'Year to date', 'pointly-booking' ) },
];

function Delta( { current, previous } ) {
	if ( ! previous ) {
		return null;
	}
	const change = Math.round( ( ( current - previous ) / previous ) * 100 );
	if ( change === 0 ) {
		return null;
	}
	return (
		<span className={ `pbk-kpi__delta ${ change > 0 ? 'is-up' : 'is-down' }` }>
			<Icon name="trending" size={ 12 } />
			{ change > 0 ? '+' : '' }
			{ change }%
		</span>
	);
}

function KpiCard( { icon, label, value, rawValue, previous, tone = 'brand' } ) {
	return (
		<Card className="pbk-kpi" padding="sm">
			<span className={ `pbk-kpi__icon pbk-kpi__icon--${ tone }` }>
				<Icon name={ icon } size={ 20 } />
			</span>
			<span className="pbk-kpi__value pbk-tabular">{ value }</span>
			<span className="pbk-kpi__label">{ label }</span>
			{ previous !== undefined && <Delta current={ rawValue !== undefined ? rawValue : value } previous={ previous } /> }
		</Card>
	);
}

function BookingRow( { booking, fmt, onOpen } ) {
	return (
		<button type="button" className="pbk-mini-row" onClick={ () => onOpen( booking.id ) }>
			<Avatar name={ booking.customer_name } size={ 32 } />
			<span className="pbk-mini-row__text">
				<span className="pbk-mini-row__title">{ booking.customer_name || __( '(no name)', 'pointly-booking' ) }</span>
				<span className="pbk-mini-row__meta">
					{ booking.service_name } · { fmt.date( booking.start, 'short' ) } { fmt.time( booking.start.slice( 11, 16 ) ) }
				</span>
			</span>
			<StatusBadge status={ booking.status } size="sm" />
		</button>
	);
}

export default function DashboardScreen() {
	const config = useAdminConfig();
	const fmt = useAdminFormat();
	const { navigate } = useRoute();
	const [ range, setRange ] = useState( '30' );
	const [ metric, setMetric ] = useState( 'count' );
	const [ onboardingOpen, setOnboardingOpen ] = useState( false );
	const [ dismissedBanner, setDismissedBanner ] = useState( false );

	const { data, loading, error, reload } = useResource( 'admin/dashboard', { range } );

	const openBooking = ( id ) => navigate( 'bookings', { view: 'edit', id } );

	const chartData = useMemo( () => ( data ? data.chart.map( ( row ) => ( { date: row.date, value: row[ metric ] } ) ) : [] ), [ data, metric ] );

	const showOnboarding = config.onboarding && config.onboarding.show && ! dismissedBanner;

	if ( error ) {
		return (
			<div>
				<PageHeader title={ __( 'Dashboard', 'pointly-booking' ) } />
				<ErrorState message={ error } onRetry={ reload } />
			</div>
		);
	}

	return (
		<div className="pbk-dashboard">
			<PageHeader
				title={ sprintf(
					/* translators: %s: business name */
					__( 'Welcome back%s', 'pointly-booking' ),
					config.businessName ? `, ${ config.businessName }` : ''
				) }
				description={ __( 'Here is what is happening with your bookings.', 'pointly-booking' ) }
				actions={
					<>
						<Button variant="secondary" icon="settings" onClick={ () => navigate( 'settings', { tab: 'form_fields' } ) }>
							{ __( 'Form fields', 'pointly-booking' ) }
						</Button>
						<Button variant="primary" icon="plus" onClick={ () => navigate( 'bookings', { view: 'new' } ) }>
							{ __( 'New booking', 'pointly-booking' ) }
						</Button>
					</>
				}
			/>

			{ showOnboarding && (
				<Card className="pbk-onboarding-banner">
					<span className="pbk-onboarding-banner__icon">
						<Icon name="sparkles" size={ 22 } />
					</span>
					<div className="pbk-onboarding-banner__text">
						<h3>{ __( 'Finish setting up BookPoint', 'pointly-booking' ) }</h3>
						<p className="pbk-subtle">{ __( 'Add your business details, first service and working hours — it takes about two minutes.', 'pointly-booking' ) }</p>
					</div>
					<div className="pbk-onboarding-banner__actions">
						<Button variant="ghost" onClick={ () => setDismissedBanner( true ) }>
							{ __( 'Dismiss', 'pointly-booking' ) }
						</Button>
						<Button variant="primary" onClick={ () => setOnboardingOpen( true ) }>
							{ __( 'Continue setup', 'pointly-booking' ) }
						</Button>
					</div>
				</Card>
			) }

			<div className="pbk-quick-actions">
				{ [
					{ icon: 'plus', label: __( 'Add service', 'pointly-booking' ), route: 'services', params: { view: 'new' } },
					{ icon: 'user-plus', label: __( 'Add staff', 'pointly-booking' ), route: 'staff', params: { view: 'new' } },
					{ icon: 'calendar-check', label: __( 'Manage bookings', 'pointly-booking' ), route: 'bookings' },
					{ icon: 'calendar', label: __( 'Open calendar', 'pointly-booking' ), route: 'calendar' },
					{ icon: 'settings', label: __( 'Edit form fields', 'pointly-booking' ), route: 'settings', params: { tab: 'form_fields' } },
					{ icon: 'sliders', label: __( 'Settings', 'pointly-booking' ), route: 'settings' },
				].map( ( action ) => (
					<button key={ action.label } type="button" className="pbk-quick-action" onClick={ () => navigate( action.route, action.params || {} ) }>
						<Icon name={ action.icon } size={ 18 } />
						{ action.label }
					</button>
				) ) }
			</div>

			{ loading && ! data ? (
				<div className="pbk-cards">
					{ Array.from( { length: 5 } ).map( ( _, i ) => (
						<Card key={ i } padding="sm">
							<Skeleton variant="block" height={ 64 } />
						</Card>
					) ) }
				</div>
			) : (
				data && (
					<>
						<div className="pbk-kpi-row">
							<KpiCard icon="calendar" label={ __( 'Today', 'pointly-booking' ) } value={ data.kpis.today } tone="brand" />
							<KpiCard icon="clock" label={ __( 'Next 7 days', 'pointly-booking' ) } value={ data.kpis.upcoming_7d } tone="info" />
							<KpiCard icon="hourglass" label={ __( 'Pending', 'pointly-booking' ) } value={ data.kpis.pending } tone="warning" />
							<KpiCard icon="dollar" label={ __( 'Revenue', 'pointly-booking' ) } value={ fmt.money( data.kpis.revenue ) } rawValue={ data.kpis.revenue } previous={ data.kpis.revenue_previous } tone="success" />
							<KpiCard icon="user-plus" label={ __( 'New customers', 'pointly-booking' ) } value={ data.kpis.new_customers } tone="brand" />
						</div>

						<div className="pbk-dashboard__grid">
							<Card
								className="pbk-dashboard__chart"
								title={ __( 'Bookings over time', 'pointly-booking' ) }
								actions={
									<div className="pbk-row">
										<Tabs
											variant="pills"
											value={ metric }
											onChange={ setMetric }
											label={ __( 'Metric', 'pointly-booking' ) }
											tabs={ [
												{ key: 'count', label: __( 'Bookings', 'pointly-booking' ) },
												{ key: 'revenue', label: __( 'Revenue', 'pointly-booking' ) },
											] }
										/>
										<Tabs variant="pills" value={ range } onChange={ setRange } label={ __( 'Range', 'pointly-booking' ) } tabs={ RANGES } />
									</div>
								}
							>
								<MiniChart
									data={ chartData }
									formatLabel={ ( date ) => fmt.date( date, 'short' ) }
									formatValue={ ( value ) => ( metric === 'revenue' ? fmt.money( value ) : value ) }
									caption={ __( 'Bookings by day', 'pointly-booking' ) }
								/>
								{ data.top_services.length > 0 && (
									<div className="pbk-top-services">
										<h4>{ __( 'Top services', 'pointly-booking' ) }</h4>
										{ data.top_services.map( ( service ) => (
											<div key={ service.service_id } className="pbk-top-services__row">
												<span>{ service.name }</span>
												<span className="pbk-subtle pbk-tabular">
													{ service.bookings } · { fmt.money( service.revenue ) }
												</span>
											</div>
										) ) }
									</div>
								) }
							</Card>

							<Card className="pbk-dashboard__mini-calendar" title={ __( 'This month', 'pointly-booking' ) }>
								<Calendar
									variant="plain"
									month={ data.month.month }
									onMonthChange={ () => {} }
									value=""
									today={ data.today }
									onChange={ () => navigate( 'calendar' ) }
									getDayInfo={ ( date ) => ( data.month.days[ date ] ? { dots: Math.min( 3, Math.ceil( data.month.days[ date ] / 2 ) ), label: availabilityLabel( data.month.days[ date ] ) } : {} ) }
									locale={ fmt.locale }
									weekStartsOn={ config.weekStartsOn }
									label={ __( 'Bookings this month', 'pointly-booking' ) }
								/>
							</Card>

							<Card className="pbk-dashboard__list" title={ __( 'Upcoming', 'pointly-booking' ) } actions={ <Button size="sm" variant="ghost" onClick={ () => navigate( 'bookings' ) }>{ __( 'View all', 'pointly-booking' ) }</Button> }>
								{ data.upcoming.length ? (
									<div className="pbk-mini-list">
										{ data.upcoming.map( ( booking ) => (
											<BookingRow key={ booking.id } booking={ booking } fmt={ fmt } onOpen={ openBooking } />
										) ) }
									</div>
								) : (
									<EmptyState compact icon="calendar" title={ __( 'Nothing coming up', 'pointly-booking' ) } />
								) }
							</Card>

							<Card className="pbk-dashboard__list" title={ __( 'Recent activity', 'pointly-booking' ) }>
								{ data.recent.length ? (
									<div className="pbk-mini-list">
										{ data.recent.map( ( booking ) => (
											<BookingRow key={ booking.id } booking={ booking } fmt={ fmt } onOpen={ openBooking } />
										) ) }
									</div>
								) : (
									<EmptyState compact icon="activity" title={ __( 'No bookings yet', 'pointly-booking' ) } />
								) }
							</Card>
						</div>
					</>
				)
			) }

			<OnboardingWizard
				open={ onboardingOpen }
				onClose={ () => setOnboardingOpen( false ) }
				onFinished={ () => {
					setOnboardingOpen( false );
					setDismissedBanner( true );
				} }
			/>
		</div>
	);
}
