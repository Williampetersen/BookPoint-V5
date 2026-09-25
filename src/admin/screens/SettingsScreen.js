/**
 * Settings: general, payments, schedule, holidays, promo codes, form fields, audit log, tools.
 */
import { __ } from '@wordpress/i18n';
import { Tabs } from '../../ui';
import { useRoute } from '../router';
import { PageHeader } from '../layout/PageHeader';
import GeneralTab from '../settings/GeneralTab';
import PaymentsTab from '../settings/PaymentsTab';
import ScheduleTab from '../settings/ScheduleTab';
import HolidaysTab from '../settings/HolidaysTab';
import PromoCodesTab from '../settings/PromoCodesTab';
import FormFieldsTab from '../settings/FormFieldsTab';
import AuditLogTab from '../settings/AuditLogTab';
import ToolsTab from '../settings/ToolsTab';
import '../settings/settings.css';

const TABS = [
	{ key: 'general', label: __( 'General', 'pointly-booking' ) },
	{ key: 'payments', label: __( 'Payments', 'pointly-booking' ) },
	{ key: 'schedule', label: __( 'Schedule', 'pointly-booking' ) },
	{ key: 'holidays', label: __( 'Holidays', 'pointly-booking' ) },
	{ key: 'promo_codes', label: __( 'Promo codes', 'pointly-booking' ) },
	{ key: 'form_fields', label: __( 'Form fields', 'pointly-booking' ) },
	{ key: 'audit_log', label: __( 'Activity log', 'pointly-booking' ) },
	{ key: 'tools', label: __( 'Tools', 'pointly-booking' ) },
];

export default function SettingsScreen() {
	const { query, setParams } = useRoute();
	const tab = query.tab || 'general';

	return (
		<div>
			<PageHeader title={ __( 'Settings', 'pointly-booking' ) } description={ __( 'Business details, payments, schedule and system tools.', 'pointly-booking' ) } />
			<div className="pbk-toolbar">
				<Tabs value={ tab } onChange={ ( key ) => setParams( { tab: key } ) } label={ __( 'Section', 'pointly-booking' ) } tabs={ TABS } />
			</div>
			{ tab === 'general' && <GeneralTab /> }
			{ tab === 'payments' && <PaymentsTab /> }
			{ tab === 'schedule' && <ScheduleTab /> }
			{ tab === 'holidays' && <HolidaysTab /> }
			{ tab === 'promo_codes' && <PromoCodesTab /> }
			{ tab === 'form_fields' && <FormFieldsTab /> }
			{ tab === 'audit_log' && <AuditLogTab /> }
			{ tab === 'tools' && <ToolsTab /> }
		</div>
	);
}
