<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function(){
  register_rest_route('bp/v1', '/admin/settings', [
    'methods' => 'GET',
    'callback' => function(){
      if (!current_user_can('bp_manage_settings') && !current_user_can('administrator')) {
        return new WP_REST_Response(['status'=>'error','message'=>'Forbidden'], 403);
      }
      return new WP_REST_Response(['status'=>'success','data'=>BP_SettingsHelper::get_all()], 200);
    },
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('bp/v1', '/admin/settings', [
    'methods' => 'POST',
    'callback' => function(WP_REST_Request $req){
      if (!current_user_can('bp_manage_settings') && !current_user_can('administrator')) {
        return new WP_REST_Response(['status'=>'error','message'=>'Forbidden'], 403);
      }
      $p = $req->get_json_params();
      if (!is_array($p)) $p = [];

      $interval = isset($p['slot_interval_minutes']) ? intval($p['slot_interval_minutes']) : null;
      if ($interval !== null) {
        if ($interval < 5) $interval = 5;
        if ($interval > 60) $interval = 60;
      }

      $currency = isset($p['currency']) ? strtoupper(sanitize_text_field($p['currency'])) : null;
      $currency_pos = isset($p['currency_position']) ? sanitize_text_field($p['currency_position']) : null;

      $allowed_pos = ['before','after'];
      if ($currency_pos !== null && !in_array($currency_pos, $allowed_pos, true)) {
        $currency_pos = 'before';
      }

      $updates = [];
      if ($interval !== null) $updates['slot_interval_minutes'] = $interval;
      if ($currency !== null) $updates['currency'] = $currency;
      if ($currency_pos !== null) $updates['currency_position'] = $currency_pos;

      $merged = BP_SettingsHelper::merge($updates);
      return new WP_REST_Response(['status'=>'success','data'=>$merged], 200);
    },
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('bp/v1', '/public/settings', [
    'methods' => 'GET',
    'callback' => function(){
      $data = [
        'slot_interval_minutes' => intval(BP_SettingsHelper::get('slot_interval_minutes', 15)),
        'currency' => BP_SettingsHelper::get('currency', 'USD'),
        'currency_position' => BP_SettingsHelper::get('currency_position', 'before'),
      ];
      return new WP_REST_Response(['status'=>'success','data'=>$data], 200);
    },
    'permission_callback' => '__return_true',
  ]);
});
