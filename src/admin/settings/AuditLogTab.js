/**
 * Settings → Audit log: filters, pagination, detail panel, export and clear.
 */
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Select, SearchInput, Pagination, Badge, Drawer, Skeleton, ErrorState, EmptyState, useConfirm, useToast } from '../../ui';
import { useResource, useDebouncedValue, client } from '../api';
import { useAdminFormat } from '../context';
import { useRoute } from '../router';

const ACTOR_TONES = { admin: 'brand', customer: 'info', system: 'neutral' };

export default function AuditLogTab() {
	const fmt = useAdminFormat();
	const toast = useToast();
	const confirm = useConfirm();
	const { navigate } = useRoute();
	const [ search, setSearch ] = useState( '' );
	const debouncedSearch = useDebouncedValue( search, 350 );
	const [ event, setEvent ] = useState( '' );
	const [ actorType, setActorType ] = useState( '' );
	const [ dateFrom, setDateFrom ] = useState( '' );
	const [ dateTo, setDateTo ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ perPage, setPerPage ] = useState( 20 );
	const [ selected, setSelected ] = useState( null );
	const [ clearing, setClearing ] = useState( false );
	const [ exporting, setExporting ] = useState( false );

	const { data: meta } = useResource( 'admin/audit-logs/meta' );
	const params = { search: debouncedSearch, event, actor_type: actorType, date_from: dateFrom, date_to: dateTo, page, per_page: perPage };
	const { data, loading, error, reload } = useResource( 'admin/audit-logs', params );

	const exportCsv = async () => {
		setExporting( true );
		try {
			const result = await client().get( 'admin/audit-logs/export', params );
			const blob = new Blob( [ result.content ], { type: result.mime } );
			const url = URL.createObjectURL( blob );
			const a = document.createElement( 'a' );
			a.href = url;
			a.download = result.filename;
			a.click();
			URL.revokeObjectURL( url );
		} catch ( e ) {
			toast.error( e.message );
		} finally {
			setExporting( false );
		}
	};

	const clearAll = async () => {
		const ok = await confirm( { title: __( 'Clear the entire activity log?', 'pointly-booking' ), message: __( 'This permanently deletes every logged event.', 'pointly-booking' ), confirmLabel: __( 'Clear log', 'pointly-booking' ), tone: 'danger' } );
		if ( ! ok ) {
			return;
		}
		setClearing( true );
		try {
			await client().post( 'admin/audit-logs/clear' );
			toast.success( __( 'Activity log cleared.', 'pointly-booking' ) );
			reload();
		} catch ( e ) {
			toast.error( e.message );
		} finally {
			setClearing( false );
		}
	};

	if ( error && ! data ) {
		return <ErrorState message={ error } onRetry={ reload } />;
	}

	return (
		<div>
			<div className="pbk-audit-toolbar">
				<div className="pbk-toolbar__search">
					<SearchInput label={ __( 'Search', 'pointly-booking' ) } value={ search } onChange={ ( e ) => { setSearch( e.target.value ); setPage( 1 ); } } onClear={ () => setSearch( '' ) } />
				</div>
				<Select value={ event } onChange={ ( e ) => { setEvent( e.target.value ); setPage( 1 ); } } placeholder={ __( 'All events', 'pointly-booking' ) } options={ ( meta ? meta.events : [] ).map( ( e ) => ( { value: e, label: e } ) ) } />
				<Select
					value={ actorType }
					onChange={ ( e ) => { setActorType( e.target.value ); setPage( 1 ); } }
					placeholder={ __( 'All actors', 'pointly-booking' ) }
					options={ ( meta ? meta.actor_types : [] ).map( ( a ) => ( { value: a, label: a } ) ) }
				/>
				<input type="date" className="pbk-input" aria-label={ __( 'From date', 'pointly-booking' ) } value={ dateFrom } onChange={ ( e ) => { setDateFrom( e.target.value ); setPage( 1 ); } } />
				<input type="date" className="pbk-input" aria-label={ __( 'To date', 'pointly-booking' ) } value={ dateTo } onChange={ ( e ) => { setDateTo( e.target.value ); setPage( 1 ); } } />
				<Select
					aria-label={ __( 'Rows per page', 'pointly-booking' ) }
					value={ String( perPage ) }
					onChange={ ( e ) => { setPerPage( Number( e.target.value ) ); setPage( 1 ); } }
					options={ [
						{ value: '20', label: sprintf( /* translators: %d: number of rows */ __( '%d / page', 'pointly-booking' ), 20 ) },
						{ value: '50', label: sprintf( /* translators: %d: number of rows */ __( '%d / page', 'pointly-booking' ), 50 ) },
						{ value: '100', label: sprintf( /* translators: %d: number of rows */ __( '%d / page', 'pointly-booking' ), 100 ) },
					] }
				/>
				<span className="pbk-spacer" />
				<Button size="sm" variant="secondary" icon="download" loading={ exporting } onClick={ exportCsv }>
					{ __( 'Export CSV', 'pointly-booking' ) }
				</Button>
				<Button size="sm" variant="ghost" icon="trash" loading={ clearing } onClick={ clearAll }>
					{ __( 'Clear log', 'pointly-booking' ) }
				</Button>
			</div>

			{ loading && ! data && <Skeleton variant="block" height={ 240 } /> }
			{ data && data.items.length === 0 && <EmptyState icon="activity" title={ __( 'No activity recorded.', 'pointly-booking' ) } /> }

			{ data && data.items.length > 0 && (
				<div className="pbk-reorder-list">
					{ data.items.map( ( row ) => (
						<button key={ row.id } type="button" className="pbk-audit-row" onClick={ () => setSelected( row ) }>
							<span>{ fmt.date( row.created_at, 'short' ) } { fmt.time( row.created_at.slice( 11, 16 ) ) }</span>
							<span>{ row.event }</span>
							<Badge tone={ ACTOR_TONES[ row.actor_type ] || 'neutral' } size="sm">{ row.actor_type }</Badge>
							<span className="pbk-subtle">{ row.customer_name || row.actor_name || '—' }</span>
						</button>
					) ) }
				</div>
			) }

			{ data && <Pagination page={ page } perPage={ perPage } total={ data.total } onChange={ setPage } /> }
			{ data && data.total > 0 && (
				<p className="pbk-subtle pbk-table-summary">
					{ sprintf( /* translators: %d: total events */ __( '%d events total', 'pointly-booking' ), data.total ) }
				</p>
			) }

			<Drawer open={ !! selected } onClose={ () => setSelected( null ) } title={ selected ? selected.event : '' } size="sm">
				{ selected && (
					<div className="pbk-stack">
						<div className="pbk-detail-list">
							<div className="pbk-detail-row">
								<div className="pbk-detail-row__text">
									<span className="pbk-detail-row__label">{ __( 'When', 'pointly-booking' ) }</span>
									<span>{ fmt.date( selected.created_at, 'long' ) } { fmt.time( selected.created_at.slice( 11, 16 ) ) }</span>
								</div>
							</div>
							<div className="pbk-detail-row">
								<div className="pbk-detail-row__text">
									<span className="pbk-detail-row__label">{ __( 'Actor', 'pointly-booking' ) }</span>
									<span>
										{ selected.actor_type } { selected.actor_name ? `(${ selected.actor_name })` : '' } { selected.actor_ip ? `· ${ selected.actor_ip }` : '' }
									</span>
								</div>
							</div>
							{ !! selected.booking_id && (
								<div className="pbk-detail-row">
									<div className="pbk-detail-row__text">
										<span className="pbk-detail-row__label">{ __( 'Booking', 'pointly-booking' ) }</span>
										<button type="button" className="pbk-link-button" onClick={ () => navigate( 'bookings', { view: 'edit', id: selected.booking_id } ) }>
											#{ selected.booking_id }
										</button>
									</div>
								</div>
							) }
							{ !! selected.customer_id && (
								<div className="pbk-detail-row">
									<div className="pbk-detail-row__text">
										<span className="pbk-detail-row__label">{ __( 'Customer', 'pointly-booking' ) }</span>
										<button type="button" className="pbk-link-button" onClick={ () => navigate( 'customers', { view: 'edit', id: selected.customer_id } ) }>
											{ selected.customer_name || `#${ selected.customer_id }` }
										</button>
									</div>
								</div>
							) }
						</div>
						{ selected.meta_data && Object.keys( selected.meta_data ).length > 0 && (
							<div>
								<h3 className="pbk-booking-detail__heading">{ __( 'Details', 'pointly-booking' ) }</h3>
								<pre className="pbk-audit-meta">{ JSON.stringify( selected.meta_data, null, 2 ) }</pre>
							</div>
						) }
					</div>
				) }
			</Drawer>
		</div>
	);
}
