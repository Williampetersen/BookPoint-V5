<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {

  register_rest_route('pointly-booking/v1', '/admin/holidays', [
    'methods' => 'GET',
    'callback' => 'pointlybooking_rest_admin_holidays_list',
    'permission_callback' => function(){ return current_user_can('pointlybooking_manage_settings'); },
  ]);

  register_rest_route('pointly-booking/v1', '/admin/holidays', [
    'methods' => 'POST',
    'callback' => 'pointlybooking_rest_admin_holiday_create',
    'permission_callback' => function(){ return current_user_can('pointlybooking_manage_settings'); },
  ]);

  register_rest_route('pointly-booking/v1', '/admin/holidays/(?P<id>\d+)', [
    'methods' => 'PATCH',
    'callback' => 'pointlybooking_rest_admin_holiday_patch',
    'permission_callback' => function(){ return current_user_can('pointlybooking_manage_settings'); },
  ]);

  register_rest_route('pointly-booking/v1', '/admin/holidays/(?P<id>\d+)', [
    'methods' => 'DELETE',
    'callback' => 'pointlybooking_rest_admin_holiday_delete',
    'permission_callback' => function(){ return current_user_can('pointlybooking_manage_settings'); },
  ]);
});

function pointlybooking_admin_holidays_normalize_ymd(string $value): string {
  $value = substr(sanitize_text_field($value), 0, 10);
  if (!function_exists('pointlybooking_is_valid_ymd')) {
    return '';
  }
  if (!pointlybooking_is_valid_ymd($value)) {
    return '';
  }
  return $value;
}

function pointlybooking_rest_admin_holiday_create(WP_REST_Request $req){
  global $wpdb;
  $t = $wpdb->prefix . 'pointlybooking_holidays';
  $b = $req->get_json_params();
  if (!is_array($b)) {
    $b = [];
  }

  $title = sanitize_text_field($b['title'] ?? 'Holiday');
  $start = pointlybooking_admin_holidays_normalize_ymd((string)($b['start_date'] ?? ''));
  $end   = pointlybooking_admin_holidays_normalize_ymd((string)($b['end_date'] ?? ''));
  $rec   = !empty($b['is_recurring']) || !empty($b['is_recurring_yearly']) ? 1 : 0;
  $en    = isset($b['is_enabled']) ? (!empty($b['is_enabled']) ? 1 : 0) : 1;
  $agent_id = !empty($b['agent_id']) ? (int)$b['agent_id'] : null;

  if ($start === '' || $end === '') {
    return new WP_REST_Response(['status'=>'error','message'=>'Invalid dates'], 400);
  }

  if ($end < $start) {
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

function pointlybooking_rest_admin_holiday_patch(WP_REST_Request $req){
  global $wpdb;
  $t = $wpdb->prefix . 'pointlybooking_holidays';
  $id = (int)$req['id'];
  $b = $req->get_json_params();
  if (!is_array($b)) {
    $b = [];
  }

  $updates = [];
  $formats = [];

  if (isset($b['title'])) { $updates['title']=sanitize_text_field($b['title']); $formats[]='%s'; }
  if (isset($b['start_date'])) {
    $normalized_start = pointlybooking_admin_holidays_normalize_ymd((string)$b['start_date']);
    if ($normalized_start === '') {
      return new WP_REST_Response(['status'=>'error','message'=>'Invalid start_date'], 400);
    }
    $updates['start_date'] = $normalized_start;
    $formats[] = '%s';
  }
  if (isset($b['end_date'])) {
    $normalized_end = pointlybooking_admin_holidays_normalize_ymd((string)$b['end_date']);
    if ($normalized_end === '') {
      return new WP_REST_Response(['status'=>'error','message'=>'Invalid end_date'], 400);
    }
    $updates['end_date'] = $normalized_end;
    $formats[] = '%s';
  }
  if (isset($b['agent_id'])) { $updates['agent_id']=(int)$b['agent_id']; $formats[]='%d'; }
  if (isset($b['is_recurring'])) { $updates['is_recurring']=!empty($b['is_recurring'])?1:0; $formats[]='%d'; }
  if (isset($b['is_recurring_yearly'])) { $updates['is_recurring_yearly']=!empty($b['is_recurring_yearly'])?1:0; $formats[]='%d'; }
  if (isset($b['is_enabled'])) { $updates['is_enabled']=!empty($b['is_enabled'])?1:0; $formats[]='%d'; }
  if (!empty($updates)) { $updates['updated_at'] = current_time('mysql'); $formats[]='%s'; }

  if (empty($updates)) return new WP_REST_Response(['status'=>'success','data'=>['updated'=>false]], 200);
  if (!preg_match('/^[A-Za-z0-9_]+$/', $t)) {
    return new WP_REST_Response(['status'=>'error','message'=>'Invalid holidays table'], 500);
  }

  $quoted_table = '`' . str_replace('`', '``', $t) . '`';

  $current = $wpdb->get_row(
    $wpdb->prepare("SELECT start_date, end_date FROM {$quoted_table} WHERE id = %d", $id),
    ARRAY_A
  );
  if (!$current) {
    return new WP_REST_Response(['status'=>'error','message'=>'Holiday not found'], 404);
  }

  $final_start = isset($updates['start_date']) ? (string)$updates['start_date'] : (string)($current['start_date'] ?? '');
  $final_end = isset($updates['end_date']) ? (string)$updates['end_date'] : (string)($current['end_date'] ?? '');
  if ($final_start !== '' && $final_end !== '' && $final_end < $final_start) {
    return new WP_REST_Response(['status'=>'error','message'=>'End must be after start'], 400);
  }

  $ok = $wpdb->update($t, $updates, ['id'=>$id], $formats, ['%d']);
  if ($ok === false) return new WP_REST_Response(['status'=>'error','message'=>'Update failed'], 500);

  return new WP_REST_Response(['status'=>'success','data'=>['updated'=>true]], 200);
}

function pointlybooking_rest_admin_holidays_list(WP_REST_Request $req) {
  global $wpdb;
  $t = pointlybooking_table('holidays');
  if (!preg_match('/^[A-Za-z0-9_]+$/', $t)) {
    return new WP_REST_Response(['status'=>'success','data'=>[]], 200);
  }

  $quoted_table = '`' . str_replace('`', '``', $t) . '`';

  $year = (int)$req->get_param('year');
  $agent_id = (int)$req->get_param('agent_id');
  $filter_agent = $agent_id > 0 ? 1 : 0;

  $start = '';
  $end = '';
  if ($year > 0) {
    $start = sprintf('%04d-01-01', $year);
    $end = sprintf('%04d-12-31', $year);
  }
  $filter_year = $year > 0 ? 1 : 0;

  $rows = $wpdb->get_results(
    $wpdb->prepare(
      "SELECT * FROM {$quoted_table}
       WHERE (
         (%d = 1 AND (agent_id IS NULL OR agent_id = %d))
         OR (%d = 0 AND agent_id IS NULL)
       )
       AND (%d = 0 OR (start_date <= %s AND end_date >= %s))
       ORDER BY start_date ASC",
      $filter_agent,
      $agent_id,
      $filter_agent,
      $filter_year,
      $end,
      $start
    ),
    ARRAY_A
  );

  return new WP_REST_Response(['status'=>'success','data'=>$rows ?: []], 200);
}

function pointlybooking_rest_admin_holiday_delete(WP_REST_Request $req){
  global $wpdb;
  $t = $wpdb->prefix . 'pointlybooking_holidays';
  $id = (int)$req['id'];
  $wpdb->delete($t, ['id'=>$id], ['%d']);
  return new WP_REST_Response(['status'=>'success','data'=>['deleted'=>true]], 200);
}
