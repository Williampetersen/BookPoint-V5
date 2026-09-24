/**
 * Brand scale generation (OKLab mixing) and WCAG contrast helpers.
 *
 * brandTokens( '#4f46e5' ) → { '--pbk-brand-50': '#…', …, '--pbk-brand-contrast': '#fff', … }
 */

const WHITE = [ 255, 255, 255 ];
const BLACK = [ 0, 0, 0 ];
const INK = '#171a21';
const DARK_SURFACE = [ 23, 26, 33 ];

export function parseHex( hex ) {
	let value = String( hex || '' )
		.trim()
		.replace( /^#/, '' );
	if ( /^[0-9a-f]{3}$/i.test( value ) ) {
		value = value
			.split( '' )
			.map( ( c ) => c + c )
			.join( '' );
	}
	if ( ! /^[0-9a-f]{6}$/i.test( value ) ) {
		return null;
	}
	return [ 0, 2, 4 ].map( ( i ) => parseInt( value.slice( i, i + 2 ), 16 ) );
}

export function toHex( rgb ) {
	return (
		'#' +
		rgb
			.map( ( c ) =>
				Math.round( Math.min( 255, Math.max( 0, c ) ) )
					.toString( 16 )
					.padStart( 2, '0' )
			)
			.join( '' )
	);
}

const toLinear = ( c ) => {
	const v = c / 255;
	return v <= 0.04045 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 );
};

const fromLinear = ( v ) => {
	const c =
		v <= 0.0031308 ? v * 12.92 : 1.055 * Math.pow( v, 1 / 2.4 ) - 0.055;
	return c * 255;
};

function toOklab( rgb ) {
	const [ r, g, b ] = rgb.map( toLinear );
	const l = Math.cbrt(
		0.4122214708 * r + 0.5363325363 * g + 0.0514459929 * b
	);
	const m = Math.cbrt(
		0.2119034982 * r + 0.6806995451 * g + 0.1073969566 * b
	);
	const s = Math.cbrt(
		0.0883024619 * r + 0.2817188376 * g + 0.6299787005 * b
	);
	return [
		0.2104542553 * l + 0.793617785 * m - 0.0040720468 * s,
		1.9779984951 * l - 2.428592205 * m + 0.4505937099 * s,
		0.0259040371 * l + 0.7827717662 * m - 0.808675766 * s,
	];
}

function fromOklab( [ L, a, b ] ) {
	const l = Math.pow( L + 0.3963377774 * a + 0.2158037573 * b, 3 );
	const m = Math.pow( L - 0.1055613458 * a - 0.0638541728 * b, 3 );
	const s = Math.pow( L - 0.0894841775 * a - 1.291485548 * b, 3 );
	return [
		fromLinear( 4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s ),
		fromLinear( -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s ),
		fromLinear( -0.0041960863 * l - 0.7034186147 * m + 1.707614701 * s ),
	];
}

/**
 * Mixes two colours in OKLab.
 *
 * @param {number[]} from   RGB.
 * @param {number[]} to     RGB.
 * @param {number}   amount 0 → from, 1 → to.
 * @return {number[]} RGB.
 */
export function mix( from, to, amount ) {
	const a = toOklab( from );
	const b = toOklab( to );
	return fromOklab( a.map( ( v, i ) => v + ( b[ i ] - v ) * amount ) );
}

export function luminance( rgb ) {
	const [ r, g, b ] = rgb.map( toLinear );
	return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

export function contrast( rgbA, rgbB ) {
	const la = luminance( rgbA );
	const lb = luminance( rgbB );
	return ( Math.max( la, lb ) + 0.05 ) / ( Math.min( la, lb ) + 0.05 );
}

/**
 * Darkens (or lightens) a colour until it reaches a contrast ratio against a background.
 *
 * @param {number[]} rgb        Colour.
 * @param {number[]} background Background.
 * @param {number}   ratio      Target ratio.
 * @return {number[]} RGB.
 */
export function ensureContrast( rgb, background, ratio = 4.5 ) {
	if ( contrast( rgb, background ) >= ratio ) {
		return rgb;
	}
	const target = luminance( background ) > 0.5 ? BLACK : WHITE;
	for ( let step = 1; step <= 20; step++ ) {
		const candidate = mix( rgb, target, step * 0.05 );
		if ( contrast( candidate, background ) >= ratio ) {
			return candidate;
		}
	}
	return target;
}

export const DEFAULT_BRAND = '#4f46e5';

/**
 * CSS custom properties for a brand colour.
 *
 * @param {string} hex Chosen colour.
 * @return {Object<string,string>} Custom properties.
 */
export function brandTokens( hex ) {
	const base = parseHex( hex ) || parseHex( DEFAULT_BRAND );
	const light = {
		50: 0.06,
		100: 0.12,
		200: 0.25,
		300: 0.4,
		400: 0.6,
		500: 0.8,
	};
	const dark = { 700: 0.18, 800: 0.36, 900: 0.52 };
	const tokens = {};
	Object.entries( light ).forEach( ( [ step, amount ] ) => {
		tokens[ `--pbk-brand-${ step }` ] = toHex( mix( WHITE, base, amount ) );
	} );
	tokens[ '--pbk-brand-600' ] = toHex( base );
	Object.entries( dark ).forEach( ( [ step, amount ] ) => {
		tokens[ `--pbk-brand-${ step }` ] = toHex( mix( base, BLACK, amount ) );
	} );

	const white = contrast( WHITE, base );
	const ink = contrast( parseHex( INK ), base );
	tokens[ '--pbk-brand-contrast' ] =
		white >= 4.5 || white >= ink ? '#fff' : INK;
	tokens[ '--pbk-brand-text-light' ] = toHex(
		ensureContrast( base, WHITE, 4.5 )
	);
	tokens[ '--pbk-brand-text-dark' ] = toHex(
		ensureContrast( mix( base, WHITE, 0.35 ), DARK_SURFACE, 4.5 )
	);
	tokens[ '--pbk-brand-ring' ] = `rgb(${ base
		.map( Math.round )
		.join( ' ' ) } / 28%)`;
	return tokens;
}

/**
 * Whether a string is a usable hex colour.
 *
 * @param {string} hex Colour.
 * @return {boolean} Valid.
 */
export function isHex( hex ) {
	return !! parseHex( hex );
}
