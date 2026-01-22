<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function(){
  register_rest_route('bp/v1', '/public/form-fields', [
    'methods'=>'GET',
    'callback'=>'bp_public_get_form_fields',
    'permission_callback'=>'__return_true',
  ]);
});

function bp_public_get_form_fields(WP_REST_Request $req){
  global $wpdb;
  $t = $wpdb->prefix.'bp_form_fields';

  $rows = $wpdb->get_results("
    SELECT * FROM {$t}
    WHERE is_enabled=1 AND show_in_wizard=1
    ORDER BY scope ASC, sort_order ASC, id ASC
  ", ARRAY_A) ?: [];

  foreach($rows as &$r){
    $r['id'] = (int)$r['id'];
    $r['field_key'] = $r['field_key'] ?: ($r['name_key'] ?? '');
    $r['is_required'] = (int)($r['is_required'] ?? $r['required'] ?? 0);
    $raw_options = $r['options'] ?: ($r['options_json'] ?? null);
    $r['options'] = $raw_options ? json_decode($raw_options, true) : null;
  }

  return new WP_REST_Response(['status'=>'success','data'=>$rows], 200);
}
