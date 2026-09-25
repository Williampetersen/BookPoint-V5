/**
 * Staff list + create/edit drawer + working-hours modal.
 */
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Icon,
	SearchInput,
	Select,
	Badge,
	Avatar,
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
import ScheduleModal from './ScheduleModal';

function emptyForm() {
	return { first_name: '', last_name: '', email: '', phone: '', image_id: 0, image_url: '', is_active: true, service_ids: [] };
}

function StaffDrawer( { id, open, services, onClose, onSaved, onDeleted } ) {
	const isNew = ! id;
	const confirm = useConfirm();
	const toast = useToast();
	const { data: detail, loading } = useResource( ! isNew && open ? `admin/agents/${ id }` : null );
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
				first_name: detail.first_name || '',
				last_name: detail.last_name || '',
				email: detail.email || '',
				phone: detail.phone || '',
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
			const payload = { ...form };
			if ( isNew ) {
				await client().post( 'admin/agents', payload );
				toast.success( __( 'Staff member added.', 'pointly-booking' ) );
			} else {
				await client().patch( `admin/agents/${ id }`, payload );
				toast.success( __( 'Staff member updated.', 'pointly-booking' ) );
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
			title: __( 'Delete this staff member?', 'pointly-booking' ),
			message: __( 'This also removes their service assignments.', 'pointly-booking' ),
			confirmLabel: __( 'Delete', 'pointly-booking' ),
			tone: 'danger',
		} );
		if ( ! ok ) {
			return;
		}
		setDeleting( true );
		try {
			await client().del( `admin/agents/${ id }` );
			toast.success( __( 'Staff member deleted.', 'pointly-booking' ) );
			onDeleted();
		} catch ( e ) {
			toast.error( e.message );
		} finally {
			setDeleting( false );
		}
	};

	const showSkeleton = ! isNew && ( loading || ! detail );

	return (
		<Drawer open={ open } onClose={ onClose } title={ isNew ? __( 'New staff member', 'pointly-booking' ) : __( 'Edit staff member', 'pointly-booking' ) } size="md">
			{ showSkeleton ? (
				<Skeleton variant="text" lines={ 8 } />
			) : (
				<div className="pbk-drawer-form">
					{ error && <Notice tone="danger">{ error.message }</Notice> }
					<MediaPicker round imageId={ form.image_id } imageUrl={ form.image_url } title={ __( 'Select a photo', 'pointly-booking' ) } onChange={ ( imgId, url ) => update( { image_id: imgId, image_url: url } ) } />
					<div className="pbk-drawer-form__grid">
						<Field label={ __( 'First name', 'pointly-booking' ) } required error={ fieldError( error, 'first_name' ) }>
							<Input value={ form.first_name } onChange={ ( e ) => update( { first_name: e.target.value } ) } />
						</Field>
						<Field label={ __( 'Last name', 'pointly-booking' ) } optional>
							<Input value={ form.last_name } onChange={ ( e ) => update( { last_name: e.target.value } ) } />
						</Field>
						<Field label={ __( 'Email', 'pointly-booking' ) } optional error={ fieldError( error, 'email' ) }>
							<Input type="email" value={ form.email } onChange={ ( e ) => update( { email: e.target.value } ) } />
						</Field>
						<Field label={ __( 'Phone', 'pointly-booking' ) } optional>
							<Input type="tel" value={ form.phone } onChange={ ( e ) => update( { phone: e.target.value } ) } />
						</Field>
					</div>
					<Toggle label={ __( 'Active', 'pointly-booking' ) } description={ __( 'Inactive staff are hidden from the booking wizard.', 'pointly-booking' ) } checked={ form.is_active } onChange={ ( v ) => update( { is_active: v } ) } />

					{ services.length > 0 && (
						<Field label={ __( 'Services they perform', 'pointly-booking' ) } optional>
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
						<Button variant="primary" loading={ saving } disabled={ ! form.first_name } onClick={ submit }>
							{ isNew ? __( 'Add staff member', 'pointly-booking' ) : __( 'Save changes', 'pointly-booking' ) }
						</Button>
					</div>
				</div>
			) }
		</Drawer>
	);
}

export default function StaffTab() {
	const { data: agents, loading, error, reload } = useResource( 'admin/agents' );
	const { data: services } = useResource( 'admin/services' );
	const [ search, setSearch ] = useState( '' );
	const [ status, setStatus ] = useState( '' );
	const [ drawerId, setDrawerId ] = useState( null );
	const [ scheduleId, setScheduleId ] = useState( null );

	const list = useMemo( () => {
		let items = agents || [];
		if ( search ) {
			const q = search.toLowerCase();
			items = items.filter( ( a ) => a.name.toLowerCase().includes( q ) || ( a.email || '' ).toLowerCase().includes( q ) );
		}
		if ( status ) {
			items = items.filter( ( a ) => ( status === 'active' ? a.is_active : ! a.is_active ) );
		}
		return items;
	}, [ agents, search, status ] );

	if ( error && ! agents ) {
		return <ErrorState message={ error } onRetry={ reload } />;
	}

	return (
		<div>
			<div className="pbk-reorder-toolbar">
				<div className="pbk-reorder-toolbar__search">
					<SearchInput label={ __( 'Search staff', 'pointly-booking' ) } value={ search } onChange={ ( e ) => setSearch( e.target.value ) } onClear={ () => setSearch( '' ) } />
				</div>
				<Select value={ status } onChange={ ( e ) => setStatus( e.target.value ) } placeholder={ __( 'All statuses', 'pointly-booking' ) } options={ [ { value: 'active', label: __( 'Active', 'pointly-booking' ) }, { value: 'inactive', label: __( 'Inactive', 'pointly-booking' ) } ] } />
				<div className="pbk-reorder-toolbar__spacer" />
				<Button variant="primary" icon="plus" onClick={ () => setDrawerId( 'new' ) }>
					{ __( 'New staff member', 'pointly-booking' ) }
				</Button>
			</div>

			{ loading && ! agents && <Skeleton variant="block" height={ 200 } /> }

			{ agents && list.length === 0 && (
				<EmptyState icon="users" title={ __( 'No staff found.', 'pointly-booking' ) } description={ search || status ? __( 'Try a different search or filter.', 'pointly-booking' ) : __( 'Add your first staff member.', 'pointly-booking' ) } />
			) }

			{ list.length > 0 && (
				<div className="pbk-reorder-list">
					{ list.map( ( a ) => (
						<div key={ a.id } className={ `pbk-reorder-row ${ ! a.is_active ? 'is-inactive' : '' }` }>
							<Avatar name={ a.name } src={ a.image_url } size={ 40 } />
							<span className="pbk-reorder-row__text">
								<span className="pbk-reorder-row__title">
									{ a.name }
									{ ! a.is_active && <Badge tone="neutral" size="sm">{ __( 'Inactive', 'pointly-booking' ) }</Badge> }
									{ a.has_own_hours && <Badge tone="brand" size="sm">{ __( 'Custom hours', 'pointly-booking' ) }</Badge> }
								</span>
								<span className="pbk-reorder-row__meta">
									{ a.email || __( 'No email', 'pointly-booking' ) } · { sprintf( /* translators: %d: number of services */ __( '%d services', 'pointly-booking' ), a.services_count || 0 ) }
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
									items={ [
										{ label: __( 'Edit', 'pointly-booking' ), icon: 'edit', onClick: () => setDrawerId( a.id ) },
										{ label: __( 'Working hours', 'pointly-booking' ), icon: 'clock', onClick: () => setScheduleId( a.id ) },
									] }
								/>
							</span>
						</div>
					) ) }
				</div>
			) }

			<StaffDrawer
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
			<ScheduleModal id={ scheduleId } open={ scheduleId !== null } onClose={ () => setScheduleId( null ) } />
		</div>
	);
}
