/**
 * Button, IconButton and Spinner.
 */
import { forwardRef } from '@wordpress/element';
import { Icon } from '../icons';
import { Tooltip } from './Tooltip';
import './button.css';

const ICON_SIZE = { sm: 16, md: 18, lg: 20 };

export function Spinner( { size = 18, label, className = '' } ) {
	return (
		<span
			className={ `pbk-spinner ${ className }`.trim() }
			style={ { width: size, height: size } }
			role={ label ? 'status' : undefined }
			aria-hidden={ label ? undefined : 'true' }
		>
			{ label && <span className="pbk-sr-only">{ label }</span> }
		</span>
	);
}

export const Button = forwardRef( function Button(
	{
		variant = 'secondary',
		size = 'md',
		loading = false,
		disabled = false,
		icon,
		iconRight,
		block = false,
		href,
		className = '',
		children,
		type = 'button',
		onClick,
		...rest
	},
	ref
) {
	const classes = [
		'pbk-btn',
		`pbk-btn--${ variant }`,
		`pbk-btn--${ size }`,
		block ? 'pbk-btn--block' : '',
		loading ? 'is-loading' : '',
		! children ? 'pbk-btn--icon-only' : '',
		className,
	]
		.filter( Boolean )
		.join( ' ' );

	const iconSize = ICON_SIZE[ size ] || 18;
	const content = (
		<>
			{ loading && (
				<span
					className="pbk-btn__spinner pbk-spinner"
					aria-hidden="true"
				/>
			) }
			{ icon && (
				<Icon
					name={ icon }
					size={ iconSize }
					className="pbk-btn__icon"
				/>
			) }
			{ children !== undefined &&
				children !== null &&
				children !== false && (
					<span className="pbk-btn__label">{ children }</span>
				) }
			{ iconRight && (
				<Icon
					name={ iconRight }
					size={ iconSize }
					className="pbk-btn__icon"
				/>
			) }
		</>
	);

	// While loading the button stays focusable (aria-disabled) so keyboard users do not lose their place.
	const handleClick = ( event ) => {
		if ( loading || disabled ) {
			event.preventDefault();
			return;
		}
		if ( onClick ) {
			onClick( event );
		}
	};

	if ( href ) {
		return (
			<a
				ref={ ref }
				className={ classes }
				href={ disabled ? undefined : href }
				aria-disabled={ disabled || loading || undefined }
				onClick={ handleClick }
				{ ...rest }
			>
				{ content }
			</a>
		);
	}

	return (
		<button
			ref={ ref }
			type={ type } // eslint-disable-line react/button-has-type
			className={ classes }
			disabled={ disabled }
			aria-disabled={ loading || undefined }
			aria-busy={ loading || undefined }
			onClick={ handleClick }
			{ ...rest }
		>
			{ content }
		</button>
	);
} );

export const IconButton = forwardRef( function IconButton(
	{ icon, label, variant = 'ghost', size = 'md', tooltip = true, ...rest },
	ref
) {
	const button = (
		<Button
			ref={ ref }
			variant={ variant }
			size={ size }
			icon={ icon }
			aria-label={ label }
			{ ...rest }
		/>
	);
	return tooltip ? <Tooltip text={ label }>{ button }</Tooltip> : button;
} );
