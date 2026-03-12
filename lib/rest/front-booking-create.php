<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
  register_rest_route('pointly-booking/v1', '/front/booking/create', [
    'methods' => 'POST',
    'callback' => function (WP_REST_Request $req) {
      $payload = $req->get_json_params();
      if (!is_array($payload)) $payload = [];

      $method = isset($payload['payment_method']) ? sanitize_text_field($payload['payment_method']) : 'stripe';
      if ($method === '') $method = 'stripe';

      $booking = pointlybooking_create_pending_payment_booking_from_payload($payload, $method);
      if (is_wp_error($booking)) return $booking;

      $booking_id = (int)($booking['booking_id'] ?? 0);
      if ($booking_id <= 0) {
        return new WP_Error('booking_fail', 'Could not create booking.', ['status' => 500]);
      }

      $amount = isset($booking['total_price']) ? (float)$booking['total_price'] : (float)($payload['total_price'] ?? 0);
      $currency = isset($booking['currency']) ? (string)$booking['currency'] : (string)($payload['currency'] ?? 'USD');
      $manage_url = isset($booking['manage_url']) ? esc_url_raw((string)$booking['manage_url']) : '';
      $manage_key = '';
      if ($manage_url !== '') {
        $parts = wp_parse_url($manage_url);
        if (!empty($parts['query'])) {
          parse_str($parts['query'], $query_vars);
          $manage_key = sanitize_text_field((string)($query_vars['key'] ?? ''));
        }
      }

      return rest_ensure_response([
        'success' => true,
        'booking_id' => $booking_id,
        'amount' => $amount,
        'currency' => $currency,
        'manage_url' => $manage_url,
        'manage_key' => $manage_key,
      ]);
    },
    'permission_callback' => '__return_true',
  ]);
});
