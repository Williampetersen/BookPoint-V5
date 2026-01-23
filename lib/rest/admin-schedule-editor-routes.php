<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {

  // GET schedule for agent
  register_rest_route('bp/v1', '/admin/agents/(?P<id>\d+)/schedule', [
    'methods'  => 'GET',
    'callback' => 'bp_rest_admin_get_agent_schedule',
    'permission_callback' => function () {
      return current_user_can('bp_manage_bookings') || current_user_can('bp_manage_services') || current_user_can('bp_manage_settings');
    },
  ]);

  // PUT (bulk save) schedule for agent
  register_rest_route('bp/v1', '/admin/agents/(?P<id>\d+)/schedule', [
    'methods'  => ['PUT', 'POST'],
    'callback' => 'bp_rest_admin_save_agent_schedule',
    'permission_callback' => function () {
      return current_user_can('bp_manage_bookings') || current_user_can('bp_manage_services') || current_user_can('bp_manage_settings');
    },
  ]);

  // POST copy schedule from another agent
  register_rest_route('bp/v1', '/admin/agents/(?P<id>\d+)/schedule/copy', [
    'methods'  => 'POST',
    'callback' => 'bp_rest_admin_copy_agent_schedule',
    'permission_callback' => function () {
      return current_user_can('bp_manage_bookings') || current_user_can('bp_manage_services') || current_user_can('bp_manage_settings');
    },
  ]);
});

function bp_rest_schedule_tables_ensure(): void {
  global $wpdb;
  $charset = $wpdb->get_charset_collate();
  $t_hours  = $wpdb->prefix . 'bp_agent_working_hours';
  $t_breaks = $wpdb->prefix . 'bp_agent_breaks';

  $hours_exists = (string)$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t_hours)) === $t_hours;
  $breaks_exists = (string)$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t_breaks)) === $t_breaks;

  if ($hours_exists && $breaks_exists) return;

  require_once ABSPATH . 'wp-admin/includes/upgrade.php';

  if (!$hours_exists) {
    dbDelta("CREATE TABLE {$t_hours} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      agent_id BIGINT UNSIGNED NOT NULL,
      weekday TINYINT NOT NULL,
      start_time TIME NOT NULL,
      end_time TIME NOT NULL,
      is_enabled TINYINT NOT NULL DEFAULT 1,
      PRIMARY KEY (id),
      KEY agent_weekday (agent_id, weekday)
    ) {$charset};");
  }

  if (!$breaks_exists) {
    dbDelta("CREATE TABLE {$t_breaks} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      agent_id BIGINT UNSIGNED NOT NULL,
      break_date DATE NOT NULL,
      start_time TIME NOT NULL,
      end_time TIME NOT NULL,
      note VARCHAR(255) NULL,
      PRIMARY KEY (id),
      KEY agent_date (agent_id, break_date)
    ) {$charset};");
  }
}

function bp_rest_admin_get_agent_schedule(WP_REST_Request $req) {
  global $wpdb;
  bp_rest_schedule_tables_ensure();
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
  bp_rest_schedule_tables_ensure();
  $agent_id = (int)$req['id'];
  if ($agent_id <= 0) return new WP_REST_Response(['status'=>'error','message'=>'Invalid agent id'], 400);

  $body = $req->get_json_params();
  if (!is_array($body) || empty($body)) {
    $raw = $req->get_body();
    $decoded = json_decode($raw ?: '', true);
    if (is_array($decoded)) $body = $decoded;
  }
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

  if (!empty($wpdb->last_error)) {
    return new WP_REST_Response(['status'=>'error','message'=>$wpdb->last_error], 500);
  }

  return new WP_REST_Response(['status'=>'success','data'=>['saved'=>true]], 200);
}

function bp_rest_admin_copy_agent_schedule(WP_REST_Request $req) {
  global $wpdb;
  
  if (!current_user_can('administrator') && !current_user_can('bp_manage_settings')) {
    return new WP_REST_Response(['status'=>'error','message'=>'Forbidden'], 403);
  }
  
  $to_agent_id = (int)$req['id'];
  $body = $req->get_json_params();
  if (!is_array($body) || empty($body)) {
    $raw = $req->get_body();
    $decoded = json_decode($raw ?: '', true);
    if (is_array($decoded)) $body = $decoded;
  }

  $tS = $wpdb->prefix . 'bp_schedules';

  // Read global schedules
  $global = $wpdb->get_results("
    SELECT day_of_week,start_time,end_time,is_enabled
    FROM {$tS}
    WHERE agent_id IS NULL OR agent_id=0
    ORDER BY day_of_week ASC, start_time ASC
  ", ARRAY_A) ?: [];

  // Overwrite agent schedules
  $wpdb->delete($tS, ['agent_id'=>$to_agent_id]);
  foreach($global as $g){
    $wpdb->insert($tS, [
      'agent_id' => $to_agent_id,
      'day_of_week' => (int)$g['day_of_week'],
      'start_time' => $g['start_time'],
      'end_time' => $g['end_time'],
      'is_enabled' => (int)$g['is_enabled'],
    ]);
  }

  return new WP_REST_Response(['status'=>'success','data'=>['copied'=>count($global)]], 200);
}
