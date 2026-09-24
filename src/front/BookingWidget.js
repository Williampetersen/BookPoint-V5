/**
 * Booking widget root (the wizard is added in phase 3).
 */
import { __ } from '@wordpress/i18n';

export default function BookingWidget( { options } ) {
	if ( options.display === 'inline' ) {
		return (
			<div className="pbk-wizard pbk-wizard--inline">
				{ __( 'Loading the booking form…', 'pointly-booking' ) }
			</div>
		);
	}
	return (
		<button type="button" className="pbk-launch" data-bp-open="wizard">
			{ options.label || __( 'Book now', 'pointly-booking' ) }
		</button>
	);
}
