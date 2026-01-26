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

  // Delete booking
  register_rest_route('bp/v1', '/admin/bookings/(?P<id>\d+)', [
    'methods'  => 'DELETE',
    'callback' => 'bp_rest_admin_booking_delete',
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

  $tBookings  = $wpdb->prefix . 'bp_bookings';
  $tCustomers = $wpdb->prefix . 'bp_customers';
  $tServices  = $wpdb->prefix . 'bp_services';
  $tAgents    = $wpdb->prefix . 'bp_agents';
  $tFields    = $wpdb->prefix . 'bp_form_fields';

  $table_exists = function($table) use ($wpdb){
    $like = $wpdb->esc_like($table);
    $found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $like));
    return !empty($found);
  };

  $read_json = function($val){
    if (!$val || !is_string($val)) return null;
    $trim = trim($val);
    if ($trim === '' || ($trim[0] !== '{' && $trim[0] !== '[')) return null;
    $decoded = json_decode($trim, true);
    return (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
  };

  $pick_first = function($row, array $keys){
    foreach($keys as $k){
      if (isset($row[$k]) && $row[$k] !== '' && $row[$k] !== null) return $row[$k];
    }
    return null;
  };

  $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tBookings} WHERE id=%d", $id), ARRAY_A);
  if (!$row) return new WP_REST_Response(['status'=>'error','message'=>'Booking not found'], 404);

  $booking = [
    'id' => (int)$row['id'],
    'status' => $row['status'] ?? ($row['booking_status'] ?? 'pending'),
    'start_datetime' => $pick_first($row, ['start_datetime','start_at','start_time','start']),
    'end_datetime'   => $pick_first($row, ['end_datetime','end_at','end_time','end']),
    'created_at'     => $pick_first($row, ['created_at','created','date_created']),
  ];

  $customer_id = (int) ($pick_first($row, ['customer_id','bp_customer_id','client_id']) ?? 0);
  $service_id  = (int) ($pick_first($row, ['service_id','bp_service_id']) ?? 0);
  $agent_id    = (int) ($pick_first($row, ['agent_id','bp_agent_id','staff_id']) ?? 0);

  $customer = [
    'id'    => $customer_id ?: null,
    'name'  => $pick_first($row, ['customer_name','name','full_name']),
    'email' => $pick_first($row, ['customer_email','email']),
    'phone' => $pick_first($row, ['customer_phone','phone','mobile']),
  ];

  if ($customer_id && $table_exists($tCustomers)) {
    $cRow = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tCustomers} WHERE id=%d", $customer_id), ARRAY_A);
    if ($cRow) {
      $first = $pick_first($cRow, ['first_name','firstname','fname']);
      $last  = $pick_first($cRow, ['last_name','lastname','lname']);
      $customer['name']  = $customer['name']  ?: trim(($first ?: '') . ' ' . ($last ?: '')) ?: ($pick_first($cRow, ['name','full_name']) ?: null);
      $customer['email'] = $customer['email'] ?: ($pick_first($cRow, ['email','customer_email']) ?: null);
      $customer['phone'] = $customer['phone'] ?: ($pick_first($cRow, ['phone','mobile']) ?: null);
    }
  }

  $service = [
    'id' => $service_id ?: null,
    'name' => $pick_first($row, ['service_name','service']),
    'duration_minutes' => null,
  ];
  $service_price = null;
  if ($service_id && $table_exists($tServices)) {
    $sRow = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tServices} WHERE id=%d", $service_id), ARRAY_A);
    if ($sRow) {
      $service['name'] = $service['name'] ?: ($pick_first($sRow, ['name','title','service_name']) ?: null);
      $service['duration_minutes'] = (int)($sRow['duration_minutes'] ?? 0);
      $service_price = $pick_first($sRow, ['price_cents','price','amount','price_amount','price_value']);
      if ($service_price !== null && array_key_exists('price_cents', $sRow)) {
        $service_price = ((float)$service_price) / 100;
      }
    }
  }

  $agent = [
    'id' => $agent_id ?: null,
    'name' => $pick_first($row, ['agent_name','agent','staff_name']),
  ];
  if ($agent_id && $table_exists($tAgents)) {
    $aRow = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tAgents} WHERE id=%d", $agent_id), ARRAY_A);
    if ($aRow) {
      $first = $pick_first($aRow, ['first_name','firstname','fname']);
      $last  = $pick_first($aRow, ['last_name','lastname','lname']);
      $agent['name'] = $agent['name'] ?: trim(($first ?: '') . ' ' . ($last ?: '')) ?: ($pick_first($aRow, ['name','full_name']) ?: null);
    }
  }

  $pricing = [
    'currency' => $pick_first($row, ['currency','currency_code','currency_symbol']),
    'subtotal' => $pick_first($row, ['subtotal','price_subtotal','amount_subtotal','subtotal_amount']),
    'discount_total' => $pick_first($row, ['discount_total','discount','discount_amount']),
    'tax_total' => $pick_first($row, ['tax_total','tax','tax_amount']),
    'total' => $pick_first($row, ['total','price_total','amount_total','grand_total','total_amount']),
    'promo_code' => $pick_first($row, ['promo_code','coupon','coupon_code']),
  ];
  if (($pricing['subtotal'] === null || $pricing['subtotal'] === '') && $service_price !== null) {
    $pricing['subtotal'] = $service_price;
  }
  if (($pricing['total'] === null || $pricing['total'] === '') && $service_price !== null) {
    $pricing['total'] = $service_price;
  }
  if (empty($pricing['currency']) && class_exists('BP_SettingsHelper')) {
    $pricing['currency'] = BP_SettingsHelper::get('currency', 'USD');
  }

  $json_columns = [];
  foreach($row as $col => $val){
    $decoded = $read_json($val);
    if ($decoded !== null) $json_columns[$col] = $decoded;
  }

  $form_answers = [];
  $order_items  = [];

  // Prefer field values table (per-booking, won't overwrite older bookings)
  $field_values = [];
  $t_field_values = $wpdb->prefix . 'bp_field_values';
  if (class_exists('BP_FieldValuesHelper') && $table_exists($t_field_values)) {
    // Only booking entity values to keep each booking unique
    $field_values = BP_FieldValuesHelper::get_for_entity('booking', (int)$booking['id']);
  }

  foreach ($field_values as $fv) {
    $key = sanitize_key($fv['field_key'] ?? '');
    if ($key === '') continue;
    $scope = sanitize_text_field($fv['scope'] ?? '');
    $val = $fv['value_long'] ?? '';
    $decoded = $read_json($val);
    $val = ($decoded !== null) ? $decoded : $val;

    if (!array_key_exists($key, $form_answers)) {
      $form_answers[$key] = $val;
    }
    if ($scope !== '') {
      $form_answers[$scope . '.' . $key] = $val;
    }
  }

  // Explicit JSON columns for booking/customer fields
  if (empty($form_answers)) {
    $booking_fields = $read_json($row['booking_fields_json'] ?? null);
    $customer_fields = $read_json($row['customer_fields_json'] ?? null);
    $custom_fields = $read_json($row['custom_fields_json'] ?? null);

    if (is_array($booking_fields)) $form_answers = $booking_fields;
    if (is_array($customer_fields)) $form_answers = array_merge($form_answers, $customer_fields);
    if (is_array($custom_fields)) $form_answers = array_merge($form_answers, $custom_fields);
  }

  // Order items: prefer explicit columns
  $explicit_items = $read_json($row['order_items_json'] ?? null)
    ?? $read_json($row['extras_json'] ?? null)
    ?? $read_json($row['items_json'] ?? null);
  if (is_array($explicit_items)) {
    $order_items = $explicit_items;
  }

  // Resolve extras (names/prices) if extras_json contains ids or partials
  $extras_items = [];
  $extras_raw = $read_json($row['extras_json'] ?? null);
  if (is_array($extras_raw) && !empty($extras_raw)) {
    $ids = [];
    foreach ($extras_raw as $ex) {
      if (is_numeric($ex)) {
        $ids[] = (int)$ex;
      } elseif (is_array($ex)) {
        $maybe_id = $ex['id'] ?? ($ex['extra_id'] ?? null);
        if (is_numeric($maybe_id)) $ids[] = (int)$maybe_id;
      }
    }

    $extras_map = [];
    if (!empty($ids)) {
      $ids = array_values(array_unique(array_filter($ids)));
      $t_extras = $wpdb->prefix . 'bp_service_extras';
      $in = implode(',', array_fill(0, count($ids), '%d'));
      $rows = $wpdb->get_results($wpdb->prepare("SELECT id, name, price FROM {$t_extras} WHERE id IN ({$in})", $ids), ARRAY_A) ?: [];
      foreach ($rows as $r) {
        $extras_map[(int)$r['id']] = $r;
      }
    }

    foreach ($extras_raw as $ex) {
      $id = null; $name = null; $price = null; $qty = 1;
      if (is_numeric($ex)) {
        $id = (int)$ex;
      } elseif (is_array($ex)) {
        $id = isset($ex['id']) ? (int)$ex['id'] : (isset($ex['extra_id']) ? (int)$ex['extra_id'] : null);
        $name = $ex['name'] ?? $ex['title'] ?? null;
        $price = $ex['price'] ?? $ex['amount'] ?? null;
        $qty = isset($ex['qty']) ? (int)$ex['qty'] : (isset($ex['quantity']) ? (int)$ex['quantity'] : 1);
      }

      if ($id && isset($extras_map[$id])) {
        $rowEx = $extras_map[$id];
        $name = $name ?: ($rowEx['name'] ?? null);
        $price = $price ?? ($rowEx['price'] ?? null);
      }

      if ($name !== null || $price !== null || $id !== null) {
        $extras_items[] = [
          'id' => $id,
          'name' => $name ?: ('Extra #' . ($id ?: '')),
          'price' => $price,
          'qty' => $qty > 0 ? $qty : 1,
          'type' => 'extra',
        ];
      }
    }
  }

  if (empty($order_items) && !empty($extras_items)) {
    $order_items = $extras_items;
  }

  $extras_total = 0.0;
  foreach ($extras_items as $ex) {
    $p = isset($ex['price']) ? (float)$ex['price'] : 0.0;
    $q = isset($ex['qty']) ? (int)$ex['qty'] : 1;
    if ($q < 1) $q = 1;
    $extras_total += ($p * $q);
  }

  if ($extras_total > 0) {
    $pricing['extras_total'] = $extras_total;
  }

  if (($pricing['subtotal'] === null || $pricing['subtotal'] === '') && $service_price !== null) {
    $pricing['subtotal'] = $service_price + $extras_total;
  }
  if (($pricing['total'] === null || $pricing['total'] === '') && $service_price !== null) {
    $pricing['total'] = $service_price + $extras_total;
  }

  // Heuristic fallback
  if (empty($form_answers) || empty($order_items)) {
    foreach($json_columns as $col => $decoded){
      if (empty($order_items) && is_array($decoded) && array_is_list($decoded) && count($decoded) > 0 && is_array($decoded[0])) {
        $first = $decoded[0];
        if (isset($first['name']) || isset($first['service_id']) || isset($first['price']) || isset($first['qty']) || isset($first['quantity'])) {
          $order_items = $decoded;
        }
      }

      if (empty($form_answers) && is_array($decoded) && !array_is_list($decoded)) {
        $form_answers = $decoded;
      }
    }
  }

  $field_defs = [];
  if ($table_exists($tFields)) {
    $defs = $wpdb->get_results("SELECT * FROM {$tFields} ORDER BY sort_order ASC, id ASC", ARRAY_A) ?: [];
    foreach($defs as $d){
      $field_defs[] = [
        'key'   => $d['field_key'] ?? ($d['slug'] ?? ($d['name'] ?? ('field_'.$d['id']))),
        'label' => $d['label'] ?? ($d['title'] ?? ($d['name'] ?? 'Field')),
        'type'  => $d['type'] ?? 'text',
        'scope' => $d['scope'] ?? ($d['context'] ?? ''),
        'enabled' => isset($d['is_enabled']) ? (bool)$d['is_enabled'] : (isset($d['enabled']) ? (bool)$d['enabled'] : true),
        'required'=> isset($d['is_required']) ? (bool)$d['is_required'] : (isset($d['required']) ? (bool)$d['required'] : false),
      ];
    }
  }

  return new WP_REST_Response([
    'status' => 'success',
    'data' => [
      'booking' => $booking,
      'customer' => $customer,
      'service' => $service,
      'agent' => $agent,
      'pricing' => $pricing,
      'order_items' => $order_items,
      'form_answers' => $form_answers,
      'form_fields' => $field_defs,
      'raw' => $row,
      'json_columns' => $json_columns,
    ]
  ], 200);
}

function bp_rest_admin_agents_list(WP_REST_Request $req) {
  global $wpdb;
  $t = $wpdb->prefix . 'bp_agents';

  $cols = $wpdb->get_col("SHOW COLUMNS FROM {$t}") ?: [];
  $has_name = in_array('name', $cols, true);
  $has_first = in_array('first_name', $cols, true);
  $has_last = in_array('last_name', $cols, true);
  $has_email = in_array('email', $cols, true);

  $select = ['id'];
  if ($has_name) $select[] = 'name';
  if ($has_first) $select[] = 'first_name';
  if ($has_last) $select[] = 'last_name';
  if ($has_email) $select[] = 'email';

  $order = $has_name
    ? 'name ASC'
    : (($has_first || $has_last) ? "first_name ASC, last_name ASC" : 'id ASC');

  $sql = "SELECT " . implode(', ', $select) . " FROM {$t} ORDER BY {$order}";
  $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];

  foreach ($rows as &$row) {
    if (empty($row['name'])) {
      $full = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
      if ($full !== '') $row['name'] = $full;
    }
  }

  return new WP_REST_Response(['status'=>'success','data'=>$rows], 200);
}


function bp_rest_admin_booking_patch(WP_REST_Request $req) {
  global $wpdb;

  $id = (int)$req['id'];
  if ($id <= 0) return new WP_REST_Response(['status'=>'error','message'=>'Invalid id'], 400);

  $t_book = $wpdb->prefix . 'bp_bookings';
  $t_srv  = $wpdb->prefix . 'bp_services';
  $t_cust = $wpdb->prefix . 'bp_customers';

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
  $cCols = $wpdb->get_col("SHOW COLUMNS FROM {$t_cust}") ?: [];
  $has_start_datetime = in_array('start_datetime', $bCols, true);
  $has_end_datetime = in_array('end_datetime', $bCols, true);
  $has_start_date = in_array('start_date', $bCols, true);
  $has_start_time = in_array('start_time', $bCols, true);
  $has_customer_name = in_array('customer_name', $bCols, true);
  $has_customer_email = in_array('customer_email', $bCols, true);
  $has_customer_phone = in_array('customer_phone', $bCols, true);

  // status change
  if (isset($body['status'])) {
    $status = sanitize_text_field($body['status']);
    if (!in_array($status, ['pending','confirmed','cancelled','completed'], true)) {
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
      if (BP_ScheduleHelper::is_date_closed($new_date, $agent_id)) {
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

function bp_rest_admin_booking_delete(WP_REST_Request $req) {
  global $wpdb;

  $id = (int)$req['id'];
  if ($id <= 0) return new WP_REST_Response(['status'=>'error','message'=>'Invalid id'], 400);

  $t_book = $wpdb->prefix . 'bp_bookings';
  $row = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$t_book} WHERE id=%d", $id), ARRAY_A);
  if (!$row) return new WP_REST_Response(['status'=>'error','message'=>'Booking not found'], 404);

  if (class_exists('BP_FieldValuesHelper')) {
    BP_FieldValuesHelper::delete_for_entity('booking', $id);
  }

  // customer fields (name/email/phone)
  $cust_name = array_key_exists('customer_name', $body) ? sanitize_text_field($body['customer_name']) : null;
  $cust_email = array_key_exists('customer_email', $body) ? sanitize_email($body['customer_email']) : null;
  $cust_phone = array_key_exists('customer_phone', $body) ? sanitize_text_field($body['customer_phone']) : null;

  if ($cust_name !== null && $has_customer_name) {
    $updates['customer_name'] = $cust_name;
    $formats[] = '%s';
  }
  if ($cust_email !== null && $has_customer_email) {
    $updates['customer_email'] = $cust_email;
    $formats[] = '%s';
  }
  if ($cust_phone !== null && $has_customer_phone) {
    $updates['customer_phone'] = $cust_phone;
    $formats[] = '%s';
  }

  // also update customer row if possible
  $customer_id = (int)($booking['customer_id'] ?? 0);
  if ($customer_id > 0 && !empty($cCols)) {
    $cu = [];
    $cf = [];
    $has_c_name = in_array('name', $cCols, true);
    $has_c_first = in_array('first_name', $cCols, true);
    $has_c_last = in_array('last_name', $cCols, true);
    $has_c_email = in_array('email', $cCols, true);
    $has_c_phone = in_array('phone', $cCols, true);

    if ($cust_name !== null) {
      if ($has_c_name) {
        $cu['name'] = $cust_name;
        $cf[] = '%s';
      } elseif ($has_c_first || $has_c_last) {
        $parts = preg_split('/\s+/', trim($cust_name), 2);
        if ($has_c_first) { $cu['first_name'] = $parts[0] ?? ''; $cf[] = '%s'; }
        if ($has_c_last) { $cu['last_name'] = $parts[1] ?? ''; $cf[] = '%s'; }
      }
    }
    if ($cust_email !== null && $has_c_email) {
      $cu['email'] = $cust_email;
      $cf[] = '%s';
    }
    if ($cust_phone !== null && $has_c_phone) {
      $cu['phone'] = $cust_phone;
      $cf[] = '%s';
    }
    if (!empty($cu)) {
      $wpdb->update($t_cust, $cu, ['id' => $customer_id], $cf, ['%d']);
    }
  }

  $ok = $wpdb->delete($t_book, ['id'=>$id], ['%d']);
  if ($ok === false) {
    return new WP_REST_Response(['status'=>'error','message'=>'Delete failed'], 500);
  }

  return new WP_REST_Response(['status'=>'success','data'=>['id'=>$id,'deleted'=>true]], 200);
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
