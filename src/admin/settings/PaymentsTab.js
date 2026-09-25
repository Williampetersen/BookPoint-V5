/**
 * Settings → Payments: methods, WooCommerce, Stripe, PayPal.
 */
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Field, Input, Select, Toggle, Checkbox, Skeleton, Notice, Badge, ErrorState, useToast } from '../../ui';
import { useResource, client } from '../api';

const METHODS = [
	{ value: 'free', label: __( 'Free', 'pointly-booking' ) },
	{ value: 'cash', label: __( 'Cash / pay at location', 'pointly-booking' ) },
	{ value: 'woocommerce', label: __( 'WooCommerce', 'pointly-booking' ) },
	{ value: 'stripe', label: __( 'Stripe', 'pointly-booking' ) },
	{ value: 'paypal', label: __( 'PayPal', 'pointly-booking' ) },
];

export default function PaymentsTab() {
	const toast = useToast();
	const { data, error, reload } = useResource( 'admin/settings/payments' );
	const [ form, setForm ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const update = ( patch ) => setForm( ( prev ) => ( { ...prev, ...patch } ) );
	const updateIn = ( group, patch ) => setForm( ( prev ) => ( { ...prev, [ group ]: { ...prev[ group ], ...patch } } ) );

	useEffect( () => {
		if ( data && ! form ) {
			setForm( data );
		}
	}, [ data, form ] );

	const toggleMethod = ( value, checked ) => {
		const methods = checked ? [ ...form.enabled_methods, value ] : form.enabled_methods.filter( ( m ) => m !== value );
		update( { enabled_methods: methods } );
	};

	const save = async () => {
		setSaving( true );
		try {
			const result = await client().post( 'admin/settings/payments', form );
			setForm( result );
			toast.success( __( 'Payment settings saved.', 'pointly-booking' ) );
		} catch ( e ) {
			toast.error( e.message );
		} finally {
			setSaving( false );
		}
	};

	if ( error && ! data ) {
		return <ErrorState message={ error } onRetry={ reload } />;
	}
	if ( ! form ) {
		return <Skeleton variant="block" height={ 320 } />;
	}

	return (
		<div className="pbk-design__panel">
			<Toggle label={ __( 'Accept payments', 'pointly-booking' ) } checked={ !! form.payments_enabled } onChange={ ( v ) => update( { payments_enabled: v } ) } />

			{ !! form.payments_enabled && (
				<>
					<Field label={ __( 'Enabled methods', 'pointly-booking' ) }>
						<div className="pbk-checkbox-grid">
							{ METHODS.map( ( m ) => (
								<Checkbox key={ m.value } label={ m.label } checked={ form.enabled_methods.includes( m.value ) } onChange={ ( e ) => toggleMethod( m.value, e.target.checked ) } />
							) ) }
						</div>
					</Field>
					<div className="pbk-drawer-form__grid">
						<Field label={ __( 'Default method', 'pointly-booking' ) }>
							<Select value={ form.default_method } onChange={ ( e ) => update( { default_method: e.target.value } ) } options={ METHODS.filter( ( m ) => form.enabled_methods.includes( m.value ) ) } />
						</Field>
					</div>
					<Toggle label={ __( 'Require payment before a booking is confirmed', 'pointly-booking' ) } checked={ !! form.require_payment_to_confirm } onChange={ ( v ) => update( { require_payment_to_confirm: v } ) } />

					{ form.enabled_methods.includes( 'woocommerce' ) && (
						<>
							<h3 className="pbk-booking-detail__heading">
								{ __( 'WooCommerce', 'pointly-booking' ) }
								{ ! form.woocommerce.available && <Badge tone="warning" size="sm">{ __( 'Not installed', 'pointly-booking' ) }</Badge> }
							</h3>
							<Field label={ __( 'Product ID', 'pointly-booking' ) } help={ __( 'The WooCommerce product used to represent a booking in the cart.', 'pointly-booking' ) }>
								<Input type="number" value={ form.woocommerce.product_id || '' } onChange={ ( e ) => updateIn( 'woocommerce', { product_id: Number( e.target.value ) } ) } />
							</Field>
						</>
					) }

					{ form.enabled_methods.includes( 'stripe' ) && (
						<>
							<h3 className="pbk-booking-detail__heading">{ __( 'Stripe', 'pointly-booking' ) }</h3>
							<div className="pbk-drawer-form__grid">
								<Field label={ __( 'Mode', 'pointly-booking' ) }>
									<Select value={ form.stripe.mode } onChange={ ( e ) => updateIn( 'stripe', { mode: e.target.value } ) } options={ [ { value: 'test', label: __( 'Test', 'pointly-booking' ) }, { value: 'live', label: __( 'Live', 'pointly-booking' ) } ] } />
								</Field>
								<Field label={ __( 'Checkout flow', 'pointly-booking' ) }>
									<Select value={ form.stripe.flow } onChange={ ( e ) => updateIn( 'stripe', { flow: e.target.value } ) } options={ [ { value: 'checkout', label: __( 'Checkout Session (redirect)', 'pointly-booking' ) }, { value: 'elements', label: __( 'Elements (inline)', 'pointly-booking' ) } ] } />
								</Field>
								<Field label={ __( 'Test secret key', 'pointly-booking' ) } optional>
									<Input type="password" value={ form.stripe.test_secret_key || '' } onChange={ ( e ) => updateIn( 'stripe', { test_secret_key: e.target.value } ) } />
								</Field>
								<Field label={ __( 'Test publishable key', 'pointly-booking' ) } optional>
									<Input value={ form.stripe.test_publishable_key || '' } onChange={ ( e ) => updateIn( 'stripe', { test_publishable_key: e.target.value } ) } />
								</Field>
								<Field label={ __( 'Live secret key', 'pointly-booking' ) } optional>
									<Input type="password" value={ form.stripe.live_secret_key || '' } onChange={ ( e ) => updateIn( 'stripe', { live_secret_key: e.target.value } ) } />
								</Field>
								<Field label={ __( 'Live publishable key', 'pointly-booking' ) } optional>
									<Input value={ form.stripe.live_publishable_key || '' } onChange={ ( e ) => updateIn( 'stripe', { live_publishable_key: e.target.value } ) } />
								</Field>
								<Field label={ __( 'Webhook secret', 'pointly-booking' ) } optional>
									<Input type="password" value={ form.stripe.webhook_secret || '' } onChange={ ( e ) => updateIn( 'stripe', { webhook_secret: e.target.value } ) } />
								</Field>
								<Field label={ __( 'Success URL', 'pointly-booking' ) } optional>
									<Input value={ form.stripe.success_url || '' } onChange={ ( e ) => updateIn( 'stripe', { success_url: e.target.value } ) } />
								</Field>
								<Field label={ __( 'Cancel URL', 'pointly-booking' ) } optional>
									<Input value={ form.stripe.cancel_url || '' } onChange={ ( e ) => updateIn( 'stripe', { cancel_url: e.target.value } ) } />
								</Field>
							</div>
							<Notice tone="info">{ __( 'Webhook URL:', 'pointly-booking' ) } { form.stripe.webhook_url }</Notice>
						</>
					) }

					{ form.enabled_methods.includes( 'paypal' ) && (
						<>
							<h3 className="pbk-booking-detail__heading">{ __( 'PayPal', 'pointly-booking' ) }</h3>
							<div className="pbk-drawer-form__grid">
								<Field label={ __( 'Mode', 'pointly-booking' ) }>
									<Select value={ form.paypal.mode } onChange={ ( e ) => updateIn( 'paypal', { mode: e.target.value } ) } options={ [ { value: 'sandbox', label: __( 'Sandbox', 'pointly-booking' ) }, { value: 'live', label: __( 'Live', 'pointly-booking' ) } ] } />
								</Field>
								<Field label={ __( 'Client ID', 'pointly-booking' ) } optional>
									<Input value={ form.paypal.client_id || '' } onChange={ ( e ) => updateIn( 'paypal', { client_id: e.target.value } ) } />
								</Field>
								<Field label={ __( 'Secret', 'pointly-booking' ) } optional>
									<Input type="password" value={ form.paypal.secret || '' } onChange={ ( e ) => updateIn( 'paypal', { secret: e.target.value } ) } />
								</Field>
								<Field label={ __( 'Return URL', 'pointly-booking' ) } optional>
									<Input value={ form.paypal.return_url || '' } onChange={ ( e ) => updateIn( 'paypal', { return_url: e.target.value } ) } />
								</Field>
								<Field label={ __( 'Cancel URL', 'pointly-booking' ) } optional>
									<Input value={ form.paypal.cancel_url || '' } onChange={ ( e ) => updateIn( 'paypal', { cancel_url: e.target.value } ) } />
								</Field>
							</div>
						</>
					) }
				</>
			) }

			<div className="pbk-drawer-footer">
				<Button variant="primary" loading={ saving } onClick={ save }>
					{ __( 'Save changes', 'pointly-booking' ) }
				</Button>
			</div>
		</div>
	);
}
