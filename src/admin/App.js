/**
 * Admin app root (screens are added in phase 4).
 */
import { __ } from '@wordpress/i18n';

export default function App( { initialRoute } ) {
	return (
		<div className="pbk-admin-app" data-route={ initialRoute }>
			<p>{ __( 'BookPoint is loading…', 'pointly-booking' ) }</p>
		</div>
	);
}
