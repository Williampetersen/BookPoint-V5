<?php
if (!defined('ABSPATH')) exit;

add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values, $order) {
  if (!empty($values['pointlybooking_booking_id'])) {
    $item->add_meta_data('pointlybooking_booking_id', (int)$values['pointlybooking_booking_id'], true);
  }
}, 10, 4);

add_action('woocommerce_payment_complete', function ($order_id) {
  $order = wc_get_order($order_id);
  if (!$order) return;

  foreach ($order->get_items() as $item) {
    $booking_id = (int)$item->get_meta('pointlybooking_booking_id');
    if ($booking_id > 0) {
      pointlybooking_mark_booking_paid_and_confirmed($booking_id);
    }
  }
});

function pointlybooking_mark_booking_paid_and_confirmed($booking_id) {
  if (class_exists('POINTLYBOOKING_BookingModel')) {
    POINTLYBOOKING_BookingModel::update_status((int)$booking_id, 'confirmed');
  }

  global $wpdb;
  $table = $wpdb->prefix . 'pointlybooking_bookings';
  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
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
