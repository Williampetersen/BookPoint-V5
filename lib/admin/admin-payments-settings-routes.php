<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
  register_rest_route('bp/v1', '/admin/settings/payments', [
    'methods' => 'GET',
    'callback' => function () {
      if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
      }

      $settings = get_option('bp_settings', []);
      $payments = bp_payments_settings_from_all($settings);
      return rest_ensure_response(['success' => true, 'payments' => $payments]);
    },
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('bp/v1', '/admin/settings/payments', [
    'methods' => 'POST',
    'callback' => function (WP_REST_Request $req) {
      if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
      }

      $body = $req->get_json_params();
      $values = isset($body['payments']) && is_array($body['payments']) ? $body['payments'] : [];

      $settings = get_option('bp_settings', []);
      $settings = bp_apply_payments_settings($settings, $values);
      update_option('bp_settings', $settings, false);

      $payments = bp_payments_settings_from_all($settings);
      return rest_ensure_response(['success' => true, 'payments' => $payments]);
    },
    'permission_callback' => '__return_true',
  ]);
});

function bp_payments_settings_from_all($settings) {
  $enabled = $settings['payments_enabled_methods'] ?? ['cash', 'woocommerce', 'stripe', 'paypal', 'free'];
  if (!is_array($enabled)) $enabled = ['cash'];

  $known = ['cash', 'woocommerce', 'stripe', 'paypal', 'free'];
  $enabled = array_values(array_intersect($enabled, $known));
  if (!$enabled) $enabled = ['cash'];

  $default = $settings['payments_default_method'] ?? 'cash';
  if (!in_array($default, $known, true)) $default = 'cash';

  $require = !empty($settings['payments_require_payment_to_confirm']) ? 1 : 0;

  return [
    'enabled_methods' => $enabled,
    'default_method' => $default,
    'require_payment_to_confirm' => $require,
    'woocommerce' => [
      'product_id' => (int)($settings['payments_wc_product_id'] ?? 0),
    ],
    'stripe' => [
      'enabled' => !empty($settings['payments_stripe_enabled']) ? 1 : 0,
    ],
    'paypal' => [
      'enabled' => !empty($settings['payments_paypal_enabled']) ? 1 : 0,
    ],
  ];
}

function bp_apply_payments_settings($settings, $values) {
  $known = ['cash', 'woocommerce', 'stripe', 'paypal', 'free'];

  $enabled = isset($values['enabled_methods']) && is_array($values['enabled_methods'])
    ? $values['enabled_methods']
    : ['cash'];
  $enabled = array_values(array_intersect($enabled, $known));
  if (!$enabled) $enabled = ['cash'];

  $default = isset($values['default_method'])
    ? sanitize_text_field($values['default_method'])
    : 'cash';
  if (!in_array($default, $known, true)) $default = 'cash';

  $settings['payments_enabled_methods'] = $enabled;
  $settings['payments_default_method'] = $default;
  $settings['payments_require_payment_to_confirm'] = !empty($values['require_payment_to_confirm']) ? 1 : 0;

  if (isset($values['woocommerce']['product_id'])) {
    $settings['payments_wc_product_id'] = (int)$values['woocommerce']['product_id'];
  }
  if (isset($values['stripe']['enabled'])) {
    $settings['payments_stripe_enabled'] = !empty($values['stripe']['enabled']) ? 1 : 0;
  }
  if (isset($values['paypal']['enabled'])) {
    $settings['payments_paypal_enabled'] = !empty($values['paypal']['enabled']) ? 1 : 0;
  }

  return $settings;
}
