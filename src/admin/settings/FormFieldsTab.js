/**
 * Settings → Form fields: reorderable list per scope, drawer editor with a live preview, reseed.
 */
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Icon, SearchInput, Select, Field, Input, Toggle, Tabs, Drawer, Dropdown, Badge, Skeleton, ErrorState, EmptyState, Notice, useConfirm, useToast } from '../../ui';
import { useResource, client, fieldError } from '../api';
import { useDragReorder } from '../catalog/useDragReorder';
import { CustomFieldInput } from '../customers/CustomFieldInput';

const SCOPES = [
	{ key: 'customer', label: __( 'Customer', 'pointly-booking' ) },
	{ key: 'booking', label: __( 'Booking', 'pointly-booking' ) },
	{ key: 'form', label: __( 'Other', 'pointly-booking' ) },
];

const TYPES = [ 'text', 'email', 'tel', 'textarea', 'number', 'date', 'select', 'radio', 'checkbox' ];
const STEPS = [
	{ value: 'details', label: __( 'Details', 'pointly-booking' ) },
	{ value: 'payment', label: __( 'Payment', 'pointly-booking' ) },
	{ value: 'summary', label: __( 'Summary', 'pointly-booking' ) },
];

function emptyForm( scope ) {
	return { field_key: '', label: '', type: 'text', scope, step_key: 'details', placeholder: '', options: [], is_required: false, is_enabled: true, show_in_wizard: true };
}

function FieldDrawer( { id, open, scope, onClose, onSaved, onDeleted } ) {
	const isNew = ! id;
	const confirm = useConfirm();
	const toast = useToast();
	const { data: detail, loading } = useResource( ! isNew && open ? `admin/form-fields/${ id }` : null );
	const [ form, setForm ] = useState( emptyForm( scope ) );
	const [ saving, setSaving ] = useState( false );
	const [ deleting, setDeleting ] = useState( false );
	const [ error, setError ] = useState( null );
	const update = ( patch ) => setForm( ( prev ) => ( { ...prev, ...patch } ) );

	useEffect( () => {
		if ( ! open ) {
			return;
		}
		if ( isNew ) {
			setForm( emptyForm( scope ) );
			setError( null );
		} else if ( detail ) {
			setForm( { ...detail } );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ open, isNew, detail ] );

	const isCore = ! isNew && detail && detail.is_core;
	const needsOptions = [ 'select', 'radio', 'checkbox' ].includes( form.type );

	const submit = async () => {
		setSaving( true );
		setError( null );
		try {
			if ( isNew ) {
				await client().post( 'admin/form-fields', form );
				toast.success( __( 'Field created.', 'pointly-booking' ) );
			} else {
				await client().patch( `admin/form-fields/${ id }`, form );
				toast.success( __( 'Field updated.', 'pointly-booking' ) );
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
		const ok = await confirm( { title: __( 'Delete this field?', 'pointly-booking' ), confirmLabel: __( 'Delete', 'pointly-booking' ), tone: 'danger' } );
		if ( ! ok ) {
			return;
		}
		setDeleting( true );
		try {
			await client().del( `admin/form-fields/${ id }` );
			toast.success( __( 'Field deleted.', 'pointly-booking' ) );
			onDeleted();
		} catch ( e ) {
			toast.error( e.message );
		} finally {
			setDeleting( false );
		}
	};

	const showSkeleton = ! isNew && ( loading || ! detail );
	const updateOption = ( i, patch ) => update( { options: form.options.map( ( o, idx ) => ( idx === i ? { ...o, ...patch } : o ) ) } );

	return (
		<Drawer open={ open } onClose={ onClose } title={ isNew ? __( 'New field', 'pointly-booking' ) : __( 'Edit field', 'pointly-booking' ) } size="md">
			{ showSkeleton ? (
				<Skeleton variant="text" lines={ 8 } />
			) : (
				<div className="pbk-drawer-form">
					{ error && <Notice tone="danger">{ error.message }</Notice> }
					{ isCore && <Notice tone="info">{ __( 'This is a built-in field. Its key and type are fixed.', 'pointly-booking' ) }</Notice> }
					<Field label={ __( 'Label', 'pointly-booking' ) } required error={ fieldError( error, 'label' ) }>
						<Input value={ form.label } onChange={ ( e ) => update( { label: e.target.value } ) } />
					</Field>
					<div className="pbk-drawer-form__grid">
						<Field label={ __( 'Type', 'pointly-booking' ) }>
							<Select disabled={ isCore } value={ form.type } onChange={ ( e ) => update( { type: e.target.value } ) } options={ TYPES.map( ( t ) => ( { value: t, label: t } ) ) } />
						</Field>
						<Field label={ __( 'Step', 'pointly-booking' ) }>
							<Select value={ form.step_key } onChange={ ( e ) => update( { step_key: e.target.value } ) } options={ STEPS } />
						</Field>
						<Field label={ __( 'Placeholder', 'pointly-booking' ) } optional className="pbk-field--full">
							<Input value={ form.placeholder } onChange={ ( e ) => update( { placeholder: e.target.value } ) } />
						</Field>
					</div>

					{ needsOptions && (
						<Field label={ __( 'Options', 'pointly-booking' ) } required error={ fieldError( error, 'options' ) }>
							<div className="pbk-stack">
								{ form.options.map( ( o, i ) => (
									<div key={ i } className="pbk-row">
										<Input placeholder={ __( 'Label', 'pointly-booking' ) } value={ o.label } onChange={ ( e ) => updateOption( i, { label: e.target.value } ) } />
										<Button size="sm" variant="ghost" icon="x" aria-label={ __( 'Remove option', 'pointly-booking' ) } onClick={ () => update( { options: form.options.filter( ( _, idx ) => idx !== i ) } ) } />
									</div>
								) ) }
								<Button size="sm" variant="secondary" icon="plus" onClick={ () => update( { options: [ ...form.options, { label: '', value: '' } ] } ) }>
									{ __( 'Add option', 'pointly-booking' ) }
								</Button>
							</div>
						</Field>
					) }

					<Toggle label={ __( 'Required', 'pointly-booking' ) } checked={ !! form.is_required } disabled={ isCore && form.field_key === 'email' } onChange={ ( v ) => update( { is_required: v } ) } />
					<Toggle label={ __( 'Enabled', 'pointly-booking' ) } checked={ !! form.is_enabled } disabled={ isCore && form.field_key === 'email' } onChange={ ( v ) => update( { is_enabled: v } ) } />
					<Toggle label={ __( 'Show in the booking wizard', 'pointly-booking' ) } checked={ !! form.show_in_wizard } onChange={ ( v ) => update( { show_in_wizard: v } ) } />

					<Field label={ __( 'Preview', 'pointly-booking' ) }>
						<CustomFieldInput field={ form } value={ needsOptions && form.type === 'checkbox' ? [] : '' } onChange={ () => {} } />
					</Field>

					<div className="pbk-drawer-footer">
						{ ! isNew && ! isCore && (
							<Button variant="ghost" className="pbk-delete-btn" icon="trash" loading={ deleting } onClick={ remove }>
								{ __( 'Delete', 'pointly-booking' ) }
							</Button>
						) }
						<Button variant="ghost" onClick={ onClose }>
							{ __( 'Cancel', 'pointly-booking' ) }
						</Button>
						<Button variant="primary" loading={ saving } disabled={ ! form.label } onClick={ submit }>
							{ isNew ? __( 'Create field', 'pointly-booking' ) : __( 'Save changes', 'pointly-booking' ) }
						</Button>
					</div>
				</div>
			) }
		</Drawer>
	);
}

export default function FormFieldsTab() {
	const toast = useToast();
	const confirm = useConfirm();
	const [ scope, setScope ] = useState( 'customer' );
	const [ search, setSearch ] = useState( '' );
	const [ status, setStatus ] = useState( '' );
	const [ drawerId, setDrawerId ] = useState( null );
	const { data: fields, loading, error, reload, setData } = useResource( 'admin/form-fields', { scope } );

	const list = useMemo( () => {
		let items = fields || [];
		if ( search ) {
			const q = search.toLowerCase();
			items = items.filter( ( f ) => f.label.toLowerCase().includes( q ) || f.field_key.toLowerCase().includes( q ) );
		}
		if ( status ) {
			items = items.filter( ( f ) => ( status === 'enabled' ? f.is_enabled : ! f.is_enabled ) );
		}
		return items;
	}, [ fields, search, status ] );

	const canReorder = ! search && ! status;
	const { rowProps, hoverId } = useDragReorder( list, async ( ids ) => {
		setData( ids.map( ( id ) => list.find( ( f ) => f.id === id ) ) );
		try {
			await client().post( 'admin/form-fields/reorder', { ids } );
		} catch ( e ) {
			toast.error( e.message );
			reload();
		}
	} );

	const reseed = async () => {
		const ok = await confirm( { title: __( 'Restore the default fields?', 'pointly-booking' ), message: __( 'Adds back any missing built-in fields. Existing fields are not changed.', 'pointly-booking' ), confirmLabel: __( 'Restore', 'pointly-booking' ) } );
		if ( ! ok ) {
			return;
		}
		try {
			const result = await client().post( 'admin/form-fields/reseed' );
			toast.success( result.created > 0 ? __( 'Default fields restored.', 'pointly-booking' ) : __( 'Nothing to restore — all default fields are present.', 'pointly-booking' ) );
			reload();
		} catch ( e ) {
			toast.error( e.message );
		}
	};

	if ( error && ! fields ) {
		return <ErrorState message={ error } onRetry={ reload } />;
	}

	return (
		<div>
			<div className="pbk-toolbar">
				<Tabs value={ scope } onChange={ setScope } label={ __( 'Scope', 'pointly-booking' ) } tabs={ SCOPES } />
			</div>
			<div className="pbk-reorder-toolbar">
				<div className="pbk-reorder-toolbar__search">
					<SearchInput label={ __( 'Search fields', 'pointly-booking' ) } value={ search } onChange={ ( e ) => setSearch( e.target.value ) } onClear={ () => setSearch( '' ) } />
				</div>
				<Select value={ status } onChange={ ( e ) => setStatus( e.target.value ) } placeholder={ __( 'All statuses', 'pointly-booking' ) } options={ [ { value: 'enabled', label: __( 'Enabled', 'pointly-booking' ) }, { value: 'disabled', label: __( 'Disabled', 'pointly-booking' ) } ] } />
				<div className="pbk-reorder-toolbar__spacer" />
				<Button variant="secondary" icon="refresh" onClick={ reseed }>
					{ __( 'Restore defaults', 'pointly-booking' ) }
				</Button>
				<Button variant="primary" icon="plus" onClick={ () => setDrawerId( 'new' ) }>
					{ __( 'New field', 'pointly-booking' ) }
				</Button>
			</div>

			{ loading && ! fields && <Skeleton variant="block" height={ 200 } /> }
			{ fields && list.length === 0 && <EmptyState icon="list" title={ __( 'No fields found.', 'pointly-booking' ) } /> }

			{ list.length > 0 && (
				<div className="pbk-reorder-list">
					{ list.map( ( f ) => (
						<div key={ f.id } className={ `pbk-reorder-row ${ ! f.is_enabled ? 'is-inactive' : '' } ${ hoverId === f.id ? 'is-drop-target' : '' }` } { ...( canReorder ? rowProps( f.id ) : {} ) }>
							{ canReorder && (
								<span className="pbk-reorder-row__handle">
									<Icon name="grip" size={ 18 } />
								</span>
							) }
							<span className="pbk-reorder-row__text">
								<span className="pbk-reorder-row__title">
									{ f.label }
									{ f.is_core && <Badge tone="brand" size="sm">{ __( 'Built-in', 'pointly-booking' ) }</Badge> }
									{ ! f.is_enabled && <Badge tone="neutral" size="sm">{ __( 'Disabled', 'pointly-booking' ) }</Badge> }
									{ !! f.is_required && <Badge tone="warning" size="sm">{ __( 'Required', 'pointly-booking' ) }</Badge> }
								</span>
								<span className="pbk-reorder-row__meta">
									{ f.field_key } · { f.type }
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
									items={ [ { label: __( 'Edit', 'pointly-booking' ), icon: 'edit', onClick: () => setDrawerId( f.id ) } ] }
								/>
							</span>
						</div>
					) ) }
				</div>
			) }

			<FieldDrawer
				id={ drawerId === 'new' ? null : drawerId }
				open={ drawerId !== null }
				scope={ scope }
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
