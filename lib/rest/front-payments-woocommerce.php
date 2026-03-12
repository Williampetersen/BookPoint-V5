<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
  register_rest_route('pointly-booking/v1', '/front/payments/woocommerce/start', [
    'methods' => 'POST',
    'callback' => 'pointlybooking_wc_start_checkout',
    'permission_callback' => '__return_true',
  ]);
});

function pointlybooking_wc_start_checkout(WP_REST_Request $req) {
  if (!class_exists('WooCommerce')) {
    return new WP_Error('no_wc', 'WooCommerce is not active.', ['status' => 400]);
  }

  $settings = POINTLYBOOKING_SettingsHelper::get_all();
  $product_id = (int)($settings['payments_wc_product_id'] ?? 0);
  if ($product_id <= 0) {
    return new WP_Error('no_product', 'WooCommerce product not configured.', ['status' => 400]);
  }

  $p = $req->get_json_params();
  if (!is_array($p)) $p = [];

  $overrides = [
    'status' => 'pending_payment',
    'payment_method' => 'woocommerce',
    'payment_status' => 'pending',
  ];

  $result = pointlybooking_insert_booking_from_payload($p, $overrides);
  if (is_wp_error($result)) return $result;
  $manage_url = isset($result['manage_url']) ? esc_url_raw((string)$result['manage_url']) : '';
  $manage_key = '';
  if ($manage_url !== '') {
    $parts = wp_parse_url($manage_url);
    if (!empty($parts['query'])) {
      parse_str($parts['query'], $query_vars);
      $manage_key = sanitize_text_field((string)($query_vars['key'] ?? ''));
    }
  }

  if (!WC()->cart) {
    return new WP_Error('no_cart', 'WooCommerce cart is not available.', ['status' => 400]);
  }

  WC()->cart->empty_cart();
  WC()->cart->add_to_cart($product_id, 1, 0, [], [
    'pointlybooking_booking_id' => (int)$result['booking_id'],
    'pointlybooking_total' => (float)($p['total_price'] ?? $p['total'] ?? 0),
    'pointlybooking_currency' => sanitize_text_field($p['currency'] ?? ''),
  ]);

  return rest_ensure_response([
    'success' => true,
    'booking_id' => (int)$result['booking_id'],
    'manage_key' => $manage_key,
    'checkout_url' => wc_get_checkout_url(),
  ]);
}
