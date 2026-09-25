/**
 * Booking widget: launch button + modal, or the wizard embedded in the page.
 * The wizard code is a lazy chunk, prefetched on hover/focus and when the browser is idle.
 */
import {
	lazy,
	Suspense,
	useCallback,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	ThemeRoot,
	Button,
	Modal,
	ConfirmProvider,
	useConfirm,
	Skeleton,
} from '../ui';

const loadWizard = () =>
	import( /* webpackChunkName: "front/wizard" */ './wizard/Wizard' );
const Wizard = lazy( loadWizard );

let prefetched = false;
export function prefetchWizard() {
	if ( ! prefetched ) {
		prefetched = true;
		loadWizard().catch( () => {
			prefetched = false;
		} );
		import( /* webpackChunkName: "front/wizard" */ './wizard/api' )
			.then( ( module ) => module.loadBootstrap().catch( () => {} ) )
			.catch( () => {} );
	}
}

function Fallback() {
	return (
		<div className="pbk-wizard pbk-wizard--state" aria-busy="true">
			<span className="pbk-sr-only">
				{ __( 'Loading the booking form…', 'pointly-booking' ) }
			</span>
			<div className="pbk-wizard__loading">
				<Skeleton variant="text" width="40%" />
				<Skeleton variant="block" height={ 72 } />
				<Skeleton variant="block" height={ 72 } />
			</div>
		</div>
	);
}

function ModalWizard( {
	config,
	options,
	open,
	setOpen,
	paymentReturn,
	clearReturn,
} ) {
	const confirm = useConfirm();
	const dirty = useRef( false );
	const [ session, setSession ] = useState( 0 );

	const close = useCallback( async () => {
		if ( dirty.current && config.confirmOnClose !== false ) {
			const leave = await confirm( {
				title: __( 'Leave this booking?', 'pointly-booking' ),
				message: __(
					'Your choices will not be saved.',
					'pointly-booking'
				),
				confirmLabel: __( 'Leave', 'pointly-booking' ),
				cancelLabel: __( 'Keep booking', 'pointly-booking' ),
				tone: 'danger',
			} );
			if ( ! leave ) {
				return;
			}
		}
		dirty.current = false;
		setOpen( false );
		clearReturn();
		setSession( ( n ) => n + 1 );
	}, [ config.confirmOnClose, confirm, setOpen, clearReturn ] );

	return (
		<Modal
			open={ open }
			onClose={ close }
			title={
				config.businessName ||
				__( 'Book an appointment', 'pointly-booking' )
			}
			size="xl"
			className="pbk-wizard-modal"
			bodyClassName="pbk-wizard-modal__body"
		>
			<Suspense fallback={ <Fallback /> }>
				<Wizard
					key={ session }
					mode="modal"
					config={ config }
					options={ options }
					paymentReturn={ paymentReturn }
					onDirtyChange={ ( value ) => {
						dirty.current = value;
					} }
					onRequestClose={ close }
					onRestart={ () => setSession( ( n ) => n + 1 ) }
				/>
			</Suspense>
		</Modal>
	);
}

export default function BookingWidget( {
	config,
	options = {},
	paymentReturn = null,
	registerOpener,
	hideButton = false,
} ) {
	const appearance = config.appearance || {};
	const [ open, setOpen ] = useState(
		!! paymentReturn && options.display !== 'inline'
	);
	const [ returnState, setReturnState ] = useState( paymentReturn );
	const [ session, setSession ] = useState( 0 );
	const buttonRef = useRef( null );

	useEffect( () => {
		if ( registerOpener ) {
			registerOpener( () => {
				prefetchWizard();
				setOpen( true );
			} );
		}
	}, [ registerOpener ] );

	useEffect( () => {
		if ( options.display === 'inline' ) {
			prefetchWizard();
			return undefined;
		}
		const idle =
			window.requestIdleCallback ||
			( ( fn ) => window.setTimeout( fn, 2500 ) );
		const cancel = window.cancelIdleCallback || window.clearTimeout;
		const handle = idle( prefetchWizard, { timeout: 4000 } );
		return () => cancel( handle );
	}, [ options.display ] );

	const clearReturn = useCallback( () => setReturnState( null ), [] );
	const variant = [
		`pbk-radius--${ appearance.radius || 'rounded' }`,
		appearance.font === 'inherit' ? 'pbk-font--inherit' : '',
	]
		.filter( Boolean )
		.join( ' ' );
	const classes = `pbk-booking__root ${ variant }`;

	return (
		<ThemeRoot
			brand={ config.primary }
			mode={ appearance.dark || 'light' }
			className={ classes }
			portalClassName={ variant }
		>
			<ConfirmProvider>
				{ options.display === 'inline' ? (
					<div
						className={ `pbk-inline ${
							options.compact ? 'is-compact' : ''
						}` }
					>
						<Suspense fallback={ <Fallback /> }>
							<Wizard
								key={ session }
								mode="inline"
								config={ config }
								options={ options }
								paymentReturn={ returnState }
								onRestart={ () => {
									clearReturn();
									setSession( ( n ) => n + 1 );
								} }
							/>
						</Suspense>
					</div>
				) : (
					<>
						{ ! hideButton && (
							<Button
								ref={ buttonRef }
								variant="primary"
								size={ options.compact ? 'md' : 'lg' }
								icon="calendar"
								className="pbk-launch"
								data-bp-open="wizard"
								aria-haspopup="dialog"
								onMouseEnter={ prefetchWizard }
								onFocus={ prefetchWizard }
								onClick={ () => setOpen( true ) }
							>
								{ options.label ||
									__( 'Book now', 'pointly-booking' ) }
							</Button>
						) }
						<ModalWizard
							config={ config }
							options={ options }
							open={ open }
							setOpen={ setOpen }
							paymentReturn={ returnState }
							clearReturn={ clearReturn }
						/>
					</>
				) }
			</ConfirmProvider>
		</ThemeRoot>
	);
}
