<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {

  register_rest_route('bp/v1', '/admin/customers', [
    'methods'  => 'GET',
    'callback' => 'bp_rest_admin_customers_list',
    'permission_callback' => function () {
      return current_user_can('bp_manage_customers')
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

  $where = '1=1';
  $params = [];
  if ($q !== '') {
    $like = '%' . $wpdb->esc_like($q) . '%';
    $where .= ' AND (c.first_name LIKE %s OR c.last_name LIKE %s OR c.email LIKE %s)';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
  }

  $sql = "
    SELECT
      c.*,
      COUNT(b.id) AS bookings_count
    FROM {$tCustomers} c
    LEFT JOIN {$tBookings} b ON b.customer_id = c.id
    WHERE {$where}
    GROUP BY c.id
    ORDER BY c.id DESC
    LIMIT 500
  ";

  $rows = $params
    ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A)
    : $wpdb->get_results($sql, ARRAY_A);

  return new WP_REST_Response(['status' => 'success', 'data' => ($rows ?: [])], 200);
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
