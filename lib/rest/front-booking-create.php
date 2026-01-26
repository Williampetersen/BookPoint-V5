<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
  register_rest_route('bp/v1', '/front/booking/create', [
    'methods' => 'POST',
    'callback' => function (WP_REST_Request $req) {
      $payload = $req->get_json_params();
      if (!is_array($payload)) $payload = [];

      $method = isset($payload['payment_method']) ? sanitize_text_field($payload['payment_method']) : 'stripe';
      if ($method === '') $method = 'stripe';

      $booking = bp_create_pending_payment_booking_from_payload($payload, $method);
      if (is_wp_error($booking)) return $booking;

      $booking_id = (int)($booking['booking_id'] ?? 0);
      if ($booking_id <= 0) {
        return new WP_Error('booking_fail', 'Could not create booking.', ['status' => 500]);
      }

      $amount = isset($booking['total_price']) ? (float)$booking['total_price'] : (float)($payload['total_price'] ?? 0);
      $currency = isset($booking['currency']) ? (string)$booking['currency'] : (string)($payload['currency'] ?? 'USD');

      return rest_ensure_response([
        'success' => true,
        'booking_id' => $booking_id,
        'amount' => $amount,
        'currency' => $currency,
      ]);
    },
    'permission_callback' => '__return_true',
  ]);
});
