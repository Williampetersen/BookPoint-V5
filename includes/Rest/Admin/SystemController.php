<?php
/**
 * Admin audit log and tools API.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Rest\Admin;

use PointlyBooking\Database\Migrator;
use PointlyBooking\Database\Tables;
use PointlyBooking\Installer;
use PointlyBooking\Repositories\AgentRepository;
use PointlyBooking\Repositories\AuditRepository;
use PointlyBooking\Repositories\BookingRepository;
use PointlyBooking\Repositories\CustomerRepository;
use PointlyBooking\Repositories\ServiceRepository;
use PointlyBooking\Rest\Controller;
use PointlyBooking\Services\Audit;
use PointlyBooking\Services\DemoData;
use PointlyBooking\Services\Notifications\Mailer;
use PointlyBooking\Services\Payments\PaymentMethods;
use PointlyBooking\Services\Transfer;
use PointlyBooking\Services\Webhooks;
use PointlyBooking\Settings\Settings;
use PointlyBooking\Support\Cache;
use PointlyBooking\Support\Dates;
use PointlyBooking\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * /admin/audit-logs…, /admin/tools/…
 */
final class SystemController extends Controller {

	/**
	 * {@inheritDoc}
	 */
	public function register_routes() {
		$audit = self::cap( 'pointlybooking_manage_settings', 'pointlybooking_manage_tools' );
		$tools = self::cap( 'pointlybooking_manage_tools' );

		$filters = array_merge(
			self::paging_args(),
			array(
				'q'                => self::arg( 'string' ),
				'event'            => self::arg( 'string' ),
				'actor_type'       => self::arg( 'key' ),
				'actor_wp_user_id' => self::arg( 'id' ),
				'booking_id'       => self::arg( 'id' ),
				'customer_id'      => self::arg( 'id' ),
				'date_from'        => self::arg( 'string' ),
				'date_to'          => self::arg( 'string' ),
			)
		);

		$this->route(
			'/admin/audit-logs',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'audit' ),
				'permission_callback' => $audit,
				'args'                => $filters,
			)
		);
		$this->route(
			'/admin/audit-logs/meta',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'audit_meta' ),
				'permission_callback' => $audit,
			)
		);
		$this->route(
			'/admin/audit-logs/clear',
			array(
				'methods'             => array( 'POST', 'DELETE' ),
				'callback'            => array( $this, 'audit_clear' ),
				'permission_callback' => $tools,
			)
		);
		$this->route(
			'/admin/audit-logs/export',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'audit_export' ),
				'permission_callback' => $audit,
				'args'                => $filters,
			)
		);

		$this->route(
			'/admin/tools/status',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'status' ),
				'permission_callback' => $tools,
			)
		);
		$this->route(
			'/admin/tools/run/(?P<action>[a-z0-9_-]+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'run' ),
				'permission_callback' => $tools,
			)
		);
		$this->route(
			'/admin/tools/report',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'report' ),
				'permission_callback' => $tools,
			)
		);
		$this->route(
			'/admin/tools/export-settings',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'export_settings' ),
				'permission_callback' => $tools,
			)
		);
		$this->route(
			'/admin/tools/import-settings',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'import_settings' ),
				'permission_callback' => $tools,
			)
		);
	}

	/**
	 * Audit filters from a request.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return array
	 */
	private static function audit_filters( \WP_REST_Request $r ) {
		return array(
			'search'           => (string) ( $r->get_param( 'search' ) ?? $r->get_param( 'q' ) ?? '' ),
			'event'            => (string) $r->get_param( 'event' ),
			'actor_type'       => (string) $r->get_param( 'actor_type' ),
			'actor_wp_user_id' => absint( $r->get_param( 'actor_wp_user_id' ) ),
			'booking_id'       => absint( $r->get_param( 'booking_id' ) ),
			'customer_id'      => absint( $r->get_param( 'customer_id' ) ),
			'date_from'        => Sanitize::date( $r->get_param( 'date_from' ) ),
			'date_to'          => Sanitize::date( $r->get_param( 'date_to' ) ),
		);
	}

	/**
	 * Audit entries.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function audit( \WP_REST_Request $r ) {
		$page     = max( 1, (int) $r->get_param( 'page' ) );
		$per_page = max( 1, (int) ( $r->get_param( 'per_page' ) ? $r->get_param( 'per_page' ) : 50 ) );
		$result   = AuditRepository::search( self::audit_filters( $r ), $page, $per_page );
		return $this->ok(
			array(
				'items'    => $result['items'],
				'total'    => $result['total'],
				'page'     => $page,
				'per_page' => $per_page,
			)
		);
	}

	/**
	 * Filter options.
	 *
	 * @return \WP_REST_Response
	 */
	public function audit_meta() {
		return $this->ok(
			array(
				'events'      => AuditRepository::events(),
				'actor_types' => array( 'admin', 'customer', 'system' ),
			)
		);
	}

	/**
	 * Clears the log.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function audit_clear() {
		if ( ! AuditRepository::clear() ) {
			return $this->error( 'clear_failed', __( 'The activity log could not be cleared.', 'pointly-booking' ), 500 );
		}
		Audit::log( 'audit_cleared' );
		return $this->ok( array( 'cleared' => true ) );
	}

	/**
	 * CSV export (returned as text so the admin app can download it).
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function audit_export( \WP_REST_Request $r ) {
		return $this->ok(
			array(
				'filename' => 'pointly-booking-activity-' . Dates::today() . '.csv',
				'mime'     => 'text/csv',
				'content'  => Transfer::audit_csv( self::audit_filters( $r ) ),
			)
		);
	}

	/**
	 * Health information.
	 *
	 * @return \WP_REST_Response
	 */
	public function status() {
		global $wpdb;
		$tables = array();
		$ok     = 0;
		foreach ( Tables::ALL as $table ) {
			$exists           = Tables::exists( $table );
			$tables[ $table ] = $exists;
			$ok              += $exists ? 1 : 0;
		}
		$next = wp_next_scheduled( Installer::CRON_CLEANUP );
		return $this->ok(
			array(
				'tables'          => $tables,
				'tables_ok_count' => $ok,
				'tables_total'    => count( Tables::ALL ),
				'db_version'      => (string) get_option( Migrator::OPTION, '' ),
				'db_expected'     => Migrator::DB_VERSION,
				'plugin_version'  => POINTLYBOOKING_VERSION,
				'upgraded_from'   => (string) get_option( 'pointlybooking_upgraded_from', '' ),
				'php_version'     => PHP_VERSION,
				'wp_version'      => get_bloginfo( 'version' ),
				'mysql_version'   => (string) $wpdb->db_version(),
				'timezone'        => wp_timezone_string(),
				'now'             => Dates::now_mysql(),
				'cron_cleanup'    => $next ? gmdate( 'c', $next ) : '',
				'wp_cron'         => ! ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ),
				'emails_enabled'  => Mailer::enabled(),
				'counts'          => array(
					'services'  => ServiceRepository::count_all(),
					'agents'    => AgentRepository::count_all(),
					'bookings'  => BookingRepository::count_all(),
					'customers' => CustomerRepository::count_created( '1970-01-01 00:00:00', '9999-12-31 23:59:59' ),
				),
			)
		);
	}

	/**
	 * Runs a maintenance action.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function run( \WP_REST_Request $r ) {
		$action = str_replace( '-', '_', (string) $r['action'] );
		$body   = $this->body( $r );

		switch ( $action ) {
			case 'sync_relations':
				$result = Migrator::sync_relations();
				Audit::log( 'tools_sync_relations', array( 'meta' => $result ) );
				return $this->message( __( 'Relations synced.', 'pointly-booking' ), $result );

			case 'generate_demo':
				$result = DemoData::generate(
					$body['services'] ?? 4,
					$body['agents'] ?? 3,
					$body['customers'] ?? 20,
					$body['bookings'] ?? 40
				);
				Audit::log( 'tools_demo_generated', array( 'meta' => $result ) );
				return $this->message( __( 'Demo data generated.', 'pointly-booking' ), $result );

			case 'reset_cache':
				Cache::flush_all();
				Settings::reset_cache();
				Audit::log( 'tools_cache_reset' );
				return $this->message( __( 'Cache cleared.', 'pointly-booking' ) );

			case 'run_migrations':
				delete_transient( 'pointlybooking_migrating' );
				Migrator::upgrade( (string) get_option( Migrator::OPTION, '' ) );
				Audit::log( 'tools_migrations_run' );
				return $this->message( __( 'Database checked and updated.', 'pointly-booking' ) );

			case 'email_test':
				$to = Sanitize::email( $body['to'] ?? wp_get_current_user()->user_email );
				if ( '' === $to ) {
					return $this->error( 'invalid_email', __( 'Please enter a valid email address.', 'pointly-booking' ), 400, array( 'field' => 'to' ) );
				}
				$sent = Mailer::send(
					$to,
					__( 'Test email from your booking system', 'pointly-booking' ),
					'<p>' . esc_html__( 'Good news: your site can send booking emails.', 'pointly-booking' ) . '</p>'
				);
				Audit::log(
					'tools_email_test',
					array(
						'meta' => array(
							'to' => $to,
							'ok' => $sent,
						),
					)
				);
				if ( ! $sent ) {
					return $this->error( 'send_failed', __( 'The email could not be sent. Check your site’s email (SMTP) configuration.', 'pointly-booking' ), 500 );
				}
				return $this->message(
					/* translators: %s: email address */
					sprintf( __( 'Test email sent to %s.', 'pointly-booking' ), $to ),
					array(
						'ok' => true,
						'to' => $to,
					)
				);

			case 'webhook_test':
				$event    = Sanitize::one_of( $body['event'] ?? 'booking_created', array( 'booking_created', 'booking_status_changed', 'booking_updated', 'booking_cancelled' ), 'booking_created' );
				$response = Webhooks::fire(
					$event,
					array(
						'test'       => true,
						'booking_id' => 0,
						'status'     => 'test',
					),
					true
				);
				Audit::log( 'tools_webhook_test', array( 'meta' => array( 'event' => $event ) ) );
				if ( is_wp_error( $response ) ) {
					return $this->error( 'webhook_failed', $response->get_error_message(), 400 );
				}
				$code = (int) wp_remote_retrieve_response_code( $response );
				if ( $code < 200 || $code >= 300 ) {
					return $this->error(
						'webhook_failed',
						/* translators: %d: HTTP status code */
						sprintf( __( 'The webhook URL answered with HTTP %d.', 'pointly-booking' ), $code ),
						400
					);
				}
				return $this->message(
					/* translators: %d: HTTP status code */
					sprintf( __( 'Webhook delivered (HTTP %d).', 'pointly-booking' ), $code ),
					array(
						'event' => $event,
						'code'  => $code,
					)
				);
		}
		return $this->error( 'unknown_action', __( 'Unknown tool action.', 'pointly-booking' ) );
	}

	/**
	 * Success with a message (2.x shape).
	 *
	 * @param string $message Message.
	 * @param mixed  $data    Data.
	 * @return \WP_REST_Response
	 */
	private function message( $message, $data = null ) {
		return new \WP_REST_Response(
			array(
				'status'  => 'success',
				'message' => $message,
				'data'    => $data,
			),
			200
		);
	}

	/**
	 * Support report (no secrets).
	 *
	 * @return \WP_REST_Response
	 */
	public function report() {
		$status = $this->status()->get_data()['data'];
		$theme  = wp_get_theme();
		$active = array();
		foreach ( (array) get_option( 'active_plugins', array() ) as $plugin ) {
			$active[] = (string) $plugin;
		}
		$settings = Settings::all( false );
		foreach ( array_keys( $settings ) as $key ) {
			if ( Settings::is_payment_key( $key ) || 0 === strpos( $key, 'webhooks_' ) ) {
				unset( $settings[ $key ] );
			}
		}
		return $this->ok(
			array_merge(
				$status,
				array(
					'generated_at' => gmdate( 'c' ),
					'site_url'     => home_url(),
					'multisite'    => is_multisite(),
					'theme'        => $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ),
					'plugins'      => $active,
					'payments'     => array(
						'enabled'    => PaymentMethods::enabled(),
						'methods'    => PaymentMethods::selected(),
						'configured' => array_values( array_filter( PaymentMethods::ALL, array( PaymentMethods::class, 'is_configured' ) ) ),
					),
					'settings'     => $settings,
				)
			)
		);
	}

	/**
	 * Settings export (JSON document).
	 *
	 * @return \WP_REST_Response
	 */
	public function export_settings() {
		return $this->ok( Transfer::settings_export() );
	}

	/**
	 * Settings import.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function import_settings( \WP_REST_Request $r ) {
		$body = $this->body( $r );
		$data = $body['data'] ?? ( $body['json'] ?? $body );
		if ( is_string( $data ) ) {
			$data = json_decode( $data, true );
		}
		$result = Transfer::settings_import( $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		Audit::log( 'tools_settings_imported', array( 'meta' => $result ) );
		return $this->message( __( 'Settings imported.', 'pointly-booking' ), $result );
	}
}
