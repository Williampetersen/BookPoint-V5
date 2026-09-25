/**
 * Legacy per-weekday single-range override editor ({"0":"HH:MM-HH:MM",...}, 0 = Sunday).
 * Used for a service's or agent's custom hours (falls back to the business schedule when closed).
 */
import { useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Toggle } from '../../ui';

const WEEKDAY_LABELS = {
	0: __( 'Sunday', 'pointly-booking' ),
	1: __( 'Monday', 'pointly-booking' ),
	2: __( 'Tuesday', 'pointly-booking' ),
	3: __( 'Wednesday', 'pointly-booking' ),
	4: __( 'Thursday', 'pointly-booking' ),
	5: __( 'Friday', 'pointly-booking' ),
	6: __( 'Saturday', 'pointly-booking' ),
};

function parseJson( value ) {
	try {
		const data = JSON.parse( value || '{}' );
		return data && typeof data === 'object' ? data : {};
	} catch ( e ) {
		return {};
	}
}

export function WeekdayHours( { value, onChange, weekStartsOn = 1 } ) {
	const map = useMemo( () => parseJson( value ), [ value ] );
	const order = useMemo( () => Array.from( { length: 7 }, ( _, i ) => ( weekStartsOn + i ) % 7 ), [ weekStartsOn ] );

	const setDay = ( day, range ) => {
		onChange( JSON.stringify( { ...map, [ String( day ) ]: range } ) );
	};

	return (
		<div className="pbk-weekday-hours">
			{ order.map( ( day ) => {
				const range = map[ String( day ) ] || '';
				const [ start, end ] = range ? range.split( '-' ) : [ '09:00', '17:00' ];
				const enabled = !! range;
				return (
					<div key={ day } className="pbk-weekday-hours__row">
						<Toggle
							label={ WEEKDAY_LABELS[ day ] }
							checked={ enabled }
							onChange={ ( checked ) => setDay( day, checked ? `${ start }-${ end }` : '' ) }
						/>
						{ enabled && (
							<div className="pbk-weekday-hours__times">
								<input type="time" className="pbk-input" value={ start } onChange={ ( e ) => setDay( day, `${ e.target.value }-${ end }` ) } />
								<span>–</span>
								<input type="time" className="pbk-input" value={ end } onChange={ ( e ) => setDay( day, `${ start }-${ e.target.value }` ) } />
							</div>
						) }
					</div>
				);
			} ) }
		</div>
	);
}
