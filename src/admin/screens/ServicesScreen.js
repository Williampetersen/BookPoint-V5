/**
 * Services: tabs for services, categories and extras.
 */
import { __ } from '@wordpress/i18n';
import { Tabs } from '../../ui';
import { useRoute } from '../router';
import { PageHeader } from '../layout/PageHeader';
import ServicesTab from '../catalog/ServicesTab';
import CategoriesTab from '../catalog/CategoriesTab';
import ExtrasTab from '../catalog/ExtrasTab';

const TABS = [
	{ key: 'services', label: __( 'Services', 'pointly-booking' ) },
	{ key: 'categories', label: __( 'Categories', 'pointly-booking' ) },
	{ key: 'extras', label: __( 'Extras', 'pointly-booking' ) },
];

export default function ServicesScreen() {
	const { query, setParams } = useRoute();
	const tab = query.tab || 'services';

	return (
		<div>
			<PageHeader title={ __( 'Services', 'pointly-booking' ) } description={ __( 'What you offer: services, categories and optional extras.', 'pointly-booking' ) } />
			<div className="pbk-toolbar">
				<Tabs value={ tab } onChange={ ( key ) => setParams( { tab: key } ) } label={ __( 'Section', 'pointly-booking' ) } tabs={ TABS } />
			</div>
			{ tab === 'services' && <ServicesTab /> }
			{ tab === 'categories' && <CategoriesTab /> }
			{ tab === 'extras' && <ExtrasTab /> }
		</div>
	);
}
