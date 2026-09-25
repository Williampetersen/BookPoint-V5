/**
 * Customer portal: sign in with a one-time code sent by email, then see your bookings.
 */
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Field,
	Input,
	Notice,
	Skeleton,
	EmptyState,
	StatusBadge,
	Icon,
	Tabs,
} from '../ui';
import { makeFormat } from '../front/wizard/context';
import { client } from './api';

const STORE = 'pointlybooking_portal';

function readSession() {
	try {
		const raw = window.sessionStorage.getItem( STORE );
		const data = raw ? JSON.parse( raw ) : null;
		return data && data.expires * 1000 > Date.now() ? data : null;
	} catch ( e ) {
		return null;
	}
}

function writeSession( data ) {
	try {
		if ( data ) {
			window.sessionStorage.setItem( STORE, JSON.stringify( data ) );
		} else {
			window.sessionStorage.removeItem( STORE );
		}
	} catch ( e ) {
		// Private mode: the session simply lasts until the page is closed.
	}
}

function BookingRow( { booking, fmt } ) {
	return (
		<li className="pbk-portal__item">
			<div className="pbk-portal__when">
				<span className="pbk-portal__day">
					{ fmt.date( booking.start, 'short' ) }
				</span>
				<span className="pbk-portal__time pbk-tabular">
					{ fmt.time( booking.start.slice( 11, 16 ) ) }
				</span>
			</div>
			<div className="pbk-portal__what">
				<span className="pbk-portal__service">
					{ booking.service_name }
				</span>
				<span className="pbk-subtle">
					{ [ booking.agent_name, booking.location_name ]
						.filter( Boolean )
						.join( ' · ' ) }
				</span>
			</div>
			<StatusBadge
				status={ booking.status }
				label={ booking.status_label }
				size="sm"
			/>
			{ booking.manage_url && (
				<Button
					size="sm"
					href={ booking.manage_url }
					iconRight="chevron-right"
				>
					{ __( 'Manage', 'pointly-booking' ) }
					<span className="pbk-sr-only">
						{ ' ' }
						{ booking.service_name }
					</span>
				</Button>
			) }
		</li>
	);
}

export default function PortalApp( { config } ) {
	const fmt = makeFormat( config.settings || {} );
	const [ session, setSession ] = useState( readSession );
	const [ step, setStep ] = useState( session ? 'list' : 'email' );
	const [ email, setEmail ] = useState( session ? session.email : '' );
	const [ code, setCode ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ info, setInfo ] = useState( '' );
	const [ bookings, setBookings ] = useState( null );
	const [ tab, setTab ] = useState( 'upcoming' );

	const signOut = ( message = '' ) => {
		if ( session ) {
			client()
				.post( 'portal/logout', {
					email: session.email,
					token: session.token,
				} )
				.catch( () => {} );
		}
		writeSession( null );
		setSession( null );
		setBookings( null );
		setCode( '' );
		setStep( 'email' );
		setInfo( message );
	};

	useEffect( () => {
		if ( step !== 'list' || ! session ) {
			return undefined;
		}
		let alive = true;
		client()
			.post( 'portal/bookings', {
				email: session.email,
				token: session.token,
			} )
			.then( ( data ) => alive && setBookings( data ) )
			.catch( ( e ) => {
				if ( ! alive ) {
					return;
				}
				if ( e.status === 401 ) {
					signOut(
						__(
							'Your session has ended. Please sign in again.',
							'pointly-booking'
						)
					);
				} else {
					setError( e.message );
				}
			} );
		return () => {
			alive = false;
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ step, session ] );

	const requestCode = ( event ) => {
		event.preventDefault();
		setBusy( true );
		setError( '' );
		setInfo( '' );
		client()
			.post( 'portal/code', { email: email.trim() } )
			.then( () => {
				setStep( 'code' );
				setInfo(
					sprintf(
						/* translators: %s: email address */
						__(
							'If %s has bookings with us, a 6-digit code is on its way. It expires in 10 minutes.',
							'pointly-booking'
						),
						email.trim()
					)
				);
			} )
			.catch( ( e ) => setError( e.message ) )
			.finally( () => setBusy( false ) );
	};

	const verify = ( event ) => {
		event.preventDefault();
		setBusy( true );
		setError( '' );
		client()
			.post( 'portal/verify', { email: email.trim(), code: code.trim() } )
			.then( ( data ) => {
				const next = {
					email: email.trim(),
					token: data.token,
					expires: data.expires,
				};
				writeSession( next );
				setSession( next );
				setInfo( '' );
				setStep( 'list' );
			} )
			.catch( ( e ) => setError( e.message ) )
			.finally( () => setBusy( false ) );
	};

	if ( step === 'list' ) {
		const list = bookings ? bookings[ tab ] : [];
		return (
			<div className="pbk-portal">
				<header className="pbk-portal__header">
					<div>
						<h2 className="pbk-portal__title">
							{ __( 'Your bookings', 'pointly-booking' ) }
						</h2>
						<p className="pbk-subtle">
							{ session && session.email }
						</p>
					</div>
					<Button
						variant="ghost"
						icon="logout"
						onClick={ () => signOut() }
					>
						{ __( 'Sign out', 'pointly-booking' ) }
					</Button>
				</header>
				{ error && <Notice tone="danger">{ error }</Notice> }
				<Tabs
					value={ tab }
					onChange={ setTab }
					label={ __( 'Bookings', 'pointly-booking' ) }
					tabs={ [
						{
							key: 'upcoming',
							label: __( 'Upcoming', 'pointly-booking' ),
							count: bookings
								? bookings.upcoming.length
								: undefined,
						},
						{
							key: 'past',
							label: __(
								'Past and cancelled',
								'pointly-booking'
							),
							count: bookings ? bookings.past.length : undefined,
						},
					] }
				/>
				{ ! bookings && ! error && (
					<div className="pbk-stack">
						<Skeleton variant="block" height={ 64 } />
						<Skeleton variant="block" height={ 64 } />
					</div>
				) }
				{ bookings && list.length > 0 && (
					<ul className="pbk-portal__list">
						{ list.map( ( booking ) => (
							<BookingRow
								key={ booking.id }
								booking={ booking }
								fmt={ fmt }
							/>
						) ) }
					</ul>
				) }
				{ bookings && ! list.length && (
					<EmptyState
						compact
						icon="calendar"
						title={
							tab === 'upcoming'
								? __(
										'No upcoming bookings',
										'pointly-booking'
								  )
								: __( 'Nothing here yet', 'pointly-booking' )
						}
						description={
							tab === 'upcoming'
								? __(
										'When you book an appointment it will show up here.',
										'pointly-booking'
								  )
								: ''
						}
					/>
				) }
			</div>
		);
	}

	return (
		<div className="pbk-portal pbk-portal--signin">
			<span className="pbk-portal__badge" aria-hidden="true">
				<Icon name="lock" size={ 22 } />
			</span>
			<h2 className="pbk-portal__title">
				{ __( 'View your bookings', 'pointly-booking' ) }
			</h2>
			<p className="pbk-subtle">
				{ step === 'email'
					? __(
							'Enter the email address you booked with. We will send you a one-time sign-in code.',
							'pointly-booking'
					  )
					: __(
							'Enter the 6-digit code from the email we just sent.',
							'pointly-booking'
					  ) }
			</p>
			{ info && <Notice tone="info">{ info }</Notice> }
			{ error && <Notice tone="danger">{ error }</Notice> }
			{ step === 'email' ? (
				<form className="pbk-stack" onSubmit={ requestCode }>
					<Field label={ __( 'Email', 'pointly-booking' ) } required>
						<Input
							type="email"
							autoComplete="email"
							value={ email }
							onChange={ ( e ) => setEmail( e.target.value ) }
							required
						/>
					</Field>
					<Button
						type="submit"
						variant="primary"
						size="lg"
						block
						loading={ busy }
					>
						{ __( 'Send me a code', 'pointly-booking' ) }
					</Button>
				</form>
			) : (
				<form className="pbk-stack" onSubmit={ verify }>
					<Field
						label={ __( 'Sign-in code', 'pointly-booking' ) }
						required
					>
						<Input
							inputMode="numeric"
							autoComplete="one-time-code"
							pattern="[0-9]*"
							maxLength={ 6 }
							value={ code }
							onChange={ ( e ) =>
								setCode( e.target.value.replace( /\D/g, '' ) )
							}
							className="pbk-portal__code"
							required
						/>
					</Field>
					<Button
						type="submit"
						variant="primary"
						size="lg"
						block
						loading={ busy }
						disabled={ code.length !== 6 }
					>
						{ __( 'Sign in', 'pointly-booking' ) }
					</Button>
					<div className="pbk-row">
						<Button
							variant="link"
							onClick={ requestCode }
							disabled={ busy }
						>
							{ __( 'Send a new code', 'pointly-booking' ) }
						</Button>
						<span className="pbk-subtle">·</span>
						<Button
							variant="link"
							onClick={ () => {
								setStep( 'email' );
								setInfo( '' );
								setError( '' );
							} }
						>
							{ __( 'Use another email', 'pointly-booking' ) }
						</Button>
					</div>
				</form>
			) }
		</div>
	);
}
