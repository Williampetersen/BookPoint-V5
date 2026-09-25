/**
 * Notification workflow editor: trigger, conditions, one or more email actions, test send.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Field, Input, Textarea, Select, Toggle, Checkbox, Drawer, Tabs, Skeleton, Notice, Badge, useConfirm, useToast } from '../../ui';
import { useResource, client, fieldError } from '../api';
import { VariablesList } from './VariablesList';
import './notifications.css';

const OPERATORS = [
	{ value: 'is', label: __( 'is', 'pointly-booking' ) },
	{ value: 'is_not', label: __( 'is not', 'pointly-booking' ) },
	{ value: 'in', label: __( 'is one of', 'pointly-booking' ) },
	{ value: 'not_in', label: __( 'is not one of', 'pointly-booking' ) },
];

const FIXED_OPTIONS = {
	status: [ 'pending', 'confirmed', 'completed', 'cancelled', 'pending_payment', 'failed_payment' ].map( ( v ) => ( { value: v, label: v } ) ),
	payment_status: [ 'unpaid', 'paid', 'refunded' ].map( ( v ) => ( { value: v, label: v } ) ),
	payment_method: [ 'cash', 'stripe', 'paypal', 'woocommerce' ].map( ( v ) => ( { value: v, label: v } ) ),
};

function emptyForm() {
	return {
		name: '',
		event_key: '',
		status: 'active',
		conditions: [],
		time_offset_minutes: 0,
		actions: [ { type: 'send_email', status: 'active', config: { to: '{{customer_email}}', subject: '', body: '', from_name: '', from_email: '', reply_to: '', attach_ics: false } } ],
	};
}

function ConditionRow( { condition, fields, lookups, onChange, onRemove } ) {
	const options = FIXED_OPTIONS[ condition.field ] || ( lookups[ condition.field ] || [] );
	return (
		<div className="pbk-row">
			<Select value={ condition.field } onChange={ ( e ) => onChange( { field: e.target.value, value: [] } ) } options={ Object.entries( fields ).map( ( [ value, label ] ) => ( { value, label } ) ) } />
			<Select value={ condition.operator } onChange={ ( e ) => onChange( { operator: e.target.value } ) } options={ OPERATORS } />
			{ options.length > 0 ? (
				<Select
					value={ condition.value[ 0 ] || '' }
					onChange={ ( e ) => onChange( { value: [ e.target.value ] } ) }
					placeholder={ __( 'Choose…', 'pointly-booking' ) }
					options={ options }
				/>
			) : (
				<Input
					value={ condition.value.join( ', ' ) }
					placeholder={ __( 'Value(s), comma separated', 'pointly-booking' ) }
					onChange={ ( e ) => onChange( { value: e.target.value.split( ',' ).map( ( v ) => v.trim() ).filter( Boolean ) } ) }
				/>
			) }
			<Button size="sm" variant="ghost" icon="x" onClick={ onRemove } aria-label={ __( 'Remove condition', 'pointly-booking' ) } />
		</div>
	);
}

function ActionEditor( { action, onChange, onRemove, onInsertRef, canRemove, workflowId, actionId, defaultTestRecipient } ) {
	const toast = useToast();
	const [ testTo, setTestTo ] = useState( defaultTestRecipient || '' );
	const [ testing, setTesting ] = useState( false );
	const [ previewing, setPreviewing ] = useState( false );
	const [ preview, setPreview ] = useState( null );

	const update = ( patch ) => onChange( { ...action, config: { ...action.config, ...patch } } );

	const sendTest = async () => {
		setTesting( true );
		try {
			const path = actionId ? `admin/notifications/actions/${ actionId }/test` : `admin/notifications/workflows/${ workflowId }/test`;
			await client().post( path, { to: testTo } );
			toast.success( sprintf( /* translators: %s: email address */ __( 'Test email sent to %s.', 'pointly-booking' ), testTo ) );
		} catch ( e ) {
			toast.error( e.message );
		} finally {
			setTesting( false );
		}
	};

	const showPreview = async () => {
		setPreviewing( true );
		try {
			const result = await client().post( 'admin/notifications/preview', { subject: action.config.subject, body: action.config.body } );
			setPreview( result );
		} catch ( e ) {
			toast.error( e.message );
		} finally {
			setPreviewing( false );
		}
	};

	return (
		<div className="pbk-action-editor">
			<div className="pbk-row">
				<Badge tone={ action.status === 'active' ? 'success' : 'neutral' } dot size="sm">
					{ action.status === 'active' ? __( 'Active', 'pointly-booking' ) : __( 'Disabled', 'pointly-booking' ) }
				</Badge>
				<span className="pbk-spacer" />
				<Toggle label={ __( 'Enabled', 'pointly-booking' ) } checked={ action.status === 'active' } onChange={ ( v ) => onChange( { ...action, status: v ? 'active' : 'disabled' } ) } />
				{ canRemove && (
					<Button size="sm" variant="ghost" icon="trash" onClick={ onRemove }>
						{ __( 'Remove', 'pointly-booking' ) }
					</Button>
				) }
			</div>
			<Field label={ __( 'Send to', 'pointly-booking' ) } required help={ __( 'e.g. {{customer_email}} or a fixed address.', 'pointly-booking' ) }>
				<Input value={ action.config.to } onChange={ ( e ) => update( { to: e.target.value } ) } />
			</Field>
			<Field label={ __( 'Subject', 'pointly-booking' ) } required>
				<Input value={ action.config.subject } onChange={ ( e ) => update( { subject: e.target.value } ) } onFocus={ () => ( onInsertRef.current = ( token ) => update( { subject: action.config.subject + token } ) ) } />
			</Field>
			<Field label={ __( 'Message (HTML)', 'pointly-booking' ) } required>
				<Textarea
					value={ action.config.body }
					rows={ 8 }
					onChange={ ( e ) => update( { body: e.target.value } ) }
					onFocus={ () => ( onInsertRef.current = ( token ) => update( { body: action.config.body + token } ) ) }
				/>
			</Field>
			<div className="pbk-drawer-form__grid">
				<Field label={ __( 'From name', 'pointly-booking' ) } optional>
					<Input value={ action.config.from_name } onChange={ ( e ) => update( { from_name: e.target.value } ) } />
				</Field>
				<Field label={ __( 'From email', 'pointly-booking' ) } optional>
					<Input type="email" value={ action.config.from_email } onChange={ ( e ) => update( { from_email: e.target.value } ) } />
				</Field>
				<Field label={ __( 'Reply-to', 'pointly-booking' ) } optional>
					<Input type="email" value={ action.config.reply_to } onChange={ ( e ) => update( { reply_to: e.target.value } ) } />
				</Field>
			</div>
			<Checkbox label={ __( 'Attach a calendar (.ics) file', 'pointly-booking' ) } checked={ !! action.config.attach_ics } onChange={ ( e ) => update( { attach_ics: e.target.checked } ) } />

			<div className="pbk-row">
				<Button size="sm" variant="secondary" loading={ previewing } onClick={ showPreview }>
					{ __( 'Preview', 'pointly-booking' ) }
				</Button>
				<Input size="sm" type="email" value={ testTo } onChange={ ( e ) => setTestTo( e.target.value ) } placeholder={ __( 'Test recipient', 'pointly-booking' ) } />
				<Button size="sm" variant="secondary" loading={ testing } disabled={ ! testTo } onClick={ sendTest }>
					{ __( 'Send test', 'pointly-booking' ) }
				</Button>
			</div>
			{ preview && (
				<div className="pbk-email-preview">
					<div className="pbk-email-preview__subject">{ preview.subject }</div>
					<iframe title={ __( 'Email preview', 'pointly-booking' ) } className="pbk-email-preview__frame" srcDoc={ preview.html } />
				</div>
			) }
		</div>
	);
}

export default function WorkflowDrawer( { id, open, meta, onClose, onSaved, onDeleted } ) {
	const isNew = ! id;
	const confirm = useConfirm();
	const toast = useToast();
	const { data: detail, loading } = useResource( ! isNew && open ? `admin/notifications/workflows/${ id }` : null );
	const { data: agents } = useResource( open ? 'admin/agents' : null );
	const { data: services } = useResource( open ? 'admin/services' : null );
	const { data: locations } = useResource( open ? 'admin/locations' : null );
	const [ form, setForm ] = useState( emptyForm() );
	const [ actionIds, setActionIds ] = useState( [] );
	const [ saving, setSaving ] = useState( false );
	const [ deleting, setDeleting ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ tab, setTab ] = useState( 'setup' );
	const insertRef = useRef( null );
	const update = ( patch ) => setForm( ( prev ) => ( { ...prev, ...patch } ) );

	useEffect( () => {
		if ( ! open ) {
			return;
		}
		setTab( 'setup' );
		if ( isNew ) {
			setForm( emptyForm() );
			setActionIds( [] );
			setError( null );
		} else if ( detail ) {
			setForm( {
				name: detail.name,
				event_key: detail.event_key,
				status: detail.status,
				conditions: detail.conditions || [],
				time_offset_minutes: detail.time_offset_minutes || 0,
				actions: ( detail.actions || [] ).map( ( a ) => ( { type: 'send_email', status: a.status, config: a.config } ) ),
			} );
			setActionIds( ( detail.actions || [] ).map( ( a ) => a.id ) );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ open, isNew, detail ] );

	const lookups = {
		agent_id: ( agents || [] ).map( ( a ) => ( { value: String( a.id ), label: a.name } ) ),
		service_id: ( services || [] ).map( ( s ) => ( { value: String( s.id ), label: s.name } ) ),
		location_id: ( locations || [] ).map( ( l ) => ( { value: String( l.id ), label: l.name } ) ),
	};

	const submit = async () => {
		setSaving( true );
		setError( null );
		try {
			const payload = {
				name: form.name,
				event_key: form.event_key,
				status: form.status,
				conditions: form.conditions,
				time_offset_minutes: form.time_offset_minutes,
				actions: form.actions.map( ( a, i ) => ( { id: actionIds[ i ] || 0, ...a } ) ),
			};
			if ( isNew ) {
				await client().post( 'admin/notifications/workflows', payload );
				toast.success( __( 'Notification created.', 'pointly-booking' ) );
			} else {
				await client().patch( `admin/notifications/workflows/${ id }`, payload );
				toast.success( __( 'Notification updated.', 'pointly-booking' ) );
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
		const ok = await confirm( { title: __( 'Delete this notification?', 'pointly-booking' ), confirmLabel: __( 'Delete', 'pointly-booking' ), tone: 'danger' } );
		if ( ! ok ) {
			return;
		}
		setDeleting( true );
		try {
			await client().del( `admin/notifications/workflows/${ id }` );
			toast.success( __( 'Notification deleted.', 'pointly-booking' ) );
			onDeleted();
		} catch ( e ) {
			toast.error( e.message );
		} finally {
			setDeleting( false );
		}
	};

	const addAction = () => update( { actions: [ ...form.actions, { type: 'send_email', status: 'active', config: { to: '{{customer_email}}', subject: '', body: '', from_name: '', from_email: '', reply_to: '', attach_ics: false } } ] } );

	const showSkeleton = ! isNew && ( loading || ! detail );

	return (
		<Drawer open={ open } onClose={ onClose } title={ isNew ? __( 'New notification', 'pointly-booking' ) : __( 'Edit notification', 'pointly-booking' ) } size="lg">
			{ showSkeleton ? (
				<Skeleton variant="text" lines={ 10 } />
			) : (
				<div className="pbk-drawer-form">
					{ error && <Notice tone="danger">{ error.message }</Notice> }
					<Tabs
						variant="pills"
						value={ tab }
						onChange={ setTab }
						label={ __( 'Section', 'pointly-booking' ) }
						tabs={ [
							{ key: 'setup', label: __( 'Setup', 'pointly-booking' ) },
							{ key: 'emails', label: __( 'Emails', 'pointly-booking' ) },
							{ key: 'variables', label: __( 'Smart variables', 'pointly-booking' ) },
						] }
					/>

					{ tab === 'setup' && (
						<div className="pbk-stack">
							<Field label={ __( 'Name', 'pointly-booking' ) } required error={ fieldError( error, 'name' ) }>
								<Input value={ form.name } onChange={ ( e ) => update( { name: e.target.value } ) } />
							</Field>
							<Field label={ __( 'When', 'pointly-booking' ) } required error={ fieldError( error, 'event_key' ) }>
								<Select value={ form.event_key } onChange={ ( e ) => update( { event_key: e.target.value } ) } placeholder={ __( 'Choose an event', 'pointly-booking' ) } options={ meta ? meta.events : [] } />
							</Field>
							<Field label={ __( 'Delay (minutes)', 'pointly-booking' ) } optional help={ __( 'Negative sends before the appointment, positive sends after. 0 sends immediately.', 'pointly-booking' ) }>
								<Input type="number" value={ form.time_offset_minutes } onChange={ ( e ) => update( { time_offset_minutes: Number( e.target.value ) } ) } />
							</Field>
							<Toggle label={ __( 'Active', 'pointly-booking' ) } checked={ form.status === 'active' } onChange={ ( v ) => update( { status: v ? 'active' : 'disabled' } ) } />

							<Field label={ __( 'Only send when', 'pointly-booking' ) } optional help={ __( 'Leave empty to always send.', 'pointly-booking' ) }>
								<div className="pbk-stack">
									{ form.conditions.map( ( c, i ) => (
										<ConditionRow
											key={ i }
											condition={ c }
											fields={ meta ? meta.condition_fields : {} }
											lookups={ lookups }
											onChange={ ( patch ) => update( { conditions: form.conditions.map( ( x, xi ) => ( xi === i ? { ...x, ...patch } : x ) ) } ) }
											onRemove={ () => update( { conditions: form.conditions.filter( ( _, xi ) => xi !== i ) } ) }
										/>
									) ) }
									<Button
										size="sm"
										variant="secondary"
										icon="plus"
										onClick={ () => update( { conditions: [ ...form.conditions, { field: 'status', operator: 'is', value: [] } ] } ) }
									>
										{ __( 'Add condition', 'pointly-booking' ) }
									</Button>
								</div>
							</Field>
						</div>
					) }

					{ tab === 'emails' && (
						<div className="pbk-stack">
							{ form.actions.map( ( action, i ) => (
								<ActionEditor
									key={ i }
									action={ action }
									workflowId={ id }
									actionId={ actionIds[ i ] }
									canRemove={ form.actions.length > 1 }
									onInsertRef={ insertRef }
									defaultTestRecipient={ meta ? meta.test_recipient : '' }
									onChange={ ( next ) => update( { actions: form.actions.map( ( a, ai ) => ( ai === i ? next : a ) ) } ) }
									onRemove={ () => {
										update( { actions: form.actions.filter( ( _, ai ) => ai !== i ) } );
										setActionIds( actionIds.filter( ( _, ai ) => ai !== i ) );
									} }
								/>
							) ) }
							<Button size="sm" variant="secondary" icon="plus" onClick={ addAction }>
								{ __( 'Add another email', 'pointly-booking' ) }
							</Button>
						</div>
					) }

					{ tab === 'variables' && <VariablesList onInsert={ ( token ) => insertRef.current && insertRef.current( token ) } /> }

					<div className="pbk-drawer-footer">
						{ ! isNew && (
							<Button variant="ghost" className="pbk-delete-btn" icon="trash" loading={ deleting } onClick={ remove }>
								{ __( 'Delete', 'pointly-booking' ) }
							</Button>
						) }
						<Button variant="ghost" onClick={ onClose }>
							{ __( 'Cancel', 'pointly-booking' ) }
						</Button>
						<Button variant="primary" loading={ saving } disabled={ ! form.name || ! form.event_key } onClick={ submit }>
							{ isNew ? __( 'Create notification', 'pointly-booking' ) : __( 'Save changes', 'pointly-booking' ) }
						</Button>
					</div>
				</div>
			) }
		</Drawer>
	);
}
