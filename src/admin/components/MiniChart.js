/**
 * Small original bar chart (no charting library): bookings or revenue over time.
 */
import { useState } from '@wordpress/element';
import './mini-chart.css';

/**
 * @param {Object}   props
 * @param {Array}    props.data        [ { date, value } ].
 * @param {Function} props.formatValue ( value ) => string.
 * @param {Function} props.formatLabel ( date ) => string.
 * @param {string}   props.caption     Accessible table caption (screen-reader data table).
 * @param {number}   props.height      SVG height.
 * @return {*} Chart.
 */
export function MiniChart( { data = [], formatValue = ( v ) => String( v ), formatLabel = ( d ) => d, caption = '', height = 160 } ) {
	const [ active, setActive ] = useState( null );

	if ( ! data.length ) {
		return null;
	}

	const max = Math.max( 1, ...data.map( ( row ) => row.value ) );
	const width = Math.max( data.length * 14, 100 );
	const barWidth = Math.max( 3, width / data.length - 3 );

	return (
		<div className="pbk-chart">
			<svg className="pbk-chart__svg" viewBox={ `0 0 ${ width } ${ height }` } preserveAspectRatio="none" aria-hidden="true" focusable="false">
				{ data.map( ( row, index ) => {
					const barHeight = Math.max( 1, ( row.value / max ) * ( height - 4 ) );
					const x = index * ( barWidth + 3 );
					return (
						<rect
							key={ row.date }
							x={ x }
							y={ height - barHeight }
							width={ barWidth }
							height={ barHeight }
							rx={ Math.min( 3, barWidth / 2 ) }
							className={ `pbk-chart__bar ${ active === index ? 'is-active' : '' }` }
						/>
					);
				} ) }
			</svg>
			<div className="pbk-chart__hit" aria-hidden="true">
				{ data.map( ( row, index ) => (
					<span key={ row.date } className="pbk-chart__hit-col" onMouseEnter={ () => setActive( index ) } onMouseLeave={ () => setActive( null ) } />
				) ) }
			</div>
			{ active !== null && data[ active ] && (
				<div className="pbk-chart__tooltip" style={ { insetInlineStart: `${ ( ( active + 0.5 ) / data.length ) * 100 }%` } }>
					<strong>{ formatValue( data[ active ].value ) }</strong>
					<span>{ formatLabel( data[ active ].date ) }</span>
				</div>
			) }
			{ caption && (
				<table className="pbk-sr-only">
					<caption>{ caption }</caption>
					<tbody>
						{ data.map( ( row ) => (
							<tr key={ row.date }>
								<th scope="row">{ formatLabel( row.date ) }</th>
								<td>{ formatValue( row.value ) }</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</div>
	);
}
