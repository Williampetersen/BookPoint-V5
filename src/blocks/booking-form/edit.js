/**
 * Editor UI: a static preview plus settings in the sidebar.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, ToggleControl, Spinner, Notice } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

function useLookups() {
	const [ state, setState ] = useState( { loading: true, data: null, error: false } );
	useEffect( () => {
		let alive = true;
		apiFetch( { path: '/pointly-booking/v1/wizard/bootstrap' } )
			.then( ( response ) => alive && setState( { loading: false, data: response.data || response, error: false } ) )
			.catch( () => alive && setState( { loading: false, data: null, error: true } ) );
		return () => {
			alive = false;
		};
	}, [] );
	return state;
}

const toOptions = ( rows, anyLabel ) => [
	{ value: '0', label: anyLabel },
	...( rows || [] ).map( ( row ) => ( { value: String( row.id ), label: row.name } ) ),
];

export default function Edit( { attributes, setAttributes } ) {
	const { display, label, serviceId, categoryId, agentId, locationId, defaultDate, hideNotes, requirePhone, compact } = attributes;
	const lookups = useLookups();
	const data = lookups.data || {};
	const blockProps = useBlockProps( { className: 'pbk-block-preview' } );
	const buttonLabel = label || __( 'Book now', 'pointly-booking' );
	const service = ( data.services || [] ).find( ( row ) => row.id === serviceId );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Display', 'pointly-booking' ) }>
					<SelectControl
						label={ __( 'How the form appears', 'pointly-booking' ) }
						value={ display }
						options={ [
							{ value: 'button', label: __( 'Button that opens the booking window', 'pointly-booking' ) },
							{ value: 'inline', label: __( 'Form embedded in the page', 'pointly-booking' ) },
						] }
						onChange={ ( value ) => setAttributes( { display: value } ) }
						__nextHasNoMarginBottom
					/>
					{ display === 'button' && (
						<TextControl
							label={ __( 'Button text', 'pointly-booking' ) }
							value={ label }
							placeholder={ __( 'Book now', 'pointly-booking' ) }
							onChange={ ( value ) => setAttributes( { label: value } ) }
							__nextHasNoMarginBottom
						/>
					) }
					<ToggleControl
						label={ __( 'Compact layout', 'pointly-booking' ) }
						checked={ compact }
						onChange={ ( value ) => setAttributes( { compact: value } ) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
				<PanelBody title={ __( 'Preselect', 'pointly-booking' ) } initialOpen={ false }>
					{ lookups.loading && <Spinner /> }
					{ lookups.error && <Notice status="warning" isDismissible={ false }>{ __( 'Could not load services.', 'pointly-booking' ) }</Notice> }
					{ lookups.data && (
						<>
							<SelectControl
								label={ __( 'Service', 'pointly-booking' ) }
								value={ String( serviceId ) }
								options={ toOptions( data.services, __( 'Let the customer choose', 'pointly-booking' ) ) }
								onChange={ ( value ) => setAttributes( { serviceId: Number( value ) } ) }
								__nextHasNoMarginBottom
							/>
							{ ! serviceId && ( data.categories || [] ).length > 0 && (
								<SelectControl
									label={ __( 'Only this category', 'pointly-booking' ) }
									value={ String( categoryId ) }
									options={ toOptions( data.categories, __( 'All categories', 'pointly-booking' ) ) }
									onChange={ ( value ) => setAttributes( { categoryId: Number( value ) } ) }
									__nextHasNoMarginBottom
								/>
							) }
							{ ( data.agents || [] ).length > 0 && (
								<SelectControl
									label={ __( 'Staff member', 'pointly-booking' ) }
									value={ String( agentId ) }
									options={ toOptions( data.agents, __( 'Let the customer choose', 'pointly-booking' ) ) }
									onChange={ ( value ) => setAttributes( { agentId: Number( value ) } ) }
									__nextHasNoMarginBottom
								/>
							) }
							{ ( data.locations || [] ).length > 0 && (
								<SelectControl
									label={ __( 'Location', 'pointly-booking' ) }
									value={ String( locationId ) }
									options={ toOptions( data.locations, __( 'Let the customer choose', 'pointly-booking' ) ) }
									onChange={ ( value ) => setAttributes( { locationId: Number( value ) } ) }
									__nextHasNoMarginBottom
								/>
							) }
						</>
					) }
					<TextControl
						type="date"
						label={ __( 'Open on date', 'pointly-booking' ) }
						help={ __( 'Optional. The calendar opens on this date.', 'pointly-booking' ) }
						value={ defaultDate }
						onChange={ ( value ) => setAttributes( { defaultDate: value } ) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
				<PanelBody title={ __( 'Form fields', 'pointly-booking' ) } initialOpen={ false }>
					<ToggleControl
						label={ __( 'Hide the notes field', 'pointly-booking' ) }
						checked={ hideNotes }
						onChange={ ( value ) => setAttributes( { hideNotes: value } ) }
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __( 'Require a phone number', 'pointly-booking' ) }
						checked={ requirePhone }
						onChange={ ( value ) => setAttributes( { requirePhone: value } ) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className="pbk-block-preview__card">
					<div className="pbk-block-preview__head">
						<span className="pbk-block-preview__badge">{ __( 'Booking form', 'pointly-booking' ) }</span>
						<span className="pbk-block-preview__mode">
							{ display === 'inline' ? __( 'Embedded in the page', 'pointly-booking' ) : __( 'Opens in a window', 'pointly-booking' ) }
						</span>
					</div>
					{ display === 'inline' ? (
						<div className="pbk-block-preview__inline" aria-hidden="true">
							<span />
							<span />
							<span />
						</div>
					) : (
						<span className="pbk-block-preview__button">{ buttonLabel }</span>
					) }
					{ service && (
						<p className="pbk-block-preview__meta">
							{ __( 'Service:', 'pointly-booking' ) } { service.name }
						</p>
					) }
				</div>
			</div>
		</>
	);
}
