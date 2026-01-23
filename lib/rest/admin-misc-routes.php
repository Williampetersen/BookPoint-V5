<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {

  register_rest_route('bp/v1', '/admin/customers', [
    'methods'  => 'GET',
    'callback' => 'bp_rest_admin_customers_list',
    'permission_callback' => function () {
      return current_user_can('bp_manage_customers')
        || current_user_can('bp_manage_bookings')
        || current_user_can('bp_manage_settings')
        || current_user_can('manage_options');
    },
  ]);

  register_rest_route('bp/v1', '/admin/customers/(?P<id>\d+)', [
    'methods'  => 'GET',
    'callback' => 'bp_rest_admin_customer_get',
    'permission_callback' => function () {
      return current_user_can('bp_manage_customers')
        || current_user_can('bp_manage_bookings')
        || current_user_can('bp_manage_settings')
        || current_user_can('manage_options');
    },
  ]);

  register_rest_route('bp/v1', '/admin/customers', [
    'methods'  => 'POST',
    'callback' => 'bp_rest_admin_customer_create',
    'permission_callback' => function () {
      return current_user_can('bp_manage_customers')
        || current_user_can('bp_manage_bookings')
        || current_user_can('bp_manage_settings')
        || current_user_can('manage_options');
    },
  ]);

  register_rest_route('bp/v1', '/admin/customers/form-fields', [
    'methods'  => 'GET',
    'callback' => 'bp_rest_admin_customer_form_fields',
    'permission_callback' => function () {
      return current_user_can('bp_manage_customers')
        || current_user_can('bp_manage_bookings')
        || current_user_can('bp_manage_settings')
        || current_user_can('manage_options');
    },
  ]);

  register_rest_route('bp/v1', '/admin/promo-codes', [
    'methods'  => 'GET',
    'callback' => 'bp_rest_admin_promo_codes_list',
    'permission_callback' => function () {
      return current_user_can('bp_manage_services')
        || current_user_can('bp_manage_settings')
        || current_user_can('manage_options');
    },
  ]);

  register_rest_route('bp/v1', '/admin/audit-logs', [
    'methods'  => 'GET',
    'callback' => 'bp_rest_admin_audit_logs_list',
    'permission_callback' => function () {
      return current_user_can('bp_manage_settings')
        || current_user_can('bp_manage_tools')
        || current_user_can('manage_options');
    },
  ]);

  register_rest_route('bp/v1', '/admin/tools/status', [
    'methods'  => 'GET',
    'callback' => 'bp_rest_admin_tools_status',
    'permission_callback' => function () {
      return current_user_can('bp_manage_tools')
        || current_user_can('bp_manage_settings')
        || current_user_can('manage_options');
    },
  ]);

  register_rest_route('bp/v1', '/admin/tools/run/(?P<action>[a-z0-9_-]+)', [
    'methods'  => 'POST',
    'callback' => 'bp_rest_admin_tools_run',
    'permission_callback' => function () {
      return current_user_can('bp_manage_tools')
        || current_user_can('bp_manage_settings')
        || current_user_can('manage_options');
    },
  ]);
});

function bp_rest_admin_customers_list(WP_REST_Request $req) {
  global $wpdb;

  $tCustomers = $wpdb->prefix . 'bp_customers';
  $tBookings  = $wpdb->prefix . 'bp_bookings';

  $q = sanitize_text_field($req->get_param('q') ?? '');
  $page = max(1, absint($req->get_param('page') ?? 1));
  $per = max(1, min(200, absint($req->get_param('per') ?? 25)));
  $sort = strtolower(sanitize_text_field($req->get_param('sort') ?? 'desc'));
  $sort = $sort === 'asc' ? 'ASC' : 'DESC';
  $offset = ($page - 1) * $per;

  $where = '1=1';
  $params = [];
  if ($q !== '') {
    $like = '%' . $wpdb->esc_like($q) . '%';
    $where .= ' AND (c.first_name LIKE %s OR c.last_name LIKE %s OR c.email LIKE %s OR c.phone LIKE %s)';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
  }

  $sql = "
    SELECT
      c.*,
      (SELECT COUNT(*) FROM {$tBookings} b WHERE b.customer_id = c.id) AS bookings_count
    FROM {$tCustomers} c
    WHERE {$where}
    ORDER BY c.id {$sort}
    LIMIT %d OFFSET %d
  ";

  $countSql = "SELECT COUNT(*) FROM {$tCustomers} c WHERE {$where}";

  if ($params) {
    $rows = $wpdb->get_results($wpdb->prepare($sql, array_merge($params, [$per, $offset])), ARRAY_A);
    $total = (int)$wpdb->get_var($wpdb->prepare($countSql, $params));
  } else {
    $rows = $wpdb->get_results($wpdb->prepare($sql, [$per, $offset]), ARRAY_A);
    $total = (int)$wpdb->get_var($countSql);
  }

  return new WP_REST_Response([
    'status' => 'success',
    'data' => [
      'items' => ($rows ?: []),
      'total' => $total,
    ],
  ], 200);
}

function bp_rest_admin_customer_get(WP_REST_Request $req) {
  global $wpdb;

  $id = (int)$req['id'];
  if ($id <= 0) return new WP_REST_Response(['status' => 'error', 'message' => 'Invalid id'], 400);

  $tCustomers = $wpdb->prefix . 'bp_customers';
  $tBookings  = $wpdb->prefix . 'bp_bookings';
  $tServices  = $wpdb->prefix . 'bp_services';
  $tAgents    = $wpdb->prefix . 'bp_agents';

  $customer = $wpdb->get_row(
    $wpdb->prepare("SELECT * FROM {$tCustomers} WHERE id = %d", $id),
    ARRAY_A
  );

  if (!$customer) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'Customer not found'], 404);
  }

  $custom_fields = [];
  if (!empty($customer['custom_fields_json'])) {
    $decoded = json_decode($customer['custom_fields_json'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
      $custom_fields = $decoded;
    }
  }

  $rows = $wpdb->get_results(
    $wpdb->prepare(
      "SELECT
        b.id,
        b.status,
        b.start_datetime,
        b.end_datetime,
        b.created_at,
        b.service_id,
        b.agent_id,
        s.name AS service_name,
        a.first_name AS agent_first_name,
        a.last_name AS agent_last_name
      FROM {$tBookings} b
      LEFT JOIN {$tServices} s ON s.id = b.service_id
      LEFT JOIN {$tAgents} a ON a.id = b.agent_id
      WHERE b.customer_id = %d
      ORDER BY b.id DESC
      LIMIT 100",
      $id
    ),
    ARRAY_A
  ) ?: [];

  $bookings = array_map(function($b){
    $agent = trim(($b['agent_first_name'] ?? '') . ' ' . ($b['agent_last_name'] ?? ''));
    return [
      'id' => (int)$b['id'],
      'status' => $b['status'] ?? 'pending',
      'start_datetime' => $b['start_datetime'] ?? null,
      'end_datetime' => $b['end_datetime'] ?? null,
      'created_at' => $b['created_at'] ?? null,
      'service_id' => $b['service_id'] ?? null,
      'service_name' => $b['service_name'] ?? null,
      'agent_id' => $b['agent_id'] ?? null,
      'agent_name' => $agent !== '' ? $agent : null,
    ];
  }, $rows);

  $customer['custom_fields'] = $custom_fields;

  return new WP_REST_Response([
    'status' => 'success',
    'data' => [
      'customer' => $customer,
      'bookings' => $bookings,
    ],
  ], 200);
}

function bp_rest_admin_customer_create(WP_REST_Request $req) {
  $body = $req->get_json_params() ?: [];

  $first_name = sanitize_text_field($body['first_name'] ?? '');
  $last_name  = sanitize_text_field($body['last_name'] ?? '');
  $email      = sanitize_email($body['email'] ?? '');
  $phone      = sanitize_text_field($body['phone'] ?? '');

  $custom_fields = $body['custom_fields'] ?? null;

  if ($first_name === '' && $last_name === '' && $email === '' && $phone === '' && empty($custom_fields)) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'Please enter at least one field.'], 400);
  }

  if ($email !== '') {
    $existing = BP_CustomerModel::find_by_email($email);
    if ($existing) {
      return new WP_REST_Response(['status' => 'success', 'data' => ['customer' => $existing, 'created' => false]], 200);
    }
  }

  $sanitize_value = function($val){
    if (is_array($val)) {
      return array_map(function($v){
        return is_scalar($v) ? sanitize_text_field($v) : $v;
      }, $val);
    }
    if (is_bool($val)) return $val ? 1 : 0;
    return is_scalar($val) ? sanitize_text_field($val) : $val;
  };

  $custom_fields_json = null;
  if (is_array($custom_fields)) {
    $clean = [];
    foreach ($custom_fields as $k => $v) {
      $key = sanitize_key($k);
      if ($key === '') continue;
      $clean[$key] = $sanitize_value($v);
    }
    $custom_fields_json = wp_json_encode($clean);
  }

  $id = BP_CustomerModel::create([
    'first_name' => $first_name ?: null,
    'last_name'  => $last_name ?: null,
    'email'      => $email ?: null,
    'phone'      => $phone ?: null,
    'custom_fields_json' => $custom_fields_json,
  ]);

  $customer = BP_CustomerModel::find($id);

  return new WP_REST_Response([
    'status' => 'success',
    'data' => [
      'customer' => $customer,
      'created' => true,
    ],
  ], 201);
}

function bp_rest_admin_customer_form_fields(WP_REST_Request $req) {
  global $wpdb;
  $t = $wpdb->prefix . 'bp_form_fields';

  $rows = $wpdb->get_results($wpdb->prepare("
    SELECT * FROM {$t}
    WHERE scope=%s
    ORDER BY sort_order ASC, id ASC
  ", 'customer'), ARRAY_A) ?: [];

  foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['field_key'] = $r['field_key'] ?: ($r['name_key'] ?? '');
    $r['is_required'] = (int)($r['is_required'] ?? $r['required'] ?? 0);
    $r['is_enabled'] = (int)($r['is_enabled'] ?? $r['is_active'] ?? 0);
    $r['show_in_wizard'] = (int)($r['show_in_wizard'] ?? 1);
    $r['sort_order'] = (int)$r['sort_order'];
    $raw_options = $r['options'] ?: ($r['options_json'] ?? null);
    $r['options'] = $raw_options ? json_decode($raw_options, true) : null;
  }

  return new WP_REST_Response(['status' => 'success', 'data' => $rows], 200);
}

function bp_rest_admin_promo_codes_list(WP_REST_Request $req) {
  $args = [
    'q' => sanitize_text_field($req->get_param('q') ?? ''),
    'is_active' => $req->get_param('is_active') !== null ? (int)$req->get_param('is_active') : null,
  ];

  $rows = BP_PromoCodeModel::all($args);
  return new WP_REST_Response(['status' => 'success', 'data' => ($rows ?: [])], 200);
}

function bp_rest_admin_audit_logs_list(WP_REST_Request $req) {
  $args = [
    'page' => absint($req->get_param('page') ?? 1),
    'per_page' => absint($req->get_param('per_page') ?? 50),
    'event' => sanitize_text_field($req->get_param('event') ?? ''),
    'actor_type' => sanitize_text_field($req->get_param('actor_type') ?? ''),
    'booking_id' => absint($req->get_param('booking_id') ?? 0),
    'customer_id' => absint($req->get_param('customer_id') ?? 0),
    'date_from' => sanitize_text_field($req->get_param('date_from') ?? ''),
    'date_to' => sanitize_text_field($req->get_param('date_to') ?? ''),
  ];

  $data = BP_AuditModel::list_paged($args);
  return new WP_REST_Response(['status' => 'success', 'data' => $data], 200);
}

function bp_rest_admin_tools_status(WP_REST_Request $req) {
  global $wpdb;

  $tables = [
    'bp_services',
    'bp_agents',
    'bp_customers',
    'bp_bookings',
    'bp_settings',
    'bp_audit_log',
    'bp_service_agents',
    'bp_service_categories',
    'bp_service_extras',
    'bp_promo_codes',
  ];

  $exists = [];
  $okCount = 0;
  foreach ($tables as $t) {
    $full = $wpdb->prefix . $t;
    $isOk = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $full)) === $full);
    $exists[$t] = $isOk;
    if ($isOk) $okCount++;
  }

  $db_version = (string)get_option('BP_db_version', '');
  $plugin_version = class_exists('BP_Plugin') ? BP_Plugin::VERSION : '';

  return new WP_REST_Response([
    'status' => 'success',
    'data' => [
      'tables' => $exists,
      'tables_ok_count' => $okCount,
      'tables_total' => count($tables),
      'db_version' => $db_version,
      'plugin_version' => $plugin_version,
    ],
  ], 200);
}

function bp_rest_admin_tools_run(WP_REST_Request $req) {
  $action = sanitize_text_field($req['action'] ?? '');

  if ($action === 'sync_relations') {
    if (!class_exists('BP_RelationsHelper')) {
      return new WP_REST_Response(['status' => 'error', 'message' => 'Relations helper missing'], 500);
    }
    $result = BP_RelationsHelper::sync_relations(true);
    return new WP_REST_Response(['status' => 'success', 'message' => 'Relations synced', 'data' => $result], 200);
  }

  if ($action === 'generate_demo') {
    if (!class_exists('BP_DemoHelper')) {
      return new WP_REST_Response(['status' => 'error', 'message' => 'Demo helper missing'], 500);
    }
    $body = $req->get_json_params() ?: [];
    $services = absint($body['services'] ?? 3);
    $agents = absint($body['agents'] ?? 3);
    $customers = absint($body['customers'] ?? 5);
    $bookings = absint($body['bookings'] ?? 10);
    $result = BP_DemoHelper::generate($services, $agents, $customers, $bookings);
    return new WP_REST_Response(['status' => 'success', 'message' => 'Demo data generated', 'data' => $result], 200);
  }

  if ($action === 'reset_cache') {
    if (function_exists('wp_cache_flush')) {
      wp_cache_flush();
    }
    return new WP_REST_Response(['status' => 'success', 'message' => 'Cache cleared'], 200);
  }

  return new WP_REST_Response(['status' => 'error', 'message' => 'Unknown tool action'], 400);
}
