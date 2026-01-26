<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
  register_rest_route('bp/v1', '/front/payment/stripe/start', [
    'methods' => 'POST',
    'callback' => function (WP_REST_Request $req) {
      global $wpdb;
      $params = $req->get_json_params();

      $booking_id = isset($params['booking_id']) ? (int)$params['booking_id'] : 0;
      if (!$booking_id) return new WP_Error('bad_request', 'Missing booking_id', ['status' => 400]);

      $table = $wpdb->prefix . 'bp_bookings';
      $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $booking_id), ARRAY_A);
      if (!$booking) return new WP_Error('not_found', 'Booking not found', ['status' => 404]);

      $amount = isset($booking['payment_amount']) && $booking['payment_amount'] !== null
        ? (float)$booking['payment_amount']
        : (float)($booking['total_price'] ?? 0);

      if ($amount <= 0) {
        return new WP_Error('bad_request', 'Invalid amount. booking.payment_amount is empty.', ['status' => 400]);
      }

      $currency = !empty($booking['payment_currency'])
        ? strtolower($booking['payment_currency'])
        : (!empty($booking['currency']) ? strtolower($booking['currency']) : 'dkk');

      $secret = get_option('bp_stripe_secret_key', '');
      if ($secret === '') {
        $settings = get_option('bp_settings', []);
        $mode = $settings['stripe_mode'] ?? 'test';
        $secret = ($mode === 'live')
          ? ($settings['stripe_live_secret_key'] ?? ($settings['payments_stripe_live_secret_key'] ?? ''))
          : ($settings['stripe_test_secret_key'] ?? ($settings['payments_stripe_test_secret_key'] ?? ''));
      }
      if (!$secret) return new WP_Error('config', 'Stripe secret key is missing', ['status' => 500]);

      $intent_body = [
        'amount' => (int)round($amount * 100),
        'currency' => $currency,
        'automatic_payment_methods[enabled]' => 'true',
        'metadata[booking_id]' => (string)$booking_id,
      ];

      $resp = wp_remote_post('https://api.stripe.com/v1/payment_intents', [
        'timeout' => 20,
        'headers' => [
          'Authorization' => 'Bearer ' . $secret,
          'Content-Type' => 'application/x-www-form-urlencoded',
        ],
        'body' => $intent_body,
      ]);

      if (is_wp_error($resp)) {
        return new WP_Error('stripe_error', $resp->get_error_message(), ['status' => 500]);
      }

      $code = (int)wp_remote_retrieve_response_code($resp);
      $body = json_decode(wp_remote_retrieve_body($resp), true);
      if ($code < 200 || $code >= 300 || empty($body['client_secret']) || empty($body['id'])) {
        return new WP_Error('stripe_error', 'Stripe PaymentIntent failed', [
          'status' => 500,
          'details' => $body,
        ]);
      }

      $wpdb->update($table, [
        'payment_method' => 'stripe',
        'payment_status' => 'unpaid',
        'payment_provider_ref' => sanitize_text_field($body['id']),
        'status' => 'pending_payment',
      ], ['id' => $booking_id]);

      return rest_ensure_response([
        'success' => true,
        'client_secret' => $body['client_secret'],
        'payment_intent_id' => $body['id'],
      ]);
    },
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('bp/v1', '/front/payment/stripe/confirm', [
    'methods' => 'POST',
    'callback' => function (WP_REST_Request $req) {
      global $wpdb;
      $params = $req->get_json_params();

      $booking_id = isset($params['booking_id']) ? (int)$params['booking_id'] : 0;
      $payment_intent_id = isset($params['payment_intent_id']) ? sanitize_text_field($params['payment_intent_id']) : '';

      if (!$booking_id || !$payment_intent_id) {
        return new WP_Error('bad_request', 'Missing booking_id or payment_intent_id', ['status' => 400]);
      }

      $secret = get_option('bp_stripe_secret_key', '');
      if ($secret === '') {
        $settings = get_option('bp_settings', []);
        $mode = $settings['stripe_mode'] ?? 'test';
        $secret = ($mode === 'live')
          ? ($settings['stripe_live_secret_key'] ?? ($settings['payments_stripe_live_secret_key'] ?? ''))
          : ($settings['stripe_test_secret_key'] ?? ($settings['payments_stripe_test_secret_key'] ?? ''));
      }
      if (!$secret) return new WP_Error('config', 'Stripe secret key is missing', ['status' => 500]);

      $resp = wp_remote_get('https://api.stripe.com/v1/payment_intents/' . rawurlencode($payment_intent_id), [
        'timeout' => 20,
        'headers' => ['Authorization' => 'Bearer ' . $secret],
      ]);

      if (is_wp_error($resp)) {
        return new WP_Error('stripe_error', $resp->get_error_message(), ['status' => 500]);
      }

      $body = json_decode(wp_remote_retrieve_body($resp), true);
      $pi_status = $body['status'] ?? '';

      $table = $wpdb->prefix . 'bp_bookings';

      if ($pi_status === 'succeeded') {
        $wpdb->update($table, [
          'payment_status' => 'paid',
          'status' => 'confirmed',
          'payment_provider_ref' => $payment_intent_id,
        ], ['id' => $booking_id]);

        return rest_ensure_response(['success' => true, 'booking_status' => 'confirmed']);
      }

      $wpdb->update($table, [
        'payment_status' => $pi_status ? $pi_status : 'unpaid',
      ], ['id' => $booking_id]);

      return rest_ensure_response([
        'success' => false,
        'booking_status' => 'pending_payment',
        'stripe_status' => $pi_status,
      ]);
    },
    'permission_callback' => '__return_true',
  ]);
});
