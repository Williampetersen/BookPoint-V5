<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {
  register_rest_route('bp/v1', '/front/payments/paypal/start', [
    'methods' => 'POST',
    'callback' => 'bp_paypal_start',
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('bp/v1', '/front/payments/paypal/capture', [
    'methods' => 'POST',
    'callback' => 'bp_paypal_capture',
    'permission_callback' => '__return_true',
  ]);
});

function bp_paypal_base(): string {
  $mode = class_exists('BP_SettingsHelper')
    ? (string)BP_SettingsHelper::get('payments_paypal_mode', 'test')
    : 'test';

  return $mode === 'live'
    ? 'https://api-m.paypal.com'
    : 'https://api-m.sandbox.paypal.com';
}

function bp_paypal_token(): string {
  $client = class_exists('BP_SettingsHelper')
    ? (string)BP_SettingsHelper::get('payments_paypal_client_id', '')
    : '';
  $secret = class_exists('BP_SettingsHelper')
    ? (string)BP_SettingsHelper::get('payments_paypal_secret', '')
    : '';

  if ($client === '' || $secret === '') return '';

  $resp = wp_remote_post(bp_paypal_base() . '/v1/oauth2/token', [
    'headers' => [
      'Authorization' => 'Basic ' . base64_encode($client . ':' . $secret),
      'Content-Type' => 'application/x-www-form-urlencoded',
    ],
    'body' => 'grant_type=client_credentials',
    'timeout' => 30,
  ]);

  if (is_wp_error($resp)) return '';
  $json = json_decode(wp_remote_retrieve_body($resp), true);
  return (string)($json['access_token'] ?? '');
}

function bp_paypal_start(WP_REST_Request $req) {
  $token = bp_paypal_token();
  if ($token === '') {
    return new WP_Error('no_token', 'PayPal credentials not configured.', ['status' => 400]);
  }

  $payload = $req->get_json_params();
  if (!is_array($payload)) $payload = [];

  $amount = (float)($payload['total_price'] ?? ($payload['total'] ?? 0));
  $currency = strtoupper(sanitize_text_field($payload['currency'] ?? 'USD'));
  if ($amount <= 0) {
    return new WP_Error('bad_amount', 'Invalid total amount.', ['status' => 400]);
  }
  if (!preg_match('/^[A-Z]{3}$/', $currency)) {
    $currency = 'USD';
  }

  $booking = bp_create_pending_payment_booking_from_payload($payload, 'paypal');
  if (is_wp_error($booking)) return $booking;
  $booking_id = (int)($booking['booking_id'] ?? 0);
  if ($booking_id <= 0) {
    return new WP_Error('booking_fail', 'Could not create booking.', ['status' => 500]);
  }

  $return = class_exists('BP_SettingsHelper')
    ? (string)BP_SettingsHelper::get('payments_paypal_return_url', '')
    : '';
  $cancel = class_exists('BP_SettingsHelper')
    ? (string)BP_SettingsHelper::get('payments_paypal_cancel_url', '')
    : '';

  if ($return === '') $return = home_url('/?bp_payment=paypal_return');
  if ($cancel === '') $cancel = home_url('/?bp_payment=paypal_cancel');

  $order_body = [
    'intent' => 'CAPTURE',
    'purchase_units' => [[
      'reference_id' => (string)$booking_id,
      'amount' => [
        'currency_code' => $currency,
        'value' => number_format($amount, 2, '.', ''),
      ],
      'custom_id' => (string)$booking_id,
    ]],
    'application_context' => [
      'return_url' => add_query_arg(['booking_id' => $booking_id], $return),
      'cancel_url' => add_query_arg(['booking_id' => $booking_id], $cancel),
      'brand_name' => 'BookPoint',
      'user_action' => 'PAY_NOW',
    ],
  ];

  $resp = wp_remote_post(bp_paypal_base() . '/v2/checkout/orders', [
    'headers' => [
      'Authorization' => 'Bearer ' . $token,
      'Content-Type' => 'application/json',
    ],
    'body' => wp_json_encode($order_body),
    'timeout' => 30,
  ]);

  if (is_wp_error($resp)) {
    return new WP_Error('paypal_error', $resp->get_error_message(), ['status' => 500]);
  }

  $code = (int)wp_remote_retrieve_response_code($resp);
  $json = json_decode(wp_remote_retrieve_body($resp), true);
  if ($code < 200 || $code >= 300) {
    return new WP_Error('paypal_error', $json['message'] ?? 'PayPal error', ['status' => 500]);
  }

  $order_id = sanitize_text_field($json['id'] ?? '');
  $approve_url = '';
  foreach (($json['links'] ?? []) as $link) {
    if (($link['rel'] ?? '') === 'approve') {
      $approve_url = $link['href'] ?? '';
      break;
    }
  }

  if ($order_id !== '') {
    global $wpdb;
    $wpdb->update(
      $wpdb->prefix . 'bp_bookings',
      ['payment_provider_ref' => $order_id],
      ['id' => $booking_id],
      ['%s'],
      ['%d']
    );
  }

  return rest_ensure_response([
    'success' => true,
    'booking_id' => $booking_id,
    'approve_url' => $approve_url,
    'order_id' => $order_id,
  ]);
}

function bp_paypal_capture(WP_REST_Request $req) {
  $token = bp_paypal_token();
  if ($token === '') {
    return new WP_Error('no_token', 'PayPal credentials not configured.', ['status' => 400]);
  }

  $p = $req->get_json_params();
  if (!is_array($p)) $p = [];

  $order_id = sanitize_text_field($p['order_id'] ?? '');
  $booking_id = (int)($p['booking_id'] ?? 0);
  if ($order_id === '' || $booking_id <= 0) {
    return new WP_Error('bad', 'Missing order_id or booking_id', ['status' => 400]);
  }

  $resp = wp_remote_post(bp_paypal_base() . "/v2/checkout/orders/{$order_id}/capture", [
    'headers' => [
      'Authorization' => 'Bearer ' . $token,
      'Content-Type' => 'application/json',
    ],
    'timeout' => 30,
  ]);

  if (is_wp_error($resp)) {
    return new WP_Error('paypal_error', $resp->get_error_message(), ['status' => 500]);
  }

  $code = (int)wp_remote_retrieve_response_code($resp);
  $json = json_decode(wp_remote_retrieve_body($resp), true);
  if ($code < 200 || $code >= 300) {
    return new WP_Error('paypal_error', $json['message'] ?? 'PayPal capture failed', ['status' => 500]);
  }

  bp_confirm_booking_paid($booking_id, $order_id);

  return rest_ensure_response(['success' => true, 'booking_id' => $booking_id]);
}
