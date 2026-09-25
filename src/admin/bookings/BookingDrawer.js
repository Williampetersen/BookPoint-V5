/**
 * Booking drawer: create a booking, or view/edit an existing one.
 */
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Drawer,
	Field,
	Input,
	Textarea,
	Select,
	Checkbox,
	Toggle,
	Button,
	Notice,
	Skeleton,
	StatusBadge,
	Badge,
	Avatar,
	Icon,
	DateInput,
	TimeSlots,
	SearchInput,
	Dropdown,
	useConfirm,
	useToast,
} from '../../ui';
import { client, useResource, useDebouncedValue, fieldError } from '../api';
import { useAdminConfig, useAdminFormat } from '../context';
import { useRoute } from '../router';
import './bookings.css';

const STATUSES = [ 'pending', 'confirmed', 'completed', 'cancelled', 'pending_payment', 'failed_payment' ];
const PAYMENT_STATUSES = [ 'unpaid', 'paid', 'pending', 'refunded' ];

function statusLabel( value ) {
	const labels = {
		pending: __( 'Pending', 'pointly-booking' ),
		confirmed: __( 'Confirmed', 'pointly-booking' ),
		completed: __( 'Completed', 'pointly-booking' ),
		cancelled: __( 'Cancelled', 'pointly-booking' ),
		pending_payment: __( 'Awaiting payment', 'pointly-booking' ),
		failed_payment: __( 'Payment failed', 'pointly-booking' ),
	};
	return labels[ value ] || value;
}

function paymentStatusLabel( value ) {
	const labels = {
		unpaid: __( 'Unpaid', 'pointly-booking' ),
		paid: __( 'Paid', 'pointly-booking' ),
		pending: __( 'Pending', 'pointly-booking' ),
		refunded: __( 'Refunded', 'pointly-booking' ),
	};
	return labels[ value ] || value;
}

/**
 * Search-and-pick existing customer, or switch to entering a new one.
 */
function CustomerPicker( { customer, onSelect, newCustomer, onNewChange, mode, onModeChange, errors } ) {
	const [ search, setSearch ] = useState( '' );
	const debounced = useDebouncedValue( search, 300 );
	const { data, loading } = useResource( debounced.length >= 2 ? 'admin/customers' : null, { search: debounced, per_page: 8 } );

	if ( mode === 'existing' && customer ) {
		return (
			<div className="pbk-customer-picked">
				<Avatar name={ customer.name } size={ 40 } />
				<div className="pbk-customer-picked__text">
					<strong>{ customer.name }</strong>
					<span className="pbk-subtle">{ customer.email }</span>
				</div>
				<Button size="sm" variant="ghost" onClick={ () => onSelect( null ) }>
					{ __( 'Change', 'pointly-booking' ) }
				</Button>
			</div>
		);
	}

	return (
		<div className="pbk-stack">
			<div className="pbk-row">
				<Button size="sm" variant={ mode === 'existing' ? 'primary' : 'secondary' } onClick={ () => onModeChange( 'existing' ) }>
					{ __( 'Existing customer', 'pointly-booking' ) }
				</Button>
				<Button size="sm" variant={ mode === 'new' ? 'primary' : 'secondary' } onClick={ () => onModeChange( 'new' ) }>
					{ __( 'New customer', 'pointly-booking' ) }
				</Button>
			</div>
			{ mode === 'existing' ? (
				<div className="pbk-customer-search">
					<SearchInput label={ __( 'Search by name or email', 'pointly-booking' ) } value={ search } onChange={ ( e ) => setSearch( e.target.value ) } onClear={ () => setSearch( '' ) } />
					{ loading && <Skeleton variant="text" lines={ 2 } /> }
					{ data && data.items.length > 0 && (
						<ul className="pbk-customer-results">
							{ data.items.map( ( row ) => (
								<li key={ row.id }>
									<button type="button" onClick={ () => onSelect( row ) }>
										<Avatar name={ row.name } size={ 28 } />
										<span>
											{ row.name } <span className="pbk-subtle">{ row.email }</span>
										</span>
									</button>
								</li>
							) ) }
						</ul>
					) }
					{ data && ! data.items.length && debounced.length >= 2 && <p className="pbk-subtle">{ __( 'No matching customers.', 'pointly-booking' ) }</p> }
				</div>
			) : (
				<div className="pbk-drawer-form__grid">
					<Field label={ __( 'First name', 'pointly-booking' ) } required error={ fieldError( errors, 'customer.first_name' ) }>
						<Input value={ newCustomer.first_name } onChange={ ( e ) => onNewChange( { first_name: e.target.value } ) } />
					</Field>
					<Field label={ __( 'Last name', 'pointly-booking' ) } optional>
						<Input value={ newCustomer.last_name } onChange={ ( e ) => onNewChange( { last_name: e.target.value } ) } />
					</Field>
					<Field label={ __( 'Email', 'pointly-booking' ) } required error={ fieldError( errors, 'customer.email' ) } className="pbk-field--full">
						<Input type="email" value={ newCustomer.email } onChange={ ( e ) => onNewChange( { email: e.target.value } ) } />
					</Field>
					<Field label={ __( 'Phone', 'pointly-booking' ) } optional className="pbk-field--full">
						<Input type="tel" value={ newCustomer.phone } onChange={ ( e ) => onNewChange( { phone: e.target.value } ) } />
					</Field>
				</div>
			) }
		</div>
	);
}

function ScheduleFields( { serviceId, agentId, locationId, date, start, onChange, excludeId } ) {
	const scope = { service_id: serviceId, agent_id: agentId || 0, location_id: locationId || 0 };
	const [ slots, setSlots ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const fmt = useAdminFormat();
	const config = useAdminConfig();

	useEffect( () => {
		if ( ! serviceId || ! date ) {
			setSlots( null );
			return undefined;
		}
		let alive = true;
		setLoading( true );
		client()
			.get( 'admin/availability', { ...scope, date, exclude_id: excludeId || 0 } )
			.then( ( result ) => alive && setSlots( result.slots ) )
			.catch( () => alive && setSlots( [] ) )
			.finally( () => alive && setLoading( false ) );
		return () => {
			alive = false;
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ serviceId, agentId, locationId, date, excludeId ] );

	return (
		<div className="pbk-stack">
			<Field label={ __( 'Date', 'pointly-booking' ) } required>
				<DateInput value={ date } onChange={ ( value ) => onChange( { date: value, start: '' } ) } locale={ fmt.locale } weekStartsOn={ config.weekStartsOn } />
			</Field>
			{ date && (
				<Field label={ __( 'Time', 'pointly-booking' ) } required>
					<TimeSlots slots={ slots || [] } loading={ loading || ! serviceId } value={ start } onChange={ ( value ) => onChange( { start: value } ) } locale={ fmt.locale } timeFormat={ config.timeFormat } emptyText={ __( 'No free times for this staff member on this day.', 'pointly-booking' ) } />
				</Field>
			) }
		</div>
	);
}

export default function BookingDrawer( { id, open, onClose, onSaved, onDeleted, prefillDate = '' } ) {
	const isNew = ! id;
	const fmt = useAdminFormat();
	const confirm = useConfirm();
	const toast = useToast();
	const { navigate } = useRoute();
	const { data: lookups } = useResource( open ? 'admin/lookups' : null );
	const { data: detail, loading: loadingDetail, reload } = useResource( ! isNew && open ? `admin/bookings/${ id }` : null );

	const [ form, setForm ] = useState( null );
	const [ customer, setCustomer ] = useState( null );
	const [ customerMode, setCustomerMode ] = useState( 'existing' );
	const [ newCustomer, setNewCustomer ] = useState( { first_name: '', last_name: '', email: '', phone: '' } );
	const [ error, setError ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ notes, setNotes ] = useState( '' );
	const [ savingNotes, setSavingNotes ] = useState( false );

	useEffect( () => {
		if ( ! open ) {
			return;
		}
		if ( isNew ) {
			setForm( {
				service_id: '',
				agent_id: '',
				location_id: '',
				date: prefillDate || '',
				start: '',
				extras: [],
				status: 'pending',
				payment_status: 'unpaid',
				notify: true,
				force: false,
			} );
			setCustomer( null );
			setCustomerMode( 'existing' );
			setNewCustomer( { first_name: '', last_name: '', email: '', phone: '' } );
			setError( null );
		} else if ( detail ) {
			setNotes( detail.notes || '' );
		}
	}, [ open, isNew, detail, prefillDate ] );

	const services = lookups ? lookups.services : [];
	const agents = lookups ? lookups.agents : [];
	const locations = lookups ? lookups.locations : [];
	const extrasList = useMemo( () => ( lookups ? lookups.extras : [] ), [ lookups ] );
	const selectedService = form && services.find( ( s ) => Number( s.id ) === Number( form.service_id ) );

	const total = useMemo( () => {
		if ( ! form || ! selectedService ) {
			return 0;
		}
		const extrasTotal = extrasList.filter( ( e ) => form.extras.includes( e.id ) ).reduce( ( sum, e ) => sum + Number( e.price ), 0 );
		return Number( selectedService.price ) + extrasTotal;
	}, [ form, selectedService, extrasList ] );

	const update = ( patch ) => setForm( ( prev ) => ( { ...prev, ...patch } ) );

	const submitNew = async () => {
		setSaving( true );
		setError( null );
		try {
			const payload = {
				service_id: Number( form.service_id ),
				agent_id: form.agent_id ? Number( form.agent_id ) : 0,
				location_id: form.location_id ? Number( form.location_id ) : 0,
				date: form.date,
				start: form.start,
				extras: form.extras,
				status: form.status,
				payment_status: form.payment_status,
				notify: form.notify,
				force: form.force,
				notes,
			};
			if ( customerMode === 'existing' && customer ) {
				payload.customer_id = customer.id;
			} else {
				payload.fields = { customer: newCustomer };
			}
			const created = await client().post( 'admin/bookings', payload );
			toast.success( __( 'Booking created.', 'pointly-booking' ) );
			onSaved( created );
			onClose();
		} catch ( e ) {
			setError( e );
		} finally {
			setSaving( false );
		}
	};

	const changeStatus = async ( status ) => {
		setSaving( true );
		try {
			await client().patch( `admin/bookings/${ id }`, { status } );
			toast.success( sprintf( /* translators: %s: status label */ __( 'Marked as %s.', 'pointly-booking' ), statusLabel( status ) ) );
			reload();
			onSaved();
		} catch ( e ) {
			toast.error( e.message );
		} finally {
			setSaving( false );
		}
	};

	const changePaymentStatus = async ( paymentStatus ) => {
		try {
			await client().patch( `admin/bookings/${ id }`, { payment_status: paymentStatus } );
			toast.success( __( 'Payment status updated.', 'pointly-booking' ) );
			reload();
		} catch ( e ) {
			toast.error( e.message );
		}
	};

	const saveNotes = async () => {
		setSavingNotes( true );
		try {
			await client().patch( `admin/bookings/${ id }`, { notes } );
			toast.success( __( 'Notes saved.', 'pointly-booking' ) );
			reload();
		} catch ( e ) {
			toast.error( e.message );
		} finally {
			setSavingNotes( false );
		}
	};

	const [ rescheduling, setRescheduling ] = useState( false );
	const [ reschedule, setReschedule ] = useState( { date: '', start: '', agent_id: '' } );
	const startReschedule = () => {
		setReschedule( { date: detail.start.slice( 0, 10 ), start: detail.start.slice( 11, 16 ), agent_id: detail.agent_id || '' } );
		setRescheduling( true );
	};
	const saveReschedule = async () => {
		setSaving( true );
		setError( null );
		try {
			await client().patch( `admin/bookings/${ id }`, { date: reschedule.date, start: reschedule.start, agent_id: reschedule.agent_id ? Number( reschedule.agent_id ) : 0 } );
			toast.success( __( 'Booking rescheduled.', 'pointly-booking' ) );
			setRescheduling( false );
			reload();
			onSaved();
		} catch ( e ) {
			setError( e );
		} finally {
			setSaving( false );
		}
	};

	const deleteBooking = async () => {
		const ok = await confirm( {
			title: __( 'Delete this booking?', 'pointly-booking' ),
			message: __( 'This removes the booking permanently. This cannot be undone.', 'pointly-booking' ),
			confirmLabel: __( 'Delete booking', 'pointly-booking' ),
			tone: 'danger',
		} );
		if ( ! ok ) {
			return;
		}
		try {
			await client().del( `admin/bookings/${ id }` );
			toast.success( __( 'Booking deleted.', 'pointly-booking' ) );
			onDeleted( id );
		} catch ( e ) {
			toast.error( e.message );
		}
	};

	let title = '';
	if ( isNew ) {
		title = __( 'New booking', 'pointly-booking' );
	} else if ( detail ) {
		title = sprintf( /* translators: %d: booking id */ __( 'Booking #%d', 'pointly-booking' ), detail.id );
	}

	let body = null;
	if ( isNew ) {
		body = ! form || ! lookups ? (
			<Skeleton variant="text" lines={ 6 } />
		) : (
				<div className="pbk-drawer-form">
					{ error && <Notice tone="danger">{ error.message }</Notice> }
						<div className="pbk-drawer-form__grid">
							<Field label={ __( 'Service', 'pointly-booking' ) } required className="pbk-field--full" error={ fieldError( error, 'service_id' ) }>
								<Select value={ form.service_id } onChange={ ( e ) => update( { service_id: e.target.value, date: '', start: '' } ) } placeholder={ __( 'Choose a service', 'pointly-booking' ) } options={ services.map( ( s ) => ( { value: s.id, label: `${ s.name } — ${ fmt.money( s.price ) }` } ) ) } />
							</Field>
							<Field label={ __( 'Staff', 'pointly-booking' ) } optional>
								<Select value={ form.agent_id } onChange={ ( e ) => update( { agent_id: e.target.value, date: '', start: '' } ) } placeholder={ __( 'Any available', 'pointly-booking' ) } options={ agents.map( ( a ) => ( { value: a.id, label: a.name } ) ) } />
							</Field>
							{ locations.length > 0 && (
								<Field label={ __( 'Location', 'pointly-booking' ) } optional>
									<Select value={ form.location_id } onChange={ ( e ) => update( { location_id: e.target.value } ) } placeholder={ __( 'None', 'pointly-booking' ) } options={ locations.map( ( l ) => ( { value: l.id, label: l.name } ) ) } />
								</Field>
							) }
						</div>

						{ form.service_id && (
							<ScheduleFields serviceId={ Number( form.service_id ) } agentId={ Number( form.agent_id ) } locationId={ Number( form.location_id ) } date={ form.date } start={ form.start } onChange={ update } />
						) }

						{ extrasList.length > 0 && (
							<Field label={ __( 'Extras', 'pointly-booking' ) } optional>
								<div className="pbk-inline-list">
									{ extrasList.map( ( extra ) => (
										<Checkbox
											key={ extra.id }
											label={ `${ extra.name } (+${ fmt.money( extra.price ) })` }
											checked={ form.extras.includes( extra.id ) }
											onChange={ ( e ) => update( { extras: e.target.checked ? [ ...form.extras, extra.id ] : form.extras.filter( ( x ) => x !== extra.id ) } ) }
										/>
									) ) }
								</div>
							</Field>
						) }

						<Field label={ __( 'Customer', 'pointly-booking' ) } required error={ fieldError( error, 'customer.email' ) }>
							<CustomerPicker customer={ customer } onSelect={ setCustomer } newCustomer={ newCustomer } onNewChange={ ( patch ) => setNewCustomer( ( prev ) => ( { ...prev, ...patch } ) ) } mode={ customerMode } onModeChange={ setCustomerMode } errors={ error } />
						</Field>

						<Field label={ __( 'Notes', 'pointly-booking' ) } optional>
							<Textarea value={ notes } onChange={ ( e ) => setNotes( e.target.value ) } rows={ 3 } />
						</Field>

						<div className="pbk-drawer-form__grid">
							<Field label={ __( 'Status', 'pointly-booking' ) }>
								<Select value={ form.status } onChange={ ( e ) => update( { status: e.target.value } ) } options={ STATUSES.map( ( s ) => ( { value: s, label: statusLabel( s ) } ) ) } />
							</Field>
							<Field label={ __( 'Payment status', 'pointly-booking' ) }>
								<Select value={ form.payment_status } onChange={ ( e ) => update( { payment_status: e.target.value } ) } options={ PAYMENT_STATUSES.map( ( s ) => ( { value: s, label: paymentStatusLabel( s ) } ) ) } />
							</Field>
						</div>

						<Toggle label={ __( 'Notify the customer by email', 'pointly-booking' ) } checked={ form.notify } onChange={ ( v ) => update( { notify: v } ) } />
						<Checkbox label={ __( 'Book outside availability (force)', 'pointly-booking' ) } description={ __( 'Skips the schedule and conflict checks.', 'pointly-booking' ) } checked={ form.force } onChange={ ( e ) => update( { force: e.target.checked } ) } />

						<div className="pbk-drawer-total">
							<span>{ __( 'Total', 'pointly-booking' ) }</span>
							<strong>{ fmt.money( total ) }</strong>
						</div>

						<div className="pbk-drawer-footer">
							<Button variant="ghost" onClick={ onClose }>
								{ __( 'Cancel', 'pointly-booking' ) }
							</Button>
							<Button
								variant="primary"
								loading={ saving }
								disabled={ ! form.service_id || ! form.date || ( ! form.start && ! form.force ) || ( customerMode === 'existing' ? ! customer : ! newCustomer.first_name || ! newCustomer.email ) }
								onClick={ submitNew }
							>
								{ __( 'Create booking', 'pointly-booking' ) }
							</Button>
						</div>
					</div>
			);
	} else if ( loadingDetail || ! detail ) {
		body = <Skeleton variant="text" lines={ 8 } />;
	} else {
		body = (
				<div className="pbk-booking-detail">
					<div className="pbk-booking-detail__head">
						<StatusBadge status={ detail.status } />
						{ detail.payment_status && detail.total > 0 && <Badge tone={ detail.payment_status === 'paid' ? 'success' : 'neutral' }>{ paymentStatusLabel( detail.payment_status ) }</Badge> }
						<Dropdown
							label={ __( 'Change status', 'pointly-booking' ) }
							trigger={ ( props ) => (
								<Button { ...props } size="sm" variant="secondary" iconRight="chevron-down">
									{ __( 'Change status', 'pointly-booking' ) }
								</Button>
							) }
							items={ STATUSES.filter( ( s ) => s !== detail.status ).map( ( s ) => ( { label: statusLabel( s ), onClick: () => changeStatus( s ) } ) ) }
						/>
					</div>

					<section className="pbk-booking-detail__section">
						<h3 className="pbk-booking-detail__heading">{ __( 'Customer', 'pointly-booking' ) }</h3>
						<button type="button" className="pbk-customer-picked pbk-customer-picked--link" onClick={ () => navigate( 'customers', { view: 'edit', id: detail.customer_id } ) }>
							<Avatar name={ detail.customer_name } size={ 40 } />
							<div className="pbk-customer-picked__text">
								<strong>{ detail.customer_name || __( '(no name)', 'pointly-booking' ) }</strong>
								<span className="pbk-subtle">{ detail.customer_email }</span>
								{ detail.customer_phone && <span className="pbk-subtle">{ detail.customer_phone }</span> }
							</div>
							<Icon name="chevron-right" size={ 16 } />
						</button>
					</section>

					<section className="pbk-booking-detail__section">
						<div className="pbk-booking-detail__section-head">
							<h3 className="pbk-booking-detail__heading">{ __( 'Appointment', 'pointly-booking' ) }</h3>
							{ ! rescheduling && (
								<Button size="sm" variant="ghost" icon="repeat" onClick={ startReschedule }>
									{ __( 'Reschedule', 'pointly-booking' ) }
								</Button>
							) }
						</div>
						{ rescheduling ? (
							<div className="pbk-stack">
								{ error && <Notice tone="danger">{ error.message }</Notice> }
								<Field label={ __( 'Staff', 'pointly-booking' ) } optional>
									<Select value={ reschedule.agent_id } onChange={ ( e ) => setReschedule( ( prev ) => ( { ...prev, agent_id: e.target.value, start: '' } ) ) } placeholder={ __( 'Any available', 'pointly-booking' ) } options={ ( lookups ? lookups.agents : [] ).map( ( a ) => ( { value: a.id, label: a.name } ) ) } />
								</Field>
								<ScheduleFields serviceId={ detail.service_id } agentId={ Number( reschedule.agent_id ) } date={ reschedule.date } start={ reschedule.start } excludeId={ detail.id } onChange={ ( patch ) => setReschedule( ( prev ) => ( { ...prev, ...patch } ) ) } />
								<div className="pbk-row">
									<Button variant="ghost" onClick={ () => setRescheduling( false ) }>
										{ __( 'Cancel', 'pointly-booking' ) }
									</Button>
									<Button variant="primary" loading={ saving } disabled={ ! reschedule.date || ! reschedule.start } onClick={ saveReschedule }>
										{ __( 'Save new time', 'pointly-booking' ) }
									</Button>
								</div>
							</div>
						) : (
							<div className="pbk-detail-list">
								<div className="pbk-detail-row">
									<Icon name="sparkles" size={ 18 } />
									<div className="pbk-detail-row__text">
										<span>{ detail.service_name }</span>
									</div>
								</div>
								<div className="pbk-detail-row">
									<Icon name="calendar" size={ 18 } />
									<div className="pbk-detail-row__text">
										<span>{ fmt.date( detail.start, 'long' ) }</span>
									</div>
								</div>
								<div className="pbk-detail-row">
									<Icon name="clock" size={ 18 } />
									<div className="pbk-detail-row__text">
										<span>
											{ fmt.time( detail.start.slice( 11, 16 ) ) } – { fmt.time( detail.end.slice( 11, 16 ) ) } ({ fmt.duration( detail.duration ) })
										</span>
									</div>
								</div>
								{ detail.agent_name && (
									<div className="pbk-detail-row">
										<Icon name="user" size={ 18 } />
										<div className="pbk-detail-row__text">
											<span>{ detail.agent_name }</span>
										</div>
									</div>
								) }
								{ detail.location_name && (
									<div className="pbk-detail-row">
										<Icon name="map-pin" size={ 18 } />
										<div className="pbk-detail-row__text">
											<span>{ detail.location_name }</span>
										</div>
									</div>
								) }
							</div>
						) }
					</section>

					{ ( detail.extras.length > 0 || detail.total > 0 ) && (
						<section className="pbk-booking-detail__section">
							<h3 className="pbk-booking-detail__heading">{ __( 'Payment', 'pointly-booking' ) }</h3>
							<div className="pbk-summary__prices pbk-summary__prices--flat">
								{ detail.extras.map( ( extra ) => (
									<div key={ extra.id } className="pbk-summary__price">
										<dt>{ extra.name || __( 'Extra', 'pointly-booking' ) }</dt>
										<dd>{ fmt.money( extra.price ) }</dd>
									</div>
								) ) }
								{ detail.discount > 0 && (
									<div className="pbk-summary__price is-discount">
										<dt>{ __( 'Discount', 'pointly-booking' ) } { detail.promo_code && `(${ detail.promo_code })` }</dt>
										<dd>−{ fmt.money( detail.discount ) }</dd>
									</div>
								) }
								<div className="pbk-summary__price is-total">
									<dt>{ __( 'Total', 'pointly-booking' ) }</dt>
									<dd>{ fmt.money( detail.total ) }</dd>
								</div>
							</div>
							<div className="pbk-drawer-form__grid">
								<Field label={ __( 'Payment method', 'pointly-booking' ) }>
									<Input value={ detail.payment_method } disabled />
								</Field>
								<Field label={ __( 'Payment status', 'pointly-booking' ) }>
									<Select value={ detail.payment_status } onChange={ ( e ) => changePaymentStatus( e.target.value ) } options={ PAYMENT_STATUSES.map( ( s ) => ( { value: s, label: paymentStatusLabel( s ) } ) ) } />
								</Field>
							</div>
						</section>
					) }

					{ detail.responses.length > 0 && (
						<section className="pbk-booking-detail__section">
							<h3 className="pbk-booking-detail__heading">{ __( 'Form answers', 'pointly-booking' ) }</h3>
							<dl className="pbk-review__answers">
								{ detail.responses.map( ( response ) => (
									<div key={ `${ response.scope }.${ response.key }` } className="pbk-review__answer">
										<dt>{ response.label }</dt>
										<dd>{ Array.isArray( response.value ) ? response.value.join( ', ' ) : String( response.value ) }</dd>
									</div>
								) ) }
							</dl>
						</section>
					) }

					<section className="pbk-booking-detail__section">
						<h3 className="pbk-booking-detail__heading">{ __( 'Admin notes', 'pointly-booking' ) }</h3>
						<Textarea value={ notes } onChange={ ( e ) => setNotes( e.target.value ) } rows={ 3 } placeholder={ __( 'Private notes, not shown to the customer.', 'pointly-booking' ) } />
						{ notes !== ( detail.notes || '' ) && (
							<Button size="sm" variant="secondary" loading={ savingNotes } onClick={ saveNotes }>
								{ __( 'Save notes', 'pointly-booking' ) }
							</Button>
						) }
					</section>

					<section className="pbk-booking-detail__section">
						<a className="pbk-link-button" href={ detail.manage_url } target="_blank" rel="noopener noreferrer">
							<Icon name="external-link" size={ 16 } />
							{ __( 'Open the customer manage page', 'pointly-booking' ) }
						</a>
					</section>

					<div className="pbk-drawer-footer">
						<Button variant="ghost" icon="trash" className="pbk-delete-btn" onClick={ deleteBooking }>
							{ __( 'Delete booking', 'pointly-booking' ) }
						</Button>
						<Button variant="secondary" onClick={ onClose }>
							{ __( 'Close', 'pointly-booking' ) }
						</Button>
					</div>
				</div>
		);
	}

	return (
		<Drawer open={ open } onClose={ onClose } title={ title } size="md">
			{ body }
		</Drawer>
	);
}
