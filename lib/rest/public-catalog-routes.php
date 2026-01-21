<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {

  // Categories list (enabled only - if you have status column; otherwise all)
  register_rest_route('bp/v1', '/public/categories', [
    'methods'  => 'GET',
    'callback' => 'bp_rest_public_categories',
    'permission_callback' => '__return_true',
  ]);

  // Services (optional filter by category_id)
  register_rest_route('bp/v1', '/public/services', [
    'methods'  => 'GET',
    'callback' => 'bp_rest_public_services',
    'permission_callback' => '__return_true',
  ]);

  // Extras (filter by service_id)
  register_rest_route('bp/v1', '/public/extras', [
    'methods'  => 'GET',
    'callback' => 'bp_rest_public_extras',
    'permission_callback' => '__return_true',
  ]);

  // Agents (filter by service_id)
  register_rest_route('bp/v1', '/public/agents', [
    'methods'  => 'GET',
    'callback' => 'bp_rest_public_agents',
    'permission_callback' => '__return_true',
  ]);
});

function bp_public_img_url($id, $size = 'medium') {
  $id = (int)$id;
  if ($id <= 0) return '';
  $u = wp_get_attachment_image_url($id, $size);
  return $u ? $u : '';
}

function bp_rest_public_categories() {
  global $wpdb;
  $t = $wpdb->prefix . 'bp_categories';
  $rows = $wpdb->get_results("SELECT id,name,image_id,sort_order FROM {$t} ORDER BY sort_order ASC, id DESC", ARRAY_A) ?: [];
  foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['image_id'] = (int)($r['image_id'] ?? 0);
    $r['image_url'] = bp_public_img_url($r['image_id'], 'medium');
    $r['sort_order'] = (int)($r['sort_order'] ?? 0);
  }
  return new WP_REST_Response(['status' => 'success', 'data' => $rows], 200);
}

function bp_rest_public_services(WP_REST_Request $req) {
  global $wpdb;
  $t = $wpdb->prefix . 'bp_services';
  $t_rel = $wpdb->prefix . 'bp_service_categories';

  $category_id = (int)($req->get_param('category_id') ?? 0);

  $has_rel = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$t_rel}");
  if ($category_id > 0 && $has_rel === 0) {
    $category_id = 0;
  }

  if ($category_id > 0) {
    $rows = $wpdb->get_results($wpdb->prepare("
      SELECT s.id,s.name,s.duration,s.price,s.image_id,s.sort_order,
             s.buffer_before,s.buffer_after,s.capacity
      FROM {$t} s
      INNER JOIN {$t_rel} sc ON sc.service_id=s.id
      WHERE sc.category_id=%d
      ORDER BY s.sort_order ASC, s.id DESC
    ", $category_id), ARRAY_A) ?: [];
  } else {
    $rows = $wpdb->get_results("
      SELECT id,name,duration,price,image_id,sort_order,buffer_before,buffer_after,capacity
      FROM {$t}
      ORDER BY sort_order ASC, id DESC
    ", ARRAY_A) ?: [];
  }

  foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['duration'] = (int)($r['duration'] ?? 30);
    $r['price'] = (float)($r['price'] ?? 0);
    $r['image_id'] = (int)($r['image_id'] ?? 0);
    $r['image_url'] = bp_public_img_url($r['image_id'], 'medium');
    $r['sort_order'] = (int)($r['sort_order'] ?? 0);
    $r['buffer_before'] = (int)($r['buffer_before'] ?? 0);
    $r['buffer_after'] = (int)($r['buffer_after'] ?? 0);
    $r['capacity'] = (int)($r['capacity'] ?? 1);
  }

  return new WP_REST_Response(['status' => 'success', 'data' => $rows], 200);
}

function bp_extras_table_public() {
  // ⚠️ change if your extras table name is different
  return 'bp_service_extras';
}

function bp_rest_public_extras(WP_REST_Request $req) {
  global $wpdb;
  $service_id = (int)($req->get_param('service_id') ?? 0);
  if ($service_id <= 0) return new WP_REST_Response(['status' => 'success', 'data' => []], 200);

  $t = $wpdb->prefix . bp_extras_table_public();
  $t_rel = $wpdb->prefix . 'bp_extra_services';

  $rows = $wpdb->get_results($wpdb->prepare("
    SELECT e.id,e.name,e.price,e.image_id,e.sort_order
    FROM {$t} e
    INNER JOIN {$t_rel} es ON es.extra_id=e.id
    WHERE es.service_id=%d
    ORDER BY e.sort_order ASC, e.id DESC
  ", $service_id), ARRAY_A) ?: [];

  foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['price'] = (float)($r['price'] ?? 0);
    $r['image_id'] = (int)($r['image_id'] ?? 0);
    $r['image_url'] = bp_public_img_url($r['image_id'], 'medium');
    $r['sort_order'] = (int)($r['sort_order'] ?? 0);
  }

  return new WP_REST_Response(['status' => 'success', 'data' => $rows], 200);
}

function bp_rest_public_agents(WP_REST_Request $req) {
  global $wpdb;
  $service_id = (int)($req->get_param('service_id') ?? 0);
  if ($service_id <= 0) return new WP_REST_Response(['status' => 'success', 'data' => []], 200);

  $t = $wpdb->prefix . 'bp_agents';
  $t_rel = $wpdb->prefix . 'bp_agent_services';

  $rows = $wpdb->get_results($wpdb->prepare("
    SELECT a.id,a.name,a.image_id
    FROM {$t} a
    INNER JOIN {$t_rel} r ON r.agent_id=a.id
    WHERE r.service_id=%d
    ORDER BY a.id DESC
  ", $service_id), ARRAY_A) ?: [];

  foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['image_id'] = (int)($r['image_id'] ?? 0);
    $r['image_url'] = bp_public_img_url($r['image_id'], 'medium');
  }

  return new WP_REST_Response(['status' => 'success', 'data' => $rows], 200);
}
