<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function(){
  register_rest_route('bp/v1', '/public/bookings', [
    'methods' => 'POST',
    'callback' => 'bp_public_create_booking',
    'permission_callback' => '__return_true',
  ]);
});

if (!function_exists('bp_public_create_booking')) {
function bp_public_create_booking(WP_REST_Request $req){
  $p = $req->get_json_params();
  if (!is_array($p)) $p = [];

  $allowed_methods = ['cash', 'free', 'woocommerce', 'stripe', 'paypal'];
  $default_method = class_exists('BP_SettingsHelper')
    ? (string)BP_SettingsHelper::get('payments_default_method', 'cash')
    : 'cash';
  if (!in_array($default_method, $allowed_methods, true)) $default_method = 'cash';

  $payment_method = sanitize_key($p['payment_method'] ?? $default_method);
  if (!in_array($payment_method, $allowed_methods, true)) $payment_method = $default_method;

  if (!in_array($payment_method, ['cash', 'free'], true)) {
    return new WP_Error('method_requires_checkout', 'This payment method requires checkout flow.', ['status' => 400]);
  }

  $overrides = [
    'status' => 'confirmed',
    'payment_method' => $payment_method,
    'payment_status' => $payment_method === 'free' ? 'paid' : 'unpaid',
  ];

  $result = bp_insert_booking_from_payload($p, $overrides);
  if (is_wp_error($result)) return $result;

  return new WP_REST_Response(['status' => 'success', 'data' => [
    'booking_id' => (int)$result['booking_id'],
    'manage_url' => $result['manage_url'],
  ]], 200);
}
}

if (!function_exists('bp_insert_booking_from_payload')) {
function bp_insert_booking_from_payload(array $p, array $overrides = []) {
  global $wpdb;

  $service_id = (int)($p['service_id'] ?? 0);
  $agent_id = (int)($p['agent_id'] ?? 0);
  $date = substr(sanitize_text_field($p['date'] ?? ''), 0, 10);
  $start_time = substr(sanitize_text_field($p['start_time'] ?? ''), 0, 5);

  $customer_fields = $p['customer_fields'] ?? [];
  if (!is_array($customer_fields)) $customer_fields = [];
  $booking_fields = $p['booking_fields'] ?? [];
  if (!is_array($booking_fields)) $booking_fields = [];

  $extras = $p['extras'] ?? [];
  if (!is_array($extras)) $extras = [];
  $promo_code = strtoupper(sanitize_text_field($p['promo_code'] ?? ''));

  $field_values = $p['field_values'] ?? [];
  if (!is_array($field_values)) $field_values = [];

  if (!$field_values && ($customer_fields || $booking_fields)) {
    foreach ($customer_fields as $k => $v) {
      $field_values['customer.' . $k] = $v;
    }
    foreach ($booking_fields as $k => $v) {
      $field_values['booking.' . $k] = $v;
    }
  }

  $t_fields = $wpdb->prefix . 'bp_form_fields';
  $fields = $wpdb->get_results("\
    SELECT id, field_key, name_key, label, scope, type, is_required, required
    FROM {$t_fields}
    WHERE is_enabled=1 AND show_in_wizard=1
    ORDER BY scope ASC, sort_order ASC
  ", ARRAY_A) ?: [];

  foreach ($fields as $f) {
    $is_required = (int)($f['is_required'] ?? $f['required'] ?? 0);
    if ($is_required !== 1) continue;
    $field_key = $f['field_key'] ?: ($f['name_key'] ?? '');
    $k = $f['scope'] . '.' . $field_key;
    $v = $field_values[$k] ?? null;
    $empty = ($v === null || $v === '' || (is_array($v) && empty($v)));
    if ($empty) {
      $label = !empty($f['label']) ? $f['label'] : $f['field_key'];
      return new WP_Error('missing_field', $label . ' is required', ['status' => 400]);
    }
  }

  if (!$customer_fields) {
    foreach ($field_values as $k => $v) {
      if (strpos($k, 'customer.') === 0) {
        $customer_fields[substr($k, 9)] = $v;
      }
    }
  }
  if (!$booking_fields) {
    foreach ($field_values as $k => $v) {
      if (strpos($k, 'booking.') === 0) {
        $booking_fields[substr($k, 8)] = $v;
      }
    }
  }

  $first_name = sanitize_text_field($customer_fields['first_name'] ?? ($p['first_name'] ?? ''));
  $last_name  = sanitize_text_field($customer_fields['last_name'] ?? ($p['last_name'] ?? ''));
  $email      = sanitize_email($customer_fields['email'] ?? ($p['email'] ?? ''));
  $phone      = sanitize_text_field($customer_fields['phone'] ?? ($p['phone'] ?? ''));
  $notes      = wp_kses_post($booking_fields['notes'] ?? ($p['notes'] ?? ''));

  if ($service_id <= 0 || $agent_id <= 0) {
    return new WP_Error('missing_service_agent', 'Missing service/agent', ['status' => 400]);
  }
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    return new WP_Error('invalid_date', 'Invalid date', ['status' => 400]);
  }
  if (!preg_match('/^\d{2}:\d{2}$/', $start_time)) {
    return new WP_Error('invalid_time', 'Invalid time', ['status' => 400]);
  }
  if (!$email) {
    return new WP_Error('email_required', 'Email required', ['status' => 400]);
  }

  if (class_exists('BP_ScheduleHelper') && BP_ScheduleHelper::is_date_closed($date, $agent_id)) {
    return new WP_Error('date_closed', 'Date is closed', ['status' => 400]);
  }

  $service = BP_ServiceModel::find($service_id);
  if (!$service) {
    return new WP_Error('service_not_found', 'Service not found', ['status' => 404]);
  }

  $duration = (int)($service['duration_minutes'] ?? 0);
  if ($duration <= 0) $duration = (int)($service['duration'] ?? 30);

  $buf_before = (int)($service['buffer_before_minutes'] ?? 0);
  if ($buf_before <= 0) $buf_before = (int)($service['buffer_before'] ?? 0);

  $buf_after = (int)($service['buffer_after_minutes'] ?? 0);
  if ($buf_after <= 0) $buf_after = (int)($service['buffer_after'] ?? 0);

  $capacity = (int)($service['capacity'] ?? 1);
  if ($capacity <= 0) $capacity = 1;

  $occupied = max(5, $duration + $buf_before + $buf_after);
  $start_dt = $date . ' ' . $start_time . ':00';
  $start_ts = strtotime($start_dt);
  if (!$start_ts) {
    return new WP_Error('invalid_datetime', 'Invalid date/time', ['status' => 400]);
  }
  $end_ts = $start_ts + ($duration * 60);
  $end_dt = date('Y-m-d H:i:s', $end_ts);

  $start_dt_adj = date('Y-m-d H:i:s', $start_ts - ($buf_before * 60));
  $end_dt_adj = date('Y-m-d H:i:s', $start_ts + ($duration * 60) + ($buf_after * 60));

  if (class_exists('BP_ScheduleHelper') && method_exists('BP_ScheduleHelper','is_within_schedule')) {
    if (!BP_ScheduleHelper::is_within_schedule($agent_id, $date, $start_time, $occupied)) {
      return new WP_Error('outside_schedule', 'Outside schedule', ['status' => 400]);
    }
  }

  if (!BP_AvailabilityHelper::is_slot_available($service_id, $start_dt_adj, $end_dt_adj, $capacity, $agent_id)) {
    return new WP_Error('time_conflict', 'Time conflict', ['status' => 409]);
  }

  $existing = BP_CustomerModel::find_by_email($email);
  $customer_fields_json = $customer_fields ? wp_json_encode($customer_fields) : null;
  $booking_fields_json = $booking_fields ? wp_json_encode($booking_fields) : null;

  if ($existing) {
    $customer_id = (int)$existing['id'];
    $wpdb->update(BP_CustomerModel::table(), [
      'first_name' => $first_name,
      'last_name' => $last_name,
      'phone' => $phone,
      'custom_fields_json' => $customer_fields_json,
      'updated_at' => current_time('mysql'),
    ], ['id' => $customer_id], ['%s','%s','%s','%s','%s'], ['%d']);
  } else {
    $customer_id = BP_CustomerModel::create([
      'first_name' => $first_name,
      'last_name'  => $last_name,
      'email'      => $email,
      'phone'      => $phone,
      'custom_fields_json' => $customer_fields_json,
      'wp_user_id' => is_user_logged_in() ? get_current_user_id() : null,
    ]);
  }

  $payment_method = $overrides['payment_method'] ?? sanitize_key($p['payment_method'] ?? 'cash');
  $payment_status = $overrides['payment_status'] ?? sanitize_key($p['payment_status'] ?? 'unpaid');
  $payment_provider_ref = $overrides['payment_provider_ref'] ?? null;

  $currency = sanitize_text_field($p['currency'] ?? ($service['currency'] ?? 'USD'));
  if (!preg_match('/^[A-Z]{3}$/', $currency)) {
    $currency = 'USD';
  }

  $total_price = $p['total_price'] ?? ($p['total'] ?? ($p['amount_total'] ?? null));
  $total_price = $total_price !== null ? (float)$total_price : 0.0;
  $payment_amount = isset($overrides['payment_amount']) ? (float)$overrides['payment_amount'] : $total_price;
  $payment_currency = $overrides['payment_currency'] ?? $currency;

  $booking_id = BP_BookingModel::create([
    'service_id'     => $service_id,
    'customer_id'    => $customer_id,
    'agent_id'       => $agent_id,
    'start_datetime' => $start_dt,
    'end_datetime'   => $end_dt,
    'status'         => $overrides['status'] ?? null,
    'notes'          => $notes ?: null,
    'payment_method' => $payment_method,
    'payment_status' => $payment_status,
    'payment_provider_ref' => $payment_provider_ref,
    'payment_amount' => $payment_amount,
    'payment_currency' => $payment_currency,
    'currency'       => $currency,
    'total_price'    => $total_price,
    'customer_fields_json' => $customer_fields_json,
    'booking_fields_json' => $booking_fields_json,
    'custom_fields_json' => $booking_fields_json,
  ]);

  if ($booking_id <= 0) {
    return new WP_Error('insert_failed', 'Insert failed', ['status' => 500]);
  }

  $t_bookings = $wpdb->prefix . 'bp_bookings';
  $extras_json = $extras ? wp_json_encode($extras) : null;
  $discount_total = isset($p['discount_total']) ? (float)$p['discount_total'] : null;

  $update = [];
  $formats = [];
  if ($extras_json !== null) { $update['extras_json'] = $extras_json; $formats[] = '%s'; }
  if ($promo_code !== '') { $update['promo_code'] = $promo_code; $formats[] = '%s'; }
  if ($discount_total !== null) { $update['discount_total'] = $discount_total; $formats[] = '%f'; }
  if (!empty($update)) {
    $wpdb->update($t_bookings, $update, ['id' => $booking_id], $formats, ['%d']);
  }

  if (class_exists('BP_FieldValuesHelper') && $fields) {
    foreach ($fields as $f) {
      $scope = $f['scope'];
      $field_key = $f['field_key'];
      $key = $scope . '.' . $field_key;

      if (!array_key_exists($key, $field_values)) continue;

      $raw = $field_values[$key];
      $type = $f['type'];

      if ($type === 'email') $raw = sanitize_email($raw);
      elseif ($type === 'tel') $raw = sanitize_text_field($raw);
      elseif ($type === 'number') $raw = is_numeric($raw) ? (string)$raw : '';
      elseif ($type === 'checkbox') $raw = !empty($raw) ? '1' : '0';
      elseif ($type === 'textarea') $raw = sanitize_textarea_field($raw);
      else $raw = sanitize_text_field($raw);

      BP_FieldValuesHelper::upsert('booking', $booking_id, (int)$f['id'], $field_key, $scope, $raw);
    }
  }

  $row = BP_BookingModel::find($booking_id);
  $manage_key = $row['manage_key'] ?? '';
  $manage_url = add_query_arg([
    'bp_manage_booking' => 1,
    'key' => $manage_key,
  ], home_url('/'));

  return [
    'booking_id' => $booking_id,
    'manage_url' => $manage_url,
  ];
}
}
