<?php
/**
 * Customer portal API ([pointlybooking_customer_portal]).
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Rest\Front;

use PointlyBooking\Repositories\BookingRepository;
use PointlyBooking\Rest\Controller;
use PointlyBooking\Rest\Presenter;
use PointlyBooking\Services\Portal;
use PointlyBooking\Support\Dates;
use PointlyBooking\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * /portal/…: email → one-time code → booking list.
 */
final class PortalController extends Controller {

	/**
	 * {@inheritDoc}
	 */
	public function register_routes() {
		$public  = array( Controller::class, 'public_access' );
		$session = array(
			'email' => self::arg( 'email', true ),
			'token' => self::arg( 'string', true ),
		);

		$this->route(
			'/portal/code',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'code' ),
				'permission_callback' => $public,
				'args'                => array( 'email' => self::arg( 'email', true ) ),
			)
		);
		$this->route(
			'/portal/verify',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'verify' ),
				'permission_callback' => $public,
				'args'                => array(
					'email' => self::arg( 'email', true ),
					'code'  => self::arg( 'string', true ),
				),
			)
		);
		$this->route(
			'/portal/bookings',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'bookings' ),
				'permission_callback' => $public,
				'args'                => $session,
			)
		);
		$this->route(
			'/portal/logout',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'logout' ),
				'permission_callback' => $public,
				'args'                => $session,
			)
		);
	}

	/**
	 * Sends a one-time code (the answer never reveals whether the address is known).
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function code( \WP_REST_Request $r ) {
		$limited = WizardController::throttle( 'portal_action', 20, 600 );
		if ( $limited ) {
			return $limited;
		}
		$email = Sanitize::email( $r->get_param( 'email' ) );
		if ( '' === $email ) {
			return $this->error( 'invalid_email', __( 'Please enter a valid email address.', 'pointly-booking' ), 400, array( 'field' => 'email' ) );
		}
		Portal::send_code( $email );
		return $this->ok( array( 'sent' => true ) );
	}

	/**
	 * Verifies the code and returns a 20-minute session token.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function verify( \WP_REST_Request $r ) {
		$limited = WizardController::throttle( 'portal_action', 20, 600 );
		if ( $limited ) {
			return $limited;
		}
		$email = Sanitize::email( $r->get_param( 'email' ) );
		$token = Portal::verify( $email, (string) $r->get_param( 'code' ) );
		if ( ! $token ) {
			return $this->error( 'invalid_code', __( 'That code is not valid or has expired. Please try again or request a new code.', 'pointly-booking' ), 400, array( 'field' => 'code' ) );
		}
		return $this->ok(
			array(
				'token'   => $token,
				'expires' => time() + Portal::SESSION_TTL,
			)
		);
	}

	/**
	 * Bookings of the signed-in customer, upcoming first.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function bookings( \WP_REST_Request $r ) {
		$limited = WizardController::throttle( 'portal_view', 120, 600 );
		if ( $limited ) {
			return $limited;
		}
		$email = Sanitize::email( $r->get_param( 'email' ) );
		if ( ! Portal::session_valid( $email, (string) $r->get_param( 'token' ) ) ) {
			return $this->error( 'session_expired', __( 'Your session has expired. Please sign in again.', 'pointly-booking' ), 401 );
		}
		$now      = Dates::now_mysql();
		$upcoming = array();
		$past     = array();
		foreach ( BookingRepository::for_email( $email ) as $booking ) {
			$row = array_merge( Presenter::booking_public( $booking ), ManageController::permissions( $booking ) );
			if ( $booking['end_datetime'] >= $now && ! in_array( $booking['status'], BookingRepository::FREEING_STATUSES, true ) ) {
				$upcoming[] = $row;
			} else {
				$past[] = $row;
			}
		}
		usort(
			$upcoming,
			static function ( $a, $b ) {
				return strcmp( $a['start'], $b['start'] );
			}
		);
		return $this->ok(
			array(
				'upcoming' => $upcoming,
				'past'     => $past,
			)
		);
	}

	/**
	 * Ends the session.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function logout( \WP_REST_Request $r ) {
		$email = Sanitize::email( $r->get_param( 'email' ) );
		if ( Portal::session_valid( $email, (string) $r->get_param( 'token' ) ) ) {
			Portal::end_session( $email );
		}
		return $this->ok( array( 'signed_out' => true ) );
	}
}
