<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {

  register_rest_route('bp/v1', '/admin/holidays', [
    'methods' => 'GET',
    'callback' => 'bp_rest_admin_holidays_list',
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
  $rec   = !empty($b['is_recurring']) || !empty($b['is_recurring_yearly']) ? 1 : 0;
  $en    = isset($b['is_enabled']) ? (!empty($b['is_enabled']) ? 1 : 0) : 1;
  $agent_id = !empty($b['agent_id']) ? (int)$b['agent_id'] : null;

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
    'agent_id'=>$agent_id,
    'is_recurring'=>$rec,
    'is_recurring_yearly'=>$rec,
    'is_enabled'=>$en,
    'created_at'=> current_time('mysql'),
    'updated_at'=> current_time('mysql'),
  ], ['%s','%s','%s','%d','%d','%d','%d','%s','%s']);

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
  if (isset($b['agent_id'])) { $updates['agent_id']=(int)$b['agent_id']; $formats[]='%d'; }
  if (isset($b['is_recurring'])) { $updates['is_recurring']=!empty($b['is_recurring'])?1:0; $formats[]='%d'; }
  if (isset($b['is_recurring_yearly'])) { $updates['is_recurring_yearly']=!empty($b['is_recurring_yearly'])?1:0; $formats[]='%d'; }
  if (isset($b['is_enabled'])) { $updates['is_enabled']=!empty($b['is_enabled'])?1:0; $formats[]='%d'; }
  if (!empty($updates)) { $updates['updated_at'] = current_time('mysql'); $formats[]='%s'; }

  if (empty($updates)) return new WP_REST_Response(['status'=>'success','data'=>['updated'=>false]], 200);

  $ok = $wpdb->update($t, $updates, ['id'=>$id], $formats, ['%d']);
  if ($ok === false) return new WP_REST_Response(['status'=>'error','message'=>'Update failed'], 500);

  return new WP_REST_Response(['status'=>'success','data'=>['updated'=>true]], 200);
}

function bp_rest_admin_holidays_list(WP_REST_Request $req) {
  global $wpdb;
  $t = $wpdb->prefix . 'bp_holidays';

  $year = (int)$req->get_param('year');
  $agent_id = (int)$req->get_param('agent_id');

  $where = "WHERE 1=1";
  $params = [];

  if ($agent_id > 0) {
    $where .= " AND (agent_id IS NULL OR agent_id = %d)";
    $params[] = $agent_id;
  } else {
    $where .= " AND agent_id IS NULL";
  }

  if ($year > 0) {
    $start = sprintf('%04d-01-01', $year);
    $end = sprintf('%04d-12-31', $year);
    $where .= " AND (start_date <= %s AND end_date >= %s)";
    $params[] = $end;
    $params[] = $start;
  }

  $sql = "SELECT * FROM {$t} {$where} ORDER BY start_date ASC";
  $rows = !empty($params) ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

  return new WP_REST_Response(['status'=>'success','data'=>$rows ?: []], 200);
}

function bp_rest_admin_holiday_delete(WP_REST_Request $req){
  global $wpdb;
  $t = $wpdb->prefix . 'bp_holidays';
  $id = (int)$req['id'];
  $wpdb->delete($t, ['id'=>$id], ['%d']);
  return new WP_REST_Response(['status'=>'success','data'=>['deleted'=>true]], 200);
}
