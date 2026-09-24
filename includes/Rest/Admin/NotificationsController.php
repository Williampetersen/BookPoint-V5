<?php
/**
 * Admin notifications (workflows) API.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Rest\Admin;

use PointlyBooking\Repositories\BookingRepository;
use PointlyBooking\Repositories\ServiceRepository;
use PointlyBooking\Repositories\WorkflowRepository;
use PointlyBooking\Rest\Controller;
use PointlyBooking\Services\Notifications\DefaultWorkflows;
use PointlyBooking\Services\Notifications\Mailer;
use PointlyBooking\Services\Notifications\Variables;
use PointlyBooking\Services\Notifications\WorkflowEngine;
use PointlyBooking\Support\Dates;
use PointlyBooking\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * /admin/notifications/…
 */
final class NotificationsController extends Controller {

	const BASE = '/admin/notifications';

	/**
	 * {@inheritDoc}
	 */
	public function register_routes() {
		$can = self::cap( 'pointlybooking_manage_settings' );

		$this->route(
			self::BASE . '/meta',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'meta' ),
				'permission_callback' => $can,
			)
		);
		$this->route(
			self::BASE . '/smart-variables',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'variables' ),
				'permission_callback' => $can,
			)
		);
		$this->route(
			self::BASE . '/preview',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => $can,
			)
		);
		$this->route(
			self::BASE . '/templates/(?P<template>[a-z0-9_]+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'install_template' ),
				'permission_callback' => $can,
			)
		);
		$this->route(
			self::BASE . '/workflows',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => $can,
					'args'                => array_merge(
						self::paging_args(),
						array(
							'status' => self::arg( 'key' ),
							'event'  => self::arg( 'key' ),
						)
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save' ),
					'permission_callback' => $can,
				),
			)
		);
		$this->route(
			self::BASE . '/workflows/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => $can,
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save' ),
					'permission_callback' => $can,
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete' ),
					'permission_callback' => $can,
				),
			)
		);
		$this->route(
			self::BASE . '/workflows/(?P<id>\d+)/test',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'test_workflow' ),
				'permission_callback' => $can,
			)
		);
		$this->route(
			self::BASE . '/workflows/(?P<id>\d+)/actions',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'actions' ),
					'permission_callback' => $can,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'action_create' ),
					'permission_callback' => $can,
				),
			)
		);
		$this->route(
			self::BASE . '/actions/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'action_save' ),
					'permission_callback' => $can,
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'action_delete' ),
					'permission_callback' => $can,
				),
			)
		);
		$this->route(
			self::BASE . '/actions/(?P<id>\d+)/test',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'test_action' ),
				'permission_callback' => $can,
			)
		);
	}

	/**
	 * Event labels.
	 *
	 * @return array<string,string>
	 */
	public static function event_labels() {
		return array(
			'booking_created'   => __( 'Booking created', 'pointly-booking' ),
			'booking_updated'   => __( 'Booking rescheduled or updated', 'pointly-booking' ),
			'booking_confirmed' => __( 'Booking confirmed', 'pointly-booking' ),
			'booking_cancelled' => __( 'Booking cancelled', 'pointly-booking' ),
			'customer_created'  => __( 'New customer', 'pointly-booking' ),
		);
	}

	/**
	 * Editor metadata: events, counts, templates, condition fields.
	 *
	 * @return \WP_REST_Response
	 */
	public function meta() {
		$installed = array();
		foreach ( WorkflowRepository::search( array( 'per_page' => 100 ) )['items'] as $w ) {
			$installed[] = $w['name'];
		}
		$templates = array();
		foreach ( DefaultWorkflows::templates() as $template ) {
			$templates[] = array(
				'id'        => $template['id'],
				'name'      => $template['name'],
				'event_key' => $template['event'],
				'recipient' => false !== strpos( (string) $template['action']['to'], 'customer_email' ) ? 'customer' : 'team',
				'default'   => ! empty( $template['default'] ),
				'subject'   => (string) $template['action']['subject'],
				'installed' => in_array( $template['name'], $installed, true ),
			);
		}
		$events = array();
		foreach ( self::event_labels() as $key => $label ) {
			$events[] = array(
				'value' => $key,
				'label' => $label,
			);
		}
		return $this->ok(
			array(
				'events'           => $events,
				'counts'           => WorkflowRepository::counts(),
				'templates'        => $templates,
				'action_types'     => array(
					array(
						'value' => 'send_email',
						'label' => __( 'Send email', 'pointly-booking' ),
					),
				),
				'condition_fields' => array(
					'service_id'     => __( 'Service', 'pointly-booking' ),
					'agent_id'       => __( 'Staff member', 'pointly-booking' ),
					'location_id'    => __( 'Location', 'pointly-booking' ),
					'status'         => __( 'Status', 'pointly-booking' ),
					'payment_method' => __( 'Payment method', 'pointly-booking' ),
					'payment_status' => __( 'Payment status', 'pointly-booking' ),
				),
				'emails_enabled'   => Mailer::enabled(),
				'test_recipient'   => wp_get_current_user()->user_email,
			)
		);
	}

	/**
	 * Smart variables catalogue.
	 *
	 * @return \WP_REST_Response
	 */
	public function variables() {
		return $this->ok( Variables::catalog() );
	}

	/**
	 * Workflow list.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function index( \WP_REST_Request $r ) {
		$result = WorkflowRepository::search(
			array(
				'search'   => (string) $r->get_param( 'search' ),
				'status'   => (string) $r->get_param( 'status' ),
				'event'    => (string) $r->get_param( 'event' ),
				'page'     => (int) $r->get_param( 'page' ),
				'per_page' => (int) $r->get_param( 'per_page' ),
			)
		);
		return $this->ok( $result );
	}

	/**
	 * Workflow with actions and recent runs.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show( \WP_REST_Request $r ) {
		$workflow = WorkflowRepository::find( (int) $r['id'] );
		if ( ! $workflow ) {
			return $this->error( 'not_found', __( 'Notification not found.', 'pointly-booking' ), 404 );
		}
		$workflow['actions'] = WorkflowRepository::actions( $workflow['id'] );
		$workflow['logs']    = WorkflowRepository::logs( $workflow['id'] );
		return $this->ok( $workflow );
	}

	/**
	 * Create or update a workflow (optionally with its actions in one request).
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save( \WP_REST_Request $r ) {
		$id       = (int) ( $r['id'] ?? 0 );
		$body     = $this->body( $r );
		$existing = $id ? WorkflowRepository::find( $id ) : null;
		if ( $id && ! $existing ) {
			return $this->error( 'not_found', __( 'Notification not found.', 'pointly-booking' ), 404 );
		}
		$merged = array_merge( $existing ? $existing : array(), $body );

		$name = Sanitize::text( $merged['name'] ?? '', 255 );
		if ( '' === $name ) {
			return $this->error( 'invalid_name', __( 'Please enter a name.', 'pointly-booking' ), 400, array( 'field' => 'name' ) );
		}
		$event = (string) ( $merged['event_key'] ?? ( $merged['event'] ?? '' ) );
		if ( ! in_array( $event, WorkflowRepository::EVENTS, true ) ) {
			return $this->error( 'invalid_event', __( 'Please choose when this notification is sent.', 'pointly-booking' ), 400, array( 'field' => 'event_key' ) );
		}

		$conditions = array();
		foreach ( (array) ( $merged['conditions'] ?? array() ) as $condition ) {
			if ( ! is_array( $condition ) || empty( $condition['field'] ) ) {
				continue;
			}
			$conditions[] = array(
				'field'    => Sanitize::key( $condition['field'] ),
				'operator' => Sanitize::one_of( $condition['operator'] ?? 'is', array( 'is', 'is_not', 'in', 'not_in' ), 'is' ),
				'value'    => array_map( array( Sanitize::class, 'text' ), (array) ( $condition['value'] ?? array() ) ),
			);
		}

		$offset = (int) ( $merged['time_offset_minutes'] ?? 0 );
		$offset = max( -525600, min( 525600, $offset ) );
		$data   = array(
			'name'                => $name,
			'status'              => Sanitize::one_of( $merged['status'] ?? 'active', array( 'active', 'disabled' ), 'active' ),
			'event_key'           => $event,
			'is_conditional'      => $conditions ? 1 : 0,
			'conditions_json'     => $conditions ? wp_json_encode( $conditions ) : null,
			'has_time_offset'     => 0 !== $offset ? 1 : 0,
			'time_offset_minutes' => $offset,
		);

		if ( $id ) {
			WorkflowRepository::save( $id, $data );
		} else {
			$id = WorkflowRepository::create( $data );
			if ( ! $id ) {
				return $this->error( 'save_failed', __( 'The notification could not be saved.', 'pointly-booking' ), 500 );
			}
		}

		if ( isset( $body['actions'] ) && is_array( $body['actions'] ) ) {
			$keep = array();
			foreach ( array_values( $body['actions'] ) as $index => $action ) {
				if ( ! is_array( $action ) ) {
					continue;
				}
				$clean = $this->clean_action( $action );
				if ( is_wp_error( $clean ) ) {
					return $clean;
				}
				$clean['sort_order'] = $index + 1;
				$action_id           = absint( $action['id'] ?? 0 );
				$current             = $action_id ? WorkflowRepository::action( $action_id ) : null;
				if ( $current && $current['workflow_id'] === $id ) {
					WorkflowRepository::save_action( $action_id, $clean );
				} else {
					$action_id = WorkflowRepository::create_action( $id, $clean );
					WorkflowRepository::save_action( $action_id, array( 'sort_order' => $clean['sort_order'] ) );
				}
				$keep[] = $action_id;
			}
			foreach ( WorkflowRepository::actions( $id ) as $action ) {
				if ( ! in_array( $action['id'], $keep, true ) ) {
					WorkflowRepository::delete_action( $action['id'] );
				}
			}
		}

		$request       = new \WP_REST_Request( 'GET' );
		$request['id'] = $id;
		$response      = $this->show( $request );
		if ( ! $existing && ! is_wp_error( $response ) ) {
			$response->set_status( 201 );
		}
		return $response;
	}

	/**
	 * Validates an action payload.
	 *
	 * @param array $action Raw action.
	 * @return array|\WP_Error
	 */
	private function clean_action( array $action ) {
		$config = isset( $action['config'] ) && is_array( $action['config'] ) ? $action['config'] : $action;
		$to     = Sanitize::text( $config['to'] ?? '', 500 );
		if ( '' === $to ) {
			return $this->error( 'invalid_recipient', __( 'Please enter a recipient, for example {customer_email}.', 'pointly-booking' ), 400, array( 'field' => 'to' ) );
		}
		return array(
			'type'   => 'send_email',
			'status' => Sanitize::one_of( $action['status'] ?? 'active', array( 'active', 'disabled' ), 'active' ),
			'config' => array(
				'to'         => $to,
				'subject'    => Sanitize::text( $config['subject'] ?? '', 255 ),
				'body'       => wp_kses( (string) ( $config['body'] ?? '' ), Mailer::allowed_html() ),
				'from_name'  => Sanitize::text( $config['from_name'] ?? '', 190 ),
				'from_email' => Sanitize::text( $config['from_email'] ?? '', 190 ),
				'reply_to'   => Sanitize::text( $config['reply_to'] ?? '', 190 ),
				'attach_ics' => Sanitize::bool01( $config['attach_ics'] ?? 0 ),
			),
		);
	}

	/**
	 * Deletes a workflow with its actions and logs.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function delete( \WP_REST_Request $r ) {
		WorkflowRepository::remove( (int) $r['id'] );
		return $this->ok( array( 'deleted' => true ) );
	}

	/**
	 * Actions of a workflow.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function actions( \WP_REST_Request $r ) {
		return $this->ok( WorkflowRepository::actions( (int) $r['id'] ) );
	}

	/**
	 * Adds an action.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function action_create( \WP_REST_Request $r ) {
		if ( ! WorkflowRepository::find( (int) $r['id'] ) ) {
			return $this->error( 'not_found', __( 'Notification not found.', 'pointly-booking' ), 404 );
		}
		$clean = $this->clean_action( $this->body( $r ) );
		if ( is_wp_error( $clean ) ) {
			return $clean;
		}
		$id = WorkflowRepository::create_action( (int) $r['id'], $clean );
		return $this->ok( WorkflowRepository::action( $id ), 201 );
	}

	/**
	 * Updates an action.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function action_save( \WP_REST_Request $r ) {
		$current = WorkflowRepository::action( (int) $r['id'] );
		if ( ! $current ) {
			return $this->error( 'not_found', __( 'Action not found.', 'pointly-booking' ), 404 );
		}
		$body  = $this->body( $r );
		$clean = $this->clean_action( array_merge( array( 'config' => $current['config'] ), $body, array( 'config' => array_merge( $current['config'], (array) ( $body['config'] ?? array() ) ) ) ) );
		if ( is_wp_error( $clean ) ) {
			return $clean;
		}
		WorkflowRepository::save_action( $current['id'], $clean );
		return $this->ok( WorkflowRepository::action( $current['id'] ) );
	}

	/**
	 * Deletes an action.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function action_delete( \WP_REST_Request $r ) {
		WorkflowRepository::delete_action( (int) $r['id'] );
		return $this->ok( array( 'deleted' => true ) );
	}

	/**
	 * Booking used for tests and previews: the requested one, the latest, or a sample.
	 *
	 * @param int $booking_id Requested booking.
	 * @return array
	 */
	private function sample_booking( $booking_id = 0 ) {
		$booking = $booking_id ? BookingRepository::find( $booking_id ) : null;
		if ( ! $booking ) {
			$booking = BookingRepository::latest();
		}
		if ( $booking ) {
			return $booking;
		}
		$services = ServiceRepository::all();
		$start    = Dates::add_days( Dates::today(), 1 ) . ' 10:00:00';
		return array(
			'id'             => 0,
			'status'         => 'confirmed',
			'service_id'     => $services ? $services[0]['id'] : 0,
			'agent_id'       => 0,
			'customer_id'    => 0,
			'location_id'    => 0,
			'start_datetime' => $start,
			'end_datetime'   => Dates::add_days( Dates::today(), 1 ) . ' 11:00:00',
			'total_price'    => $services ? $services[0]['price'] : 0,
			'discount_total' => 0,
			'currency'       => '',
			'notes'          => '',
			'manage_key'     => str_repeat( '0', 64 ),
			'payment_method' => 'cash',
			'payment_status' => 'unpaid',
			'extras_json'    => '',
		);
	}

	/**
	 * Sends every action of a workflow to a test recipient.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function test_workflow( \WP_REST_Request $r ) {
		$workflow = WorkflowRepository::find( (int) $r['id'] );
		if ( ! $workflow ) {
			return $this->error( 'not_found', __( 'Notification not found.', 'pointly-booking' ), 404 );
		}
		$actions = array_filter(
			WorkflowRepository::actions( $workflow['id'] ),
			static function ( $a ) {
				return 'active' === $a['status'];
			}
		);
		if ( ! $actions ) {
			return $this->error( 'no_actions', __( 'Add an email to this notification first.', 'pointly-booking' ) );
		}
		return $this->run_tests( $workflow, $actions, $r );
	}

	/**
	 * Sends one action to a test recipient.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function test_action( \WP_REST_Request $r ) {
		$action   = WorkflowRepository::action( (int) $r['id'] );
		$workflow = $action ? WorkflowRepository::find( $action['workflow_id'] ) : null;
		if ( ! $action || ! $workflow ) {
			return $this->error( 'not_found', __( 'Action not found.', 'pointly-booking' ), 404 );
		}
		return $this->run_tests( $workflow, array( $action ), $r );
	}

	/**
	 * Runs actions against a sample booking, redirected to the test recipient.
	 *
	 * @param array            $workflow Workflow.
	 * @param array            $actions  Actions.
	 * @param \WP_REST_Request $r        Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function run_tests( array $workflow, array $actions, \WP_REST_Request $r ) {
		$body = $this->body( $r );
		$to   = Sanitize::email( $body['to'] ?? wp_get_current_user()->user_email );
		if ( '' === $to ) {
			return $this->error( 'invalid_email', __( 'Please enter a valid email address.', 'pointly-booking' ), 400, array( 'field' => 'to' ) );
		}
		$booking = $this->sample_booking( absint( $body['booking_id'] ?? 0 ) );
		$sent    = 0;
		foreach ( $actions as $action ) {
			$action['config']['to'] = $to;
			if ( WorkflowEngine::run_action( $workflow, $action, $booking, $workflow['event_key'], true ) ) {
				++$sent;
			}
		}
		if ( ! $sent ) {
			return $this->error( 'send_failed', __( 'The test email could not be sent. Check your site’s email configuration.', 'pointly-booking' ), 500 );
		}
		return $this->ok(
			array(
				'sent'       => $sent,
				'to'         => $to,
				'booking_id' => (int) $booking['id'],
			)
		);
	}

	/**
	 * Renders a subject/body pair with sample data.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function preview( \WP_REST_Request $r ) {
		$body    = $this->body( $r );
		$vars    = Variables::for_booking( $this->sample_booking( absint( $body['booking_id'] ?? 0 ) ) );
		$subject = Variables::render( Sanitize::text( $body['subject'] ?? '', 255 ), $vars );
		$html    = Variables::render( wp_kses( (string) ( $body['body'] ?? '' ), Mailer::allowed_html() ), $vars, true );
		return $this->ok(
			array(
				'subject' => $subject,
				'html'    => Mailer::layout( $html, $subject ),
			)
		);
	}

	/**
	 * Installs one of the built-in notification templates.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function install_template( \WP_REST_Request $r ) {
		$id = DefaultWorkflows::install( (string) $r['template'] );
		if ( ! $id ) {
			return $this->error( 'invalid_template', __( 'Unknown template.', 'pointly-booking' ), 404 );
		}
		$request       = new \WP_REST_Request( 'GET' );
		$request['id'] = $id;
		return $this->show( $request );
	}
}
