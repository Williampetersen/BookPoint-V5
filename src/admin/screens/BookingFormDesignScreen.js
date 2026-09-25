/**
 * Booking Form Designer: steps, appearance, texts, fields layout and behavior.
 */
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Icon,
	Field,
	Input,
	Textarea,
	Select,
	Toggle,
	Checkbox,
	Tabs,
	Skeleton,
	ErrorState,
	Notice,
	useConfirm,
	useToast,
} from '../../ui';
import { useResource, client } from '../api';
import { PageHeader } from '../layout/PageHeader';
import { MediaPicker } from '../components/MediaPicker';
import { useDragReorder } from '../catalog/useDragReorder';
import '../design/design.css';

const STEP_LABELS = {
	location: __( 'Location', 'pointly-booking' ),
	category: __( 'Category', 'pointly-booking' ),
	service: __( 'Service', 'pointly-booking' ),
	extras: __( 'Extras', 'pointly-booking' ),
	agents: __( 'Staff', 'pointly-booking' ),
	datetime: __( 'Date & time', 'pointly-booking' ),
	customer: __( 'Your details', 'pointly-booking' ),
	payment: __( 'Payment', 'pointly-booking' ),
	review: __( 'Review', 'pointly-booking' ),
	confirm: __( 'Confirmation', 'pointly-booking' ),
};

const STEP_ICONS = {
	location: 'map-pin',
	category: 'layers',
	service: 'sparkles',
	extras: 'tag',
	agents: 'users',
	datetime: 'calendar',
	customer: 'user',
	payment: 'credit-card',
	review: 'list',
	confirm: 'check-circle',
};

const REQUIRED_STEPS = [ 'service', 'agents', 'datetime', 'customer', 'review', 'confirm' ];
const TOP_TABS = [
	{ key: 'steps', label: __( 'Steps', 'pointly-booking' ) },
	{ key: 'appearance', label: __( 'Appearance', 'pointly-booking' ) },
	{ key: 'texts', label: __( 'Texts', 'pointly-booking' ) },
	{ key: 'fields', label: __( 'Fields layout', 'pointly-booking' ) },
	{ key: 'behavior', label: __( 'Behavior', 'pointly-booking' ) },
];

const DRAFT_KEY = 'pointlybooking_design_draft';

function StepsPanel( { steps, onChange } ) {
	const [ selectedKey, setSelectedKey ] = useState( steps[ 0 ] ? steps[ 0 ].key : '' );
	const selected = steps.find( ( s ) => s.key === selectedKey ) || steps[ 0 ];
	const { rowProps, hoverId } = useDragReorder(
		steps.map( ( s ) => ( { id: s.key } ) ),
		( ids ) => onChange( ids.map( ( id ) => steps.find( ( s ) => s.key === id ) ) )
	);
	const updateStep = ( key, patch ) => onChange( steps.map( ( s ) => ( s.key === key ? { ...s, ...patch } : s ) ) );

	if ( ! selected ) {
		return null;
	}

	return (
		<div className="pbk-design">
			<div className="pbk-design__steps">
				{ steps.map( ( s ) => (
					<div
						key={ s.key }
						className={ `pbk-design__step ${ s.key === selected.key ? 'is-active' : '' } ${ ! s.enabled ? 'is-disabled' : '' } ${ hoverId === s.key ? 'is-drop-target' : '' }` }
						{ ...rowProps( s.key ) }
					>
						<span className="pbk-design__step-handle">
							<Icon name="grip" size={ 16 } />
						</span>
						<button type="button" className="pbk-design__step-select" onClick={ () => setSelectedKey( s.key ) }>
							<Icon name={ STEP_ICONS[ s.key ] || 'sparkles' } size={ 16 } />
							<span className="pbk-design__step-name">{ s.title || STEP_LABELS[ s.key ] || s.key }</span>
						</button>
						<Toggle
							checked={ s.enabled }
							disabled={ REQUIRED_STEPS.includes( s.key ) }
							onChange={ ( v ) => updateStep( s.key, { enabled: v } ) }
							aria-label={ sprintf( /* translators: %s: step name */ __( 'Enable %s step', 'pointly-booking' ), STEP_LABELS[ s.key ] || s.key ) }
						/>
					</div>
				) ) }
			</div>

			<div className="pbk-design__panel">
				<div className="pbk-design__preview" style={ { '--preview-accent': selected.accentOverride || undefined } }>
					<div className="pbk-design__preview-art">
						{ selected.imageUrl ? <img src={ selected.imageUrl } alt="" style={ { width: '100%', height: '100%', objectFit: 'cover', borderRadius: 12 } } /> : <Icon name={ STEP_ICONS[ selected.key ] || 'sparkles' } size={ 64 } /> }
					</div>
					<div className="pbk-design__preview-title">{ selected.title || STEP_LABELS[ selected.key ] }</div>
					{ selected.subtitle && <div className="pbk-design__preview-subtitle">{ selected.subtitle }</div> }
				</div>

				{ REQUIRED_STEPS.includes( selected.key ) && <Notice tone="info">{ __( 'This step is required and always shown.', 'pointly-booking' ) }</Notice> }

				<MediaPicker
					imageId={ selected.imageId }
					imageUrl={ selected.imageUrl }
					title={ sprintf( /* translators: %s: step name */ __( 'Select an image for %s', 'pointly-booking' ), STEP_LABELS[ selected.key ] ) }
					onChange={ ( imgId, url ) => updateStep( selected.key, { imageId: imgId, imageUrl: url } ) }
				/>

				<div className="pbk-drawer-form__grid">
					<Field label={ __( 'Title', 'pointly-booking' ) } optional help={ sprintf( /* translators: %s: default label */ __( 'Default: %s', 'pointly-booking' ), STEP_LABELS[ selected.key ] ) }>
						<Input value={ selected.title } onChange={ ( e ) => updateStep( selected.key, { title: e.target.value } ) } />
					</Field>
					<Field label={ __( 'Subtitle', 'pointly-booking' ) } optional>
						<Input value={ selected.subtitle } onChange={ ( e ) => updateStep( selected.key, { subtitle: e.target.value } ) } />
					</Field>
					<Field label={ __( 'Back button label', 'pointly-booking' ) } optional>
						<Input value={ selected.buttonBackLabel } onChange={ ( e ) => updateStep( selected.key, { buttonBackLabel: e.target.value } ) } />
					</Field>
					<Field label={ __( 'Next button label', 'pointly-booking' ) } optional>
						<Input value={ selected.buttonNextLabel } onChange={ ( e ) => updateStep( selected.key, { buttonNextLabel: e.target.value } ) } />
					</Field>
				</div>
				<Field label={ __( 'Accent color override', 'pointly-booking' ) } optional>
					<div className="pbk-design__color-row">
						<input type="color" value={ selected.accentOverride || '#4f46e5' } onChange={ ( e ) => updateStep( selected.key, { accentOverride: e.target.value } ) } />
						<Input value={ selected.accentOverride } placeholder={ __( 'Use the brand color', 'pointly-booking' ) } onChange={ ( e ) => updateStep( selected.key, { accentOverride: e.target.value } ) } />
						{ selected.accentOverride && (
							<Button size="sm" variant="ghost" onClick={ () => updateStep( selected.key, { accentOverride: '' } ) }>
								{ __( 'Clear', 'pointly-booking' ) }
							</Button>
						) }
					</div>
				</Field>
				<Toggle label={ __( 'Show the left illustration panel', 'pointly-booking' ) } checked={ selected.showLeftPanel } onChange={ ( v ) => updateStep( selected.key, { showLeftPanel: v } ) } />
				<Toggle label={ __( 'Show the help box', 'pointly-booking' ) } checked={ selected.showHelpBox } onChange={ ( v ) => updateStep( selected.key, { showHelpBox: v } ) } />
			</div>
		</div>
	);
}

function AppearancePanel( { appearance, onChange } ) {
	const update = ( patch ) => onChange( { ...appearance, ...patch } );
	return (
		<div className="pbk-design__panel">
			<Field label={ __( 'Primary color', 'pointly-booking' ) }>
				<div className="pbk-design__color-row">
					<input type="color" value={ appearance.primaryColor || '#4f46e5' } onChange={ ( e ) => update( { primaryColor: e.target.value } ) } />
					<Input value={ appearance.primaryColor } onChange={ ( e ) => update( { primaryColor: e.target.value } ) } />
				</div>
			</Field>
			<Field label={ __( 'Border style', 'pointly-booking' ) }>
				<Select
					value={ appearance.borderStyle }
					onChange={ ( e ) => update( { borderStyle: e.target.value } ) }
					options={ [
						{ value: 'rounded', label: __( 'Rounded', 'pointly-booking' ) },
						{ value: 'flat', label: __( 'Flat', 'pointly-booking' ) },
						{ value: 'square', label: __( 'Square', 'pointly-booking' ) },
						{ value: 'pill', label: __( 'Pill', 'pointly-booking' ) },
					] }
				/>
			</Field>
			<Field label={ __( 'Font', 'pointly-booking' ) }>
				<Select
					value={ appearance.font }
					onChange={ ( e ) => update( { font: e.target.value } ) }
					options={ [
						{ value: 'system', label: __( 'System font', 'pointly-booking' ) },
						{ value: 'inherit', label: __( 'Match my theme', 'pointly-booking' ) },
					] }
				/>
			</Field>
			<Toggle label={ __( 'Dark mode by default', 'pointly-booking' ) } checked={ appearance.darkModeDefault } onChange={ ( v ) => update( { darkModeDefault: v } ) } />
		</div>
	);
}

function TextsPanel( { texts, onChange } ) {
	const update = ( patch ) => onChange( { ...texts, ...patch } );
	return (
		<div className="pbk-design__panel">
			<div className="pbk-drawer-form__grid">
				<Field label={ __( 'Help box title', 'pointly-booking' ) } optional>
					<Input value={ texts.helpTitle } onChange={ ( e ) => update( { helpTitle: e.target.value } ) } />
				</Field>
				<Field label={ __( 'Help box phone', 'pointly-booking' ) } optional>
					<Input value={ texts.helpPhone } onChange={ ( e ) => update( { helpPhone: e.target.value } ) } />
				</Field>
				<Field label={ __( 'Global back label', 'pointly-booking' ) } optional>
					<Input value={ texts.backLabel } onChange={ ( e ) => update( { backLabel: e.target.value } ) } />
				</Field>
				<Field label={ __( 'Global next label', 'pointly-booking' ) } optional>
					<Input value={ texts.nextLabel } onChange={ ( e ) => update( { nextLabel: e.target.value } ) } />
				</Field>
				<Field label={ __( 'Success title', 'pointly-booking' ) } optional>
					<Input value={ texts.successTitle } onChange={ ( e ) => update( { successTitle: e.target.value } ) } />
				</Field>
			</div>
			<Field label={ __( 'Success message', 'pointly-booking' ) } optional>
				<Textarea value={ texts.successMessage } rows={ 3 } onChange={ ( e ) => update( { successMessage: e.target.value } ) } />
			</Field>
		</div>
	);
}

function FieldScopeEditor( { scopeKey, scopeLabel, fieldsLayout, allFields, onChange } ) {
	const list = ( fieldsLayout[ scopeKey ] && fieldsLayout[ scopeKey ].fields ) || [];
	const available = ( allFields[ scopeKey ] || [] ).filter( ( f ) => ! list.some( ( l ) => l.id === f.field_key ) );
	const updateList = ( next ) => onChange( { ...fieldsLayout, [ scopeKey ]: { fields: next } } );
	const { rowProps, hoverId } = useDragReorder(
		list.map( ( f ) => ( { id: f.id } ) ),
		( ids ) => updateList( ids.map( ( id ) => list.find( ( f ) => f.id === id ) ) )
	);

	return (
		<div className="pbk-stack">
			<h3 className="pbk-booking-detail__heading">{ scopeLabel }</h3>
			{ list.map( ( f ) => {
				const def = ( allFields[ scopeKey ] || [] ).find( ( x ) => x.field_key === f.id );
				return (
					<div key={ f.id } className={ `pbk-design__field-row ${ hoverId === f.id ? 'is-drop-target' : '' }` } { ...rowProps( f.id ) }>
						<Icon name="grip" size={ 16 } />
						<span className="pbk-design__field-name">{ def ? def.label : f.id }</span>
						<Select
							value={ f.width }
							onChange={ ( e ) => updateList( list.map( ( x ) => ( x.id === f.id ? { ...x, width: e.target.value } : x ) ) ) }
							options={ [ { value: 'half', label: __( 'Half width', 'pointly-booking' ) }, { value: 'full', label: __( 'Full width', 'pointly-booking' ) } ] }
						/>
						<Checkbox
							label={ __( 'Required', 'pointly-booking' ) }
							checked={ !! f.required }
							onChange={ ( e ) => updateList( list.map( ( x ) => ( x.id === f.id ? { ...x, required: e.target.checked } : x ) ) ) }
						/>
						<Button size="sm" variant="ghost" icon="x" aria-label={ __( 'Remove', 'pointly-booking' ) } onClick={ () => updateList( list.filter( ( x ) => x.id !== f.id ) ) } />
					</div>
				);
			} ) }
			{ available.length > 0 && (
				<Select
					value=""
					onChange={ ( e ) => {
						if ( e.target.value ) {
							updateList( [ ...list, { id: e.target.value, width: 'full', required: false } ] );
						}
					} }
					placeholder={ __( 'Add a field…', 'pointly-booking' ) }
					options={ available.map( ( f ) => ( { value: f.field_key, label: f.label } ) ) }
				/>
			) }
		</div>
	);
}

function FieldsLayoutPanel( { fieldsLayout, allFields, onChange } ) {
	return (
		<div className="pbk-design__panel">
			<FieldScopeEditor scopeKey="customer" scopeLabel={ __( 'Customer fields', 'pointly-booking' ) } fieldsLayout={ fieldsLayout } allFields={ allFields } onChange={ onChange } />
			<FieldScopeEditor scopeKey="booking" scopeLabel={ __( 'Booking fields', 'pointly-booking' ) } fieldsLayout={ fieldsLayout } allFields={ allFields } onChange={ onChange } />
		</div>
	);
}

function BehaviorPanel( { behavior, onChange } ) {
	const update = ( patch ) => onChange( { ...behavior, ...patch } );
	return (
		<div className="pbk-design__panel">
			<Toggle label={ __( 'Show the live summary sidebar', 'pointly-booking' ) } checked={ behavior.showSummary } onChange={ ( v ) => update( { showSummary: v } ) } />
			<Toggle label={ __( 'Auto-select the first available date', 'pointly-booking' ) } checked={ behavior.autoSelectDate } onChange={ ( v ) => update( { autoSelectDate: v } ) } />
			<Toggle label={ __( 'Show the visitor’s timezone', 'pointly-booking' ) } checked={ behavior.showTimezone } onChange={ ( v ) => update( { showTimezone: v } ) } />
			<Toggle label={ __( 'Confirm before closing an in-progress booking', 'pointly-booking' ) } checked={ behavior.confirmOnClose } onChange={ ( v ) => update( { confirmOnClose: v } ) } />
			<Toggle label={ __( 'Show the promo code field', 'pointly-booking' ) } checked={ behavior.showPromoCode } onChange={ ( v ) => update( { showPromoCode: v } ) } />
			<Field label={ __( 'Show a search box once there are at least this many services', 'pointly-booking' ) }>
				<Input type="number" min={ 0 } max={ 100 } value={ behavior.serviceSearchMin } onChange={ ( e ) => update( { serviceSearchMin: Number( e.target.value ) } ) } />
			</Field>
		</div>
	);
}

export default function BookingFormDesignScreen() {
	const toast = useToast();
	const confirm = useConfirm();
	const { data, loading, error, reload } = useResource( 'admin/booking-form-design' );
	const { data: fieldsData } = useResource( 'admin/form-fields/all' );
	const [ tab, setTab ] = useState( 'steps' );
	const [ form, setForm ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ resetting, setResetting ] = useState( false );
	const [ draft, setDraft ] = useState( null );
	const loaded = useRef( false );

	useEffect( () => {
		if ( data && ! loaded.current ) {
			loaded.current = true;
			try {
				const stored = window.localStorage.getItem( DRAFT_KEY );
				if ( stored ) {
					setDraft( JSON.parse( stored ) );
				}
			} catch ( e ) {
				// ignore
			}
			setForm( data );
		}
	}, [ data ] );

	useEffect( () => {
		if ( ! form ) {
			return;
		}
		const timer = window.setTimeout( () => {
			try {
				window.localStorage.setItem( DRAFT_KEY, JSON.stringify( form ) );
			} catch ( e ) {
				// ignore (private mode / quota)
			}
		}, 600 );
		return () => window.clearTimeout( timer );
	}, [ form ] );

	const allFields = useMemo( () => ( { customer: fieldsData ? fieldsData.customer : [], booking: fieldsData ? fieldsData.booking : [] } ), [ fieldsData ] );

	const restoreDraft = () => {
		setForm( draft );
		setDraft( null );
	};
	const discardDraft = () => {
		try {
			window.localStorage.removeItem( DRAFT_KEY );
		} catch ( e ) {
			// ignore
		}
		setDraft( null );
	};

	const save = async () => {
		setSaving( true );
		try {
			const result = await client().post( 'admin/booking-form-design', { config: form } );
			setForm( result );
			discardDraft();
			toast.success( __( 'Booking form design saved.', 'pointly-booking' ) );
		} catch ( e ) {
			toast.error( e.message );
		} finally {
			setSaving( false );
		}
	};

	const reset = async () => {
		const ok = await confirm( { title: __( 'Reset to the default design?', 'pointly-booking' ), message: __( 'This replaces every customization on this page.', 'pointly-booking' ), confirmLabel: __( 'Reset', 'pointly-booking' ), tone: 'danger' } );
		if ( ! ok ) {
			return;
		}
		setResetting( true );
		try {
			const result = await client().post( 'admin/booking-form-design-reset' );
			setForm( result );
			discardDraft();
			toast.success( __( 'Design reset to defaults.', 'pointly-booking' ) );
		} catch ( e ) {
			toast.error( e.message );
		} finally {
			setResetting( false );
		}
	};

	if ( error && ! data ) {
		return (
			<div>
				<PageHeader title={ __( 'Booking form', 'pointly-booking' ) } />
				<ErrorState message={ error } onRetry={ reload } />
			</div>
		);
	}

	return (
		<div>
			<PageHeader
				title={ __( 'Booking form', 'pointly-booking' ) }
				description={ __( 'Customize the steps, look and text of the public booking wizard.', 'pointly-booking' ) }
				actions={
					<>
						<Button variant="secondary" loading={ resetting } onClick={ reset }>
							{ __( 'Reset to defaults', 'pointly-booking' ) }
						</Button>
						<Button variant="primary" loading={ saving } onClick={ save }>
							{ __( 'Save changes', 'pointly-booking' ) }
						</Button>
					</>
				}
			/>

			{ draft && (
				<Notice tone="warning" className="pbk-design__draft-banner">
					{ __( 'You have unsaved changes from a previous session.', 'pointly-booking' ) }
					<Button size="sm" variant="secondary" onClick={ restoreDraft }>
						{ __( 'Restore', 'pointly-booking' ) }
					</Button>
					<Button size="sm" variant="ghost" onClick={ discardDraft }>
						{ __( 'Discard', 'pointly-booking' ) }
					</Button>
				</Notice>
			) }

			{ ! form ? (
				<Skeleton variant="block" height={ 320 } />
			) : (
				<>
					<div className="pbk-toolbar">
						<Tabs value={ tab } onChange={ setTab } label={ __( 'Section', 'pointly-booking' ) } tabs={ TOP_TABS } />
					</div>
					{ tab === 'steps' && <StepsPanel steps={ form.steps } onChange={ ( steps ) => setForm( { ...form, steps } ) } /> }
					{ tab === 'appearance' && <AppearancePanel appearance={ form.appearance } onChange={ ( appearance ) => setForm( { ...form, appearance } ) } /> }
					{ tab === 'texts' && <TextsPanel texts={ form.texts } onChange={ ( texts ) => setForm( { ...form, texts } ) } /> }
					{ tab === 'fields' && <FieldsLayoutPanel fieldsLayout={ form.fieldsLayout } allFields={ allFields } onChange={ ( fieldsLayout ) => setForm( { ...form, fieldsLayout } ) } /> }
					{ tab === 'behavior' && <BehaviorPanel behavior={ form.behavior } onChange={ ( behavior ) => setForm( { ...form, behavior } ) } /> }
				</>
			) }
			{ loading && ! data && <Skeleton variant="block" height={ 320 } /> }
		</div>
	);
}
