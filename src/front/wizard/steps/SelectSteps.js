/**
 * Location, category, service, extras and staff steps.
 */
import { useMemo, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	RadioCards,
	ChoiceCard,
	SearchInput,
	Avatar,
	Icon,
	EmptyState,
	Badge,
} from '../../../ui';
import { formatDuration } from '../../../shared/format';
import { useFormat } from '../context';
import { availableServices, candidateAgents, extrasFor } from '../flow';

const Thumb = ( { src, icon } ) =>
	src ? (
		<span className="pbk-thumb">
			<img src={ src } alt="" loading="lazy" decoding="async" />
		</span>
	) : (
		<span className="pbk-thumb pbk-thumb--icon">
			<Icon name={ icon } size={ 22 } />
		</span>
	);

export function LocationStep( { data, selection, update } ) {
	return (
		<RadioCards
			legend={ __( 'Locations', 'pointly-booking' ) }
			hideLegend
			columns="2"
			value={ selection.locationId || '' }
			onChange={ ( value ) => update( { locationId: Number( value ) } ) }
			options={ data.locations.map( ( location ) => ( {
				value: location.id,
				label: location.name,
				description: location.address,
				media: <Thumb src={ location.image_url } icon="map-pin" />,
			} ) ) }
		/>
	);
}

export function CategoryStep( { data, selection, update } ) {
	const counts = useMemo( () => {
		const out = {};
		availableServices( data, selection.locationId, 0 ).forEach(
			( service ) =>
				( service.category_ids || [] ).forEach( ( id ) => {
					out[ id ] = ( out[ id ] || 0 ) + 1;
				} )
		);
		return out;
	}, [ data, selection.locationId ] );
	const categories = data.categories.filter(
		( category ) => counts[ category.id ]
	);

	return (
		<RadioCards
			legend={ __( 'Categories', 'pointly-booking' ) }
			hideLegend
			columns="2"
			value={ selection.categoryId || '' }
			onChange={ ( value ) => update( { categoryId: Number( value ) } ) }
			options={ categories.map( ( category ) => ( {
				value: category.id,
				label: category.name,
				description: category.description,
				media: <Thumb src={ category.image_url } icon="layers" />,
				meta: sprintf(
					/* translators: %d: number of services */
					_n(
						'%d service',
						'%d services',
						counts[ category.id ],
						'pointly-booking'
					),
					counts[ category.id ]
				),
			} ) ) }
		/>
	);
}

export function ServiceStep( { data, selection, update, showCategoryFilter } ) {
	const fmt = useFormat();
	const [ search, setSearch ] = useState( '' );
	const [ filter, setFilter ] = useState( 0 );
	const behavior = ( data.design && data.design.behavior ) || {};
	const base = availableServices(
		data,
		selection.locationId,
		selection.categoryId
	);
	const categories = data.categories.filter( ( category ) =>
		base.some( ( service ) =>
			( service.category_ids || [] ).includes( category.id )
		)
	);
	const showSearch = base.length >= ( behavior.serviceSearchMin || 6 );
	const term = search.trim().toLowerCase();
	const services = base.filter(
		( service ) =>
			( ! filter || ( service.category_ids || [] ).includes( filter ) ) &&
			( ! term ||
				service.name.toLowerCase().includes( term ) ||
				( service.description || '' ).toLowerCase().includes( term ) )
	);

	return (
		<div className="pbk-stack">
			{ ( showSearch ||
				( showCategoryFilter && categories.length > 1 ) ) && (
				<div className="pbk-wizard__filters">
					{ showSearch && (
						<SearchInput
							label={ __( 'Search services', 'pointly-booking' ) }
							value={ search }
							onChange={ ( event ) =>
								setSearch( event.target.value )
							}
							onClear={ () => setSearch( '' ) }
						/>
					) }
					{ showCategoryFilter && categories.length > 1 && (
						<div
							className="pbk-chips"
							role="group"
							aria-label={ __(
								'Filter by category',
								'pointly-booking'
							) }
						>
							{ [
								{ id: 0, name: __( 'All', 'pointly-booking' ) },
								...categories,
							].map( ( category ) => (
								<button
									key={ category.id }
									type="button"
									className={ `pbk-chip ${
										filter === category.id
											? 'is-active'
											: ''
									}` }
									aria-pressed={ filter === category.id }
									onClick={ () => setFilter( category.id ) }
								>
									{ category.name }
								</button>
							) ) }
						</div>
					) }
				</div>
			) }
			{ services.length ? (
				<RadioCards
					legend={ __( 'Services', 'pointly-booking' ) }
					hideLegend
					columns="1"
					value={ selection.serviceId || '' }
					onChange={ ( value ) =>
						update( { serviceId: Number( value ) } )
					}
					options={ services.map( ( service ) => ( {
						value: service.id,
						label: service.name,
						description: service.description,
						media: (
							<Thumb src={ service.image_url } icon="sparkles" />
						),
						meta: (
							<>
								<span className="pbk-meta">
									<Icon name="clock" size={ 14 } />
									{ formatDuration(
										service.duration,
										fmt.durationLabels
									) }
								</span>
								{ service.capacity > 1 && (
									<span className="pbk-meta">
										<Icon name="users" size={ 14 } />
										{ sprintf(
											/* translators: %d: places per time slot */
											_n(
												'Up to %d person',
												'Up to %d people',
												service.capacity,
												'pointly-booking'
											),
											service.capacity
										) }
									</span>
								) }
							</>
						),
						aside:
							service.price > 0 ? (
								fmt.money( service.price )
							) : (
								<Badge tone="success">
									{ __( 'Free', 'pointly-booking' ) }
								</Badge>
							),
					} ) ) }
				/>
			) : (
				<EmptyState
					compact
					icon="search"
					title={ __( 'No services found', 'pointly-booking' ) }
					description={
						term
							? __(
									'Try another search word.',
									'pointly-booking'
							  )
							: __(
									'There is nothing to book here right now.',
									'pointly-booking'
							  )
					}
				/>
			) }
		</div>
	);
}

export function ExtrasStep( { data, selection, update } ) {
	const fmt = useFormat();
	const extras = extrasFor( data, selection.serviceId );
	return (
		<div className="pbk-stack pbk-stack--tight">
			{ extras.map( ( extra ) => (
				<ChoiceCard
					key={ extra.id }
					label={ extra.name }
					description={ extra.description }
					media={
						extra.image_url ? (
							<Thumb src={ extra.image_url } icon="plus" />
						) : null
					}
					meta={
						extra.duration > 0 ? (
							<span className="pbk-meta">
								<Icon name="clock" size={ 14 } />
								{ formatDuration(
									extra.duration,
									fmt.durationLabels
								) }
							</span>
						) : null
					}
					aside={
						extra.price > 0
							? `+${ fmt.money( extra.price ) }`
							: __( 'Free', 'pointly-booking' )
					}
					checked={ selection.extras.includes( extra.id ) }
					onChange={ ( checked ) =>
						update( {
							extras: checked
								? [ ...selection.extras, extra.id ]
								: selection.extras.filter(
										( id ) => id !== extra.id
								  ),
						} )
					}
				/>
			) ) }
			<p className="pbk-wizard__hint">
				{ __(
					'Extras are optional. Continue without any if you like.',
					'pointly-booking'
				) }
			</p>
		</div>
	);
}

export function StaffStep( { data, selection, update } ) {
	const agents = candidateAgents(
		data,
		selection.serviceId,
		selection.locationId
	);
	const options = [
		{
			value: 0,
			label: __( 'Any available', 'pointly-booking' ),
			description: __(
				'See every free time. We will assign someone who is available.',
				'pointly-booking'
			),
			media: <Avatar icon="users" size={ 44 } />,
		},
		...agents.map( ( agent ) => ( {
			value: agent.id,
			label: agent.name,
			media: (
				<Avatar
					name={ agent.name }
					src={ agent.image_url }
					size={ 44 }
				/>
			),
		} ) ),
	];
	return (
		<RadioCards
			legend={ __( 'Staff', 'pointly-booking' ) }
			hideLegend
			columns="2"
			value={ selection.agentId === null ? '' : selection.agentId }
			onChange={ ( value ) => update( { agentId: Number( value ) } ) }
			options={ options }
		/>
	);
}
