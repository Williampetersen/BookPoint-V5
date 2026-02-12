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
        ['key' => 'location', 'enabled' => true, 'title' => 'Select Location', 'subtitle' => '', 'image' => 'locations.svg', 'buttonBackLabel'=>'<- Back', 'buttonNextLabel'=>'Next ->', 'accentOverride'=>'', 'showLeftPanel'=>true, 'showHelpBox'=>true],
        ['key' => 'category', 'enabled' => true, 'title' => 'Choose Category', 'subtitle' => '', 'image' => 'categories.svg', 'buttonBackLabel'=>'<- Back', 'buttonNextLabel'=>'Next ->', 'accentOverride'=>'', 'showLeftPanel'=>true, 'showHelpBox'=>true],
        ['key' => 'service', 'enabled' => true, 'title' => 'Choose Service', 'subtitle' => '', 'image' => 'services.svg', 'buttonBackLabel'=>'<- Back', 'buttonNextLabel'=>'Next ->', 'accentOverride'=>'', 'showLeftPanel'=>true, 'showHelpBox'=>true],
        ['key' => 'extras', 'enabled' => true, 'title' => 'Service Extras', 'subtitle' => '', 'image' => 'service-extras.svg', 'buttonBackLabel'=>'<- Back', 'buttonNextLabel'=>'Next ->', 'accentOverride'=>'', 'showLeftPanel'=>true, 'showHelpBox'=>true],
        ['key' => 'agents', 'enabled' => true, 'title' => 'Choose Agent', 'subtitle' => '', 'image' => 'agents.svg', 'buttonBackLabel'=>'<- Back', 'buttonNextLabel'=>'Next ->', 'accentOverride'=>'', 'showLeftPanel'=>true, 'showHelpBox'=>true],
        ['key' => 'datetime', 'enabled' => true, 'title' => 'Choose Date & Time', 'subtitle' => '', 'image' => 'calendar.svg', 'buttonBackLabel'=>'<- Back', 'buttonNextLabel'=>'Next ->', 'accentOverride'=>'', 'showLeftPanel'=>true, 'showHelpBox'=>true],
        ['key' => 'customer', 'enabled' => true, 'title' => 'Customer Information', 'subtitle' => '', 'image' => 'customers.svg', 'buttonBackLabel'=>'<- Back', 'buttonNextLabel'=>'Next ->', 'accentOverride'=>'', 'showLeftPanel'=>true, 'showHelpBox'=>true],
        ['key' => 'payment', 'enabled' => true, 'title' => 'Payment', 'subtitle' => 'Choose a payment method', 'image' => 'payment.svg', 'buttonBackLabel'=>'<- Back', 'buttonNextLabel'=>'Next ->', 'accentOverride'=>'', 'showLeftPanel'=>true, 'showHelpBox'=>true],
        ['key' => 'review', 'enabled' => true, 'title' => 'Review Order', 'subtitle' => '', 'image' => 'bookings.svg', 'buttonBackLabel'=>'<- Back', 'buttonNextLabel'=>'Next ->', 'accentOverride'=>'', 'showLeftPanel'=>true, 'showHelpBox'=>true],
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

  if (function_exists('bp_booking_form_design_upgrade_step_images') && is_array($config)) {
    $config = bp_booking_form_design_upgrade_step_images($config);
  }

  return rest_ensure_response(['config' => $config]);
}
