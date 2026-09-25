/**
 * Onboarding wizard: business info → first service → working hours → done.
 */
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Modal, Stepper, Field, Input, Select, Notice, Button, Icon, useToast } from '../../ui';
import { client, useResource, fieldError } from '../api';
import { useAdminConfig } from '../context';
import './onboarding.css';

const STEPS = [
	{ key: 'business', label: __( 'Your business', 'pointly-booking' ) },
	{ key: 'service', label: __( 'First service', 'pointly-booking' ) },
	{ key: 'hours', label: __( 'Working hours', 'pointly-booking' ) },
	{ key: 'done', label: __( 'Done', 'pointly-booking' ) },
];

const CURRENCIES = [ 'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'NZD', 'CHF', 'SEK', 'NOK', 'DKK', 'JPY', 'INR', 'BRL', 'MXN', 'ZAR' ];

const DAY_LABELS = [ '', __( 'Monday', 'pointly-booking' ), __( 'Tuesday', 'pointly-booking' ), __( 'Wednesday', 'pointly-booking' ), __( 'Thursday', 'pointly-booking' ), __( 'Friday', 'pointly-booking' ), __( 'Saturday', 'pointly-booking' ), __( 'Sunday', 'pointly-booking' ) ];

function BusinessStep( { value, onChange, error } ) {
	return (
		<div className="pbk-drawer-form__grid">
			<Field label={ __( 'Business name', 'pointly-booking' ) } required error={ fieldError( error, 'business_name' ) } className="pbk-field--full">
				<Input value={ value.business_name } onChange={ ( e ) => onChange( { business_name: e.target.value } ) } />
			</Field>
			<Field label={ __( 'Business email', 'pointly-booking' ) } required error={ fieldError( error, 'business_email' ) }>
				<Input type="email" value={ value.business_email } onChange={ ( e ) => onChange( { business_email: e.target.value } ) } />
			</Field>
			<Field label={ __( 'Phone', 'pointly-booking' ) } optional>
				<Input type="tel" value={ value.business_phone } onChange={ ( e ) => onChange( { business_phone: e.target.value } ) } />
			</Field>
			<Field label={ __( 'Currency', 'pointly-booking' ) }>
				<Select value={ value.currency } onChange={ ( e ) => onChange( { currency: e.target.value } ) } options={ CURRENCIES.map( ( code ) => ( { value: code, label: code } ) ) } />
			</Field>
			<Field label={ __( 'Address', 'pointly-booking' ) } optional className="pbk-field--full">
				<Input value={ value.business_address } onChange={ ( e ) => onChange( { business_address: e.target.value } ) } />
			</Field>
		</div>
	);
}

function ServiceStep( { value, onChange, error, currencySymbol } ) {
	return (
		<div className="pbk-drawer-form__grid">
			<Field label={ __( 'Service name', 'pointly-booking' ) } required error={ fieldError( error, 'name' ) } className="pbk-field--full">
				<Input value={ value.name } onChange={ ( e ) => onChange( { name: e.target.value } ) } placeholder={ __( 'e.g. Consultation', 'pointly-booking' ) } />
			</Field>
			<Field label={ __( 'Duration (minutes)', 'pointly-booking' ) } required>
				<Input type="number" min="5" max="1440" step="5" value={ value.duration_minutes } onChange={ ( e ) => onChange( { duration_minutes: e.target.value } ) } />
			</Field>
			<Field label={ __( 'Price', 'pointly-booking' ) } required>
				<Input type="number" min="0" step="0.01" prefix={ currencySymbol } value={ value.price } onChange={ ( e ) => onChange( { price: e.target.value } ) } />
			</Field>
		</div>
	);
}

function HoursStep( { value, onChange } ) {
	const toggle = ( day, enabled ) => {
		const schedule = { ...value.schedule };
		if ( enabled ) {
			schedule[ day ] = [ { start: '09:00', end: '17:00', is_enabled: true } ];
		} else {
			delete schedule[ day ];
		}
		onChange( { schedule } );
	};
	const setTime = ( day, key, time ) => {
		const schedule = { ...value.schedule };
		schedule[ day ] = [ { ...schedule[ day ][ 0 ], [ key ]: time } ];
		onChange( { schedule } );
	};
	return (
		<div className="pbk-stack">
			<Field label={ __( 'Slot length (minutes)', 'pointly-booking' ) }>
				<Input type="number" min="5" max="120" step="5" value={ value.slot_interval_minutes } onChange={ ( e ) => onChange( { slot_interval_minutes: e.target.value } ) } />
			</Field>
			<div className="pbk-onboarding-hours">
				{ [ 1, 2, 3, 4, 5, 6, 7 ].map( ( day ) => {
					const on = !! value.schedule[ day ];
					const row = on ? value.schedule[ day ][ 0 ] : { start: '09:00', end: '17:00' };
					const dayId = `pbk-onboarding-day-${ day }`;
					return (
						<div key={ day } className="pbk-onboarding-hours__row">
							<label className="pbk-onboarding-hours__day" htmlFor={ dayId }>
								<input id={ dayId } type="checkbox" checked={ on } onChange={ ( e ) => toggle( day, e.target.checked ) } />
								{ DAY_LABELS[ day ] }
							</label>
							{ on ? (
								<div className="pbk-onboarding-hours__times">
									<Input type="time" value={ row.start } onChange={ ( e ) => setTime( day, 'start', e.target.value ) } />
									<span className="pbk-subtle">{ __( 'to', 'pointly-booking' ) }</span>
									<Input type="time" value={ row.end } onChange={ ( e ) => setTime( day, 'end', e.target.value ) } />
								</div>
							) : (
								<span className="pbk-subtle">{ __( 'Closed', 'pointly-booking' ) }</span>
							) }
						</div>
					);
				} ) }
			</div>
		</div>
	);
}

export default function OnboardingWizard( { open, onFinished } ) {
	const { data } = useResource( open ? 'admin/onboarding' : null );
	const config = useAdminConfig();
	const toast = useToast();
	const [ step, setStep ] = useState( 0 );
	const [ business, setBusiness ] = useState( { business_name: '', business_email: '', business_phone: '', business_address: '', currency: 'USD' } );
	const [ service, setService ] = useState( { name: '', duration_minutes: 30, price: 0 } );
	const [ hours, setHours ] = useState( { slot_interval_minutes: 30, schedule: { 1: [ { start: '09:00', end: '17:00', is_enabled: true } ], 2: [ { start: '09:00', end: '17:00', is_enabled: true } ], 3: [ { start: '09:00', end: '17:00', is_enabled: true } ], 4: [ { start: '09:00', end: '17:00', is_enabled: true } ], 5: [ { start: '09:00', end: '17:00', is_enabled: true } ] } } );
	const [ error, setError ] = useState( null );
	const [ busy, setBusy ] = useState( false );

	useEffect( () => {
		if ( data && data.business ) {
			setBusiness( ( prev ) => ( { ...prev, ...data.business } ) );
			if ( data.steps.service ) {
				if ( ! data.steps.business ) {
					setStep( 0 );
				} else {
					setStep( data.steps.hours ? 3 : 2 );
				}
			}
		}
	}, [ data ] );

	const save = async ( key, values ) => {
		setBusy( true );
		setError( null );
		try {
			await client().post( 'admin/onboarding', { step: key, values } );
			return true;
		} catch ( e ) {
			setError( e );
			return false;
		} finally {
			setBusy( false );
		}
	};

	const next = async () => {
		if ( step === 0 ) {
			if ( await save( 'business', business ) ) {
				setStep( 1 );
			}
		} else if ( step === 1 ) {
			if ( await save( 'service', service ) ) {
				setStep( 2 );
			}
		} else if ( step === 2 ) {
			if ( await save( 'hours', hours ) ) {
				await save( 'complete', {} );
				setStep( 3 );
			}
		} else {
			onFinished();
		}
	};

	const skip = async () => {
		await save( 'dismiss', {} );
		onFinished();
	};

	return (
		<Modal open={ open } onClose={ skip } title={ __( 'Set up BookPoint', 'pointly-booking' ) } size="md" hideClose={ step === 3 }>
			<div className="pbk-stack">
				<Stepper steps={ STEPS } current={ STEPS[ step ].key } done={ STEPS.slice( 0, step ).map( ( s ) => s.key ) } />
				{ error && <Notice tone="danger">{ error.message }</Notice> }
				{ step === 0 && <BusinessStep value={ business } onChange={ ( patch ) => setBusiness( ( prev ) => ( { ...prev, ...patch } ) ) } error={ error } /> }
				{ step === 1 && <ServiceStep value={ service } onChange={ ( patch ) => setService( ( prev ) => ( { ...prev, ...patch } ) ) } error={ error } currencySymbol={ config.currency_symbol } /> }
				{ step === 2 && <HoursStep value={ hours } onChange={ ( patch ) => setHours( ( prev ) => ( { ...prev, ...patch } ) ) } /> }
				{ step === 3 && (
					<div className="pbk-onboarding-done">
						<span className="pbk-onboarding-done__icon">
							<Icon name="check-circle" size={ 32 } />
						</span>
						<h3>{ __( 'You’re all set!', 'pointly-booking' ) }</h3>
						<p className="pbk-subtle">{ __( 'Add the booking form to any page with the “Booking form” block, or the [pointlybooking_booking_form] shortcode.', 'pointly-booking' ) }</p>
					</div>
				) }
				<div className="pbk-onboarding-actions">
					{ step < 3 && (
						<Button variant="ghost" onClick={ skip } disabled={ busy }>
							{ __( 'Skip for now', 'pointly-booking' ) }
						</Button>
					) }
					<Button
						variant="primary"
						loading={ busy }
						onClick={ () => {
							next().then( () => step === 2 && toast.success( __( 'Setup complete.', 'pointly-booking' ) ) );
						} }
					>
						{ step === 3 ? __( 'Go to dashboard', 'pointly-booking' ) : __( 'Continue', 'pointly-booking' ) }
					</Button>
				</div>
			</div>
		</Modal>
	);
}
