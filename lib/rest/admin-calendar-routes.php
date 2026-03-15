<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {

  // Calendar bookings list (admin UI)
  register_rest_route('pointly-booking/v1', '/admin/calendar/bookings', [
    'methods'  => 'GET',
    'callback' => 'pointlybooking_admin_calendar_get_bookings',
    'permission_callback' => function () {
      return current_user_can('pointlybooking_manage_bookings') || current_user_can('pointlybooking_manage_services') || current_user_can('pointlybooking_manage_settings');
    },
  ]);

  // GET events for FullCalendar
  register_rest_route('pointly-booking/v1', '/admin/calendar', [
    'methods'  => 'GET',
    'callback' => 'pointlybooking_rest_admin_calendar_events',
    'permission_callback' => function () {
      return current_user_can('pointlybooking_manage_bookings');
    },
    'args' => [
      'start' => ['required' => true],
      'end'   => ['required' => true],
      'agent_id' => ['required' => false],
      'service_id'=> ['required' => false],
      'status' => ['required' => false],
    ],
  ]);

  // PATCH/POST reschedule booking (drag/drop)
  register_rest_route('pointly-booking/v1', '/admin/bookings/(?P<id>\d+)/reschedule', [
    'methods'  => ['PATCH','POST'],
    'callback' => 'pointlybooking_rest_admin_booking_reschedule',
    'permission_callback' => function () {
      return current_user_can('pointlybooking_manage_bookings');
    },
  ]);

  // POST change booking status
  register_rest_route('pointly-booking/v1', '/admin/bookings/(?P<id>\d+)/status', [
    'methods'  => 'POST',
    'callback' => 'pointlybooking_rest_admin_booking_change_status',
    'permission_callback' => function () {
      return current_user_can('pointlybooking_manage_bookings');
    },
  ]);
});

function pointlybooking_admin_calendar_normalize_ymd(string $value): string {
  $value = substr(sanitize_text_field($value), 0, 10);
  if (!function_exists('pointlybooking_is_valid_ymd')) {
    return '';
  }
  if (!pointlybooking_is_valid_ymd($value)) {
    return '';
  }
  return $value;
}

function pointlybooking_admin_calendar_parse_request_datetime(string $value): ?int {
  $value = trim(sanitize_text_field($value));
  if ($value === '') {
    return null;
  }

  $is_strict_parse = static function ($dt): bool {
    if (!$dt instanceof \DateTimeImmutable) {
      return false;
    }
    $errors = \DateTimeImmutable::getLastErrors();
    if (!is_array($errors)) {
      return true;
    }
    return (int)($errors['warning_count'] ?? 0) === 0 && (int)($errors['error_count'] ?? 0) === 0;
  };

  $has_z_suffix = preg_match('/Z$/', $value) === 1;
  $has_offset_suffix = preg_match('/[+\-]\d{2}:\d{2}$/', $value) === 1;

  if ($has_z_suffix || $has_offset_suffix) {
    $normalized = $has_z_suffix ? (substr($value, 0, -1) . '+00:00') : $value;
    $timezone_formats = ['Y-m-d\TH:iP', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s.uP'];

    foreach ($timezone_formats as $format) {
      $dt = \DateTimeImmutable::createFromFormat('!' . $format, $normalized, new \DateTimeZone('UTC'));
      if ($is_strict_parse($dt)) {
        return $dt->getTimestamp();
      }
    }

    return null;
  }

  $local_formats = ['Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i', 'Y-m-d\TH:i:s.u'];
  foreach ($local_formats as $format) {
    $dt = \DateTimeImmutable::createFromFormat('!' . $format, $value, wp_timezone());
    if ($is_strict_parse($dt)) {
      return $dt->getTimestamp();
    }
  }

  return null;
}

function pointlybooking_admin_calendar_fetch_rows_by_ids(string $table, array $ids): array {
  global $wpdb;
  $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
  if (empty($ids) || !pointlybooking_is_safe_sql_identifier($table)) {
    return [];
  }

  $placeholders = implode(',', array_fill(0, count($ids), '%d'));
  $rows = $wpdb->get_results(
    $wpdb->prepare("SELECT * FROM {$table} WHERE id IN ({$placeholders})", $ids),
    ARRAY_A
  ) ?: [];

  $map = [];
  foreach ($rows as $row) {
    $map[(int)($row['id'] ?? 0)] = $row;
  }
  return $map;
}

function pointlybooking_admin_calendar_enrich_bookings(array $rows): array {
  global $wpdb;

  $services_table = $wpdb->prefix . 'pointlybooking_services';
  $agents_table = $wpdb->prefix . 'pointlybooking_agents';
  $customers_table = $wpdb->prefix . 'pointlybooking_customers';

  $service_ids = [];
  $agent_ids = [];
  $customer_ids = [];
  foreach ($rows as $row) {
    $service_id = (int)($row['service_id'] ?? 0);
    $agent_id = (int)($row['agent_id'] ?? 0);
    $customer_id = (int)($row['customer_id'] ?? 0);
    if ($service_id > 0) {
      $service_ids[] = $service_id;
    }
    if ($agent_id > 0) {
      $agent_ids[] = $agent_id;
    }
    if ($customer_id > 0) {
      $customer_ids[] = $customer_id;
    }
  }

  $services = pointlybooking_admin_calendar_fetch_rows_by_ids($services_table, $service_ids);
  $agents = pointlybooking_admin_calendar_fetch_rows_by_ids($agents_table, $agent_ids);
  $customers = pointlybooking_admin_calendar_fetch_rows_by_ids($customers_table, $customer_ids);
  $service_rules = [];

  foreach ($rows as &$row) {
    $service_id = (int)($row['service_id'] ?? 0);
    $agent_id = (int)($row['agent_id'] ?? 0);
    $customer_id = (int)($row['customer_id'] ?? 0);

    if (!isset($service_rules[$service_id])) {
      $service_rules[$service_id] = POINTLYBOOKING_ScheduleHelper::get_service_rules($service_id);
    }

    $service = $services[$service_id] ?? [];
    $agent = $agents[$agent_id] ?? [];
    $customer = $customers[$customer_id] ?? [];
    $rules = $service_rules[$service_id];

    $customer_name = trim((string)($customer['first_name'] ?? '') . ' ' . (string)($customer['last_name'] ?? ''));
    if ($customer_name === '') {
      $customer_name = trim((string)($row['customer_name'] ?? ''));
    }

    $agent_name = trim((string)($agent['first_name'] ?? '') . ' ' . (string)($agent['last_name'] ?? ''));
    if ($agent_name === '') {
      $agent_name = (string)($agent['name'] ?? ($row['agent_name'] ?? ''));
    }

    $row['service_name'] = (string)($service['name'] ?? ($row['service_name'] ?? ''));
    $row['duration'] = (int)($rules['duration'] ?? 30);
    $row['buffer_before'] = (int)($rules['buffer_before'] ?? 0);
    $row['buffer_after'] = (int)($rules['buffer_after'] ?? 0);
    $row['customer_name_display'] = $customer_name;
    $row['customer_email_display'] = (string)($customer['email'] ?? ($row['customer_email'] ?? ''));
    $row['agent_name_display'] = $agent_name;
  }
  unset($row);

  return $rows;
}

function pointlybooking_admin_calendar_matches_search(array $row, string $query): bool {
  $query = trim(strtolower($query));
  if ($query === '') {
    return true;
  }

  $parts = [
    (string)($row['customer_name_display'] ?? ''),
    (string)($row['customer_email_display'] ?? ''),
    (string)($row['service_name'] ?? ''),
    (string)($row['agent_name_display'] ?? ''),
    (string)($row['customer_name'] ?? ''),
    (string)($row['customer_email'] ?? ''),
  ];

  return strpos(strtolower(implode(' ', $parts)), $query) !== false;
}

function pointlybooking_admin_calendar_get_bookings(WP_REST_Request $req) {
  global $wpdb;

  $start = sanitize_text_field($req->get_param('start') ?? '');
  $end   = sanitize_text_field($req->get_param('end') ?? '');
  $status = sanitize_text_field($req->get_param('status') ?? 'all');
  $agent_id = sanitize_text_field($req->get_param('agent_id') ?? 'all');
  $service_id = sanitize_text_field($req->get_param('service_id') ?? 'all');

  $start = pointlybooking_admin_calendar_normalize_ymd($start);
  $end   = pointlybooking_admin_calendar_normalize_ymd($end);
  if ($start === '' || $end === '') {
    return new WP_REST_Response(['status'=>'error','message'=>'Invalid start/end'], 400);
  }

  $bookings_table = $wpdb->prefix . 'pointlybooking_bookings';
  if (!pointlybooking_is_safe_sql_identifier($bookings_table)) {
    return new WP_REST_Response(['status'=>'error','message'=>'Invalid table configuration'], 500);
  }

  $bCols = pointlybooking_db_table_columns($bookings_table);

  $has_start_datetime = in_array('start_datetime', $bCols, true);
  $has_start_date = in_array('start_date', $bCols, true);
  $has_start_time = in_array('start_time', $bCols, true);

  if (!$has_start_datetime && !$has_start_date) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'Booking date columns not found'], 500);
  }
  $date_from_value = $has_start_datetime ? ($start . ' 00:00:00') : $start;
  $date_to_value = $has_start_datetime ? ($end . ' 23:59:59') : $end;
  $status_value = ($status !== 'all' && in_array($status, ['pending','confirmed','cancelled'], true)) ? $status : '';
  $agent_value = ($agent_id !== 'all') ? (int)$agent_id : 0;
  $service_value = ($service_id !== 'all') ? (int)$service_id : 0;

  if ($has_start_datetime) {
    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT *
        FROM {$bookings_table}
        WHERE start_datetime >= %s
          AND start_datetime <= %s
          AND (%d = 0 OR status = %s)
          AND (%d = 0 OR agent_id = %d)
          AND (%d = 0 OR service_id = %d)
        ORDER BY start_datetime ASC
        LIMIT 2000",
        $date_from_value,
        $date_to_value,
        $status_value !== '' ? 1 : 0,
        $status_value,
        $agent_value > 0 ? 1 : 0,
        $agent_value,
        $service_value > 0 ? 1 : 0,
        $service_value
      ),
      ARRAY_A
    ) ?: [];
  } else {
    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT *
        FROM {$bookings_table}
        WHERE start_date >= %s
          AND start_date <= %s
          AND (%d = 0 OR status = %s)
          AND (%d = 0 OR agent_id = %d)
          AND (%d = 0 OR service_id = %d)
        ORDER BY start_date ASC
        LIMIT 2000",
        $date_from_value,
        $date_to_value,
        $status_value !== '' ? 1 : 0,
        $status_value,
        $agent_value > 0 ? 1 : 0,
        $agent_value,
        $service_value > 0 ? 1 : 0,
        $service_value
      ),
      ARRAY_A
    ) ?: [];
  }

  $rows = pointlybooking_admin_calendar_enrich_bookings($rows);

  $data = [];
  foreach ($rows as $r) {
    $duration = (int)($r['duration'] ?? 30);
    $bf = (int)($r['buffer_before'] ?? 0);
    $ba = (int)($r['buffer_after'] ?? 0);
    $occupied = max(5, $duration + $bf + $ba);

    $start_dt = '';
    if ($has_start_datetime && !empty($r['start_datetime'])) {
      $start_dt = $r['start_datetime'];
    } elseif ($has_start_date && $has_start_time) {
      $start_dt = ($r['start_date'] ?? '') . ' ' . ($r['start_time'] ?? '');
    }

    $start_ts = $start_dt ? strtotime($start_dt) : null;
    $end_ts = $start_ts ? ($start_ts + ($occupied * 60)) : null;

    $start_date = $start_ts ? gmdate('Y-m-d', $start_ts) : ($r['start_date'] ?? '');
    $start_time = $start_ts ? gmdate('H:i', $start_ts) : substr((string)($r['start_time'] ?? ''), 0, 5);
    $end_time = $end_ts ? gmdate('H:i', $end_ts) : '';

    $customer_name = (string)($r['customer_name_display'] ?? '');
    $agent_name = (string)($r['agent_name_display'] ?? '');

    $data[] = [
      'id' => (int)$r['id'],
      'date' => $start_date,
      'start_time' => $start_time,
      'end_time' => $end_time,
      'status' => $r['status'],
      'service_id' => (int)$r['service_id'],
      'agent_id' => (int)$r['agent_id'],
      'customer_id' => (int)$r['customer_id'],
      'service_name' => (string)($r['service_name'] ?? ''),
      'agent_name' => $agent_name,
      'customer_name' => $customer_name ?: ('Customer #' . (int)$r['customer_id']),
      'title' => trim((($r['service_name'] ?? 'Service') ?: 'Service') . ' - ' . ($customer_name ?: 'Customer')),
    ];
  }

  return new WP_REST_Response(['status'=>'success','data'=>$data], 200);
}


function pointlybooking_rest_admin_calendar_events(WP_REST_Request $req) {
  global $wpdb;

  $start = sanitize_text_field($req->get_param('start'));
  $end   = sanitize_text_field($req->get_param('end'));

  // Expect YYYY-MM-DD or ISO; normalize to strict YYYY-MM-DD.
  $start_date = pointlybooking_admin_calendar_normalize_ymd($start);
  $end_date   = pointlybooking_admin_calendar_normalize_ymd($end);
  if ($start_date === '' || $end_date === '') {
    return new WP_REST_Response(['status'=>'error','message'=>'Invalid date range'], 400);
  }

  $agent_id  = (int)($req->get_param('agent_id') ?: 0);
  $service_id= (int)($req->get_param('service_id') ?: 0);
  $status    = sanitize_text_field($req->get_param('status') ?: '');
  $q         = sanitize_text_field($req->get_param('q') ?: '');

  $bookings_table = $wpdb->prefix . 'pointlybooking_bookings';
  if (!pointlybooking_is_safe_sql_identifier($bookings_table)) {
    return new WP_REST_Response(['status'=>'error','message'=>'Invalid table configuration'], 500);
  }

  $bCols = pointlybooking_db_table_columns($bookings_table);

  $has_start_datetime = in_array('start_datetime', $bCols, true);
  $has_end_datetime = in_array('end_datetime', $bCols, true);
  $has_start_date = in_array('start_date', $bCols, true);
  $has_start_time = in_array('start_time', $bCols, true);
  if (!$has_start_datetime && !$has_start_date) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'Booking date columns not found'], 500);
  }
  $date_from_value = $has_start_datetime ? ($start_date . ' 00:00:00') : $start_date;
  $date_to_value = $has_start_datetime ? ($end_date . ' 23:59:59') : $end_date;
  $status_value = ($status !== '' && $status !== 'all') ? strtolower($status) : '';

  if ($has_start_datetime) {
    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT *
        FROM {$bookings_table}
        WHERE start_datetime >= %s
          AND start_datetime <= %s
          AND (%d = 0 OR agent_id = %d)
          AND (%d = 0 OR service_id = %d)
          AND (%d = 0 OR LOWER(status) = %s)
        ORDER BY start_datetime ASC",
        $date_from_value,
        $date_to_value,
        $agent_id > 0 ? 1 : 0,
        $agent_id,
        $service_id > 0 ? 1 : 0,
        $service_id,
        $status_value !== '' ? 1 : 0,
        $status_value
      ),
      ARRAY_A
    ) ?: [];
  } else {
    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT *
        FROM {$bookings_table}
        WHERE start_date >= %s
          AND start_date <= %s
          AND (%d = 0 OR agent_id = %d)
          AND (%d = 0 OR service_id = %d)
          AND (%d = 0 OR LOWER(status) = %s)
        ORDER BY start_date ASC",
        $date_from_value,
        $date_to_value,
        $agent_id > 0 ? 1 : 0,
        $agent_id,
        $service_id > 0 ? 1 : 0,
        $service_id,
        $status_value !== '' ? 1 : 0,
        $status_value
      ),
      ARRAY_A
    ) ?: [];
  }

  $rows = pointlybooking_admin_calendar_enrich_bookings($rows);

  if ($q !== '') {
    $rows = array_values(array_filter($rows, static function ($row) use ($q) {
      return pointlybooking_admin_calendar_matches_search($row, $q);
    }));
  }

  $events = [];
  foreach ($rows as $r) {
    $duration = (int)($r['duration'] ?? 30);
    $bf = (int)($r['buffer_before'] ?? 0);
    $ba = (int)($r['buffer_after'] ?? 0);

    $start_dt = '';
    if ($has_start_datetime && !empty($r['start_datetime'])) {
      $start_dt = $r['start_datetime'];
    } elseif ($has_start_date && $has_start_time) {
      $start_dt = ($r['start_date'] ?? '') . ' ' . ($r['start_time'] ?? '');
    }

    $start_ts = $start_dt ? strtotime($start_dt) : null;
    if (!$start_ts) continue;

    $end_dt = '';
    if ($has_end_datetime && !empty($r['end_datetime'])) {
      $end_dt = $r['end_datetime'];
    } else {
      $end_dt = gmdate('Y-m-d H:i:s', $start_ts + ($duration * 60));
    }

    $customer_name = (string)($r['customer_name_display'] ?? '');
    $agent_name = (string)($r['agent_name_display'] ?? '');

    $title = trim((($r['service_name'] ?? 'Service') ?: 'Service') . ' - ' . ($customer_name ?: ('#'.$r['id'])));

    $events[] = [
      'id' => (string)$r['id'],
      'title' => $title,
      'start' => gmdate('Y-m-d H:i:s', $start_ts),
      'end' => $end_dt,
      'status' => $r['status'] ?? 'pending',
      'service_name' => $r['service_name'] ?? '',
      'agent_name' => $agent_name,
      'customer_name' => $customer_name,
      'customer_email' => (string)($r['customer_email_display'] ?? ''),
      'agent_id' => (int)($r['agent_id'] ?? 0),
      'service_id' => (int)($r['service_id'] ?? 0),
      'buffer_before' => $bf,
      'buffer_after' => $ba,
    ];
  }

  return new WP_REST_Response(['status'=>'success','data'=>$events], 200);
}


function pointlybooking_rest_admin_booking_reschedule(WP_REST_Request $req) {
  global $wpdb;

  $id = (int)$req['id'];
  if ($id <= 0) return new WP_REST_Response(['status'=>'error','message'=>'Invalid booking id'], 400);

  $body = $req->get_json_params();
  if (!is_array($body)) {
    $body = [];
  }
  $start = isset($body['start_datetime']) ? sanitize_text_field($body['start_datetime']) : (isset($body['start']) ? sanitize_text_field($body['start']) : '');
  $end   = isset($body['end_datetime']) ? sanitize_text_field($body['end_datetime']) : (isset($body['end']) ? sanitize_text_field($body['end']) : '');
  $agent_id = isset($body['agent_id']) ? (int)$body['agent_id'] : 0;

  if (!$start || !$end) {
    return new WP_REST_Response(['status'=>'error','message'=>'Missing start/end'], 400);
  }

  $start_dt = pointlybooking_admin_calendar_parse_request_datetime($start);
  $end_dt = pointlybooking_admin_calendar_parse_request_datetime($end);
  if ($start_dt === null || $end_dt === null || $end_dt <= $start_dt) {
    return new WP_REST_Response(['status'=>'error','message'=>'Invalid start/end'], 400);
  }

  $bookings_table = $wpdb->prefix . 'pointlybooking_bookings';
  if (!pointlybooking_is_safe_sql_identifier($bookings_table)) {
    return new WP_REST_Response(['status'=>'error','message'=>'Invalid table configuration'], 500);
  }

  $booking = $wpdb->get_row(
    $wpdb->prepare(
      "SELECT * FROM {$bookings_table} WHERE id=%d",
      $id
    ),
    ARRAY_A
  );
  if (!$booking) return new WP_REST_Response(['status'=>'error','message'=>'Booking not found'], 404);

  // Determine agent_id if not supplied
  if ($agent_id <= 0) $agent_id = (int)($booking['agent_id'] ?? 0);

  // Rule: do not move cancelled bookings (optional)
  if (($booking['status'] ?? '') === 'cancelled') {
    return new WP_REST_Response(['status'=>'error','message'=>'Cancelled booking cannot be rescheduled'], 400);
  }

  // Compute end time using service duration (C15.3)
  $service_id = (int)($booking['service_id'] ?? 0);
  $duration_rules = POINTLYBOOKING_ScheduleHelper::get_service_rules($service_id);
  $duration = (int)($duration_rules['duration'] ?? 0);
  if ($duration <= 0) $duration = (int)get_option('pointlybooking_slot_interval_minutes', 30);

  // If end doesn't match duration, recompute.
  if ((($end_dt - $start_dt) / 60) != $duration) {
    $end_dt = $start_dt + ($duration * 60);
  }

  // (Optional but recommended) validate agent can do service
  // If you have mapping table later: wp_pointlybooking_agent_services(agent_id, service_id) - check here.
  // For now we allow.

  // Conflict validation: same agent cannot overlap another booking (pending/confirmed)
  // We must compare against existing bookings and compute their end using duration.
  $service_id = (int)($booking['service_id'] ?? 0);

  // compute new times
  $new_date = gmdate('Y-m-d', $start_dt);
  $new_time = gmdate('H:i:s', $start_dt);

  $rules = POINTLYBOOKING_ScheduleHelper::get_service_rules($service_id);
  $dur = (int)$rules['duration'];
  $occupied = (int)$rules['occupied_min'];
  $capacity = (int)$rules['capacity'];

  // Display end uses duration; occupied end is used for conflicts
  $new_end_dt = $start_dt + ($dur * 60);
  $new_end_iso = gmdate('Y-m-d\TH:i:s', $new_end_dt);
  $new_end_dt_occ = $start_dt + ($occupied * 60);

  // Validate within working hours + breaks
  $start_time_only = gmdate('H:i:s', $start_dt);
  if (!POINTLYBOOKING_ScheduleHelper::is_within_schedule($agent_id, $new_date, $start_time_only, $occupied)) {
    return new WP_REST_Response(['status'=>'error','message'=>'Outside working hours or in break'], 409);
  }

  // Determine booking schema columns
  $book_cols = pointlybooking_db_table_columns($bookings_table);
  $has_start_date = in_array('start_date', $book_cols, true);
  $has_start_time = in_array('start_time', $book_cols, true);
  $has_end_time = in_array('end_time', $book_cols, true);
  $has_start_datetime = in_array('start_datetime', $book_cols, true);
  $has_end_datetime = in_array('end_datetime', $book_cols, true);
  $has_agent_id = in_array('agent_id', $book_cols, true);
  $has_updated_at = in_array('updated_at', $book_cols, true);

  // Find overlaps for the same agent (excluding this booking)
  $start_iso = gmdate('Y-m-d\TH:i:s', $start_dt);
  $can_check_conflicts = ($capacity > 0) && $has_agent_id && ($has_start_datetime || ($has_start_date && $has_start_time));

  if ($can_check_conflicts) {
    $existing_intervals = [];
    if ($has_start_datetime) {
      $window_from = gmdate('Y-m-d H:i:s', $start_dt - DAY_IN_SECONDS);
      $window_to = gmdate('Y-m-d H:i:s', $new_end_dt_occ);
      $conflict_rows = $wpdb->get_results(
        $wpdb->prepare(
          "SELECT id, service_id, start_datetime
          FROM {$bookings_table}
          WHERE id <> %d
            AND agent_id = %d
            AND status IN ('pending','confirmed')
            AND start_datetime <= %s
            AND start_datetime >= %s",
          $id,
          $agent_id,
          $window_to,
          $window_from
        ),
        ARRAY_A
      ) ?: [];
      foreach ($conflict_rows as $conflict_row) {
        $existing_start_ts = strtotime((string)($conflict_row['start_datetime'] ?? ''));
        if ($existing_start_ts === false) {
          continue;
        }
        $existing_rules = POINTLYBOOKING_ScheduleHelper::get_service_rules((int)($conflict_row['service_id'] ?? 0));
        $existing_intervals[] = [
          'start' => $existing_start_ts,
          'end' => $existing_start_ts + (max(5, (int)($existing_rules['occupied_min'] ?? 30)) * 60),
        ];
      }
    } else {
      $window_from = gmdate('Y-m-d', $start_dt - DAY_IN_SECONDS);
      $window_to = gmdate('Y-m-d', $new_end_dt_occ);
      $conflict_rows = $wpdb->get_results(
        $wpdb->prepare(
          "SELECT id, service_id, start_date, start_time
          FROM {$bookings_table}
          WHERE id <> %d
            AND agent_id = %d
            AND status IN ('pending','confirmed')
            AND start_date BETWEEN %s AND %s",
          $id,
          $agent_id,
          $window_from,
          $window_to
        ),
        ARRAY_A
      ) ?: [];
      foreach ($conflict_rows as $conflict_row) {
        $existing_start_ts = strtotime(
          (string)($conflict_row['start_date'] ?? '') . ' ' . (string)($conflict_row['start_time'] ?? '')
        );
        if ($existing_start_ts === false) {
          continue;
        }
        $existing_rules = POINTLYBOOKING_ScheduleHelper::get_service_rules((int)($conflict_row['service_id'] ?? 0));
        $existing_intervals[] = [
          'start' => $existing_start_ts,
          'end' => $existing_start_ts + (max(5, (int)($existing_rules['occupied_min'] ?? 30)) * 60),
        ];
      }
    }

    $overlap_count = 0;
    foreach ($existing_intervals as $existing_interval) {
      if ($existing_interval['start'] < $new_end_dt_occ && $existing_interval['end'] > $start_dt) {
        $overlap_count++;
        if ($capacity <= 1) {
          break;
        }
      }
    }

    if ($capacity <= 1 && $overlap_count > 0) {
      return new WP_REST_Response(['status'=>'error','message'=>'Time slot is not available (conflict)'], 409);
    }
    if ($capacity > 1 && $overlap_count >= $capacity) {
      return new WP_REST_Response(['status'=>'error','message'=>'Time slot is not available (capacity)'], 409);
    }
  }

  // Update booking

  $update_data = [];
  $update_formats = [];

  if ($has_start_date) {
    $update_data['start_date'] = $new_date;
    $update_formats[] = '%s';
  }
  if ($has_start_time) {
    $update_data['start_time'] = $new_time;
    $update_formats[] = '%s';
  }
  if ($has_end_time) {
    $update_data['end_time'] = gmdate('H:i:s', $new_end_dt);
    $update_formats[] = '%s';
  }
  if ($has_start_datetime) {
    $update_data['start_datetime'] = gmdate('Y-m-d H:i:s', $start_dt);
    $update_formats[] = '%s';
  }
  if ($has_end_datetime) {
    $update_data['end_datetime'] = gmdate('Y-m-d H:i:s', $new_end_dt);
    $update_formats[] = '%s';
  }
  if ($has_agent_id) {
    $update_data['agent_id'] = $agent_id;
    $update_formats[] = '%d';
  }
  if ($has_updated_at) {
    $update_data['updated_at'] = current_time('mysql');
    $update_formats[] = '%s';
  }

  if (!$update_data) {
    return new WP_REST_Response(['status'=>'error','message'=>'No updatable columns found'], 500);
  }

  $updated = $wpdb->update(
    $bookings_table,
    $update_data,
    ['id' => $id],
    $update_formats,
    ['%d']
  );

  if ($updated === false) {
    $last_error = $wpdb->last_error;
    $message = $last_error ? ('Database update failed: ' . $last_error) : 'Database update failed';
    return new WP_REST_Response(['status'=>'error','message'=>$message], 500);
  }

  // TODO later:
  // - audit log
  // - webhook
  // - email notifications

  return new WP_REST_Response([
    'status'=>'success',
    'data'=>[
      'id'=>$id,
      'start'=>$start_iso,
      'end'=>$new_end_iso,
      'agent_id'=>$agent_id
    ]
  ], 200);
}

function pointlybooking_rest_admin_booking_change_status(\WP_REST_Request $req) {
  global $wpdb;
  $id = (int) $req['id'];
  $params = $req->get_json_params();
  $status = sanitize_text_field($params['status'] ?? 'pending');

  // Validate status
  $valid_statuses = ['pending', 'confirmed', 'cancelled', 'completed'];
  if (!in_array($status, $valid_statuses)) {
    return new WP_REST_Response(['status'=>'error','message'=>'Invalid status'], 400);
  }

  $t_book = $wpdb->prefix . 'pointlybooking_bookings';
  $updated = $wpdb->update(
    $t_book,
    [
      'status' => $status,
      'updated_at' => current_time('mysql')
    ],
    ['id' => $id],
    ['%s', '%s'],
    ['%d']
  );

  if ($updated === false) {
    return new WP_REST_Response(['status'=>'error','message'=>'Failed to update status'], 500);
  }

  return new WP_REST_Response(['status'=>'success','data'=>['id'=>$id,'status'=>$status]], 200);
}


