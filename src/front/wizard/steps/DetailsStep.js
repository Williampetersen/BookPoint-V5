/**
 * Details step: dynamic customer and booking fields with validation on blur.
 */
import { __ } from '@wordpress/i18n';
import {
	Field,
	Input,
	Textarea,
	Select,
	Checkbox,
	RadioCards,
	useUniqueId,
} from '../../../ui';
import { validateField } from '../flow';

const INPUT_TYPES = {
	email: 'email',
	tel: 'tel',
	number: 'number',
	date: 'date',
	text: 'text',
};

function Control( { field, value, onChange, onBlur, invalid } ) {
	const common = {
		onBlur,
		invalid,
		name: `pbk_${ field.scope }_${ field.key }`,
	};
	switch ( field.type ) {
		case 'textarea':
			return (
				<Textarea
					{ ...common }
					value={ value || '' }
					placeholder={ field.placeholder }
					onChange={ ( e ) => onChange( e.target.value ) }
					rows={ 3 }
				/>
			);
		case 'select':
			return (
				<Select
					{ ...common }
					value={ value || '' }
					placeholder={
						field.placeholder || __( 'Choose…', 'pointly-booking' )
					}
					options={ field.options.map( ( option ) => ( {
						value: option.value,
						label: option.label,
					} ) ) }
					onChange={ ( e ) => onChange( e.target.value ) }
				/>
			);
		case 'radio':
			return (
				<RadioCards
					legend={ field.label }
					hideLegend
					columns="2"
					invalid={ invalid }
					value={ value || '' }
					onChange={ ( next ) => {
						onChange( next );
						onBlur();
					} }
					options={ field.options.map( ( option ) => ( {
						value: option.value,
						label: option.label,
					} ) ) }
				/>
			);
		case 'checkbox':
			if ( field.options.length > 1 ) {
				const list = Array.isArray( value ) ? value : [];
				return (
					<div className="pbk-stack pbk-stack--tight">
						{ field.options.map( ( option ) => (
							<Checkbox
								key={ option.value }
								label={ option.label }
								checked={ list.includes( option.value ) }
								onChange={ ( e ) => {
									onChange(
										e.target.checked
											? [ ...list, option.value ]
											: list.filter(
													( item ) =>
														item !== option.value
											  )
									);
									onBlur();
								} }
							/>
						) ) }
					</div>
				);
			}
			return (
				<Checkbox
					{ ...common }
					label={
						field.options[ 0 ]
							? field.options[ 0 ].label
							: field.placeholder || field.label
					}
					checked={ !! value && value !== '0' }
					onChange={ ( e ) => {
						onChange( e.target.checked ? '1' : '' );
						onBlur();
					} }
				/>
			);
		default:
			return (
				<Input
					{ ...common }
					type={ INPUT_TYPES[ field.type ] || 'text' }
					value={ value || '' }
					placeholder={ field.placeholder }
					autoComplete={ field.autocomplete }
					inputMode={ field.type === 'tel' ? 'tel' : undefined }
					onChange={ ( e ) => onChange( e.target.value ) }
				/>
			);
	}
}

export default function DetailsStep( {
	fields,
	values,
	onChange,
	errors,
	touched,
	onTouch,
	honeypotRef,
} ) {
	const trapId = useUniqueId( 'pbk-website' );
	return (
		<div className="pbk-details">
			<div className="pbk-details__grid">
				{ fields.map( ( field ) => {
					const id = `${ field.scope }.${ field.key }`;
					const value = ( values[ field.scope ] || {} )[ field.key ];
					const error = touched[ id ] ? errors[ id ] : '';
					const singleCheckbox =
						field.type === 'checkbox' && field.options.length <= 1;
					return (
						<div
							key={ id }
							className={ `pbk-details__cell pbk-details__cell--${ field.width }` }
						>
							<Field
								label={
									singleCheckbox ? undefined : field.label
								}
								required={ field.required }
								optional={
									! field.required && field.key !== 'notes'
								}
								error={ error }
							>
								<Control
									field={ field }
									value={ value }
									invalid={ !! error }
									onChange={ ( next ) =>
										onChange( field, next )
									}
									onBlur={ () =>
										onTouch(
											id,
											validateField(
												field,
												( values[ field.scope ] || {} )[
													field.key
												]
											)
										)
									}
								/>
							</Field>
						</div>
					);
				} ) }
			</div>
			<div className="pbk-hp" aria-hidden="true">
				<label htmlFor={ trapId }>
					{ __( 'Leave this field empty', 'pointly-booking' ) }
				</label>
				<input
					ref={ honeypotRef }
					id={ trapId }
					type="text"
					name="website"
					tabIndex={ -1 }
					autoComplete="off"
				/>
			</div>
		</div>
	);
}
