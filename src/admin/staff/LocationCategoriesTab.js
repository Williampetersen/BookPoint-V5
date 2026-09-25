/**
 * Location categories list + create/edit drawer.
 */
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Icon, Drawer, Field, Input, Dropdown, Skeleton, ErrorState, EmptyState, Notice, useConfirm, useToast } from '../../ui';
import { useResource, client, fieldError } from '../api';
import { MediaPicker } from '../components/MediaPicker';

function CategoryDrawer( { id, open, onClose, onSaved, onDeleted } ) {
	const isNew = ! id;
	const confirm = useConfirm();
	const toast = useToast();
	const [ form, setForm ] = useState( { name: '', image_id: 0, image_url: '' } );
	const [ saving, setSaving ] = useState( false );
	const [ deleting, setDeleting ] = useState( false );
	const [ error, setError ] = useState( null );
	const update = ( patch ) => setForm( ( prev ) => ( { ...prev, ...patch } ) );

	useEffect( () => {
		if ( ! open ) {
			return;
		}
		setError( null );
		if ( isNew ) {
			setForm( { name: '', image_id: 0, image_url: '' } );
		}
	}, [ open, isNew ] );

	const submit = async () => {
		setSaving( true );
		setError( null );
		try {
			const payload = { name: form.name, image_id: form.image_id };
			if ( isNew ) {
				await client().post( 'admin/location-categories', payload );
				toast.success( __( 'Category created.', 'pointly-booking' ) );
			} else {
				await client().patch( `admin/location-categories/${ id }`, payload );
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
		const ok = await confirm( { title: __( 'Delete this category?', 'pointly-booking' ), confirmLabel: __( 'Delete', 'pointly-booking' ), tone: 'danger' } );
		if ( ! ok ) {
			return;
		}
		setDeleting( true );
		try {
			await client().del( `admin/location-categories/${ id }` );
			toast.success( __( 'Category deleted.', 'pointly-booking' ) );
			onDeleted();
		} catch ( e ) {
			toast.error( e.message );
		} finally {
			setDeleting( false );
		}
	};

	return (
		<Drawer open={ open } onClose={ onClose } title={ isNew ? __( 'New location category', 'pointly-booking' ) : __( 'Edit location category', 'pointly-booking' ) } size="sm">
			<div className="pbk-drawer-form">
				{ error && <Notice tone="danger">{ error.message }</Notice> }
				<MediaPicker imageId={ form.image_id } imageUrl={ form.image_url } title={ __( 'Select an image', 'pointly-booking' ) } onChange={ ( imgId, url ) => update( { image_id: imgId, image_url: url } ) } />
				<Field label={ __( 'Name', 'pointly-booking' ) } required error={ fieldError( error, 'name' ) }>
					<Input value={ form.name } onChange={ ( e ) => update( { name: e.target.value } ) } />
				</Field>
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
		</Drawer>
	);
}

export default function LocationCategoriesTab() {
	const { data: categories, loading, error, reload } = useResource( 'admin/location-categories' );
	const [ drawerId, setDrawerId ] = useState( null );

	if ( error && ! categories ) {
		return <ErrorState message={ error } onRetry={ reload } />;
	}

	return (
		<div>
			<div className="pbk-reorder-toolbar">
				<div className="pbk-reorder-toolbar__spacer" />
				<Button variant="primary" icon="plus" onClick={ () => setDrawerId( 'new' ) }>
					{ __( 'New category', 'pointly-booking' ) }
				</Button>
			</div>

			{ loading && ! categories && <Skeleton variant="block" height={ 200 } /> }

			{ categories && categories.length === 0 && <EmptyState icon="layers" title={ __( 'No location categories yet.', 'pointly-booking' ) } /> }

			{ categories && categories.length > 0 && (
				<div className="pbk-reorder-list">
					{ categories.map( ( c ) => (
						<div key={ c.id } className="pbk-reorder-row">
							<span className="pbk-reorder-row__thumb">{ c.image_url ? <img src={ c.image_url } alt="" /> : <Icon name="layers" size={ 18 } /> }</span>
							<span className="pbk-reorder-row__text">
								<span className="pbk-reorder-row__title">{ c.name }</span>
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
