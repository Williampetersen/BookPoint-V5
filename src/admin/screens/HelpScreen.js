/**
 * Help / How to use screen (F-021).
 */
import { __ } from '@wordpress/i18n';
import { Card, Icon, Button } from '../../ui';
import { PageHeader } from '../layout/PageHeader';
import { useRoute } from '../router';
import './help.css';

const STEPS = [
	{
		icon: 'building',
		title: __( 'Set up your business', 'pointly-booking' ),
		text: __( 'Add your business name, currency and contact details in Settings → General.', 'pointly-booking' ),
		route: 'settings',
		params: { tab: 'general' },
	},
	{
		icon: 'sparkles',
		title: __( 'Add your services', 'pointly-booking' ),
		text: __( 'Create the services customers can book, with a price, duration and description.', 'pointly-booking' ),
		route: 'services',
	},
	{
		icon: 'user-plus',
		title: __( 'Add your staff', 'pointly-booking' ),
		text: __( 'Add the people who perform each service, and their working hours.', 'pointly-booking' ),
		route: 'staff',
	},
	{
		icon: 'clock',
		title: __( 'Set your hours', 'pointly-booking' ),
		text: __( 'Set business hours, breaks and holidays in Settings → Schedule.', 'pointly-booking' ),
		route: 'settings',
		params: { tab: 'schedule' },
	},
	{
		icon: 'palette',
		title: __( 'Design your booking form', 'pointly-booking' ),
		text: __( 'Choose your brand colour, which steps to show, and the wording customers see.', 'pointly-booking' ),
		route: 'booking-form',
	},
	{
		icon: 'layout',
		title: __( 'Add the booking form to your site', 'pointly-booking' ),
		text: __( 'Use the shortcode [pointlybooking_booking_form], the Booking form block, or embed it inline.', 'pointly-booking' ),
	},
	{
		icon: 'credit-card',
		title: __( 'Turn on payments (optional)', 'pointly-booking' ),
		text: __( 'Accept cards with Stripe, PayPal, or connect to WooCommerce, in Settings → Payments.', 'pointly-booking' ),
		route: 'settings',
		params: { tab: 'payments' },
	},
	{
		icon: 'bell',
		title: __( 'Check your notifications', 'pointly-booking' ),
		text: __( 'BookPoint emails customers automatically. Review or customise the wording in Notifications.', 'pointly-booking' ),
		route: 'notifications',
	},
];

export default function HelpScreen() {
	const { navigate } = useRoute();
	return (
		<div className="pbk-help-screen">
			<PageHeader title={ __( 'Help', 'pointly-booking' ) } description={ __( 'A quick tour of BookPoint, and where to find everything.', 'pointly-booking' ) } />
			<div className="pbk-help-screen__steps">
				{ STEPS.map( ( step, index ) => (
					<Card key={ step.title } className="pbk-help-step">
						<div className="pbk-help-step__number">{ index + 1 }</div>
						<span className="pbk-help-step__icon">
							<Icon name={ step.icon } size={ 22 } />
						</span>
						<h3 className="pbk-help-step__title">{ step.title }</h3>
						<p className="pbk-help-step__text">{ step.text }</p>
						{ step.route && (
							<Button variant="secondary" size="sm" iconRight="arrow-right" onClick={ () => navigate( step.route, step.params || {} ) }>
								{ __( 'Go there', 'pointly-booking' ) }
							</Button>
						) }
					</Card>
				) ) }
			</div>
			<Card title={ __( 'Good to know', 'pointly-booking' ) } className="pbk-help-screen__support">
				<ul className="pbk-help-screen__tips">
					<li>{ __( 'Every screen with a list supports search, filters and bulk actions where useful.', 'pointly-booking' ) }</li>
					<li>{ __( 'Deleting a service, staff member or location never deletes past bookings.', 'pointly-booking' ) }</li>
					<li>{ __( 'Use Settings → Tools to check your setup, resend a test email, or export your data.', 'pointly-booking' ) }</li>
				</ul>
			</Card>
		</div>
	);
}
