/**
 * Bookings list: search, filters, sort, bulk actions, and the booking drawer.
 */
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { SearchInput, Table, Pagination, StatusBadge, Avatar, Button, Dropdown, Icon, ErrorState, useConfirm, useToast } from '../../ui';
import { useResource, useDebouncedValue, client } from '../api';
import { useAdminFormat } from '../context';
import { useRoute } from '../router';
import { PageHeader } from '../layout/PageHeader';
import BookingDrawer from '../bookings/BookingDrawer';
import '../bookings/bookings.css';

const STATUS_ORDER = [ 'all', 'pending', 'confirmed', 'pending_payment', 'completed', 'cancelled', 'failed_payment' ];

function statusLabel( value ) {
	const labels = {
		all: __( 'All', 'pointly-booking' ),
		pending: __( 'Pending', 'pointly-booking' ),
		confirmed: __( 'Confirmed', 'pointly-booking' ),
		completed: __( 'Completed', 'pointly-booking' ),
		cancelled: __( 'Cancelled', 'pointly-booking' ),
		pending_payment: __( 'Awaiting payment', 'pointly-booking' ),
		failed_payment: __( 'Payment failed', 'pointly-booking' ),
	};
	return labels[ value ] || value;
}

export default function BookingsScreen() {
	const { query, setParams } = useRoute();
	const fmt = useAdminFormat();
	const toast = useToast();
	const confirm = useConfirm();

	const [ search, setSearch ] = useState( '' );
	const debouncedSearch = useDebouncedValue( search, 350 );
	const [ status, setStatus ] = useState( 'all' );
	const [ dateFrom, setDateFrom ] = useState( '' );
	const [ dateTo, setDateTo ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ sort, setSort ] = useState( { key: 'start', order: 'desc' } );
	const [ selected, setSelected ] = useState( [] );

	const params = {
		search: debouncedSearch,
		status,
		date_from: dateFrom,
		date_to: dateTo,
		page,
		per_page: 20,
		orderby: sort.key,
		order: sort.order,
	};
	const { data, loading, error, reload } = useResource( 'admin/bookings', params );

	let drawerId = null;
	if ( query.view === 'new' ) {
		drawerId = 'new';
	} else if ( query.view === 'edit' && query.id ) {
		drawerId = Number( query.id );
	}
	const closeDrawer = () => setParams( { view: null, id: null } );

	const bulkAction = async ( action ) => {
		if ( action === 'delete' ) {
			const ok = await confirm( {
				title: sprintf( /* translators: %d: number of bookings */ __( 'Delete %d bookings?', 'pointly-booking' ), selected.length ),
				message: __( 'This cannot be undone.', 'pointly-booking' ),
				confirmLabel: __( 'Delete', 'pointly-booking' ),
				tone: 'danger',
			} );
			if ( ! ok ) {
				return;
			}
		}
		try {
			const result = await client().post( 'admin/bookings/bulk', { ids: selected, action } );
			toast.success( sprintf( /* translators: %d: number updated */ __( 'Updated %d bookings.', 'pointly-booking' ), result.updated ) );
			setSelected( [] );
			reload();
		} catch ( e ) {
			toast.error( e.message );
		}
	};

	const columns = [
		{
			key: 'customer',
			header: __( 'Customer', 'pointly-booking' ),
			sortable: true,
			primary: true,
			render: ( row ) => (
				<span className="pbk-avatar-cell">
					<Avatar name={ row.customer_name } size={ 32 } />
					<span className="pbk-avatar-cell__text">
						<span className="pbk-avatar-cell__name">{ row.customer_name || __( '(no name)', 'pointly-booking' ) }</span>
						<span className="pbk-avatar-cell__meta">{ row.customer_email }</span>
					</span>
				</span>
			),
		},
		{ key: 'service', header: __( 'Service', 'pointly-booking' ), sortable: true, render: ( row ) => row.service_name },
		{ key: 'agent', header: __( 'Staff', 'pointly-booking' ), hideOnMobile: true, render: ( row ) => row.agent_name || '—' },
		{
			key: 'start',
			header: __( 'Date & time', 'pointly-booking' ),
			sortable: true,
			render: ( row ) => (
				<span>
					{ fmt.date( row.start, 'short' ) }
					<br />
					<span className="pbk-subtle">{ fmt.time( row.start.slice( 11, 16 ) ) }</span>
				</span>
			),
		},
		{ key: 'status', header: __( 'Status', 'pointly-booking' ), sortable: true, render: ( row ) => <StatusBadge status={ row.status } /> },
		{ key: 'total', header: __( 'Total', 'pointly-booking' ), sortable: true, align: 'end', render: ( row ) => fmt.money( row.total ) },
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
					items={ [
						{ label: __( 'View / edit', 'pointly-booking' ), icon: 'edit', onClick: () => setParams( { view: 'edit', id: row.id } ) },
						{ divider: true },
						...( row.status !== 'confirmed' ? [ { label: __( 'Mark confirmed', 'pointly-booking' ), icon: 'check', onClick: () => rowStatus( row.id, 'confirmed' ) } ] : [] ),
						...( row.status !== 'completed' ? [ { label: __( 'Mark completed', 'pointly-booking' ), icon: 'check-circle', onClick: () => rowStatus( row.id, 'completed' ) } ] : [] ),
						...( row.status !== 'cancelled' ? [ { label: __( 'Cancel', 'pointly-booking' ), icon: 'x-circle', onClick: () => rowStatus( row.id, 'cancelled' ) } ] : [] ),
						{ divider: true },
						{ label: __( 'Delete', 'pointly-booking' ), icon: 'trash', danger: true, onClick: () => rowDelete( row.id ) },
					] }
				/>
			),
		},
	];

	const rowStatus = async ( id, next ) => {
		try {
			await client().patch( `admin/bookings/${ id }`, { status: next } );
			toast.success( __( 'Booking updated.', 'pointly-booking' ) );
			reload();
		} catch ( e ) {
			toast.error( e.message );
		}
	};

	const rowDelete = async ( id ) => {
		const ok = await confirm( { title: __( 'Delete this booking?', 'pointly-booking' ), confirmLabel: __( 'Delete', 'pointly-booking' ), tone: 'danger' } );
		if ( ! ok ) {
			return;
		}
		try {
			await client().del( `admin/bookings/${ id }` );
			toast.success( __( 'Booking deleted.', 'pointly-booking' ) );
			reload();
		} catch ( e ) {
			toast.error( e.message );
		}
	};

	if ( error && ! data ) {
		return (
			<div>
				<PageHeader title={ __( 'Bookings', 'pointly-booking' ) } />
				<ErrorState message={ error } onRetry={ reload } />
			</div>
		);
	}

	const counts = data ? data.counts : {};

	return (
		<div>
			<PageHeader
				title={ __( 'Bookings', 'pointly-booking' ) }
				description={ __( 'Every appointment booked through BookPoint.', 'pointly-booking' ) }
				actions={
					<Button variant="primary" icon="plus" onClick={ () => setParams( { view: 'new' } ) }>
						{ __( 'New booking', 'pointly-booking' ) }
					</Button>
				}
			/>

			<div className="pbk-status-pills">
				{ STATUS_ORDER.map( ( key ) => (
					<button key={ key } type="button" className={ `pbk-status-pill ${ status === key ? 'is-active' : '' }` } onClick={ () => { setStatus( key ); setPage( 1 ); } }>
						{ statusLabel( key ) }
						{ counts[ key ] !== undefined && <span className="pbk-status-pill__count">{ counts[ key ] }</span> }
					</button>
				) ) }
			</div>

			<div className="pbk-toolbar">
				<div className="pbk-toolbar__search">
					<SearchInput label={ __( 'Search bookings', 'pointly-booking' ) } value={ search } onChange={ ( e ) => { setSearch( e.target.value ); setPage( 1 ); } } onClear={ () => setSearch( '' ) } />
				</div>
				<div className="pbk-toolbar__filters">
					<input type="date" className="pbk-input" aria-label={ __( 'From date', 'pointly-booking' ) } value={ dateFrom } onChange={ ( e ) => { setDateFrom( e.target.value ); setPage( 1 ); } } />
					<input type="date" className="pbk-input" aria-label={ __( 'To date', 'pointly-booking' ) } value={ dateTo } onChange={ ( e ) => { setDateTo( e.target.value ); setPage( 1 ); } } />
					{ ( dateFrom || dateTo ) && (
						<Button size="sm" variant="ghost" onClick={ () => { setDateFrom( '' ); setDateTo( '' ); } }>
							{ __( 'Clear dates', 'pointly-booking' ) }
						</Button>
					) }
				</div>
			</div>

			<Table
				caption={ __( 'Bookings', 'pointly-booking' ) }
				columns={ columns }
				rows={ data ? data.items : [] }
				loading={ loading }
				sort={ sort }
				onSort={ ( key ) => setSort( ( prev ) => ( { key, order: prev.key === key && prev.order === 'asc' ? 'desc' : 'asc' } ) ) }
				selectable
				selected={ selected }
				onSelectionChange={ setSelected }
				onRowClick={ ( row ) => setParams( { view: 'edit', id: row.id } ) }
				bulkActions={
					<>
						<Button size="sm" variant="secondary" onClick={ () => bulkAction( 'confirmed' ) }>
							{ __( 'Confirm', 'pointly-booking' ) }
						</Button>
						<Button size="sm" variant="secondary" onClick={ () => bulkAction( 'cancelled' ) }>
							{ __( 'Cancel', 'pointly-booking' ) }
						</Button>
						<Button size="sm" variant="ghost" onClick={ () => bulkAction( 'delete' ) }>
							{ __( 'Delete', 'pointly-booking' ) }
						</Button>
					</>
				}
				empty={
					<p className="pbk-table__empty">
						{ debouncedSearch || status !== 'all' ? __( 'No bookings match your filters.', 'pointly-booking' ) : __( 'No bookings yet.', 'pointly-booking' ) }
					</p>
				}
			/>
			{ data && (
				<Pagination
					page={ page }
					perPage={ 20 }
					total={ data.total }
					onChange={ setPage }
				/>
			) }
			{ data && data.total > 0 && (
				<p className="pbk-subtle pbk-table-summary">
					{ sprintf(
						/* translators: %d: total bookings */
						_n( '%d booking total', '%d bookings total', data.total, 'pointly-booking' ),
						data.total
					) }
				</p>
			) }

			<BookingDrawer
				id={ drawerId === 'new' ? null : drawerId }
				open={ drawerId !== null }
				onClose={ closeDrawer }
				onSaved={ () => {
					reload();
				} }
				onDeleted={ () => {
					closeDrawer();
					reload();
				} }
			/>
		</div>
	);
}
