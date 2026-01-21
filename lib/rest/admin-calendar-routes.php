<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {

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
