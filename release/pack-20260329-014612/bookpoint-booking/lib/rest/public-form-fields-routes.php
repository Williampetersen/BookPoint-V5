<?php
defined('ABSPATH') || exit;
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- This file's wpdb SQL paths interpolate only hardcoded plugin table names with a sanitized WordPress prefix; dynamic values remain prepared or static by design.

add_action('rest_api_init', function(){
  register_rest_route('pointly-booking/v1', '/public/form-fields', [
    'methods'=>'GET',
    'callback'=>'pointlybooking_public_get_form_fields',
    'permission_callback'=>'__return_true',
  ]);
});

function pointlybooking_public_get_form_fields(WP_REST_Request $req){
  // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names in this function are validated local plugin table names built from hardcoded plugin suffixes.
  global $wpdb;
  $form_fields_table = $wpdb->prefix . 'pointlybooking_form_fields';
  if (!preg_match('/^[A-Za-z0-9_]+$/', $form_fields_table)) {
    return new WP_REST_Response(['status'=>'success','data'=>[]], 200);
  }

  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
  $rows = $wpdb->get_results(
    "SELECT * FROM {$form_fields_table}
    WHERE is_enabled=1 AND show_in_wizard=1
    ORDER BY scope ASC, sort_order ASC, id ASC",
    ARRAY_A
  ) ?: [];

  foreach($rows as &$r){
    $r['id'] = (int)$r['id'];
    $r['field_key'] = $r['field_key'] ?: ($r['name_key'] ?? '');
    $r['is_required'] = (int)($r['is_required'] ?? $r['required'] ?? 0);
    $raw_options = $r['options'] ?: ($r['options_json'] ?? null);
    $r['options'] = $raw_options ? json_decode($raw_options, true) : null;
  }

  return new WP_REST_Response(['status'=>'success','data'=>$rows], 200);
}
