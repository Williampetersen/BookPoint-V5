<?php
/**
 * Handlers for admin-post.php: downloads, uploads and the 2.x form posts.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Admin;

use PointlyBooking\Rest\Admin\BookingsController;
use PointlyBooking\Rest\Controller;
use PointlyBooking\Services\Transfer;
use PointlyBooking\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Every handler checks a nonce and a capability before doing anything.
 */
final class Actions {

	const NONCE = 'pointlybooking_admin';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register() {
		$own = array(
			'customers_export_csv'  => array( 'customers_export', 'pointlybooking_manage_customers' ),
			'customers_import_csv'  => array( 'customers_import', 'pointlybooking_manage_customers' ),
			'bookings_export_csv'   => array( 'bookings_export', 'pointlybooking_manage_bookings' ),
			'bookings_export_pdf'   => array( 'bookings_print', 'pointlybooking_manage_bookings' ),
			'settings_export_json'  => array( 'settings_export', 'pointlybooking_manage_tools' ),
			'tools_export_settings' => array( 'settings_export', 'pointlybooking_manage_tools' ),
			'settings_import_json'  => array( 'settings_import', 'pointlybooking_manage_tools' ),
			'tools_import_settings' => array( 'settings_import', 'pointlybooking_manage_tools' ),
			'audit_export_csv'      => array( 'audit_export', 'pointlybooking_manage_settings' ),
			'booking_notes_save'    => array( 'booking_notes', 'pointlybooking_manage_bookings' ),
			'booking_quick_update'  => array( 'booking_status', 'pointlybooking_manage_bookings' ),
			'customer_gdpr_delete'  => array( 'customer_gdpr', 'pointlybooking_manage_customers' ),
			'settings_save'         => array( 'settings_save', 'pointlybooking_manage_settings' ),
			'tools_email_test'      => array( 'tool_email', 'pointlybooking_manage_tools' ),
			'tools_webhook_test'    => array( 'tool_webhook', 'pointlybooking_manage_tools' ),
			'tools_generate_demo'   => array( 'tool_demo', 'pointlybooking_manage_tools' ),
			'services_save'         => array( 'services_save', 'pointlybooking_manage_services' ),
			'categories_save'       => array( 'categories_save', 'pointlybooking_manage_services' ),
			'extras_save'           => array( 'extras_save', 'pointlybooking_manage_services' ),
			'promo_codes_save'      => array( 'promo_save', 'pointlybooking_manage_settings' ),
			'form_fields_save'      => array( 'fields_save', 'pointlybooking_manage_settings' ),
			'agents_save'           => array( 'agents_save', 'pointlybooking_manage_agents' ),
		);
		foreach ( $own as $action => $def ) {
			add_action(
				'admin_post_pointlybooking_admin_' . $action,
				static function () use ( $action, $def ) {
					self::guard( 'pointlybooking_admin_' . $action, $def[1] );
					call_user_func( array( __CLASS__, $def[0] ) );
				}
			);
		}
	}

	/**
	 * Nonce + capability check (accepts the shared admin nonce or the action's own nonce).
	 *
	 * @param string $action Action name.
	 * @param string $cap    Capability.
	 * @return void
	 */
	private static function guard( $action, $cap ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonces are compared, never output.
		$nonce = isset( $_REQUEST['_wpnonce'] ) ? wp_unslash( $_REQUEST['_wpnonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) && ! wp_verify_nonce( $nonce, $action ) ) {
			wp_die( esc_html__( 'This link has expired. Please go back and try again.', 'pointly-booking' ), '', array( 'response' => 403 ) );
		}
		if ( ! current_user_can( $cap ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'pointly-booking' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Request parameters as a REST request (so existing validation is reused).
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route below the namespace.
	 * @param array  $params Parameters.
	 * @return \WP_REST_Response
	 */
	private static function dispatch( $method, $route, array $params ) {
		$request = new \WP_REST_Request( $method, '/' . Controller::NS . $route );
		$request->set_body_params( $params );
		$request->set_header( 'content-type', 'application/x-www-form-urlencoded' );
		return rest_do_request( $request );
	}

	/**
	 * Posted fields without WordPress' slashes and the routing keys.
	 *
	 * @return array
	 */
	private static function posted() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in guard().
		$data = wp_unslash( $_POST );
		unset( $data['_wpnonce'], $data['_wp_http_referer'], $data['action'] );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Redirects back with a result message.
	 *
	 * @param string $page    Page slug.
	 * @param array  $args    Query args.
	 * @param string $notice  Result code.
	 * @return void
	 */
	private static function back( $page, array $args, $notice ) {
		wp_safe_redirect( Menu::url( $page, array_merge( $args, array( 'pbk_notice' => $notice ) ) ) );
		exit;
	}

	/**
	 * Redirects back after a REST-backed save.
	 *
	 * @param \WP_REST_Response $response Response.
	 * @param string            $page     Page slug.
	 * @param array             $args     Query args.
	 * @return void
	 */
	private static function back_from( \WP_REST_Response $response, $page, array $args = array() ) {
		self::back( $page, $args, $response->is_error() ? 'error' : 'saved' );
	}

	/**
	 * Sends a download and stops.
	 *
	 * @param string $filename File name.
	 * @param string $mime     MIME type.
	 * @param string $content  Body.
	 * @return void
	 */
	private static function download( $filename, $mime, $content ) {
		nocache_headers();
		header( 'Content-Type: ' . $mime . '; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- File download (CSV/JSON), not HTML.
		echo $content;
		exit;
	}

	/**
	 * Uploaded file contents (≤ 5 MB).
	 *
	 * @param string $field Field name.
	 * @return string|\WP_Error
	 */
	private static function upload( $field ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified in guard().
		if ( empty( $_FILES[ $field ]['tmp_name'] ) || ! empty( $_FILES[ $field ]['error'] ) ) {
			return new \WP_Error( 'no_file', __( 'Please choose a file.', 'pointly-booking' ) );
		}
		$size = isset( $_FILES[ $field ]['size'] ) ? (int) $_FILES[ $field ]['size'] : 0;
		$tmp  = sanitize_text_field( wp_unslash( $_FILES[ $field ]['tmp_name'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( $size <= 0 || $size > 5 * MB_IN_BYTES || ! is_uploaded_file( $tmp ) ) {
			return new \WP_Error( 'bad_file', __( 'The file must be smaller than 5 MB.', 'pointly-booking' ) );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a just-uploaded temporary file.
		return (string) file_get_contents( $tmp );
	}

	/**
	 * Booking filters from the query string.
	 *
	 * @return array
	 */
	private static function booking_filters() {
		$request = new \WP_REST_Request( 'GET' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified in guard().
		$request->set_query_params( wp_unslash( $_GET ) );
		$filters = BookingsController::filters( $request );
		unset( $filters['page'], $filters['per_page'] );
		return $filters;
	}

	/**
	 * Customers CSV.
	 *
	 * @return void
	 */
	private static function customers_export() {
		self::download( 'customers-' . Dates::today() . '.csv', 'text/csv', Transfer::customers_csv() );
	}

	/**
	 * Customers CSV import.
	 *
	 * @return void
	 */
	private static function customers_import() {
		$csv = self::upload( 'file' );
		if ( is_wp_error( $csv ) ) {
			self::back( 'pointlybooking_customers', array(), 'import_error' );
		}
		$result = Transfer::import_customers( $csv );
		self::back(
			'pointlybooking_customers',
			array(
				'created' => (int) $result['created'],
				'updated' => (int) $result['updated'],
				'skipped' => (int) $result['skipped'],
			),
			'imported'
		);
	}

	/**
	 * Bookings CSV.
	 *
	 * @return void
	 */
	private static function bookings_export() {
		self::download( 'bookings-' . Dates::today() . '.csv', 'text/csv', Transfer::bookings_csv( self::booking_filters() ) );
	}

	/**
	 * Printable bookings report ("PDF" = the browser's print-to-PDF).
	 *
	 * @return void
	 */
	private static function bookings_print() {
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template output, escaped inside templates/admin/bookings-print.php.
		echo Transfer::bookings_print( self::booking_filters() );
		exit;
	}

	/**
	 * Settings JSON export.
	 *
	 * @return void
	 */
	private static function settings_export() {
		self::download( 'bookpoint-settings-' . Dates::today() . '.json', 'application/json', (string) wp_json_encode( Transfer::settings_export(), JSON_PRETTY_PRINT ) );
	}

	/**
	 * Settings JSON import.
	 *
	 * @return void
	 */
	private static function settings_import() {
		$json = self::upload( 'file' );
		if ( is_wp_error( $json ) ) {
			self::back( 'pointlybooking_settings', array( 'tab' => 'tools' ), 'import_error' );
		}
		$result = Transfer::settings_import( json_decode( $json, true ) );
		self::back( 'pointlybooking_settings', array( 'tab' => 'tools' ), is_wp_error( $result ) ? 'import_error' : 'imported' );
	}

	/**
	 * Activity log CSV.
	 *
	 * @return void
	 */
	private static function audit_export() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified in guard().
		$get = wp_unslash( $_GET );
		self::download(
			'activity-' . Dates::today() . '.csv',
			'text/csv',
			Transfer::audit_csv(
				array(
					'search'     => sanitize_text_field( $get['search'] ?? '' ),
					'event'      => sanitize_text_field( $get['event'] ?? '' ),
					'actor_type' => sanitize_key( $get['actor_type'] ?? '' ),
					'date_from'  => sanitize_text_field( $get['date_from'] ?? '' ),
					'date_to'    => sanitize_text_field( $get['date_to'] ?? '' ),
				)
			)
		);
	}

	/**
	 * 2.x: save booking notes.
	 *
	 * @return void
	 */
	private static function booking_notes() {
		$data = self::posted();
		$id   = absint( $data['booking_id'] ?? ( $data['id'] ?? 0 ) );
		self::back_from(
			self::dispatch( 'PATCH', '/admin/bookings/' . $id, array( 'notes' => (string) ( $data['notes'] ?? '' ) ) ),
			'pointlybooking_bookings',
			array(
				'view' => 'edit',
				'id'   => $id,
			)
		);
	}

	/**
	 * 2.x: quick status change.
	 *
	 * @return void
	 */
	private static function booking_status() {
		$data = self::posted();
		$id   = absint( $data['booking_id'] ?? ( $data['id'] ?? 0 ) );
		self::back_from( self::dispatch( 'PATCH', '/admin/bookings/' . $id, array( 'status' => sanitize_key( $data['status'] ?? '' ) ) ), 'pointlybooking_bookings' );
	}

	/**
	 * 2.x: GDPR erase of a customer.
	 *
	 * @return void
	 */
	private static function customer_gdpr() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified in guard().
		$id = absint( wp_unslash( $_REQUEST['customer_id'] ?? ( $_REQUEST['id'] ?? 0 ) ) );
		self::back_from( self::dispatch( 'POST', '/admin/customers/' . $id . '/anonymize', array() ), 'pointlybooking_customers' );
	}

	/**
	 * 2.x: settings form.
	 *
	 * @return void
	 */
	private static function settings_save() {
		$data = self::posted();
		self::back_from( self::dispatch( 'POST', '/admin/settings', isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : $data ), 'pointlybooking_settings' );
	}

	/**
	 * 2.x: test email.
	 *
	 * @return void
	 */
	private static function tool_email() {
		self::back_from( self::dispatch( 'POST', '/admin/tools/run/email_test', self::posted() ), 'pointlybooking_settings', array( 'tab' => 'tools' ) );
	}

	/**
	 * 2.x: test webhook.
	 *
	 * @return void
	 */
	private static function tool_webhook() {
		self::back_from( self::dispatch( 'POST', '/admin/tools/run/webhook_test', self::posted() ), 'pointlybooking_settings', array( 'tab' => 'tools' ) );
	}

	/**
	 * 2.x: demo data.
	 *
	 * @return void
	 */
	private static function tool_demo() {
		self::back_from( self::dispatch( 'POST', '/admin/tools/run/generate_demo', self::posted() ), 'pointlybooking_settings', array( 'tab' => 'tools' ) );
	}

	/**
	 * Saves an entity through its REST route (create or update by "id").
	 *
	 * @param string $route Collection route.
	 * @param string $page  Page to return to.
	 * @param array  $args  Query args.
	 * @return void
	 */
	private static function save_entity( $route, $page, array $args = array() ) {
		$data = self::posted();
		$id   = absint( $data['id'] ?? 0 );
		unset( $data['id'] );
		self::back_from( self::dispatch( $id ? 'PUT' : 'POST', $route . ( $id ? '/' . $id : '' ), $data ), $page, $args );
	}

	/**
	 * 2.x: service form.
	 *
	 * @return void
	 */
	private static function services_save() {
		self::save_entity( '/admin/services', 'pointlybooking_services' );
	}

	/**
	 * 2.x: category form.
	 *
	 * @return void
	 */
	private static function categories_save() {
		self::save_entity( '/admin/categories', 'pointlybooking_services', array( 'tab' => 'categories' ) );
	}

	/**
	 * 2.x: extra form.
	 *
	 * @return void
	 */
	private static function extras_save() {
		self::save_entity( '/admin/extras', 'pointlybooking_services', array( 'tab' => 'extras' ) );
	}

	/**
	 * 2.x: promo code form.
	 *
	 * @return void
	 */
	private static function promo_save() {
		self::save_entity( '/admin/promo-codes', 'pointlybooking_settings', array( 'tab' => 'promo_codes' ) );
	}

	/**
	 * 2.x: form field form.
	 *
	 * @return void
	 */
	private static function fields_save() {
		self::save_entity( '/admin/form-fields', 'pointlybooking_settings', array( 'tab' => 'form_fields' ) );
	}

	/**
	 * 2.x: staff form.
	 *
	 * @return void
	 */
	private static function agents_save() {
		self::save_entity( '/admin/agents', 'pointlybooking_agents' );
	}
}
