<?php
/**
 * Imports and exports: bookings, customers, audit log, settings.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Services;

use PointlyBooking\Repositories\AuditRepository;
use PointlyBooking\Repositories\BookingRepository;
use PointlyBooking\Repositories\CustomerRepository;
use PointlyBooking\Services\Notifications\Variables;
use PointlyBooking\Settings\Design;
use PointlyBooking\Settings\Settings;
use PointlyBooking\Support\Dates;
use PointlyBooking\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * Data transfer helpers (pure: they return strings/arrays, controllers send them).
 */
final class Transfer {

	/**
	 * Builds CSV text with a UTF-8 BOM (Excel friendly).
	 *
	 * @param array $header Header row.
	 * @param array $rows   Rows.
	 * @return string
	 */
	public static function csv( array $header, array $rows ) {
		$file = new \SplTempFileObject();
		$file->fputcsv( $header );
		foreach ( $rows as $row ) {
			$file->fputcsv(
				array_map(
					static function ( $cell ) {
						$cell = (string) $cell;
						// Neutralise spreadsheet formula injection.
						return ( '' !== $cell && in_array( $cell[0], array( '=', '+', '-', '@' ), true ) && ! is_numeric( $cell ) ) ? "'" . $cell : $cell;
					},
					$row
				)
			);
		}
		$file->rewind();
		$out = '';
		while ( ! $file->eof() ) {
			$out .= (string) $file->fgets();
		}
		return "\xEF\xBB\xBF" . $out;
	}

	/**
	 * Bookings as CSV.
	 *
	 * @param array $filters BookingRepository filters.
	 * @return string
	 */
	public static function bookings_csv( array $filters ) {
		$rows = array();
		foreach ( BookingRepository::list_all( $filters ) as $b ) {
			$rows[] = array(
				$b['id'],
				Variables::status_label( (string) $b['status'] ),
				$b['service_name'],
				$b['agent_name'],
				$b['location_name'],
				$b['customer_name'],
				$b['customer_email'],
				$b['customer_phone'],
				$b['start_datetime'],
				$b['end_datetime'],
				number_format( (float) $b['total_price'], 2, '.', '' ),
				$b['currency'],
				$b['payment_method'],
				$b['payment_status'],
				$b['notes'],
				$b['created_at'],
			);
		}
		return self::csv(
			array(
				__( 'Booking ID', 'pointly-booking' ),
				__( 'Status', 'pointly-booking' ),
				__( 'Service', 'pointly-booking' ),
				__( 'Staff', 'pointly-booking' ),
				__( 'Location', 'pointly-booking' ),
				__( 'Customer', 'pointly-booking' ),
				__( 'Email', 'pointly-booking' ),
				__( 'Phone', 'pointly-booking' ),
				__( 'Start', 'pointly-booking' ),
				__( 'End', 'pointly-booking' ),
				__( 'Total', 'pointly-booking' ),
				__( 'Currency', 'pointly-booking' ),
				__( 'Payment method', 'pointly-booking' ),
				__( 'Payment status', 'pointly-booking' ),
				__( 'Notes', 'pointly-booking' ),
				__( 'Created', 'pointly-booking' ),
			),
			$rows
		);
	}

	/**
	 * Bookings as a printable HTML document (Print → Save as PDF).
	 *
	 * @param array $filters BookingRepository filters.
	 * @return string
	 */
	public static function bookings_print( array $filters ) {
		$bookings = BookingRepository::list_all( $filters );
		ob_start();
		include POINTLYBOOKING_PLUGIN_DIR . 'templates/admin/bookings-print.php';
		return (string) ob_get_clean();
	}

	/**
	 * Customers as CSV (same columns as 2.x plus bookings count).
	 *
	 * @return string
	 */
	public static function customers_csv() {
		$rows = array();
		foreach ( CustomerRepository::all_rows() as $c ) {
			$rows[] = array( $c['id'], $c['first_name'], $c['last_name'], $c['email'], $c['phone'], $c['wp_user_id'] ? $c['wp_user_id'] : '', $c['created_at'], $c['updated_at'] );
		}
		return self::csv( array( 'id', 'first_name', 'last_name', 'email', 'phone', 'wp_user_id', 'created_at', 'updated_at' ), $rows );
	}

	/**
	 * Imports customers from CSV text (columns: first_name,last_name,email,phone or name).
	 *
	 * @param string $csv CSV text.
	 * @return array{created:int,updated:int,skipped:int}
	 */
	public static function import_customers( $csv ) {
		$csv   = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $csv );
		$lines = preg_split( '/\r\n|\n|\r/', $csv );
		$rows  = array();
		foreach ( $lines as $line ) {
			if ( '' !== trim( $line ) ) {
				$rows[] = str_getcsv( $line );
			}
		}
		$result = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
		);
		if ( count( $rows ) < 2 ) {
			return $result;
		}
		$header = array_map(
			static function ( $h ) {
				return strtolower( trim( (string) $h ) );
			},
			array_shift( $rows )
		);
		$col    = array_flip( $header );
		$now    = Dates::now_mysql();

		foreach ( $rows as $row ) {
			$get   = static function ( $name ) use ( $row, $col ) {
				return isset( $col[ $name ], $row[ $col[ $name ] ] ) ? trim( (string) $row[ $col[ $name ] ] ) : '';
			};
			$first = Sanitize::text( $get( 'first_name' ), 190 );
			$last  = Sanitize::text( $get( 'last_name' ), 190 );
			if ( '' === $first && '' === $last && '' !== $get( 'name' ) ) {
				$parts = preg_split( '/\s+/', Sanitize::text( $get( 'name' ) ), 2 );
				$first = $parts[0] ?? '';
				$last  = $parts[1] ?? '';
			}
			$email = Sanitize::email( $get( 'email' ) );
			$phone = Sanitize::text( $get( 'phone' ), 50 );
			if ( '' === $email && '' === $first && '' === $last && '' === $phone ) {
				++$result['skipped'];
				continue;
			}
			$existing = '' !== $email ? CustomerRepository::find_by_email( $email ) : null;
			if ( $existing ) {
				CustomerRepository::update(
					$existing['id'],
					array_filter(
						array(
							'first_name' => $first,
							'last_name'  => $last,
							'phone'      => $phone,
						),
						'strlen'
					) + array( 'updated_at' => $now )
				);
				++$result['updated'];
				continue;
			}
			CustomerRepository::insert(
				array(
					'first_name' => $first,
					'last_name'  => $last,
					'email'      => $email,
					'phone'      => $phone,
					'created_at' => $now,
					'updated_at' => $now,
				)
			);
			++$result['created'];
		}
		return $result;
	}

	/**
	 * Audit log as CSV.
	 *
	 * @param array $filters Filters.
	 * @return string
	 */
	public static function audit_csv( array $filters ) {
		$data = AuditRepository::search( $filters, 1, 5000 );
		$rows = array();
		foreach ( $data['items'] as $r ) {
			$actor  = $r['actor_name'] ? $r['actor_name'] : ( trim( (string) $r['customer_name'] ) ? $r['customer_name'] : $r['actor_type'] );
			$rows[] = array( $r['id'], $r['created_at'], $r['event'], $r['actor_type'], $actor, $r['actor_ip'], $r['booking_id'] ? $r['booking_id'] : '', $r['customer_id'] ? $r['customer_id'] : '', (string) $r['meta'] );
		}
		return self::csv( array( 'id', 'created_at', 'event', 'actor_type', 'actor', 'actor_ip', 'booking_id', 'customer_id', 'meta' ), $rows );
	}

	/**
	 * Settings export (format compatible with 2.x imports).
	 *
	 * @return array
	 */
	public static function settings_export() {
		return array(
			'plugin'                  => 'pointly-booking',
			'version'                 => POINTLYBOOKING_VERSION,
			'exported_at'             => Dates::now_mysql(),
			'pointlybooking_settings' => Settings::legacy_rows(),
			'wp_options'              => array(
				'pointlybooking_settings'            => get_option( Settings::OPTION, array() ),
				'pointlybooking_booking_form_design' => get_option( Design::OPTION, null ),
			),
			'options'                 => array(
				'pointlybooking_remove_data_on_uninstall' => (int) get_option( 'pointlybooking_remove_data_on_uninstall', 0 ),
			),
		);
	}

	/**
	 * Imports a settings export (2.x or 3.x).
	 *
	 * @param mixed $data Decoded JSON.
	 * @return array|\WP_Error {applied:int}
	 */
	public static function settings_import( $data ) {
		if ( ! is_array( $data ) || ! in_array( (string) ( $data['plugin'] ?? '' ), array( 'bookpoint-booking', 'pointly-booking', 'bookpoint' ), true ) ) {
			return new \WP_Error( 'invalid_file', __( 'This file is not a BookPoint settings export.', 'pointly-booking' ), array( 'status' => 400 ) );
		}
		$applied = 0;
		$prefix  = array( 'pointlybooking_', 'payments_', 'stripe_', 'webhooks_', 'emails_', 'tpl_', 'booking_', 'portal_' );
		if ( isset( $data['pointlybooking_settings'] ) && is_array( $data['pointlybooking_settings'] ) ) {
			foreach ( $data['pointlybooking_settings'] as $key => $value ) {
				$key = sanitize_key( (string) $key );
				foreach ( $prefix as $p ) {
					if ( 0 === strpos( $key, $p ) ) {
						Settings::set_legacy( $key, is_scalar( $value ) ? sanitize_text_field( (string) $value ) : map_deep( $value, 'sanitize_text_field' ) );
						++$applied;
						break;
					}
				}
			}
		}
		$options = $data['wp_options'] ?? array();
		if ( isset( $options['pointlybooking_settings'] ) && is_array( $options['pointlybooking_settings'] ) ) {
			$clean = array();
			foreach ( $options['pointlybooking_settings'] as $key => $value ) {
				$key = sanitize_key( (string) $key );
				if ( '' === $key ) {
					continue;
				}
				$sanitized = Settings::sanitize( $key, $value );
				if ( is_wp_error( $sanitized ) ) {
					if ( 'unknown_setting' !== $sanitized->get_error_code() ) {
						continue;
					}
					$sanitized = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : map_deep( $value, 'sanitize_text_field' );
				}
				$clean[ $key ] = $sanitized;
				++$applied;
			}
			Settings::replace( $clean );
		}
		if ( isset( $options['pointlybooking_booking_form_design'] ) && is_array( $options['pointlybooking_booking_form_design'] ) ) {
			Design::save( $options['pointlybooking_booking_form_design'] );
			++$applied;
		}
		if ( isset( $data['options']['pointlybooking_remove_data_on_uninstall'] ) ) {
			update_option( 'pointlybooking_remove_data_on_uninstall', Sanitize::bool01( $data['options']['pointlybooking_remove_data_on_uninstall'] ), false );
		}
		Settings::reset_cache();
		return array( 'applied' => $applied );
	}
}
