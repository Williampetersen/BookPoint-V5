<?php
/**
 * Runs notification workflows when booking events happen.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Services\Notifications;

use PointlyBooking\Repositories\BookingRepository;
use PointlyBooking\Repositories\WorkflowRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Event → workflow matching, conditions, delays and the send_email action.
 */
final class WorkflowEngine {

	const CRON_HOOK        = 'pointlybooking_run_workflow';
	const LEGACY_CRON_HOOK = 'pointlybooking_run_workflow_event';

	/**
	 * Hooks the engine to booking lifecycle actions.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'pointlybooking_booking_created', array( __CLASS__, 'on_created' ), 10, 2 );
		add_action( 'pointlybooking_booking_status_changed', array( __CLASS__, 'on_status' ), 10, 4 );
		add_action( 'pointlybooking_booking_rescheduled', array( __CLASS__, 'on_rescheduled' ), 10, 3 );
		add_action( 'pointlybooking_customer_created', array( __CLASS__, 'on_customer_created' ), 10, 2 );
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_scheduled' ), 10, 3 );
		add_action( self::LEGACY_CRON_HOOK, array( __CLASS__, 'run_legacy_scheduled' ), 10, 2 );
	}

	/**
	 * New booking.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $context    Context.
	 * @return void
	 */
	public static function on_created( $booking_id, $context ) {
		if ( ! empty( $context['notify'] ) ) {
			self::dispatch( 'booking_created', (int) $booking_id );
		}
	}

	/**
	 * Status change.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $new_status New status.
	 * @param string $old_status Old status.
	 * @param array  $context    Context.
	 * @return void
	 */
	public static function on_status( $booking_id, $new_status, $old_status, $context ) {
		unset( $old_status );
		if ( empty( $context['notify'] ) ) {
			return;
		}
		self::dispatch( 'booking_updated', (int) $booking_id );
		if ( 'confirmed' === $new_status ) {
			self::dispatch( 'booking_confirmed', (int) $booking_id );
		} elseif ( 'cancelled' === $new_status ) {
			self::dispatch( 'booking_cancelled', (int) $booking_id );
		}
	}

	/**
	 * Reschedule.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $old        Previous times.
	 * @param array $context    Context.
	 * @return void
	 */
	public static function on_rescheduled( $booking_id, $old, $context ) {
		unset( $old );
		if ( ! empty( $context['notify'] ) ) {
			self::dispatch( 'booking_updated', (int) $booking_id );
		}
	}

	/**
	 * New customer: runs once the customer's first booking exists (so templates have booking data).
	 *
	 * @param int   $customer_id Customer ID.
	 * @param array $context     Context.
	 * @return void
	 */
	public static function on_customer_created( $customer_id, $context ) {
		if ( empty( $context['notify'] ) ) {
			return;
		}
		add_action(
			'pointlybooking_booking_created',
			static function ( $booking_id ) use ( $customer_id ) {
				$booking = BookingRepository::find( $booking_id );
				if ( $booking && (int) $booking['customer_id'] === (int) $customer_id ) {
					self::dispatch( 'customer_created', (int) $booking_id );
				}
			},
			20
		);
	}

	/**
	 * Runs (or schedules) every active workflow for an event.
	 *
	 * @param string $event      Event key.
	 * @param int    $booking_id Booking ID.
	 * @return void
	 */
	public static function dispatch( $event, $booking_id ) {
		$booking = BookingRepository::find( $booking_id );
		if ( ! $booking ) {
			return;
		}
		foreach ( WorkflowRepository::active_for( $event ) as $workflow ) {
			if ( ! self::conditions_match( $workflow, $booking ) ) {
				continue;
			}
			$delay = ! empty( $workflow['has_time_offset'] ) ? (int) $workflow['time_offset_minutes'] : 0;
			if ( $delay > 0 ) {
				wp_schedule_single_event( time() + $delay * MINUTE_IN_SECONDS, self::CRON_HOOK, array( (int) $workflow['id'], (int) $booking_id, (string) $event ) );
				continue;
			}
			self::execute( $workflow, $booking, $event );
		}
	}

	/**
	 * Delayed run (3.0 format: IDs only, data rebuilt at send time).
	 *
	 * @param int    $workflow_id Workflow ID.
	 * @param int    $booking_id  Booking ID.
	 * @param string $event       Event key.
	 * @return void
	 */
	public static function run_scheduled( $workflow_id, $booking_id, $event ) {
		$workflow = WorkflowRepository::find( $workflow_id );
		$booking  = BookingRepository::find( $booking_id );
		if ( ! $workflow || ! $booking || 'active' !== $workflow['status'] ) {
			return;
		}
		// A "cancelled" follow-up must not go out if the booking was re-confirmed meanwhile, and vice versa.
		if ( 'booking_cancelled' === $event && 'cancelled' !== $booking['status'] ) {
			WorkflowRepository::log( $workflow['id'], $event, $booking['id'], 'skipped', 'Booking status changed before the delayed send.' );
			return;
		}
		if ( ! self::conditions_match( $workflow, $booking ) ) {
			return;
		}
		self::execute( $workflow, $booking, (string) $event );
	}

	/**
	 * Delayed run scheduled by 2.x (payload JSON).
	 *
	 * @param int    $workflow_id  Workflow ID.
	 * @param string $payload_json Payload.
	 * @return void
	 */
	public static function run_legacy_scheduled( $workflow_id, $payload_json ) {
		$payload = json_decode( (string) $payload_json, true );
		$id      = is_array( $payload ) ? (int) ( $payload['entity_id'] ?? ( $payload['booking']['id'] ?? 0 ) ) : 0;
		if ( $id ) {
			$workflow = WorkflowRepository::find( $workflow_id );
			self::run_scheduled( $workflow_id, $id, $workflow ? $workflow['event_key'] : 'booking_created' );
		}
	}

	/**
	 * Evaluates workflow conditions (all must match).
	 *
	 * Condition format: {"field":"service_id","operator":"is|is_not|in|not_in","value":…}.
	 *
	 * @param array $workflow Workflow row.
	 * @param array $booking  Booking row.
	 * @return bool
	 */
	public static function conditions_match( array $workflow, array $booking ) {
		if ( empty( $workflow['is_conditional'] ) ) {
			return true;
		}
		$conditions = isset( $workflow['conditions']['rules'] ) ? $workflow['conditions']['rules'] : $workflow['conditions'];
		if ( ! is_array( $conditions ) ) {
			return true;
		}
		$fields = array( 'service_id', 'agent_id', 'location_id', 'status', 'payment_method', 'category_id', 'payment_status' );
		foreach ( $conditions as $condition ) {
			if ( ! is_array( $condition ) || ! in_array( $condition['field'] ?? '', $fields, true ) ) {
				continue;
			}
			$actual   = (string) ( $booking[ $condition['field'] ] ?? '' );
			$expected = array_map( 'strval', (array) ( $condition['value'] ?? array() ) );
			$operator = (string) ( $condition['operator'] ?? 'is' );
			$hit      = in_array( $actual, $expected, true );
			if ( in_array( $operator, array( 'is', 'in' ), true ) && ! $hit ) {
				return false;
			}
			if ( in_array( $operator, array( 'is_not', 'not_in' ), true ) && $hit ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Executes every active action of a workflow.
	 *
	 * @param array  $workflow Workflow row.
	 * @param array  $booking  Booking row.
	 * @param string $event    Event key.
	 * @return array<int,bool> action ID => success.
	 */
	public static function execute( array $workflow, array $booking, $event ) {
		$results = array();
		foreach ( WorkflowRepository::actions( $workflow['id'] ) as $action ) {
			if ( 'active' !== $action['status'] ) {
				continue;
			}
			$results[ $action['id'] ] = self::run_action( $workflow, $action, $booking, $event );
		}
		return $results;
	}

	/**
	 * Runs one action.
	 *
	 * @param array  $workflow Workflow row.
	 * @param array  $action   Action row.
	 * @param array  $booking  Booking row.
	 * @param string $event    Event key.
	 * @param bool   $test     Test run (ignores the global email switch).
	 * @return bool
	 */
	public static function run_action( array $workflow, array $action, array $booking, $event, $test = false ) {
		if ( 'send_email' !== $action['type'] ) {
			WorkflowRepository::log( $workflow['id'], $event, $booking['id'], 'failed', 'Unsupported action type.' );
			return false;
		}
		if ( ! $test && ! Mailer::enabled() ) {
			WorkflowRepository::log( $workflow['id'], $event, $booking['id'], 'skipped', 'Emails are turned off in Settings.' );
			return false;
		}

		$config  = $action['config'];
		$vars    = Variables::for_booking( $booking );
		$to      = Variables::render( (string) ( $config['to'] ?? '' ), $vars );
		$subject = Variables::render( (string) ( $config['subject'] ?? '' ), $vars );
		$body    = Variables::render( (string) ( $config['body'] ?? '' ), $vars, true );
		if ( '' === trim( $to ) ) {
			WorkflowRepository::log( $workflow['id'], $event, $booking['id'], 'failed', 'Recipient is empty.' );
			return false;
		}

		$attachments = array();
		if ( ! empty( $config['attach_ics'] ) ) {
			$file = Ics::temp_file( $booking );
			if ( '' !== $file ) {
				$attachments[] = $file;
			}
		}

		$sent = Mailer::send(
			$to,
			'' !== $subject ? $subject : __( 'Booking update', 'pointly-booking' ),
			$body,
			array(
				'from_name'  => Variables::render( (string) ( $config['from_name'] ?? '' ), $vars ),
				'from_email' => Variables::render( (string) ( $config['from_email'] ?? '' ), $vars ),
				'reply_to'   => Variables::render( (string) ( $config['reply_to'] ?? '' ), $vars ),
			),
			$attachments
		);

		foreach ( $attachments as $file ) {
			wp_delete_file( $file );
		}

		WorkflowRepository::log(
			$workflow['id'],
			$event,
			$booking['id'],
			$sent ? 'success' : 'failed',
			$sent ? ( $test ? 'Test email sent to ' . $to : 'Email sent to ' . $to ) : 'wp_mail() could not send the email.'
		);
		return $sent;
	}
}
