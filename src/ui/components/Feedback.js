/**
 * Skeleton, EmptyState, ErrorState, Notice.
 */
import { __ } from '@wordpress/i18n';
import { Icon } from '../icons';
import { Button } from './Button';
import './feedback.css';

export function Skeleton( {
	variant = 'text',
	width,
	height,
	lines = 1,
	className = '',
	style,
} ) {
	if ( variant === 'text' && lines > 1 ) {
		return (
			<span
				className={ `pbk-skeleton-lines ${ className }`.trim() }
				aria-hidden="true"
			>
				{ Array.from( { length: lines } ).map( ( _, i ) => (
					<span
						key={ i }
						className="pbk-skeleton pbk-skeleton--text"
						style={ {
							inlineSize: i === lines - 1 ? '60%' : '100%',
						} }
					/>
				) ) }
			</span>
		);
	}
	return (
		<span
			className={ `pbk-skeleton pbk-skeleton--${ variant } ${ className }`.trim() }
			style={ { inlineSize: width, blockSize: height, ...style } }
			aria-hidden="true"
		/>
	);
}

/**
 * Friendly empty state with a small line illustration.
 *
 * @param {Object} props
 * @param {string} props.icon        Icon name for the illustration.
 * @param {string} props.title       Title.
 * @param {string} props.description Text.
 * @param {*}      props.action      Button(s).
 * @return {*} Empty state.
 */
export function EmptyState( {
	icon = 'calendar',
	title,
	description,
	action,
	className = '',
	compact = false,
} ) {
	return (
		<div
			className={ `pbk-empty ${
				compact ? 'pbk-empty--compact' : ''
			} ${ className }`.trim() }
		>
			<div className="pbk-empty__art" aria-hidden="true">
				<svg viewBox="0 0 120 96" width="120" height="96" fill="none">
					<rect
						x="14"
						y="18"
						width="92"
						height="64"
						rx="16"
						className="pbk-empty__panel"
					/>
					<rect
						x="26"
						y="32"
						width="36"
						height="6"
						rx="3"
						className="pbk-empty__line"
					/>
					<rect
						x="26"
						y="46"
						width="56"
						height="6"
						rx="3"
						className="pbk-empty__line pbk-empty__line--soft"
					/>
					<rect
						x="26"
						y="60"
						width="44"
						height="6"
						rx="3"
						className="pbk-empty__line pbk-empty__line--soft"
					/>
					<circle
						cx="92"
						cy="22"
						r="14"
						className="pbk-empty__badge"
					/>
				</svg>
				<span className="pbk-empty__icon">
					<Icon name={ icon } size={ 18 } />
				</span>
			</div>
			{ title && <h3 className="pbk-empty__title">{ title }</h3> }
			{ description && (
				<p className="pbk-empty__description">{ description }</p>
			) }
			{ action && <div className="pbk-empty__actions">{ action }</div> }
		</div>
	);
}

export function ErrorState( {
	title,
	message,
	onRetry,
	retryLabel,
	className = '',
} ) {
	return (
		<div
			className={ `pbk-empty pbk-empty--error ${ className }`.trim() }
			role="alert"
		>
			<span className="pbk-empty__error-icon" aria-hidden="true">
				<Icon name="alert-triangle" size={ 24 } />
			</span>
			<h3 className="pbk-empty__title">
				{ title || __( 'Something went wrong', 'pointly-booking' ) }
			</h3>
			{ message && <p className="pbk-empty__description">{ message }</p> }
			{ onRetry && (
				<div className="pbk-empty__actions">
					<Button
						variant="secondary"
						icon="refresh"
						onClick={ onRetry }
					>
						{ retryLabel || __( 'Try again', 'pointly-booking' ) }
					</Button>
				</div>
			) }
		</div>
	);
}

const NOTICE_ICONS = {
	info: 'info',
	success: 'check-circle',
	warning: 'alert-triangle',
	danger: 'alert-circle',
	neutral: 'info',
};

export function Notice( {
	tone = 'info',
	title,
	children,
	action,
	onDismiss,
	className = '',
	icon,
} ) {
	return (
		<div
			className={ `pbk-notice pbk-notice--${ tone } ${ className }`.trim() }
			role={ tone === 'danger' ? 'alert' : undefined }
		>
			<Icon
				name={ icon || NOTICE_ICONS[ tone ] }
				size={ 20 }
				className="pbk-notice__icon"
			/>
			<div className="pbk-notice__body">
				{ title && <p className="pbk-notice__title">{ title }</p> }
				{ children && (
					<div className="pbk-notice__text">{ children }</div>
				) }
				{ action && (
					<div className="pbk-notice__action">{ action }</div>
				) }
			</div>
			{ onDismiss && (
				<button
					type="button"
					className="pbk-notice__dismiss"
					onClick={ onDismiss }
					aria-label={ __( 'Dismiss', 'pointly-booking' ) }
				>
					<Icon name="x" size={ 16 } />
				</button>
			) }
		</div>
	);
}
