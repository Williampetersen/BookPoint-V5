/**
 * Settings → Schedule: global weekly hours (multiple intervals + breaks per day) and slot interval.
 */
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Field, Input, Skeleton, Notice, ErrorState, useToast } from '../../ui';
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

function DayEditor( { label, intervals, onChange } ) {
	const addInterval = () => onChange( [ ...intervals, { start: '09:00', end: '17:00', breaks: [], is_enabled: true } ] );
	const updateInterval = ( i, patch ) => onChange( intervals.map( ( iv, idx ) => ( idx === i ? { ...iv, ...patch } : iv ) ) );
	const removeInterval = ( i ) => onChange( intervals.filter( ( _, idx ) => idx !== i ) );
	const addBreak = ( i ) => updateInterval( i, { breaks: [ ...intervals[ i ].breaks, { start: '12:00', end: '13:00' } ] } );
	const updateBreak = ( i, bi, patch ) => updateInterval( i, { breaks: intervals[ i ].breaks.map( ( b, idx ) => ( idx === bi ? { ...b, ...patch } : b ) ) } );
	const removeBreak = ( i, bi ) => updateInterval( i, { breaks: intervals[ i ].breaks.filter( ( _, idx ) => idx !== bi ) } );

	return (
		<div className="pbk-schedule-day">
			<div className="pbk-schedule-day__label">{ label }</div>
			<div className="pbk-schedule-day__body">
				{ intervals.length === 0 && <span className="pbk-subtle">{ __( 'Closed', 'pointly-booking' ) }</span> }
				{ intervals.map( ( iv, i ) => (
					<div key={ i } className="pbk-schedule-interval">
						<div className="pbk-row">
							<input type="time" className="pbk-input" value={ iv.start } onChange={ ( e ) => updateInterval( i, { start: e.target.value } ) } />
							<span>–</span>
							<input type="time" className="pbk-input" value={ iv.end } onChange={ ( e ) => updateInterval( i, { end: e.target.value } ) } />
							<Button size="sm" variant="ghost" icon="x" aria-label={ __( 'Remove interval', 'pointly-booking' ) } onClick={ () => removeInterval( i ) } />
						</div>
						{ iv.breaks.map( ( b, bi ) => (
							<div key={ bi } className="pbk-row pbk-schedule-break">
								<span className="pbk-subtle">{ __( 'break', 'pointly-booking' ) }</span>
								<input type="time" className="pbk-input" value={ b.start } onChange={ ( e ) => updateBreak( i, bi, { start: e.target.value } ) } />
								<span>–</span>
								<input type="time" className="pbk-input" value={ b.end } onChange={ ( e ) => updateBreak( i, bi, { end: e.target.value } ) } />
								<Button size="sm" variant="ghost" icon="x" aria-label={ __( 'Remove break', 'pointly-booking' ) } onClick={ () => removeBreak( i, bi ) } />
							</div>
						) ) }
						<Button size="sm" variant="ghost" icon="plus" onClick={ () => addBreak( i ) }>
							{ __( 'Add break', 'pointly-booking' ) }
						</Button>
					</div>
				) ) }
				<Button size="sm" variant="secondary" icon="plus" onClick={ addInterval }>
					{ __( 'Add hours', 'pointly-booking' ) }
				</Button>
			</div>
		</div>
	);
}

export default function ScheduleTab() {
	const toast = useToast();
	const { data, error, reload } = useResource( 'admin/schedule' );
	const [ week, setWeek ] = useState( null );
	const [ slotInterval, setSlotInterval ] = useState( 30 );
	const [ saving, setSaving ] = useState( false );

	useEffect( () => {
		if ( data && ! week ) {
			const w = {};
			WEEKDAYS.forEach( ( { iso } ) => {
				w[ iso ] = ( data.schedule[ String( iso ) ] || [] ).map( ( iv ) => ( { start: iv.start, end: iv.end, breaks: iv.breaks || [], is_enabled: true } ) );
			} );
			setWeek( w );
			setSlotInterval( data.settings.slot_interval_minutes );
		}
	}, [ data, week ] );

	const applyPreset = ( preset ) => {
		const w = {};
		WEEKDAYS.forEach( ( { iso } ) => {
			w[ iso ] = [];
		} );
		if ( preset === 'weekdays-9-17' ) {
			[ 1, 2, 3, 4, 5 ].forEach( ( d ) => ( w[ d ] = [ { start: '09:00', end: '17:00', breaks: [], is_enabled: true } ] ) );
		} else if ( preset === 'weekdays-8-16' ) {
			[ 1, 2, 3, 4, 5 ].forEach( ( d ) => ( w[ d ] = [ { start: '08:00', end: '16:00', breaks: [], is_enabled: true } ] ) );
		} else if ( preset === 'every-day-9-17' ) {
			[ 1, 2, 3, 4, 5, 6, 7 ].forEach( ( d ) => ( w[ d ] = [ { start: '09:00', end: '17:00', breaks: [], is_enabled: true } ] ) );
		}
		setWeek( w );
	};

	const save = async () => {
		setSaving( true );
		try {
			const schedule = {};
			WEEKDAYS.forEach( ( { iso } ) => {
				schedule[ iso ] = week[ iso ];
			} );
			await client().post( 'admin/schedule', { schedule, settings: { slot_interval_minutes: slotInterval } } );
			toast.success( __( 'Schedule saved.', 'pointly-booking' ) );
			reload();
		} catch ( e ) {
			toast.error( e.message );
		} finally {
			setSaving( false );
		}
	};

	if ( error && ! data ) {
		return <ErrorState message={ error } onRetry={ reload } />;
	}
	if ( ! week ) {
		return <Skeleton variant="block" height={ 320 } />;
	}

	return (
		<div className="pbk-design__panel">
			{ data.source === 'legacy' && <Notice tone="info">{ __( 'Showing hours migrated from the legacy settings. Save to switch to the new schedule.', 'pointly-booking' ) }</Notice> }
			<div className="pbk-row">
				<Button size="sm" variant="secondary" onClick={ () => applyPreset( 'weekdays-9-17' ) }>
					{ __( 'Mon–Fri 9–5', 'pointly-booking' ) }
				</Button>
				<Button size="sm" variant="secondary" onClick={ () => applyPreset( 'weekdays-8-16' ) }>
					{ __( 'Mon–Fri 8–4', 'pointly-booking' ) }
				</Button>
				<Button size="sm" variant="secondary" onClick={ () => applyPreset( 'every-day-9-17' ) }>
					{ __( 'Every day 9–5', 'pointly-booking' ) }
				</Button>
				<Button size="sm" variant="ghost" onClick={ () => applyPreset( 'clear' ) }>
					{ __( 'Clear', 'pointly-booking' ) }
				</Button>
			</div>

			<div className="pbk-schedule-week">
				{ WEEKDAYS.map( ( { iso, label } ) => (
					<DayEditor key={ iso } label={ label } intervals={ week[ iso ] } onChange={ ( intervals ) => setWeek( { ...week, [ iso ]: intervals } ) } />
				) ) }
			</div>

			<div className="pbk-drawer-form__grid">
				<Field label={ __( 'Slot interval (minutes)', 'pointly-booking' ) }>
					<Input type="number" min={ 5 } max={ 120 } step={ 5 } value={ slotInterval } onChange={ ( e ) => setSlotInterval( Number( e.target.value ) ) } />
				</Field>
				<Field label={ __( 'Timezone', 'pointly-booking' ) }>
					<Input value={ data.settings.timezone } disabled />
				</Field>
			</div>

			<div className="pbk-drawer-footer">
				<Button variant="primary" loading={ saving } onClick={ save }>
					{ __( 'Save changes', 'pointly-booking' ) }
				</Button>
			</div>
		</div>
	);
}
