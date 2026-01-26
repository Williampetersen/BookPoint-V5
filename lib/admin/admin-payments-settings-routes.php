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
  $payments_enabled = !array_key_exists('payments_enabled', $settings) || !empty($settings['payments_enabled']) ? 1 : 0;

  return [
    'payments_enabled' => $payments_enabled,
    'enabled_methods' => $enabled,
    'default_method' => $default,
    'require_payment_to_confirm' => $require,
    'woocommerce' => [
      'product_id' => (int)($settings['payments_wc_product_id'] ?? 0),
    ],
    'stripe' => [
      'enabled' => !empty($settings['payments_stripe_enabled']) ? 1 : 0,
      'mode' => $settings['stripe_mode'] ?? 'test',
      'test_secret_key' => $settings['stripe_test_secret_key'] ?? ($settings['payments_stripe_test_secret_key'] ?? ''),
      'test_publishable_key' => $settings['stripe_test_publishable_key'] ?? '',
      'live_secret_key' => $settings['stripe_live_secret_key'] ?? ($settings['payments_stripe_live_secret_key'] ?? ''),
      'live_publishable_key' => $settings['stripe_live_publishable_key'] ?? '',
      'webhook_secret' => $settings['stripe_webhook_secret'] ?? ($settings['payments_stripe_webhook_secret'] ?? ''),
      'success_url' => $settings['stripe_success_url'] ?? ($settings['payments_stripe_success_url'] ?? ''),
      'cancel_url' => $settings['stripe_cancel_url'] ?? ($settings['payments_stripe_cancel_url'] ?? ''),
    ],
    'paypal' => [
      'enabled' => !empty($settings['payments_paypal_enabled']) ? 1 : 0,
      'mode' => $settings['paypal_mode'] ?? ($settings['payments_paypal_mode'] ?? 'test'),
      'client_id' => $settings['paypal_client_id'] ?? ($settings['payments_paypal_client_id'] ?? ''),
      'secret' => $settings['paypal_secret'] ?? ($settings['payments_paypal_secret'] ?? ''),
      'return_url' => $settings['paypal_return_url'] ?? ($settings['payments_paypal_return_url'] ?? ''),
      'cancel_url' => $settings['paypal_cancel_url'] ?? ($settings['payments_paypal_cancel_url'] ?? ''),
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
  $settings['payments_enabled'] = !empty($values['payments_enabled']) ? 1 : 0;

  if (isset($values['woocommerce']['product_id'])) {
    $settings['payments_wc_product_id'] = (int)$values['woocommerce']['product_id'];
  }
  if (isset($values['stripe']['enabled'])) {
    $settings['payments_stripe_enabled'] = !empty($values['stripe']['enabled']) ? 1 : 0;
  }
  if (isset($values['paypal']['enabled'])) {
    $settings['payments_paypal_enabled'] = !empty($values['paypal']['enabled']) ? 1 : 0;
  }

  if (isset($values['stripe']) && is_array($values['stripe'])) {
    $settings['stripe_mode'] = ($values['stripe']['mode'] ?? 'test') === 'live' ? 'live' : 'test';
    $settings['stripe_test_secret_key'] = sanitize_text_field($values['stripe']['test_secret_key'] ?? '');
    $settings['stripe_test_publishable_key'] = sanitize_text_field($values['stripe']['test_publishable_key'] ?? '');
    $settings['stripe_live_secret_key'] = sanitize_text_field($values['stripe']['live_secret_key'] ?? '');
    $settings['stripe_live_publishable_key'] = sanitize_text_field($values['stripe']['live_publishable_key'] ?? '');
    $settings['stripe_webhook_secret'] = sanitize_text_field($values['stripe']['webhook_secret'] ?? '');
    $settings['stripe_success_url'] = esc_url_raw($values['stripe']['success_url'] ?? '');
    $settings['stripe_cancel_url'] = esc_url_raw($values['stripe']['cancel_url'] ?? '');

    $settings['payments_stripe_test_secret_key'] = $settings['stripe_test_secret_key'];
    $settings['payments_stripe_live_secret_key'] = $settings['stripe_live_secret_key'];
    $settings['payments_stripe_webhook_secret'] = $settings['stripe_webhook_secret'];
    $settings['payments_stripe_success_url'] = $settings['stripe_success_url'];
    $settings['payments_stripe_cancel_url'] = $settings['stripe_cancel_url'];
  }

  if (isset($values['paypal']) && is_array($values['paypal'])) {
    $settings['paypal_mode'] = ($values['paypal']['mode'] ?? 'test') === 'live' ? 'live' : 'test';
    $settings['paypal_client_id'] = sanitize_text_field($values['paypal']['client_id'] ?? '');
    $settings['paypal_secret'] = sanitize_text_field($values['paypal']['secret'] ?? '');
    $settings['paypal_return_url'] = esc_url_raw($values['paypal']['return_url'] ?? '');
    $settings['paypal_cancel_url'] = esc_url_raw($values['paypal']['cancel_url'] ?? '');

    $settings['payments_paypal_mode'] = $settings['paypal_mode'];
    $settings['payments_paypal_client_id'] = $settings['paypal_client_id'];
    $settings['payments_paypal_secret'] = $settings['paypal_secret'];
    $settings['payments_paypal_return_url'] = $settings['paypal_return_url'];
    $settings['payments_paypal_cancel_url'] = $settings['paypal_cancel_url'];
  }

  return $settings;
}
