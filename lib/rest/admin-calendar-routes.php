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

  $local_formats = ['Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i', 'Y-m-d\TH:i:s.u'];
  foreach ($local_formats as $format) {
    $dt = \DateTimeImmutable::createFromFormat('!' . $format, $value, wp_timezone());
    $errors = \DateTimeImmutable::getLastErrors();
    if ($dt instanceof \DateTimeImmutable && is_array($errors) && (int)$errors['warning_count'] === 0 && (int)$errors['error_count'] === 0) {
      return $dt->getTimestamp();
    }
  }

  $tz_formats = ['Y-m-d\TH:i:sP', 'Y-m-d\TH:iP', 'Y-m-d\TH:i:s.uP', 'Y-m-d\TH:i:s\Z', 'Y-m-d\TH:i:s.u\Z'];
  foreach ($tz_formats as $format) {
    $dt = \DateTimeImmutable::createFromFormat('!' . $format, $value);
    $errors = \DateTimeImmutable::getLastErrors();
    if ($dt instanceof \DateTimeImmutable && is_array($errors) && (int)$errors['warning_count'] === 0 && (int)$errors['error_count'] === 0) {
      return $dt->getTimestamp();
    }
  }

  return null;
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

  // Tables
  $tB = pointlybooking_table('bookings');
  $tS = pointlybooking_table('services');
  $tA = pointlybooking_table('agents');
  $tC = pointlybooking_table('customers');

  $bCols = $wpdb->get_col(pointlybooking_prepare_query_with_identifiers("SHOW COLUMNS FROM %i", [$tB])) ?: [];
  $sCols = $wpdb->get_col(pointlybooking_prepare_query_with_identifiers("SHOW COLUMNS FROM %i", [$tS])) ?: [];
  $aCols = $wpdb->get_col(pointlybooking_prepare_query_with_identifiers("SHOW COLUMNS FROM %i", [$tA])) ?: [];
  $cCols = $wpdb->get_col(pointlybooking_prepare_query_with_identifiers("SHOW COLUMNS FROM %i", [$tC])) ?: [];

  $has_start_datetime = in_array('start_datetime', $bCols, true);
  $has_start_date = in_array('start_date', $bCols, true);
  $has_start_time = in_array('start_time', $bCols, true);

  $durationExpr = in_array('duration_minutes', $sCols, true)
    ? 's.duration_minutes'
    : (in_array('duration', $sCols, true) ? 's.duration' : '30');
  $bufferBeforeExpr = in_array('buffer_before_minutes', $sCols, true)
    ? 's.buffer_before_minutes'
    : (in_array('buffer_before', $sCols, true) ? 's.buffer_before' : '0');
  $bufferAfterExpr = in_array('buffer_after_minutes', $sCols, true)
    ? 's.buffer_after_minutes'
    : (in_array('buffer_after', $sCols, true) ? 's.buffer_after' : '0');

  $select = [
    'b.id',
    'b.status',
    'b.service_id',
    'b.agent_id',
    'b.customer_id',
    "COALESCE(s.name,'') AS service_name",
    "COALESCE(" . $durationExpr . ",30) AS duration",
    "COALESCE(" . $bufferBeforeExpr . ",0) AS buffer_before",
    "COALESCE(" . $bufferAfterExpr . ",0) AS buffer_after",
  ];

  if ($has_start_datetime) $select[] = 'b.start_datetime';
  if ($has_start_date) $select[] = 'b.start_date';
  if ($has_start_time) $select[] = 'b.start_time';

  if (in_array('name', $aCols, true)) {
    $select[] = "COALESCE(a.name,'') AS agent_name";
  } else {
    if (in_array('first_name', $aCols, true)) $select[] = "COALESCE(a.first_name,'') AS agent_first_name";
    if (in_array('last_name', $aCols, true)) $select[] = "COALESCE(a.last_name,'') AS agent_last_name";
  }

  if (in_array('first_name', $cCols, true)) $select[] = "COALESCE(c.first_name,'') AS customer_first_name";
  if (in_array('last_name', $cCols, true)) $select[] = "COALESCE(c.last_name,'') AS customer_last_name";

  $where_clauses = [];
  $args = [];
  if ($has_start_datetime) {
    $where_clauses[] = 'b.start_datetime >= %s';
    $where_clauses[] = 'b.start_datetime <= %s';
    $args[] = $start . ' 00:00:00';
    $args[] = $end . ' 23:59:59';
  } else {
    $where_clauses[] = 'b.start_date >= %s';
    $where_clauses[] = 'b.start_date <= %s';
    $args[] = $start;
    $args[] = $end;
  }

  if ($status !== 'all' && in_array($status, ['pending','confirmed','cancelled'], true)) {
    $where_clauses[] = 'b.status = %s';
    $args[] = $status;
  }
  if ($agent_id !== 'all') {
    $aid = (int)$agent_id;
    if ($aid > 0) {
      $where_clauses[] = 'b.agent_id = %d';
      $args[] = $aid;
    }
  }
  if ($service_id !== 'all') {
    $sid = (int)$service_id;
    if ($sid > 0) {
      $where_clauses[] = 'b.service_id = %d';
      $args[] = $sid;
    }
  }

  $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
  $order_by = $has_start_datetime ? 'b.start_datetime' : 'b.start_date';
  $sql = "SELECT " . implode(',', $select) . "
      FROM %i b
      LEFT JOIN %i s ON s.id = b.service_id
      LEFT JOIN %i a ON a.id = b.agent_id
      LEFT JOIN %i c ON c.id = b.customer_id
      " . $where_sql . "
      ORDER BY " . $order_by . " ASC
      LIMIT 2000";

  $rows = $wpdb->get_results(
    pointlybooking_prepare_query_with_identifiers(
      $sql,
      [$tB, $tS, $tA, $tC],
      $args
    ),
    ARRAY_A
  ) ?: [];

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

    $customer_name = trim((string)($r['customer_first_name'] ?? '') . ' ' . (string)($r['customer_last_name'] ?? ''));

    $agent_name = '';
    if (!empty($r['agent_name'])) {
      $agent_name = (string)$r['agent_name'];
    } else {
      $agent_name = trim((string)($r['agent_first_name'] ?? '') . ' ' . (string)($r['agent_last_name'] ?? ''));
    }

    $data[] = [
      'id' => (int)$r['id'],
      'date' => $start_date,
      'start_time' => $start_time,
      'end_time' => $end_time,
      'status' => $r['status'],
      'service_id' => (int)$r['service_id'],
      'agent_id' => (int)$r['agent_id'],
      'customer_id' => (int)$r['customer_id'],
      'service_name' => $r['service_name'] ?? '',
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

  $t_book = pointlybooking_table('bookings');
  $t_srv  = pointlybooking_table('services');
  $t_cust = pointlybooking_table('customers');
  $t_agent = pointlybooking_table('agents');

  $bCols = $wpdb->get_col(pointlybooking_prepare_query_with_identifiers("SHOW COLUMNS FROM %i", [$t_book])) ?: [];
  $sCols = $wpdb->get_col(pointlybooking_prepare_query_with_identifiers("SHOW COLUMNS FROM %i", [$t_srv])) ?: [];
  $aCols = $wpdb->get_col(pointlybooking_prepare_query_with_identifiers("SHOW COLUMNS FROM %i", [$t_agent])) ?: [];
  $cCols = $wpdb->get_col(pointlybooking_prepare_query_with_identifiers("SHOW COLUMNS FROM %i", [$t_cust])) ?: [];

  $has_start_datetime = in_array('start_datetime', $bCols, true);
  $has_end_datetime = in_array('end_datetime', $bCols, true);
  $has_start_date = in_array('start_date', $bCols, true);
  $has_start_time = in_array('start_time', $bCols, true);

  $durationExpr = in_array('duration_minutes', $sCols, true)
    ? 's.duration_minutes'
    : (in_array('duration', $sCols, true) ? 's.duration' : '30');

  $bufferBeforeExpr = in_array('buffer_before_minutes', $sCols, true)
    ? 's.buffer_before_minutes'
    : (in_array('buffer_before', $sCols, true) ? 's.buffer_before' : '0');
  $bufferAfterExpr = in_array('buffer_after_minutes', $sCols, true)
    ? 's.buffer_after_minutes'
    : (in_array('buffer_after', $sCols, true) ? 's.buffer_after' : '0');

  $select = [
    'b.id',
    'b.status',
    'b.service_id',
    'b.agent_id',
    'b.customer_id',
    "COALESCE(s.name,'') AS service_name",
    "COALESCE(" . $durationExpr . ",30) AS duration",
    "COALESCE(" . $bufferBeforeExpr . ",0) AS buffer_before",
    "COALESCE(" . $bufferAfterExpr . ",0) AS buffer_after",
  ];

  if ($has_start_datetime) $select[] = 'b.start_datetime';
  if ($has_end_datetime) $select[] = 'b.end_datetime';
  if ($has_start_date) $select[] = 'b.start_date';
  if ($has_start_time) $select[] = 'b.start_time';

  if (in_array('name', $aCols, true)) {
    $select[] = "COALESCE(a.name,'') AS agent_name";
  } else {
    if (in_array('first_name', $aCols, true)) $select[] = "COALESCE(a.first_name,'') AS agent_first_name";
    if (in_array('last_name', $aCols, true)) $select[] = "COALESCE(a.last_name,'') AS agent_last_name";
  }

  if (in_array('first_name', $cCols, true)) $select[] = "COALESCE(c.first_name,'') AS customer_first_name";
  if (in_array('last_name', $cCols, true)) $select[] = "COALESCE(c.last_name,'') AS customer_last_name";
  if (in_array('email', $cCols, true)) $select[] = "COALESCE(c.email,'') AS customer_email";

  $where_clauses = [];
  $params = [];
  if ($has_start_datetime) {
    $where_clauses[] = 'b.start_datetime >= %s';
    $where_clauses[] = 'b.start_datetime <= %s';
    $params[] = $start_date . ' 00:00:00';
    $params[] = $end_date . ' 23:59:59';
  } else {
    $where_clauses[] = 'b.start_date >= %s';
    $where_clauses[] = 'b.start_date <= %s';
    $params[] = $start_date;
    $params[] = $end_date;
  }

  if ($agent_id > 0) { $where_clauses[] = 'b.agent_id = %d'; $params[] = $agent_id; }
  if ($service_id > 0) { $where_clauses[] = 'b.service_id = %d'; $params[] = $service_id; }
  if ($status !== '' && $status !== 'all') { $where_clauses[] = 'LOWER(b.status) = %s'; $params[] = strtolower($status); }
  if ($q !== '') { 
    $like = '%'.$wpdb->esc_like($q).'%';
    $where_clauses[] = "(b.customer_name LIKE %s OR b.customer_email LIKE %s OR s.name LIKE %s OR a.name LIKE %s OR CONCAT(a.first_name,' ',a.last_name) LIKE %s OR c.email LIKE %s)";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
  }

  $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
  $order_by = $has_start_datetime ? 'b.start_datetime' : 'b.start_date';
  $sql = "SELECT " . implode(',', $select) . "
      FROM %i b
      LEFT JOIN %i s ON s.id = b.service_id
      LEFT JOIN %i a ON a.id = b.agent_id
      LEFT JOIN %i c ON c.id = b.customer_id
      " . $where_sql . "
      ORDER BY " . $order_by . " ASC";

  $rows = $wpdb->get_results(
    pointlybooking_prepare_query_with_identifiers(
      $sql,
      [$t_book, $t_srv, $t_agent, $t_cust],
      $params
    ),
    ARRAY_A
  ) ?: [];

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

    $customer_name = trim((string)($r['customer_first_name'] ?? '') . ' ' . (string)($r['customer_last_name'] ?? ''));

    $agent_name = '';
    if (!empty($r['agent_name'])) {
      $agent_name = (string)$r['agent_name'];
    } else {
      $agent_name = trim((string)($r['agent_first_name'] ?? '') . ' ' . (string)($r['agent_last_name'] ?? ''));
    }

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
      'customer_email' => $r['customer_email'] ?? '',
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

  $t_book = pointlybooking_table('bookings');
  $t_srv  = pointlybooking_table('services');

  $booking = $wpdb->get_row(
    pointlybooking_prepare_query_with_identifiers(
      "SELECT * FROM %i WHERE id=%d",
      [$t_book],
      [$id]
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
  $duration = 0;
  if ($service_id > 0) {
    $sCols = $wpdb->get_col(pointlybooking_prepare_query_with_identifiers("SHOW COLUMNS FROM %i", [$t_srv])) ?: [];
    $durationCol = in_array('duration_minutes', $sCols, true) ? 'duration_minutes' : (in_array('duration', $sCols, true) ? 'duration' : '');
    if ($durationCol !== '') {
      $durationSql = "SELECT " . pointlybooking_quote_sql_identifier($durationCol) . " FROM %i WHERE id=%d";
      $duration = (int)$wpdb->get_var(
        pointlybooking_prepare_query_with_identifiers(
          $durationSql,
          [$t_srv],
          [$service_id]
        )
      );
    }
  }
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
  $book_cols = $wpdb->get_col(pointlybooking_prepare_query_with_identifiers("SHOW COLUMNS FROM %i", [$t_book])) ?: [];
  $has_start_date = in_array('start_date', $book_cols, true);
  $has_start_time = in_array('start_time', $book_cols, true);
  $has_end_time = in_array('end_time', $book_cols, true);
  $has_start_datetime = in_array('start_datetime', $book_cols, true);
  $has_end_datetime = in_array('end_datetime', $book_cols, true);
  $has_agent_id = in_array('agent_id', $book_cols, true);
  $has_updated_at = in_array('updated_at', $book_cols, true);

  // Find overlaps for the same agent (excluding this booking)
  // We compare by building existing booking start+duration end in SQL.
  $start_iso = gmdate('Y-m-d\TH:i:s', $start_dt);
  $end_iso   = gmdate('Y-m-d\TH:i:s', $new_end_dt_occ);

  $start_expr = '';
  if ($has_start_datetime) {
    $start_expr = 'b.start_datetime';
  } elseif ($has_start_date && $has_start_time) {
    $start_expr = "STR_TO_DATE(CONCAT(b.start_date,' ',b.start_time), '%%Y-%%m-%%d %%H:%%i:%%s')";
  }

  $can_check_conflicts = ($capacity > 0) && $has_agent_id && !empty($start_expr);

  if ($can_check_conflicts) {
    $sCols = $wpdb->get_col(pointlybooking_prepare_query_with_identifiers("SHOW COLUMNS FROM %i", [$t_srv])) ?: [];
    $durationExpr = in_array('duration_minutes', $sCols, true)
      ? ('s.' . pointlybooking_quote_sql_identifier('duration_minutes'))
      : (in_array('duration', $sCols, true) ? 's.duration' : '30');
    $bufferBeforeExpr = in_array('buffer_before_minutes', $sCols, true)
      ? ('s.' . pointlybooking_quote_sql_identifier('buffer_before_minutes'))
      : (in_array('buffer_before', $sCols, true) ? 's.buffer_before' : '0');
    $bufferAfterExpr = in_array('buffer_after_minutes', $sCols, true)
      ? ('s.' . pointlybooking_quote_sql_identifier('buffer_after_minutes'))
      : (in_array('buffer_after', $sCols, true) ? 's.buffer_after' : '0');
    if ($durationExpr === 's.duration') $durationExpr = 's.' . pointlybooking_quote_sql_identifier('duration');
    if ($bufferBeforeExpr === 's.buffer_before') $bufferBeforeExpr = 's.' . pointlybooking_quote_sql_identifier('buffer_before');
    if ($bufferAfterExpr === 's.buffer_after') $bufferAfterExpr = 's.' . pointlybooking_quote_sql_identifier('buffer_after');

    $end_expr = "DATE_ADD(" . $start_expr . ", INTERVAL (COALESCE(" . $durationExpr . ",30) + COALESCE(" . $bufferBeforeExpr . ",0) + COALESCE(" . $bufferAfterExpr . ",0)) MINUTE)";

    if ($capacity <= 1) {
      $conflict_sql = "SELECT b.id
          FROM %i b
          LEFT JOIN %i s ON s.id = b.service_id
          WHERE b.id <> %d
            AND b.agent_id = %d
            AND b.status IN ('pending','confirmed')
            AND (" . $start_expr . " < STR_TO_DATE(%s, '%%Y-%%m-%%dT%%H:%%i:%%s'))
            AND (" . $end_expr . " > STR_TO_DATE(%s, '%%Y-%%m-%%dT%%H:%%i:%%s'))
          LIMIT 1";
      $conflict = $wpdb->get_var(
        pointlybooking_prepare_query_with_identifiers(
          $conflict_sql,
          [$t_book, $t_srv],
          [$id, $agent_id, $end_iso, $start_iso]
        )
      );
      if ($wpdb->last_error) {
        return new WP_REST_Response(['status'=>'error','message'=>'Conflict check failed: ' . $wpdb->last_error], 500);
      }
      if ($conflict) {
        return new WP_REST_Response(['status'=>'error','message'=>'Time slot is not available (conflict)'], 409);
      }
    } else {
      $capacity_sql = "SELECT COUNT(*)
          FROM %i b
          LEFT JOIN %i s ON s.id = b.service_id
          WHERE b.id <> %d
            AND b.agent_id = %d
            AND b.status IN ('pending','confirmed')
            AND (" . $start_expr . " < STR_TO_DATE(%s, '%%Y-%%m-%%dT%%H:%%i:%%s'))
            AND (" . $end_expr . " > STR_TO_DATE(%s, '%%Y-%%m-%%dT%%H:%%i:%%s'))";
      $count = (int)$wpdb->get_var(
        pointlybooking_prepare_query_with_identifiers(
          $capacity_sql,
          [$t_book, $t_srv],
          [$id, $agent_id, $end_iso, $start_iso]
        )
      );
      if ($wpdb->last_error) {
        return new WP_REST_Response(['status'=>'error','message'=>'Conflict check failed: ' . $wpdb->last_error], 500);
      }
      if ($count >= $capacity) {
        return new WP_REST_Response(['status'=>'error','message'=>'Time slot is not available (capacity)'], 409);
      }
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
    $t_book,
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

