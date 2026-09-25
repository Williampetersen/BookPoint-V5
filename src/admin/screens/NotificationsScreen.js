/**
 * Notifications: workflow list, templates, and the workflow editor drawer.
 */
import { useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Icon, Select, Badge, Skeleton, ErrorState, EmptyState } from '../../ui';
import { useResource } from '../api';
import { useAdminFormat } from '../context';
import { useRoute } from '../router';
import { PageHeader } from '../layout/PageHeader';
import WorkflowDrawer from '../notifications/WorkflowDrawer';
import TemplatesModal from '../notifications/TemplatesModal';

const EVENT_ICONS = {
	booking_created: 'calendar-check',
	booking_updated: 'repeat',
	booking_confirmed: 'check-circle',
	booking_cancelled: 'calendar-x',
	customer_created: 'user-plus',
};

export default function NotificationsScreen() {
	const fmt = useAdminFormat();
	const { query, setParams } = useRoute();
	const [ event, setEvent ] = useState( '' );
	const [ status, setStatus ] = useState( '' );
	const [ templatesOpen, setTemplatesOpen ] = useState( false );

	const { data: meta, reload: reloadMeta } = useResource( 'admin/notifications/meta' );
	const { data, loading, error, reload } = useResource( 'admin/notifications/workflows', { event, status, per_page: 100 } );

	let drawerId = null;
	if ( query.view === 'new' ) {
		drawerId = 'new';
	} else if ( query.view === 'edit' && query.id ) {
		drawerId = Number( query.id );
	}
	const closeDrawer = () => setParams( { view: null, id: null } );

	const eventLabel = useMemo( () => {
		const map = {};
		( meta ? meta.events : [] ).forEach( ( e ) => {
			map[ e.value ] = e.label;
		} );
		return map;
	}, [ meta ] );

	const reloadAll = () => {
		reload();
		reloadMeta();
	};

	if ( error && ! data ) {
		return (
			<div>
				<PageHeader title={ __( 'Notifications', 'pointly-booking' ) } />
				<ErrorState message={ error } onRetry={ reload } />
			</div>
		);
	}

	return (
		<div>
			<PageHeader
				title={ __( 'Notifications', 'pointly-booking' ) }
				description={ __( 'Automatic emails sent to customers and your team.', 'pointly-booking' ) }
				actions={
					<>
						<Button variant="secondary" icon="file-text" onClick={ () => setTemplatesOpen( true ) }>
							{ __( 'Add from template', 'pointly-booking' ) }
						</Button>
						<Button variant="primary" icon="plus" onClick={ () => setParams( { view: 'new' } ) }>
							{ __( 'New notification', 'pointly-booking' ) }
						</Button>
					</>
				}
			/>

			<div className="pbk-toolbar">
				<Select value={ event } onChange={ ( e ) => setEvent( e.target.value ) } placeholder={ __( 'All events', 'pointly-booking' ) } options={ meta ? meta.events : [] } />
				<Select
					value={ status }
					onChange={ ( e ) => setStatus( e.target.value ) }
					placeholder={ __( 'All statuses', 'pointly-booking' ) }
					options={ [ { value: 'active', label: __( 'Active', 'pointly-booking' ) }, { value: 'disabled', label: __( 'Disabled', 'pointly-booking' ) } ] }
				/>
			</div>

			{ loading && ! data && <Skeleton variant="block" height={ 200 } /> }

			{ data && data.items.length === 0 && (
				<EmptyState
					icon="bell"
					title={ __( 'No notifications yet.', 'pointly-booking' ) }
					description={ __( 'Add one from a template, or build your own.', 'pointly-booking' ) }
					action={
						<Button variant="primary" onClick={ () => setTemplatesOpen( true ) }>
							{ __( 'Add from template', 'pointly-booking' ) }
						</Button>
					}
				/>
			) }

			{ data && data.items.length > 0 && (
				<div className="pbk-reorder-list">
					{ data.items.map( ( w ) => (
						<div key={ w.id } className={ `pbk-workflow-row ${ w.status !== 'active' ? 'is-disabled' : '' }` } onClick={ () => setParams( { view: 'edit', id: w.id } ) } role="button" tabIndex={ 0 } onKeyDown={ ( e ) => e.key === 'Enter' && setParams( { view: 'edit', id: w.id } ) }>
							<span className="pbk-workflow-row__icon">
								<Icon name={ EVENT_ICONS[ w.event_key ] || 'bell' } size={ 18 } />
							</span>
							<span className="pbk-workflow-row__text">
								<span className="pbk-workflow-row__title">
									{ w.name }
									{ w.status !== 'active' && <Badge tone="neutral" size="sm">{ __( 'Disabled', 'pointly-booking' ) }</Badge> }
									{ w.is_conditional ? <Badge tone="brand" size="sm">{ __( 'Conditional', 'pointly-booking' ) }</Badge> : null }
								</span>
								<span className="pbk-workflow-row__meta">
									{ eventLabel[ w.event_key ] || w.event_key } · { sprintf( /* translators: %d: number of email actions */ __( '%d email(s)', 'pointly-booking' ), w.actions_count || 0 ) }
									{ w.last_run_at && ` · ${ sprintf( /* translators: %s: date */ __( 'Last sent %s', 'pointly-booking' ), fmt.date( w.last_run_at, 'short' ) ) }` }
								</span>
							</span>
						</div>
					) ) }
				</div>
			) }

			<WorkflowDrawer
				id={ drawerId === 'new' ? null : drawerId }
				open={ drawerId !== null }
				meta={ meta }
				onClose={ closeDrawer }
				onSaved={ reloadAll }
				onDeleted={ () => {
					closeDrawer();
					reloadAll();
				} }
			/>
			<TemplatesModal
				open={ templatesOpen }
				onClose={ () => setTemplatesOpen( false ) }
				templates={ meta ? meta.templates : [] }
				onInstalled={ () => {
					reloadAll();
				} }
			/>
		</div>
	);
}
