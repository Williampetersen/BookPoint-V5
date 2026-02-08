<?php
defined('ABSPATH') || exit;

function bp_rest_admin_license_payload(): array {
  return [
    'key' => BP_LicenseHelper::get_key(),
    'status' => BP_LicenseHelper::status(),
    'server_base_url' => BP_LicenseHelper::get_saved_api_base(),
    'server_base_effective' => BP_LicenseHelper::api_base(),
    'validate_url' => BP_LicenseHelper::api_validate_url(),
    'deactivate_url' => BP_LicenseHelper::api_deactivate_url(),
    'updates_url' => BP_LicenseHelper::api_updates_url(),
    'ajax_validate_url' => BP_LicenseHelper::api_ajax_validate_url(),
    'ajax_deactivate_url' => BP_LicenseHelper::api_ajax_deactivate_url(),
    'public_validate_urls' => BP_LicenseHelper::api_public_validate_urls(),
    'public_deactivate_urls' => BP_LicenseHelper::api_public_deactivate_urls(),
    'last_request_url' => (string)get_option('bp_license_last_request_url', ''),
    'checked_at' => (int)get_option('bp_license_checked_at', 0),
    'last_error' => (string)get_option('bp_license_last_error', ''),
    'plan' => (string)get_option('bp_license_plan', ''),
    'expires_at' => (string)get_option('bp_license_expires_at', ''),
    'licensed_domain' => (string)get_option('bp_license_licensed_domain', ''),
    'instance_id' => (string)get_option('bp_license_instance_id', ''),
    'data' => (string)get_option('bp_license_data_json', ''),
  ];
}

add_action('rest_api_init', function(){
  register_rest_route('bp/v1', '/admin/license', [
    'methods' => 'GET',
    'callback' => function(){
      if (!current_user_can('bp_manage_settings') && !current_user_can('administrator')) {
        return new WP_REST_Response(['status'=>'error','message'=>'Forbidden'], 403);
      }

      $data = bp_rest_admin_license_payload();

      return new WP_REST_Response(['status'=>'success','data'=>$data], 200);
    },
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('bp/v1', '/admin/license', [
    'methods' => 'POST',
    'callback' => function(WP_REST_Request $req){
      if (!current_user_can('bp_manage_settings') && !current_user_can('administrator')) {
        return new WP_REST_Response(['status'=>'error','message'=>'Forbidden'], 403);
      }

      $p = $req->get_json_params();
      if (!is_array($p)) $p = [];
      $key = sanitize_text_field($p['key'] ?? '');
      $serverBase = sanitize_text_field($p['server_base_url'] ?? '');
      BP_LicenseHelper::set_saved_api_base($serverBase);
      BP_LicenseHelper::set_key($key);
      if ($key !== '') {
        BP_LicenseHelper::validate(true);
      }

      $data = bp_rest_admin_license_payload();

      return new WP_REST_Response(['status'=>'success','data'=>$data], 200);
    },
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('bp/v1', '/admin/license/validate', [
    'methods' => 'POST',
    'callback' => function(WP_REST_Request $req){
      if (!current_user_can('bp_manage_settings') && !current_user_can('administrator')) {
        return new WP_REST_Response(['status'=>'error','message'=>'Forbidden'], 403);
      }

      $p = $req->get_json_params();
      if (!is_array($p)) $p = [];
      if (isset($p['server_base_url'])) {
        BP_LicenseHelper::set_saved_api_base(sanitize_text_field($p['server_base_url']));
      }
      if (isset($p['key'])) {
        BP_LicenseHelper::set_key(sanitize_text_field($p['key']));
      }

      BP_LicenseHelper::validate(true);

      $data = bp_rest_admin_license_payload();

      return new WP_REST_Response(['status'=>'success','data'=>$data], 200);
    },
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('bp/v1', '/admin/license/activate', [
    'methods' => 'POST',
    'callback' => function(WP_REST_Request $req){
      if (!current_user_can('bp_manage_settings') && !current_user_can('administrator')) {
        return new WP_REST_Response(['status'=>'error','message'=>'Forbidden'], 403);
      }

      $p = $req->get_json_params();
      if (!is_array($p)) $p = [];
      if (isset($p['server_base_url'])) {
        BP_LicenseHelper::set_saved_api_base(sanitize_text_field($p['server_base_url']));
      }
      if (isset($p['key'])) {
        BP_LicenseHelper::set_key(sanitize_text_field($p['key']));
      }

      BP_LicenseHelper::activate();
      $data = bp_rest_admin_license_payload();
      return new WP_REST_Response(['status'=>'success','data'=>$data], 200);
    },
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('bp/v1', '/admin/license/deactivate', [
    'methods' => 'POST',
    'callback' => function(WP_REST_Request $req){
      if (!current_user_can('bp_manage_settings') && !current_user_can('administrator')) {
        return new WP_REST_Response(['status'=>'error','message'=>'Forbidden'], 403);
      }

      $p = $req->get_json_params();
      if (!is_array($p)) $p = [];
      if (isset($p['server_base_url'])) {
        BP_LicenseHelper::set_saved_api_base(sanitize_text_field($p['server_base_url']));
      }
      if (isset($p['key'])) {
        BP_LicenseHelper::set_key(sanitize_text_field($p['key']));
      }

      BP_LicenseHelper::deactivate();
      $data = bp_rest_admin_license_payload();
      return new WP_REST_Response(['status'=>'success','data'=>$data], 200);
    },
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('bp/v1', '/admin/settings', [
    'methods' => 'GET',
    'callback' => function(){
      if (!current_user_can('bp_manage_settings') && !current_user_can('administrator')) {
        return new WP_REST_Response(['status'=>'error','message'=>'Forbidden'], 403);
      }
      $data = BP_SettingsHelper::get_all();
      $data['bp_remove_data_on_uninstall'] = (int)get_option('bp_remove_data_on_uninstall', 0);
      return new WP_REST_Response(['status'=>'success','data'=>$data], 200);
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

      $sanitize_value = function($val) use (&$sanitize_value) {
        if (is_array($val)) {
          $out = [];
          foreach ($val as $k => $v) {
            $out[$k] = $sanitize_value($v);
          }
          return $out;
        }
        if (is_bool($val) || is_int($val) || is_float($val)) return $val;
        if (is_string($val)) return sanitize_text_field($val);
        return $val;
      };

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
      foreach ($p as $k => $v) {
        $key = sanitize_key($k);
        if ($key === '') continue;
        $updates[$key] = $sanitize_value($v);
      }

      if ($interval !== null) $updates['slot_interval_minutes'] = $interval;
      if ($currency !== null) $updates['currency'] = $currency;
      if ($currency_pos !== null) $updates['currency_position'] = $currency_pos;

      $remove_flag = null;
      if (array_key_exists('bp_remove_data_on_uninstall', $updates)) {
        $remove_flag = (int)!empty($updates['bp_remove_data_on_uninstall']);
        unset($updates['bp_remove_data_on_uninstall']);
      }
      if (array_key_exists('remove_data_on_uninstall', $updates)) {
        $remove_flag = (int)!empty($updates['remove_data_on_uninstall']);
        unset($updates['remove_data_on_uninstall']);
      }

      if ($remove_flag !== null) {
        update_option('bp_remove_data_on_uninstall', $remove_flag, false);
      }

      $merged = BP_SettingsHelper::merge($updates);
      if ($remove_flag !== null) {
        $merged['bp_remove_data_on_uninstall'] = $remove_flag;
      }
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
