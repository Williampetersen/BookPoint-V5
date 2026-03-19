<?php
if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- This file's wpdb SQL paths interpolate only hardcoded plugin table names with a sanitized WordPress prefix; dynamic values remain prepared or static by design.

function pointlybooking_rest_front_booking_access(WP_REST_Request $req) {
  // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names in this function are validated local plugin table names built from hardcoded plugin suffixes.
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

  $bookings_table = $wpdb->prefix . 'pointlybooking_bookings';
  if (!preg_match('/^[A-Za-z0-9_]+$/', $bookings_table)) {
    return new WP_Error('server_error', 'Invalid bookings table', ['status' => 500]);
  }

  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
  $stored_key = $wpdb->get_var(
    $wpdb->prepare("SELECT manage_key FROM {$bookings_table} WHERE id=%d", $id)
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
      $bookings_table = $wpdb->prefix . 'pointlybooking_bookings';
      if (!preg_match('/^[A-Za-z0-9_]+$/', $bookings_table)) {
        return new WP_Error('server_error', 'Invalid bookings table', ['status' => 500]);
      }

      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
      $row = $wpdb->get_row(
        $wpdb->prepare(
          "SELECT id, status, payment_method, payment_status, payment_provider_ref FROM {$bookings_table} WHERE id=%d",
          $id
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
      $bookings_table = $wpdb->prefix . 'pointlybooking_bookings';
      if (!preg_match('/^[A-Za-z0-9_]+$/', $bookings_table)) {
        return new WP_Error('server_error', 'Invalid bookings table', ['status' => 500]);
      }

      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
      $row = $wpdb->get_row(
        $wpdb->prepare("SELECT id, payment_status FROM {$bookings_table} WHERE id=%d", $id),
        ARRAY_A
      );
      if (!$row) {
        return new WP_Error('not_found', 'Booking not found', ['status' => 404]);
      }
      if (($row['payment_status'] ?? '') === 'paid') {
        return new WP_Error('already_paid', 'Paid bookings cannot be cancelled here.', ['status' => 409]);
      }

      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
      $wpdb->update($bookings_table, [
        'payment_status' => 'cancelled',
        'status' => 'cancelled',
      ], ['id' => $id], ['%s', '%s'], ['%d']);

      return rest_ensure_response(['success' => true]);
    },
    'permission_callback' => 'pointlybooking_rest_front_booking_access',
  ]);
});
