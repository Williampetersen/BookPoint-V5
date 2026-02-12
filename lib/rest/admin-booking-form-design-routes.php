<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
  register_rest_route('bp/v1', '/admin/booking-form-design', [
    [
      'methods'  => 'GET',
      'callback' => 'bp_admin_get_booking_form_design',
      'permission_callback' => 'bp_admin_can_manage',
    ],
    [
      'methods'  => 'POST',
      'callback' => 'bp_admin_save_booking_form_design',
      'permission_callback' => 'bp_admin_can_manage',
      'args' => [
        'config' => [
          'required' => true,
          'type' => 'object',
        ],
      ],
    ],
  ]);

  register_rest_route('bp/v1', '/admin/booking-form-design-reset', [
    'methods'  => 'POST',
    'callback' => function () {
      if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', 'Forbidden', ['status'=>403]);
      }
      $cfg = bp_booking_form_design_default();
      update_option('bp_booking_form_design', $cfg, false);
      return rest_ensure_response(['success'=>true,'config'=>$cfg]);
    },
    'permission_callback' => '__return_true',
  ]);
});

function bp_admin_can_manage() {
  return current_user_can('manage_options');
}

function bp_booking_form_design_default() {
  if (function_exists('bp_default_design_from_file')) {
    $file_design = bp_default_design_from_file();
    if (is_array($file_design)) {
      return $file_design;
    }
  }
  return [
    'version' => 1,
    'appearance' => [
      'primaryColor' => '#2563EB',
      'borderStyle' => 'rounded',
      'darkModeDefault' => false,
    ],
    'steps' => [
      ['key'=>'location', 'enabled'=>true, 'title'=>'Select Location', 'subtitle'=>'Please select a location', 'image'=>'locations.svg', 'buttonBackLabel'=>'<- Back', 'buttonNextLabel'=>'Next ->', 'accentOverride'=>'', 'showLeftPanel'=>true, 'showHelpBox'=>true],
      ['key'=>'category', 'enabled'=>true, 'title'=>'Choose Category', 'subtitle'=>'Select a category', 'image'=>'categories.svg', 'buttonBackLabel'=>'<- Back', 'buttonNextLabel'=>'Next ->', 'accentOverride'=>'', 'showLeftPanel'=>true, 'showHelpBox'=>true],
      ['key'=>'service', 'enabled'=>true, 'title'=>'Choose Service', 'subtitle'=>'Select a service', 'image'=>'services.svg', 'buttonBackLabel'=>'<- Back', 'buttonNextLabel'=>'Next ->', 'accentOverride'=>'', 'showLeftPanel'=>true, 'showHelpBox'=>true],
      ['key'=>'extras', 'enabled'=>true, 'title'=>'Service Extras', 'subtitle'=>'Pick extras', 'image'=>'service-extras.svg', 'buttonBackLabel'=>'<- Back', 'buttonNextLabel'=>'Next ->', 'accentOverride'=>'', 'showLeftPanel'=>true, 'showHelpBox'=>true],
      ['key'=>'agents', 'enabled'=>true, 'title'=>'Choose Agent', 'subtitle'=>'Pick your agent', 'image'=>'agents.svg', 'buttonBackLabel'=>'<- Back', 'buttonNextLabel'=>'Next ->', 'accentOverride'=>'', 'showLeftPanel'=>true, 'showHelpBox'=>true],
      ['key'=>'datetime', 'enabled'=>true, 'title'=>'Date & Time', 'subtitle'=>'Pick an available slot', 'image'=>'calendar.svg', 'buttonBackLabel'=>'<- Back', 'buttonNextLabel'=>'Next ->', 'accentOverride'=>'', 'showLeftPanel'=>true, 'showHelpBox'=>true],
      ['key'=>'customer', 'enabled'=>true, 'title'=>'Customer Info', 'subtitle'=>'Enter your details', 'image'=>'customers.svg', 'buttonBackLabel'=>'<- Back', 'buttonNextLabel'=>'Next ->', 'accentOverride'=>'', 'showLeftPanel'=>true, 'showHelpBox'=>true],
      ['key'=>'payment', 'enabled'=>true, 'title'=>'Payment', 'subtitle'=>'Choose a payment method', 'image'=>'payment.svg', 'buttonBackLabel'=>'<- Back', 'buttonNextLabel'=>'Next ->', 'accentOverride'=>'', 'showLeftPanel'=>true, 'showHelpBox'=>true],
      ['key'=>'review', 'enabled'=>true, 'title'=>'Review Order', 'subtitle'=>'Confirm everything', 'image'=>'bookings.svg', 'buttonBackLabel'=>'<- Back', 'buttonNextLabel'=>'Next ->', 'accentOverride'=>'', 'showLeftPanel'=>true, 'showHelpBox'=>true],
      ['key'=>'confirm', 'enabled'=>true, 'title'=>'Confirmation', 'subtitle'=>'Done', 'image'=>'logo.png', 'buttonBackLabel'=>'<- Back', 'buttonNextLabel'=>'Next ->', 'accentOverride'=>'', 'showLeftPanel'=>true, 'showHelpBox'=>true],
    ],
    'texts' => [
      'helpTitle' => 'Need help?',
      'helpPhone' => '+1 234 567 89',
      'nextLabel' => 'Next ->',
      'backLabel' => '<- Back',
    ],
    'fieldsLayout' => [
      'customer' => [
        'fields' => [
          ['id'=>'first_name','required'=>true,'width'=>'half'],
          ['id'=>'last_name','required'=>false,'width'=>'half'],
          ['id'=>'email','required'=>true,'width'=>'full'],
          ['id'=>'phone','required'=>false,'width'=>'full'],
        ],
      ],
      'booking' => [
        'fields' => [
          ['id'=>'notes','required'=>false,'width'=>'full'],
        ],
      ],
    ],
  ];
}

function bp_booking_form_design_step_image_defaults(): array {
  return [
    'location' => 'locations.svg',
    'category' => 'categories.svg',
    'service' => 'services.svg',
    'extras' => 'service-extras.svg',
    'agents' => 'agents.svg',
    'datetime' => 'calendar.svg',
    'customer' => 'customers.svg',
    'payment' => 'payment.svg',
    'review' => 'bookings.svg',
    'confirm' => 'logo.png',
  ];
}

function bp_booking_form_design_upgrade_step_images(array $config): array {
  $changed = false;
  $defaults = bp_booking_form_design_step_image_defaults();
  $legacy = [
    'location-image.png' => true,
    'service-image.png' => true,
    'default-avatar.jpg' => true,
    'blue-dot.png' => true,
    'white-curve.png' => true,
    'payment_now.png' => true,
    'payment_now_w_paypal.png' => true,
  ];

  if (!isset($config['steps']) || !is_array($config['steps'])) return $config;

  foreach ($config['steps'] as $idx => $step) {
    if (!is_array($step)) continue;
    $key = isset($step['key']) ? (string)$step['key'] : '';
    if ($key === '' || !isset($defaults[$key])) continue;

    $hasCustomMedia = !empty($step['imageUrl']) || !empty($step['imageId']);
    if ($hasCustomMedia) continue;

    $img = isset($step['image']) ? trim((string)$step['image']) : '';
    if ($img === '' || isset($legacy[$img])) {
      $config['steps'][$idx]['image'] = $defaults[$key];
      $changed = true;
    }
  }

  if ($changed) {
    $config['version'] = isset($config['version']) ? (int)$config['version'] : 1;
  }

  return $config;
}

function bp_admin_get_booking_form_design(\WP_REST_Request $req) {
  $config = get_option('bp_booking_form_design', null);
  if (!$config || !is_array($config)) {
    $config = bp_booking_form_design_default();
    update_option('bp_booking_form_design', $config, false);
  } else {
    $upgraded = bp_booking_form_design_upgrade_step_images($config);
    if ($upgraded !== $config) {
      $config = $upgraded;
      update_option('bp_booking_form_design', $config, false);
    }
  }
  return rest_ensure_response([
    'success' => true,
    'config' => $config,
  ]);
}

function bp_admin_save_booking_form_design(\WP_REST_Request $req) {
  $body = $req->get_json_params();
  $config = isset($body['config']) ? $body['config'] : null;

  if (!$config || !is_array($config)) {
    return new \WP_REST_Response([
      'success' => false,
      'message' => 'Invalid config payload'
    ], 400);
  }

  $json = wp_json_encode($config);
  if (strlen($json) > 200000) {
    return new \WP_REST_Response([
      'success' => false,
      'message' => 'Config too large'
    ], 413);
  }

  update_option('bp_booking_form_design', $config, false);

  return rest_ensure_response([
    'success' => true,
    'config' => $config,
  ]);
}
