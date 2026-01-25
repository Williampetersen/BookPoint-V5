<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
  register_rest_route('bp/v1', '/admin/booking-form-design', [
    [
      'methods'  => 'GET',
      'callback' => function () {
        if (!current_user_can('manage_options')) {
          return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }

        $defaults = bp_booking_form_design_defaults();
        $saved = get_option('bp_booking_form_design', null);

        $value = is_array($saved) ? wp_parse_args($saved, $defaults) : $defaults;
        return rest_ensure_response($value);
      },
      'permission_callback' => '__return_true',
    ],
    [
      'methods'  => 'POST',
      'callback' => function (WP_REST_Request $req) {
        if (!current_user_can('manage_options')) {
          return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }

        $data = $req->get_json_params();
        if (!is_array($data)) {
          return new WP_Error('bad_request', 'Invalid payload', ['status' => 400]);
        }

        if (!isset($data['steps']) || !is_array($data['steps'])) {
          return new WP_Error('bad_request', 'Missing steps', ['status' => 400]);
        }

        update_option('bp_booking_form_design', $data, false);
        return rest_ensure_response(['ok' => true]);
      },
      'permission_callback' => '__return_true',
    ],
  ]);
});

function bp_booking_form_design_defaults() {
  return [
    'appearance' => [
      'theme' => 'light',
      'accent' => '#2563EB',
      'borderStyle' => 'rounded',
      'buttonStyle' => 'filled',
      'fontScale' => 'md',
    ],
    'layout' => [
      'leftPanel' => true,
      'helpPhone' => '',
      'showSummary' => 'auto',
    ],
    'steps' => [
      ['key'=>'location','enabled'=>true,'title'=>'Select Location','subtitle'=>'','image'=>'location-image.png'],
      ['key'=>'category','enabled'=>true,'title'=>'Choose Category','subtitle'=>'','image'=>'service-image.png'],
      ['key'=>'service','enabled'=>true,'title'=>'Choose Service','subtitle'=>'','image'=>'service-image.png','options'=>['showServiceCategories'=>true,'showServiceCount'=>false]],
      ['key'=>'extras','enabled'=>true,'title'=>'Service Extras','subtitle'=>'','image'=>'service-image.png'],
      ['key'=>'agent','enabled'=>true,'title'=>'Choose Agent','subtitle'=>'','image'=>'default-avatar.jpg'],
      ['key'=>'datetime','enabled'=>true,'title'=>'Choose date & time','subtitle'=>'','image'=>'','options'=>['timeSlotsAs'=>'boxes','style'=>'modern','hideUnavailable'=>false,'disableAutoFirstSlot'=>true,'timeFormat'=>'HH:mm']],
      ['key'=>'customer','enabled'=>true,'title'=>'Customer Information','subtitle'=>'','image'=>''],
      ['key'=>'review','enabled'=>true,'title'=>'Review Order','subtitle'=>'','image'=>''],
      ['key'=>'confirmation','enabled'=>true,'title'=>'Confirmation','subtitle'=>'','image'=>''],
    ],
  ];
}
