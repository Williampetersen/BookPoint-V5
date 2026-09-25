/**
 * Payment choice, review (with Edit links and promo code) and the success screen.
 */
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	RadioCards,
	Icon,
	Input,
	Button,
	Notice,
	Badge,
	StatusBadge,
} from '../../../ui';
import { useFormat } from '../context';
import { formatDuration } from '../../../shared/format';

const METHOD_ICONS = {
	cash: 'wallet',
	stripe: 'credit-card',
	paypal: 'send',
	woocommerce: 'globe',
	free: 'check-circle',
};

export function PaymentStep( { methods, value, onChange } ) {
	return (
		<RadioCards
			legend={ __( 'Payment options', 'pointly-booking' ) }
			hideLegend
			columns="1"
			value={ value }
			onChange={ onChange }
			options={ methods.map( ( method ) => ( {
				value: method.id,
				label: method.label,
				description: method.description,
				media: (
					<span className="pbk-thumb pbk-thumb--icon">
						<Icon
							name={ METHOD_ICONS[ method.id ] || 'wallet' }
							size={ 22 }
						/>
					</span>
				),
				aside: method.online ? (
					<Badge tone="brand">
						{ __( 'Online', 'pointly-booking' ) }
					</Badge>
				) : null,
			} ) ) }
		/>
	);
}

function Section( { title, onEdit, children } ) {
	return (
		<section className="pbk-review__section">
			<div className="pbk-review__head">
				<h3 className="pbk-review__title">{ title }</h3>
				{ onEdit && (
					<button
						type="button"
						className="pbk-review__edit"
						onClick={ onEdit }
					>
						<Icon name="edit" size={ 14 } />
						{ __( 'Edit', 'pointly-booking' ) }
						<span className="pbk-sr-only"> { title }</span>
					</button>
				) }
			</div>
			<div className="pbk-review__body">{ children }</div>
		</section>
	);
}

function PromoCode( { applied, message, valid, busy, onApply, onRemove } ) {
	const [ open, setOpen ] = useState( !! applied );
	const [ code, setCode ] = useState( applied || '' );
	if ( ! open ) {
		return (
			<button
				type="button"
				className="pbk-link-button"
				onClick={ () => setOpen( true ) }
			>
				<Icon name="tag" size={ 16 } />
				{ __( 'Have a promo code?', 'pointly-booking' ) }
			</button>
		);
	}
	return (
		<form
			className="pbk-promo"
			onSubmit={ ( event ) => {
				event.preventDefault();
				if ( code.trim() ) {
					onApply( code.trim() );
				}
			} }
		>
			<label className="pbk-sr-only" htmlFor="pbk-promo-code">
				{ __( 'Promo code', 'pointly-booking' ) }
			</label>
			<div className="pbk-promo__row">
				<Input
					id="pbk-promo-code"
					value={ code }
					onChange={ ( e ) =>
						setCode( e.target.value.toUpperCase() )
					}
					placeholder={ __( 'Promo code', 'pointly-booking' ) }
					autoComplete="off"
					invalid={ valid === false }
					aria-describedby={ message ? 'pbk-promo-msg' : undefined }
				/>
				{ applied && valid ? (
					<Button
						onClick={ () => {
							setCode( '' );
							onRemove();
						} }
					>
						{ __( 'Remove', 'pointly-booking' ) }
					</Button>
				) : (
					<Button type="submit" loading={ busy }>
						{ __( 'Apply', 'pointly-booking' ) }
					</Button>
				) }
			</div>
			{ message && (
				<p
					id="pbk-promo-msg"
					className={ `pbk-promo__msg ${
						valid ? 'is-ok' : 'is-error'
					}` }
					role="status"
				>
					{ message }
				</p>
			) }
		</form>
	);
}

export function ReviewStep( {
	data,
	selection,
	fields,
	price,
	methods,
	onEdit,
	promo,
	onPromo,
	onPromoRemove,
	visible,
} ) {
	const fmt = useFormat();
	const service =
		data.services.find( ( s ) => s.id === selection.serviceId ) || {};
	const agent = selection.agentId
		? data.agents.find( ( a ) => a.id === selection.agentId )
		: null;
	const location = data.locations.find(
		( l ) => l.id === selection.locationId
	);
	const extras = data.extras.filter( ( e ) =>
		selection.extras.includes( e.id )
	);
	const method = methods.find( ( m ) => m.id === selection.paymentMethod );
	const behavior = ( data.design && data.design.behavior ) || {};
	const edit = ( key ) =>
		visible.includes( key ) ? () => onEdit( key ) : undefined;

	const answers = fields
		.map( ( field ) => {
			const raw = ( selection.fields[ field.scope ] || {} )[ field.key ];
			if (
				raw === undefined ||
				raw === '' ||
				( Array.isArray( raw ) && ! raw.length )
			) {
				return null;
			}
			let text = Array.isArray( raw ) ? raw.join( ', ' ) : String( raw );
			if ( field.type === 'checkbox' && ! Array.isArray( raw ) ) {
				text = __( 'Yes', 'pointly-booking' );
			} else if ( [ 'select', 'radio' ].includes( field.type ) ) {
				const option = field.options.find(
					( o ) => String( o.value ) === text
				);
				text = option ? option.label : text;
			}
			return {
				key: `${ field.scope }.${ field.key }`,
				label: field.label,
				text,
			};
		} )
		.filter( Boolean );

	return (
		<div className="pbk-review">
			<Section
				title={ __( 'Appointment', 'pointly-booking' ) }
				onEdit={ edit( 'service' ) || edit( 'datetime' ) }
			>
				<p className="pbk-review__strong">{ service.name }</p>
				<p className="pbk-review__line">
					<Icon name="calendar" size={ 16 } />
					{ fmt.date( selection.date, 'long' ) }
				</p>
				<p className="pbk-review__line">
					<Icon name="clock" size={ 16 } />
					{ fmt.timeRange(
						selection.date,
						selection.start,
						service.duration
					) }{ ' ' }
					· { formatDuration( service.duration, fmt.durationLabels ) }
				</p>
				{ ! data.settings.no_staff && (
					<p className="pbk-review__line">
						<Icon name="user" size={ 16 } />
						{ agent
							? agent.name
							: __(
									'Any available staff member',
									'pointly-booking'
							  ) }
					</p>
				) }
				{ location && (
					<p className="pbk-review__line">
						<Icon name="map-pin" size={ 16 } />
						{ location.name }
						{ location.address && (
							<span className="pbk-subtle">
								{ ' ' }
								· { location.address }
							</span>
						) }
					</p>
				) }
				{ extras.length > 0 && (
					<p className="pbk-review__line">
						<Icon name="plus" size={ 16 } />
						{ extras.map( ( extra ) => extra.name ).join( ', ' ) }
					</p>
				) }
			</Section>

			<Section
				title={ __( 'Your details', 'pointly-booking' ) }
				onEdit={ edit( 'customer' ) }
			>
				<dl className="pbk-review__answers">
					{ answers.map( ( answer ) => (
						<div key={ answer.key } className="pbk-review__answer">
							<dt>{ answer.label }</dt>
							<dd>{ answer.text }</dd>
						</div>
					) ) }
				</dl>
			</Section>

			{ price.subtotal > 0 && (
				<Section
					title={ __( 'Payment', 'pointly-booking' ) }
					onEdit={ edit( 'payment' ) }
				>
					<dl className="pbk-summary__prices pbk-summary__prices--flat">
						<div className="pbk-summary__price">
							<dt>{ service.name }</dt>
							<dd>{ fmt.money( price.service ) }</dd>
						</div>
						{ extras.map( ( extra ) => (
							<div
								key={ extra.id }
								className="pbk-summary__price"
							>
								<dt>{ extra.name }</dt>
								<dd>{ fmt.money( extra.price ) }</dd>
							</div>
						) ) }
						{ price.discount > 0 && (
							<div className="pbk-summary__price is-discount">
								<dt>
									{ sprintf(
										/* translators: %s: promo code */
										__(
											'Promo code %s',
											'pointly-booking'
										),
										promo.applied
									) }
								</dt>
								<dd>−{ fmt.money( price.discount ) }</dd>
							</div>
						) }
						<div className="pbk-summary__price is-total">
							<dt>{ __( 'Total', 'pointly-booking' ) }</dt>
							<dd>{ fmt.money( price.total ) }</dd>
						</div>
					</dl>
					{ method && (
						<p className="pbk-review__line">
							<Icon
								name={ METHOD_ICONS[ method.id ] || 'wallet' }
								size={ 16 }
							/>
							{ method.label }
						</p>
					) }
					{ behavior.showPromoCode !== false &&
						data.settings.promo_enabled !== false && (
							<PromoCode
								applied={ promo.applied }
								message={ promo.message }
								valid={ promo.valid }
								busy={ promo.busy }
								onApply={ onPromo }
								onRemove={ onPromoRemove }
							/>
						) }
				</Section>
			) }
		</div>
	);
}

function SuccessMark() {
	return (
		<svg
			className="pbk-success__mark"
			viewBox="0 0 64 64"
			width="64"
			height="64"
			aria-hidden="true"
			focusable="false"
		>
			<circle cx="32" cy="32" r="30" className="pbk-success__ring" />
			<path d="m20 33 8 8 16-17" className="pbk-success__tick" />
		</svg>
	);
}

/**
 * Success / payment result screen.
 *
 * @param {Object} props
 * @return {*} Screen.
 */
export function DoneStep( {
	data,
	booking,
	paymentError,
	onRetryPayment,
	retrying,
	onClose,
	closeLabel,
	cancelled,
} ) {
	const fmt = useFormat();
	const texts = ( data.design && data.design.texts ) || {};
	const awaitingPayment =
		booking.status === 'pending_payment' &&
		booking.payment_status !== 'paid';
	let title =
		texts.successTitle || __( 'You are booked!', 'pointly-booking' );
	let message =
		texts.successMessage ||
		__(
			'We have emailed you a confirmation with all the details.',
			'pointly-booking'
		);
	if ( cancelled ) {
		title = __( 'Payment cancelled', 'pointly-booking' );
		message = __(
			'Your booking was not completed and the time has been released. You can start again whenever you like.',
			'pointly-booking'
		);
	} else if ( awaitingPayment ) {
		title = __( 'Almost done', 'pointly-booking' );
		message = __(
			'Your time is reserved. Complete the payment to confirm your booking.',
			'pointly-booking'
		);
	} else if ( booking.status === 'pending' ) {
		title =
			texts.successTitle || __( 'Booking received', 'pointly-booking' );
		message =
			texts.successMessage ||
			__(
				'Thanks! We will confirm your appointment soon. A summary is on its way to your inbox.',
				'pointly-booking'
			);
	}

	return (
		<div
			className={ `pbk-success ${
				cancelled || awaitingPayment ? 'is-pending' : ''
			}` }
		>
			{ ! cancelled && ! awaitingPayment ? (
				<SuccessMark />
			) : (
				<span className="pbk-success__icon">
					<Icon
						name={ cancelled ? 'x-circle' : 'hourglass' }
						size={ 28 }
					/>
				</span>
			) }
			<h2 className="pbk-success__title" tabIndex={ -1 }>
				{ title }
			</h2>
			<p className="pbk-success__message">{ message }</p>

			{ ! cancelled && (
				<div className="pbk-success__card">
					<div className="pbk-success__row">
						<span className="pbk-subtle">
							{ __( 'Booking', 'pointly-booking' ) }
						</span>
						<strong className="pbk-tabular">#{ booking.id }</strong>
						<StatusBadge
							status={ booking.status }
							label={ booking.status_label }
						/>
					</div>
					<p className="pbk-success__service">
						{ booking.service_name }
					</p>
					<p className="pbk-review__line">
						<Icon name="calendar" size={ 16 } />
						{ fmt.date( booking.start, 'long' ) }
					</p>
					<p className="pbk-review__line">
						<Icon name="clock" size={ 16 } />
						{ fmt.time( booking.start.slice( 11, 16 ) ) } –{ ' ' }
						{ fmt.time( booking.end.slice( 11, 16 ) ) } (
						{ data.settings.timezone_label })
					</p>
					{ booking.agent_name && ! data.settings.no_staff && (
						<p className="pbk-review__line">
							<Icon name="user" size={ 16 } />
							{ booking.agent_name }
						</p>
					) }
					{ booking.location_name && (
						<p className="pbk-review__line">
							<Icon name="map-pin" size={ 16 } />
							{ booking.location_name }
						</p>
					) }
					{ booking.total > 0 && (
						<p className="pbk-review__line">
							<Icon name="wallet" size={ 16 } />
							{ fmt.money( booking.total ) }
							{ booking.payment_status === 'paid' && (
								<Badge tone="success">
									{ __( 'Paid', 'pointly-booking' ) }
								</Badge>
							) }
						</p>
					) }
				</div>
			) }

			{ paymentError && (
				<Notice
					tone="danger"
					title={ __(
						'The payment could not be started',
						'pointly-booking'
					) }
				>
					{ paymentError }
				</Notice>
			) }

			<div className="pbk-success__actions">
				{ awaitingPayment && onRetryPayment && (
					<Button
						variant="primary"
						icon="credit-card"
						loading={ retrying }
						onClick={ onRetryPayment }
					>
						{ __( 'Pay now', 'pointly-booking' ) }
					</Button>
				) }
				{ ! cancelled && ! awaitingPayment && booking.ics_url && (
					<Button
						variant="primary"
						icon="calendar-plus"
						href={ booking.ics_url }
						download={ `booking-${ booking.id }.ics` }
					>
						{ __( 'Add to calendar', 'pointly-booking' ) }
					</Button>
				) }
				{ ! cancelled && booking.manage_url && (
					<Button
						icon="external-link"
						href={ booking.manage_url }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'Manage booking', 'pointly-booking' ) }
					</Button>
				) }
				{ onClose && (
					<Button variant="ghost" onClick={ onClose }>
						{ closeLabel }
					</Button>
				) }
			</div>
		</div>
	);
}
