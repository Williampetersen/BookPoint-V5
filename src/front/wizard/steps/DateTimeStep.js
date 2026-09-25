/**
 * Date & time step: month calendar + time slots, prefetching nearby days.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Calendar,
	TimeSlots,
	Notice,
	Button,
	Icon,
	availabilityLabel,
	shiftMonth,
	monthOf,
} from '../../../ui';
import { loadDay, loadMonth, prefetchDays } from '../api';
import { useFormat, visitorTimezoneDiffers } from '../context';

function dotsFor( count ) {
	if ( count >= 6 ) {
		return 3;
	}
	return count >= 3 ? 2 : 1;
}

export default function DateTimeStep( {
	data,
	selection,
	update,
	scope,
	defaultDate,
} ) {
	const fmt = useFormat();
	const settings = data.settings;
	const behavior = ( data.design && data.design.behavior ) || {};
	const service =
		data.services.find( ( s ) => s.id === selection.serviceId ) || {};
	const [ month, setMonth ] = useState(
		monthOf( selection.date || defaultDate || settings.today )
	);
	const [ monthData, setMonthData ] = useState( null );
	const [ monthError, setMonthError ] = useState( '' );
	const [ day, setDay ] = useState( null );
	const [ dayLoading, setDayLoading ] = useState( false );
	const [ dayError, setDayError ] = useState( '' );
	const [ retry, setRetry ] = useState( 0 );
	const autoPicked = useRef( !! selection.date );
	const scopeKey = `${ scope.serviceId }|${ scope.agentId }|${ scope.locationId }`;

	// Month availability (the first load lets the server jump to the first free month).
	useEffect( () => {
		let alive = true;
		setMonthError( '' );
		const requested =
			autoPicked.current || selection.date || defaultDate ? month : '';
		loadMonth( scope, requested )
			.then( ( result ) => {
				if ( ! alive ) {
					return;
				}
				setMonthData( result );
				if ( result.month !== month ) {
					setMonth( result.month );
				}
				const days = result.days || {};
				if (
					selection.date &&
					monthOf( selection.date ) === result.month &&
					! days[ selection.date ]
				) {
					update( { date: '', start: '' } );
				} else if (
					! selection.date &&
					! autoPicked.current &&
					behavior.autoSelectDate !== false
				) {
					const preferred =
						defaultDate && days[ defaultDate ]
							? defaultDate
							: result.first_available;
					if ( preferred ) {
						autoPicked.current = true;
						update( { date: preferred, start: '' } );
					}
				}
				loadMonth( scope, shiftMonth( result.month, 1 ) ).catch(
					() => {}
				);
			} )
			.catch( ( error ) => alive && setMonthError( error.message ) );
		return () => {
			alive = false;
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ scopeKey, month, retry ] );

	// Slots of the selected day + prefetch of the neighbouring free days.
	useEffect( () => {
		if ( ! selection.date ) {
			setDay( null );
			return undefined;
		}
		let alive = true;
		setDayLoading( true );
		setDayError( '' );
		loadDay( scope, selection.date )
			.then( ( result ) => {
				if ( ! alive ) {
					return;
				}
				setDay( result );
				setDayLoading( false );
				if (
					selection.start &&
					! result.slots.some(
						( slot ) => slot.start === selection.start
					)
				) {
					update( { start: '' } );
				}
				if ( monthData && monthData.days ) {
					const free = Object.keys( monthData.days )
						.filter( ( date ) => monthData.days[ date ] > 0 )
						.sort();
					const index = free.indexOf( selection.date );
					prefetchDays( scope, [
						free[ index - 1 ],
						free[ index + 1 ],
						free[ index + 2 ],
					] );
				}
			} )
			.catch( ( error ) => {
				if ( alive ) {
					setDayLoading( false );
					setDayError( error.message );
				}
			} );
		return () => {
			alive = false;
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ scopeKey, selection.date, retry ] );

	const days =
		( monthData && monthData.month === month && monthData.days ) || null;
	const isDisabled = ( date ) =>
		date < settings.today ||
		date > settings.last_date ||
		! days ||
		! days[ date ];
	const differs =
		behavior.showTimezone !== false &&
		visitorTimezoneDiffers( settings.timezone );
	const noneThisMonth = days && ! Object.values( days ).some( Boolean );

	return (
		<div className="pbk-dt">
			<div className="pbk-dt__calendar">
				<Calendar
					month={ month }
					onMonthChange={ setMonth }
					value={ selection.date }
					onChange={ ( date ) => update( { date, start: '' } ) }
					isDateDisabled={ isDisabled }
					getDayInfo={ ( date ) =>
						days && days[ date ]
							? {
									dots: dotsFor( days[ date ] ),
									label: availabilityLabel( days[ date ] ),
							  }
							: {}
					}
					today={ settings.today }
					min={ settings.today }
					max={ settings.last_date }
					weekStartsOn={ settings.week_starts }
					locale={ fmt.locale }
					loading={ ! days && ! monthError }
					label={ __( 'Choose a date', 'pointly-booking' ) }
				/>
				{ monthError && (
					<Notice
						tone="danger"
						action={
							<Button
								size="sm"
								onClick={ () => setRetry( ( n ) => n + 1 ) }
							>
								{ __( 'Try again', 'pointly-booking' ) }
							</Button>
						}
					>
						{ monthError }
					</Notice>
				) }
				{ noneThisMonth && (
					<Notice tone="info">
						{ __( 'No free times this month.', 'pointly-booking' ) }{ ' ' }
						{ monthData.next_available ? (
							<button
								type="button"
								className="pbk-link-button"
								onClick={ () => {
									setMonth(
										monthOf( monthData.next_available )
									);
									update( {
										date: monthData.next_available,
										start: '',
									} );
								} }
							>
								{ sprintf(
									/* translators: %s: date */
									__(
										'Jump to the next free day (%s)',
										'pointly-booking'
									),
									fmt.date(
										monthData.next_available,
										'short'
									)
								) }
							</button>
						) : (
							__( 'Try the next month.', 'pointly-booking' )
						) }
					</Notice>
				) }
			</div>
			<div className="pbk-dt__slots" aria-live="polite">
				<h3 className="pbk-dt__day">
					{ selection.date
						? fmt.date( selection.date, 'long' )
						: __(
								'Choose a date to see free times',
								'pointly-booking'
						  ) }
				</h3>
				{ selection.date && ! dayError && (
					<TimeSlots
						slots={
							day && day.date === selection.date ? day.slots : []
						}
						loading={ dayLoading }
						value={ selection.start }
						onChange={ ( start ) => update( { start } ) }
						locale={ fmt.locale }
						timeFormat={ fmt.timeFormat }
						lowThreshold={ service.capacity > 1 ? 2 : 0 }
						legend={ __( 'Available times', 'pointly-booking' ) }
					/>
				) }
				{ dayError && (
					<Notice
						tone="danger"
						action={
							<Button
								size="sm"
								onClick={ () => setRetry( ( n ) => n + 1 ) }
							>
								{ __( 'Try again', 'pointly-booking' ) }
							</Button>
						}
					>
						{ dayError }
					</Notice>
				) }
				{ behavior.showTimezone !== false && (
					<p className="pbk-dt__tz">
						<Icon name="globe" size={ 16 } />
						<span>
							{ sprintf(
								/* translators: %s: time zone name */
								__(
									'Times are shown in %s.',
									'pointly-booking'
								),
								settings.timezone_label
							) }
							{ differs &&
								` ${ __(
									'Your device uses a different time zone.',
									'pointly-booking'
								) }` }
						</span>
					</p>
				) }
			</div>
		</div>
	);
}
