<?php
if (!defined('ABSPATH')) exit;

add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values, $order) {
  if (!empty($values['bp_booking_id'])) {
    $item->add_meta_data('bp_booking_id', (int)$values['bp_booking_id'], true);
  }
}, 10, 4);

add_action('woocommerce_payment_complete', function ($order_id) {
  $order = wc_get_order($order_id);
  if (!$order) return;

  foreach ($order->get_items() as $item) {
    $booking_id = (int)$item->get_meta('bp_booking_id');
    if ($booking_id > 0) {
      bp_mark_booking_paid_and_confirmed($booking_id);
    }
  }
});

function bp_mark_booking_paid_and_confirmed($booking_id) {
  if (class_exists('BP_BookingModel')) {
    BP_BookingModel::update_status((int)$booking_id, 'confirmed');
  }

  global $wpdb;
  $table = $wpdb->prefix . 'bp_bookings';
  $wpdb->update(
    $table,
    [
      'payment_status' => 'paid',
      'payment_method' => 'woocommerce',
      'updated_at' => current_time('mysql'),
    ],
    ['id' => (int)$booking_id],
    ['%s','%s','%s'],
    ['%d']
  );
}
