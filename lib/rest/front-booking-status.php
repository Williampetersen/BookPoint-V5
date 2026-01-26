<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
  register_rest_route('bp/v1', '/front/bookings/(?P<id>\d+)/status', [
    'methods' => 'GET',
    'callback' => function (WP_REST_Request $req) {
      global $wpdb;
      $id = (int)$req['id'];
      $row = $wpdb->get_row($wpdb->prepare(
        "SELECT id, status, payment_method, payment_status, payment_provider_ref FROM {$wpdb->prefix}bp_bookings WHERE id=%d",
        $id
      ), ARRAY_A);

      if (!$row) {
        return new WP_Error('not_found', 'Booking not found', ['status' => 404]);
      }

      return rest_ensure_response(['success' => true, 'booking' => $row]);
    },
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('bp/v1', '/front/bookings/(?P<id>\d+)/payment-cancel', [
    'methods' => 'POST',
    'callback' => function (WP_REST_Request $req) {
      global $wpdb;
      $id = (int)$req['id'];
      $wpdb->update($wpdb->prefix . 'bp_bookings', [
        'payment_status' => 'cancelled',
        'status' => 'cancelled',
      ], ['id' => $id]);

      return rest_ensure_response(['success' => true]);
    },
    'permission_callback' => '__return_true',
  ]);
});
