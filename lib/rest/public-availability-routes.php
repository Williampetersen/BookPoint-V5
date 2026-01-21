<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {
  register_rest_route('bp/v1', '/public/availability-slots', [
    'methods'  => 'GET',
    'callback' => 'bp_rest_public_availability_slots',
    'permission_callback' => '__return_true',
  ]);
});

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
    return new WP_REST_Response(['status' => 'success', 'data' => ['date' => $date, 'slots' => []]], 200);
  }
  if ($ts > strtotime('+' . $max_days . ' days')) {
    return new WP_REST_Response(['status' => 'success', 'data' => ['date' => $date, 'slots' => []]], 200);
  }

  if (!class_exists('BP_ScheduleHelper')) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'Schedule helper missing'], 500);
  }

  if (BP_ScheduleHelper::is_date_closed($date)) {
    return new WP_REST_Response(['status' => 'success', 'data' => [
      'date' => $date,
      'service_id' => $service_id,
      'agent_id' => $agent_id,
      'slots' => [],
    ]], 200);
  }

  $rules = BP_ScheduleHelper::get_service_rules($service_id);
  $occupied = (int)$rules['occupied_min'];
  $capacity = (int)$rules['capacity'];

  $slots = bp_generate_slots_for_public($date, $agent_id, $service_id, $occupied, $capacity);

  return new WP_REST_Response(['status' => 'success', 'data' => [
    'date' => $date,
    'service_id' => $service_id,
    'agent_id' => $agent_id,
    'duration_min' => (int)$rules['duration'],
    'buffer_before' => (int)$rules['buffer_before'],
    'buffer_after' => (int)$rules['buffer_after'],
    'occupied_min' => $occupied,
    'slots' => $slots,
  ]], 200);
}

function bp_generate_slots_for_public(string $date, int $agent_id, int $service_id, int $occupied_min, int $capacity): array {
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

  $day_start = '07:00:00';
  $day_end   = '20:00:00';

  $interval = (int)apply_filters('bp_public_slot_step_minutes', 15);
  if ($interval < 5) $interval = 5;

  $slots = [];

  $start_ts = strtotime("$date $day_start");
  $end_ts   = strtotime("$date $day_end");
  if ($start_ts === false || $end_ts === false || $end_ts <= $start_ts) return [];

  for ($t = $start_ts; $t + ($occupied_min * 60) <= $end_ts; $t += $interval * 60) {
    $start_time = date('H:i:s', $t);
    $end_time   = date('H:i:s', $t + ($occupied_min * 60));

    if (!BP_ScheduleHelper::is_within_schedule($agent_id, $date, $start_time, $end_time)) {
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

  return $slots;
}
