/**
 * Pure wizard logic: which steps apply, who can be booked, prices, fields and validation.
 */
import { __ } from '@wordpress/i18n';

export const STEP_ORDER = [
	'location',
	'category',
	'service',
	'extras',
	'agents',
	'datetime',
	'customer',
	'payment',
	'review',
];

export const initialSelection = ( options = {} ) => ( {
	locationId: options.locationId || 0,
	categoryId: options.categoryId || 0,
	serviceId: options.serviceId || 0,
	extras: [],
	agentId: options.agentId ? options.agentId : null,
	date: '',
	start: '',
	fields: { customer: {}, booking: {} },
	promoCode: '',
	paymentMethod: '',
} );

/**
 * Staff who can perform the service (at the location, if one is chosen).
 *
 * @param {Object} data      Bootstrap data.
 * @param {number} serviceId Service.
 * @param {number} locationId Location.
 * @return {Array} Agents.
 */
export function candidateAgents( data, serviceId, locationId ) {
	const service = data.services.find( ( s ) => s.id === serviceId );
	if ( ! service ) {
		return [];
	}
	let ids = service.agent_ids || [];
	const location = locationId
		? data.locations.find( ( l ) => l.id === locationId )
		: null;
	if ( location && location.assignments && location.assignments.length ) {
		const allowed = location.assignments
			.filter(
				( a ) =>
					! a.services ||
					! a.services.length ||
					a.services.includes( serviceId )
			)
			.map( ( a ) => a.agent_id );
		ids = ids.filter( ( id ) => allowed.includes( id ) );
	}
	return data.agents.filter( ( agent ) => ids.includes( agent.id ) );
}

/**
 * Services bookable for the current location/category choice.
 *
 * @param {Object} data       Bootstrap.
 * @param {number} locationId Location.
 * @param {number} categoryId Category.
 * @return {Array} Services.
 */
export function availableServices( data, locationId, categoryId ) {
	return data.services.filter( ( service ) => {
		if (
			categoryId &&
			! ( service.category_ids || [] ).includes( categoryId )
		) {
			return false;
		}
		if ( locationId && ! data.settings.no_staff ) {
			return candidateAgents( data, service.id, locationId ).length > 0;
		}
		return true;
	} );
}

export function extrasFor( data, serviceId ) {
	return data.extras.filter( ( extra ) =>
		( extra.service_ids || [] ).includes( serviceId )
	);
}

/**
 * Payment methods the customer may pick for a total.
 *
 * @param {Object} data  Bootstrap.
 * @param {number} total Total.
 * @return {Array} Methods.
 */
export function paymentMethods( data, total ) {
	if ( ! data.payment || ! data.payment.enabled || total <= 0 ) {
		return [];
	}
	return ( data.payment.methods || [] ).filter(
		( method ) => method.id !== 'free'
	);
}

export function defaultMethod( data, total ) {
	const methods = paymentMethods( data, total );
	if ( ! methods.length ) {
		return total > 0 ? 'cash' : 'free';
	}
	const preferred = methods.find(
		( method ) => method.id === data.payment.default
	);
	return ( preferred || methods[ 0 ] ).id;
}

/**
 * Local price preview (the server recalculates everything on booking).
 *
 * @param {Object} data      Bootstrap.
 * @param {Object} selection Selection.
 * @param {Object} quote     Server quote (for discount), optional.
 * @return {Object} { service, extras, subtotal, discount, total }.
 */
export function priceOf( data, selection, quote ) {
	const service = data.services.find( ( s ) => s.id === selection.serviceId );
	const base = service ? Number( service.price ) || 0 : 0;
	const extras = data.extras
		.filter( ( extra ) => selection.extras.includes( extra.id ) )
		.reduce( ( sum, extra ) => sum + ( Number( extra.price ) || 0 ), 0 );
	const subtotal = Math.round( ( base + extras ) * 100 ) / 100;
	const discount =
		quote && quote.promo_valid ? Number( quote.discount ) || 0 : 0;
	return {
		service: base,
		extras,
		subtotal,
		discount,
		total: Math.max( 0, Math.round( ( subtotal - discount ) * 100 ) / 100 ),
	};
}

/**
 * Ordered list of steps that apply to this booking (the final "confirm" screen excluded).
 *
 * @param {Object} data      Bootstrap.
 * @param {Object} selection Selection.
 * @param {Object} options   Widget options (preselection).
 * @param {number} total     Current total.
 * @return {string[]} Step keys.
 */
export function visibleSteps( data, selection, options, total ) {
	const design = data.design || {};
	const configured = ( design.steps || [] )
		.map( ( step ) => step.key )
		.filter( ( key ) => STEP_ORDER.includes( key ) );
	const order = configured.length ? configured : STEP_ORDER;
	const enabled = ( key ) => {
		const step = ( design.steps || [] ).find(
			( item ) => item.key === key
		);
		return ! step || step.enabled !== false;
	};
	const preService =
		options.serviceId &&
		data.services.some( ( s ) => s.id === options.serviceId );

	const rules = {
		location: () =>
			enabled( 'location' ) &&
			data.locations.length > 1 &&
			! options.locationId,
		category: () =>
			enabled( 'category' ) &&
			data.categories.length > 1 &&
			! preService &&
			! options.categoryId,
		service: () =>
			! preService &&
			availableServices(
				data,
				selection.locationId,
				selection.categoryId
			).length !== 1,
		extras: () =>
			enabled( 'extras' ) &&
			!! selection.serviceId &&
			extrasFor( data, selection.serviceId ).length > 0,
		agents: () =>
			! data.settings.no_staff &&
			! options.agentId &&
			( ! selection.serviceId ||
				candidateAgents(
					data,
					selection.serviceId,
					selection.locationId
				).length > 1 ),
		datetime: () => true,
		customer: () => true,
		payment: () =>
			enabled( 'payment' ) && paymentMethods( data, total ).length > 1,
		review: () => true,
	};
	return order.filter( ( key ) => rules[ key ] && rules[ key ]() );
}

/**
 * Selections that follow from the context when a step is skipped.
 *
 * @param {Object} data      Bootstrap.
 * @param {Object} selection Selection.
 * @return {Object} Patch.
 */
export function autoSelect( data, selection ) {
	const patch = {};
	if ( ! selection.locationId && data.locations.length === 1 ) {
		patch.locationId = data.locations[ 0 ].id;
	}
	const locationId = patch.locationId || selection.locationId;
	if ( ! selection.serviceId ) {
		const services = availableServices(
			data,
			locationId,
			selection.categoryId
		);
		if ( services.length === 1 ) {
			patch.serviceId = services[ 0 ].id;
		}
	}
	const serviceId = patch.serviceId || selection.serviceId;
	if ( serviceId && selection.agentId === null ) {
		if ( data.settings.no_staff ) {
			patch.agentId = 0;
		} else {
			const agents = candidateAgents( data, serviceId, locationId );
			if ( agents.length === 1 ) {
				patch.agentId = agents[ 0 ].id;
			}
		}
	}
	return patch;
}

/**
 * Whether a step has what it needs to continue.
 *
 * @param {string} key       Step.
 * @param {Object} selection Selection.
 * @return {boolean} Complete.
 */
export function stepComplete( key, selection ) {
	switch ( key ) {
		case 'location':
			return !! selection.locationId;
		case 'category':
			return true;
		case 'service':
			return !! selection.serviceId;
		case 'agents':
			return selection.agentId !== null;
		case 'datetime':
			return !! selection.date && !! selection.start;
		case 'payment':
			return !! selection.paymentMethod;
		default:
			return true;
	}
}

const AUTOCOMPLETE = {
	first_name: 'given-name',
	last_name: 'family-name',
	name: 'name',
	email: 'email',
	phone: 'tel',
	address: 'street-address',
	city: 'address-level2',
	zip: 'postal-code',
	postcode: 'postal-code',
	country: 'country-name',
	company: 'organization',
};

/**
 * Fields shown on the details step, in layout order.
 *
 * @param {Object} data    Bootstrap.
 * @param {Object} options Widget options (hideNotes, requirePhone).
 * @return {Array} [ { scope, key, label, type, required, width, placeholder, options, autocomplete } ].
 */
export function detailFields( data, options ) {
	const layout = ( data.design && data.design.fieldsLayout ) || {};
	const out = [];
	[ 'customer', 'booking', 'form' ].forEach( ( scope ) => {
		const defs = ( data.fields && data.fields[ scope ] ) || [];
		const order = ( layout[ scope ] && layout[ scope ].fields ) || [];
		const sorted = [
			...order
				.map( ( entry ) =>
					defs.find( ( def ) => def.field_key === entry.id )
				)
				.filter( Boolean ),
			...defs.filter(
				( def ) =>
					! order.some( ( entry ) => entry.id === def.field_key )
			),
		];
		sorted.forEach( ( def ) => {
			const entry =
				order.find( ( item ) => item.id === def.field_key ) || {};
			if ( def.field_key === 'notes' && options.hideNotes ) {
				return;
			}
			let required =
				entry.required !== undefined
					? !! entry.required
					: !! def.is_required;
			if ( def.field_key === 'email' && scope === 'customer' ) {
				required = true;
			}
			if ( def.field_key === 'phone' && options.requirePhone ) {
				required = true;
			}
			out.push( {
				scope: scope === 'form' ? 'booking' : scope,
				key: def.field_key,
				label: def.label,
				type: def.type,
				required,
				width: entry.width === 'half' ? 'half' : 'full',
				placeholder: def.placeholder || '',
				options: def.options || [],
				autocomplete: AUTOCOMPLETE[ def.field_key ] || 'off',
			} );
		} );
	} );
	return out;
}

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
const PHONE = /^[+()0-9\s./-]{6,}$/;

/**
 * Validation message for one field value ('' when valid).
 *
 * @param {Object} field Field.
 * @param {*}      value Value.
 * @return {string} Error.
 */
export function validateField( field, value ) {
	const empty =
		value === undefined ||
		value === null ||
		( typeof value === 'string' && value.trim() === '' ) ||
		( Array.isArray( value ) && ! value.length ) ||
		( field.type === 'checkbox' && ! value );
	if ( empty ) {
		return field.required
			? __( 'This field is required.', 'pointly-booking' )
			: '';
	}
	if ( field.type === 'email' && ! EMAIL.test( String( value ).trim() ) ) {
		return __(
			'Please enter a valid email address, like name@example.com.',
			'pointly-booking'
		);
	}
	if ( field.type === 'tel' && ! PHONE.test( String( value ).trim() ) ) {
		return __( 'Please enter a valid phone number.', 'pointly-booking' );
	}
	if ( field.type === 'number' && Number.isNaN( Number( value ) ) ) {
		return __( 'Please enter a number.', 'pointly-booking' );
	}
	return '';
}

export function validateAll( fields, values ) {
	const errors = {};
	fields.forEach( ( field ) => {
		const message = validateField(
			field,
			( values[ field.scope ] || {} )[ field.key ]
		);
		if ( message ) {
			errors[ `${ field.scope }.${ field.key }` ] = message;
		}
	} );
	return errors;
}
