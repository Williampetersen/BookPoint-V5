<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {
  register_rest_route('bp/v1', '/admin/schedule', [
    'methods' => 'GET',
    'callback' => 'bp_rest_admin_schedule_get',
    'permission_callback' => function () { return current_user_can('bp_manage_settings') || current_user_can('bp_manage_bookings'); },
    'args' => [
      'agent_id' => ['required' => false],
    ],
  ]);

  register_rest_route('bp/v1', '/admin/schedule', [
    'methods' => ['POST','PUT'],
    'callback' => 'bp_rest_admin_schedule_save',
    'permission_callback' => function () { return current_user_can('bp_manage_settings') || current_user_can('bp_manage_bookings'); },
  ]);

  register_rest_route('bp/v1', '/admin/schedule/unavailable', [
    'methods' => 'GET',
    'callback' => 'bp_rest_admin_unavailable_blocks',
    'permission_callback' => function () { return current_user_can('bp_manage_bookings'); },
    'args' => [
      'start' => ['required'=>true],
      'end'   => ['required'=>true],
      'agent_id' => ['required'=>true],
    ],
  ]);
});

function bp_rest_admin_schedule_get(WP_REST_Request $req) {
  global $wpdb;

  $agent_id = (int)$req->get_param('agent_id');
  $t = $wpdb->prefix . 'bp_schedules';

  $schedule = [];
  for ($d = 1; $d <= 7; $d++) $schedule[(string)$d] = [];

  $rows = [];
  if ((string)$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t)) === $t) {
    if ($agent_id > 0) {
      $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$t} WHERE agent_id=%d ORDER BY day_of_week ASC, start_time ASC",
        $agent_id
      ), ARRAY_A) ?: [];
    }

    if (empty($rows)) {
      $rows = $wpdb->get_results(
        "SELECT * FROM {$t} WHERE agent_id IS NULL ORDER BY day_of_week ASC, start_time ASC",
        ARRAY_A
      ) ?: [];
    }
  }

  if (!empty($rows)) {
    foreach ($rows as $r) {
      $day = (string)(int)($r['day_of_week'] ?? 0);
      if (!isset($schedule[$day])) continue;

      $breaks = [];
      if (!empty($r['breaks_json'])) {
        $decoded = json_decode((string)$r['breaks_json'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
          foreach ($decoded as $b) {
            $st = isset($b['start']) ? $b['start'] : ($b['start_time'] ?? '');
            $et = isset($b['end']) ? $b['end'] : ($b['end_time'] ?? '');
            $st = substr(trim((string)$st), 0, 5);
            $et = substr(trim((string)$et), 0, 5);
            if (!preg_match('/^\d{2}:\d{2}$/', $st) || !preg_match('/^\d{2}:\d{2}$/', $et)) continue;
            $breaks[] = ['start' => $st, 'end' => $et];
          }
        }
      }

      $schedule[$day][] = [
        'id' => (int)($r['id'] ?? 0),
        'start_time' => substr((string)($r['start_time'] ?? ''), 0, 5),
        'end_time' => substr((string)($r['end_time'] ?? ''), 0, 5),
        'is_enabled' => (int)($r['is_enabled'] ?? 1) === 1,
        'breaks' => $breaks,
      ];
    }
  } else {
    // Fallback from legacy settings
    for ($d = 1; $d <= 7; $d++) {
      $legacy_key = ($d === 7) ? 0 : $d;
      $raw = (string)BP_SettingsHelper::get_with_default('bp_schedule_' . $legacy_key);
      $raw = trim($raw);
      if ($raw === '' || !preg_match('/^\d{2}:\d{2}\-\d{2}:\d{2}$/', $raw)) continue;
      [$open, $close] = explode('-', $raw);
      $schedule[(string)$d][] = [
        'start_time' => $open,
        'end_time' => $close,
        'is_enabled' => true,
        'breaks' => BP_ScheduleHelper::get_break_ranges(),
      ];
    }
  }

  return new WP_REST_Response([
    'status' => 'success',
    'data' => [
      'agent_id' => $agent_id,
      'schedule' => $schedule,
      'settings' => BP_ScheduleHelper::get_schedule_settings(),
    ],
  ], 200);
}

function bp_rest_admin_schedule_save(WP_REST_Request $req) {
  global $wpdb;
  $t = $wpdb->prefix . 'bp_schedules';

  $body = $req->get_json_params();
  if (!is_array($body) || empty($body)) {
    $raw = $req->get_body();
    $decoded = json_decode($raw ?: '', true);
    if (is_array($decoded)) $body = $decoded;
  }

  $agent_id = (int)($body['agent_id'] ?? 0);
  $schedule = $body['schedule'] ?? null;
  $settings = $body['settings'] ?? null;

  if (!is_array($schedule)) {
    return new WP_REST_Response(['status'=>'error','message'=>'Invalid schedule payload'], 400);
  }

  if ((string)$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t)) !== $t) {
    return new WP_REST_Response(['status'=>'error','message'=>'Schedules table missing'], 500);
  }

  if ($agent_id > 0) {
    $wpdb->query($wpdb->prepare("DELETE FROM {$t} WHERE agent_id=%d", $agent_id));
  } else {
    $wpdb->query("DELETE FROM {$t} WHERE agent_id IS NULL");
  }

  $now = current_time('mysql');
  for ($d = 1; $d <= 7; $d++) {
    $dayKey = (string)$d;
    $intervals = $schedule[$dayKey] ?? [];
    if (!is_array($intervals)) continue;

    foreach ($intervals as $it) {
      $st = substr(trim((string)($it['start_time'] ?? '')), 0, 5);
      $et = substr(trim((string)($it['end_time'] ?? '')), 0, 5);
      $enabled = !empty($it['is_enabled']) ? 1 : 0;
      if (!preg_match('/^\d{2}:\d{2}$/', $st) || !preg_match('/^\d{2}:\d{2}$/', $et)) continue;
      if (BP_ScheduleHelper::to_minutes($et) <= BP_ScheduleHelper::to_minutes($st)) continue;

      $breaks = $it['breaks'] ?? [];
      if (!is_array($breaks)) $breaks = [];
      $clean_breaks = [];
      foreach ($breaks as $b) {
        $bst = isset($b['start']) ? $b['start'] : ($b['start_time'] ?? '');
        $bet = isset($b['end']) ? $b['end'] : ($b['end_time'] ?? '');
        $bst = substr(trim((string)$bst), 0, 5);
        $bet = substr(trim((string)$bet), 0, 5);
        if (!preg_match('/^\d{2}:\d{2}$/', $bst) || !preg_match('/^\d{2}:\d{2}$/', $bet)) continue;
        if (BP_ScheduleHelper::to_minutes($bet) <= BP_ScheduleHelper::to_minutes($bst)) continue;
        $clean_breaks[] = ['start' => $bst, 'end' => $bet];
      }

      $wpdb->insert($t, [
        'agent_id' => $agent_id > 0 ? $agent_id : null,
        'day_of_week' => $d,
        'start_time' => $st . ':00',
        'end_time' => $et . ':00',
        'breaks_json' => !empty($clean_breaks) ? wp_json_encode($clean_breaks) : null,
        'is_enabled' => $enabled,
        'created_at' => $now,
        'updated_at' => $now,
      ], ['%d','%d','%s','%s','%s','%d','%s','%s']);
    }
  }

  if (is_array($settings)) {
    $slot = (int)($settings['slot_interval_minutes'] ?? 30);
    $timezone = (string)($settings['timezone'] ?? 'Europe/Copenhagen');
    BP_ScheduleHelper::set_schedule_settings($slot, $timezone);
    BP_SettingsHelper::set('bp_slot_interval_minutes', $slot);
  }

  if (!empty($wpdb->last_error)) {
    return new WP_REST_Response(['status'=>'error','message'=>$wpdb->last_error], 500);
  }

  return new WP_REST_Response(['status'=>'success','data'=>['saved'=>true]], 200);
}

function bp_rest_admin_unavailable_blocks(WP_REST_Request $req) {
  $start = sanitize_text_field($req->get_param('start'));
  $end   = sanitize_text_field($req->get_param('end'));
  $agent_id = (int)$req->get_param('agent_id');

  $from = substr($start,0,10);
  $to   = substr($end,0,10);

  if ($agent_id <= 0) {
    // only show unavailable blocks when a specific agent is selected
    return new WP_REST_Response(['status'=>'success','data'=>[]], 200);
  }

  $blocks = BP_ScheduleHelper::build_unavailable_blocks($agent_id, $from, $to);

  // FullCalendar background events
  $events = array_map(function($b){
    return [
      'start'=>$b['start'],
      'end'=>$b['end'],
      'display'=>'background',
    ];
  }, $blocks);

  return new WP_REST_Response(['status'=>'success','data'=>$events], 200);
}
