/**
 * Card, Badge, StatusBadge, Avatar.
 */
import { __ } from '@wordpress/i18n';
import { Icon } from '../icons';
import './display.css';

export function Card( {
	title,
	description,
	actions,
	children,
	footer,
	className = '',
	padding = 'md',
	as: Tag = 'section',
	headingLevel = 2,
	...rest
} ) {
	const Heading = `h${ headingLevel }`;
	return (
		<Tag
			className={ `pbk-card pbk-card--pad-${ padding } ${ className }`.trim() }
			{ ...rest }
		>
			{ ( title || actions ) && (
				<div className="pbk-card__header">
					<div className="pbk-card__heading">
						{ title && (
							<Heading className="pbk-card__title">
								{ title }
							</Heading>
						) }
						{ description && (
							<p className="pbk-card__description">
								{ description }
							</p>
						) }
					</div>
					{ actions && (
						<div className="pbk-card__actions">{ actions }</div>
					) }
				</div>
			) }
			{ children !== undefined && (
				<div className="pbk-card__body">{ children }</div>
			) }
			{ footer && <div className="pbk-card__footer">{ footer }</div> }
		</Tag>
	);
}

export function Badge( {
	tone = 'neutral',
	dot = false,
	icon,
	children,
	className = '',
	size = 'md',
} ) {
	return (
		<span
			className={ `pbk-badge pbk-badge--${ tone } pbk-badge--${ size } ${ className }`.trim() }
		>
			{ dot && <span className="pbk-badge__dot" aria-hidden="true" /> }
			{ icon && <Icon name={ icon } size={ 14 } /> }
			{ children }
		</span>
	);
}

export const STATUS_TONES = {
	pending: 'warning',
	confirmed: 'success',
	completed: 'info',
	cancelled: 'neutral',
	pending_payment: 'brand',
	failed_payment: 'danger',
};

export function statusLabel( status ) {
	const labels = {
		pending: __( 'Pending', 'pointly-booking' ),
		confirmed: __( 'Confirmed', 'pointly-booking' ),
		completed: __( 'Completed', 'pointly-booking' ),
		cancelled: __( 'Cancelled', 'pointly-booking' ),
		pending_payment: __( 'Awaiting payment', 'pointly-booking' ),
		failed_payment: __( 'Payment failed', 'pointly-booking' ),
	};
	return labels[ status ] || status;
}

export function StatusBadge( { status, label, size } ) {
	return (
		<Badge tone={ STATUS_TONES[ status ] || 'neutral' } dot size={ size }>
			{ label || statusLabel( status ) }
		</Badge>
	);
}

const TINTS = [
	'#e8e7fd',
	'#e3f2fb',
	'#e6f6ec',
	'#fdf0e1',
	'#fbe7ef',
	'#efe7fb',
	'#e5f4f2',
	'#f5eedd',
];
const INKS = [
	'#3a32b4',
	'#16608f',
	'#1d6b3d',
	'#8a4d0f',
	'#9c2a55',
	'#5b2f98',
	'#1c6660',
	'#6e5716',
];

function hash( text ) {
	let value = 0;
	for ( let i = 0; i < text.length; i++ ) {
		value = ( value * 31 + text.charCodeAt( i ) ) % 9973;
	}
	return value;
}

export function initials( name ) {
	const parts = String( name || '' )
		.trim()
		.split( /\s+/ )
		.filter( Boolean );
	if ( ! parts.length ) {
		return '?';
	}
	const first = parts[ 0 ][ 0 ] || '';
	const last = parts.length > 1 ? parts[ parts.length - 1 ][ 0 ] : '';
	return ( first + last ).toUpperCase();
}

export function Avatar( { name = '', src, size = 40, className = '', icon } ) {
	const index = hash( name ) % TINTS.length;
	const style = {
		width: size,
		height: size,
		fontSize: Math.round( size * 0.38 ),
	};
	if ( src ) {
		return (
			<span
				className={ `pbk-avatar ${ className }`.trim() }
				style={ style }
			>
				<img src={ src } alt="" loading="lazy" decoding="async" />
			</span>
		);
	}
	return (
		<span
			className={ `pbk-avatar ${ className }`.trim() }
			style={ {
				...style,
				background: TINTS[ index ],
				color: INKS[ index ],
			} }
			aria-hidden="true"
		>
			{ icon ? (
				<Icon name={ icon } size={ Math.round( size * 0.5 ) } />
			) : (
				initials( name )
			) }
		</span>
	);
}
