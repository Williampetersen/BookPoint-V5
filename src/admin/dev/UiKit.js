/**
 * Design system gallery (admin, WP_DEBUG only): every component in every state.
 * Open: wp-admin/admin.php?page=pointlybooking_dashboard&pbk_ui_kit=1
 */
import { useState } from '@wordpress/element';
import {
	ThemeRoot,
	Button,
	IconButton,
	Field,
	Input,
	SearchInput,
	Textarea,
	Select,
	Checkbox,
	Toggle,
	RadioCards,
	ChoiceCard,
	Calendar,
	DateInput,
	TimeSlots,
	Modal,
	Drawer,
	ConfirmProvider,
	useConfirm,
	Tabs,
	Stepper,
	Card,
	Badge,
	StatusBadge,
	Avatar,
	Table,
	Pagination,
	Dropdown,
	Tooltip,
	ToastProvider,
	useToast,
	Skeleton,
	EmptyState,
	ErrorState,
	Notice,
	Icon,
	ICONS,
	Spinner,
} from '../../ui';
import './uikit.css';

const pad = ( n ) => String( n ).padStart( 2, '0' );
const today = new Date();
const TODAY = `${ today.getFullYear() }-${ pad( today.getMonth() + 1 ) }-${ pad(
	today.getDate()
) }`;

const ROWS = Array.from( { length: 23 } ).map( ( _, i ) => ( {
	id: i + 1,
	customer: [
		'Ada Lovelace',
		'Grace Hopper',
		'Alan Turing',
		'Katherine Johnson',
		'Linus Torvalds',
	][ i % 5 ],
	service: [ 'Consultation', 'Haircut', 'Massage 60 min' ][ i % 3 ],
	status: [
		'confirmed',
		'pending',
		'completed',
		'cancelled',
		'pending_payment',
		'failed_payment',
	][ i % 6 ],
	price: 30 + ( i % 4 ) * 15,
} ) );

function Section( { title, children } ) {
	return (
		<Card title={ title } className="pbk-kit__section">
			<div className="pbk-kit__demo">{ children }</div>
		</Card>
	);
}

function Overlays() {
	const [ modal, setModal ] = useState( false );
	const [ drawer, setDrawer ] = useState( false );
	const confirm = useConfirm();
	const toast = useToast();
	return (
		<div className="pbk-row">
			<Button onClick={ () => setModal( true ) }>Open modal</Button>
			<Button onClick={ () => setDrawer( true ) }>Open drawer</Button>
			<Button
				variant="danger"
				onClick={ async () => {
					const ok = await confirm( {
						title: 'Delete this booking?',
						message:
							'The customer will not be notified. This cannot be undone.',
						confirmLabel: 'Delete booking',
						tone: 'danger',
					} );
					toast[ ok ? 'success' : 'info' ](
						ok ? 'Booking deleted.' : 'Nothing changed.'
					);
				} }
			>
				Confirm dialog
			</Button>
			<Button
				onClick={ () =>
					toast.success( 'Changes saved.', {
						action: {
							label: 'Undo',
							onClick: () => toast.info( 'Undone.' ),
						},
					} )
				}
			>
				Success toast
			</Button>
			<Button
				onClick={ () =>
					toast.error(
						'Could not save. Check your connection and try again.'
					)
				}
			>
				Error toast
			</Button>
			<Modal
				open={ modal }
				onClose={ () => setModal( false ) }
				title="Reschedule booking"
				description="Pick a new date and time. The customer gets an email."
				footer={
					<>
						<Button onClick={ () => setModal( false ) }>
							Cancel
						</Button>
						<Button
							variant="primary"
							onClick={ () => setModal( false ) }
						>
							Save
						</Button>
					</>
				}
			>
				<Field label="Date" required>
					<Input type="date" defaultValue={ TODAY } />
				</Field>
			</Modal>
			<Drawer
				open={ drawer }
				onClose={ () => setDrawer( false ) }
				title="Booking #1042"
				description="Confirmed · Paid"
				footer={
					<Button
						variant="primary"
						onClick={ () => setDrawer( false ) }
					>
						Done
					</Button>
				}
			>
				<div className="pbk-stack">
					<Skeleton lines={ 4 } />
					<Notice tone="info" title="Heads up">
						Drawers become bottom sheets on phones.
					</Notice>
				</div>
			</Drawer>
		</div>
	);
}

export default function UiKit( { config } ) {
	const [ brand, setBrand ] = useState( '#4f46e5' );
	const [ mode, setMode ] = useState( 'light' );
	const [ month, setMonth ] = useState( TODAY.slice( 0, 7 ) );
	const [ date, setDate ] = useState( '' );
	const [ time, setTime ] = useState( '10:00' );
	const [ radio, setRadio ] = useState( 'any' );
	const [ extras, setExtras ] = useState( [] );
	const [ tab, setTab ] = useState( 'all' );
	const [ toggle, setToggle ] = useState( true );
	const [ search, setSearch ] = useState( '' );
	const [ selected, setSelected ] = useState( [] );
	const [ page, setPage ] = useState( 1 );
	const [ sort, setSort ] = useState( { key: 'customer', order: 'asc' } );
	const [ email, setEmail ] = useState( 'not-an-email' );
	const [ dateInput, setDateInput ] = useState( TODAY );

	const rows = [ ...ROWS ]
		.sort(
			( a, b ) =>
				( a[ sort.key ] > b[ sort.key ] ? 1 : -1 ) *
				( sort.order === 'asc' ? 1 : -1 )
		)
		.slice( ( page - 1 ) * 10, page * 10 );

	return (
		<ThemeRoot brand={ brand } mode={ mode } admin className="pbk-kit">
			<ToastProvider>
				<ConfirmProvider>
					<div className="pbk-kit__bar">
						<h1 className="pbk-kit__title">BookPoint UI kit</h1>
						<label className="pbk-row" htmlFor="pbk-kit-brand">
							Brand{ ' ' }
							<input
								id="pbk-kit-brand"
								type="color"
								value={ brand }
								onChange={ ( e ) => setBrand( e.target.value ) }
								aria-label="Brand colour"
							/>
						</label>
						<Tabs
							variant="pills"
							label="Theme"
							value={ mode }
							onChange={ setMode }
							tabs={ [
								{ key: 'light', label: 'Light', icon: 'sun' },
								{ key: 'dark', label: 'Dark', icon: 'moon' },
								{
									key: 'auto',
									label: 'System',
									icon: 'monitor',
								},
							] }
						/>
					</div>

					<div className="pbk-kit__grid">
						<Section title="Buttons">
							<div className="pbk-row">
								<Button variant="primary">Primary</Button>
								<Button>Secondary</Button>
								<Button variant="ghost">Ghost</Button>
								<Button variant="danger" icon="trash">
									Danger
								</Button>
								<Button variant="link">Link</Button>
							</div>
							<div className="pbk-row">
								<Button variant="primary" size="sm" icon="plus">
									Small
								</Button>
								<Button
									variant="primary"
									size="lg"
									iconRight="arrow-right"
								>
									Large
								</Button>
								<Button variant="primary" loading>
									Saving
								</Button>
								<Button disabled>Disabled</Button>
								<IconButton icon="edit" label="Edit booking" />
								<IconButton
									icon="more-horizontal"
									label="More actions"
									variant="secondary"
								/>
								<Spinner label="Loading" />
							</div>
						</Section>

						<Section title="Form controls">
							<div className="pbk-stack">
								<Field
									label="Full name"
									required
									help="As it should appear on the booking."
								>
									<Input
										placeholder="Jane Doe"
										autoComplete="name"
									/>
								</Field>
								<Field
									label="Email"
									required
									error={
										/@/.test( email )
											? ''
											: 'Please enter a valid email address.'
									}
								>
									<Input
										type="email"
										value={ email }
										onChange={ ( e ) =>
											setEmail( e.target.value )
										}
									/>
								</Field>
								<Field label="Price">
									<Input
										prefix="$"
										type="number"
										defaultValue="45"
									/>
								</Field>
								<SearchInput
									label="Search bookings"
									value={ search }
									onChange={ ( e ) =>
										setSearch( e.target.value )
									}
									onClear={ () => setSearch( '' ) }
								/>
								<Field label="Staff member" optional>
									<Select
										placeholder="Any available"
										options={ [
											{ value: '1', label: 'Maya' },
											{ value: '2', label: 'Jonas' },
										] }
									/>
								</Field>
								<Field label="Notes">
									<Textarea placeholder="Anything we should know?" />
								</Field>
								<Checkbox
									label="Send confirmation email"
									description="The customer gets the booking details."
									defaultChecked
								/>
								<Toggle
									label="Accept online payments"
									description="Customers can pay by card."
									checked={ toggle }
									onChange={ setToggle }
								/>
								<Field label="Pick a date">
									<DateInput
										value={ dateInput }
										onChange={ setDateInput }
									/>
								</Field>
							</div>
						</Section>

						<Section title="Selectable cards">
							<RadioCards
								legend="Staff"
								value={ radio }
								onChange={ setRadio }
								columns="2"
								options={ [
									{
										value: 'any',
										label: 'Any available',
										description:
											'We will pick someone free at your time.',
										media: (
											<Avatar icon="users" size={ 44 } />
										),
									},
									{
										value: '1',
										label: 'Maya Jensen',
										description: 'Senior stylist',
										media: (
											<Avatar
												name="Maya Jensen"
												size={ 44 }
											/>
										),
									},
									{
										value: '2',
										label: 'Jonas Berg',
										media: (
											<Avatar
												name="Jonas Berg"
												size={ 44 }
											/>
										),
										disabled: true,
										description:
											'Not available for this service',
									},
								] }
							/>
							<div
								className="pbk-stack"
								style={ { marginTop: 16 } }
							>
								{ [ 'Hot towel', 'Beard trim' ].map(
									( name, i ) => (
										<ChoiceCard
											key={ name }
											label={ name }
											description="Adds 10 minutes"
											aside={ `+$${ 5 + i * 5 }` }
											checked={ extras.includes( name ) }
											onChange={ ( checked ) =>
												setExtras(
													checked
														? [ ...extras, name ]
														: extras.filter(
																( x ) =>
																	x !== name
														  )
												)
											}
										/>
									)
								) }
							</div>
						</Section>

						<Section title="Calendar and times">
							<div className="pbk-kit__split">
								<Calendar
									month={ month }
									onMonthChange={ setMonth }
									value={ date }
									onChange={ setDate }
									today={ TODAY }
									min={ TODAY }
									isDateDisabled={ ( d ) =>
										d < TODAY ||
										[ 0, 6 ].includes(
											new Date(
												d + 'T12:00:00Z'
											).getUTCDay()
										)
									}
									getDayInfo={ ( d ) => ( {
										dots:
											( Number( d.slice( 8 ) ) % 3 ) + 1,
									} ) }
									weekStartsOn={ config.weekStartsOn }
									locale={ config.locale }
								/>
								<TimeSlots
									value={ time }
									onChange={ setTime }
									lowThreshold={ 1 }
									slots={ [
										'09:00',
										'09:30',
										'10:00',
										'11:30',
										'13:00',
										'14:30',
										'16:00',
										'17:30',
										'18:00',
									].map( ( start, i ) => ( {
										start,
										end: start,
										available: i === 2 ? 1 : 3,
									} ) ) }
									locale={ config.locale }
									timeFormat={ config.timeFormat }
								/>
							</div>
							<TimeSlots loading />
						</Section>

						<Section title="Navigation">
							<Tabs
								value={ tab }
								onChange={ setTab }
								label="Booking status"
								tabs={ [
									{ key: 'all', label: 'All', count: 128 },
									{
										key: 'pending',
										label: 'Pending',
										count: 4,
									},
									{
										key: 'confirmed',
										label: 'Confirmed',
										count: 97,
									},
									{
										key: 'cancelled',
										label: 'Cancelled',
										count: 27,
									},
								] }
							/>
							<Stepper
								steps={ [
									{ key: 'service', label: 'Service' },
									{ key: 'staff', label: 'Staff' },
									{ key: 'time', label: 'Date & time' },
									{ key: 'details', label: 'Your details' },
									{ key: 'confirm', label: 'Confirm' },
								] }
								current="time"
								done={ [ 'service', 'staff' ] }
								onStepClick={ () => {} }
							/>
							<Stepper
								steps={ [
									{ key: 'a', label: 'Service' },
									{ key: 'b', label: 'Date & time' },
									{ key: 'c', label: 'Details' },
								] }
								current="b"
								variant="compact"
							/>
							<div className="pbk-row">
								<Dropdown
									label="Booking actions"
									trigger={ ( props ) => (
										<Button
											{ ...props }
											iconRight="chevron-down"
										>
											Actions
										</Button>
									) }
									items={ [
										{ label: 'Confirm', icon: 'check' },
										{ label: 'Reschedule', icon: 'repeat' },
										{ divider: true },
										{
											label: 'Cancel booking',
											icon: 'x-circle',
											danger: true,
										},
									] }
								/>
								<Tooltip text="Shown on hover and keyboard focus">
									<Button variant="ghost" icon="help-circle">
										Tooltip
									</Button>
								</Tooltip>
							</div>
						</Section>

						<Section title="Display">
							<div className="pbk-row">
								{ [
									'confirmed',
									'pending',
									'completed',
									'cancelled',
									'pending_payment',
									'failed_payment',
								].map( ( s ) => (
									<StatusBadge key={ s } status={ s } />
								) ) }
								<Badge tone="brand">New</Badge>
								<Badge tone="outline">Outline</Badge>
							</div>
							<div className="pbk-row">
								<Avatar name="Ada Lovelace" />
								<Avatar name="Grace Hopper" size={ 32 } />
								<Avatar name="Alan Turing" size={ 56 } />
								<Avatar icon="user" />
							</div>
							<Notice
								tone="warning"
								title="Payments are in test mode"
							>
								Real cards will not be charged.
							</Notice>
							<Notice tone="danger" onDismiss={ () => {} }>
								Stripe rejected the key.
							</Notice>
						</Section>

						<Section title="Overlays and feedback">
							<Overlays />
							<div className="pbk-kit__split">
								<EmptyState
									title="No bookings yet"
									description="When customers book, they show up here."
									action={
										<Button variant="primary">
											Add booking
										</Button>
									}
									compact
								/>
								<ErrorState
									message="We could not load the calendar."
									onRetry={ () => {} }
								/>
							</div>
						</Section>
					</div>

					<Card
						title="Table"
						padding="none"
						className="pbk-kit__section"
					>
						<Table
							caption="Bookings"
							columns={ [
								{
									key: 'customer',
									header: 'Customer',
									sortable: true,
									primary: true,
								},
								{
									key: 'service',
									header: 'Service',
									sortable: true,
								},
								{
									key: 'status',
									header: 'Status',
									render: ( r ) => (
										<StatusBadge status={ r.status } />
									),
								},
								{
									key: 'price',
									header: 'Price',
									align: 'end',
									sortable: true,
									render: ( r ) => `$${ r.price }`,
								},
							] }
							rows={ rows }
							sort={ sort }
							onSort={ ( key ) =>
								setSort( {
									key,
									order:
										sort.key === key && sort.order === 'asc'
											? 'desc'
											: 'asc',
								} )
							}
							selectable
							selected={ selected }
							onSelectionChange={ setSelected }
							bulkActions={
								<Button size="sm" variant="secondary">
									Mark confirmed
								</Button>
							}
						/>
						<Pagination
							page={ page }
							perPage={ 10 }
							total={ ROWS.length }
							onChange={ setPage }
						/>
						<Table
							columns={ [
								{ key: 'a', header: 'Customer' },
								{ key: 'b', header: 'Service' },
							] }
							rows={ [] }
							loading
							skeletonRows={ 3 }
						/>
					</Card>

					<Card
						title={ `Icons (${ Object.keys( ICONS ).length })` }
						className="pbk-kit__section"
					>
						<div className="pbk-kit__icons">
							{ Object.keys( ICONS ).map( ( name ) => (
								<Tooltip key={ name } text={ name }>
									<span
										className="pbk-kit__icon"
										tabIndex={ 0 }
									>
										<Icon name={ name } size={ 24 } />
									</span>
								</Tooltip>
							) ) }
						</div>
					</Card>
				</ConfirmProvider>
			</ToastProvider>
		</ThemeRoot>
	);
}
