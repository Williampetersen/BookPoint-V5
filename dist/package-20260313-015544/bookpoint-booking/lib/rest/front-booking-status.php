<?php
if (!defined('ABSPATH')) exit;

function pointlybooking_rest_front_booking_access(WP_REST_Request $req) {
  global $wpdb;

  $id = absint($req['id']);
  if ($id <= 0) {
    return new WP_Error('bad_request', 'Invalid booking ID', ['status' => 400]);
  }

  $key = sanitize_text_field((string) $req->get_param('key'));
  if ($key === '') {
    $key = sanitize_text_field((string) $req->get_param('manage_key'));
  }
  if ($key === '') {
    return new WP_Error('forbidden', 'Invalid booking key', ['status' => 403]);
  }

  $table = $wpdb->prefix . 'pointlybooking_bookings';
  $stored_key = $wpdb->get_var(
    pointlybooking_prepare_query_with_identifiers(
      "SELECT manage_key FROM %i WHERE id=%d",
      [$table],
      [$id]
    )
  );

  if (!is_string($stored_key) || $stored_key === '' || !hash_equals($stored_key, $key)) {
    return new WP_Error('forbidden', 'Invalid booking key', ['status' => 403]);
  }

  return true;
}

add_action('rest_api_init', function () {
  register_rest_route('pointly-booking/v1', '/front/bookings/(?P<id>\d+)/status', [
    'methods' => 'GET',
    'callback' => function (WP_REST_Request $req) {
      global $wpdb;

      $id = absint($req['id']);
      $table = $wpdb->prefix . 'pointlybooking_bookings';
      $row = $wpdb->get_row(
        pointlybooking_prepare_query_with_identifiers(
          "SELECT id, status, payment_method, payment_status, payment_provider_ref FROM %i WHERE id=%d",
          [$table],
          [$id]
        ),
        ARRAY_A
      );

      if (!$row) {
        return new WP_Error('not_found', 'Booking not found', ['status' => 404]);
      }

      return rest_ensure_response(['success' => true, 'booking' => $row]);
    },
    'permission_callback' => 'pointlybooking_rest_front_booking_access',
  ]);

  register_rest_route('pointly-booking/v1', '/front/bookings/(?P<id>\d+)/payment-cancel', [
    'methods' => 'POST',
    'callback' => function (WP_REST_Request $req) {
      global $wpdb;

      $id = absint($req['id']);
      $table = $wpdb->prefix . 'pointlybooking_bookings';
      $row = $wpdb->get_row(
        pointlybooking_prepare_query_with_identifiers(
          "SELECT id, payment_status FROM %i WHERE id=%d",
          [$table],
          [$id]
        ),
        ARRAY_A
      );
      if (!$row) {
        return new WP_Error('not_found', 'Booking not found', ['status' => 404]);
      }
      if (($row['payment_status'] ?? '') === 'paid') {
        return new WP_Error('already_paid', 'Paid bookings cannot be cancelled here.', ['status' => 409]);
      }

      $wpdb->update($table, [
        'payment_status' => 'cancelled',
        'status' => 'cancelled',
      ], ['id' => $id], ['%s', '%s'], ['%d']);

      return rest_ensure_response(['success' => true]);
    },
    'permission_callback' => 'pointlybooking_rest_front_booking_access',
  ]);
});
