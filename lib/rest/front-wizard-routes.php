<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {
  register_rest_route('bp/v1', '/front/locations', [
    'methods'  => 'GET',
    'callback' => 'bp_rest_front_locations',
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('bp/v1', '/front/categories', [
    'methods'  => 'GET',
    'callback' => 'bp_rest_front_categories',
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('bp/v1', '/front/services', [
    'methods'  => 'POST',
    'callback' => 'bp_rest_front_services',
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('bp/v1', '/front/extras', [
    'methods'  => 'POST',
    'callback' => 'bp_rest_front_extras',
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('bp/v1', '/front/agents', [
    'methods'  => 'POST',
    'callback' => 'bp_rest_front_agents',
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('bp/v1', '/front/slots', [
    'methods'  => 'POST',
    'callback' => 'bp_rest_front_slots',
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('bp/v1', '/front/form-fields', [
    'methods'  => 'GET',
    'callback' => 'bp_rest_front_form_fields',
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('bp/v1', '/front/bookings', [
    'methods'  => 'POST',
    'callback' => 'bp_rest_front_bookings',
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('bp/v1', '/front/availability', [
    'methods'  => 'GET',
    'callback' => 'bp_rest_front_availability_month',
    'permission_callback' => '__return_true',
  ]);
});

function bp_rest_front_locations() {
  if (class_exists('BP_Locations_Migrations_Helper')) {
    BP_Locations_Migrations_Helper::ensure_tables();
  }

  global $wpdb;
  $t = $wpdb->prefix . 'bp_locations';

  $exists = (string)$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t)) === $t;
  if (!$exists) {
    return new WP_REST_Response(['status' => 'success', 'data' => []], 200);
  }

  $rows = $wpdb->get_results("SELECT id,name,address,status,image_id FROM {$t} WHERE status='active' ORDER BY id DESC", ARRAY_A) ?: [];

  foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['image_id'] = (int)($r['image_id'] ?? 0);
    if (function_exists('bp_locations_img_url')) {
      $r['image_url'] = bp_locations_img_url($r['image_id'], 'medium');
    } else {
      $r['image_url'] = $r['image_id'] ? (wp_get_attachment_image_url($r['image_id'], 'medium') ?: '') : '';
    }
  }

  return new WP_REST_Response(['status' => 'success', 'data' => $rows], 200);
}

function bp_rest_front_categories() {
  if (function_exists('bp_rest_public_categories')) {
    return bp_rest_public_categories();
  }
  return new WP_REST_Response(['status' => 'success', 'data' => []], 200);
}

function bp_front_services_format_rows(array $rows): array {
  global $wpdb;
  $t = $wpdb->prefix . 'bp_services';

  $cols = $wpdb->get_col("SHOW COLUMNS FROM {$t}") ?: [];
  $has_price_cents = in_array('price_cents', $cols, true);
  $has_duration_minutes = in_array('duration_minutes', $cols, true);
  $has_buffer_before_minutes = in_array('buffer_before_minutes', $cols, true);
  $has_buffer_after_minutes = in_array('buffer_after_minutes', $cols, true);
  $has_buffer_before = in_array('buffer_before', $cols, true);
  $has_buffer_after = in_array('buffer_after', $cols, true);

  foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['duration'] = $has_duration_minutes
      ? (int)($r['duration_minutes'] ?? 30)
      : (int)($r['duration'] ?? 30);

    $r['price'] = $has_price_cents
      ? ((int)($r['price_cents'] ?? 0)) / 100
      : (float)($r['price'] ?? 0);

    $r['image_id'] = (int)($r['image_id'] ?? 0);
    $r['image_url'] = function_exists('bp_public_img_url')
      ? bp_public_img_url($r['image_id'], 'medium')
      : ($r['image_id'] ? (wp_get_attachment_image_url($r['image_id'], 'medium') ?: '') : '');
    $r['sort_order'] = (int)($r['sort_order'] ?? 0);
    $r['buffer_before'] = $has_buffer_before_minutes
      ? (int)($r['buffer_before_minutes'] ?? 0)
      : (int)($r['buffer_before'] ?? 0);
    $r['buffer_after'] = $has_buffer_after_minutes
      ? (int)($r['buffer_after_minutes'] ?? 0)
      : (int)($r['buffer_after'] ?? 0);
    $r['capacity'] = (int)($r['capacity'] ?? 1);
  }

  return $rows;
}

function bp_rest_front_services(WP_REST_Request $req) {
  global $wpdb;
  $t = $wpdb->prefix . 'bp_services';
  $t_rel = $wpdb->prefix . 'bp_service_categories';

  $p = $req->get_json_params();
  if (!is_array($p)) $p = [];
  $category_ids = $p['category_ids'] ?? [];
  if (!is_array($category_ids)) $category_ids = [];
  $category_ids = array_values(array_filter(array_map('intval', $category_ids)));

  $cols = $wpdb->get_col("SHOW COLUMNS FROM {$t}") ?: [];
  $has_is_active = in_array('is_active', $cols, true);

  $rows = [];
  $rel_exists = (string)$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t_rel)) === $t_rel;

  if ($category_ids && $rel_exists) {
    $placeholders = implode(',', array_fill(0, count($category_ids), '%d'));
    $where = "WHERE sc.category_id IN ({$placeholders})";
    if ($has_is_active) $where .= ' AND s.is_active=1';

    $rows = $wpdb->get_results($wpdb->prepare("
      SELECT s.*
      FROM {$t} s
      INNER JOIN {$t_rel} sc ON sc.service_id=s.id
      {$where}
      ORDER BY s.id DESC
    ", $category_ids), ARRAY_A) ?: [];
  } else {
    $where = $has_is_active ? 'WHERE is_active=1' : '';
    $rows = $wpdb->get_results("SELECT * FROM {$t} {$where} ORDER BY id DESC", ARRAY_A) ?: [];
  }

  $rows = bp_front_services_format_rows($rows);

  return new WP_REST_Response(['status' => 'success', 'data' => $rows], 200);
}

function bp_rest_front_extras(WP_REST_Request $req) {
  $p = $req->get_json_params();
  if (!is_array($p)) $p = [];
  $service_id = (int)($p['service_id'] ?? 0);
  if ($service_id <= 0) {
    return new WP_REST_Response(['status' => 'success', 'data' => []], 200);
  }

  if (function_exists('bp_rest_public_extras')) {
    $r = new WP_REST_Request('GET', '/bp/v1/public/extras');
    $r->set_param('service_id', $service_id);
    return bp_rest_public_extras($r);
  }

  return new WP_REST_Response(['status' => 'success', 'data' => []], 200);
}

function bp_rest_front_agents(WP_REST_Request $req) {
  $p = $req->get_json_params();
  if (!is_array($p)) $p = [];
  $service_id = (int)($p['service_id'] ?? 0);
  if ($service_id <= 0) {
    return new WP_REST_Response(['status' => 'success', 'data' => []], 200);
  }

  if (function_exists('bp_rest_public_agents')) {
    $r = new WP_REST_Request('GET', '/bp/v1/public/agents');
    $r->set_param('service_id', $service_id);
    return bp_rest_public_agents($r);
  }

  return new WP_REST_Response(['status' => 'success', 'data' => []], 200);
}

function bp_rest_front_slots(WP_REST_Request $req) {
  $p = $req->get_json_params();
  if (!is_array($p)) $p = [];
  $service_id = (int)($p['service_id'] ?? 0);
  $agent_id = (int)($p['agent_id'] ?? 0);
  $date = sanitize_text_field($p['date'] ?? '');

  if (function_exists('bp_rest_public_availability_slots')) {
    $r = new WP_REST_Request('GET', '/bp/v1/public/availability-slots');
    $r->set_param('service_id', $service_id);
    $r->set_param('agent_id', $agent_id);
    $r->set_param('date', $date);
    return bp_rest_public_availability_slots($r);
  }

  return new WP_REST_Response(['status' => 'success', 'data' => []], 200);
}

function bp_rest_front_form_fields() {
  // Call the public route by simulating GET /public/form-fields.
  $route = rest_do_request(new WP_REST_Request('GET', '/bp/v1/public/form-fields'));
  if ($route instanceof WP_REST_Response) {
    return $route;
  }

  return new WP_REST_Response(['status' => 'success', 'data' => []], 200);
}

function bp_rest_front_bookings(WP_REST_Request $req) {
  if (function_exists('bp_public_create_booking')) {
    return bp_public_create_booking($req);
  }
  return new WP_REST_Response(['status' => 'error', 'message' => 'Booking handler missing'], 500);
}

function bp_rest_front_availability_month(WP_REST_Request $req) {
  $service_id = (int)($req->get_param('service_id') ?? 0);
  $agent_id   = (int)($req->get_param('agent_id') ?? 0);
  $month      = sanitize_text_field($req->get_param('month') ?? '');
  $location_id = (int)($req->get_param('location_id') ?? 0);

  if ($service_id <= 0 || $agent_id <= 0) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'service_id and agent_id are required'], 400);
  }
  if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'Invalid month'], 400);
  }

  $cache_key = 'bp_front_avail_' . md5($month . '|' . $service_id . '|' . $agent_id . '|' . $location_id);
  $cached = get_transient($cache_key);
  if (is_array($cached)) {
    return new WP_REST_Response(['status' => 'success', 'data' => $cached], 200);
  }

  if (!function_exists('bp_rest_public_availability_slots')) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'Availability handler missing'], 500);
  }

  $start_ts = strtotime($month . '-01 00:00:00');
  if ($start_ts === false) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'Invalid month'], 400);
  }
  $end_ts = strtotime(date('Y-m-t', $start_ts) . ' 00:00:00');

  $max_days = (int)apply_filters('bp_public_max_booking_days', 120);
  $today_ts = strtotime(date('Y-m-d') . ' 00:00:00');
  $max_ts = strtotime('+' . $max_days . ' days', $today_ts);

  $data = [];
  for ($ts = $start_ts; $ts <= $end_ts; $ts = strtotime('+1 day', $ts)) {
    if ($ts < $today_ts || $ts > $max_ts) {
      $data[date('Y-m-d', $ts)] = 0;
      continue;
    }
    $date = date('Y-m-d', $ts);
    $r = new WP_REST_Request('GET', '/bp/v1/public/availability-slots');
    $r->set_param('service_id', $service_id);
    $r->set_param('agent_id', $agent_id);
    $r->set_param('date', $date);
    $resp = bp_rest_public_availability_slots($r);
    if ($resp instanceof WP_REST_Response) {
      $payload = $resp->get_data();
      $slots = $payload['data'] ?? [];
      $data[$date] = is_array($slots) ? count($slots) : 0;
    } else {
      $data[$date] = 0;
    }
  }

  set_transient($cache_key, $data, 60); // 60s cache
  return new WP_REST_Response(['status' => 'success', 'data' => $data], 200);
}
