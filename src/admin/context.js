/**
 * Admin config + formatting context.
 */
import { createContext, useContext, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { formatMoney, formatDate, formatTime, formatDuration, formatRange } from '../shared/format';

const ConfigContext = createContext( null );

export function AdminConfigProvider( { config, children } ) {
	const fmt = useMemo( () => {
		const locale = config.locale || 'en';
		const timeFormat = config.timeFormat || 'H:i';
		return {
			locale,
			money: ( amount ) => formatMoney( amount, { currency: config.currency, symbol: config.currency_symbol, position: config.currency_position, locale } ),
			date: ( value, style = 'medium' ) => formatDate( value, { locale, style } ),
			time: ( value ) => formatTime( value, { locale, timeFormat } ),
			range: ( start, end ) => formatRange( start, end, { locale, timeFormat } ),
			duration: ( minutes ) => formatDuration( minutes, { h: __( 'h', 'pointly-booking' ), min: __( 'min', 'pointly-booking' ) } ),
		};
	}, [ config ] );

	const value = useMemo( () => ( { config, fmt } ), [ config, fmt ] );
	return <ConfigContext.Provider value={ value }>{ children }</ConfigContext.Provider>;
}

export const useAdminConfig = () => useContext( ConfigContext ).config;
export const useAdminFormat = () => useContext( ConfigContext ).fmt;
