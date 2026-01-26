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

      return rest_ensure_response([
        'success' => true,
        'settings' => [
          'currency' => $settings['currency'] ?? 'USD',
          'currency_position' => $settings['currency_position'] ?? 'before',
          'payments_enabled_methods' => $enabled,
          'payments_default_method' => $settings['payments_default_method'] ?? 'cash',
          'payments_require_payment_to_confirm' => $require,
        ],
      ]);
    },
    'permission_callback' => '__return_true',
  ]);
});
