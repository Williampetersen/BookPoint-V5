/**
 * Staff: list, profile drawer, service assignments and working hours.
 */
import { __ } from '@wordpress/i18n';
import { PageHeader } from '../layout/PageHeader';
import StaffTab from '../staff/StaffTab';

export default function StaffScreen() {
	return (
		<div>
			<PageHeader title={ __( 'Staff', 'pointly-booking' ) } description={ __( 'Your team, the services they perform, and their working hours.', 'pointly-booking' ) } />
			<StaffTab />
		</div>
	);
}
