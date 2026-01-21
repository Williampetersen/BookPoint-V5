<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {

  // Calendar bookings list (admin UI)
  register_rest_route('bp/v1', '/admin/calendar/bookings', [
    'methods'  => 'GET',
    'callback' => 'bp_admin_calendar_get_bookings',
    'permission_callback' => function () {
      return current_user_can('bp_manage_bookings') || current_user_can('bp_manage_services') || current_user_can('bp_manage_settings');
    },
  ]);

  // GET events for FullCalendar
  register_rest_route('bp/v1', '/admin/calendar', [
    'methods'  => 'GET',
    'callback' => 'bp_rest_admin_calendar_events',
    'permission_callback' => function () {
      return current_user_can('bp_manage_bookings');
    },
    'args' => [
      'start' => ['required' => true],
      'end'   => ['required' => true],
      'agent_id' => ['required' => false],
      'service_id'=> ['required' => false],
      'status' => ['required' => false],
    ],
  ]);

  // PATCH reschedule booking (drag/drop)
  register_rest_route('bp/v1', '/admin/bookings/(?P<id>\d+)/reschedule', [
    'methods'  => 'PATCH',
    'callback' => 'bp_rest_admin_booking_reschedule',
    'permission_callback' => function () {
      return current_user_can('bp_manage_bookings');
    },
  ]);
});

function bp_admin_calendar_get_bookings(WP_REST_Request $req) {
  global $wpdb;

  $start = sanitize_text_field($req->get_param('start') ?? '');
  $end   = sanitize_text_field($req->get_param('end') ?? '');
  $status = sanitize_text_field($req->get_param('status') ?? 'all');
  $agent_id = sanitize_text_field($req->get_param('agent_id') ?? 'all');
  $service_id = sanitize_text_field($req->get_param('service_id') ?? 'all');

  $start = substr($start, 0, 10);
  $end   = substr($end, 0, 10);

  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
    return new WP_REST_Response(['status'=>'error','message'=>'Invalid start/end'], 400);
  }

  // Tables
  $tB = $wpdb->prefix . 'bp_bookings';
  $tS = $wpdb->prefix . 'bp_services';
  $tA = $wpdb->prefix . 'bp_agents';
  $tC = $wpdb->prefix . 'bp_customers';

  $bCols = $wpdb->get_col("SHOW COLUMNS FROM {$tB}") ?: [];
  $sCols = $wpdb->get_col("SHOW COLUMNS FROM {$tS}") ?: [];
  $aCols = $wpdb->get_col("SHOW COLUMNS FROM {$tA}") ?: [];
  $cCols = $wpdb->get_col("SHOW COLUMNS FROM {$tC}") ?: [];

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
    "COALESCE({$durationExpr},30) AS duration",
    "COALESCE({$bufferBeforeExpr},0) AS buffer_before",
    "COALESCE({$bufferAfterExpr},0) AS buffer_after",
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

  $where = '';
  $args = [];
  if ($has_start_datetime) {
    $where = "WHERE b.start_datetime >= %s AND b.start_datetime <= %s";
    $args[] = $start . ' 00:00:00';
    $args[] = $end . ' 23:59:59';
  } else {
    $where = "WHERE b.start_date >= %s AND b.start_date <= %s";
    $args[] = $start;
    $args[] = $end;
  }

  if ($status !== 'all' && in_array($status, ['pending','confirmed','cancelled'], true)) {
    $where .= " AND b.status = %s";
    $args[] = $status;
  }
  if ($agent_id !== 'all') {
    $aid = (int)$agent_id;
    if ($aid > 0) {
      $where .= " AND b.agent_id = %d";
      $args[] = $aid;
    }
  }
  if ($service_id !== 'all') {
    $sid = (int)$service_id;
    if ($sid > 0) {
      $where .= " AND b.service_id = %d";
      $args[] = $sid;
    }
  }

  $sql = "
    SELECT " . implode(',', $select) . "
    FROM {$tB} b
    LEFT JOIN {$tS} s ON s.id = b.service_id
    LEFT JOIN {$tA} a ON a.id = b.agent_id
    LEFT JOIN {$tC} c ON c.id = b.customer_id
    {$where}
    ORDER BY " . ($has_start_datetime ? 'b.start_datetime' : 'b.start_date') . " ASC
    LIMIT 2000
  ";

  $prepared = $wpdb->prepare($sql, $args);
  $rows = $wpdb->get_results($prepared, ARRAY_A) ?: [];

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

    $start_date = $start_ts ? date('Y-m-d', $start_ts) : ($r['start_date'] ?? '');
    $start_time = $start_ts ? date('H:i', $start_ts) : substr((string)($r['start_time'] ?? ''), 0, 5);
    $end_time = $end_ts ? date('H:i', $end_ts) : '';

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
      'title' => trim((($r['service_name'] ?? 'Service') ?: 'Service') . ' • ' . ($customer_name ?: 'Customer')),
    ];
  }

  return new WP_REST_Response(['status'=>'success','data'=>$data], 200);
}


function bp_rest_admin_calendar_events(WP_REST_Request $req) {
  global $wpdb;

  $start = sanitize_text_field($req->get_param('start'));
  $end   = sanitize_text_field($req->get_param('end'));

  // Expect YYYY-MM-DD or ISO; normalize to YYYY-MM-DD
  $start_date = substr($start, 0, 10);
  $end_date   = substr($end, 0, 10);

  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    return new WP_REST_Response(['status'=>'error','message'=>'Invalid date range'], 400);
  }

  $agent_id  = (int)($req->get_param('agent_id') ?: 0);
  $service_id= (int)($req->get_param('service_id') ?: 0);
  $status    = sanitize_text_field($req->get_param('status') ?: '');

  $t_book = $wpdb->prefix . 'bp_bookings';
  $t_srv  = $wpdb->prefix . 'bp_services';

  // We compute end time using service duration (minutes)
  // Assumed columns:
  // bookings: id, customer_name, status, service_id, agent_id, start_date, start_time, total_price
  // services: id, name, duration
  $where = "WHERE b.start_date BETWEEN %s AND %s";
  $params = [$start_date, $end_date];

  if ($agent_id > 0) { $where .= " AND b.agent_id = %d"; $params[] = $agent_id; }
  if ($service_id > 0) { $where .= " AND b.service_id = %d"; $params[] = $service_id; }
  if ($status !== '') { $where .= " AND b.status = %s"; $params[] = $status; }

  $sql = "
    SELECT
      b.id,
      b.customer_name,
      b.status,
      b.service_id,
      b.agent_id,
      b.start_date,
      b.start_time,
      COALESCE(b.total_price,0) as total_price,
      COALESCE(s.name,'(deleted)') as service_name,
      COALESCE(s.duration,30) as duration_min
    FROM {$t_book} b
    LEFT JOIN {$t_srv} s ON s.id = b.service_id
    {$where}
    ORDER BY b.start_date ASC, b.start_time ASC
  ";

  $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

  $events = [];
  foreach ($rows as $r) {
    $dur = max(5, (int)$r['duration_min']);
    $startIso = $r['start_date'] . 'T' . substr($r['start_time'], 0, 5) . ':00';
    $endIso = gmdate('Y-m-d\TH:i:s', strtotime($startIso . " +{$dur} minutes"));

    $title = $r['service_name'] . ' • ' . ($r['customer_name'] ?: ('#'.$r['id']));

    $events[] = [
      'id' => (int)$r['id'],
      'title' => $title,
      'start' => $startIso,
      'end'   => $endIso,
      'extendedProps' => [
        'status' => $r['status'],
        'service_id' => (int)$r['service_id'],
        'agent_id' => (int)$r['agent_id'],
        'customer_name' => $r['customer_name'],
        'total_price' => (float)$r['total_price'],
      ],
    ];
  }

  return new WP_REST_Response(['status'=>'success','data'=>$events], 200);
}


function bp_rest_admin_booking_reschedule(WP_REST_Request $req) {
  global $wpdb;

  $id = (int)$req['id'];
  if ($id <= 0) return new WP_REST_Response(['status'=>'error','message'=>'Invalid booking id'], 400);

  $body = $req->get_json_params();
  $start = isset($body['start']) ? sanitize_text_field($body['start']) : '';
  $end   = isset($body['end']) ? sanitize_text_field($body['end']) : '';
  $agent_id = isset($body['agent_id']) ? (int)$body['agent_id'] : 0;

  if (!$start || !$end) {
    return new WP_REST_Response(['status'=>'error','message'=>'Missing start/end'], 400);
  }

  // Normalize: YYYY-MM-DDTHH:MM:SS
  $start_dt = strtotime($start);
  $end_dt = strtotime($end);
  if (!$start_dt || !$end_dt || $end_dt <= $start_dt) {
    return new WP_REST_Response(['status'=>'error','message'=>'Invalid start/end'], 400);
  }

  $t_book = $wpdb->prefix . 'bp_bookings';
  $t_srv  = $wpdb->prefix . 'bp_services';

  $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t_book} WHERE id=%d", $id), ARRAY_A);
  if (!$booking) return new WP_REST_Response(['status'=>'error','message'=>'Booking not found'], 404);

  // Determine agent_id if not supplied
  if ($agent_id <= 0) $agent_id = (int)($booking['agent_id'] ?? 0);

  // Rule: do not move cancelled bookings (optional)
  if (($booking['status'] ?? '') === 'cancelled') {
    return new WP_REST_Response(['status'=>'error','message'=>'Cancelled booking cannot be rescheduled'], 400);
  }

  // (Optional but recommended) validate agent can do service
  // If you have mapping table later: wp_bp_agent_services(agent_id, service_id) - check here.
  // For now we allow.

  // Conflict validation: same agent cannot overlap another booking (pending/confirmed)
  // We must compare against existing bookings and compute their end using duration.
  $service_id = (int)($booking['service_id'] ?? 0);

  // compute new times
  $new_date = gmdate('Y-m-d', $start_dt);
  $new_time = gmdate('H:i:s', $start_dt);

  $rules = BP_ScheduleHelper::get_service_rules($service_id);
  $dur = (int)$rules['duration'];
  $occupied = (int)$rules['occupied_min'];
  $capacity = (int)$rules['capacity'];

  // Display end uses duration; occupied end is used for conflicts
  $new_end_dt = $start_dt + ($dur * 60);
  $new_end_iso = gmdate('Y-m-d\TH:i:s', $new_end_dt);
  $new_end_dt_occ = $start_dt + ($occupied * 60);

  // Validate within working hours + breaks
  $start_time_only = gmdate('H:i:s', $start_dt);
  if (!BP_ScheduleHelper::is_within_schedule($agent_id, $new_date, $start_time_only, $occupied)) {
    return new WP_REST_Response(['status'=>'error','message'=>'Outside working hours or in break'], 409);
  }

  // Find overlaps for the same agent (excluding this booking)
  // We compare by building existing booking start+duration end in SQL.
  $start_iso = gmdate('Y-m-d\TH:i:s', $start_dt);
  $end_iso   = gmdate('Y-m-d\TH:i:s', $new_end_dt_occ);

  if ($capacity <= 1) {
    $sql = "
      SELECT b.id
      FROM {$t_book} b
      LEFT JOIN {$t_srv} s ON s.id = b.service_id
      WHERE b.id <> %d
        AND b.agent_id = %d
        AND b.status IN ('pending','confirmed')
        AND (
          STR_TO_DATE(CONCAT(b.start_date,' ',b.start_time), '%%Y-%%m-%%d %%H:%%i:%%s')
          < STR_TO_DATE(%s, '%%Y-%%m-%%dT%%H:%%i:%%s')
        )
        AND (
          DATE_ADD(
            STR_TO_DATE(CONCAT(b.start_date,' ',b.start_time), '%%Y-%%m-%%d %%H:%%i:%%s'),
            INTERVAL (COALESCE(s.duration,30) + COALESCE(s.buffer_before,0) + COALESCE(s.buffer_after,0)) MINUTE
          )
          > STR_TO_DATE(%s, '%%Y-%%m-%%dT%%H:%%i:%%s')
        )
      LIMIT 1
    ";

    $conflict = $wpdb->get_var($wpdb->prepare($sql, $id, $agent_id, $end_iso, $start_iso));
    if ($conflict) {
      return new WP_REST_Response(['status'=>'error','message'=>'Time slot is not available (conflict)'], 409);
    }
  } else {
    $sql = "
      SELECT COUNT(*)
      FROM {$t_book} b
      LEFT JOIN {$t_srv} s ON s.id = b.service_id
      WHERE b.id <> %d
        AND b.agent_id = %d
        AND b.status IN ('pending','confirmed')
        AND (
          STR_TO_DATE(CONCAT(b.start_date,' ',b.start_time), '%%Y-%%m-%%d %%H:%%i:%%s')
          < STR_TO_DATE(%s, '%%Y-%%m-%%dT%%H:%%i:%%s')
        )
        AND (
          DATE_ADD(
            STR_TO_DATE(CONCAT(b.start_date,' ',b.start_time), '%%Y-%%m-%%d %%H:%%i:%%s'),
            INTERVAL (COALESCE(s.duration,30) + COALESCE(s.buffer_before,0) + COALESCE(s.buffer_after,0)) MINUTE
          )
          > STR_TO_DATE(%s, '%%Y-%%m-%%dT%%H:%%i:%%s')
        )
    ";

    $count = (int)$wpdb->get_var($wpdb->prepare($sql, $id, $agent_id, $end_iso, $start_iso));
    if ($count >= $capacity) {
      return new WP_REST_Response(['status'=>'error','message'=>'Time slot is not available (capacity)'], 409);
    }
  }

  // Update booking
  $updated = $wpdb->update(
    $t_book,
    [
      'start_date' => $new_date,
      'start_time' => $new_time,
      'agent_id'   => $agent_id,
    ],
    ['id' => $id],
    ['%s','%s','%d'],
    ['%d']
  );

  if ($updated === false) {
    return new WP_REST_Response(['status'=>'error','message'=>'Database update failed'], 500);
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
