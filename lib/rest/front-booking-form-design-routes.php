<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
  register_rest_route('bp/v1', '/front/booking-form-design', [
    'methods'  => 'GET',
    'callback' => 'bp_front_get_booking_form_design',
    'permission_callback' => '__return_true',
  ]);
});

function bp_front_get_booking_form_design() {
  if (function_exists('bp_booking_form_design_default')) {
    $defaults = bp_booking_form_design_default();
  } else {
    $defaults = [
      'appearance' => [
        'primaryColor' => '#2563EB',
        'borderStyle' => 'rounded',
        'darkModeDefault' => false,
      ],
      'layout' => [
        'leftPanel' => true,
        'helpPhone' => '',
        'showSummary' => 'auto',
      ],
      'steps' => [
        ['key' => 'location', 'enabled' => true, 'title' => 'Select Location', 'subtitle' => '', 'image' => 'location-image.png', 'buttonBackLabel'=>'<- Back', 'buttonNextLabel'=>'Next ->', 'accentOverride'=>'', 'showLeftPanel'=>true, 'showHelpBox'=>true],
        ['key' => 'category', 'enabled' => true, 'title' => 'Choose Category', 'subtitle' => '', 'image' => 'service-image.png', 'buttonBackLabel'=>'<- Back', 'buttonNextLabel'=>'Next ->', 'accentOverride'=>'', 'showLeftPanel'=>true, 'showHelpBox'=>true],
        ['key' => 'service', 'enabled' => true, 'title' => 'Choose Service', 'subtitle' => '', 'image' => 'service-image.png', 'buttonBackLabel'=>'<- Back', 'buttonNextLabel'=>'Next ->', 'accentOverride'=>'', 'showLeftPanel'=>true, 'showHelpBox'=>true],
        ['key' => 'extras', 'enabled' => true, 'title' => 'Service Extras', 'subtitle' => '', 'image' => 'service-image.png', 'buttonBackLabel'=>'<- Back', 'buttonNextLabel'=>'Next ->', 'accentOverride'=>'', 'showLeftPanel'=>true, 'showHelpBox'=>true],
        ['key' => 'agents', 'enabled' => true, 'title' => 'Choose Agent', 'subtitle' => '', 'image' => 'default-avatar.jpg', 'buttonBackLabel'=>'<- Back', 'buttonNextLabel'=>'Next ->', 'accentOverride'=>'', 'showLeftPanel'=>true, 'showHelpBox'=>true],
        ['key' => 'datetime', 'enabled' => true, 'title' => 'Choose Date & Time', 'subtitle' => '', 'image' => 'blue-dot.png', 'buttonBackLabel'=>'<- Back', 'buttonNextLabel'=>'Next ->', 'accentOverride'=>'', 'showLeftPanel'=>true, 'showHelpBox'=>true],
        ['key' => 'customer', 'enabled' => true, 'title' => 'Customer Information', 'subtitle' => '', 'image' => 'default-avatar.jpg', 'buttonBackLabel'=>'<- Back', 'buttonNextLabel'=>'Next ->', 'accentOverride'=>'', 'showLeftPanel'=>true, 'showHelpBox'=>true],
        ['key' => 'payment', 'enabled' => true, 'title' => 'Payment', 'subtitle' => 'Choose a payment method', 'image' => 'service-image.png', 'buttonBackLabel'=>'<- Back', 'buttonNextLabel'=>'Next ->', 'accentOverride'=>'', 'showLeftPanel'=>true, 'showHelpBox'=>true],
        ['key' => 'review', 'enabled' => true, 'title' => 'Review Order', 'subtitle' => '', 'image' => 'white-curve.png', 'buttonBackLabel'=>'<- Back', 'buttonNextLabel'=>'Next ->', 'accentOverride'=>'', 'showLeftPanel'=>true, 'showHelpBox'=>true],
        ['key' => 'confirm', 'enabled' => true, 'title' => 'Confirmation', 'subtitle' => '', 'image' => 'logo.png', 'buttonBackLabel'=>'<- Back', 'buttonNextLabel'=>'Next ->', 'accentOverride'=>'', 'showLeftPanel'=>true, 'showHelpBox'=>true],
      ],
      'texts' => [
        'helpTitle' => 'Need help?',
        'helpPhone' => '',
        'nextLabel' => 'Next ->',
        'backLabel' => '<- Back',
      ],
    ];
  }

  $saved = get_option('bp_booking_form_design', null);
  $config = (is_array($saved)) ? wp_parse_args($saved, $defaults) : $defaults;

  return rest_ensure_response(['config' => $config]);
}
