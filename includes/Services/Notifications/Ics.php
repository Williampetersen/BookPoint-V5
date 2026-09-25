<?php
/**
 * Calendar file (.ics) generation.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Services\Notifications;

use PointlyBooking\Repositories\LocationRepository;
use PointlyBooking\Repositories\ServiceRepository;
use PointlyBooking\Settings\Settings;
use PointlyBooking\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * RFC 5545 event for a booking.
 */
final class Ics {

	/**
	 * Escapes a text value.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function escape( $text ) {
		$text = wp_strip_all_tags( (string) $text );
		return str_replace( array( '\\', ';', ',', "\r\n", "\n" ), array( '\\\\', '\\;', '\\,', '\\n', '\\n' ), $text );
	}

	/**
	 * Folds long lines at 74 octets.
	 *
	 * @param string $line Line.
	 * @return string
	 */
	private static function fold( $line ) {
		$out    = '';
		$length = strlen( $line );
		while ( $length > 74 ) {
			$part   = mb_strcut( $line, 0, 74, 'UTF-8' );
			$out   .= $part . "\r\n ";
			$line   = substr( $line, strlen( $part ) );
			$length = strlen( $line );
		}
		return $out . $line;
	}

	/**
	 * Builds the .ics content.
	 *
	 * @param array $booking Booking row.
	 * @return string
	 */
	public static function build( array $booking ) {
		$start = Dates::parse( $booking['start_datetime'] );
		$end   = Dates::parse( $booking['end_datetime'] );
		if ( ! $start || ! $end ) {
			return '';
		}
		$service  = ServiceRepository::find( (int) $booking['service_id'] );
		$location = ! empty( $booking['location_id'] ) ? LocationRepository::find( (int) $booking['location_id'] ) : null;
		$business = (string) Settings::get( 'business_name', get_bloginfo( 'name' ) );
		$summary  = $service ? $service['name'] : __( 'Appointment', 'pointly-booking' );
		if ( '' !== $business ) {
			$summary .= ' — ' . wp_specialchars_decode( $business, ENT_QUOTES );
		}
		$where = $location ? trim( $location['name'] . ( $location['address'] ? ', ' . $location['address'] : '' ) ) : (string) Settings::get( 'business_address', '' );
		$host  = wp_parse_url( home_url(), PHP_URL_HOST );

		$lines = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//BookPoint//Booking//EN',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'BEGIN:VEVENT',
			'UID:booking-' . (int) $booking['id'] . '@' . ( $host ? $host : 'bookpoint' ),
			'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
			'DTSTART:' . gmdate( 'Ymd\THis\Z', $start->getTimestamp() ),
			'DTEND:' . gmdate( 'Ymd\THis\Z', $end->getTimestamp() ),
			'SUMMARY:' . self::escape( $summary ),
			'DESCRIPTION:' . self::escape(
				/* translators: %d: booking id */
				sprintf( __( 'Booking #%d', 'pointly-booking' ), (int) $booking['id'] ) . ' — ' . Variables::manage_url( (string) $booking['manage_key'] )
			),
		);
		if ( '' !== $where ) {
			$lines[] = 'LOCATION:' . self::escape( $where );
		}
		$lines[] = 'STATUS:' . ( 'cancelled' === $booking['status'] ? 'CANCELLED' : 'CONFIRMED' );
		$lines[] = 'END:VEVENT';
		$lines[] = 'END:VCALENDAR';

		return implode( "\r\n", array_map( array( __CLASS__, 'fold' ), $lines ) ) . "\r\n";
	}

	/**
	 * Writes the .ics to a temporary file (email attachment). Caller deletes it.
	 *
	 * @param array $booking Booking row.
	 * @return string Path or ''.
	 */
	public static function temp_file( array $booking ) {
		$content = self::build( $booking );
		if ( '' === $content ) {
			return '';
		}
		$dir  = get_temp_dir();
		$path = trailingslashit( $dir ) . 'booking-' . (int) $booking['id'] . '-' . wp_generate_password( 6, false ) . '.ics';

		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		if ( $wp_filesystem && $wp_filesystem->put_contents( $path, $content, FS_CHMOD_FILE ) ) {
			return $path;
		}
		return '';
	}
}
