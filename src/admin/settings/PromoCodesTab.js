/**
 * Settings → Promo codes: list, drawer, duplicate, and a client-side test calculator.
 */
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Icon, Field, Input, Select, Toggle, Drawer, Dropdown, Badge, Skeleton, ErrorState, EmptyState, Notice, useConfirm, useToast } from '../../ui';
import { useResource, client, fieldError } from '../api';
import { useAdminFormat } from '../context';

const STATE_TONES = { active: 'success', scheduled: 'info', expired: 'neutral', disabled: 'neutral' };
const STATE_LABELS = {
	active: __( 'Active', 'pointly-booking' ),
	scheduled: __( 'Scheduled', 'pointly-booking' ),
	expired: __( 'Expired', 'pointly-booking' ),
	disabled: __( 'Disabled', 'pointly-booking' ),
};

function generateCode() {
	const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
	let out = '';
	for ( let i = 0; i < 8; i++ ) {
		out += chars[ Math.floor( Math.random() * chars.length ) ];
	}
	return out;
}

function emptyForm() {
	return { code: '', type: 'percent', amount: 10, starts_at: '', ends_at: '', max_uses: '', min_total: '', is_active: true };
}

function TestCalculator( { type, amount } ) {
	const fmt = useAdminFormat();
	const [ total, setTotal ] = useState( 100 );
	const discount = type === 'percent' ? ( total * Number( amount || 0 ) ) / 100 : Math.min( total, Number( amount || 0 ) );
	const result = Math.max( 0, total - discount );
	return (
		<Field label={ __( 'Test this code', 'pointly-booking' ) } help={ __( 'Not saved — just a preview.', 'pointly-booking' ) }>
			<div className="pbk-row">
				<Input type="number" min={ 0 } value={ total } onChange={ ( e ) => setTotal( Number( e.target.value ) ) } />
				<span className="pbk-subtle">
					{ sprintf( /* translators: 1: discount amount, 2: resulting total */ __( '−%1$s → %2$s', 'pointly-booking' ), fmt.money( discount ), fmt.money( result ) ) }
				</span>
			</div>
		</Field>
	);
}

function PromoDrawer( { id, open, onClose, onSaved, onDeleted } ) {
	const isNew = ! id;
	const confirm = useConfirm();
	const toast = useToast();
	const { data: detail, loading } = useResource( ! isNew && open ? `admin/promo-codes/${ id }` : null );
	const [ form, setForm ] = useState( emptyForm() );
	const [ saving, setSaving ] = useState( false );
	const [ deleting, setDeleting ] = useState( false );
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
				code: detail.code,
				type: detail.type,
				amount: detail.amount,
				starts_at: detail.starts_at ? detail.starts_at.slice( 0, 10 ) : '',
				ends_at: detail.ends_at ? detail.ends_at.slice( 0, 10 ) : '',
				max_uses: detail.max_uses ?? '',
				min_total: detail.min_total ?? '',
				is_active: !! detail.is_active,
			} );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ open, isNew, detail ] );

	const submit = async () => {
		setSaving( true );
		setError( null );
		try {
			const payload = { ...form, max_uses: form.max_uses === '' ? null : Number( form.max_uses ), min_total: form.min_total === '' ? null : Number( form.min_total ) };
			if ( isNew ) {
				await client().post( 'admin/promo-codes', payload );
				toast.success( __( 'Promo code created.', 'pointly-booking' ) );
			} else {
				await client().patch( `admin/promo-codes/${ id }`, payload );
				toast.success( __( 'Promo code updated.', 'pointly-booking' ) );
			}
			onSaved();
			onClose();
		} catch ( e ) {
			setError( e );
		} finally {
			setSaving( false );
		}
	};

	const remove = async () => {
		const ok = await confirm( { title: __( 'Delete this promo code?', 'pointly-booking' ), confirmLabel: __( 'Delete', 'pointly-booking' ), tone: 'danger' } );
		if ( ! ok ) {
			return;
		}
		setDeleting( true );
		try {
			await client().del( `admin/promo-codes/${ id }` );
			toast.success( __( 'Promo code deleted.', 'pointly-booking' ) );
			onDeleted();
		} catch ( e ) {
			toast.error( e.message );
		} finally {
			setDeleting( false );
		}
	};

	const showSkeleton = ! isNew && ( loading || ! detail );

	return (
		<Drawer open={ open } onClose={ onClose } title={ isNew ? __( 'New promo code', 'pointly-booking' ) : __( 'Edit promo code', 'pointly-booking' ) } size="sm">
			{ showSkeleton ? (
				<Skeleton variant="text" lines={ 6 } />
			) : (
				<div className="pbk-drawer-form">
					{ error && <Notice tone="danger">{ error.message }</Notice> }
					<Field label={ __( 'Code', 'pointly-booking' ) } required error={ fieldError( error, 'code' ) }>
						<div className="pbk-row">
							<Input value={ form.code } onChange={ ( e ) => update( { code: e.target.value.toUpperCase() } ) } />
							<Button size="sm" variant="secondary" onClick={ () => update( { code: generateCode() } ) }>
								{ __( 'Generate', 'pointly-booking' ) }
							</Button>
						</div>
					</Field>
					<div className="pbk-drawer-form__grid">
						<Field label={ __( 'Type', 'pointly-booking' ) }>
							<Select
								value={ form.type }
								onChange={ ( e ) => update( { type: e.target.value } ) }
								options={ [ { value: 'percent', label: __( 'Percent off', 'pointly-booking' ) }, { value: 'fixed', label: __( 'Fixed amount off', 'pointly-booking' ) } ] }
							/>
						</Field>
						<Field label={ __( 'Amount', 'pointly-booking' ) } required error={ fieldError( error, 'amount' ) }>
							<Input type="number" min={ 0 } max={ form.type === 'percent' ? 100 : undefined } value={ form.amount } onChange={ ( e ) => update( { amount: Number( e.target.value ) } ) } />
						</Field>
						<Field label={ __( 'Starts', 'pointly-booking' ) } optional>
							<Input type="date" value={ form.starts_at } onChange={ ( e ) => update( { starts_at: e.target.value } ) } />
						</Field>
						<Field label={ __( 'Ends', 'pointly-booking' ) } optional error={ fieldError( error, 'ends_at' ) }>
							<Input type="date" value={ form.ends_at } onChange={ ( e ) => update( { ends_at: e.target.value } ) } />
						</Field>
						<Field label={ __( 'Max uses', 'pointly-booking' ) } optional help={ __( 'Leave empty for unlimited.', 'pointly-booking' ) }>
							<Input type="number" min={ 0 } value={ form.max_uses } onChange={ ( e ) => update( { max_uses: e.target.value } ) } />
						</Field>
						<Field label={ __( 'Minimum order total', 'pointly-booking' ) } optional>
							<Input type="number" min={ 0 } value={ form.min_total } onChange={ ( e ) => update( { min_total: e.target.value } ) } />
						</Field>
					</div>
					<Toggle label={ __( 'Active', 'pointly-booking' ) } checked={ form.is_active } onChange={ ( v ) => update( { is_active: v } ) } />

					<TestCalculator type={ form.type } amount={ form.amount } />

					<div className="pbk-drawer-footer">
						{ ! isNew && (
							<Button variant="ghost" className="pbk-delete-btn" icon="trash" loading={ deleting } onClick={ remove }>
								{ __( 'Delete', 'pointly-booking' ) }
							</Button>
						) }
						<Button variant="ghost" onClick={ onClose }>
							{ __( 'Cancel', 'pointly-booking' ) }
						</Button>
						<Button variant="primary" loading={ saving } disabled={ ! form.code || ! form.amount } onClick={ submit }>
							{ isNew ? __( 'Create code', 'pointly-booking' ) : __( 'Save changes', 'pointly-booking' ) }
						</Button>
					</div>
				</div>
			) }
		</Drawer>
	);
}

export default function PromoCodesTab() {
	const fmt = useAdminFormat();
	const toast = useToast();
	const { data: codes, loading, error, reload } = useResource( 'admin/promo-codes' );
	const [ drawerId, setDrawerId ] = useState( null );

	const duplicate = async ( id ) => {
		try {
			await client().post( `admin/promo-codes/${ id }/duplicate` );
			toast.success( __( 'Promo code duplicated.', 'pointly-booking' ) );
			reload();
		} catch ( e ) {
			toast.error( e.message );
		}
	};

	if ( error && ! codes ) {
		return <ErrorState message={ error } onRetry={ reload } />;
	}

	return (
		<div>
			<div className="pbk-reorder-toolbar">
				<div className="pbk-reorder-toolbar__spacer" />
				<Button variant="primary" icon="plus" onClick={ () => setDrawerId( 'new' ) }>
					{ __( 'New promo code', 'pointly-booking' ) }
				</Button>
			</div>

			{ loading && ! codes && <Skeleton variant="block" height={ 200 } /> }
			{ codes && codes.length === 0 && <EmptyState icon="percent" title={ __( 'No promo codes yet.', 'pointly-booking' ) } /> }

			{ codes && codes.length > 0 && (
				<div className="pbk-reorder-list">
					{ codes.map( ( c ) => (
						<div key={ c.id } className={ `pbk-reorder-row ${ 'active' !== c.state ? 'is-inactive' : '' }` }>
							<span className="pbk-reorder-row__thumb">
								<Icon name="percent" size={ 18 } />
							</span>
							<span className="pbk-reorder-row__text">
								<span className="pbk-reorder-row__title">
									{ c.code }
									<Badge tone={ STATE_TONES[ c.state ] || 'neutral' } size="sm">{ STATE_LABELS[ c.state ] || c.state }</Badge>
								</span>
								<span className="pbk-reorder-row__meta">
									{ c.type === 'percent' ? `${ c.amount }%` : fmt.money( c.amount ) } { __( 'off', 'pointly-booking' ) } · { sprintf( /* translators: %d: number of uses */ __( '%d uses', 'pointly-booking' ), c.uses_count || 0 ) }{ c.max_uses ? ` / ${ c.max_uses }` : '' }
								</span>
							</span>
							<span className="pbk-reorder-row__actions">
								<Dropdown
									label={ __( 'Row actions', 'pointly-booking' ) }
									trigger={ ( props ) => (
										<button type="button" { ...props } className="pbk-btn pbk-btn--ghost pbk-btn--sm pbk-btn--icon-only">
											<Icon name="more-horizontal" size={ 18 } />
										</button>
									) }
									items={ [
										{ label: __( 'Edit', 'pointly-booking' ), icon: 'edit', onClick: () => setDrawerId( c.id ) },
										{ label: __( 'Duplicate', 'pointly-booking' ), icon: 'copy', onClick: () => duplicate( c.id ) },
									] }
								/>
							</span>
						</div>
					) ) }
				</div>
			) }

			<PromoDrawer
				id={ drawerId === 'new' ? null : drawerId }
				open={ drawerId !== null }
				onClose={ () => setDrawerId( null ) }
				onSaved={ reload }
				onDeleted={ () => {
					setDrawerId( null );
					reload();
				} }
			/>
		</div>
	);
}
