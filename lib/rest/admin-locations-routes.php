<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {

  $perm = function () {
    return current_user_can('manage_options') || current_user_can('bp_manage_settings');
  };

  register_rest_route('bp/v1', '/admin/locations', [
    [
      'methods' => WP_REST_Server::READABLE,
      'permission_callback' => $perm,
      'callback' => 'bp_rest_admin_locations_list',
    ],
    [
      'methods' => WP_REST_Server::CREATABLE,
      'permission_callback' => $perm,
      'callback' => 'bp_rest_admin_locations_create',
    ],
  ]);

  register_rest_route('bp/v1', '/admin/locations/(?P<id>\d+)', [
    [
      'methods' => WP_REST_Server::READABLE,
      'permission_callback' => $perm,
      'callback' => 'bp_rest_admin_locations_get',
    ],
    [
      'methods' => WP_REST_Server::EDITABLE,
      'permission_callback' => $perm,
      'callback' => 'bp_rest_admin_locations_update',
    ],
  ]);

  register_rest_route('bp/v1', '/admin/locations/(?P<id>\d+)/agents', [
    [
      'methods' => WP_REST_Server::READABLE,
      'permission_callback' => $perm,
      'callback' => 'bp_rest_admin_locations_agents_get',
    ],
    [
      'methods' => WP_REST_Server::CREATABLE,
      'permission_callback' => $perm,
      'callback' => 'bp_rest_admin_locations_agents_set',
    ],
  ]);

  register_rest_route('bp/v1', '/admin/location-categories', [
    [
      'methods' => WP_REST_Server::READABLE,
      'permission_callback' => $perm,
      'callback' => 'bp_rest_admin_location_categories_list',
    ],
    [
      'methods' => WP_REST_Server::CREATABLE,
      'permission_callback' => $perm,
      'callback' => 'bp_rest_admin_location_categories_create',
    ],
  ]);

  register_rest_route('bp/v1', '/admin/location-categories/(?P<id>\d+)', [
    [
      'methods' => WP_REST_Server::EDITABLE,
      'permission_callback' => $perm,
      'callback' => 'bp_rest_admin_location_categories_update',
    ],
    [
      'methods' => WP_REST_Server::DELETABLE,
      'permission_callback' => $perm,
      'callback' => 'bp_rest_admin_location_categories_delete',
    ],
  ]);
});

function bp_locations_img_url($image_id, $size = 'thumbnail') {
  $id = (int)$image_id;
  if ($id <= 0) return '';
  $url = wp_get_attachment_image_url($id, $size);
  return $url ? $url : '';
}

function bp_locations_decode_json($value) {
  if (!$value) return null;
  $decoded = json_decode((string)$value, true);
  return is_array($decoded) ? $decoded : null;
}

function bp_locations_require_tables() {
  if (class_exists('BP_Locations_Migrations_Helper')) {
    BP_Locations_Migrations_Helper::ensure_tables();
  }
}

function bp_rest_admin_locations_list() {
  global $wpdb;
  bp_locations_require_tables();

  $loc = $wpdb->prefix . 'bp_locations';
  $cat = $wpdb->prefix . 'bp_location_categories';

  $rows = $wpdb->get_results("
    SELECT l.*, c.name AS category_name, c.image_id AS category_image_id
    FROM {$loc} l
    LEFT JOIN {$cat} c ON c.id = l.category_id
    WHERE l.status = 'active'
    ORDER BY l.id DESC
  ", ARRAY_A) ?: [];

  foreach ($rows as &$r) {
    $r['image_id'] = (int)($r['image_id'] ?? 0);
    $r['image_url'] = bp_locations_img_url($r['image_id'], 'medium');
    $r['category_id'] = (int)($r['category_id'] ?? 0);
    $r['category_image_id'] = (int)($r['category_image_id'] ?? 0);
    $r['category_image_url'] = bp_locations_img_url($r['category_image_id'], 'thumbnail');
  }

  return rest_ensure_response(['status' => 'success', 'data' => $rows]);
}

function bp_rest_admin_locations_create(WP_REST_Request $req) {
  global $wpdb;
  bp_locations_require_tables();

  $loc = $wpdb->prefix . 'bp_locations';
  $b = $req->get_json_params();
  if (!is_array($b)) $b = [];

  $now = current_time('mysql');
  $payload = [
    'status' => 'active',
    'name' => sanitize_text_field($b['name'] ?? 'New Location'),
    'address' => sanitize_text_field($b['address'] ?? ''),
    'category_id' => !empty($b['category_id']) ? (int)$b['category_id'] : null,
    'image_id' => !empty($b['image_id']) ? (int)$b['image_id'] : null,
    'use_custom_schedule' => !empty($b['use_custom_schedule']) ? 1 : 0,
    'schedule_json' => !empty($b['schedule']) ? wp_json_encode($b['schedule']) : null,
    'created_at' => $now,
    'updated_at' => $now,
  ];

  $ok = $wpdb->insert($loc, $payload);
  if (!$ok) {
    return new WP_Error('bp_location_create_failed', $wpdb->last_error ?: 'DB insert failed', ['status' => 500]);
  }

  $id = (int)$wpdb->insert_id;
  return bp_rest_admin_locations_get(['id' => $id]);
}

function bp_rest_admin_locations_get($req) {
  global $wpdb;
  bp_locations_require_tables();

  $id = (int)(is_array($req) ? ($req['id'] ?? 0) : $req['id']);
  if ($id <= 0) return new WP_Error('bp_location_invalid', 'Invalid id', ['status' => 400]);

  $loc = $wpdb->prefix . 'bp_locations';
  $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$loc} WHERE id=%d", $id), ARRAY_A);
  if (!$row) return new WP_Error('bp_location_not_found', 'Location not found', ['status' => 404]);

  $row['image_id'] = (int)($row['image_id'] ?? 0);
  $row['image_url'] = bp_locations_img_url($row['image_id'], 'medium');
  $row['category_id'] = (int)($row['category_id'] ?? 0);
  $row['schedule'] = bp_locations_decode_json($row['schedule_json'] ?? null);

  return rest_ensure_response(['status' => 'success', 'data' => $row]);
}

function bp_rest_admin_locations_update(WP_REST_Request $req) {
  global $wpdb;
  bp_locations_require_tables();

  $id = (int)$req['id'];
  if ($id <= 0) return new WP_Error('bp_location_invalid', 'Invalid id', ['status' => 400]);

  $loc = $wpdb->prefix . 'bp_locations';
  $b = $req->get_json_params();
  if (!is_array($b)) $b = [];

  $payload = [
    'status' => 'active',
    'name' => sanitize_text_field($b['name'] ?? ''),
    'address' => sanitize_text_field($b['address'] ?? ''),
    'category_id' => !empty($b['category_id']) ? (int)$b['category_id'] : null,
    'image_id' => !empty($b['image_id']) ? (int)$b['image_id'] : null,
    'use_custom_schedule' => !empty($b['use_custom_schedule']) ? 1 : 0,
    'schedule_json' => array_key_exists('schedule', $b) ? wp_json_encode($b['schedule']) : null,
    'updated_at' => current_time('mysql'),
  ];

  $ok = $wpdb->update($loc, $payload, ['id' => $id]);
  if ($ok === false) {
    return new WP_Error('bp_location_update_failed', $wpdb->last_error ?: 'DB update failed', ['status' => 500]);
  }

  return bp_rest_admin_locations_get(['id' => $id]);
}

function bp_rest_admin_locations_agents_get(WP_REST_Request $req) {
  global $wpdb;
  bp_locations_require_tables();

  $id = (int)$req['id'];
  if ($id <= 0) return new WP_Error('bp_location_invalid', 'Invalid id', ['status' => 400]);

  $map = $wpdb->prefix . 'bp_location_agents';
  $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$map} WHERE location_id=%d", $id), ARRAY_A) ?: [];

  foreach ($rows as &$r) {
    $r['agent_id'] = (int)$r['agent_id'];
    $r['services'] = bp_locations_decode_json($r['services_json'] ?? null) ?: [];
  }

  return rest_ensure_response(['status' => 'success', 'data' => $rows]);
}

function bp_rest_admin_locations_agents_set(WP_REST_Request $req) {
  global $wpdb;
  bp_locations_require_tables();

  $id = (int)$req['id'];
  if ($id <= 0) return new WP_Error('bp_location_invalid', 'Invalid id', ['status' => 400]);

  $map = $wpdb->prefix . 'bp_location_agents';
  $b = $req->get_json_params();
  if (!is_array($b)) $b = [];

  $agents = $b['agents'] ?? [];
  if (!is_array($agents)) $agents = [];

  $wpdb->delete($map, ['location_id' => $id], ['%d']);
  $now = current_time('mysql');

  foreach ($agents as $a) {
    $agent_id = (int)($a['agent_id'] ?? 0);
    if ($agent_id <= 0) continue;

    $services = $a['services'] ?? null;
    if (!is_array($services)) $services = null;
    $services_json = $services !== null ? wp_json_encode(array_values(array_unique(array_map('intval', $services)))) : null;

    $wpdb->insert($map, [
      'location_id' => $id,
      'agent_id' => $agent_id,
      'services_json' => $services_json,
      'created_at' => $now,
      'updated_at' => $now,
    ], ['%d','%d','%s','%s','%s']);
  }

  return rest_ensure_response(['status' => 'success', 'data' => ['saved' => true]]);
}

function bp_rest_admin_location_categories_list() {
  global $wpdb;
  bp_locations_require_tables();

  $t = $wpdb->prefix . 'bp_location_categories';
  $rows = $wpdb->get_results("SELECT * FROM {$t} WHERE status='active' ORDER BY id DESC", ARRAY_A) ?: [];

  foreach ($rows as &$r) {
    $r['image_id'] = (int)($r['image_id'] ?? 0);
    $r['image_url'] = bp_locations_img_url($r['image_id'], 'thumbnail');
  }

  return rest_ensure_response(['status' => 'success', 'data' => $rows]);
}

function bp_rest_admin_location_categories_create(WP_REST_Request $req) {
  global $wpdb;
  bp_locations_require_tables();

  $t = $wpdb->prefix . 'bp_location_categories';
  $b = $req->get_json_params();
  if (!is_array($b)) $b = [];

  $name = sanitize_text_field($b['name'] ?? '');
  if ($name === '') {
    return new WP_Error('bp_location_category_invalid', 'Name is required', ['status' => 400]);
  }

  $now = current_time('mysql');
  $payload = [
    'status' => 'active',
    'name' => $name,
    'image_id' => !empty($b['image_id']) ? (int)$b['image_id'] : null,
    'created_at' => $now,
    'updated_at' => $now,
  ];

  $ok = $wpdb->insert($t, $payload);
  if (!$ok) {
    return new WP_Error('bp_location_category_create_failed', $wpdb->last_error ?: 'DB insert failed', ['status' => 500]);
  }

  return bp_rest_admin_location_categories_list();
}

function bp_rest_admin_location_categories_update(WP_REST_Request $req) {
  global $wpdb;
  bp_locations_require_tables();

  $id = (int)$req['id'];
  if ($id <= 0) return new WP_Error('bp_location_category_invalid', 'Invalid id', ['status' => 400]);

  $t = $wpdb->prefix . 'bp_location_categories';
  $b = $req->get_json_params();
  if (!is_array($b)) $b = [];

  $u = [
    'status' => 'active',
    'updated_at' => current_time('mysql'),
  ];
  if (isset($b['name'])) $u['name'] = sanitize_text_field($b['name']);
  if (array_key_exists('image_id', $b)) $u['image_id'] = !empty($b['image_id']) ? (int)$b['image_id'] : null;

  $ok = $wpdb->update($t, $u, ['id' => $id]);
  if ($ok === false) {
    return new WP_Error('bp_location_category_update_failed', $wpdb->last_error ?: 'DB update failed', ['status' => 500]);
  }

  return bp_rest_admin_location_categories_list();
}

function bp_rest_admin_location_categories_delete(WP_REST_Request $req) {
  global $wpdb;
  bp_locations_require_tables();

  $id = (int)$req['id'];
  if ($id <= 0) return new WP_Error('bp_location_category_invalid', 'Invalid id', ['status' => 400]);

  $t = $wpdb->prefix . 'bp_location_categories';
  $wpdb->delete($t, ['id' => $id], ['%d']);

  return rest_ensure_response(['status' => 'success', 'data' => ['deleted' => true]]);
}
