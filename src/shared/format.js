/**
 * Money and date formatting.
 *
 * Booking times are stored and returned in the site's time zone as "YYYY-MM-DD HH:MM:SS".
 * They are displayed exactly as stored (never converted to the visitor's clock): dates are
 * built as UTC and formatted with timeZone "UTC" so no shift can happen.
 */

const cache = new Map();

function formatter( locale, options ) {
	const key = locale + JSON.stringify( options );
	if ( ! cache.has( key ) ) {
		try {
			cache.set( key, new Intl.DateTimeFormat( locale, { timeZone: 'UTC', ...options } ) );
		} catch ( e ) {
			cache.set( key, new Intl.DateTimeFormat( 'en-US', { timeZone: 'UTC', ...options } ) );
		}
	}
	return cache.get( key );
}

/**
 * Parses "YYYY-MM-DD" or "YYYY-MM-DD HH:MM[:SS]" into a UTC Date carrying the local wall time.
 *
 * @param {string} value Date/time string.
 * @return {Date|null} Date.
 */
export function parseLocal( value ) {
	const match = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/.exec( String( value || '' ) );
	if ( ! match ) {
		return null;
	}
	return new Date( Date.UTC( +match[ 1 ], +match[ 2 ] - 1, +match[ 3 ], +( match[ 4 ] || 0 ), +( match[ 5 ] || 0 ) ) );
}

/**
 * "YYYY-MM-DD" of a UTC-based date.
 *
 * @param {Date} date Date.
 * @return {string} Y-m-d.
 */
export function ymd( date ) {
	return date.toISOString().slice( 0, 10 );
}

/**
 * Adds days to a Y-m-d string.
 *
 * @param {string} value Y-m-d.
 * @param {number} days  Days.
 * @return {string} Y-m-d.
 */
export function addDays( value, days ) {
	const date = parseLocal( value );
	date.setUTCDate( date.getUTCDate() + days );
	return ymd( date );
}

/**
 * Whether a WordPress time_format uses a 12-hour clock.
 *
 * @param {string} wpTimeFormat e.g. "g:i a" or "H:i".
 * @return {boolean} 12-hour clock.
 */
export function is12h( wpTimeFormat ) {
	return /[gh]/.test( String( wpTimeFormat || '' ) ) || /[aA]/.test( String( wpTimeFormat || '' ) );
}

/**
 * Formats "HH:MM" (or a datetime) as a time.
 *
 * @param {string} value   "HH:MM" or datetime.
 * @param {Object} options { locale, timeFormat }.
 * @return {string} Time.
 */
export function formatTime( value, { locale = 'en', timeFormat = 'H:i' } = {} ) {
	const text = String( value || '' );
	const date = /^\d{2}:\d{2}/.test( text ) ? parseLocal( '2000-01-01 ' + text ) : parseLocal( text );
	if ( ! date ) {
		return text;
	}
	return formatter( locale, { hour: 'numeric', minute: '2-digit', hour12: is12h( timeFormat ) } ).format( date );
}

/**
 * Formats a date.
 *
 * @param {string} value   Y-m-d or datetime.
 * @param {Object} options { locale, style: 'long'|'medium'|'short'|'weekday'|'month' }.
 * @return {string} Date.
 */
export function formatDate( value, { locale = 'en', style = 'long' } = {} ) {
	const date = parseLocal( value );
	if ( ! date ) {
		return String( value || '' );
	}
	const styles = {
		long: { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' },
		medium: { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' },
		short: { day: 'numeric', month: 'short' },
		weekday: { weekday: 'short' },
		weekdayLong: { weekday: 'long' },
		month: { month: 'long', year: 'numeric' },
		monthShort: { month: 'short' },
		day: { day: 'numeric' },
	};
	return formatter( locale, styles[ style ] || styles.long ).format( date );
}

/**
 * Formats a date and time range, e.g. "Tue, 3 Mar 2026 · 10:00 – 11:00".
 *
 * @param {string} start   Start datetime.
 * @param {string} end     End datetime.
 * @param {Object} options { locale, timeFormat }.
 * @return {string} Range.
 */
export function formatRange( start, end, options = {} ) {
	const day = formatDate( start, { ...options, style: 'medium' } );
	const from = formatTime( start, options );
	const to = end ? formatTime( end, options ) : '';
	return to ? `${ day } · ${ from } – ${ to }` : `${ day } · ${ from }`;
}

/**
 * Formats money with the site's currency settings.
 *
 * @param {number} amount  Amount.
 * @param {Object} options { currency, symbol, position ('before'|'after'), locale }.
 * @return {string} Money.
 */
export function formatMoney( amount, { currency = 'USD', symbol = '', position = 'before', locale = 'en' } = {} ) {
	const value = Number( amount ) || 0;
	let number;
	try {
		const digits = new Intl.NumberFormat( locale, { style: 'currency', currency } ).resolvedOptions().maximumFractionDigits;
		number = new Intl.NumberFormat( locale, {
			minimumFractionDigits: value % 1 === 0 && digits > 0 ? 0 : digits,
			maximumFractionDigits: digits,
		} ).format( value );
	} catch ( e ) {
		number = value.toFixed( 2 );
	}
	const sign = symbol || currency;
	if ( position === 'after' ) {
		return `${ number } ${ sign }`;
	}
	return /^[A-Z]{3}$/.test( sign ) ? `${ sign } ${ number }` : `${ sign }${ number }`;
}

/**
 * Human duration, e.g. "1 h 30 min".
 *
 * @param {number} minutes Minutes.
 * @param {Object} labels  { h: 'h', min: 'min' }.
 * @return {string} Duration.
 */
export function formatDuration( minutes, labels = { h: 'h', min: 'min' } ) {
	const total = Math.max( 0, Math.round( Number( minutes ) || 0 ) );
	const hours = Math.floor( total / 60 );
	const rest = total % 60;
	if ( ! hours ) {
		return `${ rest } ${ labels.min }`;
	}
	return rest ? `${ hours } ${ labels.h } ${ rest } ${ labels.min }` : `${ hours } ${ labels.h }`;
}
