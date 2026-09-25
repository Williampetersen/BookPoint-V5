/**
 * Locations: tabs for locations and their categories.
 */
import { __ } from '@wordpress/i18n';
import { Tabs } from '../../ui';
import { useRoute } from '../router';
import { PageHeader } from '../layout/PageHeader';
import LocationsTab from '../staff/LocationsTab';
import LocationCategoriesTab from '../staff/LocationCategoriesTab';

const TABS = [
	{ key: 'locations', label: __( 'Locations', 'pointly-booking' ) },
	{ key: 'categories', label: __( 'Categories', 'pointly-booking' ) },
];

export default function LocationsScreen() {
	const { query, setParams } = useRoute();
	const tab = query.tab || 'locations';

	return (
		<div>
			<PageHeader title={ __( 'Locations', 'pointly-booking' ) } description={ __( 'Where you take bookings, and who works where.', 'pointly-booking' ) } />
			<div className="pbk-toolbar">
				<Tabs value={ tab } onChange={ ( key ) => setParams( { tab: key } ) } label={ __( 'Section', 'pointly-booking' ) } tabs={ TABS } />
			</div>
			{ tab === 'locations' && <LocationsTab /> }
			{ tab === 'categories' && <LocationCategoriesTab /> }
		</div>
	);
}
