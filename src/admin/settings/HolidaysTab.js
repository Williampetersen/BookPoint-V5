/**
 * Settings → Holidays: list, filters, quick templates, and the holiday drawer.
 */
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Icon, Field, Input, DateInput, Select, Toggle, Drawer, Dropdown, Badge, Skeleton, ErrorState, EmptyState, Notice, useConfirm, useToast } from '../../ui';
import { useResource, client, fieldError } from '../api';
import { useAdminFormat } from '../context';

const TEMPLATES = [
	{ label: __( 'New Year', 'pointly-booking' ), title: __( 'New Year', 'pointly-booking' ), month: '01', day: '01', days: 1 },
	{ label: __( 'Christmas', 'pointly-booking' ), title: __( 'Christmas', 'pointly-booking' ), month: '12', day: '25', days: 1 },
	{ label: __( 'Boxing Day', 'pointly-booking' ), title: __( 'Boxing Day', 'pointly-booking' ), month: '12', day: '26', days: 1 },
	{ label: __( 'Christmas (2 days)', 'pointly-booking' ), title: __( 'Christmas', 'pointly-booking' ), month: '12', day: '25', days: 2 },
	{ label: __( 'Independence Day', 'pointly-booking' ), title: __( 'Independence Day', 'pointly-booking' ), month: '07', day: '04', days: 1 },
];

function emptyForm() {
	return { title: '', start_date: '', end_date: '', agent_id: '', is_recurring: false, is_enabled: true };
}

function HolidayDrawer( { id, open, agents, initial, onClose, onSaved, onDeleted } ) {
	const isNew = ! id;
	const confirm = useConfirm();
	const toast = useToast();
	const [ form, setForm ] = useState( emptyForm() );
	const [ saving, setSaving ] = useState( false );
	const [ deleting, setDeleting ] = useState( false );
	const [ error, setError ] = useState( null );
	const update = ( patch ) => setForm( ( prev ) => ( { ...prev, ...patch } ) );

	useEffect( () => {
		if ( ! open ) {
			return;
		}
		setError( null );
		if ( initial ) {
			setForm( { ...emptyForm(), ...initial } );
		} else if ( isNew ) {
			setForm( emptyForm() );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ open, isNew, initial ] );

	const submit = async () => {
		setSaving( true );
		setError( null );
		try {
			const payload = {
				title: form.title,
				start_date: form.start_date,
				end_date: form.end_date || form.start_date,
				agent_id: form.agent_id ? Number( form.agent_id ) : 0,
				is_recurring: form.is_recurring,
				is_enabled: form.is_enabled,
			};
			if ( isNew ) {
				await client().post( 'admin/holidays', payload );
				toast.success( __( 'Holiday added.', 'pointly-booking' ) );
			} else {
				await client().patch( `admin/holidays/${ id }`, payload );
				toast.success( __( 'Holiday updated.', 'pointly-booking' ) );
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
		const ok = await confirm( { title: __( 'Delete this holiday?', 'pointly-booking' ), confirmLabel: __( 'Delete', 'pointly-booking' ), tone: 'danger' } );
		if ( ! ok ) {
			return;
		}
		setDeleting( true );
		try {
			await client().del( `admin/holidays/${ id }` );
			toast.success( __( 'Holiday deleted.', 'pointly-booking' ) );
			onDeleted();
		} catch ( e ) {
			toast.error( e.message );
		} finally {
			setDeleting( false );
		}
	};

	return (
		<Drawer open={ open } onClose={ onClose } title={ isNew ? __( 'New holiday', 'pointly-booking' ) : __( 'Edit holiday', 'pointly-booking' ) } size="sm">
			<div className="pbk-drawer-form">
				{ error && <Notice tone="danger">{ error.message }</Notice> }
				<Field label={ __( 'Title', 'pointly-booking' ) } required error={ fieldError( error, 'title' ) }>
					<Input value={ form.title } onChange={ ( e ) => update( { title: e.target.value } ) } />
				</Field>
				<div className="pbk-drawer-form__grid">
					<Field label={ __( 'Start date', 'pointly-booking' ) } required error={ fieldError( error, 'start_date' ) }>
						<DateInput value={ form.start_date } onChange={ ( value ) => update( { start_date: value, end_date: form.end_date && form.end_date >= value ? form.end_date : value } ) } />
					</Field>
					<Field label={ __( 'End date', 'pointly-booking' ) } optional error={ fieldError( error, 'end_date' ) }>
						<DateInput value={ form.end_date } onChange={ ( value ) => update( { end_date: value } ) } min={ form.start_date } />
					</Field>
				</div>
				<Field label={ __( 'Applies to', 'pointly-booking' ) }>
					<Select value={ form.agent_id } onChange={ ( e ) => update( { agent_id: e.target.value } ) } placeholder={ __( 'Whole business', 'pointly-booking' ) } options={ agents.map( ( a ) => ( { value: a.id, label: a.name } ) ) } />
				</Field>
				<Toggle label={ __( 'Repeat every year', 'pointly-booking' ) } checked={ form.is_recurring } onChange={ ( v ) => update( { is_recurring: v } ) } />
				<Toggle label={ __( 'Enabled', 'pointly-booking' ) } checked={ form.is_enabled } onChange={ ( v ) => update( { is_enabled: v } ) } />

				<div className="pbk-drawer-footer">
					{ ! isNew && (
						<Button variant="ghost" className="pbk-delete-btn" icon="trash" loading={ deleting } onClick={ remove }>
							{ __( 'Delete', 'pointly-booking' ) }
						</Button>
					) }
					<Button variant="ghost" onClick={ onClose }>
						{ __( 'Cancel', 'pointly-booking' ) }
					</Button>
					<Button variant="primary" loading={ saving } disabled={ ! form.title || ! form.start_date } onClick={ submit }>
						{ isNew ? __( 'Add holiday', 'pointly-booking' ) : __( 'Save changes', 'pointly-booking' ) }
					</Button>
				</div>
			</div>
		</Drawer>
	);
}

export default function HolidaysTab() {
	const fmt = useAdminFormat();
	const year = new Date().getFullYear();
	const [ yearFilter, setYearFilter ] = useState( String( year ) );
	const [ agentFilter, setAgentFilter ] = useState( '' );
	const { data: holidays, loading, error, reload } = useResource( 'admin/holidays', { year: yearFilter, agent_id: agentFilter } );
	const { data: agents } = useResource( 'admin/agents' );
	const [ drawerId, setDrawerId ] = useState( null );
	const [ prefill, setPrefill ] = useState( null );

	const agentName = useMemo( () => {
		const map = {};
		( agents || [] ).forEach( ( a ) => {
			map[ a.id ] = a.name;
		} );
		return map;
	}, [ agents ] );

	const openTemplate = ( t ) => {
		setPrefill( { title: t.title, start_date: `${ year }-${ t.month }-${ t.day }`, end_date: t.days > 1 ? `${ year }-${ t.month }-${ String( Number( t.day ) + t.days - 1 ).padStart( 2, '0' ) }` : `${ year }-${ t.month }-${ t.day }`, is_recurring: true } );
		setDrawerId( 'new' );
	};

	if ( error && ! holidays ) {
		return <ErrorState message={ error } onRetry={ reload } />;
	}

	return (
		<div>
			<div className="pbk-row" style={ { marginBottom: 'var(--pbk-space-3)' } }>
				<span className="pbk-subtle">{ __( 'Quick templates:', 'pointly-booking' ) }</span>
				{ TEMPLATES.map( ( t ) => (
					<Button key={ t.label } size="sm" variant="secondary" onClick={ () => openTemplate( t ) }>
						{ t.label }
					</Button>
				) ) }
			</div>

			<div className="pbk-reorder-toolbar">
				<Select value={ yearFilter } onChange={ ( e ) => setYearFilter( e.target.value ) } options={ [ year - 1, year, year + 1, year + 2 ].map( ( y ) => ( { value: String( y ), label: String( y ) } ) ) } />
				<Select value={ agentFilter } onChange={ ( e ) => setAgentFilter( e.target.value ) } placeholder={ __( 'Whole business + all staff', 'pointly-booking' ) } options={ ( agents || [] ).map( ( a ) => ( { value: String( a.id ), label: a.name } ) ) } />
				<div className="pbk-reorder-toolbar__spacer" />
				<Button
					variant="primary"
					icon="plus"
					onClick={ () => {
						setPrefill( null );
						setDrawerId( 'new' );
					} }
				>
					{ __( 'New holiday', 'pointly-booking' ) }
				</Button>
			</div>

			{ loading && ! holidays && <Skeleton variant="block" height={ 200 } /> }

			{ holidays && holidays.length === 0 && <EmptyState icon="calendar-x" title={ __( 'No holidays for this year.', 'pointly-booking' ) } /> }

			{ holidays && holidays.length > 0 && (
				<div className="pbk-reorder-list">
					{ holidays.map( ( h ) => (
						<div key={ h.id } className={ `pbk-reorder-row ${ ! h.is_enabled ? 'is-inactive' : '' }` }>
							<span className="pbk-reorder-row__thumb">
								<Icon name="calendar-x" size={ 18 } />
							</span>
							<span className="pbk-reorder-row__text">
								<span className="pbk-reorder-row__title">
									{ h.title }
									{ h.is_recurring_yearly ? <Badge tone="brand" size="sm">{ __( 'Yearly', 'pointly-booking' ) }</Badge> : null }
									{ ! h.is_enabled && <Badge tone="neutral" size="sm">{ __( 'Disabled', 'pointly-booking' ) }</Badge> }
								</span>
								<span className="pbk-reorder-row__meta">
									{ h.start_date === h.end_date ? fmt.date( h.start_date, 'medium' ) : `${ fmt.date( h.start_date, 'short' ) } – ${ fmt.date( h.end_date, 'short' ) }` } · { h.agent_id ? ( agentName[ h.agent_id ] || __( 'One staff member', 'pointly-booking' ) ) : __( 'Whole business', 'pointly-booking' ) }
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
										{
											label: __( 'Edit', 'pointly-booking' ),
											icon: 'edit',
											onClick: () => {
												setPrefill( { title: h.title, start_date: h.start_date, end_date: h.end_date, agent_id: h.agent_id || '', is_recurring: !! h.is_recurring_yearly, is_enabled: !! h.is_enabled } );
												setDrawerId( h.id );
											},
										},
									] }
								/>
							</span>
						</div>
					) ) }
				</div>
			) }

			<HolidayDrawer
				id={ drawerId === 'new' ? null : drawerId }
				open={ drawerId !== null }
				agents={ agents || [] }
				initial={ prefill }
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
