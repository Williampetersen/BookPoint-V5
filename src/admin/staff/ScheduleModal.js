/**
 * Per-staff working-hours editor: business vs custom weekly hours, plus dated time off.
 */
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Modal, Field, Input, Toggle, Tabs, Skeleton, Notice, useToast } from '../../ui';
import { useResource, client } from '../api';

const WEEKDAYS = [
	{ iso: 1, label: __( 'Monday', 'pointly-booking' ) },
	{ iso: 2, label: __( 'Tuesday', 'pointly-booking' ) },
	{ iso: 3, label: __( 'Wednesday', 'pointly-booking' ) },
	{ iso: 4, label: __( 'Thursday', 'pointly-booking' ) },
	{ iso: 5, label: __( 'Friday', 'pointly-booking' ) },
	{ iso: 6, label: __( 'Saturday', 'pointly-booking' ) },
	{ iso: 7, label: __( 'Sunday', 'pointly-booking' ) },
];

function toWeekMap( schedule ) {
	const map = {};
	WEEKDAYS.forEach( ( { iso } ) => {
		const intervals = schedule[ String( iso ) ] || [];
		const first = intervals[ 0 ];
		map[ iso ] = first
			? { enabled: first.is_enabled !== false, start: first.start || '09:00', end: first.end || '17:00', breakStart: ( first.breaks && first.breaks[ 0 ] && first.breaks[ 0 ].start ) || '', breakEnd: ( first.breaks && first.breaks[ 0 ] && first.breaks[ 0 ].end ) || '' }
			: { enabled: false, start: '09:00', end: '17:00', breakStart: '', breakEnd: '' };
	} );
	return map;
}

function toPayloadSchedule( map ) {
	const out = {};
	WEEKDAYS.forEach( ( { iso } ) => {
		const d = map[ iso ];
		if ( ! d || ! d.enabled ) {
			out[ iso ] = [];
			return;
		}
		const breaks = d.breakStart && d.breakEnd ? [ { start: d.breakStart, end: d.breakEnd } ] : [];
		out[ iso ] = [ { start: d.start, end: d.end, breaks, is_enabled: true } ];
	} );
	return out;
}

export default function ScheduleModal( { id, open, onClose } ) {
	const toast = useToast();
	const { data, loading, reload } = useResource( open && id ? `admin/agents/${ id }/schedule` : null );
	const [ mode, setMode ] = useState( 'business' );
	const [ week, setWeek ] = useState( () => toWeekMap( {} ) );
	const [ breaks, setBreaks ] = useState( [] );
	const [ saving, setSaving ] = useState( false );
	const [ copying, setCopying ] = useState( false );

	useEffect( () => {
		if ( data ) {
			setMode( data.mode || 'business' );
			setWeek( toWeekMap( data.schedule || {} ) );
			setBreaks( ( data.breaks || [] ).map( ( b ) => ( { ...b } ) ) );
		}
	}, [ data ] );

	const setDay = ( iso, patch ) => setWeek( ( prev ) => ( { ...prev, [ iso ]: { ...prev[ iso ], ...patch } } ) );

	const addBreak = () => setBreaks( ( prev ) => [ ...prev, { break_date: '', start_time: '09:00', end_time: '10:00', note: '' } ] );
	const updateBreak = ( index, patch ) => setBreaks( ( prev ) => prev.map( ( b, i ) => ( i === index ? { ...b, ...patch } : b ) ) );
	const removeBreak = ( index ) => setBreaks( ( prev ) => prev.filter( ( _, i ) => i !== index ) );

	const copyBusiness = async () => {
		setCopying( true );
		try {
			await client().post( `admin/agents/${ id }/schedule/copy` );
			toast.success( __( 'Business hours copied.', 'pointly-booking' ) );
			reload();
		} catch ( e ) {
			toast.error( e.message );
		} finally {
			setCopying( false );
		}
	};

	const save = async () => {
		setSaving( true );
		try {
			await client().put( `admin/agents/${ id }/schedule`, {
				mode,
				schedule: mode === 'custom' ? toPayloadSchedule( week ) : {},
				breaks: breaks.filter( ( b ) => b.break_date && b.start_time && b.end_time ),
			} );
			toast.success( __( 'Working hours saved.', 'pointly-booking' ) );
			onClose();
		} catch ( e ) {
			toast.error( e.message );
		} finally {
			setSaving( false );
		}
	};

	return (
		<Modal open={ open } onClose={ onClose } title={ __( 'Working hours', 'pointly-booking' ) } size="md">
			{ loading || ! data ? (
				<Skeleton variant="text" lines={ 8 } />
			) : (
				<div className="pbk-stack">
					<Tabs
						variant="pills"
						value={ mode }
						onChange={ setMode }
						label={ __( 'Hours mode', 'pointly-booking' ) }
						tabs={ [
							{ key: 'business', label: __( 'Business hours', 'pointly-booking' ) },
							{ key: 'custom', label: __( 'Custom hours', 'pointly-booking' ) },
						] }
					/>

					{ mode === 'business' && <Notice tone="info">{ __( 'This staff member follows the shared business hours set in Settings → Schedule.', 'pointly-booking' ) }</Notice> }

					{ mode === 'custom' && (
						<>
							<div className="pbk-row">
								<Button size="sm" variant="secondary" icon="copy" loading={ copying } onClick={ copyBusiness }>
									{ __( 'Copy business hours', 'pointly-booking' ) }
								</Button>
							</div>
							<div className="pbk-weekday-hours">
								{ WEEKDAYS.map( ( { iso, label } ) => {
									const d = week[ iso ];
									return (
										<div key={ iso } className="pbk-weekday-hours__row">
											<Toggle label={ label } checked={ d.enabled } onChange={ ( v ) => setDay( iso, { enabled: v } ) } />
											{ d.enabled && (
												<div className="pbk-weekday-hours__times">
													<span className="pbk-weekday-hours__pair">
														<input type="time" className="pbk-input" value={ d.start } onChange={ ( e ) => setDay( iso, { start: e.target.value } ) } />
														<span>–</span>
														<input type="time" className="pbk-input" value={ d.end } onChange={ ( e ) => setDay( iso, { end: e.target.value } ) } />
													</span>
													<span className="pbk-weekday-hours__pair">
														<span className="pbk-subtle">{ __( 'break', 'pointly-booking' ) }</span>
														<input type="time" className="pbk-input" value={ d.breakStart } onChange={ ( e ) => setDay( iso, { breakStart: e.target.value } ) } />
														<span>–</span>
														<input type="time" className="pbk-input" value={ d.breakEnd } onChange={ ( e ) => setDay( iso, { breakEnd: e.target.value } ) } />
													</span>
												</div>
											) }
										</div>
									);
								} ) }
							</div>
						</>
					) }

					<Field label={ __( 'Time off', 'pointly-booking' ) } help={ __( 'Specific dates this staff member is unavailable.', 'pointly-booking' ) }>
						<div className="pbk-stack">
							{ breaks.map( ( b, i ) => (
								<div key={ i } className="pbk-row">
									<input type="date" className="pbk-input" value={ b.break_date } onChange={ ( e ) => updateBreak( i, { break_date: e.target.value } ) } />
									<input type="time" className="pbk-input" value={ b.start_time } onChange={ ( e ) => updateBreak( i, { start_time: e.target.value } ) } />
									<span>–</span>
									<input type="time" className="pbk-input" value={ b.end_time } onChange={ ( e ) => updateBreak( i, { end_time: e.target.value } ) } />
									<Input placeholder={ __( 'Note (optional)', 'pointly-booking' ) } value={ b.note || '' } onChange={ ( e ) => updateBreak( i, { note: e.target.value } ) } />
									<Button size="sm" variant="ghost" icon="x" onClick={ () => removeBreak( i ) } aria-label={ __( 'Remove', 'pointly-booking' ) } />
								</div>
							) ) }
							<Button size="sm" variant="secondary" icon="plus" onClick={ addBreak }>
								{ __( 'Add time off', 'pointly-booking' ) }
							</Button>
						</div>
					</Field>

					<div className="pbk-drawer-footer">
						<Button variant="ghost" onClick={ onClose }>
							{ __( 'Cancel', 'pointly-booking' ) }
						</Button>
						<Button variant="primary" loading={ saving } onClick={ save }>
							{ __( 'Save hours', 'pointly-booking' ) }
						</Button>
					</div>
				</div>
			) }
		</Modal>
	);
}
