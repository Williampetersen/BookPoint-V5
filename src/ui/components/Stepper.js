/**
 * Stepper: horizontal list (desktop) or compact "Step 2 of 5" bar (mobile).
 * Finished steps are buttons (go back without losing data); upcoming steps are inert.
 */
import { __, sprintf } from '@wordpress/i18n';
import { Icon } from '../icons';
import './stepper.css';

/**
 * @param {Object}   props
 * @param {Array}    props.steps   [ { key, label } ].
 * @param {string}   props.current Current step key.
 * @param {Array}    props.done    Keys of completed steps.
 * @param {Function} props.onStepClick ( key ) => void.
 * @param {string}   props.variant horizontal | vertical | compact.
 * @return {*} Stepper.
 */
export function Stepper( {
	steps = [],
	current,
	done = [],
	onStepClick,
	variant = 'horizontal',
	label,
	className = '',
} ) {
	const index = Math.max(
		0,
		steps.findIndex( ( step ) => step.key === current )
	);

	if ( variant === 'compact' ) {
		const progress =
			steps.length > 1 ? ( index / ( steps.length - 1 ) ) * 100 : 100;
		return (
			<div
				className={ `pbk-stepper pbk-stepper--compact ${ className }`.trim() }
			>
				<div className="pbk-stepper__compact-text">
					<span className="pbk-stepper__count">
						{ sprintf(
							/* translators: 1: current step number, 2: total steps */
							__( 'Step %1$d of %2$d', 'pointly-booking' ),
							index + 1,
							steps.length
						) }
					</span>
					<span className="pbk-stepper__compact-label">
						{ steps[ index ] && steps[ index ].label }
					</span>
				</div>
				<div
					className="pbk-stepper__bar"
					role="progressbar"
					aria-valuemin={ 1 }
					aria-valuemax={ steps.length }
					aria-valuenow={ index + 1 }
					aria-label={
						label || __( 'Booking progress', 'pointly-booking' )
					}
				>
					<span style={ { inlineSize: `${ progress }%` } } />
				</div>
			</div>
		);
	}

	return (
		<nav
			className={ `pbk-stepper pbk-stepper--${ variant } ${ className }`.trim() }
			aria-label={ label || __( 'Booking steps', 'pointly-booking' ) }
		>
			<ol className="pbk-stepper__list">
				{ steps.map( ( step, i ) => {
					const isCurrent = step.key === current;
					const isDone = done.includes( step.key ) && ! isCurrent;
					const clickable = isDone && !! onStepClick;
					let state = 'upcoming';
					if ( isCurrent ) {
						state = 'current';
					} else if ( isDone ) {
						state = 'done';
					}
					const content = (
						<>
							<span
								className="pbk-stepper__marker"
								aria-hidden="true"
							>
								{ isDone ? (
									<Icon
										name="check"
										size={ 14 }
										strokeWidth={ 2.5 }
									/>
								) : (
									i + 1
								) }
							</span>
							<span className="pbk-stepper__label">
								{ step.label }
							</span>
							{ isDone && (
								<span className="pbk-sr-only">
									{ __( '(completed)', 'pointly-booking' ) }
								</span>
							) }
						</>
					);
					return (
						<li
							key={ step.key }
							className={ `pbk-stepper__item is-${ state }` }
						>
							{ clickable ? (
								<button
									type="button"
									className="pbk-stepper__step"
									onClick={ () => onStepClick( step.key ) }
								>
									{ content }
								</button>
							) : (
								<span
									className="pbk-stepper__step"
									aria-current={
										isCurrent ? 'step' : undefined
									}
								>
									{ content }
								</span>
							) }
						</li>
					);
				} ) }
			</ol>
		</nav>
	);
}
