<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {

  // GET schedule for agent
  register_rest_route('bp/v1', '/admin/agents/(?P<id>\d+)/schedule', [
    'methods'  => 'GET',
    'callback' => 'bp_rest_admin_get_agent_schedule',
    'permission_callback' => function () { return current_user_can('bp_manage_bookings'); },
  ]);

  // PUT (bulk save) schedule for agent
  register_rest_route('bp/v1', '/admin/agents/(?P<id>\d+)/schedule', [
    'methods'  => 'PUT',
    'callback' => 'bp_rest_admin_save_agent_schedule',
    'permission_callback' => function () { return current_user_can('bp_manage_bookings'); },
  ]);

  // POST copy schedule from another agent
  register_rest_route('bp/v1', '/admin/agents/(?P<id>\d+)/schedule/copy', [
    'methods'  => 'POST',
    'callback' => 'bp_rest_admin_copy_agent_schedule',
    'permission_callback' => function () { return current_user_can('bp_manage_bookings'); },
  ]);
});

function bp_rest_admin_get_agent_schedule(WP_REST_Request $req) {
  global $wpdb;
  $agent_id = (int)$req['id'];
  if ($agent_id <= 0) return new WP_REST_Response(['status'=>'error','message'=>'Invalid agent id'], 400);

  $t_hours  = $wpdb->prefix . 'bp_agent_working_hours';
  $t_breaks = $wpdb->prefix . 'bp_agent_breaks';

  $hours_rows = $wpdb->get_results($wpdb->prepare(
    "SELECT id, weekday, start_time, end_time, is_enabled
     FROM {$t_hours}
     WHERE agent_id=%d
     ORDER BY weekday ASC, start_time ASC",
    $agent_id
  ), ARRAY_A) ?: [];

  $break_rows = $wpdb->get_results($wpdb->prepare(
    "SELECT id, break_date, start_time, end_time, note
     FROM {$t_breaks}
     WHERE agent_id=%d
     ORDER BY break_date ASC, start_time ASC",
    $agent_id
  ), ARRAY_A) ?: [];

  $hours = [];
  for ($d = 1; $d <= 7; $d++) $hours[(string)$d] = [];
  foreach ($hours_rows as $r) {
    $d = (string)((int)$r['weekday']);
    $hours[$d][] = [
      'id' => (int)$r['id'],
      'start_time' => substr($r['start_time'], 0, 5),
      'end_time'   => substr($r['end_time'], 0, 5),
      'is_enabled' => (int)$r['is_enabled'] === 1,
    ];
  }

  $breaks = array_map(function($b){
    return [
      'id' => (int)$b['id'],
      'break_date' => $b['break_date'],
      'start_time' => substr($b['start_time'], 0, 5),
      'end_time'   => substr($b['end_time'], 0, 5),
      'note' => $b['note'] ?? '',
    ];
  }, $break_rows);

  return new WP_REST_Response(['status'=>'success','data'=>[
    'agent_id'=>$agent_id,
    'hours'=>$hours,
    'breaks'=>$breaks
  ]], 200);
}

function bp_rest_admin_save_agent_schedule(WP_REST_Request $req) {
  global $wpdb;
  $agent_id = (int)$req['id'];
  if ($agent_id <= 0) return new WP_REST_Response(['status'=>'error','message'=>'Invalid agent id'], 400);

  $body = $req->get_json_params();
  $hours = $body['hours'] ?? null;
  $breaks = $body['breaks'] ?? null;

  if (!is_array($hours) || !is_array($breaks)) {
    return new WP_REST_Response(['status'=>'error','message'=>'Invalid payload'], 400);
  }

  $t_hours  = $wpdb->prefix . 'bp_agent_working_hours';
  $t_breaks = $wpdb->prefix . 'bp_agent_breaks';

  $wpdb->query($wpdb->prepare("DELETE FROM {$t_hours} WHERE agent_id=%d", $agent_id));
  $wpdb->query($wpdb->prepare("DELETE FROM {$t_breaks} WHERE agent_id=%d", $agent_id));

  for ($d = 1; $d <= 7; $d++) {
    $dayKey = (string)$d;
    $intervals = $hours[$dayKey] ?? [];
    if (!is_array($intervals)) $intervals = [];

    foreach ($intervals as $it) {
      $enabled = !empty($it['is_enabled']) ? 1 : 0;

      $st = sanitize_text_field($it['start_time'] ?? '');
      $et = sanitize_text_field($it['end_time'] ?? '');

      if (!preg_match('/^\d{2}:\d{2}$/', $st) || !preg_match('/^\d{2}:\d{2}$/', $et)) continue;

      $st .= ':00';
      $et .= ':00';
      if (strtotime("1970-01-01 {$et}") <= strtotime("1970-01-01 {$st}")) continue;

      $wpdb->insert($t_hours, [
        'agent_id'=>$agent_id,
        'weekday'=>$d,
        'start_time'=>$st,
        'end_time'=>$et,
        'is_enabled'=>$enabled
      ], ['%d','%d','%s','%s','%d']);
    }
  }

  foreach ($breaks as $b) {
    $date = sanitize_text_field($b['break_date'] ?? '');
    $st = sanitize_text_field($b['start_time'] ?? '');
    $et = sanitize_text_field($b['end_time'] ?? '');
    $note = sanitize_text_field($b['note'] ?? '');

    $date = substr($date, 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) continue;
    if (!preg_match('/^\d{2}:\d{2}$/', $st) || !preg_match('/^\d{2}:\d{2}$/', $et)) continue;

    $st .= ':00';
    $et .= ':00';
    if (strtotime("1970-01-01 {$et}") <= strtotime("1970-01-01 {$st}")) continue;

    $wpdb->insert($t_breaks, [
      'agent_id'=>$agent_id,
      'break_date'=>$date,
      'start_time'=>$st,
      'end_time'=>$et,
      'note'=>$note
    ], ['%d','%s','%s','%s','%s']);
  }

  return new WP_REST_Response(['status'=>'success','data'=>['saved'=>true]], 200);
}

function bp_rest_admin_copy_agent_schedule(WP_REST_Request $req) {
  global $wpdb;
  $to_agent_id = (int)$req['id'];
  $body = $req->get_json_params();
  $from_agent_id = (int)($body['from_agent_id'] ?? 0);

  if ($to_agent_id <= 0 || $from_agent_id <= 0) {
    return new WP_REST_Response(['status'=>'error','message'=>'Invalid agent id(s)'], 400);
  }
  if ($to_agent_id === $from_agent_id) {
    return new WP_REST_Response(['status'=>'error','message'=>'Cannot copy from same agent'], 400);
  }

  $t_hours  = $wpdb->prefix . 'bp_agent_working_hours';
  $t_breaks = $wpdb->prefix . 'bp_agent_breaks';

  $wpdb->query($wpdb->prepare("DELETE FROM {$t_hours} WHERE agent_id=%d", $to_agent_id));
  $wpdb->query($wpdb->prepare("DELETE FROM {$t_breaks} WHERE agent_id=%d", $to_agent_id));

  $hours = $wpdb->get_results($wpdb->prepare("
    SELECT weekday, start_time, end_time, is_enabled
    FROM {$t_hours}
    WHERE agent_id=%d
  ", $from_agent_id), ARRAY_A) ?: [];

  foreach ($hours as $h) {
    $wpdb->insert($t_hours, [
      'agent_id'=>$to_agent_id,
      'weekday'=>(int)$h['weekday'],
      'start_time'=>$h['start_time'],
      'end_time'=>$h['end_time'],
      'is_enabled'=>(int)$h['is_enabled'],
    ], ['%d','%d','%s','%s','%d']);
  }

  $breaks = $wpdb->get_results($wpdb->prepare("
    SELECT break_date, start_time, end_time, note
    FROM {$t_breaks}
    WHERE agent_id=%d
  ", $from_agent_id), ARRAY_A) ?: [];

  foreach ($breaks as $b) {
    $wpdb->insert($t_breaks, [
      'agent_id'=>$to_agent_id,
      'break_date'=>$b['break_date'],
      'start_time'=>$b['start_time'],
      'end_time'=>$b['end_time'],
      'note'=>$b['note'],
    ], ['%d','%s','%s','%s','%s']);
  }

  return new WP_REST_Response(['status'=>'success','data'=>['copied'=>true]], 200);
}
