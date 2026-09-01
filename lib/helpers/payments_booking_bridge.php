<?php
defined('ABSPATH') || exit;

function pointlybooking_create_pending_payment_booking_from_payload(array $payload, string $method) {
  if (!function_exists('pointlybooking_insert_booking_from_payload')) {
    return new WP_Error('booking_handler_missing', 'Booking handler missing', ['status' => 500]);
  }

  // Amount/currency are intentionally omitted here: pointlybooking_insert_booking_from_payload()
  // computes the authoritative total server-side (service price + valid extras + validated promo
  // code) rather than trusting client-submitted totals for what actually gets charged.
  $overrides = [
    'status' => 'pending_payment',
    'payment_method' => $method,
    'payment_status' => 'unpaid',
  ];

  return pointlybooking_insert_booking_from_payload($payload, $overrides);
}

function pointlybooking_confirm_booking_paid(int $booking_id, string $provider_ref = ''): void {
  if ($booking_id <= 0) return;

  POINTLYBOOKING_BookingModel::update_status($booking_id, 'confirmed');

  global $wpdb;
  $table = $wpdb->prefix . 'pointlybooking_bookings';
  $now = current_time('mysql');

  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
  $wpdb->update(
    $table,
    [
      'payment_status' => 'paid',
      'payment_provider_ref' => $provider_ref ? sanitize_text_field($provider_ref) : null,
      'updated_at' => $now,
    ],
    ['id' => $booking_id],
    ['%s','%s','%s'],
    ['%d']
  );
}
