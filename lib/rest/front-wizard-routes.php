<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {
  register_rest_route('pointly-booking/v1', '/front/locations', [
    'methods'  => 'GET',
    'callback' => 'pointlybooking_rest_front_locations',
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('pointly-booking/v1', '/front/categories', [
    'methods'  => 'GET',
    'callback' => 'pointlybooking_rest_front_categories',
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('pointly-booking/v1', '/front/services', [
    'methods'  => 'POST',
    'callback' => 'pointlybooking_rest_front_services',
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('pointly-booking/v1', '/front/extras', [
    'methods'  => 'POST',
    'callback' => 'pointlybooking_rest_front_extras',
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('pointly-booking/v1', '/front/agents', [
    'methods'  => 'POST',
    'callback' => 'pointlybooking_rest_front_agents',
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('pointly-booking/v1', '/front/slots', [
    'methods'  => 'POST',
    'callback' => 'pointlybooking_rest_front_slots',
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('pointly-booking/v1', '/front/form-fields', [
    'methods'  => 'GET',
    'callback' => 'pointlybooking_rest_front_form_fields',
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('pointly-booking/v1', '/front/form-fields/active', [
    'methods'  => 'GET',
    'callback' => 'pointlybooking_rest_front_form_fields_active',
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('pointly-booking/v1', '/front/bookings', [
    'methods'  => 'POST',
    'callback' => 'pointlybooking_rest_front_bookings',
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('pointly-booking/v1', '/front/availability', [
    'methods'  => 'GET',
    'callback' => 'pointlybooking_rest_front_availability_month',
    'permission_callback' => '__return_true',
  ]);
});

function pointlybooking_rest_front_locations() {
  if (class_exists('POINTLYBOOKING_Locations_Migrations_Helper')) {
    POINTLYBOOKING_Locations_Migrations_Helper::ensure_tables();
  }

  global $wpdb;
  $locations_table = $wpdb->prefix . 'pointlybooking_locations';
  if (!preg_match('/^[A-Za-z0-9_]+$/', $locations_table)) {
    return new WP_REST_Response(['status' => 'success', 'data' => []], 200);
  }

  $exists = pointlybooking_db_table_exists($locations_table);
  if (!$exists) {
    return new WP_REST_Response(['status' => 'success', 'data' => []], 200);
  }

  $rows = $wpdb->get_results(
    "SELECT id,name,address,status,image_id FROM {$locations_table} WHERE status='active' ORDER BY id DESC",
    ARRAY_A
  ) ?: [];

  foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['image_id'] = (int)($r['image_id'] ?? 0);
    if (function_exists('pointlybooking_locations_img_url')) {
      $r['image_url'] = pointlybooking_locations_img_url($r['image_id'], 'medium');
    } else {
      $r['image_url'] = $r['image_id'] ? (wp_get_attachment_image_url($r['image_id'], 'medium') ?: '') : '';
    }
  }

  return new WP_REST_Response(['status' => 'success', 'data' => $rows], 200);
}

function pointlybooking_rest_front_categories() {
  if (function_exists('pointlybooking_rest_public_categories')) {
    return pointlybooking_rest_public_categories();
  }
  return new WP_REST_Response(['status' => 'success', 'data' => []], 200);
}

function pointlybooking_front_services_format_rows(array $rows): array {
  global $wpdb;
  $services_table = $wpdb->prefix . 'pointlybooking_services';
  if (!preg_match('/^[A-Za-z0-9_]+$/', $services_table)) {
    return [];
  }

  $cols = pointlybooking_db_table_columns($services_table);
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
    $r['image_url'] = function_exists('pointlybooking_public_img_url')
      ? pointlybooking_public_img_url($r['image_id'], 'medium')
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

function pointlybooking_rest_front_services(WP_REST_Request $req) {
  global $wpdb;
  $services_table = $wpdb->prefix . 'pointlybooking_services';
  $relation_table = $wpdb->prefix . 'pointlybooking_service_categories';
  if (!preg_match('/^[A-Za-z0-9_]+$/', $services_table) || !preg_match('/^[A-Za-z0-9_]+$/', $relation_table)) {
    return new WP_REST_Response(['status' => 'success', 'data' => []], 200);
  }

  $p = $req->get_json_params();
  if (!is_array($p)) $p = [];
  $category_ids = $p['category_ids'] ?? [];
  if (!is_array($category_ids)) $category_ids = [];
  $category_ids = array_values(array_filter(array_map('intval', $category_ids)));

  $cols = pointlybooking_db_table_columns($services_table);
  $has_is_active = in_array('is_active', $cols, true);

  $rows = [];
  $rel_exists = pointlybooking_db_table_exists($relation_table);

  if ($category_ids && $rel_exists) {
    $service_ids = [];
    foreach ($category_ids as $category_id) {
      $matched = $wpdb->get_col(
        $wpdb->prepare("SELECT service_id FROM {$relation_table} WHERE category_id=%d", (int)$category_id)
      ) ?: [];
      foreach ($matched as $service_id) {
        $service_id = (int)$service_id;
        if ($service_id > 0) {
          $service_ids[$service_id] = true;
        }
      }
    }

    if (!empty($service_ids)) {
      if ($has_is_active) {
        $rows = $wpdb->get_results(
          "SELECT * FROM {$services_table} WHERE is_active=1 ORDER BY id DESC",
          ARRAY_A
        ) ?: [];
      } else {
        $rows = $wpdb->get_results(
          "SELECT * FROM {$services_table} ORDER BY id DESC",
          ARRAY_A
        ) ?: [];
      }

      $rows = array_values(array_filter($rows, static function ($row) use ($service_ids) {
        $service_id = (int)($row['id'] ?? 0);
        return $service_id > 0 && isset($service_ids[$service_id]);
      }));
    }
  } else {
    if ($has_is_active) {
      $rows = $wpdb->get_results(
        "SELECT * FROM {$services_table} WHERE is_active=1 ORDER BY id DESC",
        ARRAY_A
      ) ?: [];
    } else {
      $rows = $wpdb->get_results(
        "SELECT * FROM {$services_table} ORDER BY id DESC",
        ARRAY_A
      ) ?: [];
    }
  }

  $rows = pointlybooking_front_services_format_rows($rows);

  return new WP_REST_Response(['status' => 'success', 'data' => $rows], 200);
}

function pointlybooking_rest_front_extras(WP_REST_Request $req) {
  $p = $req->get_json_params();
  if (!is_array($p)) $p = [];
  $service_id = (int)($p['service_id'] ?? 0);
  if ($service_id <= 0) {
    return new WP_REST_Response(['status' => 'success', 'data' => []], 200);
  }

  if (function_exists('pointlybooking_rest_public_extras')) {
    $r = new WP_REST_Request('GET', '/pointly-booking/v1/public/extras');
    $r->set_param('service_id', $service_id);
    return pointlybooking_rest_public_extras($r);
  }

  return new WP_REST_Response(['status' => 'success', 'data' => []], 200);
}

function pointlybooking_rest_front_agents(WP_REST_Request $req) {
  $p = $req->get_json_params();
  if (!is_array($p)) $p = [];
  $service_id = (int)($p['service_id'] ?? 0);
  $location_id = (int)($p['location_id'] ?? 0);
  if ($service_id <= 0) {
    return new WP_REST_Response(['status' => 'success', 'data' => []], 200);
  }

  if (function_exists('pointlybooking_rest_public_agents')) {
    $r = new WP_REST_Request('GET', '/pointly-booking/v1/public/agents');
    $r->set_param('service_id', $service_id);
    if ($location_id > 0) {
      $r->set_param('location_id', $location_id);
    }
    $resp = pointlybooking_rest_public_agents($r);
    if ($resp instanceof WP_REST_Response) {
      $payload = $resp->get_data();
      $rows = $payload['data'] ?? $payload;
      if (!is_array($rows)) {
        $rows = [];
      }
      foreach ($rows as &$row) {
        if (!is_array($row)) continue;
        if (isset($row['id'])) $row['id'] = (int)$row['id'];
        if (!isset($row['image_url']) && isset($row['image_id'])) {
          $row['image_id'] = (int)$row['image_id'];
          $row['image_url'] = $row['image_id'] ? (wp_get_attachment_image_url($row['image_id'], 'medium') ?: '') : '';
        }
      }
      return new WP_REST_Response(['status' => 'success', 'data' => $rows], 200);
    }
    return $resp;
  }

  return new WP_REST_Response(['status' => 'success', 'data' => []], 200);
}

function pointlybooking_rest_front_slots(WP_REST_Request $req) {
  $p = $req->get_json_params();
  if (!is_array($p)) $p = [];
  $service_id = (int)($p['service_id'] ?? 0);
  $agent_id = (int)($p['agent_id'] ?? 0);
  $date = sanitize_text_field($p['date'] ?? '');
  $location_id = (int)($p['location_id'] ?? 0);

  if ($service_id <= 0 || $agent_id <= 0) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'service_id and agent_id are required'], 400);
  }

  if (function_exists('pointlybooking_rest_public_availability_slots')) {
    $cache_key = 'pointlybooking_front_slots_' . md5($date . '|' . $service_id . '|' . $agent_id . '|' . $location_id);
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
      return new WP_REST_Response(['status' => 'success', 'data' => $cached], 200);
    }

    $r = new WP_REST_Request('GET', '/pointly-booking/v1/public/availability-slots');
    $r->set_param('service_id', $service_id);
    $r->set_param('agent_id', $agent_id);
    $r->set_param('date', $date);
    $resp = pointlybooking_rest_public_availability_slots($r);
    if ($resp instanceof WP_REST_Response) {
      $payload = $resp->get_data();
      $slots = $payload['data'] ?? [];
      if (is_array($slots)) {
        set_transient($cache_key, $slots, 60);
      }
    }
    return $resp;
  }

  return new WP_REST_Response(['status' => 'success', 'data' => []], 200);
}

function pointlybooking_rest_front_form_fields() {
  // Call the public route by simulating GET /public/form-fields.
  $route = rest_do_request(new WP_REST_Request('GET', '/pointly-booking/v1/public/form-fields'));
  if ($route instanceof WP_REST_Response) {
    return $route;
  }

  return new WP_REST_Response(['status' => 'success', 'data' => []], 200);
}

function pointlybooking_rest_front_form_fields_active() {
  global $wpdb;
  $form_fields_table = $wpdb->prefix . 'pointlybooking_form_fields';
  if (!preg_match('/^[A-Za-z0-9_]+$/', $form_fields_table)) {
    return new WP_REST_Response(['status' => 'success', 'data' => ['form'=>[], 'customer'=>[], 'booking'=>[]]], 200);
  }

  $rows = $wpdb->get_results(
    "SELECT * FROM {$form_fields_table}
    WHERE is_enabled=1 AND show_in_wizard=1
    ORDER BY scope ASC, sort_order ASC, id ASC",
    ARRAY_A
  ) ?: [];

  $out = ['form'=>[], 'customer'=>[], 'booking'=>[]];

  foreach ($rows as $r) {
    $scope = $r['scope'] ?? 'customer';
    if (!in_array($scope, ['booking','customer','form'], true)) $scope = 'customer';

    $raw_options = $r['options'] ?: ($r['options_json'] ?? null);
    $options = $raw_options ? json_decode($raw_options, true) : null;

    $out[$scope][] = [
      'id' => $r['field_key'] ?: ($r['name_key'] ?? ''),
      'field_key' => $r['field_key'] ?: ($r['name_key'] ?? ''),
      'label' => $r['label'] ?? '',
      'type' => $r['type'] ?? 'text',
      'scope' => $scope,
      'placeholder' => $r['placeholder'] ?? '',
      'options' => $options,
      'is_required' => (int)($r['is_required'] ?? $r['required'] ?? 0),
      'is_enabled' => (int)($r['is_enabled'] ?? $r['is_active'] ?? 0),
      'show_in_wizard' => (int)($r['show_in_wizard'] ?? 1),
      'sort_order' => (int)($r['sort_order'] ?? 0),
    ];
  }

  return new WP_REST_Response(['status' => 'success', 'data' => $out], 200);
}

function pointlybooking_rest_front_bookings(WP_REST_Request $req) {
  if (function_exists('pointlybooking_public_create_booking')) {
    return pointlybooking_public_create_booking($req);
  }
  return new WP_REST_Response(['status' => 'error', 'message' => 'Booking handler missing'], 500);
}

function pointlybooking_rest_front_availability_month(WP_REST_Request $req) {
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

  $cache_key = 'pointlybooking_front_avail_' . md5($month . '|' . $service_id . '|' . $agent_id . '|' . $location_id);
  $cached = get_transient($cache_key);
  if (is_array($cached)) {
    return new WP_REST_Response(['status' => 'success', 'data' => $cached], 200);
  }

  if (!function_exists('pointlybooking_rest_public_availability_slots')) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'Availability handler missing'], 500);
  }

  $start_ts = strtotime($month . '-01 00:00:00');
  if ($start_ts === false) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'Invalid month'], 400);
  }
  $end_ts = strtotime(gmdate('Y-m-t', $start_ts) . ' 00:00:00');

  $max_days = (int)apply_filters('pointlybooking_public_max_booking_days', 120);
  $today_ts = strtotime(gmdate('Y-m-d') . ' 00:00:00');
  $max_ts = strtotime('+' . $max_days . ' days', $today_ts);

  $data = [];
  for ($ts = $start_ts; $ts <= $end_ts; $ts = strtotime('+1 day', $ts)) {
    if ($ts < $today_ts || $ts > $max_ts) {
      $data[gmdate('Y-m-d', $ts)] = 0;
      continue;
    }
    $date = gmdate('Y-m-d', $ts);
    $r = new WP_REST_Request('GET', '/pointly-booking/v1/public/availability-slots');
    $r->set_param('service_id', $service_id);
    $r->set_param('agent_id', $agent_id);
    $r->set_param('date', $date);
    $resp = pointlybooking_rest_public_availability_slots($r);
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


