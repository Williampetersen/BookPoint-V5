/**
 * The booking wizard.
 */
import {
	lazy,
	Suspense,
	useCallback,
	useEffect,
	useLayoutEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Stepper,
	Notice,
	Skeleton,
	ErrorState,
	Spinner,
	Icon,
	useTheme,
} from '../../ui';
import * as api from './api';
import { FormatProvider, makeFormat } from './context';
import {
	initialSelection,
	visibleSteps,
	autoSelect,
	stepComplete,
	priceOf,
	paymentMethods,
	defaultMethod,
	detailFields,
	validateAll,
	availableServices,
	candidateAgents,
	extrasFor,
} from './flow';
import { stepText } from './texts';
import StepArt from './StepArt';
import { SummaryCard, SummaryBar } from './Summary';
import {
	LocationStep,
	CategoryStep,
	ServiceStep,
	ExtrasStep,
	StaffStep,
} from './steps/SelectSteps';
import DateTimeStep from './steps/DateTimeStep';
import DetailsStep from './steps/DetailsStep';
import { PaymentStep, ReviewStep, DoneStep } from './steps/FinalSteps';
import './wizard.css';

const StripePayment = lazy( () =>
	import( /* webpackChunkName: "front/stripe" */ './StripePayment' )
);

const PAYMENT_PARAMS = [
	'pointlybooking_payment',
	'booking_id',
	'key',
	'token',
	'PayerID',
	'session_id',
	'payment_intent',
	'payment_intent_client_secret',
	'redirect_status',
];

export function cleanUrl() {
	const url = new URL( window.location.href );
	PAYMENT_PARAMS.forEach( ( param ) => url.searchParams.delete( param ) );
	url.hash = '';
	return url.toString();
}

// Re-subscribes when `key` changes (the root element differs between phases).
function useWidth( ref, key ) {
	const [ width, setWidth ] = useState( 0 );
	useLayoutEffect( () => {
		if ( ! ref.current ) {
			return undefined;
		}
		setWidth( ref.current.getBoundingClientRect().width );
		if ( typeof window.ResizeObserver !== 'function' ) {
			return undefined;
		}
		const observer = new window.ResizeObserver( ( entries ) =>
			setWidth( entries[ 0 ].contentRect.width )
		);
		observer.observe( ref.current );
		return () => observer.disconnect();
	}, [ ref, key ] );
	return width;
}

function hasInput( selection, options ) {
	const typed = Object.values( selection.fields ).some( ( group ) =>
		Object.values( group ).some(
			( value ) => value && String( value ).trim() !== ''
		)
	);
	return (
		typed ||
		( !! selection.serviceId &&
			selection.serviceId !== options.serviceId ) ||
		!! selection.date
	);
}

function emit( name, detail ) {
	try {
		window.dispatchEvent(
			new window.CustomEvent( `pointlybooking:${ name }`, { detail } )
		);
	} catch ( e ) {
		// Old browsers: events are optional.
	}
}

function prefillFromUser( config ) {
	const user = config.user || {};
	const customer = {};
	[ 'first_name', 'last_name', 'email' ].forEach( ( key ) => {
		if ( user[ key ] ) {
			customer[ key ] = user[ key ];
		}
	} );
	return customer;
}

export default function Wizard( {
	options = {},
	mode = 'modal',
	paymentReturn = null,
	onDirtyChange,
	onRequestClose,
	onRestart,
	config = {},
} ) {
	const [ data, setData ] = useState( null );
	const [ loadError, setLoadError ] = useState( '' );
	const [ reload, setReload ] = useState( 0 );

	useEffect( () => {
		let alive = true;
		setLoadError( '' );
		api.loadBootstrap()
			.then( ( result ) => alive && setData( result ) )
			.catch( ( error ) => alive && setLoadError( error.message ) );
		return () => {
			alive = false;
		};
	}, [ reload ] );

	if ( loadError ) {
		return (
			<div className="pbk-wizard pbk-wizard--state">
				<ErrorState
					title={ __(
						'The booking form could not be loaded',
						'pointly-booking'
					) }
					message={ loadError }
					onRetry={ () => setReload( ( n ) => n + 1 ) }
				/>
			</div>
		);
	}
	if ( ! data ) {
		return (
			<div className="pbk-wizard pbk-wizard--state" aria-busy="true">
				<span className="pbk-sr-only">
					{ __( 'Loading the booking form…', 'pointly-booking' ) }
				</span>
				<div className="pbk-wizard__loading">
					<Skeleton variant="text" width="40%" />
					<Skeleton variant="block" height={ 72 } />
					<Skeleton variant="block" height={ 72 } />
					<Skeleton variant="block" height={ 72 } />
				</div>
			</div>
		);
	}
	return (
		<FormatProvider settings={ data.settings }>
			<WizardFlow
				data={ data }
				options={ options }
				mode={ mode }
				paymentReturn={ paymentReturn }
				onDirtyChange={ onDirtyChange }
				onRequestClose={ onRequestClose }
				onRestart={ onRestart }
				config={ config }
			/>
		</FormatProvider>
	);
}

function WizardFlow( {
	data,
	options,
	mode,
	paymentReturn,
	onDirtyChange,
	onRequestClose,
	onRestart,
	config,
} ) {
	const theme = useTheme();
	const fmt = useMemo( () => makeFormat( data.settings ), [ data.settings ] );
	const design = data.design || {};
	const behavior = design.behavior || {};
	const rootRef = useRef( null );
	const headingRef = useRef( null );
	const honeypotRef = useRef( null );
	const firstRender = useRef( true );

	const [ selection, setSelection ] = useState( () => {
		const start = initialSelection( options );
		start.fields.customer = prefillFromUser( config );
		return { ...start, ...autoSelect( data, start ) };
	} );
	const [ step, setStep ] = useState( null );
	const [ completed, setCompleted ] = useState( [] );
	const [ touched, setTouched ] = useState( {} );
	const [ errors, setErrors ] = useState( {} );
	const [ quoteData, setQuoteData ] = useState( null );
	const [ promo, setPromo ] = useState( {
		applied: '',
		message: '',
		valid: null,
		busy: false,
	} );
	const [ phase, setPhase ] = useState(
		paymentReturn ? 'returning' : 'form'
	);
	const width = useWidth( rootRef, phase );
	const wide = width >= 760;
	const [ result, setResult ] = useState( null );
	const [ notice, setNotice ] = useState( null );
	const [ paymentError, setPaymentError ] = useState( '' );
	const [ retrying, setRetrying ] = useState( false );
	const [ cancelled, setCancelled ] = useState( false );

	const quoteMatches =
		quoteData &&
		quoteData.service_id === selection.serviceId &&
		quoteData.extrasKey === selection.extras.slice().sort().join( ',' );
	const price = priceOf( data, selection, quoteMatches ? quoteData : null );
	const visible = visibleSteps( data, selection, options, price.total );
	const current =
		step && visible.includes( step )
			? step
			: visible.find( ( key ) => ! completed.includes( key ) ) ||
			  visible[ visible.length - 1 ];
	const methods = paymentMethods( data, price.total );
	const fields = useMemo(
		() => detailFields( data, options ),
		[ data, options ]
	);
	const text = stepText( design, current );
	const dirty = phase !== 'done' && hasInput( selection, options );

	useEffect( () => {
		if ( onDirtyChange ) {
			onDirtyChange( dirty );
		}
	}, [ dirty, onDirtyChange ] );

	// Keep a valid payment method selected.
	useEffect( () => {
		const ids = methods.map( ( method ) => method.id );
		if ( ids.length && ! ids.includes( selection.paymentMethod ) ) {
			setSelection( ( prev ) => ( {
				...prev,
				paymentMethod: defaultMethod( data, price.total ),
			} ) );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ methods.length, price.total ] );

	// Move keyboard/screen-reader focus to the new step heading.
	useEffect( () => {
		if ( firstRender.current ) {
			firstRender.current = false;
			if ( mode === 'inline' ) {
				return;
			}
		}
		if ( headingRef.current ) {
			headingRef.current.focus( { preventScroll: false } );
		}
	}, [ current, phase, mode ] );

	const update = useCallback(
		( patch ) =>
			setSelection( ( prev ) => {
				const next = { ...prev, ...patch };
				if (
					'locationId' in patch &&
					patch.locationId !== prev.locationId
				) {
					if (
						next.serviceId &&
						! availableServices(
							data,
							next.locationId,
							next.categoryId
						).some( ( s ) => s.id === next.serviceId )
					) {
						next.serviceId = 0;
					}
					next.agentId = options.agentId || null;
					next.start = '';
				}
				if (
					'categoryId' in patch &&
					patch.categoryId !== prev.categoryId &&
					next.serviceId
				) {
					const service = data.services.find(
						( s ) => s.id === next.serviceId
					);
					if (
						service &&
						patch.categoryId &&
						! ( service.category_ids || [] ).includes(
							patch.categoryId
						)
					) {
						next.serviceId = 0;
					}
				}
				if ( next.serviceId !== prev.serviceId ) {
					const allowed = extrasFor( data, next.serviceId ).map(
						( extra ) => extra.id
					);
					next.extras = next.extras.filter( ( id ) =>
						allowed.includes( id )
					);
					if (
						next.agentId &&
						! candidateAgents(
							data,
							next.serviceId,
							next.locationId
						).some( ( agent ) => agent.id === next.agentId )
					) {
						next.agentId = null;
					}
					next.start = '';
				}
				if ( 'agentId' in patch && patch.agentId !== prev.agentId ) {
					next.start = '';
				}
				return { ...next, ...autoSelect( data, next ) };
			} ),
		[ data, options.agentId ]
	);

	// Re-price an applied promo code when the service or extras change.
	useEffect( () => {
		if ( ! promo.applied || ! selection.serviceId ) {
			return;
		}
		api.quote( selection.serviceId, selection.extras, promo.applied )
			.then( ( q ) =>
				setQuoteData( {
					...q,
					service_id: selection.serviceId,
					extrasKey: selection.extras.slice().sort().join( ',' ),
				} )
			)
			.catch( () => {} );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ selection.serviceId, selection.extras.join( ',' ) ] );

	const applyPromo = ( code ) => {
		setPromo( ( p ) => ( { ...p, busy: true } ) );
		api.quote( selection.serviceId, selection.extras, code )
			.then( ( q ) => {
				setQuoteData( {
					...q,
					service_id: selection.serviceId,
					extrasKey: selection.extras.slice().sort().join( ',' ),
				} );
				setPromo( {
					applied: q.promo_valid ? q.promo_code : '',
					valid: !! q.promo_valid,
					message: q.promo_valid
						? sprintf(
								/* translators: %s: discount amount */
								__(
									'Promo code applied. You save %s.',
									'pointly-booking'
								),
								fmt.money( q.discount )
						  )
						: q.promo_message ||
						  __(
								'This promo code is not valid.',
								'pointly-booking'
						  ),
					busy: false,
				} );
			} )
			.catch( ( error ) =>
				setPromo( {
					applied: '',
					valid: false,
					message: error.message,
					busy: false,
				} )
			);
	};

	// Where an "Edit" link should go: the step itself, or the step that chose it (null = not editable).
	const editTarget = ( key ) => {
		if ( visible.includes( key ) ) {
			return key;
		}
		if ( key === 'service' && visible.includes( 'category' ) ) {
			return 'category';
		}
		return null;
	};
	const editStep = ( key ) => {
		const target = editTarget( key );
		if ( target ) {
			goTo( target );
		}
	};

	const goTo = ( key ) => {
		setNotice( null );
		setStep( key );
	};

	const onFieldChange = ( field, value ) => {
		setSelection( ( prev ) => ( {
			...prev,
			fields: {
				...prev.fields,
				[ field.scope ]: {
					...prev.fields[ field.scope ],
					[ field.key ]: value,
				},
			},
		} ) );
		const id = `${ field.scope }.${ field.key }`;
		if ( touched[ id ] ) {
			setErrors( ( prev ) => ( {
				...prev,
				[ id ]:
					validateAll( [ field ], {
						[ field.scope ]: { [ field.key ]: value },
					} )[ id ] || '',
			} ) );
		}
	};

	const selectionPrompt = {
		location: __(
			'Please choose a location to continue.',
			'pointly-booking'
		),
		service: __(
			'Please choose a service to continue.',
			'pointly-booking'
		),
		agents: __(
			'Please choose a staff member, or “Any available”.',
			'pointly-booking'
		),
		datetime: __(
			'Please choose a date and a time to continue.',
			'pointly-booking'
		),
		payment: __(
			'Please choose how you would like to pay.',
			'pointly-booking'
		),
	};

	const handlePayment = ( booking, payment ) => {
		if ( ! payment || payment.type === 'none' ) {
			setPhase( 'done' );
			return;
		}
		if ( payment.type === 'redirect' ) {
			setPhase( 'redirecting' );
			window.location.assign( payment.url );
			return;
		}
		if ( payment.type === 'stripe_elements' ) {
			setResult( { booking, payment } );
			setPhase( 'paying' );
			return;
		}
		setPaymentError(
			payment.error ||
				__(
					'Online payment is not available right now.',
					'pointly-booking'
				)
		);
		setPhase( 'done' );
	};

	const submit = async () => {
		setPhase( 'submitting' );
		setNotice( null );
		try {
			const response = await api.createBooking( {
				service_id: selection.serviceId,
				agent_id: selection.agentId || 0,
				location_id: selection.locationId || 0,
				date: selection.date,
				start: selection.start,
				extras: selection.extras,
				promo_code: promo.valid ? promo.applied : '',
				payment_method:
					selection.paymentMethod ||
					defaultMethod( data, price.total ),
				fields: selection.fields,
				return_url: cleanUrl(),
				website: honeypotRef.current ? honeypotRef.current.value : '',
			} );
			setResult( response );
			emit( 'booking-created', {
				id: response.booking.id,
				status: response.booking.status,
			} );
			handlePayment( response.booking, response.payment );
		} catch ( error ) {
			setPhase( 'form' );
			if ( error.status === 409 || error.code === 'slot_unavailable' ) {
				api.clearAvailability();
				update( { start: '' } );
				setCompleted( ( list ) =>
					list.filter( ( key ) => key !== 'datetime' )
				);
				setStep( 'datetime' );
				setNotice( { tone: 'warning', text: error.message } );
				return;
			}
			const field = fields.find(
				( f ) =>
					f.key === error.field ||
					`${ f.scope }.${ f.key }` === error.field
			);
			if ( field ) {
				const id = `${ field.scope }.${ field.key }`;
				setTouched( ( prev ) => ( { ...prev, [ id ]: true } ) );
				setErrors( ( prev ) => ( { ...prev, [ id ]: error.message } ) );
				setStep( 'customer' );
				return;
			}
			if (
				error.field === 'payment_method' &&
				visible.includes( 'payment' )
			) {
				setStep( 'payment' );
			}
			setNotice( { tone: 'danger', text: error.message } );
		}
	};

	const next = () => {
		setNotice( null );
		if ( current === 'customer' ) {
			const found = validateAll( fields, selection.fields );
			if ( Object.keys( found ).length ) {
				const all = {};
				fields.forEach( ( field ) => {
					all[ `${ field.scope }.${ field.key }` ] = true;
				} );
				setTouched( all );
				setErrors( found );
				window.requestAnimationFrame( () => {
					const invalid =
						rootRef.current &&
						rootRef.current.querySelector(
							'[aria-invalid="true"]'
						);
					if ( invalid ) {
						invalid.focus();
					}
				} );
				return;
			}
		}
		if ( ! stepComplete( current, selection ) ) {
			setNotice( { tone: 'warning', text: selectionPrompt[ current ] } );
			return;
		}
		setCompleted( ( list ) =>
			list.includes( current ) ? list : [ ...list, current ]
		);
		const index = visible.indexOf( current );
		if ( index >= visible.length - 1 ) {
			submit();
		} else {
			setStep( visible[ index + 1 ] );
		}
	};

	const back = () => {
		const index = visible.indexOf( current );
		if ( index > 0 ) {
			goTo( visible[ index - 1 ] );
		}
	};

	// Payment provider return (?pointlybooking_payment=…).
	useEffect( () => {
		if ( ! paymentReturn ) {
			return undefined;
		}
		let alive = true;
		const { type, bookingId, key, token } = paymentReturn;
		const finish = ( booking, wasCancelled = false ) => {
			if ( ! alive ) {
				return;
			}
			setResult( { booking, payment: null } );
			setCancelled( wasCancelled );
			setPhase( 'done' );
		};
		const fail = ( error ) => {
			if ( ! alive ) {
				return;
			}
			setPaymentError( error.message );
			api.bookingStatus( bookingId, key )
				.then( ( booking ) => finish( booking ) )
				.catch( () => setPhase( 'form' ) );
		};
		if ( /_cancel$/.test( type ) ) {
			api.cancelPayment( bookingId, key )
				.then( ( booking ) => finish( booking, true ) )
				.catch( fail );
		} else if ( type === 'paypal_return' ) {
			api.capturePaypal( bookingId, key, token )
				.then( ( response ) => finish( response.booking ) )
				.catch( fail );
		} else {
			let attempts = 0;
			const poll = () =>
				api
					.bookingStatus( bookingId, key )
					.then( ( booking ) => {
						attempts += 1;
						if (
							booking.payment_status === 'paid' ||
							attempts >= 8
						) {
							finish( booking );
						} else if ( alive ) {
							window.setTimeout( poll, 2000 );
						}
					} )
					.catch( fail );
			if ( paymentReturn.paymentIntent ) {
				// Back from a card check (3-D Secure): confirm the PaymentIntent directly.
				api.confirmStripe( bookingId, key, paymentReturn.paymentIntent )
					.then( ( response ) => finish( response.booking ) )
					.catch( poll );
			} else {
				poll();
			}
		}
		return () => {
			alive = false;
		};
	}, [ paymentReturn ] );

	const retryPayment = () => {
		if ( ! result || ! result.booking ) {
			return;
		}
		setRetrying( true );
		setPaymentError( '' );
		api.startPayment( result.booking.id, result.booking.key, cleanUrl() )
			.then( ( payment ) => {
				setRetrying( false );
				handlePayment( result.booking, payment );
			} )
			.catch( ( error ) => {
				setRetrying( false );
				setPaymentError( error.message );
			} );
	};

	const restart = () => {
		if ( onRestart ) {
			onRestart();
		}
	};

	const steps = visible.map( ( key ) => ( {
		key,
		label: stepText( design, key ).short,
	} ) );
	const isLast = visible.indexOf( current ) === visible.length - 1;
	const method = methods.find( ( m ) => m.id === selection.paymentMethod );
	let nextLabel = text.next;
	if ( isLast ) {
		nextLabel =
			method && method.online
				? __( 'Continue to payment', 'pointly-booking' )
				: __( 'Confirm booking', 'pointly-booking' );
	}
	const stepProps = { data, selection, update };
	const helpTitle = ( design.texts || {} ).helpTitle;
	const helpPhone =
		( design.texts || {} ).helpPhone || data.settings.business_phone;
	const showAside =
		wide &&
		( behavior.showSummary !== false ||
			( text.step && text.step.showLeftPanel !== false ) );

	if ( phase === 'returning' || phase === 'redirecting' ) {
		return (
			<div
				ref={ rootRef }
				className="pbk-wizard pbk-wizard--state"
				role="status"
			>
				<Spinner size={ 28 } />
				<p className="pbk-wizard__status">
					{ phase === 'redirecting'
						? __(
								'Taking you to the secure payment page…',
								'pointly-booking'
						  )
						: __( 'Checking your payment…', 'pointly-booking' ) }
				</p>
			</div>
		);
	}

	if ( phase === 'done' && result ) {
		return (
			<div ref={ rootRef } className="pbk-wizard pbk-wizard--done">
				<div
					className="pbk-wizard__scroll"
					ref={ headingRef }
					tabIndex={ -1 }
				>
					<DoneStep
						data={ data }
						booking={ result.booking }
						cancelled={ cancelled }
						paymentError={ paymentError }
						retrying={ retrying }
						onRetryPayment={ retryPayment }
						onClose={ mode === 'modal' ? onRequestClose : restart }
						closeLabel={
							mode === 'modal'
								? __( 'Close', 'pointly-booking' )
								: __(
										'Book another appointment',
										'pointly-booking'
								  )
						}
					/>
				</div>
			</div>
		);
	}

	let content = null;
	if ( phase === 'paying' && result ) {
		content = (
			<Suspense fallback={ <Skeleton variant="block" height={ 180 } /> }>
				<StripePayment
					booking={ result.booking }
					payment={ result.payment }
					brand={ theme.brand }
					dark={ theme.theme === 'dark' }
					amountLabel={ fmt.money( result.booking.total ) }
					returnUrl={ `${ cleanUrl() }${
						cleanUrl().includes( '?' ) ? '&' : '?'
					}pointlybooking_payment=stripe_success&booking_id=${
						result.booking.id
					}&key=${ result.booking.key }` }
					onPaid={ ( booking ) => {
						setResult( { booking, payment: null } );
						setPhase( 'done' );
					} }
				/>
			</Suspense>
		);
	} else {
		switch ( current ) {
			case 'location':
				content = <LocationStep { ...stepProps } />;
				break;
			case 'category':
				content = <CategoryStep { ...stepProps } />;
				break;
			case 'service':
				content = (
					<ServiceStep
						{ ...stepProps }
						showCategoryFilter={
							! visible.includes( 'category' ) &&
							! options.categoryId
						}
					/>
				);
				break;
			case 'extras':
				content = <ExtrasStep { ...stepProps } />;
				break;
			case 'agents':
				content = <StaffStep { ...stepProps } />;
				break;
			case 'datetime':
				content = (
					<DateTimeStep
						{ ...stepProps }
						scope={ {
							serviceId: selection.serviceId,
							agentId: selection.agentId || 0,
							locationId: selection.locationId || 0,
						} }
						defaultDate={ options.defaultDate }
					/>
				);
				break;
			case 'customer':
				content = (
					<DetailsStep
						fields={ fields }
						values={ selection.fields }
						onChange={ onFieldChange }
						errors={ errors }
						touched={ touched }
						honeypotRef={ honeypotRef }
						onTouch={ ( id, message ) => {
							setTouched( ( prev ) => ( {
								...prev,
								[ id ]: true,
							} ) );
							setErrors( ( prev ) => ( {
								...prev,
								[ id ]: message,
							} ) );
						} }
					/>
				);
				break;
			case 'payment':
				content = (
					<PaymentStep
						methods={ methods }
						value={ selection.paymentMethod }
						onChange={ ( value ) =>
							update( { paymentMethod: value } )
						}
					/>
				);
				break;
			default:
				content = (
					<ReviewStep
						data={ data }
						selection={ selection }
						fields={ fields }
						price={ price }
						methods={
							data.payment && data.payment.methods
								? data.payment.methods
								: []
						}
						visible={ visible }
						onEdit={ goTo }
						promo={ promo }
						onPromo={ applyPromo }
						onPromoRemove={ () => {
							setPromo( {
								applied: '',
								message: '',
								valid: null,
								busy: false,
							} );
							setQuoteData( null );
						} }
					/>
				);
		}
	}

	const headingText =
		phase === 'paying' ? __( 'Payment', 'pointly-booking' ) : text.title;
	const subtitleText =
		phase === 'paying'
			? __(
					'Enter your card details to complete the booking.',
					'pointly-booking'
			  )
			: text.subtitle;

	return (
		<div
			ref={ rootRef }
			className={ `pbk-wizard pbk-wizard--${ mode } ${
				wide ? 'is-wide' : 'is-narrow'
			} ${ options.compact ? 'is-compact' : '' }`.trim() }
		>
			<div className="pbk-wizard__layout">
				{ showAside && (
					<aside className="pbk-wizard__aside">
						{ text.step &&
							text.step.showLeftPanel !== false &&
							! options.compact && (
								<div className="pbk-wizard__art">
									<StepArt
										stepKey={ current }
										design={ design }
									/>
								</div>
							) }
						{ behavior.showSummary !== false && (
							<SummaryCard
								data={ data }
								selection={ selection }
								price={ price }
								onEdit={
									phase === 'form' ? editStep : undefined
								}
								editable={ editTarget }
							/>
						) }
						{ ( helpTitle || helpPhone ) &&
							text.step &&
							text.step.showHelpBox !== false && (
								<div className="pbk-help">
									<Icon name="help-circle" size={ 18 } />
									<div>
										<p className="pbk-help__title">
											{ helpTitle ||
												__(
													'Need help?',
													'pointly-booking'
												) }
										</p>
										{ helpPhone && (
											<a
												className="pbk-help__phone"
												href={ `tel:${ helpPhone.replace(
													/[^+0-9]/g,
													''
												) }` }
											>
												{ helpPhone }
											</a>
										) }
									</div>
								</div>
							) }
					</aside>
				) }
				<div className="pbk-wizard__main">
					{ phase !== 'paying' && steps.length > 1 && (
						<div className="pbk-wizard__stepper">
							<Stepper
								steps={ steps }
								current={ current }
								done={ completed.filter( ( key ) =>
									stepComplete( key, selection )
								) }
								onStepClick={
									phase === 'form' ? goTo : undefined
								}
								variant={
									width < 640 ? 'compact' : 'horizontal'
								}
							/>
						</div>
					) }
					<div className="pbk-wizard__scroll">
						<header className="pbk-wizard__header">
							<h2
								className="pbk-wizard__title"
								ref={ headingRef }
								tabIndex={ -1 }
							>
								{ headingText }
							</h2>
							{ subtitleText && (
								<p className="pbk-wizard__subtitle">
									{ subtitleText }
								</p>
							) }
						</header>
						{ notice && (
							<div className="pbk-wizard__notice">
								<Notice
									tone={ notice.tone }
									onDismiss={ () => setNotice( null ) }
								>
									{ notice.text }
								</Notice>
							</div>
						) }
						<div
							className="pbk-wizard__content"
							key={ `${ current }-${ phase }` }
						>
							{ content }
						</div>
					</div>
					{ phase !== 'paying' && (
						<div className="pbk-wizard__footer">
							{ ! wide && behavior.showSummary !== false && (
								<SummaryBar
									data={ data }
									selection={ selection }
									price={ price }
									onEdit={ editStep }
									editable={ editTarget }
								/>
							) }
							<div className="pbk-wizard__actions">
								{ visible.indexOf( current ) > 0 && (
									<Button
										variant="ghost"
										icon="arrow-left"
										onClick={ back }
										disabled={ phase === 'submitting' }
										className="pbk-wizard__back"
									>
										{ text.back }
									</Button>
								) }
								<Button
									variant="primary"
									size="lg"
									onClick={ next }
									loading={ phase === 'submitting' }
									iconRight={
										isLast ? undefined : 'arrow-right'
									}
									className="pbk-wizard__next"
								>
									{ nextLabel }
								</Button>
							</div>
						</div>
					) }
				</div>
			</div>
		</div>
	);
}
