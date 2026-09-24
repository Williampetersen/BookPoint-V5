/**
 * Form controls: Field, Input, Textarea, Select, Checkbox, Toggle.
 */
import {
	cloneElement,
	forwardRef,
	isValidElement,
	useEffect,
	useRef,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Icon } from '../icons';
import { useUniqueId } from '../hooks';
import './form.css';

/**
 * Label + control + help + error, with ARIA wiring.
 *
 * The child control receives id, aria-describedby, aria-invalid and aria-required.
 *
 * @param {Object} props
 * @return {*} Field.
 */
export function Field( {
	label,
	help,
	error,
	required = false,
	optional = false,
	id,
	children,
	className = '',
	hideLabel = false,
	inline = false,
} ) {
	const autoId = useUniqueId( 'pbk-field' );
	const controlId = id || autoId;
	const helpId = help ? `${ controlId }-help` : undefined;
	const errorId = error ? `${ controlId }-error` : undefined;
	const describedBy =
		[ errorId, helpId ].filter( Boolean ).join( ' ' ) || undefined;

	const control = isValidElement( children )
		? cloneElement( children, {
				id: children.props.id || controlId,
				'aria-describedby':
					[ children.props[ 'aria-describedby' ], describedBy ]
						.filter( Boolean )
						.join( ' ' ) || undefined,
				'aria-invalid': error ? true : children.props[ 'aria-invalid' ],
				'aria-required': required || undefined,
				// Only our components understand "invalid"; native elements rely on aria-invalid.
				...( typeof children.type === 'string'
					? {}
					: { invalid: error ? true : children.props.invalid } ),
		  } )
		: children;

	return (
		<div
			className={ `pbk-field ${ error ? 'has-error' : '' } ${
				inline ? 'pbk-field--inline' : ''
			} ${ className }`.trim() }
		>
			{ label && (
				<label
					className={ `pbk-field__label ${
						hideLabel ? 'pbk-sr-only' : ''
					}`.trim() }
					htmlFor={ controlId }
				>
					{ label }
					{ required && (
						<span
							className="pbk-field__required"
							aria-hidden="true"
						>
							*
						</span>
					) }
					{ optional && ! required && (
						<span className="pbk-field__optional">
							{ __( '(optional)', 'pointly-booking' ) }
						</span>
					) }
				</label>
			) }
			{ control }
			{ error && (
				<p className="pbk-field__error" id={ errorId } role="alert">
					<Icon name="alert-circle" size={ 16 } />
					<span>{ error }</span>
				</p>
			) }
			{ help && (
				<p className="pbk-field__help" id={ helpId }>
					{ help }
				</p>
			) }
		</div>
	);
}

export const Input = forwardRef( function Input(
	{
		invalid,
		prefix,
		prefixIcon,
		suffix,
		className = '',
		size = 'md',
		type = 'text',
		...rest
	},
	ref
) {
	if ( prefixIcon ) {
		prefix = <Icon name={ prefixIcon } size={ 18 } />;
	}
	const input = (
		<input
			ref={ ref }
			type={ type }
			className={ `pbk-input pbk-input--${ size } ${
				invalid ? 'is-invalid' : ''
			} ${ prefix || suffix ? '' : className }`.trim() }
			{ ...rest }
		/>
	);
	if ( ! prefix && ! suffix ) {
		return input;
	}
	return (
		<div
			className={ `pbk-input-group ${ invalid ? 'is-invalid' : '' } ${
				rest.disabled ? 'is-disabled' : ''
			} ${ className }`.trim() }
		>
			{ prefix && (
				<span className="pbk-input-group__addon">{ prefix }</span>
			) }
			{ input }
			{ suffix && (
				<span className="pbk-input-group__addon">{ suffix }</span>
			) }
		</div>
	);
} );

export const SearchInput = forwardRef( function SearchInput(
	{ label, onClear, value, ...rest },
	ref
) {
	return (
		<div className="pbk-search">
			<Icon name="search" size={ 18 } className="pbk-search__icon" />
			<input
				ref={ ref }
				type="search"
				className="pbk-input pbk-search__input"
				aria-label={ label }
				placeholder={ rest.placeholder || label }
				value={ value }
				{ ...rest }
			/>
			{ onClear && value && (
				<button
					type="button"
					className="pbk-search__clear"
					onClick={ onClear }
					aria-label={ __( 'Clear search', 'pointly-booking' ) }
				>
					<Icon name="x" size={ 16 } />
				</button>
			) }
		</div>
	);
} );

export const Textarea = forwardRef( function Textarea(
	{ invalid, className = '', autoGrow = true, rows = 3, onChange, ...rest },
	ref
) {
	const inner = useRef( null );
	const resize = () => {
		const el = inner.current;
		if ( autoGrow && el ) {
			el.style.height = 'auto';
			el.style.height = `${ Math.min( el.scrollHeight + 2, 480 ) }px`;
		}
	};
	useEffect( resize, [ rest.value, autoGrow ] );
	return (
		<textarea
			ref={ ( node ) => {
				inner.current = node;
				if ( typeof ref === 'function' ) {
					ref( node );
				} else if ( ref ) {
					ref.current = node;
				}
			} }
			rows={ rows }
			className={ `pbk-input pbk-textarea ${
				invalid ? 'is-invalid' : ''
			} ${ className }`.trim() }
			onChange={ ( event ) => {
				if ( onChange ) {
					onChange( event );
				}
				resize();
			} }
			{ ...rest }
		/>
	);
} );

/**
 * Native select.
 *
 * @param {Object} props
 * @param {Array}  props.options [ { value, label, disabled } ] or [ { label, options: [] } ] groups.
 * @return {*} Select.
 */
export const Select = forwardRef( function Select(
	{
		invalid,
		options = [],
		placeholder,
		className = '',
		size = 'md',
		children,
		...rest
	},
	ref
) {
	const renderOption = ( option ) => (
		<option
			key={ String( option.value ) }
			value={ option.value }
			disabled={ option.disabled }
		>
			{ option.label }
		</option>
	);
	return (
		<div
			className={ `pbk-select pbk-select--${ size } ${
				invalid ? 'is-invalid' : ''
			} ${ className }`.trim() }
		>
			<select ref={ ref } className="pbk-select__control" { ...rest }>
				{ placeholder !== undefined && (
					<option value="">{ placeholder }</option>
				) }
				{ children ||
					options.map( ( option ) =>
						option.options ? (
							<optgroup
								key={ option.label }
								label={ option.label }
							>
								{ option.options.map( renderOption ) }
							</optgroup>
						) : (
							renderOption( option )
						)
					) }
			</select>
			<Icon
				name="chevron-down"
				size={ 16 }
				className="pbk-select__chevron"
			/>
		</div>
	);
} );

export const Checkbox = forwardRef( function Checkbox(
	{
		label,
		description,
		invalid,
		className = '',
		indeterminate = false,
		id,
		...rest
	},
	ref
) {
	const autoId = useUniqueId( 'pbk-check' );
	const inputId = id || autoId;
	const inner = useRef( null );
	useEffect( () => {
		if ( inner.current ) {
			inner.current.indeterminate = indeterminate;
		}
	}, [ indeterminate ] );
	return (
		<div
			className={ `pbk-check ${ invalid ? 'is-invalid' : '' } ${
				! label ? 'pbk-check--bare' : ''
			} ${ className }`.trim() }
		>
			<span className="pbk-check__control">
				<input
					ref={ ( node ) => {
						inner.current = node;
						if ( typeof ref === 'function' ) {
							ref( node );
						} else if ( ref ) {
							ref.current = node;
						}
					} }
					id={ inputId }
					type="checkbox"
					className="pbk-check__input"
					aria-describedby={
						description ? `${ inputId }-desc` : undefined
					}
					{ ...rest }
				/>
				<span className="pbk-check__box" aria-hidden="true">
					<Icon
						name={ indeterminate ? 'minus' : 'check' }
						size={ 14 }
						strokeWidth={ 2.5 }
					/>
				</span>
			</span>
			{ label && (
				<span className="pbk-check__text">
					<label htmlFor={ inputId } className="pbk-check__label">
						{ label }
					</label>
					{ description && (
						<span
							className="pbk-check__description"
							id={ `${ inputId }-desc` }
						>
							{ description }
						</span>
					) }
				</span>
			) }
		</div>
	);
} );

/**
 * On/off switch.
 *
 * @param {Object}   props
 * @param {boolean}  props.checked  State.
 * @param {Function} props.onChange ( checked ) => void.
 * @param {string}   props.label    Visible label (or pass aria-label).
 * @return {*} Toggle.
 */
export function Toggle( {
	checked = false,
	onChange,
	label,
	description,
	disabled = false,
	id,
	className = '',
	...rest
} ) {
	const autoId = useUniqueId( 'pbk-toggle' );
	const buttonId = id || autoId;
	return (
		<div className={ `pbk-toggle ${ className }`.trim() }>
			<button
				id={ buttonId }
				type="button"
				role="switch"
				aria-checked={ checked }
				aria-labelledby={ label ? `${ buttonId }-label` : undefined }
				aria-describedby={
					description ? `${ buttonId }-desc` : undefined
				}
				disabled={ disabled }
				className={ `pbk-toggle__switch ${ checked ? 'is-on' : '' }` }
				onClick={ () => onChange && onChange( ! checked ) }
				{ ...rest }
			>
				<span className="pbk-toggle__thumb" />
			</button>
			{ label && (
				<span className="pbk-toggle__text">
					<span
						className="pbk-toggle__label"
						id={ `${ buttonId }-label` }
					>
						{ label }
					</span>
					{ description && (
						<span
							className="pbk-toggle__description"
							id={ `${ buttonId }-desc` }
						>
							{ description }
						</span>
					) }
				</span>
			) }
		</div>
	);
}
