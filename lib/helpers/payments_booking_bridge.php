<?php
defined('ABSPATH') || exit;

function bp_create_pending_payment_booking_from_payload(array $payload, string $method) {
  if (!function_exists('bp_insert_booking_from_payload')) {
    return new WP_Error('booking_handler_missing', 'Booking handler missing', ['status' => 500]);
  }

  $amount = isset($payload['total_price'])
    ? (float)$payload['total_price']
    : (float)($payload['total'] ?? 0);
  $currency = sanitize_text_field($payload['currency'] ?? '');

  $overrides = [
    'status' => 'pending_payment',
    'payment_method' => $method,
    'payment_status' => 'pending',
    'payment_amount' => $amount,
  ];
  if ($currency !== '') {
    $overrides['payment_currency'] = $currency;
  }

  return bp_insert_booking_from_payload($payload, $overrides);
}

function bp_confirm_booking_paid(int $booking_id, string $provider_ref = ''): void {
  if ($booking_id <= 0) return;

  BP_BookingModel::update_status($booking_id, 'confirmed');

  global $wpdb;
  $table = $wpdb->prefix . 'bp_bookings';
  $now = current_time('mysql');

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
