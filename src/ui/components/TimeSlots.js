/**
 * Time slot chips grouped into morning / afternoon / evening (native radios: arrow keys work).
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useUniqueId } from '../hooks';
import { formatTime } from '../../shared/format';
import './timeslots.css';

const GROUPS = [
	{ key: 'morning', test: ( h ) => h < 12 },
	{ key: 'afternoon', test: ( h ) => h >= 12 && h < 17 },
	{ key: 'evening', test: ( h ) => h >= 17 },
];

function groupLabel( key ) {
	if ( key === 'morning' ) {
		return __( 'Morning', 'pointly-booking' );
	}
	if ( key === 'afternoon' ) {
		return __( 'Afternoon', 'pointly-booking' );
	}
	return __( 'Evening', 'pointly-booking' );
}

/**
 * @param {Object}   props
 * @param {Array}    props.slots         [ { start: 'HH:MM', end: 'HH:MM', available: n } ].
 * @param {string}   props.value         Selected start.
 * @param {Function} props.onChange      ( start ) => void.
 * @param {boolean}  props.loading       Show skeleton chips.
 * @param {string}   props.locale        Locale.
 * @param {string}   props.timeFormat    WordPress time format.
 * @param {boolean}  props.showEnd       Show end times.
 * @param {number}   props.lowThreshold  Show "n left" when available ≤ this (0 = never).
 * @param {string}   props.legend        Accessible group label.
 * @return {*} Slots.
 */
export function TimeSlots( {
	slots = [],
	value,
	onChange,
	loading = false,
	locale = 'en',
	timeFormat = 'H:i',
	showEnd = false,
	lowThreshold = 0,
	legend,
	emptyText,
	className = '',
} ) {
	const name = useUniqueId( 'pbk-slot' );

	if ( loading ) {
		return (
			<div
				className={ `pbk-slots is-loading ${ className }`.trim() }
				aria-busy="true"
			>
				<span className="pbk-sr-only">
					{ __( 'Loading available times…', 'pointly-booking' ) }
				</span>
				<div className="pbk-slots__grid" aria-hidden="true">
					{ Array.from( { length: 8 } ).map( ( _, i ) => (
						<span
							key={ i }
							className="pbk-slot pbk-slot--skeleton"
						/>
					) ) }
				</div>
			</div>
		);
	}

	if ( ! slots.length ) {
		return (
			<p className="pbk-slots__empty">
				{ emptyText ||
					__(
						'No free times on this day. Please choose another date.',
						'pointly-booking'
					) }
			</p>
		);
	}

	const grouped = GROUPS.map( ( group ) => ( {
		...group,
		items: slots.filter( ( slot ) =>
			group.test( Number( String( slot.start ).slice( 0, 2 ) ) )
		),
	} ) ).filter( ( group ) => group.items.length );

	return (
		<fieldset className={ `pbk-slots ${ className }`.trim() }>
			<legend className="pbk-sr-only">
				{ legend || __( 'Available times', 'pointly-booking' ) }
			</legend>
			{ grouped.map( ( group ) => (
				<div
					key={ group.key }
					className="pbk-slots__group"
					role="group"
					aria-labelledby={ `${ name }-${ group.key }` }
				>
					<p
						className="pbk-slots__label"
						id={ `${ name }-${ group.key }` }
					>
						{ groupLabel( group.key ) }
					</p>
					<div className="pbk-slots__grid">
						{ group.items.map( ( slot ) => {
							const id = `${ name }-${ slot.start.replace(
								':',
								''
							) }`;
							const checked = value === slot.start;
							const low =
								lowThreshold > 0 &&
								slot.available > 0 &&
								slot.available <= lowThreshold;
							const time = formatTime( slot.start, {
								locale,
								timeFormat,
							} );
							const end =
								showEnd && slot.end
									? formatTime( slot.end, {
											locale,
											timeFormat,
									  } )
									: '';
							return (
								<label
									key={ slot.start }
									htmlFor={ id }
									className={ `pbk-slot ${
										checked ? 'is-selected' : ''
									}`.trim() }
								>
									<input
										id={ id }
										type="radio"
										name={ name }
										className="pbk-slot__input"
										value={ slot.start }
										checked={ checked }
										onChange={ () =>
											onChange( slot.start )
										}
									/>
									<span className="pbk-slot__time pbk-tabular">
										{ time }
										{ end && (
											<span className="pbk-slot__end">
												{ ' ' }
												– { end }
											</span>
										) }
									</span>
									{ low && (
										<span className="pbk-slot__hint">
											{ sprintf(
												/* translators: %d: number of places left */
												_n(
													'%d spot left',
													'%d spots left',
													slot.available,
													'pointly-booking'
												),
												slot.available
											) }
										</span>
									) }
								</label>
							);
						} ) }
					</div>
				</div>
			) ) }
		</fieldset>
	);
}
