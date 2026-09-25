/**
 * Settings → General: business info, currency, booking window, defaults.
 */
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Field, Input, Select, Toggle, Skeleton, Notice, ErrorState, useToast } from '../../ui';
import { useResource, client } from '../api';

const CURRENCIES = [ 'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'NZD', 'CHF', 'JPY', 'CNY', 'INR', 'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'HUF', 'RON', 'BGN', 'HRK', 'ISK', 'BRL', 'MXN', 'ZAR', 'SGD', 'HKD', 'AED', 'SAR', 'ILS', 'TRY', 'RUB', 'KRW', 'THB', 'MYR', 'IDR', 'PHP', 'VND' ];

export default function GeneralTab() {
	const toast = useToast();
	const { data, error, reload } = useResource( 'admin/settings' );
	const [ form, setForm ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const update = ( patch ) => setForm( ( prev ) => ( { ...prev, ...patch } ) );

	useEffect( () => {
		if ( data && ! form ) {
			setForm( data );
		}
	}, [ data, form ] );

	const save = async () => {
		setSaving( true );
		try {
			const result = await client().post( 'admin/settings', form );
			setForm( result );
			toast.success( __( 'Settings saved.', 'pointly-booking' ) );
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
			<h3 className="pbk-booking-detail__heading">{ __( 'Business', 'pointly-booking' ) }</h3>
			<div className="pbk-drawer-form__grid">
				<Field label={ __( 'Business name', 'pointly-booking' ) }>
					<Input value={ form.business_name || '' } onChange={ ( e ) => update( { business_name: e.target.value } ) } />
				</Field>
				<Field label={ __( 'Business email', 'pointly-booking' ) }>
					<Input type="email" value={ form.business_email || '' } onChange={ ( e ) => update( { business_email: e.target.value } ) } />
				</Field>
				<Field label={ __( 'Business phone', 'pointly-booking' ) } optional>
					<Input type="tel" value={ form.business_phone || '' } onChange={ ( e ) => update( { business_phone: e.target.value } ) } />
				</Field>
				<Field label={ __( 'Business address', 'pointly-booking' ) } optional>
					<Input value={ form.business_address || '' } onChange={ ( e ) => update( { business_address: e.target.value } ) } />
				</Field>
			</div>

			<h3 className="pbk-booking-detail__heading">{ __( 'Currency', 'pointly-booking' ) }</h3>
			<div className="pbk-drawer-form__grid">
				<Field label={ __( 'Currency', 'pointly-booking' ) }>
					<Select value={ form.currency } onChange={ ( e ) => update( { currency: e.target.value } ) } options={ CURRENCIES.map( ( c ) => ( { value: c, label: c } ) ) } />
				</Field>
				<Field label={ __( 'Symbol position', 'pointly-booking' ) }>
					<Select
						value={ form.currency_position }
						onChange={ ( e ) => update( { currency_position: e.target.value } ) }
						options={ [ { value: 'before', label: __( 'Before the amount ($10)', 'pointly-booking' ) }, { value: 'after', label: __( 'After the amount (10€)', 'pointly-booking' ) } ] }
					/>
				</Field>
			</div>

			<h3 className="pbk-booking-detail__heading">{ __( 'Booking rules', 'pointly-booking' ) }</h3>
			<div className="pbk-drawer-form__grid">
				<Field label={ __( 'How far ahead customers can book (days)', 'pointly-booking' ) }>
					<Input type="number" min={ 1 } max={ 365 } value={ form.pointlybooking_future_days_limit } onChange={ ( e ) => update( { pointlybooking_future_days_limit: Number( e.target.value ) } ) } />
				</Field>
				<Field label={ __( 'Default booking status', 'pointly-booking' ) }>
					<Select
						value={ form.pointlybooking_default_booking_status }
						onChange={ ( e ) => update( { pointlybooking_default_booking_status: e.target.value } ) }
						options={ [
							{ value: 'pending', label: __( 'Pending', 'pointly-booking' ) },
							{ value: 'confirmed', label: __( 'Confirmed', 'pointly-booking' ) },
							{ value: 'cancelled', label: __( 'Cancelled', 'pointly-booking' ) },
							{ value: 'completed', label: __( 'Completed', 'pointly-booking' ) },
						] }
					/>
				</Field>
				<Field label={ __( 'Unpaid booking timeout (minutes)', 'pointly-booking' ) } optional help={ __( 'How long a pending-payment booking holds its slot.', 'pointly-booking' ) }>
					<Input type="number" min={ 0 } value={ form.pending_payment_timeout_minutes } onChange={ ( e ) => update( { pending_payment_timeout_minutes: Number( e.target.value ) } ) } />
				</Field>
			</div>

			<h3 className="pbk-booking-detail__heading">{ __( 'Email', 'pointly-booking' ) }</h3>
			<Toggle label={ __( 'Send booking emails', 'pointly-booking' ) } checked={ !! form.pointlybooking_email_enabled } onChange={ ( v ) => update( { pointlybooking_email_enabled: v } ) } />
			<div className="pbk-drawer-form__grid">
				<Field label={ __( 'Admin notification email', 'pointly-booking' ) } optional>
					<Input type="email" value={ form.pointlybooking_admin_email || '' } onChange={ ( e ) => update( { pointlybooking_admin_email: e.target.value } ) } />
				</Field>
				<Field label={ __( 'From name', 'pointly-booking' ) } optional>
					<Input value={ form.pointlybooking_email_from_name || '' } onChange={ ( e ) => update( { pointlybooking_email_from_name: e.target.value } ) } />
				</Field>
				<Field label={ __( 'From email', 'pointly-booking' ) } optional>
					<Input type="email" value={ form.pointlybooking_email_from_email || '' } onChange={ ( e ) => update( { pointlybooking_email_from_email: e.target.value } ) } />
				</Field>
			</div>

			<h3 className="pbk-booking-detail__heading">{ __( 'Webhooks', 'pointly-booking' ) }</h3>
			<Toggle label={ __( 'Enable webhooks', 'pointly-booking' ) } checked={ !! form.webhooks_enabled } onChange={ ( v ) => update( { webhooks_enabled: v } ) } />
			{ !! form.webhooks_enabled && (
				<div className="pbk-drawer-form__grid">
					<Field label={ __( 'Secret', 'pointly-booking' ) } optional help={ __( 'Used to sign the X-BP-Signature header.', 'pointly-booking' ) }>
						<Input value={ form.webhooks_secret || '' } onChange={ ( e ) => update( { webhooks_secret: e.target.value } ) } />
					</Field>
					<Field label={ __( 'Booking created URL', 'pointly-booking' ) } optional>
						<Input value={ form.webhooks_url_booking_created || '' } onChange={ ( e ) => update( { webhooks_url_booking_created: e.target.value } ) } />
					</Field>
					<Field label={ __( 'Status changed URL', 'pointly-booking' ) } optional>
						<Input value={ form.webhooks_url_booking_status_changed || '' } onChange={ ( e ) => update( { webhooks_url_booking_status_changed: e.target.value } ) } />
					</Field>
					<Field label={ __( 'Booking updated URL', 'pointly-booking' ) } optional>
						<Input value={ form.webhooks_url_booking_updated || '' } onChange={ ( e ) => update( { webhooks_url_booking_updated: e.target.value } ) } />
					</Field>
					<Field label={ __( 'Booking cancelled URL', 'pointly-booking' ) } optional>
						<Input value={ form.webhooks_url_booking_cancelled || '' } onChange={ ( e ) => update( { webhooks_url_booking_cancelled: e.target.value } ) } />
					</Field>
				</div>
			) }

			<h3 className="pbk-booking-detail__heading">{ __( 'Uninstall', 'pointly-booking' ) }</h3>
			<Toggle
				label={ __( 'Delete all plugin data when uninstalled', 'pointly-booking' ) }
				description={ __( 'Removes every table and setting. There is no undo.', 'pointly-booking' ) }
				checked={ !! form.pointlybooking_remove_data_on_uninstall }
				onChange={ ( v ) => update( { pointlybooking_remove_data_on_uninstall: v } ) }
			/>
			{ !! form.pointlybooking_remove_data_on_uninstall && <Notice tone="warning">{ __( 'This cannot be undone once the plugin is deleted.', 'pointly-booking' ) }</Notice> }

			<div className="pbk-drawer-footer">
				<Button variant="primary" loading={ saving } onClick={ save }>
					{ __( 'Save changes', 'pointly-booking' ) }
				</Button>
			</div>
		</div>
	);
}
