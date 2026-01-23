<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {
  register_rest_route('bp/v1', '/availability/timeslots', [
    'methods'  => 'GET',
    'callback' => 'bp_rest_public_timeslots',
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('bp/v1', '/public/availability-slots', [
    'methods'  => 'GET',
    'callback' => 'bp_rest_public_availability_slots',
    'permission_callback' => '__return_true',
  ]);
});

function bp_rest_public_timeslots(WP_REST_Request $req) {
  $service_id = (int)($req->get_param('service_id') ?? 0);
  $agent_id   = (int)($req->get_param('agent_id') ?? 0);
  $date       = sanitize_text_field($req->get_param('date') ?? '');

  $date = substr($date, 0, 10);

  if ($service_id <= 0) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'service_id is required'], 400);
  }
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'Invalid date'], 400);
  }

  if (!class_exists('BP_AvailabilityHelper')) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'Availability helper missing'], 500);
  }

  if (class_exists('BP_ScheduleHelper') && !BP_ScheduleHelper::is_date_allowed($date)) {
    return new WP_REST_Response(['status' => 'success', 'data' => []], 200);
  }

  $slots = BP_AvailabilityHelper::get_timeslots_for_date($service_id, $date, $agent_id);

  return new WP_REST_Response([
    'status' => 'success',
    'data' => $slots,
    'meta' => [
      'date' => $date,
      'service_id' => $service_id,
      'agent_id' => $agent_id,
    ],
  ], 200);
}

function bp_rest_public_availability_slots(WP_REST_Request $req) {
  $service_id = (int)($req->get_param('service_id') ?? 0);
  $agent_id   = (int)($req->get_param('agent_id') ?? 0);
  $date       = sanitize_text_field($req->get_param('date') ?? '');

  $date = substr($date, 0, 10);

  if ($service_id <= 0 || $agent_id <= 0) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'service_id and agent_id are required'], 400);
  }
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'Invalid date'], 400);
  }

  $max_days = (int)apply_filters('bp_public_max_booking_days', 120);
  $ts = strtotime($date . ' 00:00:00');
  if ($ts === false) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'Invalid date'], 400);
  }
  if ($ts < strtotime(date('Y-m-d') . ' 00:00:00')) {
    return new WP_REST_Response(['status' => 'success', 'data' => [], 'meta' => ['date' => $date]], 200);
  }
  if ($ts > strtotime('+' . $max_days . ' days')) {
    return new WP_REST_Response(['status' => 'success', 'data' => [], 'meta' => ['date' => $date]], 200);
  }

  if (!class_exists('BP_ScheduleHelper')) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'Schedule helper missing'], 500);
  }

  if (BP_ScheduleHelper::is_date_closed($date, $agent_id)) {
    return new WP_REST_Response(['status' => 'success', 'data' => [], 'meta' => [
      'date' => $date,
      'service_id' => $service_id,
      'agent_id' => $agent_id,
    ]], 200);
  }

  $rules = BP_ScheduleHelper::get_service_rules($service_id);
  $occupied = (int)$rules['occupied_min'];
  $capacity = (int)$rules['capacity'];

  $debug_meta = [];
  $slots = bp_generate_slots_for_public($date, $agent_id, $service_id, $occupied, $capacity, $debug_meta);

  $debug = $req->get_param('debug');
  if ($debug) {
    return new WP_REST_Response([
      'status'=>'success',
      'debug'=>[
        'date'=>$date,
        'dow'=>$debug_meta['dow'] ?? null,
        'step'=>$debug_meta['step'] ?? null,
        'service_id'=>$service_id,
        'agent_id'=>$agent_id,
        'occupied_minutes'=>$occupied,
        'windows'=>$debug_meta['windows'] ?? [],
        'slots_count'=>count($slots),
      ],
      'data'=>$slots
    ], 200);
  }

  return new WP_REST_Response(['status' => 'success', 'data' => $slots, 'meta' => [
    'date' => $date,
    'service_id' => $service_id,
    'agent_id' => $agent_id,
    'duration_min' => (int)$rules['duration'],
    'buffer_before' => (int)$rules['buffer_before'],
    'buffer_after' => (int)$rules['buffer_after'],
    'occupied_min' => $occupied,
  ]], 200);
}

function bp_generate_slots_for_public(string $date, int $agent_id, int $service_id, int $occupied_min, int $capacity, array &$debug_meta = []): array {
  global $wpdb;

  $t_book = $wpdb->prefix . 'bp_bookings';
  $t_srv  = $wpdb->prefix . 'bp_services';

  $cols = $wpdb->get_col("SHOW COLUMNS FROM {$t_srv}") ?: [];
  $has_duration_minutes = in_array('duration_minutes', $cols, true);
  $has_duration = in_array('duration', $cols, true);
  $has_buffer_before_minutes = in_array('buffer_before_minutes', $cols, true);
  $has_buffer_after_minutes = in_array('buffer_after_minutes', $cols, true);
  $has_buffer_before = in_array('buffer_before', $cols, true);
  $has_buffer_after = in_array('buffer_after', $cols, true);

  $duration_expr = $has_duration_minutes ? 's.duration_minutes' : ($has_duration ? 's.duration' : '30');
  $buffer_before_expr = $has_buffer_before_minutes ? 's.buffer_before_minutes' : ($has_buffer_before ? 's.buffer_before' : '0');
  $buffer_after_expr = $has_buffer_after_minutes ? 's.buffer_after_minutes' : ($has_buffer_after ? 's.buffer_after' : '0');

  $step = 15;
  if (class_exists('BP_SettingsHelper')) {
    $step = intval(BP_SettingsHelper::get('slot_interval_minutes', 15));
  }
  if ($step < 5) $step = 5;
  if ($step > 60) $step = 60;

  $sched = [];
  if (class_exists('BP_ScheduleHelper')) {
    $sched = BP_ScheduleHelper::get_agent_weekly_schedule($agent_id);
  }
  if (!is_array($sched)) $sched = [];

  $ts = strtotime($date.' 00:00:00');
  $dow = strtolower(date('D', $ts));

  $mapLong = [
    'monday'=>'mon','tuesday'=>'tue','wednesday'=>'wed','thursday'=>'thu','friday'=>'fri','saturday'=>'sat','sunday'=>'sun'
  ];

  $windows = $sched[$dow] ?? null;
  if ($windows === null) {
    foreach ($mapLong as $long=>$short) {
      if ($short === $dow && isset($sched[$long])) { $windows = $sched[$long]; break; }
    }
  }
  if (!is_array($windows)) $windows = [];

  if (empty($windows)) {
    $windows = [['start'=>'08:00','end'=>'20:00']];
  }

  $slots = [];

  $debug_meta['dow'] = $dow;
  $debug_meta['step'] = $step;
  $debug_meta['windows'] = $windows;

  foreach ($windows as $w) {
    $wStart = isset($w['start']) ? $w['start'] : ($w['start_time'] ?? '08:00');
    $wEnd = isset($w['end']) ? $w['end'] : ($w['end_time'] ?? '20:00');

    $start_ts = strtotime("$date $wStart");
    $end_ts   = strtotime("$date $wEnd");
    if ($start_ts === false || $end_ts === false || $end_ts <= $start_ts) continue;

    for ($t = $start_ts; $t + ($occupied_min * 60) <= $end_ts; $t += $step * 60) {
      $start_time = date('H:i:s', $t);
      $end_time   = date('H:i:s', $t + ($occupied_min * 60));

      if (!BP_ScheduleHelper::is_within_schedule($agent_id, $date, $start_time, $occupied_min)) {
        continue;
      }

    if ($capacity <= 1) {
      $conflict = (int)$wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*)
        FROM {$t_book} b
        LEFT JOIN {$t_srv} s ON s.id=b.service_id
        WHERE b.agent_id=%d
          AND b.start_date=%s
          AND b.status IN ('pending','confirmed')
          AND (
            STR_TO_DATE(CONCAT(b.start_date,' ',b.start_time), '%%Y-%%m-%%d %%H:%%i:%%s')
            < STR_TO_DATE(CONCAT(%s,' ',%s), '%%Y-%%m-%%d %%H:%%i:%%s')
          )
          AND (
            DATE_ADD(
              STR_TO_DATE(CONCAT(b.start_date,' ',b.start_time), '%%Y-%%m-%%d %%H:%%i:%%s'),
              INTERVAL (COALESCE({$duration_expr},30)+COALESCE({$buffer_before_expr},0)+COALESCE({$buffer_after_expr},0)) MINUTE
            )
            > STR_TO_DATE(CONCAT(%s,' ',%s), '%%Y-%%m-%%d %%H:%%i:%%s')
          )
        LIMIT 1
      ", $agent_id, $date, $date, $end_time, $date, $start_time));

      if ($conflict > 0) continue;
    } else {
      $overlapCount = (int)$wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*)
        FROM {$t_book} b
        LEFT JOIN {$t_srv} s ON s.id=b.service_id
        WHERE b.agent_id=%d
          AND b.start_date=%s
          AND b.status IN ('pending','confirmed')
          AND (
            STR_TO_DATE(CONCAT(b.start_date,' ',b.start_time), '%%Y-%%m-%%d %%H:%%i:%%s')
            < STR_TO_DATE(CONCAT(%s,' ',%s), '%%Y-%%m-%%d %%H:%%i:%%s')
          )
          AND (
            DATE_ADD(
              STR_TO_DATE(CONCAT(b.start_date,' ',b.start_time), '%%Y-%%m-%%d %%H:%%i:%%s'),
              INTERVAL (COALESCE({$duration_expr},30)+COALESCE({$buffer_before_expr},0)+COALESCE({$buffer_after_expr},0)) MINUTE
            )
            > STR_TO_DATE(CONCAT(%s,' ',%s), '%%Y-%%m-%%d %%H:%%i:%%s')
          )
      ", $agent_id, $date, $date, $end_time, $date, $start_time));

      if ($overlapCount >= $capacity) continue;
    }

      $slots[] = [
        'start_time' => $start_time,
        'end_time' => $end_time,
        'label' => substr($start_time, 0, 5) . ' - ' . substr($end_time, 0, 5),
      ];
    }
  }

  return $slots;
}
