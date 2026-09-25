/**
 * Generic renderer for a custom form-field definition (customer-scope extra fields).
 */
import { Field, Input, Textarea, Select, Checkbox } from '../../ui';

export function CustomFieldInput( { field, value, onChange } ) {
	const label = field.label;
	const required = !! field.is_required;

	if ( field.type === 'textarea' ) {
		return (
			<Field label={ label } required={ required } optional={ ! required }>
				<Textarea value={ value || '' } onChange={ ( e ) => onChange( e.target.value ) } rows={ 3 } />
			</Field>
		);
	}
	if ( field.type === 'select' ) {
		return (
			<Field label={ label } required={ required } optional={ ! required }>
				<Select value={ value || '' } onChange={ ( e ) => onChange( e.target.value ) } placeholder={ field.placeholder || '' } options={ field.options || [] } />
			</Field>
		);
	}
	if ( field.type === 'radio' ) {
		return (
			<Field label={ label } required={ required } optional={ ! required }>
				<div className="pbk-row">
					{ ( field.options || [] ).map( ( opt ) => {
						const optionId = `pbk-radio-${ field.field_key }-${ opt.value }`;
						return (
							<label key={ opt.value } htmlFor={ optionId } className="pbk-row" style={ { gap: 4 } }>
								<input id={ optionId } type="radio" name={ field.field_key } checked={ value === opt.value } onChange={ () => onChange( opt.value ) } />
								{ opt.label }
							</label>
						);
					} ) }
				</div>
			</Field>
		);
	}
	if ( field.type === 'checkbox' && ( field.options || [] ).length ) {
		const list = Array.isArray( value ) ? value : [];
		return (
			<Field label={ label } required={ required } optional={ ! required }>
				<div className="pbk-checkbox-grid">
					{ field.options.map( ( opt ) => (
						<Checkbox
							key={ opt.value }
							label={ opt.label }
							checked={ list.includes( opt.value ) }
							onChange={ ( e ) => onChange( e.target.checked ? [ ...list, opt.value ] : list.filter( ( v ) => v !== opt.value ) ) }
						/>
					) ) }
				</div>
			</Field>
		);
	}
	if ( field.type === 'checkbox' ) {
		return <Checkbox label={ label } checked={ !! value } onChange={ ( e ) => onChange( e.target.checked ) } />;
	}
	const inputType = { email: 'email', tel: 'tel', number: 'number', date: 'date' }[ field.type ] || 'text';
	return (
		<Field label={ label } required={ required } optional={ ! required }>
			<Input type={ inputType } value={ value || '' } placeholder={ field.placeholder || '' } onChange={ ( e ) => onChange( e.target.value ) } />
		</Field>
	);
}
