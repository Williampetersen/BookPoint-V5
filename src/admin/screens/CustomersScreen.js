/**
 * Customers: search, sort, pagination, CSV export/import, and the customer drawer.
 */
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { SearchInput, Select, Table, Pagination, Avatar, Button, Dropdown, Icon, ErrorState, useToast } from '../../ui';
import { useResource, useDebouncedValue } from '../api';
import { useAdminConfig, useAdminFormat } from '../context';
import { useRoute } from '../router';
import { PageHeader } from '../layout/PageHeader';
import CustomerDrawer from '../customers/CustomerDrawer';
import ImportModal from '../customers/ImportModal';

export default function CustomersScreen() {
	const config = useAdminConfig();
	const fmt = useAdminFormat();
	const toast = useToast();
	const { query, setParams } = useRoute();

	const [ search, setSearch ] = useState( '' );
	const debouncedSearch = useDebouncedValue( search, 350 );
	const [ sort, setSort ] = useState( 'latest' );
	const [ page, setPage ] = useState( 1 );
	const [ importOpen, setImportOpen ] = useState( false );

	const params = { search: debouncedSearch, sort, page, per_page: 20 };
	const { data, loading, error, reload } = useResource( 'admin/customers', params );

	let drawerId = null;
	if ( query.view === 'new' ) {
		drawerId = 'new';
	} else if ( query.view === 'edit' && query.id ) {
		drawerId = Number( query.id );
	}
	const closeDrawer = () => setParams( { view: null, id: null } );

	useEffect( () => {
		if ( ! query.pbk_notice ) {
			return;
		}
		if ( 'imported' === query.pbk_notice ) {
			toast.success(
				sprintf(
					/* translators: 1: created, 2: updated, 3: skipped */
					__( 'Import finished: %1$d created, %2$d updated, %3$d skipped.', 'pointly-booking' ),
					Number( query.created || 0 ),
					Number( query.updated || 0 ),
					Number( query.skipped || 0 )
				)
			);
		} else if ( 'import_error' === query.pbk_notice ) {
			toast.error( __( 'The CSV file could not be imported.', 'pointly-booking' ) );
		}
		setParams( { pbk_notice: null, created: null, updated: null, skipped: null }, { replace: true } );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const exportUrl = `${ config.adminPostUrl }?action=pointlybooking_admin_customers_export_csv&_wpnonce=${ encodeURIComponent( config.adminNonce ) }`;

	const columns = [
		{
			key: 'customer',
			header: __( 'Customer', 'pointly-booking' ),
			primary: true,
			render: ( row ) => (
				<span className="pbk-avatar-cell">
					<Avatar name={ row.name } size={ 32 } />
					<span className="pbk-avatar-cell__text">
						<span className="pbk-avatar-cell__name">{ row.name || __( '(no name)', 'pointly-booking' ) }</span>
						<span className="pbk-avatar-cell__meta">{ row.email }</span>
					</span>
				</span>
			),
		},
		{ key: 'phone', header: __( 'Phone', 'pointly-booking' ), hideOnMobile: true, render: ( row ) => row.phone || '—' },
		{ key: 'bookings', header: __( 'Bookings', 'pointly-booking' ), align: 'end', render: ( row ) => row.bookings_count || 0 },
		{ key: 'spent', header: __( 'Total spent', 'pointly-booking' ), align: 'end', render: ( row ) => fmt.money( row.total_spent || 0 ) },
		{ key: 'last', header: __( 'Last booking', 'pointly-booking' ), hideOnMobile: true, render: ( row ) => ( row.last_booking ? fmt.date( row.last_booking, 'short' ) : '—' ) },
		{
			key: 'actions',
			header: '',
			align: 'end',
			render: ( row ) => (
				<Dropdown
					label={ __( 'Row actions', 'pointly-booking' ) }
					trigger={ ( props ) => (
						<button type="button" { ...props } className="pbk-btn pbk-btn--ghost pbk-btn--sm pbk-btn--icon-only">
							<Icon name="more-horizontal" size={ 18 } />
						</button>
					) }
					items={ [ { label: __( 'View / edit', 'pointly-booking' ), icon: 'edit', onClick: () => setParams( { view: 'edit', id: row.id } ) } ] }
				/>
			),
		},
	];

	if ( error && ! data ) {
		return (
			<div>
				<PageHeader title={ __( 'Customers', 'pointly-booking' ) } />
				<ErrorState message={ error } onRetry={ reload } />
			</div>
		);
	}

	return (
		<div>
			<PageHeader
				title={ __( 'Customers', 'pointly-booking' ) }
				description={ __( 'Everyone who has booked with you.', 'pointly-booking' ) }
				actions={
					<>
						<Button variant="secondary" icon="download" href={ exportUrl }>
							{ __( 'Export CSV', 'pointly-booking' ) }
						</Button>
						<Button variant="secondary" icon="upload" onClick={ () => setImportOpen( true ) }>
							{ __( 'Import CSV', 'pointly-booking' ) }
						</Button>
						<Button variant="primary" icon="plus" onClick={ () => setParams( { view: 'new' } ) }>
							{ __( 'New customer', 'pointly-booking' ) }
						</Button>
					</>
				}
			/>

			<div className="pbk-toolbar">
				<div className="pbk-toolbar__search">
					<SearchInput label={ __( 'Search customers', 'pointly-booking' ) } value={ search } onChange={ ( e ) => { setSearch( e.target.value ); setPage( 1 ); } } onClear={ () => setSearch( '' ) } />
				</div>
				<Select
					value={ sort }
					onChange={ ( e ) => setSort( e.target.value ) }
					options={ [
						{ value: 'latest', label: __( 'Newest first', 'pointly-booking' ) },
						{ value: 'earliest', label: __( 'Oldest first', 'pointly-booking' ) },
					] }
				/>
			</div>

			<Table
				caption={ __( 'Customers', 'pointly-booking' ) }
				columns={ columns }
				rows={ data ? data.items : [] }
				loading={ loading }
				onRowClick={ ( row ) => setParams( { view: 'edit', id: row.id } ) }
				empty={ <p className="pbk-table__empty">{ debouncedSearch ? __( 'No customers match your search.', 'pointly-booking' ) : __( 'No customers yet.', 'pointly-booking' ) }</p> }
			/>
			{ data && <Pagination page={ page } perPage={ 20 } total={ data.total } onChange={ setPage } /> }
			{ data && data.total > 0 && (
				<p className="pbk-subtle pbk-table-summary">
					{ sprintf(
						/* translators: %d: total customers */
						__( '%d customers total', 'pointly-booking' ),
						data.total
					) }
				</p>
			) }

			<CustomerDrawer
				id={ drawerId === 'new' ? null : drawerId }
				open={ drawerId !== null }
				onClose={ closeDrawer }
				onSaved={ reload }
				onDeleted={ () => {
					closeDrawer();
					reload();
				} }
			/>
			<ImportModal open={ importOpen } onClose={ () => setImportOpen( false ) } />
		</div>
	);
}
