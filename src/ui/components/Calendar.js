/**
 * Calendar (month grid) and DateInput (calendar in a popover).
 */
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Icon } from '../icons';
import { Popover } from './Popover';
import { formatDate, parseLocal, ymd } from '../../shared/format';
import './calendar.css';

const pad = ( n ) => String( n ).padStart( 2, '0' );

export function monthOf( date ) {
	return String( date ).slice( 0, 7 );
}

export function shiftMonth( month, delta ) {
	const [ y, m ] = month.split( '-' ).map( Number );
	const d = new Date( Date.UTC( y, m - 1 + delta, 1 ) );
	return `${ d.getUTCFullYear() }-${ pad( d.getUTCMonth() + 1 ) }`;
}

function shiftDay( date, delta ) {
	const d = parseLocal( date );
	d.setUTCDate( d.getUTCDate() + delta );
	return ymd( d );
}

function daysInMonth( month ) {
	const [ y, m ] = month.split( '-' ).map( Number );
	return new Date( Date.UTC( y, m, 0 ) ).getUTCDate();
}

/**
 * Weeks of a month as arrays of 7 "Y-m-d" strings or null (padding).
 *
 * @param {string} month        Y-m.
 * @param {number} weekStartsOn 0 = Sunday.
 * @return {Array} Weeks.
 */
export function monthMatrix( month, weekStartsOn = 1 ) {
	const first = parseLocal( `${ month }-01` );
	const offset = ( first.getUTCDay() - weekStartsOn + 7 ) % 7;
	const total = daysInMonth( month );
	const cells = Array( offset ).fill( null );
	for ( let day = 1; day <= total; day++ ) {
		cells.push( `${ month }-${ pad( day ) }` );
	}
	while ( cells.length % 7 ) {
		cells.push( null );
	}
	const weeks = [];
	for ( let i = 0; i < cells.length; i += 7 ) {
		weeks.push( cells.slice( i, i + 7 ) );
	}
	return weeks;
}

/**
 * @param {Object}   props
 * @param {string}   props.month          Visible month (Y-m).
 * @param {Function} props.onMonthChange  ( month ) => void.
 * @param {string}   props.value          Selected date.
 * @param {Function} props.onChange       ( date ) => void.
 * @param {Function} props.isDateDisabled ( date ) => boolean.
 * @param {Function} props.getDayInfo     ( date ) => { dots: 0-3, label: string }.
 * @param {string}   props.today          Today in the business time zone.
 * @param {string}   props.min            First selectable month boundary (date).
 * @param {string}   props.max            Last selectable date.
 * @param {number}   props.weekStartsOn   0 = Sunday.
 * @param {string}   props.locale         BCP 47 locale.
 * @param {boolean}  props.loading        Show a loading shimmer on the days.
 * @return {*} Calendar.
 */
export function Calendar( {
	month,
	onMonthChange,
	value,
	onChange,
	isDateDisabled = () => false,
	getDayInfo,
	today,
	min,
	max,
	weekStartsOn = 1,
	locale = 'en',
	loading = false,
	className = '',
	label,
	variant = 'availability',
} ) {
	const gridRef = useRef( null );
	const [ focused, setFocused ] = useState( value || null );
	const keyboardMove = useRef( false );
	const weeks = useMemo(
		() => monthMatrix( month, weekStartsOn ),
		[ month, weekStartsOn ]
	);
	const currentToday = today || ymd( new Date() );

	const weekdays = useMemo( () => {
		const out = [];
		// 2023-01-01 was a Sunday.
		for ( let i = 0; i < 7; i++ ) {
			const date = `2023-01-${ pad( 1 + ( ( weekStartsOn + i ) % 7 ) ) }`;
			out.push( {
				short: formatDate( date, { locale, style: 'weekday' } ),
				long: formatDate( date, { locale, style: 'weekdayLong' } ),
			} );
		}
		return out;
	}, [ weekStartsOn, locale ] );

	const canPrev = ! min || shiftMonth( month, -1 ) >= monthOf( min );
	const canNext = ! max || shiftMonth( month, 1 ) <= monthOf( max );

	// The focusable day: focused (if in month), else selected, else today, else first enabled.
	const days = weeks.flat().filter( Boolean );
	const tabbable =
		[ focused, value, currentToday ].find(
			( date ) =>
				date && monthOf( date ) === month && days.includes( date )
		) ||
		days.find( ( date ) => ! isDateDisabled( date ) ) ||
		days[ 0 ];

	useEffect( () => {
		if ( keyboardMove.current && gridRef.current && focused ) {
			const button = gridRef.current.querySelector(
				`[data-date="${ focused }"]`
			);
			if ( button ) {
				button.focus();
			}
			keyboardMove.current = false;
		}
	}, [ focused, month ] );

	const move = ( date ) => {
		if (
			( min && date < min && monthOf( date ) < monthOf( min ) ) ||
			( max && date > max && monthOf( date ) > monthOf( max ) )
		) {
			return;
		}
		keyboardMove.current = true;
		setFocused( date );
		if ( monthOf( date ) !== month && onMonthChange ) {
			onMonthChange( monthOf( date ) );
		}
	};

	const onKeyDown = ( event, date ) => {
		const rowStart =
			( parseLocal( date ).getUTCDay() - weekStartsOn + 7 ) % 7;
		const rtl = getComputedStyle( event.currentTarget ).direction === 'rtl';
		const map = {
			ArrowLeft: rtl ? 1 : -1,
			ArrowRight: rtl ? -1 : 1,
			ArrowUp: -7,
			ArrowDown: 7,
			Home: -rowStart,
			End: 6 - rowStart,
		};
		if ( map[ event.key ] !== undefined ) {
			event.preventDefault();
			move( shiftDay( date, map[ event.key ] ) );
		} else if ( event.key === 'PageUp' || event.key === 'PageDown' ) {
			event.preventDefault();
			const target = shiftMonth(
				monthOf( date ),
				event.key === 'PageUp' ? -1 : 1
			);
			const day = Math.min(
				Number( date.slice( 8 ) ),
				daysInMonth( target )
			);
			move( `${ target }-${ pad( day ) }` );
		}
	};

	const title = formatDate( `${ month }-01`, { locale, style: 'month' } );

	return (
		<div
			className={ `pbk-calendar pbk-calendar--${ variant } ${
				loading ? 'is-loading' : ''
			} ${ className }`.trim() }
		>
			<div className="pbk-calendar__header">
				<h3 className="pbk-calendar__title" aria-live="polite">
					{ title }
				</h3>
				<div className="pbk-calendar__nav">
					<button
						type="button"
						className="pbk-calendar__nav-btn"
						onClick={ () =>
							onMonthChange( shiftMonth( month, -1 ) )
						}
						disabled={ ! canPrev }
						aria-label={ __( 'Previous month', 'pointly-booking' ) }
					>
						<Icon
							name="chevron-left"
							size={ 20 }
							className="pbk-flip-rtl"
						/>
					</button>
					<button
						type="button"
						className="pbk-calendar__nav-btn"
						onClick={ () =>
							onMonthChange( shiftMonth( month, 1 ) )
						}
						disabled={ ! canNext }
						aria-label={ __( 'Next month', 'pointly-booking' ) }
					>
						<Icon
							name="chevron-right"
							size={ 20 }
							className="pbk-flip-rtl"
						/>
					</button>
				</div>
			</div>
			<table
				ref={ gridRef }
				className="pbk-calendar__grid"
				role="grid"
				aria-label={ label || title }
				aria-busy={ loading || undefined }
			>
				<thead>
					<tr>
						{ weekdays.map( ( day ) => (
							<th
								key={ day.long }
								scope="col"
								abbr={ day.long }
								className="pbk-calendar__weekday"
							>
								<span aria-hidden="true">{ day.short }</span>
								<span className="pbk-sr-only">
									{ day.long }
								</span>
							</th>
						) ) }
					</tr>
				</thead>
				<tbody>
					{ weeks.map( ( week, row ) => (
						<tr key={ row }>
							{ week.map( ( date, col ) => {
								if ( ! date ) {
									return (
										<td
											key={ `e${ col }` }
											className="pbk-calendar__cell is-empty"
											aria-hidden="true"
										/>
									);
								}
								const disabled =
									loading || isDateDisabled( date );
								const selected = value === date;
								const isToday = date === currentToday;
								const info = getDayInfo
									? getDayInfo( date ) || {}
									: {};
								const dots = Math.max(
									0,
									Math.min( 3, info.dots || 0 )
								);
								const longLabel = formatDate( date, {
									locale,
									style: 'long',
								} );
								return (
									<td
										key={ date }
										className="pbk-calendar__cell"
										role="gridcell"
										aria-selected={ selected }
									>
										<button
											type="button"
											data-date={ date }
											className={ `pbk-calendar__day ${
												selected ? 'is-selected' : ''
											} ${ isToday ? 'is-today' : '' } ${
												dots ? 'has-dots' : ''
											}`.trim() }
											tabIndex={
												date === tabbable ? 0 : -1
											}
											aria-disabled={
												disabled || undefined
											}
											aria-current={
												isToday ? 'date' : undefined
											}
											aria-label={ [
												longLabel,
												isToday
													? __(
															'Today',
															'pointly-booking'
													  )
													: '',
												info.label || '',
												! loading &&
												isDateDisabled( date )
													? __(
															'Not available',
															'pointly-booking'
													  )
													: '',
											]
												.filter( Boolean )
												.join( ', ' ) }
											onClick={ () => {
												setFocused( date );
												if ( ! disabled && onChange ) {
													onChange( date );
												}
											} }
											onFocus={ () => setFocused( date ) }
											onKeyDown={ ( event ) =>
												onKeyDown( event, date )
											}
										>
											<span
												className="pbk-calendar__num"
												aria-hidden="true"
											>
												{ Number( date.slice( 8 ) ) }
											</span>
											{ dots > 0 && (
												<span
													className="pbk-calendar__dots"
													aria-hidden="true"
												>
													{ Array.from( {
														length: dots,
													} ).map( ( _, i ) => (
														<span key={ i } />
													) ) }
												</span>
											) }
										</button>
									</td>
								);
							} ) }
						</tr>
					) ) }
				</tbody>
			</table>
		</div>
	);
}

/**
 * Date field that opens a calendar popover (admin forms).
 *
 * @param {Object}   props
 * @param {string}   props.value    Y-m-d.
 * @param {Function} props.onChange ( date ) => void.
 * @return {*} Input.
 */
export function DateInput( {
	value,
	onChange,
	locale = 'en',
	weekStartsOn = 1,
	min,
	max,
	placeholder,
	id,
	invalid,
	disabled,
	isDateDisabled,
	...rest
} ) {
	const [ open, setOpen ] = useState( false );
	const [ month, setMonth ] = useState(
		monthOf( value || ymd( new Date() ) )
	);
	const anchorRef = useRef( null );

	useEffect( () => {
		if ( value ) {
			setMonth( monthOf( value ) );
		}
	}, [ value ] );

	return (
		<>
			<button
				ref={ anchorRef }
				id={ id }
				type="button"
				className={ `pbk-input pbk-date-input ${
					invalid ? 'is-invalid' : ''
				}`.trim() }
				aria-haspopup="dialog"
				aria-expanded={ open }
				disabled={ disabled }
				onClick={ () => setOpen( ( current ) => ! current ) }
				{ ...rest }
			>
				<Icon name="calendar" size={ 18 } />
				<span className={ value ? '' : 'pbk-subtle' }>
					{ value
						? formatDate( value, { locale, style: 'medium' } )
						: placeholder ||
						  __( 'Choose a date', 'pointly-booking' ) }
				</span>
			</button>
			<Popover
				open={ open }
				anchorRef={ anchorRef }
				onClose={ () => setOpen( false ) }
				label={ __( 'Choose a date', 'pointly-booking' ) }
				className="pbk-date-popover"
			>
				<Calendar
					month={ month }
					onMonthChange={ setMonth }
					value={ value }
					variant="plain"
					locale={ locale }
					weekStartsOn={ weekStartsOn }
					min={ min }
					max={ max }
					isDateDisabled={ ( date ) =>
						( min && date < min ) ||
						( max && date > max ) ||
						( isDateDisabled ? isDateDisabled( date ) : false )
					}
					onChange={ ( date ) => {
						onChange( date );
						setOpen( false );
						if ( anchorRef.current ) {
							anchorRef.current.focus();
						}
					} }
				/>
			</Popover>
		</>
	);
}

/**
 * Screen-reader summary of a day's availability, e.g. "5 times available".
 *
 * @param {number} count Slots.
 * @return {string} Label.
 */
export function availabilityLabel( count ) {
	if ( ! count ) {
		return '';
	}
	/* translators: %d: number of available times */
	return sprintf( __( '%d times available', 'pointly-booking' ), count );
}
