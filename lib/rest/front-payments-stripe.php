<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {
  register_rest_route('bp/v1', '/front/payments/stripe/start', [
    'methods' => 'POST',
    'callback' => 'bp_stripe_start_checkout',
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('bp/v1', '/webhooks/stripe', [
    'methods' => 'POST',
    'callback' => 'bp_stripe_webhook',
    'permission_callback' => '__return_true',
  ]);
});

function bp_stripe_secret_key(): string {
  $mode = class_exists('BP_SettingsHelper')
    ? (string)BP_SettingsHelper::get('payments_stripe_mode', 'test')
    : 'test';

  if ($mode === 'live') {
    return (string)(class_exists('BP_SettingsHelper')
      ? BP_SettingsHelper::get('payments_stripe_live_secret_key', '')
      : '');
  }

  return (string)(class_exists('BP_SettingsHelper')
    ? BP_SettingsHelper::get('payments_stripe_test_secret_key', '')
    : '');
}

function bp_stripe_start_checkout(WP_REST_Request $req) {
  $secret = bp_stripe_secret_key();
  if ($secret === '') {
    return new WP_Error('no_key', 'Stripe secret key not configured.', ['status' => 400]);
  }

  $payload = $req->get_json_params();
  if (!is_array($payload)) $payload = [];

  $amount = (float)($payload['total_price'] ?? ($payload['total'] ?? 0));
  $currency = strtolower(sanitize_text_field($payload['currency'] ?? 'usd'));
  if ($amount <= 0) {
    return new WP_Error('bad_amount', 'Invalid total amount.', ['status' => 400]);
  }
  if (!preg_match('/^[a-z]{3}$/', $currency)) {
    $currency = 'usd';
  }

  $booking = bp_create_pending_payment_booking_from_payload($payload, 'stripe');
  if (is_wp_error($booking)) return $booking;
  $booking_id = (int)($booking['booking_id'] ?? 0);
  if ($booking_id <= 0) {
    return new WP_Error('booking_fail', 'Could not create booking.', ['status' => 500]);
  }

  $success = class_exists('BP_SettingsHelper')
    ? (string)BP_SettingsHelper::get('payments_stripe_success_url', '')
    : '';
  $cancel = class_exists('BP_SettingsHelper')
    ? (string)BP_SettingsHelper::get('payments_stripe_cancel_url', '')
    : '';

  if ($success === '') $success = home_url('/?bp_payment=stripe_success');
  if ($cancel === '') $cancel = home_url('/?bp_payment=stripe_cancel');

  $line_name = 'Booking #' . $booking_id;
  $unit_amount = (int)round($amount * 100);

  $body = [
    'mode' => 'payment',
    'success_url' => add_query_arg(['booking_id' => $booking_id], $success),
    'cancel_url' => add_query_arg(['booking_id' => $booking_id], $cancel),
    'client_reference_id' => (string)$booking_id,
    'metadata[booking_id]' => (string)$booking_id,
    'line_items[0][quantity]' => 1,
    'line_items[0][price_data][currency]' => $currency,
    'line_items[0][price_data][unit_amount]' => $unit_amount,
    'line_items[0][price_data][product_data][name]' => $line_name,
  ];

  $resp = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', [
    'headers' => [
      'Authorization' => 'Bearer ' . $secret,
      'Content-Type' => 'application/x-www-form-urlencoded',
    ],
    'body' => http_build_query($body, '', '&'),
    'timeout' => 30,
  ]);

  if (is_wp_error($resp)) {
    return new WP_Error('stripe_error', $resp->get_error_message(), ['status' => 500]);
  }

  $code = (int)wp_remote_retrieve_response_code($resp);
  $json = json_decode(wp_remote_retrieve_body($resp), true);
  if ($code < 200 || $code >= 300) {
    return new WP_Error('stripe_error', $json['error']['message'] ?? 'Stripe error', ['status' => 500]);
  }

  $session_id = sanitize_text_field($json['id'] ?? '');
  if ($session_id !== '') {
    global $wpdb;
    $wpdb->update(
      $wpdb->prefix . 'bp_bookings',
      ['payment_provider_ref' => $session_id],
      ['id' => $booking_id],
      ['%s'],
      ['%d']
    );
  }

  return rest_ensure_response([
    'success' => true,
    'booking_id' => $booking_id,
    'checkout_url' => $json['url'] ?? '',
  ]);
}

function bp_stripe_verify_signature(string $payload, string $sig_header, string $secret, int $tolerance = 300): bool {
  if ($secret === '' || $sig_header === '') return false;

  $timestamp = 0;
  $signatures = [];
  foreach (explode(',', $sig_header) as $part) {
    $pair = explode('=', trim($part), 2);
    if (count($pair) !== 2) continue;
    if ($pair[0] === 't') $timestamp = (int)$pair[1];
    if ($pair[0] === 'v1') $signatures[] = $pair[1];
  }

  if ($timestamp <= 0 || empty($signatures)) return false;
  if (abs(time() - $timestamp) > $tolerance) return false;

  $signed_payload = $timestamp . '.' . $payload;
  $expected = hash_hmac('sha256', $signed_payload, $secret);
  foreach ($signatures as $sig) {
    if (hash_equals($expected, $sig)) return true;
  }

  return false;
}

function bp_stripe_webhook(WP_REST_Request $req) {
  $settings = get_option('bp_settings', []);
  $webhook_secret = $settings['stripe_webhook_secret'] ?? '';

  $raw = $req->get_body();
  $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
  if (!$webhook_secret) {
    return new WP_Error('no_webhook_secret', 'Stripe webhook secret not configured.', ['status' => 400]);
  }
  if (!$sig_header) {
    return new WP_Error('no_signature', 'Missing Stripe-Signature header.', ['status' => 400]);
  }

  $tolerance = 300;
  $timestamp = 0;
  $v1_signatures = [];

  foreach (explode(',', $sig_header) as $part) {
    $kv = explode('=', trim($part), 2);
    if (count($kv) !== 2) continue;
    if ($kv[0] === 't') $timestamp = (int)$kv[1];
    if ($kv[0] === 'v1') $v1_signatures[] = $kv[1];
  }

  if ($timestamp <= 0 || empty($v1_signatures)) {
    return new WP_Error('bad_signature', 'Invalid Stripe-Signature header.', ['status' => 400]);
  }

  if (abs(time() - $timestamp) > $tolerance) {
    return new WP_Error('timestamp_out_of_range', 'Webhook timestamp out of range.', ['status' => 400]);
  }

  $signed_payload = $timestamp . '.' . $raw;
  $expected = hash_hmac('sha256', $signed_payload, $webhook_secret);

  $match = false;
  foreach ($v1_signatures as $sig) {
    if (hash_equals($expected, $sig)) {
      $match = true;
      break;
    }
  }
  if (!$match) {
    return new WP_Error('signature_mismatch', 'Stripe signature verification failed.', ['status' => 400]);
  }

  $event = json_decode($raw, true);
  if (!is_array($event)) {
    return new WP_Error('bad_json', 'Invalid JSON body.', ['status' => 400]);
  }

  if (($event['type'] ?? '') === 'checkout.session.completed') {
    $session = $event['data']['object'] ?? [];
    $booking_id = (int)($session['client_reference_id'] ?? ($session['metadata']['booking_id'] ?? 0));
    $provider_ref = sanitize_text_field($session['id'] ?? '');

    if ($booking_id > 0) {
      bp_confirm_booking_paid($booking_id, $provider_ref);
    }
  }

  return rest_ensure_response(['success' => true]);
}
