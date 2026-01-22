<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function(){
  register_rest_route('bp/v1', '/admin/form-fields', [
    'methods'=>'GET',
    'callback'=>'bp_admin_get_form_fields',
    'permission_callback'=>'bp_admin_can_manage_settings',
  ]);

  register_rest_route('bp/v1', '/admin/form-fields', [
    'methods'=>'POST',
    'callback'=>'bp_admin_create_form_field',
    'permission_callback'=>'bp_admin_can_manage_settings',
  ]);

  register_rest_route('bp/v1', '/admin/form-fields/(?P<id>\d+)', [
    'methods'=>'PATCH',
    'callback'=>'bp_admin_update_form_field',
    'permission_callback'=>'bp_admin_can_manage_settings',
  ]);

  register_rest_route('bp/v1', '/admin/form-fields/(?P<id>\d+)', [
    'methods'=>'DELETE',
    'callback'=>'bp_admin_delete_form_field',
    'permission_callback'=>'bp_admin_can_manage_settings',
  ]);

  register_rest_route('bp/v1', '/admin/form-fields/reorder', [
    'methods'=>'POST',
    'callback'=>'bp_admin_reorder_form_fields',
    'permission_callback'=>'bp_admin_can_manage_settings',
  ]);
});

function bp_admin_can_manage_settings(){
  return current_user_can('bp_manage_settings') || current_user_can('administrator');
}

function bp_admin_get_form_fields(WP_REST_Request $req){
  global $wpdb;
  $t = $wpdb->prefix.'bp_form_fields';
  $scope = sanitize_text_field($req->get_param('scope') ?? 'customer');
  if (!in_array($scope, ['booking','customer','form'], true)) $scope='customer';

  $rows = $wpdb->get_results($wpdb->prepare("
    SELECT * FROM {$t}
    WHERE scope=%s
    ORDER BY sort_order ASC, id ASC
  ", $scope), ARRAY_A) ?: [];

  foreach($rows as &$r){
    $r['id'] = (int)$r['id'];
    $r['field_key'] = $r['field_key'] ?: ($r['name_key'] ?? '');
    $r['is_required'] = (int)($r['is_required'] ?? $r['required'] ?? 0);
    $r['is_enabled'] = (int)($r['is_enabled'] ?? $r['is_active'] ?? 0);
    $r['show_in_wizard'] = (int)($r['show_in_wizard'] ?? 1);
    $r['sort_order'] = (int)$r['sort_order'];
    $raw_options = $r['options'] ?: ($r['options_json'] ?? null);
    $r['options'] = $raw_options ? json_decode($raw_options, true) : null;
  }

  return new WP_REST_Response(['status'=>'success','data'=>$rows], 200);
}

function bp_admin_create_form_field(WP_REST_Request $req){
  global $wpdb;
  $t = $wpdb->prefix.'bp_form_fields';
  $p = $req->get_json_params();
  if (!is_array($p)) $p=[];

  $field_key = sanitize_key($p['field_key'] ?? '');
  $label = sanitize_text_field($p['label'] ?? '');
  $type = sanitize_text_field($p['type'] ?? 'text');
  $scope = sanitize_text_field($p['scope'] ?? 'customer');
  $step_key = sanitize_text_field($p['step_key'] ?? 'details');

  if (!$field_key || !$label) return new WP_REST_Response(['status'=>'error','message'=>'Missing key/label'], 400);
  if (!in_array($scope, ['booking','customer','form'], true)) $scope='customer';

  $allowed_types = ['text','email','tel','textarea','number','date','select','checkbox'];
  if (!in_array($type, $allowed_types, true)) $type='text';

  $allowed_steps = ['details','payment','summary'];
  if (!in_array($step_key, $allowed_steps, true)) $step_key = 'details';

  $options = $p['options'] ?? null;
  $options_json = $options ? wp_json_encode($options) : null;

  $now = current_time('mysql');

  $wpdb->insert($t, [
    'field_key'=>$field_key,
    'label'=>$label,
    'type'=>$type,
    'scope'=>$scope,
    'step_key'=>$step_key,
    'placeholder'=>sanitize_text_field($p['placeholder'] ?? ''),
    'options'=>$options_json,
    'is_required'=>!empty($p['is_required']) ? 1 : 0,
    'is_enabled'=>!empty($p['is_enabled']) ? 1 : 0,
    'show_in_wizard'=>!empty($p['show_in_wizard']) ? 1 : 0,
    'sort_order'=>intval($p['sort_order'] ?? 0),
    'created_at'=>$now,
    'updated_at'=>$now,
    'name_key'=>$field_key,
    'options_json'=>$options_json,
    'required'=>!empty($p['is_required']) ? 1 : 0,
    'is_active'=>!empty($p['is_enabled']) ? 1 : 0,
  ], ['%s','%s','%s','%s','%s','%s','%s','%d','%d','%d','%d','%s','%s','%s','%s','%d','%d']);

  if (!$wpdb->insert_id) return new WP_REST_Response(['status'=>'error','message'=>'Insert failed'], 500);
  return bp_admin_get_form_fields(new WP_REST_Request('GET','/bp/v1/admin/form-fields'));
}

function bp_admin_update_form_field(WP_REST_Request $req){
  global $wpdb;
  $t = $wpdb->prefix.'bp_form_fields';
  $id = (int)$req['id'];

  $p = $req->get_json_params();
  if (!is_array($p)) $p=[];

  $updates = [];
  $formats = [];

  $map = [
    'label'=>'%s',
    'type'=>'%s',
    'step_key'=>'%s',
    'placeholder'=>'%s',
    'sort_order'=>'%d',
    'is_required'=>'%d',
    'is_enabled'=>'%d',
    'show_in_wizard'=>'%d',
  ];

  foreach($map as $k=>$fmt){
    if (!array_key_exists($k, $p)) continue;
    $val = $p[$k];
    if (in_array($k, ['is_required','is_enabled','show_in_wizard'], true)) $val = !empty($val) ? 1 : 0;
    if ($k==='sort_order') $val = intval($val);
    if (in_array($k, ['label','type','step_key','placeholder'], true)) $val = sanitize_text_field($val);

    if ($k === 'type') {
      $allowed_types = ['text','email','tel','textarea','number','date','select','checkbox'];
      if (!in_array($val, $allowed_types, true)) $val = 'text';
    }
    if ($k === 'step_key') {
      $allowed_steps = ['details','payment','summary'];
      if (!in_array($val, $allowed_steps, true)) $val = 'details';
    }

    $updates[$k] = $val;
    $formats[] = $fmt;
  }

  if (array_key_exists('options', $p)) {
    $updates['options'] = $p['options'] ? wp_json_encode($p['options']) : null;
    $formats[] = '%s';
  }

  if (!$updates) return new WP_REST_Response(['status'=>'error','message'=>'No changes'], 400);

  if (array_key_exists('is_required', $updates)) {
    $updates['required'] = $updates['is_required'];
    $formats[] = '%d';
  }
  if (array_key_exists('is_enabled', $updates)) {
    $updates['is_active'] = $updates['is_enabled'];
    $formats[] = '%d';
  }
  if (array_key_exists('options', $updates)) {
    $updates['options_json'] = $updates['options'];
    $formats[] = '%s';
  }

  $updates['updated_at'] = current_time('mysql');
  $formats[] = '%s';

  $ok = $wpdb->update($t, $updates, ['id'=>$id], $formats, ['%d']);
  if ($ok === false) return new WP_REST_Response(['status'=>'error','message'=>'Update failed'], 500);

  return new WP_REST_Response(['status'=>'success'], 200);
}

function bp_admin_delete_form_field(WP_REST_Request $req){
  global $wpdb;
  $t = $wpdb->prefix.'bp_form_fields';
  $id = (int)$req['id'];
  $wpdb->delete($t, ['id'=>$id], ['%d']);
  return new WP_REST_Response(['status'=>'success'], 200);
}

function bp_admin_reorder_form_fields(WP_REST_Request $req){
  global $wpdb;
  $t = $wpdb->prefix.'bp_form_fields';
  $p = $req->get_json_params();
  $ids = $p['ids'] ?? [];
  if (!is_array($ids)) $ids=[];

  $order = 10;
  foreach($ids as $id){
    $id = (int)$id;
    if ($id<=0) continue;
    $wpdb->update($t, ['sort_order'=>$order,'updated_at'=>current_time('mysql')], ['id'=>$id], ['%d','%s'], ['%d']);
    $order += 10;
  }

  return new WP_REST_Response(['status'=>'success'], 200);
}
