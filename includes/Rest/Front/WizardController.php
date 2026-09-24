<?php
/**
 * Public booking wizard API.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Rest\Front;

use PointlyBooking\Repositories\AgentRepository;
use PointlyBooking\Repositories\BookingRepository;
use PointlyBooking\Repositories\CategoryRepository;
use PointlyBooking\Repositories\ExtraRepository;
use PointlyBooking\Repositories\FormFieldRepository;
use PointlyBooking\Repositories\LocationRepository;
use PointlyBooking\Repositories\Relations;
use PointlyBooking\Repositories\ServiceRepository;
use PointlyBooking\Rest\Controller;
use PointlyBooking\Rest\Presenter;
use PointlyBooking\Services\Availability\AvailabilityService;
use PointlyBooking\Services\Booking\BookingService;
use PointlyBooking\Services\Notifications\Ics;
use PointlyBooking\Services\Payments\PaymentFlow;
use PointlyBooking\Services\Payments\PaymentMethods;
use PointlyBooking\Services\Payments\PayPal;
use PointlyBooking\Services\Payments\Stripe;
use PointlyBooking\Services\Pricing\PricingService;
use PointlyBooking\Settings\Design;
use PointlyBooking\Settings\Settings;
use PointlyBooking\Support\Cache;
use PointlyBooking\Support\Dates;
use PointlyBooking\Support\Money;
use PointlyBooking\Support\RateLimiter;
use PointlyBooking\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * /wizard/…
 */
final class WizardController extends Controller {

	/**
	 * {@inheritDoc}
	 */
	public function register_routes() {
		$public = array( Controller::class, 'public_access' );
		$scope  = array(
			'service_id'  => self::arg( 'id', true ),
			'agent_id'    => self::arg( 'id' ),
			'location_id' => self::arg( 'id' ),
		);
		$key    = array( 'key' => self::arg( 'string', true, array( 'pattern' => '^[a-fA-F0-9]{40}([a-fA-F0-9]{24})?$' ) ) );

		$this->route(
			'/wizard/bootstrap',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'bootstrap' ),
				'permission_callback' => $public,
			)
		);
		$this->route(
			'/wizard/agents',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'agents' ),
				'permission_callback' => $public,
				'args'                => array(
					'service_id'  => self::arg( 'id', true ),
					'location_id' => self::arg( 'id' ),
				),
			)
		);
		$this->route(
			'/wizard/availability/month',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'month' ),
				'permission_callback' => $public,
				'args'                => array_merge( $scope, array( 'month' => self::arg( 'month' ) ) ),
			)
		);
		$this->route(
			'/wizard/availability/day',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'day' ),
				'permission_callback' => $public,
				'args'                => array_merge( $scope, array( 'date' => self::arg( 'date', true ) ) ),
			)
		);
		$this->route(
			'/wizard/quote',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( $this, 'quote' ),
				'permission_callback' => $public,
				'args'                => array(
					'service_id' => self::arg( 'id', true ),
					'extras'     => self::arg( 'ids' ),
					'promo_code' => self::arg( 'string' ),
				),
			)
		);
		$this->route(
			'/wizard/bookings',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create' ),
				'permission_callback' => $public,
				'args'                => array(
					'service_id'     => self::arg( 'id', true ),
					'agent_id'       => self::arg( 'id' ),
					'location_id'    => self::arg( 'id' ),
					'date'           => self::arg( 'date', true ),
					'start'          => self::arg( 'time', true ),
					'extras'         => self::arg( 'ids' ),
					'promo_code'     => self::arg( 'string' ),
					'payment_method' => self::arg( 'key' ),
					'fields'         => self::arg( 'object' ),
					'return_url'     => self::arg( 'string' ),
				),
			)
		);
		$this->route(
			'/wizard/bookings/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'status' ),
				'permission_callback' => $public,
				'args'                => $key,
			)
		);
		$this->route(
			'/wizard/bookings/(?P<id>\d+)/pay',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'pay' ),
				'permission_callback' => $public,
				'args'                => array_merge( $key, array( 'return_url' => self::arg( 'string' ) ) ),
			)
		);
		$this->route(
			'/wizard/bookings/(?P<id>\d+)/confirm-payment',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'confirm_payment' ),
				'permission_callback' => $public,
				'args'                => array_merge( $key, array( 'payment_intent_id' => self::arg( 'string', true ) ) ),
			)
		);
		$this->route(
			'/wizard/bookings/(?P<id>\d+)/paypal-capture',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'paypal_capture' ),
				'permission_callback' => $public,
				'args'                => array_merge( $key, array( 'order_id' => self::arg( 'string', true ) ) ),
			)
		);
		$this->route(
			'/wizard/bookings/(?P<id>\d+)/cancel-payment',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'cancel_payment' ),
				'permission_callback' => $public,
				'args'                => $key,
			)
		);
		$this->route(
			'/wizard/bookings/(?P<id>\d+)/ics',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'ics' ),
				'permission_callback' => $public,
				'args'                => $key,
			)
		);
	}

	/**
	 * Throttles an action per IP.
	 *
	 * @param string $bucket Bucket.
	 * @param int    $limit  Hits.
	 * @param int    $window Seconds.
	 * @return \WP_Error|null
	 */
	public static function throttle( $bucket, $limit, $window ) {
		if ( RateLimiter::hit( $bucket, $limit, $window ) ) {
			return null;
		}
		return new \WP_Error( 'rate_limited', __( 'Too many requests. Please wait a moment and try again.', 'pointly-booking' ), array( 'status' => 429 ) );
	}

	/**
	 * Booking by ID + manage key (constant-time comparison).
	 *
	 * @param int    $id  Booking ID.
	 * @param string $key Manage key.
	 * @return array|\WP_Error
	 */
	public static function booking_for_key( $id, $key ) {
		$booking = BookingRepository::find( (int) $id );
		if ( ! $booking || '' === (string) $key || ! hash_equals( strtolower( (string) $booking['manage_key'] ), strtolower( (string) $key ) ) ) {
			return new \WP_Error( 'not_found', __( 'We could not find this booking. Please use the link from your confirmation email.', 'pointly-booking' ), array( 'status' => 404 ) );
		}
		return $booking;
	}

	/**
	 * Everything the wizard needs in one cached response.
	 *
	 * @return \WP_REST_Response
	 */
	public function bootstrap() {
		$data = Cache::remember(
			'catalog',
			'wizard_bootstrap:' . determine_locale(),
			static function () {
				$services      = ServiceRepository::all( true );
				$service_ids   = wp_list_pluck( $services, 'id' );
				$category_map  = Relations::map( 'service_categories', 'service_id', $service_ids );
				$agent_map     = Relations::map( 'agent_services', 'service_id', $service_ids );
				$agents_active = AgentRepository::all( true );
				$active_ids    = array_map( 'intval', wp_list_pluck( $agents_active, 'id' ) );

				$service_rows = array();
				foreach ( $services as $s ) {
					$row              = Presenter::service_public( $s, $category_map[ $s['id'] ] ?? array() );
					$linked           = array_values( array_intersect( $agent_map[ $s['id'] ] ?? array(), $active_ids ) );
					$row['agent_ids'] = $linked ? $linked : $active_ids;
					$service_rows[]   = $row;
				}

				$extras      = ExtraRepository::all( true );
				$extra_map   = Relations::map( 'extra_services', 'extra_id', wp_list_pluck( $extras, 'id' ) );
				$extra_rows  = array();
				foreach ( $extras as $e ) {
					$links = $extra_map[ $e['id'] ] ?? array();
					if ( ! $links && ! empty( $e['service_id'] ) ) {
						$links = array( (int) $e['service_id'] );
					}
					$extra_rows[] = array(
						'id'          => (int) $e['id'],
						'name'        => (string) $e['name'],
						'description' => wp_strip_all_tags( (string) $e['description'] ),
						'price'       => (float) $e['price'],
						'duration'    => (int) $e['duration_min'],
						'image_url'   => ExtraRepository::image_url( $e['image_id'], 'medium' ),
						'service_ids' => array_values( $links ),
					);
				}

				$categories = array();
				foreach ( CategoryRepository::all( true ) as $c ) {
					$categories[] = array(
						'id'          => (int) $c['id'],
						'name'        => (string) $c['name'],
						'description' => wp_strip_all_tags( (string) $c['description'] ),
						'image_url'   => CategoryRepository::image_url( $c['image_id'], 'medium' ),
					);
				}

				$assignments = LocationRepository::all_assignments();
				$locations   = array();
				foreach ( LocationRepository::all( true ) as $l ) {
					$locations[] = array(
						'id'          => (int) $l['id'],
						'name'        => (string) $l['name'],
						'address'     => (string) ( $l['address'] ?? '' ),
						'image_url'   => LocationRepository::image_url( $l['image_id'], 'medium' ),
						'assignments' => $assignments[ $l['id'] ] ?? array(),
					);
				}

				return array(
					'services'   => $service_rows,
					'categories' => $categories,
					'extras'     => $extra_rows,
					'agents'     => array_map( array( Presenter::class, 'agent_public' ), $agents_active ),
					'locations'  => $locations,
					'fields'     => FormFieldRepository::wizard_fields(),
				);
			},
			HOUR_IN_SECONDS
		);

		$data['design']   = Design::get();
		$data['payment']  = PaymentMethods::public_config();
		$data['settings'] = array(
			'currency'         => Money::currency(),
			'currency_symbol'  => Money::symbol(),
			'currency_pos'     => (string) Settings::get( 'currency_position', 'before' ),
			'timezone'         => wp_timezone_string(),
			'timezone_label'   => self::timezone_label(),
			'today'            => Dates::today(),
			'last_date'        => AvailabilityService::last_date(),
			'week_starts'      => (int) get_option( 'start_of_week', 1 ),
			'time_format'      => (string) get_option( 'time_format', 'H:i' ),
			'date_format'      => (string) get_option( 'date_format', 'F j, Y' ),
			'business_name'    => (string) Settings::get( 'business_name', '' ),
			'business_phone'   => (string) Settings::get( 'business_phone', '' ),
			'no_staff'         => ! $data['agents'],
			'locale'           => str_replace( '_', '-', determine_locale() ),
			'promo_enabled'    => ! empty( $data['design']['behavior']['showPromoCode'] ),
		);
		return $this->ok( $data );
	}

	/**
	 * Human readable site time zone (e.g. "Europe/Copenhagen (UTC+02:00)").
	 *
	 * @return string
	 */
	public static function timezone_label() {
		$tz     = wp_timezone();
		$offset = $tz->getOffset( new \DateTimeImmutable( 'now', $tz ) );
		$sign   = $offset < 0 ? '-' : '+';
		$offset = abs( $offset );
		$utc    = sprintf( 'UTC%s%02d:%02d', $sign, intdiv( $offset, 3600 ), intdiv( $offset % 3600, 60 ) );
		$name   = wp_timezone_string();
		return 0 === strpos( $name, '+' ) || 0 === strpos( $name, '-' ) ? $utc : str_replace( '_', ' ', $name ) . ' (' . $utc . ')';
	}

	/**
	 * Staff who can perform a service (optionally at a location).
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function agents( \WP_REST_Request $r ) {
		$ids    = AvailabilityService::candidate_agents( (int) $r->get_param( 'service_id' ), (int) $r->get_param( 'location_id' ) );
		$agents = AgentRepository::by_id( true );
		$out    = array();
		foreach ( $ids as $id ) {
			if ( isset( $agents[ $id ] ) ) {
				$out[] = Presenter::agent_public( $agents[ $id ] );
			}
		}
		return $this->ok( $out );
	}

	/**
	 * Validates the service/staff/location scope of an availability request.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return array|\WP_Error
	 */
	private function scope( \WP_REST_Request $r ) {
		$service = ServiceRepository::find( (int) $r->get_param( 'service_id' ) );
		if ( ! $service || ! $service['is_active'] ) {
			return $this->error( 'service_not_found', __( 'This service is no longer available.', 'pointly-booking' ), 404 );
		}
		return array(
			'service_id'  => (int) $service['id'],
			'agent_id'    => (int) $r->get_param( 'agent_id' ),
			'location_id' => (int) $r->get_param( 'location_id' ),
		);
	}

	/**
	 * Days with free times in a month (plus the first bookable date).
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function month( \WP_REST_Request $r ) {
		$scope = $this->scope( $r );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}
		$month = (string) $r->get_param( 'month' );
		if ( '' === $month ) {
			$first = AvailabilityService::first_available( $scope['service_id'], $scope['agent_id'], $scope['location_id'] );
			$month = $first ? substr( $first, 0, 7 ) : substr( Dates::today(), 0, 7 );
		}
		$data = AvailabilityService::month( $scope['service_id'], $scope['agent_id'], $scope['location_id'], $month );
		if ( null === $data['first_available'] && $month === substr( Dates::today(), 0, 7 ) ) {
			$data['next_available'] = AvailabilityService::first_available( $scope['service_id'], $scope['agent_id'], $scope['location_id'], Dates::add_days( $month . '-01', 31 ) );
		}
		return $this->ok( $data );
	}

	/**
	 * Free times of one day, without revealing staff assignments.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function day( \WP_REST_Request $r ) {
		$scope = $this->scope( $r );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}
		$data = AvailabilityService::day( array_merge( $scope, array( 'date' => (string) $r->get_param( 'date' ) ) ) );
		foreach ( $data['slots'] as &$slot ) {
			$slot['available'] = count( $slot['agents'] );
			unset( $slot['agents'] );
		}
		unset( $slot );
		return $this->ok( $data );
	}

	/**
	 * Price breakdown (service + extras − promo).
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function quote( \WP_REST_Request $r ) {
		$limited = self::throttle( 'wizard_quote', 60, 600 );
		if ( $limited ) {
			return $limited;
		}
		$quote = PricingService::quote( (int) $r->get_param( 'service_id' ), Sanitize::ids( $r->get_param( 'extras' ) ), (string) $r->get_param( 'promo_code' ) );
		if ( is_wp_error( $quote ) ) {
			return $quote;
		}
		unset( $quote['promo_id'] );
		return $this->ok( $quote );
	}

	/**
	 * Creates a booking and returns the next payment step.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create( \WP_REST_Request $r ) {
		// Honeypot: real visitors never fill this hidden field.
		if ( '' !== trim( (string) $r->get_param( 'website' ) ) ) {
			return $this->error( 'rejected', __( 'Your booking could not be submitted.', 'pointly-booking' ), 400 );
		}
		$limited = self::throttle( 'wizard_create', 10, 600 );
		if ( $limited ) {
			return $limited;
		}
		$fields  = $r->get_param( 'fields' );
		$booking = BookingService::create(
			array(
				'service_id'     => (int) $r->get_param( 'service_id' ),
				'agent_id'       => (int) $r->get_param( 'agent_id' ),
				'location_id'    => (int) $r->get_param( 'location_id' ),
				'date'           => (string) $r->get_param( 'date' ),
				'start'          => (string) $r->get_param( 'start' ),
				'extras'         => Sanitize::ids( $r->get_param( 'extras' ) ),
				'promo_code'     => (string) $r->get_param( 'promo_code' ),
				'payment_method' => (string) $r->get_param( 'payment_method' ),
				'fields'         => is_array( $fields ) ? $fields : array(),
			),
			array( 'source' => 'wizard' )
		);
		if ( is_wp_error( $booking ) ) {
			return $booking;
		}
		$joined  = BookingRepository::find_joined( $booking['id'] );
		$payment = PaymentFlow::start( $booking, (string) $r->get_param( 'return_url' ) );
		if ( is_wp_error( $payment ) ) {
			// The booking exists; the customer can retry payment from the confirmation screen.
			$payment = array(
				'type'  => 'error',
				'error' => $payment->get_error_message(),
			);
		}
		return $this->ok(
			array(
				'booking' => Presenter::booking_public( $joined ? $joined : $booking ),
				'payment' => $payment,
			),
			201
		);
	}

	/**
	 * Booking status (polled after returning from a payment provider).
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function status( \WP_REST_Request $r ) {
		$limited = self::throttle( 'wizard_status', 120, 600 );
		if ( $limited ) {
			return $limited;
		}
		$booking = self::booking_for_key( (int) $r['id'], (string) $r->get_param( 'key' ) );
		if ( is_wp_error( $booking ) ) {
			return $booking;
		}
		PaymentFlow::refresh( $booking );
		return $this->ok( Presenter::booking_public( BookingRepository::find_joined( $booking['id'] ) ) );
	}

	/**
	 * Starts (or retries) the online payment of a booking.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function pay( \WP_REST_Request $r ) {
		$limited = self::throttle( 'wizard_pay', 20, 600 );
		if ( $limited ) {
			return $limited;
		}
		$booking = self::booking_for_key( (int) $r['id'], (string) $r->get_param( 'key' ) );
		if ( is_wp_error( $booking ) ) {
			return $booking;
		}
		$payment = PaymentFlow::start( $booking, (string) $r->get_param( 'return_url' ) );
		return is_wp_error( $payment ) ? $payment : $this->ok( $payment );
	}

	/**
	 * Confirms a Stripe PaymentIntent after the card step.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function confirm_payment( \WP_REST_Request $r ) {
		$booking = self::booking_for_key( (int) $r['id'], (string) $r->get_param( 'key' ) );
		if ( is_wp_error( $booking ) ) {
			return $booking;
		}
		$result = Stripe::confirm_intent( $booking, (string) $r->get_param( 'payment_intent_id' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok(
			array(
				'paid'    => (bool) $result['paid'],
				'booking' => Presenter::booking_public( BookingRepository::find_joined( $booking['id'] ) ),
			)
		);
	}

	/**
	 * Captures a PayPal order after the customer approved it.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function paypal_capture( \WP_REST_Request $r ) {
		$booking = self::booking_for_key( (int) $r['id'], (string) $r->get_param( 'key' ) );
		if ( is_wp_error( $booking ) ) {
			return $booking;
		}
		$result = PayPal::capture( $booking, (string) $r->get_param( 'order_id' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok(
			array(
				'paid'    => (bool) $result['paid'],
				'booking' => Presenter::booking_public( BookingRepository::find_joined( $booking['id'] ) ),
			)
		);
	}

	/**
	 * Customer abandoned an online payment.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function cancel_payment( \WP_REST_Request $r ) {
		$booking = self::booking_for_key( (int) $r['id'], (string) $r->get_param( 'key' ) );
		if ( is_wp_error( $booking ) ) {
			return $booking;
		}
		$result = PaymentFlow::cancel( $booking );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( Presenter::booking_public( BookingRepository::find_joined( $booking['id'] ) ) );
	}

	/**
	 * Calendar file for a booking (served raw by self::serve_ics()).
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function ics( \WP_REST_Request $r ) {
		$booking = self::booking_for_key( (int) $r['id'], (string) $r->get_param( 'key' ) );
		if ( is_wp_error( $booking ) ) {
			return $booking;
		}
		$response = new \WP_REST_Response( Ics::build( BookingRepository::find_joined( $booking['id'] ) ), 200 );
		$response->header( 'Content-Type', 'text/calendar; charset=utf-8' );
		$response->header( 'Content-Disposition', 'attachment; filename="booking-' . (int) $booking['id'] . '.ics"' );
		$response->header( 'Cache-Control', 'no-store, private' );
		return $response;
	}

	/**
	 * Outputs the .ics body as-is instead of JSON.
	 *
	 * @param bool              $served  Whether the request was already served.
	 * @param \WP_HTTP_Response $result  Result.
	 * @param \WP_REST_Request  $request Request.
	 * @return bool
	 */
	public static function serve_ics( $served, $result, $request ) {
		if ( $served || ! preg_match( '#^/' . preg_quote( self::NS, '#' ) . '/wizard/bookings/\d+/ics$#', $request->get_route() ) ) {
			return $served;
		}
		if ( $result->is_error() || ! is_string( $result->get_data() ) ) {
			return $served;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- iCalendar document built and escaped by Ics::build().
		echo $result->get_data();
		return true;
	}
}
