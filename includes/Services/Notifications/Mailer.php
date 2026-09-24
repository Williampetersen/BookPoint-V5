<?php
/**
 * Sends branded HTML emails through wp_mail().
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Services\Notifications;

use PointlyBooking\Settings\Design;
use PointlyBooking\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Email transport with a neutral, responsive layout.
 */
final class Mailer {

	/**
	 * Whether plugin emails are enabled.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return Settings::bool( 'pointlybooking_email_enabled' );
	}

	/**
	 * Wraps body HTML in the email layout.
	 *
	 * @param string $body    Body HTML (already safe).
	 * @param string $subject Subject (used as preheader).
	 * @return string
	 */
	public static function layout( $body, $subject = '' ) {
		$brand    = Design::get()['appearance']['primaryColor'] ?? '#4f46e5';
		$business = wp_specialchars_decode( (string) Settings::get( 'business_name', get_bloginfo( 'name' ) ), ENT_QUOTES );
		$footer   = array_filter(
			array(
				(string) Settings::get( 'business_address', '' ),
				(string) Settings::get( 'business_phone', '' ),
				(string) Settings::get( 'business_email', '' ),
			)
		);

		ob_start();
		include POINTLYBOOKING_PLUGIN_DIR . 'templates/emails/layout.php';
		return (string) ob_get_clean();
	}

	/**
	 * Tags allowed in email bodies.
	 *
	 * @return array
	 */
	public static function allowed_html() {
		$allowed = wp_kses_allowed_html( 'post' );
		foreach ( array( 'table', 'tr', 'td', 'th', 'tbody', 'thead', 'div', 'span', 'p', 'a', 'h1', 'h2', 'h3', 'h4', 'img' ) as $tag ) {
			$allowed[ $tag ]          = $allowed[ $tag ] ?? array();
			$allowed[ $tag ]['style'] = true;
		}
		return $allowed;
	}

	/**
	 * Sends an email.
	 *
	 * @param string   $to          Recipient(s), comma separated.
	 * @param string   $subject     Subject (plain text).
	 * @param string   $body        Body HTML.
	 * @param array    $options     from_name, from_email, reply_to.
	 * @param string[] $attachments File paths.
	 * @return bool
	 */
	public static function send( $to, $subject, $body, array $options = array(), array $attachments = array() ) {
		$recipients = array();
		foreach ( preg_split( '/[,;]+/', (string) $to ) as $address ) {
			$address = sanitize_email( trim( $address ) );
			if ( is_email( $address ) ) {
				$recipients[] = $address;
			}
		}
		if ( ! $recipients ) {
			return false;
		}

		$from_name  = trim( (string) ( $options['from_name'] ?? '' ) );
		$from_email = sanitize_email( (string) ( $options['from_email'] ?? '' ) );
		if ( '' === $from_name ) {
			$from_name = (string) Settings::get( 'pointlybooking_email_from_name', get_bloginfo( 'name' ) );
		}
		if ( ! is_email( $from_email ) ) {
			$from_email = sanitize_email( (string) Settings::get( 'pointlybooking_email_from_email', get_option( 'admin_email' ) ) );
		}

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( is_email( $from_email ) ) {
			$headers[] = sprintf( 'From: %s <%s>', str_replace( array( '"', "\r", "\n" ), '', wp_specialchars_decode( $from_name, ENT_QUOTES ) ), $from_email );
		}
		$reply_to = sanitize_email( (string) ( $options['reply_to'] ?? '' ) );
		if ( is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		$subject = wp_specialchars_decode( wp_strip_all_tags( (string) $subject ), ENT_QUOTES );
		$html    = self::layout( wp_kses( (string) $body, self::allowed_html() ), $subject );

		return (bool) wp_mail( $recipients, $subject, $html, $headers, $attachments );
	}
}
