<?php
defined('ABSPATH') || exit;
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- This file's wpdb SQL paths interpolate only hardcoded plugin table names with a sanitized WordPress prefix; dynamic values remain prepared or static by design.

add_action('rest_api_init', function () {
  register_rest_route('pointly-booking/v1', '/availability/timeslots', [
    'methods'  => 'GET',
    'callback' => 'pointlybooking_rest_public_timeslots',
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('pointly-booking/v1', '/public/availability-slots', [
    'methods'  => 'GET',
    'callback' => 'pointlybooking_rest_public_availability_slots',
    'permission_callback' => '__return_true',
  ]);
});

function pointlybooking_rest_public_timeslots(WP_REST_Request $req) {
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

  if (!class_exists('POINTLYBOOKING_AvailabilityHelper')) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'Availability helper missing'], 500);
  }

  if (class_exists('POINTLYBOOKING_ScheduleHelper') && !POINTLYBOOKING_ScheduleHelper::is_date_allowed($date)) {
    return new WP_REST_Response(['status' => 'success', 'data' => []], 200);
  }

  $slots = POINTLYBOOKING_AvailabilityHelper::get_timeslots_for_date($service_id, $date, $agent_id);

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

function pointlybooking_rest_public_availability_slots(WP_REST_Request $req) {
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

  $max_days = (int)apply_filters('pointlybooking_public_max_booking_days', 120);
  $ts = strtotime($date . ' 00:00:00');
  if ($ts === false) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'Invalid date'], 400);
  }
  if ($ts < strtotime(gmdate('Y-m-d') . ' 00:00:00')) {
    return new WP_REST_Response(['status' => 'success', 'data' => [], 'meta' => ['date' => $date]], 200);
  }
  if ($ts > strtotime('+' . $max_days . ' days')) {
    return new WP_REST_Response(['status' => 'success', 'data' => [], 'meta' => ['date' => $date]], 200);
  }

  if (!class_exists('POINTLYBOOKING_ScheduleHelper')) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'Schedule helper missing'], 500);
  }

  if (POINTLYBOOKING_ScheduleHelper::is_date_closed($date, $agent_id)) {
    return new WP_REST_Response(['status' => 'success', 'data' => [], 'meta' => [
      'date' => $date,
      'service_id' => $service_id,
      'agent_id' => $agent_id,
    ]], 200);
  }

  $rules = POINTLYBOOKING_ScheduleHelper::get_service_rules($service_id);
  $occupied = (int)$rules['occupied_min'];
  $capacity = (int)$rules['capacity'];

  $debug_meta = [];
  $slots = pointlybooking_generate_slots_for_public($date, $agent_id, $service_id, $occupied, $capacity, $debug_meta);

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

function pointlybooking_generate_slots_for_public(string $date, int $agent_id, int $service_id, int $occupied_min, int $capacity, array &$debug_meta = []): array {
  // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names in this function are validated local plugin table names built from hardcoded plugin suffixes.
  global $wpdb;

  $bookings_table = $wpdb->prefix . 'pointlybooking_bookings';
  if (!preg_match('/^[A-Za-z0-9_]+$/', $bookings_table)) {
    return [];
  }

  $booking_columns = pointlybooking_db_table_columns($bookings_table);
  $has_start_date = in_array('start_date', $booking_columns, true);
  $has_start_time = in_array('start_time', $booking_columns, true);
  $has_start_datetime = in_array('start_datetime', $booking_columns, true);

  $step = 15;
  if (class_exists('POINTLYBOOKING_SettingsHelper')) {
    $step = intval(POINTLYBOOKING_SettingsHelper::get('slot_interval_minutes', 15));
  }
  if ($step < 5) $step = 5;
  if ($step > 60) $step = 60;

  $sched = [];
  if (class_exists('POINTLYBOOKING_ScheduleHelper')) {
    $sched = POINTLYBOOKING_ScheduleHelper::get_agent_weekly_schedule($agent_id);
  }
  if (!is_array($sched)) $sched = [];

  $ts = strtotime($date.' 00:00:00');
  $dow = strtolower(gmdate('D', $ts));

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

  $existing_intervals = [];
  if ($has_start_date && $has_start_time) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $existing_rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT service_id, start_time
        FROM {$bookings_table}
        WHERE agent_id=%d
          AND start_date=%s
          AND status IN ('pending','confirmed')",
        $agent_id,
        $date
      ),
      ARRAY_A
    ) ?: [];
    foreach ($existing_rows as $existing_row) {
      $rules = POINTLYBOOKING_ScheduleHelper::get_service_rules((int)($existing_row['service_id'] ?? 0));
      $existing_start_ts = strtotime($date . ' ' . (string)($existing_row['start_time'] ?? ''));
      if ($existing_start_ts === false) {
        continue;
      }
      $existing_intervals[] = [
        'start' => $existing_start_ts,
        'end' => $existing_start_ts + (max(5, (int)($rules['occupied_min'] ?? 30)) * 60),
      ];
    }
  } elseif ($has_start_datetime) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $existing_rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT service_id, start_datetime
        FROM {$bookings_table}
        WHERE agent_id=%d
          AND DATE(start_datetime)=%s
          AND status IN ('pending','confirmed')",
        $agent_id,
        $date
      ),
      ARRAY_A
    ) ?: [];
    foreach ($existing_rows as $existing_row) {
      $rules = POINTLYBOOKING_ScheduleHelper::get_service_rules((int)($existing_row['service_id'] ?? 0));
      $existing_start_ts = strtotime((string)($existing_row['start_datetime'] ?? ''));
      if ($existing_start_ts === false) {
        continue;
      }
      $existing_intervals[] = [
        'start' => $existing_start_ts,
        'end' => $existing_start_ts + (max(5, (int)($rules['occupied_min'] ?? 30)) * 60),
      ];
    }
  }

  foreach ($windows as $w) {
    $wStart = isset($w['start']) ? $w['start'] : ($w['start_time'] ?? '08:00');
    $wEnd = isset($w['end']) ? $w['end'] : ($w['end_time'] ?? '20:00');

    $start_ts = strtotime("$date $wStart");
    $end_ts   = strtotime("$date $wEnd");
    if ($start_ts === false || $end_ts === false || $end_ts <= $start_ts) continue;

    for ($t = $start_ts; $t + ($occupied_min * 60) <= $end_ts; $t += $step * 60) {
      $start_time = gmdate('H:i:s', $t);
      $end_time   = gmdate('H:i:s', $t + ($occupied_min * 60));

      if (!POINTLYBOOKING_ScheduleHelper::is_within_schedule($agent_id, $date, $start_time, $occupied_min)) {
        continue;
      }

      $slot_start_ts = $t;
      $slot_end_ts = $t + ($occupied_min * 60);
      $overlap_count = 0;
      foreach ($existing_intervals as $existing_interval) {
        if ($existing_interval['start'] < $slot_end_ts && $existing_interval['end'] > $slot_start_ts) {
          $overlap_count++;
          if ($capacity <= 1) {
            break;
          }
        }
      }

      if ($capacity <= 1 && $overlap_count > 0) continue;
      if ($capacity > 1 && $overlap_count >= $capacity) continue;

      $slots[] = [
        'start_time' => $start_time,
        'end_time' => $end_time,
        'label' => substr($start_time, 0, 5) . ' - ' . substr($end_time, 0, 5),
      ];
    }
  }

  return $slots;
}


