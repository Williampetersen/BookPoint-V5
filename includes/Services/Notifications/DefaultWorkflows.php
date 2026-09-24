<?php
/**
 * Built-in notification templates and first-run seeding.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Services\Notifications;

use PointlyBooking\Repositories\WorkflowRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Ready-made workflows (also offered as "templates" in the Notifications screen).
 */
final class DefaultWorkflows {

	const SEEDED_OPTION = 'pointlybooking_default_workflows_seeded';

	/**
	 * Template definitions.
	 *
	 * @return array[]
	 */
	public static function templates() {
		$details = '<table role="presentation" style="width:100%;border-collapse:collapse;margin:16px 0;font-size:15px;">'
			. '<tr><td style="padding:6px 0;color:#6b7280;width:38%;">' . esc_html__( 'Service', 'pointly-booking' ) . '</td><td style="padding:6px 0;"><strong>{{service_name}}</strong></td></tr>'
			. '<tr><td style="padding:6px 0;color:#6b7280;">' . esc_html__( 'Date', 'pointly-booking' ) . '</td><td style="padding:6px 0;">{{booking_date}}</td></tr>'
			. '<tr><td style="padding:6px 0;color:#6b7280;">' . esc_html__( 'Time', 'pointly-booking' ) . '</td><td style="padding:6px 0;">{{booking_time}} – {{booking_end_time}}</td></tr>'
			. '<tr><td style="padding:6px 0;color:#6b7280;">' . esc_html__( 'With', 'pointly-booking' ) . '</td><td style="padding:6px 0;">{{agent_name}}</td></tr>'
			. '<tr><td style="padding:6px 0;color:#6b7280;">' . esc_html__( 'Total', 'pointly-booking' ) . '</td><td style="padding:6px 0;">{{total_formatted}}</td></tr>'
			. '</table>';

		$button = static function ( $label ) {
			return '<p style="margin:24px 0;"><a href="{{manage_booking_url_customer}}" style="display:inline-block;padding:12px 20px;border-radius:10px;background:#4f46e5;color:#ffffff;text-decoration:none;font-weight:600;">' . esc_html( $label ) . '</a></p>';
		};

		return array(
			array(
				'id'      => 'booking_created_customer',
				'name'    => __( 'Booking received — email the customer', 'pointly-booking' ),
				'event'   => 'booking_created',
				'default' => true,
				'action'  => array(
					'to'         => '{{customer_email}}',
					'subject'    => __( 'We received your booking (#{{booking_id}})', 'pointly-booking' ),
					'body'       => '<p>' . esc_html__( 'Hi {{customer_first_name}},', 'pointly-booking' ) . '</p>'
						. '<p>' . esc_html__( 'Thanks for booking with us. Here are the details:', 'pointly-booking' ) . '</p>'
						. $details
						. '<p>' . esc_html__( 'Status: {{booking_status}}', 'pointly-booking' ) . '</p>'
						. $button( __( 'View or change your booking', 'pointly-booking' ) )
						. '<p>' . esc_html__( 'See you soon!', 'pointly-booking' ) . '<br>{{business_name}}</p>',
					'attach_ics' => true,
				),
			),
			array(
				'id'      => 'booking_created_admin',
				'name'    => __( 'New booking — notify the team', 'pointly-booking' ),
				'event'   => 'booking_created',
				'default' => true,
				'action'  => array(
					'to'         => '{{admin_email}}',
					'subject'    => __( 'New booking #{{booking_id}}: {{service_name}} on {{booking_date}}', 'pointly-booking' ),
					'body'       => '<p><strong>' . esc_html__( 'You have a new booking.', 'pointly-booking' ) . '</strong></p>'
						. $details
						. '<p>' . esc_html__( 'Customer: {{customer_name}} · {{customer_email}} · {{customer_phone}}', 'pointly-booking' ) . '</p>'
						. '<p>' . esc_html__( 'Status: {{booking_status}} · Payment: {{payment_method}} ({{payment_status}})', 'pointly-booking' ) . '</p>'
						. '<p><a href="{{manage_booking_url_agent}}">' . esc_html__( 'Open in BookPoint', 'pointly-booking' ) . '</a></p>',
					'attach_ics' => false,
				),
			),
			array(
				'id'      => 'booking_confirmed_customer',
				'name'    => __( 'Booking confirmed — email the customer', 'pointly-booking' ),
				'event'   => 'booking_confirmed',
				'default' => true,
				'action'  => array(
					'to'         => '{{customer_email}}',
					'subject'    => __( 'Your booking is confirmed (#{{booking_id}})', 'pointly-booking' ),
					'body'       => '<p>' . esc_html__( 'Hi {{customer_first_name}},', 'pointly-booking' ) . '</p>'
						. '<p>' . esc_html__( 'Good news — your booking is confirmed.', 'pointly-booking' ) . '</p>'
						. $details
						. $button( __( 'Manage your booking', 'pointly-booking' ) )
						. '<p>{{business_name}}</p>',
					'attach_ics' => true,
				),
			),
			array(
				'id'      => 'booking_cancelled_customer',
				'name'    => __( 'Booking cancelled — email the customer', 'pointly-booking' ),
				'event'   => 'booking_cancelled',
				'default' => true,
				'action'  => array(
					'to'         => '{{customer_email}}',
					'subject'    => __( 'Your booking was cancelled (#{{booking_id}})', 'pointly-booking' ),
					'body'       => '<p>' . esc_html__( 'Hi {{customer_first_name}},', 'pointly-booking' ) . '</p>'
						. '<p>' . esc_html__( 'Your booking for {{service_name}} on {{booking_date}} at {{booking_time}} has been cancelled.', 'pointly-booking' ) . '</p>'
						. '<p>' . esc_html__( 'If this is a mistake or you would like a new time, just book again on our website.', 'pointly-booking' ) . '</p>'
						. '<p>{{business_name}}</p>',
					'attach_ics' => false,
				),
			),
			array(
				'id'      => 'booking_cancelled_admin',
				'name'    => __( 'Booking cancelled — notify the team', 'pointly-booking' ),
				'event'   => 'booking_cancelled',
				'default' => true,
				'action'  => array(
					'to'         => '{{admin_email}}',
					'subject'    => __( 'Booking cancelled (#{{booking_id}})', 'pointly-booking' ),
					'body'       => '<p>' . esc_html__( 'A booking was cancelled.', 'pointly-booking' ) . '</p>'
						. $details
						. '<p>' . esc_html__( 'Customer: {{customer_name}} ({{customer_email}})', 'pointly-booking' ) . '</p>',
					'attach_ics' => false,
				),
			),
			array(
				'id'      => 'booking_updated_customer',
				'name'    => __( 'Booking changed — email the customer', 'pointly-booking' ),
				'event'   => 'booking_updated',
				'default' => false,
				'action'  => array(
					'to'         => '{{customer_email}}',
					'subject'    => __( 'Your booking was updated (#{{booking_id}})', 'pointly-booking' ),
					'body'       => '<p>' . esc_html__( 'Hi {{customer_first_name}},', 'pointly-booking' ) . '</p>'
						. '<p>' . esc_html__( 'Your booking has been updated. The latest details are below.', 'pointly-booking' ) . '</p>'
						. $details
						. '<p>' . esc_html__( 'Status: {{booking_status}}', 'pointly-booking' ) . '</p>'
						. $button( __( 'Manage your booking', 'pointly-booking' ) ),
					'attach_ics' => true,
				),
			),
		);
	}

	/**
	 * Creates a workflow (with its email action) from a template.
	 *
	 * @param string $template_id Template ID.
	 * @return int Workflow ID (0 when unknown).
	 */
	public static function install( $template_id ) {
		foreach ( self::templates() as $template ) {
			if ( $template['id'] !== $template_id ) {
				continue;
			}
			$workflow_id = WorkflowRepository::create(
				array(
					'name'      => $template['name'],
					'status'    => 'active',
					'event_key' => $template['event'],
				)
			);
			if ( $workflow_id ) {
				WorkflowRepository::create_action(
					$workflow_id,
					array(
						'type'   => 'send_email',
						'status' => 'active',
						'config' => $template['action'],
					)
				);
			}
			return $workflow_id;
		}
		return 0;
	}

	/**
	 * Seeds the default workflows once, only when the site has none.
	 *
	 * @return void
	 */
	public static function maybe_seed() {
		if ( get_option( self::SEEDED_OPTION ) ) {
			return;
		}
		update_option( self::SEEDED_OPTION, 1, false );
		if ( WorkflowRepository::count_all() > 0 ) {
			return;
		}
		foreach ( self::templates() as $template ) {
			if ( ! empty( $template['default'] ) ) {
				self::install( $template['id'] );
			}
		}
	}
}
