/**
 * Calendar: month / week / day / list views, filters, drag-and-drop reschedule, holidays.
 */
import { useMemo, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Button,
	IconButton,
	Select,
	SearchInput,
	Modal,
	Field,
	Input,
	DateInput,
	Toggle,
	Tabs,
	StatusBadge,
	STATUS_TONES,
	statusLabel,
	Skeleton,
	ErrorState,
	EmptyState,
	useToast,
} from '../../ui';
import { useResource, useDebouncedValue, client, fieldError } from '../api';
import { useAdminConfig, useAdminFormat } from '../context';
import { useRoute } from '../router';
import { PageHeader } from '../layout/PageHeader';
import BookingDrawer from '../bookings/BookingDrawer';
import { addDays, ymd, parseLocal } from '../../shared/format';
import './calendar.css';

const VIEWS = [
	{ key: 'month', label: __( 'Month', 'pointly-booking' ) },
	{ key: 'week', label: __( 'Week', 'pointly-booking' ) },
	{ key: 'day', label: __( 'Day', 'pointly-booking' ) },
	{ key: 'list', label: __( 'List', 'pointly-booking' ) },
];

const STATUSES = [ 'pending', 'confirmed', 'pending_payment', 'completed', 'cancelled', 'failed_payment' ];

const ROW_MIN = 30;
const ROW_HEIGHT = 32;

const pad = ( n ) => String( n ).padStart( 2, '0' );
const monthOf = ( date ) => String( date ).slice( 0, 7 );
const dateOfDatetime = ( dt ) => String( dt ).slice( 0, 10 );
const minutesOf = ( dt ) => {
	const [ h, m ] = String( dt ).slice( 11, 16 ).split( ':' ).map( Number );
	return h * 60 + m;
};

function shiftMonthStr( month, delta ) {
	const [ y, m ] = month.split( '-' ).map( Number );
	const d = new Date( Date.UTC( y, m - 1 + delta, 1 ) );
	return `${ d.getUTCFullYear() }-${ pad( d.getUTCMonth() + 1 ) }`;
}

function weekStartOf( date, weekStartsOn ) {
	const d = parseLocal( date );
	const day = d.getUTCDay();
	const diff = ( day - weekStartsOn + 7 ) % 7;
	d.setUTCDate( d.getUTCDate() - diff );
	return ymd( d );
}

function buildMonthGrid( month, weekStartsOn ) {
	const [ y, m ] = month.split( '-' ).map( Number );
	const offset = ( new Date( Date.UTC( y, m - 1, 1 ) ).getUTCDay() - weekStartsOn + 7 ) % 7;
	const total = new Date( Date.UTC( y, m, 0 ) ).getUTCDate();
	const weeks = Math.ceil( ( offset + total ) / 7 );
	const start = new Date( Date.UTC( y, m - 1, 1 - offset ) );
	const days = [];
	for ( let i = 0; i < weeks * 7; i++ ) {
		const d = new Date( Date.UTC( start.getUTCFullYear(), start.getUTCMonth(), start.getUTCDate() + i ) );
		const dateStr = ymd( d );
		days.push( { date: dateStr, inMonth: monthOf( dateStr ) === month } );
	}
	return days;
}

function computeHourRange( events ) {
	if ( ! events.length ) {
		return { start: 8, end: 18 };
	}
	let min = 24;
	let max = 0;
	events.forEach( ( e ) => {
		min = Math.min( min, Math.floor( minutesOf( e.start ) / 60 ) );
		max = Math.max( max, Math.ceil( minutesOf( e.end ) / 60 ) );
	} );
	min = Math.max( 0, min - 1 );
	max = Math.min( 24, Math.max( max + 1, min + 8 ) );
	return { start: min, end: max };
}

function packLanes( dayEvents ) {
	const sorted = [ ...dayEvents ].sort( ( a, b ) => a.start.localeCompare( b.start ) );
	const laneEnds = [];
	const placed = sorted.map( ( event ) => {
		const s = minutesOf( event.start );
		const en = Math.max( s + 15, minutesOf( event.end ) );
		let lane = laneEnds.findIndex( ( end ) => end <= s );
		if ( lane === -1 ) {
			lane = laneEnds.length;
			laneEnds.push( en );
		} else {
			laneEnds[ lane ] = en;
		}
		return { event, lane, s, en };
	} );
	const lanes = Math.max( 1, laneEnds.length );
	return placed.map( ( p ) => ( { ...p, lanes } ) );
}

function EventDot( { status } ) {
	return <span className={ `pbk-cal-chip__dot pbk-tone-${ STATUS_TONES[ status ] || 'neutral' }` } />;
}

function MonthGrid( { days, weekStartsOn, today, eventsByDate, fmt, dragged, hoverTarget, setHoverTarget, onDrop, onOpen, onOpenNew, onShowDay } ) {
	const weekdayLabels = useMemo( () => {
		const out = [];
		for ( let i = 0; i < 7; i++ ) {
			const date = `2023-01-${ pad( 1 + ( ( weekStartsOn + i ) % 7 ) ) }`;
			out.push( fmt.date( date, 'weekday' ) );
		}
		return out;
	}, [ weekStartsOn, fmt ] );

	return (
		<div className="pbk-cal-month">
			<div className="pbk-cal-month__weekdays">
				{ weekdayLabels.map( ( label, i ) => <span key={ i }>{ label }</span> ) }
			</div>
			<div className="pbk-cal-month__grid">
				{ days.map( ( day ) => {
					const dayEvents = eventsByDate[ day.date ] || [];
					const visible = dayEvents.slice( 0, 3 );
					const extra = dayEvents.length - visible.length;
					return (
						<div
							key={ day.date }
							className={ `pbk-cal-day ${ day.inMonth ? '' : 'is-out' } ${ day.date === today ? 'is-today' : '' } ${ hoverTarget === day.date ? 'is-drop-target' : '' }` }
							onDragOver={ ( e ) => e.preventDefault() }
							onDragEnter={ () => setHoverTarget( day.date ) }
							onDragLeave={ () => setHoverTarget( ( t ) => ( t === day.date ? null : t ) ) }
							onDrop={ ( e ) => {
								e.preventDefault();
								setHoverTarget( null );
								onDrop( day.date );
							} }
						>
							<button
								type="button"
								className="pbk-cal-day__num"
								onClick={ () => onOpenNew( day.date ) }
								aria-label={ sprintf( /* translators: %s: date */ __( 'Add a booking on %s', 'pointly-booking' ), day.date ) }
							>
								{ Number( day.date.slice( 8, 10 ) ) }
							</button>
							{ visible.map( ( event ) => (
								<button
									key={ event.id }
									type="button"
									draggable
									onDragStart={ () => {
										dragged.current = event;
									} }
									className="pbk-cal-chip"
									onClick={ () => onOpen( event.id ) }
								>
									<EventDot status={ event.status } />
									<span className="pbk-cal-chip__time">{ fmt.time( event.start.slice( 11, 16 ) ) }</span>
									<span className="pbk-cal-chip__title">{ event.customer_name || event.service_name }</span>
								</button>
							) ) }
							{ extra > 0 && (
								<button type="button" className="pbk-cal-more" onClick={ () => onShowDay( day.date ) }>
									{ sprintf(
										/* translators: %d: number of additional bookings */
										_n( '+%d more', '+%d more', extra, 'pointly-booking' ),
										extra
									) }
								</button>
							) }
						</div>
					);
				} ) }
			</div>
		</div>
	);
}

function TimeGrid( { days, today, eventsByDate, unavailableByDate, fmt, dragged, hoverTarget, setHoverTarget, onDrop, onOpen } ) {
	const allEvents = useMemo( () => days.flatMap( ( d ) => eventsByDate[ d ] || [] ), [ days, eventsByDate ] );
	const hourRange = useMemo( () => computeHourRange( allEvents ), [ allEvents ] );
	const totalRows = ( hourRange.end - hourRange.start ) * ( 60 / ROW_MIN );
	const gridHeight = totalRows * ROW_HEIGHT;
	const pxPerMin = ROW_HEIGHT / ROW_MIN;

	return (
		<div className="pbk-cal-timegrid">
			<div className="pbk-cal-timegrid__gutter">
				<div className="pbk-cal-col__head">&nbsp;</div>
				<div style={ { position: 'relative', height: gridHeight } }>
					{ Array.from( { length: hourRange.end - hourRange.start }, ( _, i ) => hourRange.start + i ).map( ( h ) => (
						<div
							key={ h }
							className="pbk-cal-timegrid__hour"
							style={ { position: 'absolute', insetInlineEnd: 0, insetInlineStart: 0, top: ( h - hourRange.start ) * 2 * ROW_HEIGHT, height: 2 * ROW_HEIGHT } }
						>
							{ fmt.time( `${ pad( h ) }:00` ) }
						</div>
					) ) }
				</div>
			</div>
			<div className="pbk-cal-timegrid__body">
				{ days.map( ( date ) => {
					const dayEvents = eventsByDate[ date ] || [];
					const packed = packLanes( dayEvents );
					const blocks = unavailableByDate[ date ] || [];
					const maxLanes = packed.reduce( ( max, p ) => Math.max( max, p.lanes ), 1 );
					return (
						<div key={ date } className={ `pbk-cal-col ${ date === today ? 'is-today' : '' }` } style={ { minWidth: Math.max( 120, maxLanes * 72 ) } }>
							<div className="pbk-cal-col__head">
								<div className="pbk-cal-col__head-day">{ fmt.date( date, 'weekday' ) }</div>
								<div className="pbk-cal-col__head-num">{ Number( date.slice( 8, 10 ) ) }</div>
							</div>
							<div className="pbk-cal-col__rows" style={ { height: gridHeight } }>
								{ blocks.map( ( block, i ) => {
									const top = ( minutesOf( block.start ) - hourRange.start * 60 ) * pxPerMin;
									const height = ( minutesOf( block.end ) - minutesOf( block.start ) ) * pxPerMin;
									return <div key={ i } className="pbk-cal-unavailable" style={ { top: Math.max( 0, top ), height: Math.max( 0, height ) } } />;
								} ) }
								{ Array.from( { length: totalRows }, ( _, i ) => i ).map( ( i ) => {
									const minute = hourRange.start * 60 + i * ROW_MIN;
									const timeStr = `${ pad( Math.floor( minute / 60 ) ) }:${ pad( minute % 60 ) }`;
									const key = `${ date }|${ timeStr }`;
									return (
										<div
											key={ i }
											className={ `pbk-cal-row ${ minute % 60 !== 0 ? 'is-half' : '' } ${ hoverTarget === key ? 'is-drop-target' : '' }` }
											style={ { height: ROW_HEIGHT } }
											onDragOver={ ( e ) => e.preventDefault() }
											onDragEnter={ () => setHoverTarget( key ) }
											onDragLeave={ () => setHoverTarget( ( t ) => ( t === key ? null : t ) ) }
											onDrop={ ( e ) => {
												e.preventDefault();
												setHoverTarget( null );
												onDrop( date, timeStr );
											} }
										/>
									);
								} ) }
								{ packed.map( ( { event, lane, s, en, lanes } ) => (
									<button
										key={ event.id }
										type="button"
										draggable
										onDragStart={ () => {
											dragged.current = event;
										} }
										className="pbk-cal-event"
										style={ {
											top: ( s - hourRange.start * 60 ) * pxPerMin,
											height: Math.max( ROW_HEIGHT * 0.7, ( en - s ) * pxPerMin ),
											insetInlineStart: `calc(${ ( lane * 100 ) / lanes }% + 2px)`,
											insetInlineEnd: 'auto',
											width: `calc(${ 100 / lanes }% - 4px)`,
											borderInlineStartColor: `var(--pbk-${ STATUS_TONES[ event.status ] || 'text-subtle' })`,
										} }
										onClick={ () => onOpen( event.id ) }
									>
										<span className="pbk-cal-event__time">{ fmt.time( event.start.slice( 11, 16 ) ) }</span>
										<span className="pbk-cal-event__title">{ event.customer_name || event.service_name }</span>
									</button>
								) ) }
							</div>
						</div>
					);
				} ) }
			</div>
		</div>
	);
}

function AgendaView( { days, eventsByDate, fmt, onOpen } ) {
	const withEvents = days.filter( ( d ) => ( eventsByDate[ d ] || [] ).length );
	if ( ! withEvents.length ) {
		return <EmptyState icon="calendar" title={ __( 'No bookings in this period.', 'pointly-booking' ) } />;
	}
	return (
		<div className="pbk-cal-agenda">
			{ withEvents.map( ( date ) => (
				<div key={ date }>
					<h3 className="pbk-cal-agenda__day-title">{ fmt.date( date, 'long' ) }</h3>
					<div className="pbk-cal-agenda__list">
						{ eventsByDate[ date ].map( ( event ) => (
							<button key={ event.id } type="button" className="pbk-cal-agenda__row" onClick={ () => onOpen( event.id ) }>
								<EventDot status={ event.status } />
								<span className="pbk-cal-agenda__time">{ fmt.time( event.start.slice( 11, 16 ) ) }</span>
								<span className="pbk-cal-agenda__text">
									<span className="pbk-cal-agenda__title">{ event.customer_name || __( '(no name)', 'pointly-booking' ) }</span>
									<span className="pbk-cal-agenda__meta">
										{ event.service_name } · { event.agent_name || '—' }
									</span>
								</span>
								<StatusBadge status={ event.status } size="sm" />
							</button>
						) ) }
					</div>
				</div>
			) ) }
		</div>
	);
}

function AddHolidayModal( { open, onClose, onSaved } ) {
	const toast = useToast();
	const { data: agents } = useResource( open ? 'admin/agents' : null );
	const [ form, setForm ] = useState( { title: '', start_date: '', end_date: '', agent_id: '', is_recurring: false, is_enabled: true } );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( null );
	const update = ( patch ) => setForm( ( prev ) => ( { ...prev, ...patch } ) );

	const submit = async () => {
		setSaving( true );
		setError( null );
		try {
			await client().post( 'admin/holidays', {
				title: form.title,
				start_date: form.start_date,
				end_date: form.end_date || form.start_date,
				agent_id: form.agent_id ? Number( form.agent_id ) : 0,
				is_recurring: form.is_recurring,
				is_enabled: form.is_enabled,
			} );
			toast.success( __( 'Holiday added.', 'pointly-booking' ) );
			setForm( { title: '', start_date: '', end_date: '', agent_id: '', is_recurring: false, is_enabled: true } );
			onSaved();
			onClose();
		} catch ( e ) {
			setError( e );
		} finally {
			setSaving( false );
		}
	};

	return (
		<Modal
			open={ open }
			onClose={ onClose }
			title={ __( 'Add a holiday', 'pointly-booking' ) }
			size="sm"
			footer={
				<>
					<Button variant="ghost" onClick={ onClose }>
						{ __( 'Cancel', 'pointly-booking' ) }
					</Button>
					<Button variant="primary" loading={ saving } disabled={ ! form.title || ! form.start_date } onClick={ submit }>
						{ __( 'Add holiday', 'pointly-booking' ) }
					</Button>
				</>
			}
		>
			<div className="pbk-stack">
				<Field label={ __( 'Title', 'pointly-booking' ) } required error={ fieldError( error, 'title' ) }>
					<Input value={ form.title } onChange={ ( e ) => update( { title: e.target.value } ) } placeholder={ __( 'e.g. Christmas', 'pointly-booking' ) } />
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
					<Select
						value={ form.agent_id }
						onChange={ ( e ) => update( { agent_id: e.target.value } ) }
						placeholder={ __( 'Whole business', 'pointly-booking' ) }
						options={ ( agents || [] ).map( ( a ) => ( { value: a.id, label: a.name } ) ) }
					/>
				</Field>
				<Toggle label={ __( 'Repeat every year', 'pointly-booking' ) } checked={ form.is_recurring } onChange={ ( v ) => update( { is_recurring: v } ) } />
				<Toggle label={ __( 'Enabled', 'pointly-booking' ) } checked={ form.is_enabled } onChange={ ( v ) => update( { is_enabled: v } ) } />
			</div>
		</Modal>
	);
}

export default function CalendarScreen() {
	const config = useAdminConfig();
	const fmt = useAdminFormat();
	const toast = useToast();
	const { query, setParams } = useRoute();

	const weekStartsOn = config.weekStartsOn ?? 1;
	const today = config.today || ymd( new Date() );

	const view = query.cal || 'month';
	const anchor = query.date || today;

	const [ agentId, setAgentId ] = useState( '' );
	const [ status, setStatus ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const debouncedSearch = useDebouncedValue( search, 350 );
	const [ holidayOpen, setHolidayOpen ] = useState( false );
	const [ hoverTarget, setHoverTarget ] = useState( null );
	const dragged = useRef( null );
	const { data: agents } = useResource( 'admin/agents' );

	const range = useMemo( () => {
		if ( view === 'month' ) {
			const days = buildMonthGrid( monthOf( anchor ), weekStartsOn );
			return { start: days[ 0 ].date, end: days[ days.length - 1 ].date, days: days.map( ( d ) => d.date ), grid: days };
		}
		if ( view === 'week' ) {
			const start = weekStartOf( anchor, weekStartsOn );
			const days = Array.from( { length: 7 }, ( _, i ) => addDays( start, i ) );
			return { start, end: days[ 6 ], days };
		}
		if ( view === 'day' ) {
			return { start: anchor, end: anchor, days: [ anchor ] };
		}
		const days = Array.from( { length: 14 }, ( _, i ) => addDays( anchor, i ) );
		return { start: anchor, end: days[ 13 ], days };
	}, [ view, anchor, weekStartsOn ] );

	let drawerId = null;
	if ( query.view === 'new' ) {
		drawerId = 'new';
	} else if ( query.view === 'edit' && query.id ) {
		drawerId = Number( query.id );
	}
	const closeDrawer = () => setParams( { view: null, id: null, pdate: null } );

	const params = {
		start: range.start,
		end: range.end,
		agent_id: agentId || undefined,
		status: status || undefined,
		search: debouncedSearch || undefined,
	};
	const { data: events, loading, error, reload } = useResource( 'admin/calendar', params );

	const showUnavailable = !! agentId && ( view === 'week' || view === 'day' );
	const { data: unavailable } = useResource( showUnavailable ? 'admin/schedule/unavailable' : null, { start: range.start, end: range.end, agent_id: agentId } );

	const eventsByDate = useMemo( () => {
		const map = {};
		( events || [] ).forEach( ( e ) => {
			const d = dateOfDatetime( e.start );
			( map[ d ] = map[ d ] || [] ).push( e );
		} );
		Object.values( map ).forEach( ( list ) => list.sort( ( a, b ) => a.start.localeCompare( b.start ) ) );
		return map;
	}, [ events ] );

	const unavailableByDate = useMemo( () => {
		const map = {};
		( unavailable || [] ).forEach( ( b ) => {
			const d = dateOfDatetime( b.start );
			( map[ d ] = map[ d ] || [] ).push( b );
		} );
		return map;
	}, [ unavailable ] );

	const shift = ( dir ) => {
		if ( view === 'month' ) {
			return `${ shiftMonthStr( monthOf( anchor ), dir ) }-01`;
		}
		if ( view === 'week' ) {
			return addDays( weekStartOf( anchor, weekStartsOn ), dir * 7 );
		}
		if ( view === 'day' ) {
			return addDays( anchor, dir );
		}
		return addDays( anchor, dir * 14 );
	};

	const periodLabel = useMemo( () => {
		if ( view === 'month' ) {
			return fmt.date( `${ monthOf( anchor ) }-01`, 'month' );
		}
		if ( view === 'week' ) {
			return `${ fmt.date( range.start, 'short' ) } – ${ fmt.date( range.end, 'short' ) }`;
		}
		if ( view === 'day' ) {
			return fmt.date( anchor, 'long' );
		}
		return `${ fmt.date( range.start, 'short' ) } – ${ fmt.date( range.end, 'short' ) }`;
	}, [ view, anchor, range, fmt ] );

	const openBooking = ( id ) => setParams( { view: 'edit', id } );
	const openNew = ( date ) => setParams( { view: 'new', pdate: date || today } );
	const showDay = ( date ) => setParams( { cal: 'day', date } );

	const commitReschedule = async ( id, date, start ) => {
		try {
			await client().patch( `admin/bookings/${ id }`, { date, start } );
			toast.success( __( 'Booking rescheduled.', 'pointly-booking' ) );
			reload();
		} catch ( e ) {
			toast.error( e.message );
		}
	};

	const handleMonthDrop = ( date ) => {
		const b = dragged.current;
		dragged.current = null;
		if ( ! b || dateOfDatetime( b.start ) === date ) {
			return;
		}
		commitReschedule( b.id, date, b.start.slice( 11, 16 ) );
	};

	const handleGridDrop = ( date, time ) => {
		const b = dragged.current;
		dragged.current = null;
		if ( ! b || ( dateOfDatetime( b.start ) === date && b.start.slice( 11, 16 ) === time ) ) {
			return;
		}
		commitReschedule( b.id, date, time );
	};

	return (
		<div>
			<PageHeader
				title={ __( 'Calendar', 'pointly-booking' ) }
				description={ __( 'Drag a booking to reschedule it, or click one to see details.', 'pointly-booking' ) }
				actions={
					<>
						<Button variant="secondary" icon="calendar-plus" onClick={ () => setHolidayOpen( true ) }>
							{ __( 'Add holiday', 'pointly-booking' ) }
						</Button>
						<Button variant="primary" icon="plus" onClick={ () => openNew() }>
							{ __( 'New booking', 'pointly-booking' ) }
						</Button>
					</>
				}
			/>

			<div className="pbk-cal-toolbar">
				<div className="pbk-cal-nav">
					<Button size="sm" variant="secondary" onClick={ () => setParams( { date: today, cal: view === 'month' ? 'month' : view } ) }>
						{ __( 'Today', 'pointly-booking' ) }
					</Button>
					<IconButton size="sm" variant="ghost" icon="chevron-left" label={ __( 'Previous', 'pointly-booking' ) } onClick={ () => setParams( { date: shift( -1 ) } ) } />
					<IconButton size="sm" variant="ghost" icon="chevron-right" label={ __( 'Next', 'pointly-booking' ) } onClick={ () => setParams( { date: shift( 1 ) } ) } />
					<span className="pbk-cal-nav__label">{ periodLabel }</span>
				</div>

				<div className="pbk-cal-toolbar__spacer" />

				<div className="pbk-cal-toolbar__filters">
					<Select
						value={ agentId }
						onChange={ ( e ) => setAgentId( e.target.value ) }
						placeholder={ __( 'All staff', 'pointly-booking' ) }
						options={ ( agents || [] ).map( ( a ) => ( { value: a.id, label: a.name } ) ) }
					/>
					<Select
						value={ status }
						onChange={ ( e ) => setStatus( e.target.value ) }
						placeholder={ __( 'All statuses', 'pointly-booking' ) }
						options={ STATUSES.map( ( s ) => ( { value: s, label: statusLabel( s ) } ) ) }
					/>
					<div className="pbk-cal-toolbar__search">
						<SearchInput label={ __( 'Search', 'pointly-booking' ) } value={ search } onChange={ ( e ) => setSearch( e.target.value ) } onClear={ () => setSearch( '' ) } />
					</div>
				</div>
			</div>

			<div className="pbk-toolbar">
				<Tabs variant="pills" value={ view } onChange={ ( key ) => setParams( { cal: key } ) } label={ __( 'View', 'pointly-booking' ) } tabs={ VIEWS } />
			</div>

			<div className="pbk-cal-legend">
				{ STATUSES.map( ( s ) => (
					<span key={ s } className="pbk-cal-legend__item">
						<span className={ `pbk-cal-legend__dot pbk-tone-${ STATUS_TONES[ s ] || 'neutral' }` } />
						{ statusLabel( s ) }
					</span>
				) ) }
			</div>

			{ error && ! events && <ErrorState message={ error } onRetry={ reload } /> }
			{ loading && ! events && <Skeleton variant="block" height={ 420 } /> }
			{ ! ( error && ! events ) && ! ( loading && ! events ) && (
				<>
					{ view === 'month' && (
						<MonthGrid
							days={ range.grid }
							weekStartsOn={ weekStartsOn }
							today={ today }
							eventsByDate={ eventsByDate }
							fmt={ fmt }
							dragged={ dragged }
							hoverTarget={ hoverTarget }
							setHoverTarget={ setHoverTarget }
							onDrop={ handleMonthDrop }
							onOpen={ openBooking }
							onOpenNew={ openNew }
							onShowDay={ showDay }
						/>
					) }
					{ ( view === 'week' || view === 'day' ) && (
						<TimeGrid
							days={ range.days }
							today={ today }
							eventsByDate={ eventsByDate }
							unavailableByDate={ unavailableByDate }
							fmt={ fmt }
							dragged={ dragged }
							hoverTarget={ hoverTarget }
							setHoverTarget={ setHoverTarget }
							onDrop={ handleGridDrop }
							onOpen={ openBooking }
						/>
					) }
					{ view === 'list' && <AgendaView days={ range.days } eventsByDate={ eventsByDate } fmt={ fmt } onOpen={ openBooking } /> }
				</>
			) }

			<BookingDrawer
				id={ drawerId === 'new' ? null : drawerId }
				open={ drawerId !== null }
				prefillDate={ query.pdate || '' }
				onClose={ closeDrawer }
				onSaved={ reload }
				onDeleted={ () => {
					closeDrawer();
					reload();
				} }
			/>

			<AddHolidayModal open={ holidayOpen } onClose={ () => setHolidayOpen( false ) } onSaved={ () => {} } />
		</div>
	);
}
