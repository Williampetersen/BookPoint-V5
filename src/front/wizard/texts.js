/**
 * Default wizard texts. Anything set in Booking form → Steps/Texts overrides them.
 */
import { __ } from '@wordpress/i18n';

export function stepDefaults() {
	return {
		location: {
			title: __( 'Choose a location', 'pointly-booking' ),
			subtitle: __(
				'Where would you like your appointment?',
				'pointly-booking'
			),
			short: __( 'Location', 'pointly-booking' ),
		},
		category: {
			title: __( 'What are you looking for?', 'pointly-booking' ),
			subtitle: __(
				'Pick a category to see its services.',
				'pointly-booking'
			),
			short: __( 'Category', 'pointly-booking' ),
		},
		service: {
			title: __( 'Choose a service', 'pointly-booking' ),
			subtitle: __(
				'Select what you would like to book.',
				'pointly-booking'
			),
			short: __( 'Service', 'pointly-booking' ),
		},
		extras: {
			title: __( 'Add extras', 'pointly-booking' ),
			subtitle: __(
				'Optional add-ons for your appointment.',
				'pointly-booking'
			),
			short: __( 'Extras', 'pointly-booking' ),
		},
		agents: {
			title: __( 'Who would you like to see?', 'pointly-booking' ),
			subtitle: __(
				'Pick a team member or let us choose for you.',
				'pointly-booking'
			),
			short: __( 'Staff', 'pointly-booking' ),
		},
		datetime: {
			title: __( 'Pick a date and time', 'pointly-booking' ),
			subtitle: __(
				'Only times that are still free are shown.',
				'pointly-booking'
			),
			short: __( 'Date & time', 'pointly-booking' ),
		},
		customer: {
			title: __( 'Your details', 'pointly-booking' ),
			subtitle: __(
				'We use these to confirm your booking.',
				'pointly-booking'
			),
			short: __( 'Details', 'pointly-booking' ),
		},
		payment: {
			title: __( 'How would you like to pay?', 'pointly-booking' ),
			subtitle: __( 'Choose a payment option.', 'pointly-booking' ),
			short: __( 'Payment', 'pointly-booking' ),
		},
		review: {
			title: __( 'Review and confirm', 'pointly-booking' ),
			subtitle: __(
				'Check everything before you book.',
				'pointly-booking'
			),
			short: __( 'Review', 'pointly-booking' ),
		},
		confirm: {
			title: __( 'You are booked!', 'pointly-booking' ),
			subtitle: __(
				'We have sent a confirmation to your email.',
				'pointly-booking'
			),
			short: __( 'Done', 'pointly-booking' ),
		},
	};
}

/**
 * Title/subtitle/labels for a step, merging design overrides.
 *
 * @param {Object} design Design configuration.
 * @param {string} key    Step key.
 * @return {Object} { title, subtitle, short, next, back, step }.
 */
export function stepText( design, key ) {
	const defaults = stepDefaults()[ key ] || {
		title: key,
		subtitle: '',
		short: key,
	};
	const step =
		( design.steps || [] ).find( ( item ) => item.key === key ) || {};
	const texts = design.texts || {};
	return {
		title: step.title || defaults.title,
		subtitle: step.subtitle || defaults.subtitle,
		short: step.title || defaults.short,
		next:
			step.buttonNextLabel ||
			texts.nextLabel ||
			__( 'Continue', 'pointly-booking' ),
		back:
			step.buttonBackLabel ||
			texts.backLabel ||
			__( 'Back', 'pointly-booking' ),
		step,
	};
}
