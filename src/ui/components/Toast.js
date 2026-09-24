/**
 * Toasts: ToastProvider + useToast().
 */
import {
	createContext,
	useCallback,
	useContext,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Portal } from '../theme/ThemeRoot';
import { Icon } from '../icons';
import './toast.css';

const ToastContext = createContext( null );

const ICONS = {
	success: 'check-circle',
	error: 'alert-circle',
	info: 'info',
	warning: 'alert-triangle',
};

let nextId = 0;

const defaultDuration = ( tone ) => ( tone === 'error' ? 8000 : 5000 );

function ToastItem( { toast, onDismiss } ) {
	const timer = useRef( 0 );
	const remaining = useRef( toast.duration );
	const started = useRef( 0 );

	const start = useCallback( () => {
		if ( ! remaining.current ) {
			return;
		}
		started.current = Date.now();
		timer.current = window.setTimeout(
			() => onDismiss( toast.id ),
			remaining.current
		);
	}, [ onDismiss, toast.id ] );

	const pause = () => {
		window.clearTimeout( timer.current );
		remaining.current = Math.max(
			1000,
			remaining.current - ( Date.now() - started.current )
		);
	};

	useEffect( () => {
		start();
		return () => window.clearTimeout( timer.current );
	}, [ start ] );

	return (
		<div
			className={ `pbk-toast pbk-toast--${ toast.tone }` }
			onMouseEnter={ pause }
			onMouseLeave={ start }
			onFocus={ pause }
			onBlur={ start }
		>
			<Icon
				name={ ICONS[ toast.tone ] || 'info' }
				size={ 20 }
				className="pbk-toast__icon"
			/>
			<div className="pbk-toast__body">
				{ toast.title && (
					<p className="pbk-toast__title">{ toast.title }</p>
				) }
				<p className="pbk-toast__message">{ toast.message }</p>
			</div>
			{ toast.action && (
				<button
					type="button"
					className="pbk-toast__action"
					onClick={ () => {
						toast.action.onClick();
						onDismiss( toast.id );
					} }
				>
					{ toast.action.label }
				</button>
			) }
			<button
				type="button"
				className="pbk-toast__close"
				onClick={ () => onDismiss( toast.id ) }
				aria-label={ __( 'Dismiss', 'pointly-booking' ) }
			>
				<Icon name="x" size={ 16 } />
			</button>
		</div>
	);
}

export function ToastProvider( { children } ) {
	const [ toasts, setToasts ] = useState( [] );

	const dismiss = useCallback(
		( id ) =>
			setToasts( ( list ) =>
				list.filter( ( toast ) => toast.id !== id )
			),
		[]
	);

	const show = useCallback( ( message, options = {} ) => {
		nextId += 1;
		const toast = {
			id: nextId,
			message,
			tone: options.tone || 'info',
			title: options.title,
			action: options.action,
			duration:
				options.duration === undefined
					? defaultDuration( options.tone )
					: options.duration,
		};
		setToasts( ( list ) => [ ...list.slice( -3 ), toast ] );
		return toast.id;
	}, [] );

	const api = useMemo(
		() => ( {
			show,
			dismiss,
			success: ( message, options ) =>
				show( message, { ...options, tone: 'success' } ),
			error: ( message, options ) =>
				show( message, { ...options, tone: 'error' } ),
			info: ( message, options ) =>
				show( message, { ...options, tone: 'info' } ),
			warning: ( message, options ) =>
				show( message, { ...options, tone: 'warning' } ),
		} ),
		[ show, dismiss ]
	);

	const polite = toasts.filter( ( toast ) => toast.tone !== 'error' );
	const urgent = toasts.filter( ( toast ) => toast.tone === 'error' );

	return (
		<ToastContext.Provider value={ api }>
			{ children }
			<Portal>
				<div className="pbk-toasts">
					<div
						role="status"
						aria-live="polite"
						className="pbk-toasts__region"
					>
						{ polite.map( ( toast ) => (
							<ToastItem
								key={ toast.id }
								toast={ toast }
								onDismiss={ dismiss }
							/>
						) ) }
					</div>
					<div
						role="alert"
						aria-live="assertive"
						className="pbk-toasts__region"
					>
						{ urgent.map( ( toast ) => (
							<ToastItem
								key={ toast.id }
								toast={ toast }
								onDismiss={ dismiss }
							/>
						) ) }
					</div>
				</div>
			</Portal>
		</ToastContext.Provider>
	);
}

/**
 * Toast API: { success, error, info, warning, show, dismiss }.
 *
 * @return {Object} API.
 */
export function useToast() {
	const api = useContext( ToastContext );
	if ( ! api ) {
		const noop = () => 0;
		return {
			show: noop,
			dismiss: noop,
			success: noop,
			error: noop,
			info: noop,
			warning: noop,
		};
	}
	return api;
}
