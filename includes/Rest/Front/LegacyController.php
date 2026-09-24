<?php
/**
 * 2.x public REST routes, kept as thin adapters over the 3.0 services so existing
 * integrations, cached pages and payment return links keep working.
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
use PointlyBooking\Services\Notifications\Variables;
use PointlyBooking\Services\Payments\PaymentFlow;
use PointlyBooking\Services\Payments\PaymentMethods;
use PointlyBooking\Services\Payments\PayPal;
use PointlyBooking\Services\Payments\Stripe;
use PointlyBooking\Services\Pricing\PricingService;
use PointlyBooking\Settings\Design;
use PointlyBooking\Settings\Settings;
use PointlyBooking\Support\Dates;
use PointlyBooking\Support\Money;
use PointlyBooking\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * Legacy routes (see FEATURES.md F-260 – F-265).
 */
final class LegacyController extends Controller {

	/**
	 * {@inheritDoc}
	 */
	public function register_routes() {
		$read  = array(
			'categories'                  => 'categories',
			'public/categories'           => 'categories',
			'front/categories'            => 'categories',
			'services'                    => 'services',
			'public/services'             => 'services',
			'front/services'              => 'services',
			'extras'                      => 'extras',
			'public/extras'               => 'extras',
			'front/extras'                => 'extras',
			'agents'                      => 'agents',
			'public/agents'               => 'agents',
			'front/agents'                => 'agents',
			'service-agents'              => 'agents',
			'front/locations'             => 'locations',
			'form-fields'                 => 'form_fields',
			'public/form-fields'          => 'form_fields',
			'front/form-fields'           => 'form_fields',
			'front/form-fields/active'    => 'form_fields_active',
			'promo/validate'              => 'promo',
			'public/settings'             => 'settings',
			'front/settings'              => 'settings',
			'front/booking-form-design'   => 'design',
			'front/slots'                 => 'slots',
			'public/availability-slots'   => 'slots',
			'availability/timeslots'      => 'slots',
			'front/availability/day'      => 'slots',
			'front/availability'          => 'month',
			'front/availability/month'    => 'month',
			'front/availability/month-slots' => 'month_slots',
		);
		foreach ( $read as $path => $callback ) {
			$this->route(
				'/' . $path,
				array(
					'methods'             => array( 'GET', 'POST' ),
					'callback'            => array( $this, $callback ),
					'permission_callback' => array( Controller::class, 'public_access' ),
				)
			);
		}

		$write = array(
			'booking/create'                 => 'create',
			'public/bookings'                => 'create',
			'front/bookings'                 => 'create',
			'front/booking/create'           => 'create',
			'front/payments/stripe/start'    => 'start_stripe',
			'front/payment/stripe/start'     => 'start_stripe',
			'front/payment/stripe/confirm'   => 'stripe_confirm',
			'front/payments/paypal/start'    => 'start_paypal',
			'front/payments/paypal/capture'  => 'paypal_capture',
			'front/payments/woocommerce/start' => 'start_woocommerce',
		);
		foreach ( $write as $path => $callback ) {
			$this->route(
				'/' . $path,
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, $callback ),
					'permission_callback' => array( Controller::class, 'public_access' ),
				)
			);
		}

		$this->route(
			'/front/bookings/(?P<id>\d+)/status',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'status' ),
				'permission_callback' => array( Controller::class, 'public_access' ),
			)
		);
		$this->route(
			'/front/bookings/(?P<id>\d+)/payment-cancel',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'payment_cancel' ),
				'permission_callback' => array( Controller::class, 'public_access' ),
			)
		);
	}

	/**
	 * 2.x success envelope.
	 *
	 * @param mixed $data  Data.
	 * @param array $extra Extra top-level keys.
	 * @param int   $code  HTTP status.
	 * @return \WP_REST_Response
	 */
	private function legacy( $data, array $extra = array(), $code = 200 ) {
		return new \WP_REST_Response(
			array_merge(
				array(
					'status'  => 'success',
					'success' => true,
					'data'    => $data,
				),
				$extra
			),
			$code
		);
	}

	/**
	 * Active categories.
	 *
	 * @return \WP_REST_Response
	 */
	public function categories() {
		$out = array();
		foreach ( CategoryRepository::all( true ) as $c ) {
			$image = CategoryRepository::image_url( $c['image_id'], 'medium' );
			$out[] = array(
				'id'          => (int) $c['id'],
				'name'        => (string) $c['name'],
				'description' => (string) $c['description'],
				'image'       => $image,
				'image_url'   => $image,
			);
		}
		return $this->legacy( $out );
	}

	/**
	 * Active services (optionally one category).
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function services( \WP_REST_Request $r ) {
		$category = absint( $r->get_param( 'category_id' ) );
		$services = ServiceRepository::all( true );
		$map      = Relations::map( 'service_categories', 'service_id', wp_list_pluck( $services, 'id' ) );
		$out      = array();
		foreach ( $services as $s ) {
			$cats = $map[ $s['id'] ] ?? array();
			if ( $category && ! in_array( $category, $cats, true ) ) {
				continue;
			}
			$row                     = Presenter::service_public( $s, $cats );
			$row['duration_minutes'] = $row['duration'];
			$row['category_id']      = $cats ? $cats[0] : 0;
			$row['image']            = $row['image_url'];
			$row['currency']         = Money::currency();
			$out[]                   = $row;
		}
		return $this->legacy( $out );
	}

	/**
	 * Extras of a service.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function extras( \WP_REST_Request $r ) {
		$service = absint( $r->get_param( 'service_id' ) );
		$out     = array();
		foreach ( $service ? ExtraRepository::for_service( $service ) : array() as $e ) {
			if ( empty( $e['is_active'] ) ) {
				continue;
			}
			$image = ExtraRepository::image_url( $e['image_id'], 'medium' );
			$out[] = array(
				'id'           => (int) $e['id'],
				'name'         => (string) $e['name'],
				'description'  => (string) $e['description'],
				'price'        => (float) $e['price'],
				'duration_min' => (int) $e['duration_min'],
				'image'        => $image,
				'image_url'    => $image,
			);
		}
		return $this->legacy( $out );
	}

	/**
	 * Staff for a service (optionally at a location).
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function agents( \WP_REST_Request $r ) {
		$service = absint( $r->get_param( 'service_id' ) );
		$agents  = AgentRepository::by_id( true );
		$ids     = $service ? AvailabilityService::candidate_agents( $service, absint( $r->get_param( 'location_id' ) ) ) : array_keys( $agents );
		$out     = array();
		foreach ( $ids as $id ) {
			if ( isset( $agents[ $id ] ) ) {
				$row          = Presenter::agent_public( $agents[ $id ] );
				$row['image'] = $row['image_url'];
				$out[]        = $row;
			}
		}
		return $this->legacy( $out );
	}

	/**
	 * Active locations.
	 *
	 * @return \WP_REST_Response
	 */
	public function locations() {
		$out = array();
		foreach ( LocationRepository::all( true ) as $l ) {
			$row          = Presenter::location( $l );
			$row['image'] = $row['image_url'];
			$out[]        = $row;
		}
		return $this->legacy( $out );
	}

	/**
	 * Enabled fields (optionally one scope).
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function form_fields( \WP_REST_Request $r ) {
		$scope  = (string) $r->get_param( 'scope' );
		$fields = FormFieldRepository::wizard_fields();
		if ( isset( $fields[ $scope ] ) ) {
			return $this->legacy( $fields[ $scope ] );
		}
		return $this->legacy( array_merge( $fields['form'], $fields['customer'], $fields['booking'] ) );
	}

	/**
	 * Enabled fields grouped by scope.
	 *
	 * @return \WP_REST_Response
	 */
	public function form_fields_active() {
		return $this->legacy( FormFieldRepository::wizard_fields() );
	}

	/**
	 * Promo validation (2.x shape).
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response
	 */
	public function promo( \WP_REST_Request $r ) {
		$limited = WizardController::throttle( 'wizard_quote', 60, 600 );
		if ( $limited ) {
			return $limited;
		}
		$result = PricingService::check_promo( (string) $r->get_param( 'code' ), (float) $r->get_param( 'subtotal' ) );
		unset( $result['promo'] );
		return new \WP_REST_Response(
			array_merge(
				array(
					'status' => $result['valid'] ? 'success' : 'error',
					'data'   => $result,
				),
				$result
			),
			200
		);
	}

	/**
	 * Public settings.
	 *
	 * @return \WP_REST_Response
	 */
	public function settings() {
		return $this->legacy(
			array(
				'currency'              => Money::currency(),
				'currency_symbol'       => Money::symbol(),
				'currency_position'     => (string) Settings::get( 'currency_position', 'before' ),
				'slot_interval_minutes' => AvailabilityService::step(),
				'future_days_limit'     => AvailabilityService::window_days(),
				'timezone'              => wp_timezone_string(),
				'payments'              => PaymentMethods::public_config(),
			)
		);
	}

	/**
	 * Booking form design.
	 *
	 * @return \WP_REST_Response
	 */
	public function design() {
		$config = Design::get();
		return $this->legacy( $config, array( 'config' => $config ) );
	}

	/**
	 * Slots of one day ({start_time, end_time} plus 3.0 keys).
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function slots( \WP_REST_Request $r ) {
		$service = absint( $r->get_param( 'service_id' ) );
		$date    = Sanitize::date( $r->get_param( 'date' ) );
		if ( ! $service || '' === $date ) {
			return $this->error( 'invalid_request', __( 'service_id and date are required.', 'pointly-booking' ) );
		}
		$data  = AvailabilityService::day(
			array(
				'service_id'  => $service,
				'agent_id'    => absint( $r->get_param( 'agent_id' ) ),
				'location_id' => absint( $r->get_param( 'location_id' ) ),
				'date'        => $date,
			)
		);
		$slots = array();
		foreach ( $data['slots'] as $slot ) {
			$slots[] = array(
				'start'      => $slot['start'],
				'end'        => $slot['end'],
				'start_time' => $slot['start'],
				'end_time'   => $slot['end'],
				'time'       => $slot['start'],
				'label'      => $slot['start'],
			);
		}
		return $this->legacy( $slots, array( 'slots' => $slots ) );
	}

	/**
	 * Month availability.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function month( \WP_REST_Request $r ) {
		$service = absint( $r->get_param( 'service_id' ) );
		$month   = (string) $r->get_param( 'month' );
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
			$month = substr( Dates::today(), 0, 7 );
		}
		if ( ! $service ) {
			return $this->error( 'invalid_request', __( 'service_id is required.', 'pointly-booking' ) );
		}
		$data = AvailabilityService::month( $service, absint( $r->get_param( 'agent_id' ) ), absint( $r->get_param( 'location_id' ) ), $month );
		$map  = array();
		foreach ( $data['days'] as $date => $count ) {
			$map[ $date ] = $count > 0;
		}
		$data['available']       = $map;
		$data['available_dates'] = array_keys( $data['days'] );
		return $this->legacy( $data );
	}

	/**
	 * Month availability with every slot per day.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function month_slots( \WP_REST_Request $r ) {
		$service = absint( $r->get_param( 'service_id' ) );
		$month   = (string) $r->get_param( 'month' );
		if ( ! $service || ! preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
			return $this->error( 'invalid_request', __( 'service_id and month are required.', 'pointly-booking' ) );
		}
		$from = $month . '-01';
		$data = AvailabilityService::compute(
			array(
				'service_id'  => $service,
				'agent_id'    => absint( $r->get_param( 'agent_id' ) ),
				'location_id' => absint( $r->get_param( 'location_id' ) ),
				'from'        => $from,
				'to'          => gmdate( 'Y-m-t', strtotime( $from . ' 12:00:00 UTC' ) ),
			)
		);
		$out = array();
		foreach ( $data['dates'] as $date => $minutes ) {
			$out[ $date ] = array_map( array( Dates::class, 'from_minutes' ), array_keys( $minutes ) );
		}
		return $this->legacy( $out );
	}

	/**
	 * Maps a 2.x booking payload to BookingService input.
	 *
	 * @param array $p Payload.
	 * @return array
	 */
	private static function input( array $p ) {
		if ( ! empty( $p['customer_name'] ) && empty( $p['first_name'] ) && empty( $p['customer_fields']['first_name'] ) ) {
			$parts           = preg_split( '/\s+/', trim( (string) $p['customer_name'] ), 2 );
			$p['first_name'] = $parts[0];
			$p['last_name']  = $parts[1] ?? '';
		}
		if ( ! empty( $p['customer_email'] ) && empty( $p['email'] ) ) {
			$p['email'] = $p['customer_email'];
		}
		if ( ! empty( $p['customer_phone'] ) && empty( $p['phone'] ) ) {
			$p['phone'] = $p['customer_phone'];
		}
		$extras = array();
		foreach ( (array) ( $p['extras'] ?? ( $p['extra_ids'] ?? array() ) ) as $extra ) {
			$extras[] = is_array( $extra ) ? absint( $extra['id'] ?? 0 ) : absint( $extra );
		}
		$p['extras'] = array_filter( $extras );
		$p['start']  = $p['start_time'] ?? ( $p['time'] ?? ( $p['start'] ?? '' ) );
		if ( ! empty( $p['start'] ) && strlen( (string) $p['start'] ) > 5 && preg_match( '/(\d{2}:\d{2})/', (string) $p['start'], $m ) ) {
			$p['start'] = $m[1];
		}
		unset( $p['status'], $p['force'], $p['customer_id'], $p['payment_status'] );
		return $p;
	}

	/**
	 * Creates a booking from a 2.x payload.
	 *
	 * @param array  $payload Payload.
	 * @param string $method  Forced payment method ('' = from payload).
	 * @return array|\WP_Error Booking row.
	 */
	private function create_booking( array $payload, $method = '' ) {
		if ( '' !== trim( (string) ( $payload['pointlybooking_hp'] ?? '' ) ) ) {
			return $this->error( 'rejected', __( 'Your booking could not be submitted.', 'pointly-booking' ), 400 );
		}
		$limited = WizardController::throttle( 'wizard_create', 10, 600 );
		if ( $limited ) {
			return $limited;
		}
		$input = self::input( $payload );
		if ( '' !== $method ) {
			$input['payment_method'] = $method;
		}
		return BookingService::create( $input, array( 'source' => 'legacy' ) );
	}

	/**
	 * Common 2.x booking response keys.
	 *
	 * @param array $booking Booking row.
	 * @return array
	 */
	private static function booking_keys( array $booking ) {
		return array(
			'booking_id'     => (int) $booking['id'],
			'manage_key'     => (string) $booking['manage_key'],
			'key'            => (string) $booking['manage_key'],
			'manage_url'     => Variables::manage_url( (string) $booking['manage_key'] ),
			'total_price'    => (float) $booking['total_price'],
			'discount_total' => (float) $booking['discount_total'],
			'subtotal'       => (float) $booking['total_price'] + (float) $booking['discount_total'],
			'amount'         => (float) $booking['total_price'],
			'currency'       => (string) $booking['currency'],
			'status_value'   => (string) $booking['status'],
			'payment_status' => (string) $booking['payment_status'],
		);
	}

	/**
	 * POST /booking/create, /public/bookings, /front/bookings, /front/booking/create.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create( \WP_REST_Request $r ) {
		$booking = $this->create_booking( $this->body( $r ) );
		if ( is_wp_error( $booking ) ) {
			return $booking;
		}
		$keys = self::booking_keys( $booking );
		return $this->legacy( $keys, $keys, 200 );
	}

	/**
	 * Existing booking (booking_id + key) or a new one from the payload.
	 *
	 * @param array  $p      Payload.
	 * @param string $method Payment method.
	 * @return array|\WP_Error
	 */
	private function booking_for_payment( array $p, $method ) {
		$id = absint( $p['booking_id'] ?? 0 );
		if ( $id ) {
			return WizardController::booking_for_key( $id, (string) ( $p['key'] ?? ( $p['manage_key'] ?? '' ) ) );
		}
		return $this->create_booking( $p, $method );
	}

	/**
	 * Starts a payment and returns the 2.x keys.
	 *
	 * @param \WP_REST_Request $r      Request.
	 * @param string           $method Method.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function start( \WP_REST_Request $r, $method ) {
		$p       = $this->body( $r );
		$booking = $this->booking_for_payment( $p, $method );
		if ( is_wp_error( $booking ) ) {
			return $booking;
		}
		$next = PaymentFlow::start( $booking, (string) ( $p['return_url'] ?? ( $p['page_url'] ?? wp_get_referer() ) ) );
		if ( is_wp_error( $next ) ) {
			return $next;
		}
		$keys = array_merge( self::booking_keys( $booking ), array( 'payment' => $next ) );
		if ( isset( $next['url'] ) ) {
			$keys['url']          = $next['url'];
			$keys['redirect_url'] = $next['url'];
			$keys['checkout_url'] = $next['url'];
			$keys['approve_url']  = $next['url'];
		}
		if ( isset( $next['client_secret'] ) ) {
			$keys['client_secret']     = $next['client_secret'];
			$keys['payment_intent_id'] = $next['payment_intent_id'];
			$keys['publishable_key']   = $next['publishable_key'];
		}
		return $this->legacy( $keys, $keys );
	}

	/**
	 * Stripe (Checkout or Elements depending on settings).
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function start_stripe( \WP_REST_Request $r ) {
		return $this->start( $r, 'stripe' );
	}

	/**
	 * PayPal.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function start_paypal( \WP_REST_Request $r ) {
		return $this->start( $r, 'paypal' );
	}

	/**
	 * WooCommerce.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function start_woocommerce( \WP_REST_Request $r ) {
		return $this->start( $r, 'woocommerce' );
	}

	/**
	 * Stripe Elements confirmation.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function stripe_confirm( \WP_REST_Request $r ) {
		$p       = $this->body( $r );
		$booking = WizardController::booking_for_key( absint( $p['booking_id'] ?? 0 ), (string) ( $p['key'] ?? '' ) );
		if ( is_wp_error( $booking ) ) {
			return $booking;
		}
		$result = Stripe::confirm_intent( $booking, Sanitize::text( $p['payment_intent_id'] ?? '' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->legacy( $result, $result );
	}

	/**
	 * PayPal capture.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function paypal_capture( \WP_REST_Request $r ) {
		$p       = $this->body( $r );
		$booking = WizardController::booking_for_key( absint( $p['booking_id'] ?? 0 ), (string) ( $p['key'] ?? '' ) );
		if ( is_wp_error( $booking ) ) {
			return $booking;
		}
		$result = PayPal::capture( $booking, Sanitize::text( $p['order_id'] ?? ( $p['token'] ?? '' ) ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->legacy( $result, $result );
	}

	/**
	 * Booking status after a payment redirect.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function status( \WP_REST_Request $r ) {
		$booking = WizardController::booking_for_key( (int) $r['id'], (string) $r->get_param( 'key' ) );
		if ( is_wp_error( $booking ) ) {
			return $booking;
		}
		$booking = PaymentFlow::refresh( $booking );
		$data    = array(
			'id'             => (int) $booking['id'],
			'booking_id'     => (int) $booking['id'],
			'status'         => (string) $booking['status'],
			'payment_status' => (string) $booking['payment_status'],
			'payment_method' => (string) $booking['payment_method'],
			'total_price'    => (float) $booking['total_price'],
			'currency'       => (string) $booking['currency'],
		);
		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			200
		);
	}

	/**
	 * Customer abandoned payment.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function payment_cancel( \WP_REST_Request $r ) {
		$booking = WizardController::booking_for_key( (int) $r['id'], (string) $r->get_param( 'key' ) );
		if ( is_wp_error( $booking ) ) {
			return $booking;
		}
		$result = PaymentFlow::cancel( $booking );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->legacy( array( 'status' => (string) $result['status'] ) );
	}
}
