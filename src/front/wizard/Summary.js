/**
 * Live booking summary: sidebar card (wide) and bottom bar + sheet (narrow).
 */
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Drawer, Icon } from '../../ui';
import { formatDuration } from '../../shared/format';
import { useFormat } from './context';

export function SummaryLines( { data, selection, price, onEdit, editable } ) {
	const fmt = useFormat();
	const service = data.services.find( ( s ) => s.id === selection.serviceId );
	const location = data.locations.find(
		( l ) => l.id === selection.locationId
	);
	const agent = selection.agentId
		? data.agents.find( ( a ) => a.id === selection.agentId )
		: null;
	const extras = data.extras.filter( ( e ) =>
		selection.extras.includes( e.id )
	);

	if ( ! service ) {
		return (
			<p className="pbk-summary__empty">
				{ __(
					'Choose a service to see your booking summary here.',
					'pointly-booking'
				) }
			</p>
		);
	}

	const rows = [];
	if ( location ) {
		rows.push( {
			key: 'location',
			icon: 'map-pin',
			label: location.name,
			meta: location.address,
			edit: 'location',
		} );
	}
	rows.push( {
		key: 'service',
		icon: 'sparkles',
		label: service.name,
		meta: formatDuration( service.duration, fmt.durationLabels ),
		edit: 'service',
	} );
	if ( selection.agentId !== null && ! data.settings.no_staff ) {
		rows.push( {
			key: 'agents',
			icon: 'user',
			label: agent
				? agent.name
				: __( 'Any available staff member', 'pointly-booking' ),
			edit: 'agents',
		} );
	}
	if ( selection.date ) {
		rows.push( {
			key: 'datetime',
			icon: 'calendar',
			label: fmt.date( selection.date, 'long' ),
			meta: selection.start
				? fmt.timeRange(
						selection.date,
						selection.start,
						service.duration
				  )
				: __( 'Choose a time', 'pointly-booking' ),
			edit: 'datetime',
		} );
	}

	return (
		<>
			<ul className="pbk-summary__rows">
				{ rows.map( ( row ) => (
					<li key={ row.key } className="pbk-summary__row">
						<Icon
							name={ row.icon }
							size={ 18 }
							className="pbk-summary__icon"
						/>
						<span className="pbk-summary__text">
							<span className="pbk-summary__label">
								{ row.label }
							</span>
							{ row.meta && (
								<span className="pbk-summary__meta">
									{ row.meta }
								</span>
							) }
						</span>
						{ onEdit &&
							row.edit &&
							( ! editable || editable( row.edit ) ) && (
								<button
									type="button"
									className="pbk-summary__edit"
									onClick={ () => onEdit( row.edit ) }
								>
									{ __( 'Edit', 'pointly-booking' ) }
									<span className="pbk-sr-only">
										{ ' ' }
										{ row.label }
									</span>
								</button>
							) }
					</li>
				) ) }
			</ul>
			<dl className="pbk-summary__prices">
				<div className="pbk-summary__price">
					<dt>{ service.name }</dt>
					<dd>{ fmt.money( price.service ) }</dd>
				</div>
				{ extras.map( ( extra ) => (
					<div key={ extra.id } className="pbk-summary__price">
						<dt>{ extra.name }</dt>
						<dd>{ fmt.money( extra.price ) }</dd>
					</div>
				) ) }
				{ price.discount > 0 && (
					<div className="pbk-summary__price is-discount">
						<dt>{ __( 'Discount', 'pointly-booking' ) }</dt>
						<dd>−{ fmt.money( price.discount ) }</dd>
					</div>
				) }
				<div className="pbk-summary__price is-total">
					<dt>{ __( 'Total', 'pointly-booking' ) }</dt>
					<dd>
						{ price.total > 0
							? fmt.money( price.total )
							: __( 'Free', 'pointly-booking' ) }
					</dd>
				</div>
			</dl>
		</>
	);
}

export function SummaryCard( props ) {
	return (
		<section className="pbk-summary" aria-labelledby="pbk-summary-title">
			<h3 className="pbk-summary__title" id="pbk-summary-title">
				{ __( 'Your booking', 'pointly-booking' ) }
			</h3>
			<SummaryLines { ...props } />
		</section>
	);
}

/**
 * Narrow layouts: total + button that opens the summary sheet.
 *
 * @param {Object} props Props (as SummaryLines).
 * @return {*} Bar.
 */
export function SummaryBar( props ) {
	const [ open, setOpen ] = useState( false );
	const fmt = useFormat();
	const service = props.data.services.find(
		( s ) => s.id === props.selection.serviceId
	);
	if ( ! service ) {
		return null;
	}
	return (
		<>
			<button
				type="button"
				className="pbk-summary-bar"
				onClick={ () => setOpen( true ) }
				aria-haspopup="dialog"
			>
				<span className="pbk-summary-bar__text">
					<span className="pbk-summary-bar__service">
						{ service.name }
					</span>
					<span className="pbk-summary-bar__total">
						{ props.price.total > 0
							? sprintf(
									/* translators: %s: price */
									__( 'Total %s', 'pointly-booking' ),
									fmt.money( props.price.total )
							  )
							: __( 'Free', 'pointly-booking' ) }
					</span>
				</span>
				<span className="pbk-summary-bar__action">
					{ __( 'Details', 'pointly-booking' ) }
					<Icon name="chevron-up" size={ 16 } />
				</span>
			</button>
			<Drawer
				open={ open }
				onClose={ () => setOpen( false ) }
				title={ __( 'Your booking', 'pointly-booking' ) }
				size="sm"
			>
				<div className="pbk-summary pbk-summary--sheet">
					<SummaryLines
						{ ...props }
						onEdit={
							props.onEdit
								? ( key ) => {
										setOpen( false );
										props.onEdit( key );
								  }
								: undefined
						}
					/>
				</div>
			</Drawer>
		</>
	);
}
