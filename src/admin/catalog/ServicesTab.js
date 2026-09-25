/**
 * Services list (reorderable) + create/edit drawer.
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
	Textarea,
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
import { useAdminConfig } from '../context';
import { MediaPicker } from '../components/MediaPicker';
import { WeekdayHours } from '../components/WeekdayHours';
import { useDragReorder } from './useDragReorder';

const SORTS = [
	{ value: 'custom', label: __( 'Custom order', 'pointly-booking' ) },
	{ value: 'name', label: __( 'Name', 'pointly-booking' ) },
	{ value: 'duration_minutes', label: __( 'Duration', 'pointly-booking' ) },
	{ value: 'price', label: __( 'Price', 'pointly-booking' ) },
];

function emptyForm() {
	return {
		name: '',
		description: '',
		image_id: 0,
		image_url: '',
		duration_minutes: 60,
		price: 0,
		buffer_before_minutes: 0,
		buffer_after_minutes: 0,
		capacity: 1,
		is_active: true,
		category_ids: [],
		use_global_schedule: true,
		schedule_json: '',
	};
}

function ServiceDrawer( { id, open, categories, onClose, onSaved, onDeleted } ) {
	const isNew = ! id;
	const config = useAdminConfig();
	const confirm = useConfirm();
	const toast = useToast();
	const { data: detail, loading } = useResource( ! isNew && open ? `admin/services/${ id }` : null );
	const [ form, setForm ] = useState( emptyForm() );
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
			setForm( {
				name: detail.name,
				description: detail.description || '',
				image_id: detail.image_id || 0,
				image_url: detail.image_url || '',
				duration_minutes: detail.duration_minutes,
				price: detail.price,
				buffer_before_minutes: detail.buffer_before_minutes || 0,
				buffer_after_minutes: detail.buffer_after_minutes || 0,
				capacity: detail.capacity || 1,
				is_active: !! detail.is_active,
				category_ids: detail.category_ids || [],
				use_global_schedule: detail.use_global_schedule !== 0,
				schedule_json: detail.schedule_json || '',
			} );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ open, isNew, detail ] );

	const submit = async () => {
		setSaving( true );
		setError( null );
		try {
			const payload = {
				name: form.name,
				description: form.description,
				image_id: form.image_id,
				duration_minutes: form.duration_minutes,
				price: form.price,
				buffer_before_minutes: form.buffer_before_minutes,
				buffer_after_minutes: form.buffer_after_minutes,
				capacity: form.capacity,
				is_active: form.is_active,
				category_ids: form.category_ids,
				use_global_schedule: form.use_global_schedule,
				schedule_json: form.use_global_schedule ? '' : form.schedule_json,
			};
			if ( isNew ) {
				await client().post( 'admin/services', payload );
				toast.success( __( 'Service created.', 'pointly-booking' ) );
			} else {
				await client().patch( `admin/services/${ id }`, payload );
				toast.success( __( 'Service updated.', 'pointly-booking' ) );
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
		const ok = await confirm( {
			title: __( 'Delete this service?', 'pointly-booking' ),
			message: __( 'This also removes its category, staff and extra assignments.', 'pointly-booking' ),
			confirmLabel: __( 'Delete', 'pointly-booking' ),
			tone: 'danger',
		} );
		if ( ! ok ) {
			return;
		}
		setDeleting( true );
		try {
			await client().del( `admin/services/${ id }` );
			toast.success( __( 'Service deleted.', 'pointly-booking' ) );
			onDeleted();
		} catch ( e ) {
			toast.error( e.message );
		} finally {
			setDeleting( false );
		}
	};

	const showSkeleton = ! isNew && ( loading || ! detail );

	return (
		<Drawer open={ open } onClose={ onClose } title={ isNew ? __( 'New service', 'pointly-booking' ) : __( 'Edit service', 'pointly-booking' ) } size="md">
			{ showSkeleton ? (
				<Skeleton variant="text" lines={ 8 } />
			) : (
				<div className="pbk-drawer-form">
					{ error && <Notice tone="danger">{ error.message }</Notice> }
					<MediaPicker imageId={ form.image_id } imageUrl={ form.image_url } title={ __( 'Select a service image', 'pointly-booking' ) } onChange={ ( imgId, url ) => update( { image_id: imgId, image_url: url } ) } />
					<Field label={ __( 'Name', 'pointly-booking' ) } required error={ fieldError( error, 'name' ) }>
						<Input value={ form.name } onChange={ ( e ) => update( { name: e.target.value } ) } />
					</Field>
					<Field label={ __( 'Description', 'pointly-booking' ) } optional>
						<Textarea value={ form.description } onChange={ ( e ) => update( { description: e.target.value } ) } rows={ 3 } />
					</Field>
					<div className="pbk-drawer-form__grid">
						<Field label={ __( 'Duration (minutes)', 'pointly-booking' ) } required error={ fieldError( error, 'duration_minutes' ) }>
							<Input type="number" min={ 5 } max={ 1440 } value={ form.duration_minutes } onChange={ ( e ) => update( { duration_minutes: Number( e.target.value ) } ) } />
						</Field>
						<Field label={ sprintf( /* translators: %s: currency code */ __( 'Price (%s)', 'pointly-booking' ), config.currency ) } error={ fieldError( error, 'price' ) }>
							<Input type="number" min={ 0 } step="0.01" value={ form.price } onChange={ ( e ) => update( { price: Number( e.target.value ) } ) } />
						</Field>
						<Field label={ __( 'Buffer before (min)', 'pointly-booking' ) } optional>
							<Input type="number" min={ 0 } max={ 240 } value={ form.buffer_before_minutes } onChange={ ( e ) => update( { buffer_before_minutes: Number( e.target.value ) } ) } />
						</Field>
						<Field label={ __( 'Buffer after (min)', 'pointly-booking' ) } optional>
							<Input type="number" min={ 0 } max={ 240 } value={ form.buffer_after_minutes } onChange={ ( e ) => update( { buffer_after_minutes: Number( e.target.value ) } ) } />
						</Field>
						<Field label={ __( 'Capacity', 'pointly-booking' ) } optional help={ __( 'How many customers can book the same slot.', 'pointly-booking' ) }>
							<Input type="number" min={ 1 } max={ 100 } value={ form.capacity } onChange={ ( e ) => update( { capacity: Number( e.target.value ) } ) } />
						</Field>
					</div>
					<Toggle label={ __( 'Active', 'pointly-booking' ) } checked={ form.is_active } onChange={ ( v ) => update( { is_active: v } ) } />

					{ categories.length > 0 && (
						<Field label={ __( 'Categories', 'pointly-booking' ) } optional>
							<div className="pbk-checkbox-grid">
								{ categories.map( ( c ) => (
									<Checkbox
										key={ c.id }
										label={ c.name }
										checked={ form.category_ids.includes( c.id ) }
										onChange={ ( e ) =>
											update( {
												category_ids: e.target.checked ? [ ...form.category_ids, c.id ] : form.category_ids.filter( ( x ) => x !== c.id ),
											} )
										}
									/>
								) ) }
							</div>
						</Field>
					) }

					<Toggle
						label={ __( 'Use the business hours', 'pointly-booking' ) }
						description={ __( 'Turn off to set custom hours just for this service.', 'pointly-booking' ) }
						checked={ form.use_global_schedule }
						onChange={ ( v ) => update( { use_global_schedule: v } ) }
					/>
					{ ! form.use_global_schedule && <WeekdayHours value={ form.schedule_json } onChange={ ( json ) => update( { schedule_json: json } ) } weekStartsOn={ config.weekStartsOn } /> }

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
							{ isNew ? __( 'Create service', 'pointly-booking' ) : __( 'Save changes', 'pointly-booking' ) }
						</Button>
					</div>
				</div>
			) }
		</Drawer>
	);
}

export default function ServicesTab() {
	const config = useAdminConfig();
	const { data: services, loading, error, reload, setData } = useResource( 'admin/services' );
	const { data: categories } = useResource( 'admin/categories' );
	const catMap = useMemo( () => Object.fromEntries( ( categories || [] ).map( ( c ) => [ c.id, c.name ] ) ), [ categories ] );

	const [ search, setSearch ] = useState( '' );
	const [ status, setStatus ] = useState( '' );
	const [ sortBy, setSortBy ] = useState( 'custom' );
	const [ drawerId, setDrawerId ] = useState( null );
	const toast = useToast();

	const list = useMemo( () => {
		let items = services || [];
		if ( search ) {
			const q = search.toLowerCase();
			items = items.filter( ( s ) => s.name.toLowerCase().includes( q ) );
		}
		if ( status ) {
			items = items.filter( ( s ) => ( status === 'active' ? s.is_active : ! s.is_active ) );
		}
		if ( sortBy !== 'custom' ) {
			items = [ ...items ].sort( ( a, b ) => ( a[ sortBy ] > b[ sortBy ] ? 1 : -1 ) );
		}
		return items;
	}, [ services, search, status, sortBy ] );

	const canReorder = ! search && ! status && sortBy === 'custom';
	const { rowProps, hoverId } = useDragReorder( list, async ( ids ) => {
		setData( ids.map( ( id ) => list.find( ( s ) => s.id === id ) ) );
		try {
			await client().post( 'admin/services/reorder', { ids } );
		} catch ( e ) {
			toast.error( e.message );
			reload();
		}
	} );

	if ( error && ! services ) {
		return <ErrorState message={ error } onRetry={ reload } />;
	}

	return (
		<div>
			<div className="pbk-reorder-toolbar">
				<div className="pbk-reorder-toolbar__search">
					<SearchInput label={ __( 'Search services', 'pointly-booking' ) } value={ search } onChange={ ( e ) => setSearch( e.target.value ) } onClear={ () => setSearch( '' ) } />
				</div>
				<Select value={ status } onChange={ ( e ) => setStatus( e.target.value ) } placeholder={ __( 'All statuses', 'pointly-booking' ) } options={ [ { value: 'active', label: __( 'Active', 'pointly-booking' ) }, { value: 'inactive', label: __( 'Inactive', 'pointly-booking' ) } ] } />
				<Select value={ sortBy } onChange={ ( e ) => setSortBy( e.target.value ) } options={ SORTS } />
				<div className="pbk-reorder-toolbar__spacer" />
				<Button variant="primary" icon="plus" onClick={ () => setDrawerId( 'new' ) }>
					{ __( 'New service', 'pointly-booking' ) }
				</Button>
			</div>

			{ loading && ! services && <Skeleton variant="block" height={ 200 } /> }

			{ services && list.length === 0 && (
				<EmptyState icon="sparkles" title={ __( 'No services found.', 'pointly-booking' ) } description={ search || status ? __( 'Try a different search or filter.', 'pointly-booking' ) : __( 'Add your first service to start taking bookings.', 'pointly-booking' ) } />
			) }

			{ list.length > 0 && (
				<div className="pbk-reorder-list">
					{ list.map( ( s ) => (
						<div
							key={ s.id }
							className={ `pbk-reorder-row ${ ! s.is_active ? 'is-inactive' : '' } ${ hoverId === s.id ? 'is-drop-target' : '' }` }
							{ ...( canReorder ? rowProps( s.id ) : {} ) }
						>
							{ canReorder && (
								<span className="pbk-reorder-row__handle">
									<Icon name="grip" size={ 18 } />
								</span>
							) }
							<span className="pbk-reorder-row__thumb">{ s.image_url ? <img src={ s.image_url } alt="" /> : <Icon name="sparkles" size={ 18 } /> }</span>
							<span className="pbk-reorder-row__text">
								<span className="pbk-reorder-row__title">
									{ s.name }
									{ ! s.is_active && <Badge tone="neutral" size="sm">{ __( 'Inactive', 'pointly-booking' ) }</Badge> }
								</span>
								<span className="pbk-reorder-row__meta">
									{ s.duration_minutes } { __( 'min', 'pointly-booking' ) } · { config.currency_symbol || config.currency }{ s.price } · { ( s.category_ids || [] ).map( ( id ) => catMap[ id ] ).filter( Boolean ).join( ', ' ) || __( 'Uncategorized', 'pointly-booking' ) } · { sprintf( /* translators: %d: number of bookings */ __( '%d bookings', 'pointly-booking' ), s.bookings_count || 0 ) }
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
									items={ [ { label: __( 'Edit', 'pointly-booking' ), icon: 'edit', onClick: () => setDrawerId( s.id ) } ] }
								/>
							</span>
						</div>
					) ) }
				</div>
			) }

			<ServiceDrawer
				id={ drawerId === 'new' ? null : drawerId }
				open={ drawerId !== null }
				categories={ categories || [] }
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
