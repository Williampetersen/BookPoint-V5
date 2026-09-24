<?php
/**
 * Customer portal: passwordless access to "my bookings" via a one-time email code.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Services;

use PointlyBooking\Repositories\CustomerRepository;
use PointlyBooking\Services\Notifications\Mailer;
use PointlyBooking\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * One-time codes and short sessions stored in transients.
 */
final class Portal {

	const CODE_TTL     = 600;
	const SESSION_TTL  = 1200;
	const MAX_ATTEMPTS = 5;

	/**
	 * Transient key for an email.
	 *
	 * @param string $email  Email.
	 * @param string $suffix Purpose.
	 * @return string
	 */
	private static function key( $email, $suffix ) {
		return 'pointlybooking_portal_' . md5( strtolower( trim( $email ) ) . '|' . $suffix . '|' . wp_salt( 'nonce' ) );
	}

	/**
	 * Sends a code when the email belongs to a customer. Always returns true so the
	 * form never reveals whether an address has bookings.
	 *
	 * @param string $email Email.
	 * @return bool
	 */
	public static function send_code( $email ) {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) || ! CustomerRepository::find_by_email( $email ) ) {
			return true;
		}
		$code = (string) wp_rand( 100000, 999999 );
		set_transient(
			self::key( $email, 'code' ),
			array(
				'hash'     => wp_hash_password( $code ),
				'attempts' => 0,
			),
			self::CODE_TTL
		);

		$business = wp_specialchars_decode( (string) Settings::get( 'business_name', get_bloginfo( 'name' ) ), ENT_QUOTES );
		/* translators: %s: business name */
		$subject = sprintf( __( 'Your sign-in code for %s', 'pointly-booking' ), $business );
		$body    = '<p>' . esc_html__( 'Use this code to see your bookings:', 'pointly-booking' ) . '</p>'
			. '<p style="font-size:28px;font-weight:700;letter-spacing:6px;margin:16px 0;">' . esc_html( $code ) . '</p>'
			. '<p style="color:#6b7280;">' . esc_html__( 'The code expires in 10 minutes. If you did not ask for it, you can ignore this email.', 'pointly-booking' ) . '</p>';

		Mailer::send( $email, $subject, $body );
		return true;
	}

	/**
	 * Verifies a code and opens a session.
	 *
	 * @param string $email Email.
	 * @param string $code  6-digit code.
	 * @return string|null Session token.
	 */
	public static function verify( $email, $code ) {
		$key  = self::key( $email, 'code' );
		$data = get_transient( $key );
		if ( ! is_array( $data ) || empty( $data['hash'] ) ) {
			return null;
		}
		if ( (int) $data['attempts'] >= self::MAX_ATTEMPTS ) {
			delete_transient( $key );
			return null;
		}
		if ( ! wp_check_password( preg_replace( '/\D+/', '', (string) $code ), $data['hash'] ) ) {
			++$data['attempts'];
			set_transient( $key, $data, self::CODE_TTL );
			return null;
		}
		delete_transient( $key );
		$token = wp_generate_password( 40, false, false );
		set_transient( self::key( $email, 'session' ), wp_hash( $token ), self::SESSION_TTL );
		return $token;
	}

	/**
	 * Whether a session token is valid.
	 *
	 * @param string $email Email.
	 * @param string $token Session token.
	 * @return bool
	 */
	public static function session_valid( $email, $token ) {
		if ( '' === (string) $token || ! is_email( $email ) ) {
			return false;
		}
		$stored = get_transient( self::key( $email, 'session' ) );
		return is_string( $stored ) && hash_equals( $stored, wp_hash( (string) $token ) );
	}

	/**
	 * Ends a session.
	 *
	 * @param string $email Email.
	 * @return void
	 */
	public static function end_session( $email ) {
		delete_transient( self::key( $email, 'session' ) );
	}
}
