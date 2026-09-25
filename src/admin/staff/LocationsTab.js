/**
 * Locations list + create/edit drawer with staff assignment and custom hours.
 */
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Icon,
	SearchInput,
	Select,
	Badge,
	Drawer,
	Field,
	Input,
	Toggle,
	Checkbox,
	Dropdown,
	Skeleton,
	ErrorState,
	EmptyState,
	Notice,
	useConfirm,
	useToast,
} from '../../ui';
import { useResource, client, fieldError } from '../api';
import { MediaPicker } from '../components/MediaPicker';
import { WeekdayHours } from '../components/WeekdayHours';

function scheduleArrayToMap( schedule ) {
	const map = {};
	( schedule || [] ).forEach( ( row ) => {
		map[ String( row.day ) ] = `${ row.start }-${ row.end }`;
	} );
	return JSON.stringify( map );
}

function scheduleMapToArray( json ) {
	let map = {};
	try {
		map = JSON.parse( json || '{}' ) || {};
	} catch ( e ) {
		map = {};
	}
	const out = [];
	Object.entries( map ).forEach( ( [ day, range ] ) => {
		if ( ! range ) {
			return;
		}
		const [ start, end ] = String( range ).split( '-' );
		if ( start && end ) {
			out.push( { day: Number( day ), start, end } );
		}
	} );
	return out;
}

function emptyForm() {
	return { name: '', address: '', category_id: '', image_id: 0, image_url: '', is_active: true, use_custom_schedule: false, schedule_json: '', agent_ids: [], agent_services: {} };
}

function LocationDrawer( { id, open, categories, agents, services, onClose, onSaved, onDeleted } ) {
	const isNew = ! id;
	const confirm = useConfirm();
	const toast = useToast();
	const { data: detail, loading } = useResource( ! isNew && open ? `admin/locations/${ id }` : null );
	const [ form, setForm ] = useState( emptyForm() );
	const [ expandedAgent, setExpandedAgent ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ deleting, setDeleting ] = useState( false );
	const [ error, setError ] = useState( null );
	const update = ( patch ) => setForm( ( prev ) => ( { ...prev, ...patch } ) );

	useEffect( () => {
		if ( ! open ) {
			return;
		}
		if ( isNew ) {
			setForm( emptyForm() );
			setError( null );
		} else if ( detail ) {
			const agentServices = {};
			( detail.agents || [] ).forEach( ( a ) => {
				agentServices[ a.agent_id ] = a.services || [];
			} );
			setForm( {
				name: detail.name,
				address: detail.address || '',
				category_id: detail.category_id || '',
				image_id: detail.image_id || 0,
				image_url: detail.image_url || '',
				is_active: detail.status !== 'inactive',
				use_custom_schedule: !! detail.use_custom_schedule,
				schedule_json: scheduleArrayToMap( detail.schedule ),
				agent_ids: ( detail.agents || [] ).map( ( a ) => a.agent_id ),
				agent_services: agentServices,
			} );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ open, isNew, detail ] );

	const toggleAgent = ( agentId, checked ) => {
		update( {
			agent_ids: checked ? [ ...form.agent_ids, agentId ] : form.agent_ids.filter( ( x ) => x !== agentId ),
		} );
	};

	const toggleAgentService = ( agentId, serviceId, checked ) => {
		const current = form.agent_services[ agentId ] || [];
		const next = checked ? [ ...current, serviceId ] : current.filter( ( x ) => x !== serviceId );
		update( { agent_services: { ...form.agent_services, [ agentId ]: next } } );
	};

	const submit = async () => {
		setSaving( true );
		setError( null );
		try {
			const payload = {
				name: form.name,
				address: form.address,
				category_id: form.category_id ? Number( form.category_id ) : 0,
				image_id: form.image_id,
				is_active: form.is_active,
				use_custom_schedule: form.use_custom_schedule,
				schedule: form.use_custom_schedule ? scheduleMapToArray( form.schedule_json ) : [],
				agents: form.agent_ids.map( ( agentId ) => ( { agent_id: agentId, services: form.agent_services[ agentId ] || [] } ) ),
			};
			if ( isNew ) {
				await client().post( 'admin/locations', payload );
				toast.success( __( 'Location created.', 'pointly-booking' ) );
			} else {
				await client().patch( `admin/locations/${ id }`, payload );
				toast.success( __( 'Location updated.', 'pointly-booking' ) );
			}
			onSaved();
			onClose();
		} catch ( e ) {
			setError( e );
		} finally {
			setSaving( false );
		}
	};

	const remove = async () => {
		const ok = await confirm( { title: __( 'Delete this location?', 'pointly-booking' ), confirmLabel: __( 'Delete', 'pointly-booking' ), tone: 'danger' } );
		if ( ! ok ) {
			return;
		}
		setDeleting( true );
		try {
			await client().del( `admin/locations/${ id }` );
			toast.success( __( 'Location deleted.', 'pointly-booking' ) );
			onDeleted();
		} catch ( e ) {
			toast.error( e.message );
		} finally {
			setDeleting( false );
		}
	};

	const showSkeleton = ! isNew && ( loading || ! detail );

	return (
		<Drawer open={ open } onClose={ onClose } title={ isNew ? __( 'New location', 'pointly-booking' ) : __( 'Edit location', 'pointly-booking' ) } size="md">
			{ showSkeleton ? (
				<Skeleton variant="text" lines={ 8 } />
			) : (
				<div className="pbk-drawer-form">
					{ error && <Notice tone="danger">{ error.message }</Notice> }
					<MediaPicker imageId={ form.image_id } imageUrl={ form.image_url } title={ __( 'Select a location image', 'pointly-booking' ) } onChange={ ( imgId, url ) => update( { image_id: imgId, image_url: url } ) } />
					<Field label={ __( 'Name', 'pointly-booking' ) } required error={ fieldError( error, 'name' ) }>
						<Input value={ form.name } onChange={ ( e ) => update( { name: e.target.value } ) } />
					</Field>
					<Field label={ __( 'Address', 'pointly-booking' ) } optional>
						<Input value={ form.address } onChange={ ( e ) => update( { address: e.target.value } ) } />
					</Field>
					<Field label={ __( 'Category', 'pointly-booking' ) } optional>
						<Select value={ form.category_id } onChange={ ( e ) => update( { category_id: e.target.value } ) } placeholder={ __( 'None', 'pointly-booking' ) } options={ categories.map( ( c ) => ( { value: c.id, label: c.name } ) ) } />
					</Field>
					<Toggle label={ __( 'Active', 'pointly-booking' ) } checked={ form.is_active } onChange={ ( v ) => update( { is_active: v } ) } />

					{ agents.length > 0 && (
						<Field label={ __( 'Staff at this location', 'pointly-booking' ) } optional help={ __( 'Leave a staff member’s services unchecked to allow all of their services here.', 'pointly-booking' ) }>
							<div className="pbk-stack">
								{ agents.map( ( a ) => {
									const checked = form.agent_ids.includes( a.id );
									const theirServices = services.filter( ( s ) => ( s.agent_ids || [] ).includes( a.id ) );
									return (
										<div key={ a.id }>
											<div className="pbk-row">
												<Checkbox label={ a.name } checked={ checked } onChange={ ( e ) => toggleAgent( a.id, e.target.checked ) } />
												{ checked && theirServices.length > 0 && (
													<Button size="sm" variant="ghost" onClick={ () => setExpandedAgent( expandedAgent === a.id ? null : a.id ) }>
														{ expandedAgent === a.id ? __( 'Hide services', 'pointly-booking' ) : __( 'Limit services', 'pointly-booking' ) }
													</Button>
												) }
											</div>
											{ checked && expandedAgent === a.id && (
												<div className="pbk-checkbox-grid" style={ { marginInlineStart: 28, marginBlockStart: 6 } }>
													{ theirServices.map( ( s ) => (
														<Checkbox
															key={ s.id }
															label={ s.name }
															checked={ ( form.agent_services[ a.id ] || [] ).includes( s.id ) }
															onChange={ ( e ) => toggleAgentService( a.id, s.id, e.target.checked ) }
														/>
													) ) }
												</div>
											) }
										</div>
									);
								} ) }
							</div>
						</Field>
					) }

					<Toggle
						label={ __( 'Use custom hours', 'pointly-booking' ) }
						description={ __( 'Turn off to follow the business hours.', 'pointly-booking' ) }
						checked={ form.use_custom_schedule }
						onChange={ ( v ) => update( { use_custom_schedule: v } ) }
					/>
					{ form.use_custom_schedule && <WeekdayHours value={ form.schedule_json } onChange={ ( json ) => update( { schedule_json: json } ) } /> }

					<div className="pbk-drawer-footer">
						{ ! isNew && (
							<Button variant="ghost" className="pbk-delete-btn" icon="trash" loading={ deleting } onClick={ remove }>
								{ __( 'Delete', 'pointly-booking' ) }
							</Button>
						) }
						<Button variant="ghost" onClick={ onClose }>
							{ __( 'Cancel', 'pointly-booking' ) }
						</Button>
						<Button variant="primary" loading={ saving } disabled={ ! form.name } onClick={ submit }>
							{ isNew ? __( 'Create location', 'pointly-booking' ) : __( 'Save changes', 'pointly-booking' ) }
						</Button>
					</div>
				</div>
			) }
		</Drawer>
	);
}

export default function LocationsTab() {
	const { data: locations, loading, error, reload } = useResource( 'admin/locations' );
	const { data: categories } = useResource( 'admin/location-categories' );
	const { data: agents } = useResource( 'admin/agents' );
	const { data: services } = useResource( 'admin/services' );
	const [ search, setSearch ] = useState( '' );
	const [ status, setStatus ] = useState( '' );
	const [ category, setCategory ] = useState( '' );
	const [ drawerId, setDrawerId ] = useState( null );

	const list = useMemo( () => {
		let items = locations || [];
		if ( search ) {
			const q = search.toLowerCase();
			items = items.filter( ( l ) => l.name.toLowerCase().includes( q ) || ( l.address || '' ).toLowerCase().includes( q ) );
		}
		if ( status ) {
			items = items.filter( ( l ) => ( status === 'active' ? l.status !== 'inactive' : l.status === 'inactive' ) );
		}
		if ( category ) {
			items = items.filter( ( l ) => String( l.category_id ) === category );
		}
		return items;
	}, [ locations, search, status, category ] );

	if ( error && ! locations ) {
		return <ErrorState message={ error } onRetry={ reload } />;
	}

	return (
		<div>
			<div className="pbk-reorder-toolbar">
				<div className="pbk-reorder-toolbar__search">
					<SearchInput label={ __( 'Search locations', 'pointly-booking' ) } value={ search } onChange={ ( e ) => setSearch( e.target.value ) } onClear={ () => setSearch( '' ) } />
				</div>
				<Select value={ status } onChange={ ( e ) => setStatus( e.target.value ) } placeholder={ __( 'All statuses', 'pointly-booking' ) } options={ [ { value: 'active', label: __( 'Active', 'pointly-booking' ) }, { value: 'inactive', label: __( 'Inactive', 'pointly-booking' ) } ] } />
				{ categories && categories.length > 0 && (
					<Select value={ category } onChange={ ( e ) => setCategory( e.target.value ) } placeholder={ __( 'All categories', 'pointly-booking' ) } options={ categories.map( ( c ) => ( { value: String( c.id ), label: c.name } ) ) } />
				) }
				<div className="pbk-reorder-toolbar__spacer" />
				<Button variant="primary" icon="plus" onClick={ () => setDrawerId( 'new' ) }>
					{ __( 'New location', 'pointly-booking' ) }
				</Button>
			</div>

			{ loading && ! locations && <Skeleton variant="block" height={ 200 } /> }

			{ locations && list.length === 0 && (
				<EmptyState icon="map-pin" title={ __( 'No locations found.', 'pointly-booking' ) } description={ search || status || category ? __( 'Try a different search or filter.', 'pointly-booking' ) : __( 'Add a location if you book across multiple places.', 'pointly-booking' ) } />
			) }

			{ list.length > 0 && (
				<div className="pbk-reorder-list">
					{ list.map( ( l ) => (
						<div key={ l.id } className={ `pbk-reorder-row ${ l.status === 'inactive' ? 'is-inactive' : '' }` }>
							<span className="pbk-reorder-row__thumb">{ l.image_url ? <img src={ l.image_url } alt="" /> : <Icon name="map-pin" size={ 18 } /> }</span>
							<span className="pbk-reorder-row__text">
								<span className="pbk-reorder-row__title">
									{ l.name }
									{ l.status === 'inactive' && <Badge tone="neutral" size="sm">{ __( 'Inactive', 'pointly-booking' ) }</Badge> }
								</span>
								<span className="pbk-reorder-row__meta">
									{ l.address || __( 'No address', 'pointly-booking' ) } { l.category_name && `· ${ l.category_name }` } · { sprintf( /* translators: %d: number of staff */ __( '%d staff', 'pointly-booking' ), ( l.agents || [] ).length ) }
								</span>
							</span>
							<span className="pbk-reorder-row__actions">
								<Dropdown
									label={ __( 'Row actions', 'pointly-booking' ) }
									trigger={ ( props ) => (
										<button type="button" { ...props } className="pbk-btn pbk-btn--ghost pbk-btn--sm pbk-btn--icon-only">
											<Icon name="more-horizontal" size={ 18 } />
										</button>
									) }
									items={ [ { label: __( 'Edit', 'pointly-booking' ), icon: 'edit', onClick: () => setDrawerId( l.id ) } ] }
								/>
							</span>
						</div>
					) ) }
				</div>
			) }

			<LocationDrawer
				id={ drawerId === 'new' ? null : drawerId }
				open={ drawerId !== null }
				categories={ categories || [] }
				agents={ agents || [] }
				services={ services || [] }
				onClose={ () => setDrawerId( null ) }
				onSaved={ reload }
				onDeleted={ () => {
					setDrawerId( null );
					reload();
				} }
			/>
		</div>
	);
}
