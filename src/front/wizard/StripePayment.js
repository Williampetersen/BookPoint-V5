/**
 * Card payment with Stripe's Payment Element (lazy chunk; Stripe.js is only loaded here).
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { loadStripe } from '@stripe/stripe-js/pure';
import { Button, Notice, Skeleton, Icon } from '../../ui';
import { confirmStripe } from './api';

export default function StripePayment( {
	booking,
	payment,
	brand,
	dark,
	amountLabel,
	onPaid,
	returnUrl,
} ) {
	const mountRef = useRef( null );
	const stripeRef = useRef( null );
	const elementsRef = useRef( null );
	const [ ready, setReady ] = useState( false );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		let alive = true;
		let element = null;
		loadStripe( payment.publishable_key )
			.then( ( stripe ) => {
				if ( ! alive || ! stripe ) {
					return;
				}
				stripeRef.current = stripe;
				const elements = stripe.elements( {
					clientSecret: payment.client_secret,
					appearance: {
						theme: dark ? 'night' : 'stripe',
						variables: {
							colorPrimary: brand,
							borderRadius: '10px',
							fontFamily:
								'-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
						},
					},
				} );
				elementsRef.current = elements;
				element = elements.create( 'payment', { layout: 'tabs' } );
				element.on( 'ready', () => alive && setReady( true ) );
				element.mount( mountRef.current );
			} )
			.catch(
				() =>
					alive &&
					setError(
						__(
							'The card form could not be loaded. Please check your connection and try again.',
							'pointly-booking'
						)
					)
			);
		return () => {
			alive = false;
			if ( element ) {
				element.destroy();
			}
		};
	}, [ payment.client_secret, payment.publishable_key, brand, dark ] );

	const pay = async () => {
		if ( ! stripeRef.current || ! elementsRef.current ) {
			return;
		}
		setBusy( true );
		setError( '' );
		const result = await stripeRef.current.confirmPayment( {
			elements: elementsRef.current,
			redirect: 'if_required',
			confirmParams: { return_url: returnUrl },
		} );
		if ( result.error ) {
			setBusy( false );
			setError(
				result.error.message ||
					__( 'Your payment was not completed.', 'pointly-booking' )
			);
			return;
		}
		try {
			const confirmed = await confirmStripe(
				booking.id,
				booking.key,
				result.paymentIntent.id
			);
			onPaid( confirmed.booking );
		} catch ( e ) {
			setBusy( false );
			setError( e.message );
		}
	};

	return (
		<div className="pbk-pay">
			<p className="pbk-pay__secure">
				<Icon name="lock" size={ 16 } />
				{ __(
					'Payments are processed securely by Stripe. We never see your card number.',
					'pointly-booking'
				) }
			</p>
			{ ! ready && ! error && (
				<Skeleton variant="block" height={ 180 } />
			) }
			<div ref={ mountRef } className="pbk-pay__element" />
			{ error && <Notice tone="danger">{ error }</Notice> }
			<Button
				variant="primary"
				size="lg"
				block
				loading={ busy }
				disabled={ ! ready }
				onClick={ pay }
				icon="lock"
			>
				{ sprintf(
					/* translators: %s: amount */
					__( 'Pay %s', 'pointly-booking' ),
					amountLabel
				) }
			</Button>
		</div>
	);
}
