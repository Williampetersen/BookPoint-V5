/**
 * Manage booking page: details, add to calendar, reschedule, cancel.
 */
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Calendar,
	TimeSlots,
	Notice,
	Skeleton,
	ErrorState,
	Icon,
	StatusBadge,
	Badge,
	useConfirm,
	useToast,
	monthOf,
	availabilityLabel,
} from '../ui';
import { makeFormat } from '../front/wizard/context';
import { client } from './api';

export function BookingDetails( { booking, fmt, timezoneLabel } ) {
	return (
		<div className="pbk-mb__card">
			<div className="pbk-mb__row">
				<span className="pbk-subtle">
					{ __( 'Booking', 'pointly-booking' ) }
				</span>
				<strong className="pbk-tabular">#{ booking.id }</strong>
				<StatusBadge
					status={ booking.status }
					label={ booking.status_label }
				/>
			</div>
			<p className="pbk-mb__service">{ booking.service_name }</p>
			<p className="pbk-mb__line">
				<Icon name="calendar" size={ 16 } />
				{ fmt.date( booking.start, 'long' ) }
			</p>
			<p className="pbk-mb__line">
				<Icon name="clock" size={ 16 } />
				{ fmt.time( booking.start.slice( 11, 16 ) ) } –{ ' ' }
				{ fmt.time( booking.end.slice( 11, 16 ) ) }
				{ timezoneLabel && (
					<span className="pbk-subtle">({ timezoneLabel })</span>
				) }
			</p>
			{ booking.agent_name && (
				<p className="pbk-mb__line">
					<Icon name="user" size={ 16 } />
					{ booking.agent_name }
				</p>
			) }
			{ booking.location_name && (
				<p className="pbk-mb__line">
					<Icon name="map-pin" size={ 16 } />
					{ booking.location_name }
				</p>
			) }
			{ booking.total > 0 && (
				<p className="pbk-mb__line">
					<Icon name="wallet" size={ 16 } />
					{ fmt.money( booking.total ) }
					{ booking.payment_status === 'paid' ? (
						<Badge tone="success">
							{ __( 'Paid', 'pointly-booking' ) }
						</Badge>
					) : (
						<Badge>
							{ __( 'Not paid yet', 'pointly-booking' ) }
						</Badge>
					) }
				</p>
			) }
		</div>
	);
}

function dotsFor( count ) {
	if ( count >= 6 ) {
		return 3;
	}
	return count >= 3 ? 2 : 1;
}

function Reschedule( { booking, settings, fmt, onDone, onCancel } ) {
	const [ month, setMonth ] = useState(
		monthOf( booking.start ) < settings.today
			? monthOf( settings.today )
			: monthOf( booking.start )
	);
	const [ days, setDays ] = useState( null );
	const [ date, setDate ] = useState( '' );
	const [ slots, setSlots ] = useState( [] );
	const [ loadingDay, setLoadingDay ] = useState( false );
	const [ start, setStart ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ refresh, setRefresh ] = useState( 0 );

	useEffect( () => {
		let alive = true;
		setDays( null );
		client()
			.get( 'manage/availability/month', { key: booking.key, month } )
			.then( ( data ) => alive && setDays( data.days || {} ) )
			.catch( ( e ) => alive && setError( e.message ) );
		return () => {
			alive = false;
		};
	}, [ booking.key, month ] );

	useEffect( () => {
		if ( ! date ) {
			return undefined;
		}
		let alive = true;
		setLoadingDay( true );
		setStart( '' );
		client()
			.get( 'manage/availability/day', { key: booking.key, date } )
			.then( ( data ) => {
				if ( alive ) {
					setSlots( data.slots || [] );
					setLoadingDay( false );
				}
			} )
			.catch( ( e ) => {
				if ( alive ) {
					setLoadingDay( false );
					setError( e.message );
				}
			} );
		return () => {
			alive = false;
		};
	}, [ booking.key, date, refresh ] );

	const save = () => {
		setSaving( true );
		setError( '' );
		client()
			.post( 'manage/reschedule', { key: booking.key, date, start } )
			.then( ( updated ) => onDone( updated ) )
			.catch( ( e ) => {
				setSaving( false );
				setError( e.message );
				if ( e.status === 409 ) {
					// That time was just taken: reload the free times.
					setRefresh( ( n ) => n + 1 );
				}
			} );
	};

	return (
		<div className="pbk-mb__reschedule">
			<h2 className="pbk-mb__heading">
				{ __( 'Choose a new time', 'pointly-booking' ) }
			</h2>
			{ error && <Notice tone="danger">{ error }</Notice> }
			<div className="pbk-dt">
				<Calendar
					month={ month }
					onMonthChange={ setMonth }
					value={ date }
					onChange={ setDate }
					today={ settings.today }
					min={ settings.today }
					weekStartsOn={ settings.week_starts }
					locale={ fmt.locale }
					loading={ ! days }
					isDateDisabled={ ( day ) =>
						day < settings.today || ! days || ! days[ day ]
					}
					getDayInfo={ ( day ) =>
						days && days[ day ]
							? {
									dots: dotsFor( days[ day ] ),
									label: availabilityLabel( days[ day ] ),
							  }
							: {}
					}
				/>
				<div className="pbk-dt__slots">
					<h3 className="pbk-dt__day">
						{ date
							? fmt.date( date, 'long' )
							: __(
									'Choose a date to see free times',
									'pointly-booking'
							  ) }
					</h3>
					{ date && (
						<TimeSlots
							slots={ slots }
							loading={ loadingDay }
							value={ start }
							onChange={ setStart }
							locale={ fmt.locale }
							timeFormat={ fmt.timeFormat }
						/>
					) }
				</div>
			</div>
			<div className="pbk-mb__actions">
				<Button
					variant="ghost"
					onClick={ onCancel }
					disabled={ saving }
				>
					{ __( 'Keep the current time', 'pointly-booking' ) }
				</Button>
				<Button
					variant="primary"
					onClick={ save }
					disabled={ ! date || ! start }
					loading={ saving }
				>
					{ date && start
						? sprintf(
								/* translators: 1: date, 2: time */
								__( 'Move to %1$s, %2$s', 'pointly-booking' ),
								fmt.date( date, 'short' ),
								fmt.time( start )
						  )
						: __( 'Choose a date and time', 'pointly-booking' ) }
				</Button>
			</div>
		</div>
	);
}

export default function ManageApp( { config, bookingKey } ) {
	const settings = config.settings || {};
	const fmt = makeFormat( settings );
	const confirm = useConfirm();
	const toast = useToast();
	const [ booking, setBooking ] = useState( null );
	const [ error, setError ] = useState(
		bookingKey
			? ''
			: __(
					'This link is not complete. Please use the link from your confirmation email.',
					'pointly-booking'
			  )
	);
	const [ mode, setMode ] = useState( 'view' );
	const [ busy, setBusy ] = useState( false );
	const [ reload, setReload ] = useState( 0 );

	useEffect( () => {
		if ( ! bookingKey ) {
			return undefined;
		}
		let alive = true;
		client()
			.get( 'manage/booking', { key: bookingKey } )
			.then( ( data ) => alive && setBooking( data ) )
			.catch( ( e ) => alive && setError( e.message ) );
		return () => {
			alive = false;
		};
	}, [ bookingKey, reload ] );

	// After a change the key rotates: keep the address bar pointing at a working link.
	const adopt = useCallback( ( updated ) => {
		setBooking( updated );
		if ( updated.key ) {
			const url = new URL( window.location.href );
			url.searchParams.set( 'key', updated.key );
			window.history.replaceState( null, '', url.toString() );
		}
	}, [] );

	const cancelBooking = async () => {
		const ok = await confirm( {
			title: __( 'Cancel this booking?', 'pointly-booking' ),
			message: __(
				'Your time will be released for other customers. This cannot be undone online.',
				'pointly-booking'
			),
			confirmLabel: __( 'Cancel booking', 'pointly-booking' ),
			cancelLabel: __( 'Keep booking', 'pointly-booking' ),
			tone: 'danger',
		} );
		if ( ! ok ) {
			return;
		}
		setBusy( true );
		client()
			.post( 'manage/cancel', { key: booking.key } )
			.then( ( updated ) => {
				adopt( updated );
				toast.success(
					__( 'Your booking was cancelled.', 'pointly-booking' )
				);
			} )
			.catch( ( e ) => toast.error( e.message ) )
			.finally( () => setBusy( false ) );
	};

	if ( error ) {
		return (
			<ErrorState
				title={ __(
					'We could not open this booking',
					'pointly-booking'
				) }
				message={ error }
				onRetry={
					bookingKey
						? () => setError( '' ) || setReload( ( n ) => n + 1 )
						: undefined
				}
			/>
		);
	}
	if ( ! booking ) {
		return (
			<div className="pbk-mb" aria-busy="true">
				<Skeleton variant="text" width="50%" />
				<Skeleton variant="block" height={ 160 } />
			</div>
		);
	}

	return (
		<div className="pbk-mb">
			<header className="pbk-mb__header">
				<h1 className="pbk-mb__title">
					{ __( 'Your booking', 'pointly-booking' ) }
				</h1>
				{ config.businessName && (
					<p className="pbk-subtle">{ config.businessName }</p>
				) }
			</header>
			<BookingDetails
				booking={ booking }
				fmt={ fmt }
				timezoneLabel={ settings.timezone_label }
			/>
			{ booking.status === 'cancelled' && (
				<Notice tone="neutral">
					{ __(
						'This booking has been cancelled.',
						'pointly-booking'
					) }
				</Notice>
			) }
			{ booking.is_past && booking.status !== 'cancelled' && (
				<Notice tone="info">
					{ __(
						'This appointment is in the past.',
						'pointly-booking'
					) }
				</Notice>
			) }
			{ mode === 'reschedule' ? (
				<Reschedule
					booking={ booking }
					settings={ settings }
					fmt={ fmt }
					onCancel={ () => setMode( 'view' ) }
					onDone={ ( updated ) => {
						adopt( updated );
						setMode( 'view' );
						toast.success(
							__(
								'Your booking was moved to the new time.',
								'pointly-booking'
							)
						);
					} }
				/>
			) : (
				<div className="pbk-mb__actions">
					{ booking.status !== 'cancelled' && booking.ics_url && (
						<Button
							variant="primary"
							icon="calendar-plus"
							href={ booking.ics_url }
							download={ `booking-${ booking.id }.ics` }
						>
							{ __( 'Add to calendar', 'pointly-booking' ) }
						</Button>
					) }
					{ booking.can_reschedule && (
						<Button
							icon="repeat"
							onClick={ () => setMode( 'reschedule' ) }
						>
							{ __( 'Reschedule', 'pointly-booking' ) }
						</Button>
					) }
					{ booking.can_cancel && (
						<Button
							variant="ghost"
							icon="x-circle"
							onClick={ cancelBooking }
							loading={ busy }
							className="pbk-mb__cancel"
						>
							{ __( 'Cancel booking', 'pointly-booking' ) }
						</Button>
					) }
				</div>
			) }
		</div>
	);
}
