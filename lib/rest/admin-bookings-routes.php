<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {

  // Booking details
  register_rest_route('bp/v1', '/admin/bookings/(?P<id>\d+)', [
    'methods'  => 'GET',
    'callback' => 'bp_rest_admin_booking_get',
    'permission_callback' => function () {
      return current_user_can('bp_manage_bookings') || current_user_can('bp_manage_settings');
    },
  ]);

  // Update booking (status/notes/agent/date+time)
  register_rest_route('bp/v1', '/admin/bookings/(?P<id>\d+)', [
    'methods'  => 'PATCH',
    'callback' => 'bp_rest_admin_booking_patch',
    'permission_callback' => function () {
      return current_user_can('bp_manage_bookings') || current_user_can('bp_manage_settings');
    },
  ]);

  // Agents list (for dropdown)
  register_rest_route('bp/v1', '/admin/agents', [
    'methods'  => 'GET',
    'callback' => 'bp_rest_admin_agents_list',
    'permission_callback' => function () {
      return current_user_can('bp_manage_bookings');
    },
  ]);
});


function bp_rest_admin_booking_get(WP_REST_Request $req) {
  global $wpdb;

  $id = (int)$req['id'];
  if ($id <= 0) return new WP_REST_Response(['status'=>'error','message'=>'Invalid id'], 400);

  $t_book = $wpdb->prefix . 'bp_bookings';
  $t_srv  = $wpdb->prefix . 'bp_services';
  $t_ag   = $wpdb->prefix . 'bp_agents';
  $t_cus  = $wpdb->prefix . 'bp_customers';

  $bCols = $wpdb->get_col("SHOW COLUMNS FROM {$t_book}") ?: [];
  $sCols = $wpdb->get_col("SHOW COLUMNS FROM {$t_srv}") ?: [];
  $aCols = $wpdb->get_col("SHOW COLUMNS FROM {$t_ag}") ?: [];
  $cCols = $wpdb->get_col("SHOW COLUMNS FROM {$t_cus}") ?: [];

  $has_start_datetime = in_array('start_datetime', $bCols, true);
  $has_start_date = in_array('start_date', $bCols, true);
  $has_start_time = in_array('start_time', $bCols, true);
  $has_admin_notes = in_array('admin_notes', $bCols, true);
  $has_notes = in_array('notes', $bCols, true);

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
    'b.*',
    "COALESCE(s.name,'') AS service_name",
    "COALESCE({$durationExpr},30) AS duration",
    "COALESCE({$bufferBeforeExpr},0) AS buffer_before",
    "COALESCE({$bufferAfterExpr},0) AS buffer_after",
  ];

  if (in_array('name', $aCols, true)) {
    $select[] = "COALESCE(a.name,'') AS agent_name";
  } else {
    if (in_array('first_name', $aCols, true)) $select[] = "COALESCE(a.first_name,'') AS agent_first_name";
    if (in_array('last_name', $aCols, true)) $select[] = "COALESCE(a.last_name,'') AS agent_last_name";
  }

  if (in_array('first_name', $cCols, true)) $select[] = "COALESCE(c.first_name,'') AS customer_first_name";
  if (in_array('last_name', $cCols, true)) $select[] = "COALESCE(c.last_name,'') AS customer_last_name";
  if (in_array('email', $cCols, true)) $select[] = "COALESCE(c.email,'') AS customer_email";
  if (in_array('phone', $cCols, true)) $select[] = "COALESCE(c.phone,'') AS customer_phone";

  $row = $wpdb->get_row($wpdb->prepare("
    SELECT " . implode(',', $select) . "
    FROM {$t_book} b
    LEFT JOIN {$t_srv} s ON s.id = b.service_id
    LEFT JOIN {$t_ag} a ON a.id = b.agent_id
    LEFT JOIN {$t_cus} c ON c.id = b.customer_id
    WHERE b.id = %d
    LIMIT 1
  ", $id), ARRAY_A);

  if (!$row) return new WP_REST_Response(['status'=>'error','message'=>'Not found'], 404);

  $customer_name = trim((string)($row['customer_first_name'] ?? '') . ' ' . (string)($row['customer_last_name'] ?? ''));

  $duration = (int)($row['duration'] ?? 30);
  $bf = (int)($row['buffer_before'] ?? 0);
  $ba = (int)($row['buffer_after'] ?? 0);
  $occupied = max(5, $duration + $bf + $ba);

  $start_dt = '';
  if ($has_start_datetime && !empty($row['start_datetime'])) {
    $start_dt = $row['start_datetime'];
  } elseif ($has_start_date && $has_start_time) {
    $start_dt = ($row['start_date'] ?? '') . ' ' . ($row['start_time'] ?? '');
  }

  $start_ts = $start_dt ? strtotime($start_dt) : null;
  $end_time = $start_ts ? date('H:i', $start_ts + $occupied * 60) : '';
  $date = $start_ts ? date('Y-m-d', $start_ts) : ($row['start_date'] ?? '');
  $start_time = $start_ts ? date('H:i', $start_ts) : substr((string)($row['start_time'] ?? ''), 0, 5);

  $agent_name = '';
  if (!empty($row['agent_name'])) {
    $agent_name = (string)$row['agent_name'];
  } else {
    $agent_name = trim((string)($row['agent_first_name'] ?? '') . ' ' . (string)($row['agent_last_name'] ?? ''));
  }

  $admin_notes = '';
  if ($has_admin_notes) {
    $admin_notes = (string)($row['admin_notes'] ?? '');
  } elseif ($has_notes) {
    $admin_notes = (string)($row['notes'] ?? '');
  }

  $data = [
    'id' => (int)$row['id'],
    'status' => $row['status'] ?? '',
    'service_id' => (int)($row['service_id'] ?? 0),
    'agent_id' => (int)($row['agent_id'] ?? 0),
    'customer_id' => (int)($row['customer_id'] ?? 0),
    'date' => $date,
    'start_time' => $start_time,
    'end_time' => $end_time,
    'service_name' => $row['service_name'] ?? '',
    'agent_name' => $agent_name,
    'customer_name' => $customer_name,
    'customer_email' => $row['customer_email'] ?? '',
    'customer_phone' => $row['customer_phone'] ?? '',
    'admin_notes' => $admin_notes,
  ];

  return new WP_REST_Response(['status'=>'success','data'=>$data], 200);
}


function bp_rest_admin_agents_list(WP_REST_Request $req) {
  global $wpdb;
  $t = $wpdb->prefix . 'bp_agents';

  // Adjust columns if needed: id, name, image_id
  $rows = $wpdb->get_results("SELECT id, name FROM {$t} ORDER BY name ASC", ARRAY_A);
  return new WP_REST_Response(['status'=>'success','data'=>($rows ?: [])], 200);
}


function bp_rest_admin_booking_patch(WP_REST_Request $req) {
  global $wpdb;

  $id = (int)$req['id'];
  if ($id <= 0) return new WP_REST_Response(['status'=>'error','message'=>'Invalid id'], 400);

  $t_book = $wpdb->prefix . 'bp_bookings';
  $t_srv  = $wpdb->prefix . 'bp_services';

  $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t_book} WHERE id=%d", $id), ARRAY_A);
  if (!$booking) return new WP_REST_Response(['status'=>'error','message'=>'Not found'], 404);

  $body = $req->get_json_params();
  if (!is_array($body) || empty($body)) {
    $raw = $req->get_body();
    $decoded = json_decode($raw ?: '', true);
    if (is_array($decoded)) $body = $decoded;
  }

  $updates = [];
  $formats = [];

  $bCols = $wpdb->get_col("SHOW COLUMNS FROM {$t_book}") ?: [];
  $sCols = $wpdb->get_col("SHOW COLUMNS FROM {$t_srv}") ?: [];
  $has_start_datetime = in_array('start_datetime', $bCols, true);
  $has_end_datetime = in_array('end_datetime', $bCols, true);
  $has_start_date = in_array('start_date', $bCols, true);
  $has_start_time = in_array('start_time', $bCols, true);

  // status change
  if (isset($body['status'])) {
    $status = sanitize_text_field($body['status']);
    if (!in_array($status, ['pending','confirmed','cancelled'], true)) {
      return new WP_REST_Response(['status'=>'error','message'=>'Invalid status'], 400);
    }
    $updates['status'] = $status;
    $formats[] = '%s';
  }

  // notes / admin_notes
  $has_admin_notes = in_array('admin_notes', $bCols, true);
  $has_notes = in_array('notes', $bCols, true);
  if (isset($body['admin_notes']) || isset($body['notes'])) {
    $notes_val = isset($body['admin_notes']) ? (string)$body['admin_notes'] : (string)$body['notes'];
    $notes_val = wp_kses_post($notes_val);
    if ($has_admin_notes) {
      $updates['admin_notes'] = $notes_val;
      $formats[] = '%s';
    } elseif ($has_notes) {
      $updates['notes'] = $notes_val;
      $formats[] = '%s';
    }
  }

  $start_date = isset($body['start_date']) ? sanitize_text_field($body['start_date']) : null;
  $start_time = isset($body['start_time']) ? sanitize_text_field($body['start_time']) : null;

  if ($start_date !== null) {
    $start_date = substr($start_date, 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
      return new WP_REST_Response(['status'=>'error','message'=>'Invalid start_date'], 400);
    }
  }
  if ($start_time !== null) {
    $start_time = substr($start_time, 0, 5);
    if (!preg_match('/^\d{2}:\d{2}$/', $start_time)) {
      return new WP_REST_Response(['status'=>'error','message'=>'Invalid start_time'], 400);
    }
  }

  // agent_id
  if (isset($body['agent_id'])) {
    $agent_id = (int)$body['agent_id'];
    if ($agent_id < 0) $agent_id = 0;
    $updates['agent_id'] = $agent_id;
    $formats[] = '%d';
  }

  if ($start_date !== null || $start_time !== null) {
    $currentDate = '';
    $currentTime = '';
    if ($has_start_datetime && !empty($booking['start_datetime'])) {
      $ts = strtotime($booking['start_datetime']);
      if ($ts) {
        $currentDate = date('Y-m-d', $ts);
        $currentTime = date('H:i', $ts);
      }
    }
    if ($currentDate === '' && $has_start_date) $currentDate = substr((string)($booking['start_date'] ?? ''), 0, 10);
    if ($currentTime === '' && $has_start_time) $currentTime = substr((string)($booking['start_time'] ?? ''), 0, 5);

    $new_date = $start_date !== null ? $start_date : $currentDate;
    $new_time = $start_time !== null ? $start_time : $currentTime;

    if (!$new_date || !$new_time) {
      return new WP_REST_Response(['status'=>'error','message'=>'Missing date/time'], 400);
    }

    $service_id = (int)($booking['service_id'] ?? 0);
    $agent_id = isset($updates['agent_id']) ? (int)$updates['agent_id'] : (int)($booking['agent_id'] ?? 0);
    if ($agent_id <= 0 || $service_id <= 0) {
      return new WP_REST_Response(['status'=>'error','message'=>'Missing agent/service'], 400);
    }

    if (class_exists('BP_ScheduleHelper') && method_exists('BP_ScheduleHelper','is_date_closed')) {
      if (BP_ScheduleHelper::is_date_closed($new_date)) {
        return new WP_REST_Response(['status'=>'error','message'=>'Selected date is closed'], 400);
      }
    }

    $durationCol = in_array('duration_minutes', $sCols, true) ? 'duration_minutes' : (in_array('duration', $sCols, true) ? 'duration' : null);
    $bufferBeforeCol = in_array('buffer_before_minutes', $sCols, true) ? 'buffer_before_minutes' : (in_array('buffer_before', $sCols, true) ? 'buffer_before' : null);
    $bufferAfterCol = in_array('buffer_after_minutes', $sCols, true) ? 'buffer_after_minutes' : (in_array('buffer_after', $sCols, true) ? 'buffer_after' : null);

    $svc = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t_srv} WHERE id=%d LIMIT 1", $service_id), ARRAY_A);
    $duration = $durationCol ? (int)($svc[$durationCol] ?? 30) : 30;
    $bf = $bufferBeforeCol ? (int)($svc[$bufferBeforeCol] ?? 0) : 0;
    $ba = $bufferAfterCol ? (int)($svc[$bufferAfterCol] ?? 0) : 0;
    $occupied = max(5, $duration + $bf + $ba);

    $startMin = bp_minutes($new_time);
    $endMin = $startMin + $occupied;
    $end_time = bp_hhmm($endMin);

    if (class_exists('BP_ScheduleHelper') && method_exists('BP_ScheduleHelper','is_within_schedule')) {
      if (!BP_ScheduleHelper::is_within_schedule($agent_id, $new_date, $new_time, $occupied)) {
        return new WP_REST_Response(['status'=>'error','message'=>'Outside agent schedule'], 400);
      }
    }

    if (bp_has_conflict($agent_id, $new_date, $new_time, $end_time, $id)) {
      return new WP_REST_Response(['status'=>'error','message'=>'Time conflicts with another booking'], 409);
    }

    if ($has_start_date) {
      $updates['start_date'] = $new_date;
      $formats[] = '%s';
    }
    if ($has_start_time) {
      $updates['start_time'] = $new_time . ':00';
      $formats[] = '%s';
    }
    if ($has_start_datetime) {
      $start_dt = $new_date . ' ' . $new_time . ':00';
      $updates['start_datetime'] = $start_dt;
      $formats[] = '%s';
      if ($has_end_datetime) {
        $end_dt = date('Y-m-d H:i:s', strtotime($start_dt . ' +' . $occupied . ' minutes'));
        $updates['end_datetime'] = $end_dt;
        $formats[] = '%s';
      }
    }
  }

  if (empty($updates)) {
    return new WP_REST_Response(['status'=>'success','data'=>['id'=>$id,'updated'=>false]], 200);
  }

  $ok = $wpdb->update($t_book, $updates, ['id'=>$id], $formats, ['%d']);
  if ($ok === false) {
    return new WP_REST_Response(['status'=>'error','message'=>'Update failed'], 500);
  }

  return bp_rest_admin_booking_get($req);
}

function bp_minutes($hhmm){
  if (!$hhmm) return 0;
  $p = explode(':', $hhmm);
  $h = intval($p[0] ?? 0);
  $m = intval($p[1] ?? 0);
  return $h*60 + $m;
}

function bp_hhmm($minutes){
  $h = floor($minutes/60);
  $m = $minutes % 60;
  return str_pad((string)$h,2,'0',STR_PAD_LEFT) . ':' . str_pad((string)$m,2,'0',STR_PAD_LEFT);
}

/**
 * Check overlap conflicts for agent on date for a time block.
 * Excludes booking_id if provided.
 */
function bp_has_conflict($agent_id, $date, $start_hhmm, $end_hhmm, $exclude_booking_id = 0){
  global $wpdb;

  $tB = $wpdb->prefix . 'bp_bookings';
  $tS = $wpdb->prefix . 'bp_services';

  $bCols = $wpdb->get_col("SHOW COLUMNS FROM {$tB}") ?: [];
  $sCols = $wpdb->get_col("SHOW COLUMNS FROM {$tS}") ?: [];

  $has_start_date = in_array('start_date', $bCols, true);
  $has_start_time = in_array('start_time', $bCols, true);
  $has_start_datetime = in_array('start_datetime', $bCols, true);

  $durationCol = in_array('duration_minutes', $sCols, true) ? 'duration_minutes' : (in_array('duration', $sCols, true) ? 'duration' : null);
  $bufferBeforeCol = in_array('buffer_before_minutes', $sCols, true) ? 'buffer_before_minutes' : (in_array('buffer_before', $sCols, true) ? 'buffer_before' : null);
  $bufferAfterCol = in_array('buffer_after_minutes', $sCols, true) ? 'buffer_after_minutes' : (in_array('buffer_after', $sCols, true) ? 'buffer_after' : null);

  $startMin = bp_minutes($start_hhmm);
  $endMin   = bp_minutes($end_hhmm);

  if ($has_start_date && $has_start_time) {
    $rows = $wpdb->get_results($wpdb->prepare("\
      SELECT b.id, b.start_time, b.service_id,\
             COALESCE(s.{$durationCol},30) AS duration,\
             COALESCE(s.{$bufferBeforeCol},0) AS buffer_before,\
             COALESCE(s.{$bufferAfterCol},0) AS buffer_after\
      FROM {$tB} b\
      LEFT JOIN {$tS} s ON s.id = b.service_id\
      WHERE b.agent_id=%d AND b.start_date=%s\
        AND (b.status IS NULL OR b.status <> 'cancelled')\
        AND b.id <> %d\
    ", (int)$agent_id, $date, (int)$exclude_booking_id), ARRAY_A) ?: [];

    foreach($rows as $r){
      $sMin = bp_minutes(substr($r['start_time'],0,5));
      $occupied = max(5, intval($r['duration']) + intval($r['buffer_before']) + intval($r['buffer_after']));
      $eMin = $sMin + $occupied;

      $overlap = !($eMin <= $startMin || $sMin >= $endMin);
      if ($overlap) return true;
    }

    return false;
  }

  if ($has_start_datetime) {
    $rows = $wpdb->get_results($wpdb->prepare("\
      SELECT b.id, b.start_datetime, b.service_id,\
             COALESCE(s.{$durationCol},30) AS duration,\
             COALESCE(s.{$bufferBeforeCol},0) AS buffer_before,\
             COALESCE(s.{$bufferAfterCol},0) AS buffer_after\
      FROM {$tB} b\
      LEFT JOIN {$tS} s ON s.id = b.service_id\
      WHERE b.agent_id=%d AND DATE(b.start_datetime)=%s\
        AND (b.status IS NULL OR b.status <> 'cancelled')\
        AND b.id <> %d\
    ", (int)$agent_id, $date, (int)$exclude_booking_id), ARRAY_A) ?: [];

    foreach($rows as $r){
      $ts = strtotime($r['start_datetime']);
      if (!$ts) continue;
      $sMin = bp_minutes(date('H:i', $ts));
      $occupied = max(5, intval($r['duration']) + intval($r['buffer_before']) + intval($r['buffer_after']));
      $eMin = $sMin + $occupied;

      $overlap = !($eMin <= $startMin || $sMin >= $endMin);
      if ($overlap) return true;
    }
  }

  return false;
}
