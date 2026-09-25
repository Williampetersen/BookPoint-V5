/**
 * Customer create/edit drawer with booking history, GDPR erase and delete.
 */
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Field, Input, Drawer, StatusBadge, Skeleton, Notice, Icon, useConfirm, useToast } from '../../ui';
import { useResource, client, fieldError } from '../api';
import { useAdminFormat } from '../context';
import { useRoute } from '../router';
import { CustomFieldInput } from './CustomFieldInput';

function emptyForm() {
	return { first_name: '', last_name: '', email: '', phone: '', custom_fields: {} };
}

export default function CustomerDrawer( { id, open, onClose, onSaved, onDeleted } ) {
	const isNew = ! id;
	const fmt = useAdminFormat();
	const confirm = useConfirm();
	const toast = useToast();
	const { navigate } = useRoute();
	const { data: detail, loading, reload } = useResource( ! isNew && open ? `admin/customers/${ id }` : null );
	const [ form, setForm ] = useState( emptyForm() );
	const [ saving, setSaving ] = useState( false );
	const [ deleting, setDeleting ] = useState( false );
	const [ anonymizing, setAnonymizing ] = useState( false );
	const [ error, setError ] = useState( null );
	const update = ( patch ) => setForm( ( prev ) => ( { ...prev, ...patch } ) );

	useEffect( () => {
		if ( ! open ) {
			return;
		}
		if ( isNew ) {
			setForm( emptyForm() );
			setError( null );
		} else if ( detail ) {
			setForm( {
				first_name: detail.first_name || '',
				last_name: detail.last_name || '',
				email: detail.email || '',
				phone: detail.phone || '',
				custom_fields: detail.custom_fields || {},
			} );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ open, isNew, detail ] );

	const extraFields = ( detail && detail.fields ) ? detail.fields.filter( ( f ) => ! f.is_core ) : [];

	const submit = async () => {
		setSaving( true );
		setError( null );
		try {
			const payload = { first_name: form.first_name, last_name: form.last_name, email: form.email, phone: form.phone, custom_fields: form.custom_fields };
			if ( isNew ) {
				const created = await client().post( 'admin/customers', payload );
				toast.success( __( 'Customer created.', 'pointly-booking' ) );
				onSaved( created );
			} else {
				await client().patch( `admin/customers/${ id }`, payload );
				toast.success( __( 'Customer updated.', 'pointly-booking' ) );
				onSaved();
			}
			onClose();
		} catch ( e ) {
			setError( e );
		} finally {
			setSaving( false );
		}
	};

	const remove = async () => {
		const ok = await confirm( {
			title: __( 'Delete this customer?', 'pointly-booking' ),
			message: __( 'Their booking history is kept, but the customer record is removed.', 'pointly-booking' ),
			confirmLabel: __( 'Delete', 'pointly-booking' ),
			tone: 'danger',
		} );
		if ( ! ok ) {
			return;
		}
		setDeleting( true );
		try {
			await client().del( `admin/customers/${ id }` );
			toast.success( __( 'Customer deleted.', 'pointly-booking' ) );
			onDeleted();
		} catch ( e ) {
			toast.error( e.message );
		} finally {
			setDeleting( false );
		}
	};

	const anonymize = async () => {
		const ok = await confirm( {
			title: __( 'Erase personal data?', 'pointly-booking' ),
			message: __( 'This anonymises the customer’s name, email and phone and removes their custom answers. Their bookings stay, but are no longer linked to identifying details. This cannot be undone.', 'pointly-booking' ),
			confirmLabel: __( 'Erase data', 'pointly-booking' ),
			tone: 'danger',
		} );
		if ( ! ok ) {
			return;
		}
		setAnonymizing( true );
		try {
			await client().post( `admin/customers/${ id }/anonymize` );
			toast.success( __( 'Customer data erased.', 'pointly-booking' ) );
			reload();
			onSaved();
		} catch ( e ) {
			toast.error( e.message );
		} finally {
			setAnonymizing( false );
		}
	};

	const showSkeleton = ! isNew && ( loading || ! detail );

	return (
		<Drawer open={ open } onClose={ onClose } title={ isNew ? __( 'New customer', 'pointly-booking' ) : __( 'Edit customer', 'pointly-booking' ) } size="md">
			{ showSkeleton ? (
				<Skeleton variant="text" lines={ 8 } />
			) : (
				<div className="pbk-drawer-form">
					{ error && <Notice tone="danger">{ error.message }</Notice> }
					<div className="pbk-drawer-form__grid">
						<Field label={ __( 'First name', 'pointly-booking' ) } required error={ fieldError( error, 'first_name' ) }>
							<Input value={ form.first_name } onChange={ ( e ) => update( { first_name: e.target.value } ) } />
						</Field>
						<Field label={ __( 'Last name', 'pointly-booking' ) } optional>
							<Input value={ form.last_name } onChange={ ( e ) => update( { last_name: e.target.value } ) } />
						</Field>
						<Field label={ __( 'Email', 'pointly-booking' ) } required error={ fieldError( error, 'email' ) }>
							<Input type="email" value={ form.email } onChange={ ( e ) => update( { email: e.target.value } ) } />
						</Field>
						<Field label={ __( 'Phone', 'pointly-booking' ) } optional>
							<Input type="tel" value={ form.phone } onChange={ ( e ) => update( { phone: e.target.value } ) } />
						</Field>
					</div>

					{ extraFields.map( ( field ) => (
						<CustomFieldInput
							key={ field.field_key }
							field={ field }
							value={ form.custom_fields[ field.field_key ] }
							onChange={ ( value ) => update( { custom_fields: { ...form.custom_fields, [ field.field_key ]: value } } ) }
						/>
					) ) }

					{ ! isNew && detail && (
						<>
							<section className="pbk-booking-detail__section">
								<h3 className="pbk-booking-detail__heading">{ __( 'Summary', 'pointly-booking' ) }</h3>
								<div className="pbk-detail-list">
									<div className="pbk-detail-row">
										<Icon name="calendar-check" size={ 18 } />
										<div className="pbk-detail-row__text">
											<span>{ sprintf( /* translators: %d: number of bookings */ __( '%d bookings', 'pointly-booking' ), detail.bookings_count || 0 ) }</span>
										</div>
									</div>
									<div className="pbk-detail-row">
										<Icon name="dollar" size={ 18 } />
										<div className="pbk-detail-row__text">
											<span>{ sprintf( /* translators: %s: money amount */ __( '%s total spent', 'pointly-booking' ), fmt.money( detail.total_spent ) ) }</span>
										</div>
									</div>
									{ detail.last_booking && (
										<div className="pbk-detail-row">
											<Icon name="clock" size={ 18 } />
											<div className="pbk-detail-row__text">
												<span>{ sprintf( /* translators: %s: date */ __( 'Last booking %s', 'pointly-booking' ), fmt.date( detail.last_booking, 'medium' ) ) }</span>
											</div>
										</div>
									) }
								</div>
							</section>

							{ detail.bookings && detail.bookings.length > 0 && (
								<section className="pbk-booking-detail__section">
									<h3 className="pbk-booking-detail__heading">{ __( 'Booking history', 'pointly-booking' ) }</h3>
									<div className="pbk-mini-list">
										{ detail.bookings.map( ( b ) => (
											<button key={ b.id } type="button" className="pbk-mini-row" onClick={ () => navigate( 'bookings', { view: 'edit', id: b.id } ) }>
												<span className="pbk-mini-row__text">
													<span className="pbk-mini-row__title">{ b.service_name }</span>
													<span className="pbk-mini-row__meta">
														{ fmt.date( b.start, 'short' ) } { fmt.time( b.start.slice( 11, 16 ) ) } · { b.agent_name || '—' }
													</span>
												</span>
												<StatusBadge status={ b.status } size="sm" />
											</button>
										) ) }
									</div>
								</section>
							) }

							<section className="pbk-booking-detail__section">
								<h3 className="pbk-booking-detail__heading">{ __( 'Privacy', 'pointly-booking' ) }</h3>
								<Button variant="secondary" icon="shield" loading={ anonymizing } onClick={ anonymize }>
									{ __( 'Erase personal data (GDPR)', 'pointly-booking' ) }
								</Button>
							</section>
						</>
					) }

					<div className="pbk-drawer-footer">
						{ ! isNew && (
							<Button variant="ghost" className="pbk-delete-btn" icon="trash" loading={ deleting } onClick={ remove }>
								{ __( 'Delete', 'pointly-booking' ) }
							</Button>
						) }
						<Button variant="ghost" onClick={ onClose }>
							{ __( 'Cancel', 'pointly-booking' ) }
						</Button>
						<Button variant="primary" loading={ saving } disabled={ ! form.first_name || ! form.email } onClick={ submit }>
							{ isNew ? __( 'Create customer', 'pointly-booking' ) : __( 'Save changes', 'pointly-booking' ) }
						</Button>
					</div>
				</div>
			) }
		</Drawer>
	);
}
