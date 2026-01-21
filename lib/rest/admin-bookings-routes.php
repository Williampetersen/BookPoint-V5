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
  $bCols = $wpdb->get_col("SHOW COLUMNS FROM {$t_book}") ?: [];
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

  // agent_id
  if (isset($body['agent_id'])) {
    $agent_id = (int)$body['agent_id'];
    if ($agent_id < 0) $agent_id = 0;
    $updates['agent_id'] = $agent_id;
    $formats[] = '%d';
  }

  // manual reschedule using start_date + start_time
  // expects: start_date: YYYY-MM-DD, start_time: HH:MM
  if (isset($body['start_date']) || isset($body['start_time'])) {
    $start_date = isset($body['start_date']) ? sanitize_text_field($body['start_date']) : ($booking['start_date'] ?? '');
    $start_time = isset($body['start_time']) ? sanitize_text_field($body['start_time']) : ($booking['start_time'] ?? '');

    $start_date = substr($start_date, 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
      return new WP_REST_Response(['status'=>'error','message'=>'Invalid start_date'], 400);
    }

    // Normalize time to HH:MM:SS
    if (preg_match('/^\d{2}:\d{2}$/', $start_time)) $start_time .= ':00';
    if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $start_time)) {
      return new WP_REST_Response(['status'=>'error','message'=>'Invalid start_time'], 400);
    }

    // Conflict validation same as reschedule endpoint:
    $service_id = (int)($booking['service_id'] ?? 0);
    $agent_id = isset($updates['agent_id']) ? (int)$updates['agent_id'] : (int)($booking['agent_id'] ?? 0);

    $rules = BP_ScheduleHelper::get_service_rules($service_id);
    $dur = (int)$rules['duration'];
    $occupied = (int)$rules['occupied_min'];
    $capacity = (int)$rules['capacity'];

    // Validate within working hours + breaks
    if (!BP_ScheduleHelper::is_within_schedule($agent_id, $start_date, $start_time, $occupied)) {
      return new WP_REST_Response(['status'=>'error','message'=>'Outside working hours or in break'], 409);
    }

    $start_iso = $start_date . 'T' . substr($start_time,0,8);
    $start_dt = strtotime($start_iso);
    if (!$start_dt) return new WP_REST_Response(['status'=>'error','message'=>'Invalid date/time'], 400);

    $end_dt = $start_dt + ($occupied * 60);

    $start_iso_full = gmdate('Y-m-d\TH:i:s', $start_dt);
    $end_iso_full   = gmdate('Y-m-d\TH:i:s', $end_dt);

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

      $conflict = $wpdb->get_var($wpdb->prepare($sql, $id, $agent_id, $end_iso_full, $start_iso_full));
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

      $count = (int)$wpdb->get_var($wpdb->prepare($sql, $id, $agent_id, $end_iso_full, $start_iso_full));
      if ($count >= $capacity) {
        return new WP_REST_Response(['status'=>'error','message'=>'Time slot is not available (capacity)'], 409);
      }
    }

    $updates['start_date'] = $start_date;
    $formats[] = '%s';
    $updates['start_time'] = $start_time;
    $formats[] = '%s';
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
