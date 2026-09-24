<?php
/**
 * REST smoke test. Run: docker compose -f .dev/docker-compose.yml exec -T cli wp eval-file wp-content/plugins/pointly-booking/.dev/smoke-rest.php
 *
 * Creates real bookings in the dev database.
 *
 * @package PointlyBooking
 */

// phpcs:ignoreFile -- Development script, not shipped.

$failures = 0;
$ns       = '/pointly-booking/v1';

$call = static function ( $method, $route, array $params = array(), $user = 0 ) use ( $ns ) {
	wp_set_current_user( $user );
	$request = new WP_REST_Request( $method, $ns . $route );
	if ( 'GET' === $method ) {
		$request->set_query_params( $params );
	} else {
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
	}
	$response = rest_do_request( $request );
	return array( $response->get_status(), rest_get_server()->response_to_data( $response, false ) );
};

$check = static function ( $label, $ok, $detail = '' ) use ( &$failures ) {
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( $ok || '' === $detail ? '' : ' → ' . ( is_string( $detail ) ? $detail : wp_json_encode( $detail ) ) ) . "\n";
	if ( ! $ok ) {
		++$failures;
	}
};

// Wizard bootstrap.
list( $code, $body ) = $call( 'GET', '/wizard/bootstrap' );
$check( 'wizard bootstrap 200', 200 === $code, $body );
$services = $body['data']['services'] ?? array();
$check( 'bootstrap has services', count( $services ) > 0 );
$check( 'bootstrap hides staff contact data', ! isset( ( $body['data']['agents'][0] ?? array() )['email'] ) );
$service = $services[0] ?? null;
if ( ! $service ) {
	echo "No services; aborting.\n";
	return;
}

// Month + day availability.
list( $code, $body ) = $call( 'GET', '/wizard/availability/month', array( 'service_id' => $service['id'] ) );
$check( 'month availability 200', 200 === $code, $body );
$first = $body['data']['first_available'] ?? null;
$check( 'first available date found', (bool) $first, $body );

list( $code, $body ) = $call( 'GET', '/wizard/availability/day', array( 'service_id' => $service['id'], 'date' => $first ) );
$slots = $body['data']['slots'] ?? array();
$check( 'day has slots', 200 === $code && count( $slots ) > 0, $body );
$check( 'day slots hide staff ids', ! isset( ( $slots[0] ?? array() )['agents'] ) );

// Quote with a bad promo code.
list( $code, $body ) = $call( 'POST', '/wizard/quote', array( 'service_id' => $service['id'], 'promo_code' => 'NOPE-XYZ' ) );
$check( 'quote 200', 200 === $code, $body );
$check( 'invalid promo flagged', empty( $body['data']['promo_valid'] ), $body );

// Create a booking (payments may be off → cash/free).
add_filter( 'pointlybooking_rate_limit_enabled', '__return_false' );
$slot    = $slots[0];
$payload = array(
	'service_id' => $service['id'],
	'agent_id'   => 0,
	'date'       => $first,
	'start'      => $slot['start'],
	'fields'     => array(
		'customer' => array(
			'first_name' => 'Smoke',
			'last_name'  => 'Test',
			'email'      => 'smoke+' . wp_rand( 1000, 9999 ) . '@example.com',
			'phone'      => '+45 12345678',
		),
		'booking'  => array( 'notes' => 'Created by smoke test' ),
	),
);
list( $code, $body ) = $call( 'POST', '/wizard/bookings', $payload );
$check( 'create booking 201', 201 === $code, $body );
$booking = $body['data']['booking'] ?? array();
$check( 'booking has 64-hex key', (bool) preg_match( '/^[a-f0-9]{64}$/', $booking['key'] ?? '' ), $booking );

// Missing required email.
$bad                               = $payload;
$bad['fields']['customer']['email'] = '';
list( $code, $body ) = $call( 'POST', '/wizard/bookings', $bad );
$check( 'missing email rejected (400)', 400 === $code, $body );

// Double booking: fill every remaining seat of that slot, then one more must fail.
$capacity = max( 1, (int) ( $slot['available'] ?? 1 ) );
$last     = 0;
for ( $i = 0; $i < $capacity + 1; $i++ ) {
	$again                               = $payload;
	$again['fields']['customer']['email'] = 'smoke-dup' . $i . '@example.com';
	list( $last, $body ) = $call( 'POST', '/wizard/bookings', $again );
}
$check( 'double booking blocked (409)', 409 === $last, $body );

// Status + ICS with the key; wrong key refused.
list( $code, $body ) = $call( 'GET', '/wizard/bookings/' . $booking['id'], array( 'key' => $booking['key'] ) );
$check( 'status with key 200', 200 === $code, $body );
list( $code ) = $call( 'GET', '/wizard/bookings/' . $booking['id'], array( 'key' => str_repeat( 'a', 64 ) ) );
$check( 'status with wrong key 404', 404 === $code );

// Manage page API.
list( $code, $body ) = $call( 'GET', '/manage/booking', array( 'key' => $booking['key'] ) );
$check( 'manage booking 200', 200 === $code && ! empty( $body['data']['can_cancel'] ), $body );
list( $code, $body ) = $call( 'POST', '/manage/cancel', array( 'key' => $booking['key'] ) );
$check( 'manage cancel 200', 200 === $code && 'cancelled' === ( $body['data']['status'] ?? '' ), $body );
$check( 'manage key rotated after cancel', ( $body['data']['key'] ?? '' ) !== $booking['key'] );
list( $code ) = $call( 'GET', '/manage/booking', array( 'key' => $booking['key'] ) );
$check( 'old manage key no longer works', 404 === $code );

// Legacy routes.
list( $code, $body ) = $call( 'GET', '/services' );
$check( 'legacy /services 200', 200 === $code && 'success' === ( $body['status'] ?? '' ), $body );
list( $code, $body ) = $call( 'GET', '/front/slots', array( 'service_id' => $service['id'], 'date' => $first ) );
$check( 'legacy /front/slots 200', 200 === $code, $body );

// Admin routes: anonymous refused, admin allowed.
list( $code ) = $call( 'GET', '/admin/bookings' );
$check( 'admin bookings refused for guests', in_array( $code, array( 401, 403 ), true ) );
$admin = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 1 );
foreach ( array( '/admin/bookings', '/admin/dashboard', '/admin/services', '/admin/agents', '/admin/customers', '/admin/settings', '/admin/schedule', '/admin/holidays', '/admin/promo-codes', '/admin/form-fields', '/admin/notifications/workflows', '/admin/notifications/meta', '/admin/audit-logs', '/admin/tools/status', '/admin/lookups', '/admin/booking-form-design', '/admin/onboarding', '/admin/locations', '/admin/categories', '/admin/extras' ) as $route ) {
	list( $code, $body ) = $call( 'GET', $route, array(), $admin );
	$check( 'admin GET ' . $route, 200 === $code, $body );
}
list( $code, $body ) = $call( 'GET', '/admin/calendar', array( 'start' => $first, 'end' => $first ), $admin );
$check( 'admin calendar', 200 === $code, $body );
list( $code, $body ) = $call( 'GET', '/admin/settings/payments', array(), $admin );
$check( 'admin payments settings', 200 === $code, $body );
$check( 'payments secrets masked', empty( $body['payments']['stripe']['test_secret_key'] ) || 0 === strpos( $body['payments']['stripe']['test_secret_key'], '••' ) );

// Settings validation error surfaces field messages.
list( $code, $body ) = $call( 'POST', '/admin/settings', array( 'pointlybooking_schedule_1' => '25:00-99:00' ), $admin );
$check( 'invalid setting rejected 400', 400 === $code, $body );

echo "\n" . ( $failures ? "{$failures} FAILURE(S)" : 'ALL PASSED' ) . "\n";
