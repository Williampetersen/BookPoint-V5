<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
  register_rest_route('bp/v1', '/front/settings', [
    'methods' => 'GET',
    'callback' => function () {
      $settings = BP_SettingsHelper::get_all();
      $enabled = $settings['payments_enabled_methods'] ?? ['cash'];
      if (!is_array($enabled)) $enabled = ['cash'];

      $require = !empty($settings['payments_require_payment_to_confirm']) ? 1 : 0;
      $payments_enabled = !array_key_exists('payments_enabled', $settings) || !empty($settings['payments_enabled']) ? 1 : 0;
      $stripe_mode = $settings['stripe_mode'] ?? 'test';
      $stripe_publishable = get_option('bp_stripe_publishable_key', '');
      if ($stripe_publishable === '') {
        if ($stripe_mode === 'live') {
          $stripe_publishable = $settings['stripe_live_publishable_key'] ?? '';
        } else {
          $stripe_publishable = $settings['stripe_test_publishable_key'] ?? '';
        }
      }

      return rest_ensure_response([
        'success' => true,
        'settings' => [
          'currency' => $settings['currency'] ?? 'USD',
          'currency_position' => $settings['currency_position'] ?? 'before',
          'payments_enabled_methods' => $enabled,
          'payments_enabled' => $payments_enabled,
          'payments_default_method' => $settings['payments_default_method'] ?? 'cash',
          'payments_require_payment_to_confirm' => $require,
          'stripe_mode' => $settings['stripe_mode'] ?? 'test',
          'paypal_mode' => $settings['paypal_mode'] ?? 'test',
          'stripe_publishable_key' => $stripe_publishable,
        ],
      ]);
    },
    'permission_callback' => '__return_true',
  ]);
});
