/**
 * Formatting context for the wizard (locale, currency, time format from the site).
 */
import { createContext, useContext, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { formatDate, formatMoney, formatTime } from '../../shared/format';

const FormatContext = createContext( null );

function addMinutes( hm, minutes ) {
	const [ h, m ] = String( hm ).split( ':' ).map( Number );
	const total = h * 60 + m + ( Number( minutes ) || 0 );
	const hh = Math.floor( ( total % 1440 ) / 60 );
	const mm = total % 60;
	return `${ String( hh ).padStart( 2, '0' ) }:${ String( mm ).padStart(
		2,
		'0'
	) }`;
}

export function makeFormat( settings ) {
	const locale = settings.locale || document.documentElement.lang || 'en';
	const currency = {
		currency: settings.currency || 'USD',
		symbol: settings.currency_symbol || '',
		position: settings.currency_pos || 'before',
		locale,
	};
	const timeOptions = { locale, timeFormat: settings.time_format || 'H:i' };
	return {
		locale,
		money: ( amount ) => formatMoney( amount, currency ),
		date: ( value, style = 'long' ) =>
			formatDate( value, { locale, style } ),
		time: ( hm ) => formatTime( hm, timeOptions ),
		timeRange: ( date, start, duration ) =>
			`${ formatTime( start, timeOptions ) } – ${ formatTime(
				addMinutes( start, duration ),
				timeOptions
			) }`,
		durationLabels: {
			h: __( 'h', 'pointly-booking' ),
			min: __( 'min', 'pointly-booking' ),
		},
		timeFormat: settings.time_format || 'H:i',
	};
}

export function FormatProvider( { settings, children } ) {
	const value = useMemo( () => makeFormat( settings || {} ), [ settings ] );
	return (
		<FormatContext.Provider value={ value }>
			{ children }
		</FormatContext.Provider>
	);
}

export const useFormat = () => useContext( FormatContext ) || makeFormat( {} );

/**
 * Whether the visitor's clock differs from the business time zone right now.
 *
 * @param {string} timezone Site time zone (name or ±HH:MM).
 * @return {boolean} Differs.
 */
export function visitorTimezoneDiffers( timezone ) {
	try {
		const visitorOffset = -new Date().getTimezoneOffset();
		let siteOffset;
		const fixed = /^([+-])(\d{2}):(\d{2})$/.exec( timezone || '' );
		if ( fixed ) {
			siteOffset =
				( fixed[ 1 ] === '-' ? -1 : 1 ) *
				( Number( fixed[ 2 ] ) * 60 + Number( fixed[ 3 ] ) );
		} else {
			const now = new Date();
			const local = new Date(
				now.toLocaleString( 'en-US', { timeZone: timezone } )
			);
			const utc = new Date(
				now.toLocaleString( 'en-US', { timeZone: 'UTC' } )
			);
			siteOffset = Math.round( ( local - utc ) / 60000 );
		}
		return siteOffset !== visitorOffset;
	} catch ( e ) {
		return false;
	}
}

export { addMinutes };
