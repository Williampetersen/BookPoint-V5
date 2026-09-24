/**
 * Modal, Drawer and ConfirmDialog (useConfirm).
 */
import {
	createContext,
	useCallback,
	useContext,
	useRef,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Portal } from '../theme/ThemeRoot';
import {
	useFocusTrap,
	usePresence,
	useScrollLock,
	useUniqueId,
} from '../hooks';
import { Icon } from '../icons';
import { Button } from './Button';
import './overlay.css';

function Surface( {
	kind,
	open,
	onClose,
	title,
	description,
	children,
	footer,
	size = 'md',
	initialFocusRef,
	hideClose = false,
	closeLabel,
	className = '',
	bodyClassName = '',
	headerExtra,
	role = 'dialog',
	labelledBy,
} ) {
	const [ mounted, visible ] = usePresence( open, 250 );
	const ref = useRef( null );
	const titleId = useUniqueId( `pbk-${ kind }-title` );
	const descId = useUniqueId( `pbk-${ kind }-desc` );
	useScrollLock( mounted );
	useFocusTrap( ref, open && mounted, {
		onEscape: onClose,
		initialFocus: initialFocusRef,
	} );

	if ( ! mounted ) {
		return null;
	}

	const onBackdrop = ( event ) => {
		if ( event.target === event.currentTarget && onClose ) {
			onClose( event );
		}
	};

	return (
		<Portal>
			{ /* Clicking the backdrop is a mouse shortcut; Escape and the close button cover keyboard users. */ }
			{ /* eslint-disable-next-line jsx-a11y/no-static-element-interactions */ }
			<div
				className={ `pbk-overlay pbk-overlay--${ kind } ${
					visible ? 'is-open' : ''
				}` }
				onMouseDown={ onBackdrop }
			>
				<div
					ref={ ref }
					role={ role }
					aria-modal="true"
					aria-labelledby={
						labelledBy || ( title ? titleId : undefined )
					}
					aria-describedby={ description ? descId : undefined }
					tabIndex={ -1 }
					className={ `pbk-${ kind } pbk-${ kind }--${ size } ${ className }`.trim() }
				>
					{ ( title || ! hideClose ) && (
						<div className={ `pbk-${ kind }__header` }>
							<div className={ `pbk-${ kind }__heading` }>
								{ title && (
									<h2
										className={ `pbk-${ kind }__title` }
										id={ titleId }
									>
										{ title }
									</h2>
								) }
								{ description && (
									<p
										className={ `pbk-${ kind }__description` }
										id={ descId }
									>
										{ description }
									</p>
								) }
							</div>
							{ headerExtra }
							{ ! hideClose && (
								<button
									type="button"
									className="pbk-overlay__close"
									onClick={ onClose }
									aria-label={
										closeLabel ||
										__( 'Close', 'pointly-booking' )
									}
								>
									<Icon name="x" size={ 20 } />
								</button>
							) }
						</div>
					) }
					<div
						className={ `pbk-${ kind }__body ${ bodyClassName }`.trim() }
					>
						{ children }
					</div>
					{ footer && (
						<div className={ `pbk-${ kind }__footer` }>
							{ footer }
						</div>
					) }
				</div>
			</div>
		</Portal>
	);
}

/**
 * Centered dialog; a full-screen sheet below 600 px.
 *
 * @param {Object} props See Surface.
 * @return {*} Modal.
 */
export function Modal( props ) {
	return <Surface kind="modal" { ...props } />;
}

/**
 * Side sheet from the inline end; a bottom sheet below 600 px.
 *
 * @param {Object} props See Surface.
 * @return {*} Drawer.
 */
export function Drawer( props ) {
	return <Surface kind="drawer" { ...props } />;
}

// Fallback outside a ConfirmProvider: resolve to "yes" (the caller asked for the action).
const ConfirmContext = createContext( () => Promise.resolve( true ) );

/**
 * Provides useConfirm() to its tree.
 *
 * @param {Object} props          Props.
 * @param {*}      props.children Children.
 * @return {*} Provider.
 */
export function ConfirmProvider( { children } ) {
	const [ state, setState ] = useState( null );
	const confirmRef = useRef( null );

	const confirm = useCallback(
		( options ) =>
			new Promise( ( resolve ) => {
				setState( { ...options, resolve } );
			} ),
		[]
	);

	const close = ( result ) => {
		if ( state ) {
			state.resolve( result );
		}
		setState( null );
	};

	return (
		<ConfirmContext.Provider value={ confirm }>
			{ children }
			<Modal
				open={ !! state }
				onClose={ () => close( false ) }
				title={ state ? state.title : '' }
				size="sm"
				role="alertdialog"
				initialFocusRef={
					state && state.tone === 'danger' ? undefined : confirmRef
				}
				className="pbk-confirm"
				footer={
					state && (
						<>
							<Button
								variant="secondary"
								onClick={ () => close( false ) }
							>
								{ state.cancelLabel ||
									__( 'Cancel', 'pointly-booking' ) }
							</Button>
							<Button
								ref={ confirmRef }
								variant={
									state.tone === 'danger'
										? 'danger'
										: 'primary'
								}
								onClick={ () => close( true ) }
							>
								{ state.confirmLabel ||
									__( 'Confirm', 'pointly-booking' ) }
							</Button>
						</>
					)
				}
			>
				{ state && state.message && (
					<p className="pbk-confirm__message">{ state.message }</p>
				) }
			</Modal>
		</ConfirmContext.Provider>
	);
}

/**
 * Returns confirm( { title, message, confirmLabel, cancelLabel, tone } ) → Promise<boolean>.
 *
 * @return {Function} confirm.
 */
export const useConfirm = () => useContext( ConfirmContext );
