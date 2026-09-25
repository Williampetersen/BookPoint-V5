/**
 * Service categories list (reorderable) + create/edit drawer.
 */
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Icon,
	SearchInput,
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
import { MediaPicker } from '../components/MediaPicker';
import { useDragReorder } from './useDragReorder';

function emptyForm() {
	return { name: '', description: '', image_id: 0, image_url: '', is_active: true, service_ids: [] };
}

function CategoryDrawer( { id, open, services, onClose, onSaved, onDeleted } ) {
	const isNew = ! id;
	const confirm = useConfirm();
	const toast = useToast();
	const { data: detail, loading } = useResource( ! isNew && open ? `admin/categories/${ id }` : null );
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
				is_active: !! detail.is_active,
				service_ids: detail.service_ids || [],
			} );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ open, isNew, detail ] );

	const submit = async () => {
		setSaving( true );
		setError( null );
		try {
			const payload = { name: form.name, description: form.description, image_id: form.image_id, is_active: form.is_active, service_ids: form.service_ids };
			if ( isNew ) {
				await client().post( 'admin/categories', payload );
				toast.success( __( 'Category created.', 'pointly-booking' ) );
			} else {
				await client().patch( `admin/categories/${ id }`, payload );
				toast.success( __( 'Category updated.', 'pointly-booking' ) );
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
			title: __( 'Delete this category?', 'pointly-booking' ),
			message: __( 'Services keep their other categories; this only removes the relation.', 'pointly-booking' ),
			confirmLabel: __( 'Delete', 'pointly-booking' ),
			tone: 'danger',
		} );
		if ( ! ok ) {
			return;
		}
		setDeleting( true );
		try {
			await client().del( `admin/categories/${ id }` );
			toast.success( __( 'Category deleted.', 'pointly-booking' ) );
			onDeleted();
		} catch ( e ) {
			toast.error( e.message );
		} finally {
			setDeleting( false );
		}
	};

	const showSkeleton = ! isNew && ( loading || ! detail );

	return (
		<Drawer open={ open } onClose={ onClose } title={ isNew ? __( 'New category', 'pointly-booking' ) : __( 'Edit category', 'pointly-booking' ) } size="sm">
			{ showSkeleton ? (
				<Skeleton variant="text" lines={ 6 } />
			) : (
				<div className="pbk-drawer-form">
					{ error && <Notice tone="danger">{ error.message }</Notice> }
					<MediaPicker imageId={ form.image_id } imageUrl={ form.image_url } title={ __( 'Select a category image', 'pointly-booking' ) } onChange={ ( imgId, url ) => update( { image_id: imgId, image_url: url } ) } />
					<Field label={ __( 'Name', 'pointly-booking' ) } required error={ fieldError( error, 'name' ) }>
						<Input value={ form.name } onChange={ ( e ) => update( { name: e.target.value } ) } />
					</Field>
					<Field label={ __( 'Description', 'pointly-booking' ) } optional>
						<Textarea value={ form.description } onChange={ ( e ) => update( { description: e.target.value } ) } rows={ 3 } />
					</Field>
					<Toggle label={ __( 'Active', 'pointly-booking' ) } checked={ form.is_active } onChange={ ( v ) => update( { is_active: v } ) } />

					{ services.length > 0 && (
						<Field label={ __( 'Services', 'pointly-booking' ) } optional>
							<div className="pbk-checkbox-grid">
								{ services.map( ( s ) => (
									<Checkbox
										key={ s.id }
										label={ s.name }
										checked={ form.service_ids.includes( s.id ) }
										onChange={ ( e ) =>
											update( {
												service_ids: e.target.checked ? [ ...form.service_ids, s.id ] : form.service_ids.filter( ( x ) => x !== s.id ),
											} )
										}
									/>
								) ) }
							</div>
						</Field>
					) }

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
							{ isNew ? __( 'Create category', 'pointly-booking' ) : __( 'Save changes', 'pointly-booking' ) }
						</Button>
					</div>
				</div>
			) }
		</Drawer>
	);
}

export default function CategoriesTab() {
	const { data: categories, loading, error, reload, setData } = useResource( 'admin/categories' );
	const { data: services } = useResource( 'admin/services' );
	const [ search, setSearch ] = useState( '' );
	const [ drawerId, setDrawerId ] = useState( null );
	const toast = useToast();

	const list = useMemo( () => {
		let items = categories || [];
		if ( search ) {
			const q = search.toLowerCase();
			items = items.filter( ( c ) => c.name.toLowerCase().includes( q ) );
		}
		return items;
	}, [ categories, search ] );

	const canReorder = ! search;
	const { rowProps, hoverId } = useDragReorder( list, async ( ids ) => {
		setData( ids.map( ( id ) => list.find( ( c ) => c.id === id ) ) );
		try {
			await client().post( 'admin/categories/reorder', { ids } );
		} catch ( e ) {
			toast.error( e.message );
			reload();
		}
	} );

	if ( error && ! categories ) {
		return <ErrorState message={ error } onRetry={ reload } />;
	}

	return (
		<div>
			<div className="pbk-reorder-toolbar">
				<div className="pbk-reorder-toolbar__search">
					<SearchInput label={ __( 'Search categories', 'pointly-booking' ) } value={ search } onChange={ ( e ) => setSearch( e.target.value ) } onClear={ () => setSearch( '' ) } />
				</div>
				<div className="pbk-reorder-toolbar__spacer" />
				<Button variant="primary" icon="plus" onClick={ () => setDrawerId( 'new' ) }>
					{ __( 'New category', 'pointly-booking' ) }
				</Button>
			</div>

			{ loading && ! categories && <Skeleton variant="block" height={ 200 } /> }

			{ categories && list.length === 0 && (
				<EmptyState icon="layers" title={ __( 'No categories found.', 'pointly-booking' ) } description={ search ? __( 'Try a different search.', 'pointly-booking' ) : __( 'Group your services into categories.', 'pointly-booking' ) } />
			) }

			{ list.length > 0 && (
				<div className="pbk-reorder-list">
					{ list.map( ( c ) => (
						<div key={ c.id } className={ `pbk-reorder-row ${ ! c.is_active ? 'is-inactive' : '' } ${ hoverId === c.id ? 'is-drop-target' : '' }` } { ...( canReorder ? rowProps( c.id ) : {} ) }>
							{ canReorder && (
								<span className="pbk-reorder-row__handle">
									<Icon name="grip" size={ 18 } />
								</span>
							) }
							<span className="pbk-reorder-row__thumb">{ c.image_url ? <img src={ c.image_url } alt="" /> : <Icon name="layers" size={ 18 } /> }</span>
							<span className="pbk-reorder-row__text">
								<span className="pbk-reorder-row__title">
									{ c.name }
									{ ! c.is_active && <Badge tone="neutral" size="sm">{ __( 'Inactive', 'pointly-booking' ) }</Badge> }
								</span>
								<span className="pbk-reorder-row__meta">{ sprintf( /* translators: %d: number of services */ __( '%d services', 'pointly-booking' ), c.services_count || 0 ) }</span>
							</span>
							<span className="pbk-reorder-row__actions">
								<Dropdown
									label={ __( 'Row actions', 'pointly-booking' ) }
									trigger={ ( props ) => (
										<button type="button" { ...props } className="pbk-btn pbk-btn--ghost pbk-btn--sm pbk-btn--icon-only">
											<Icon name="more-horizontal" size={ 18 } />
										</button>
									) }
									items={ [ { label: __( 'Edit', 'pointly-booking' ), icon: 'edit', onClick: () => setDrawerId( c.id ) } ] }
								/>
							</span>
						</div>
					) ) }
				</div>
			) }

			<CategoryDrawer
				id={ drawerId === 'new' ? null : drawerId }
				open={ drawerId !== null }
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
