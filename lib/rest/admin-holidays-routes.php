<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {

  register_rest_route('bp/v1', '/admin/holidays', [
    'methods' => 'GET',
    'callback' => function() {
      global $wpdb;
      $t = $wpdb->prefix . 'bp_holidays';
      $rows = $wpdb->get_results("SELECT * FROM {$t} ORDER BY start_date ASC", ARRAY_A) ?: [];
      return new WP_REST_Response(['status'=>'success','data'=>$rows], 200);
    },
    'permission_callback' => function(){ return current_user_can('bp_manage_settings'); },
  ]);

  register_rest_route('bp/v1', '/admin/holidays', [
    'methods' => 'POST',
    'callback' => 'bp_rest_admin_holiday_create',
    'permission_callback' => function(){ return current_user_can('bp_manage_settings'); },
  ]);

  register_rest_route('bp/v1', '/admin/holidays/(?P<id>\d+)', [
    'methods' => 'PATCH',
    'callback' => 'bp_rest_admin_holiday_patch',
    'permission_callback' => function(){ return current_user_can('bp_manage_settings'); },
  ]);

  register_rest_route('bp/v1', '/admin/holidays/(?P<id>\d+)', [
    'methods' => 'DELETE',
    'callback' => 'bp_rest_admin_holiday_delete',
    'permission_callback' => function(){ return current_user_can('bp_manage_settings'); },
  ]);
});

function bp_rest_admin_holiday_create(WP_REST_Request $req){
  global $wpdb;
  $t = $wpdb->prefix . 'bp_holidays';
  $b = $req->get_json_params();

  $title = sanitize_text_field($b['title'] ?? 'Holiday');
  $start = substr(sanitize_text_field($b['start_date'] ?? ''),0,10);
  $end   = substr(sanitize_text_field($b['end_date'] ?? ''),0,10);
  $rec   = !empty($b['is_recurring_yearly']) ? 1 : 0;
  $en    = !empty($b['is_enabled']) ? 1 : 0;

  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$end)) {
    return new WP_REST_Response(['status'=>'error','message'=>'Invalid dates'], 400);
  }

  if (strtotime($end) < strtotime($start)) {
    return new WP_REST_Response(['status'=>'error','message'=>'End must be after start'], 400);
  }

  $wpdb->insert($t, [
    'title'=>$title,
    'start_date'=>$start,
    'end_date'=>$end,
    'is_recurring_yearly'=>$rec,
    'is_enabled'=>$en
  ], ['%s','%s','%s','%d','%d']);

  return new WP_REST_Response(['status'=>'success','data'=>['id'=>$wpdb->insert_id]], 200);
}

function bp_rest_admin_holiday_patch(WP_REST_Request $req){
  global $wpdb;
  $t = $wpdb->prefix . 'bp_holidays';
  $id = (int)$req['id'];
  $b = $req->get_json_params();

  $updates = [];
  $formats = [];

  if (isset($b['title'])) { $updates['title']=sanitize_text_field($b['title']); $formats[]='%s'; }
  if (isset($b['start_date'])) { $updates['start_date']=substr(sanitize_text_field($b['start_date']),0,10); $formats[]='%s'; }
  if (isset($b['end_date'])) { $updates['end_date']=substr(sanitize_text_field($b['end_date']),0,10); $formats[]='%s'; }
  if (isset($b['is_recurring_yearly'])) { $updates['is_recurring_yearly']=!empty($b['is_recurring_yearly'])?1:0; $formats[]='%d'; }
  if (isset($b['is_enabled'])) { $updates['is_enabled']=!empty($b['is_enabled'])?1:0; $formats[]='%d'; }

  if (empty($updates)) return new WP_REST_Response(['status'=>'success','data'=>['updated'=>false]], 200);

  $ok = $wpdb->update($t, $updates, ['id'=>$id], $formats, ['%d']);
  if ($ok === false) return new WP_REST_Response(['status'=>'error','message'=>'Update failed'], 500);

  return new WP_REST_Response(['status'=>'success','data'=>['updated'=>true]], 200);
}

function bp_rest_admin_holiday_delete(WP_REST_Request $req){
  global $wpdb;
  $t = $wpdb->prefix . 'bp_holidays';
  $id = (int)$req['id'];
  $wpdb->delete($t, ['id'=>$id], ['%d']);
  return new WP_REST_Response(['status'=>'success','data'=>['deleted'=>true]], 200);
}
