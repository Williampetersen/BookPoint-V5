/**
 * Selectable cards: RadioCards (single choice, native radios) and ChoiceCard (multi-select).
 */
import { useUniqueId } from '../hooks';
import { Icon } from '../icons';
import './choice.css';

/**
 * @param {Object}   props
 * @param {string}   props.name     Radio group name (auto when omitted).
 * @param {*}        props.value    Selected value.
 * @param {Function} props.onChange ( value ) => void.
 * @param {Array}    props.options  [ { value, label, description, media, aside, meta, disabled } ].
 * @param {string}   props.legend   Group label.
 * @param {boolean}  props.hideLegend Visually hide the legend.
 * @param {string}   props.columns  '1' | '2' | '3' | 'auto'.
 * @param {string}   props.layout   'row' (media left) | 'stack' (media on top).
 * @return {*} Group.
 */
export function RadioCards( {
	name,
	value,
	onChange,
	options = [],
	legend,
	hideLegend = false,
	columns = 'auto',
	layout = 'row',
	className = '',
	invalid = false,
	...rest
} ) {
	const autoName = useUniqueId( 'pbk-radio' );
	const group = name || autoName;
	return (
		<fieldset
			className={ `pbk-choices pbk-choices--cols-${ columns } pbk-choices--${ layout } ${ className }`.trim() }
			aria-invalid={ invalid || undefined }
			{ ...rest }
		>
			{ legend && (
				<legend
					className={ `pbk-choices__legend ${
						hideLegend ? 'pbk-sr-only' : ''
					}`.trim() }
				>
					{ legend }
				</legend>
			) }
			{ options.map( ( option ) => {
				const id = `${ group }-${ String( option.value ).replace(
					/[^a-z0-9_-]/gi,
					''
				) }`;
				const checked = String( value ) === String( option.value );
				return (
					<label
						key={ String( option.value ) }
						htmlFor={ id }
						className={ `pbk-choice ${
							checked ? 'is-selected' : ''
						} ${ option.disabled ? 'is-disabled' : '' }`.trim() }
					>
						<input
							id={ id }
							type="radio"
							className="pbk-choice__input"
							name={ group }
							value={ option.value }
							checked={ checked }
							disabled={ option.disabled }
							onChange={ () =>
								onChange && onChange( option.value )
							}
							aria-describedby={
								option.description ? `${ id }-desc` : undefined
							}
						/>
						<ChoiceBody
							option={ option }
							id={ id }
							indicator="radio"
						/>
					</label>
				);
			} ) }
		</fieldset>
	);
}

function ChoiceBody( { option, id, indicator } ) {
	return (
		<span className="pbk-choice__card">
			{ option.media && (
				<span className="pbk-choice__media">{ option.media }</span>
			) }
			<span className="pbk-choice__body">
				<span className="pbk-choice__title">{ option.label }</span>
				{ option.description && (
					<span
						className="pbk-choice__description"
						id={ `${ id }-desc` }
					>
						{ option.description }
					</span>
				) }
				{ option.meta && (
					<span className="pbk-choice__meta">{ option.meta }</span>
				) }
			</span>
			{ option.aside && (
				<span className="pbk-choice__aside">{ option.aside }</span>
			) }
			<span
				className={ `pbk-choice__indicator pbk-choice__indicator--${ indicator }` }
				aria-hidden="true"
			>
				<Icon name="check" size={ 14 } strokeWidth={ 2.5 } />
			</span>
		</span>
	);
}

/**
 * Multi-select card (checkbox).
 *
 * @param {Object}   props
 * @param {boolean}  props.checked  Selected.
 * @param {Function} props.onChange ( checked ) => void.
 * @return {*} Card.
 */
export function ChoiceCard( {
	checked = false,
	onChange,
	label,
	description,
	media,
	aside,
	meta,
	disabled = false,
	className = '',
	layout = 'row',
} ) {
	const id = useUniqueId( 'pbk-choice' );
	return (
		<label
			htmlFor={ id }
			className={ `pbk-choice pbk-choice--${ layout } ${
				checked ? 'is-selected' : ''
			} ${ disabled ? 'is-disabled' : '' } ${ className }`.trim() }
		>
			<input
				id={ id }
				type="checkbox"
				className="pbk-choice__input"
				checked={ checked }
				disabled={ disabled }
				onChange={ ( event ) =>
					onChange && onChange( event.target.checked )
				}
				aria-describedby={ description ? `${ id }-desc` : undefined }
			/>
			<ChoiceBody
				option={ { label, description, media, aside, meta } }
				id={ id }
				indicator="check"
			/>
		</label>
	);
}
