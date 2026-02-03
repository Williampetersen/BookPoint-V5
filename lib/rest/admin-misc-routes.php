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

  register_rest_route('bp/v1', '/admin/customers/(?P<id>\d+)', [
    'methods'  => ['PUT','PATCH'],
    'callback' => 'bp_rest_admin_customer_update',
    'permission_callback' => function () {
      return current_user_can('bp_manage_customers')
        || current_user_can('bp_manage_bookings')
        || current_user_can('bp_manage_settings')
        || current_user_can('manage_options');
    },
  ]);

  register_rest_route('bp/v1', '/admin/customers/(?P<id>\d+)', [
    'methods'  => 'DELETE',
    'callback' => 'bp_rest_admin_customer_delete',
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

  register_rest_route('bp/v1', '/admin/promo-codes', [
    'methods'  => 'POST',
    'callback' => 'bp_rest_admin_promo_codes_create',
    'permission_callback' => function () {
      return current_user_can('bp_manage_services')
        || current_user_can('bp_manage_settings')
        || current_user_can('manage_options');
    },
  ]);

  register_rest_route('bp/v1', '/admin/promo-codes/(?P<id>\\d+)', [
    'methods'  => 'GET',
    'callback' => 'bp_rest_admin_promo_codes_get',
    'permission_callback' => function () {
      return current_user_can('bp_manage_services')
        || current_user_can('bp_manage_settings')
        || current_user_can('manage_options');
    },
  ]);

  register_rest_route('bp/v1', '/admin/promo-codes/(?P<id>\\d+)', [
    'methods'  => 'PATCH',
    'callback' => 'bp_rest_admin_promo_codes_update',
    'permission_callback' => function () {
      return current_user_can('bp_manage_services')
        || current_user_can('bp_manage_settings')
        || current_user_can('manage_options');
    },
  ]);

  register_rest_route('bp/v1', '/admin/promo-codes/(?P<id>\\d+)', [
    'methods'  => 'DELETE',
    'callback' => 'bp_rest_admin_promo_codes_delete',
    'permission_callback' => function () {
      return current_user_can('bp_manage_services')
        || current_user_can('bp_manage_settings')
        || current_user_can('manage_options');
    },
  ]);

  register_rest_route('bp/v1', '/admin/promo-codes/(?P<id>\\d+)/duplicate', [
    'methods'  => 'POST',
    'callback' => 'bp_rest_admin_promo_codes_duplicate',
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

  register_rest_route('bp/v1', '/admin/audit-logs/meta', [
    'methods'  => 'GET',
    'callback' => 'bp_rest_admin_audit_logs_meta',
    'permission_callback' => function () {
      return current_user_can('bp_manage_settings')
        || current_user_can('bp_manage_tools')
        || current_user_can('manage_options');
    },
  ]);

  register_rest_route('bp/v1', '/admin/audit-logs/clear', [
    'methods'  => 'POST',
    'callback' => 'bp_rest_admin_audit_logs_clear',
    'permission_callback' => function () {
      return current_user_can('bp_manage_settings')
        || current_user_can('bp_manage_tools')
        || current_user_can('manage_options');
    },
  ]);

  register_rest_route('bp/v1', '/admin/audit-logs/export', [
    'methods'  => 'GET',
    'callback' => 'bp_rest_admin_audit_logs_export',
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

  register_rest_route('bp/v1', '/admin/tools/report', [
    'methods'  => 'GET',
    'callback' => 'bp_rest_admin_tools_report',
    'permission_callback' => function () {
      return current_user_can('bp_manage_tools')
        || current_user_can('bp_manage_settings')
        || current_user_can('manage_options');
    },
  ]);

  register_rest_route('bp/v1', '/admin/tools/export-settings', [
    'methods'  => 'GET',
    'callback' => 'bp_rest_admin_tools_export_settings',
    'permission_callback' => function () {
      return current_user_can('bp_manage_tools')
        || current_user_can('bp_manage_settings')
        || current_user_can('manage_options');
    },
  ]);

  register_rest_route('bp/v1', '/admin/tools/import-settings', [
    'methods'  => 'POST',
    'callback' => 'bp_rest_admin_tools_import_settings',
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

function bp_rest_admin_customer_update(WP_REST_Request $req) {
  global $wpdb;

  $id = (int)$req['id'];
  if ($id <= 0) return new WP_REST_Response(['status' => 'error', 'message' => 'Invalid id'], 400);

  $existing = BP_CustomerModel::find($id);
  if (!$existing) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'Customer not found'], 404);
  }

  $body = $req->get_json_params() ?: [];

  $first_name = sanitize_text_field($body['first_name'] ?? '');
  $last_name  = sanitize_text_field($body['last_name'] ?? '');
  $email      = sanitize_email($body['email'] ?? '');
  $phone      = sanitize_text_field($body['phone'] ?? '');
  $custom_fields = $body['custom_fields'] ?? null;

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

  $table = BP_CustomerModel::table();
  $updated = $wpdb->update($table, [
    'first_name' => $first_name !== '' ? $first_name : null,
    'last_name'  => $last_name !== '' ? $last_name : null,
    'email'      => $email !== '' ? $email : null,
    'phone'      => $phone !== '' ? $phone : null,
    'custom_fields_json' => $custom_fields_json,
    // Use WP timezone timestamp. BP_Model::now_mysql() is protected (subclasses only).
    'updated_at' => current_time('mysql'),
  ], ['id' => $id], ['%s','%s','%s','%s','%s','%s'], ['%d']);

  if ($updated === false) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'Update failed'], 500);
  }

  $customer = BP_CustomerModel::find($id);

  return new WP_REST_Response(['status' => 'success', 'data' => ['customer' => $customer]], 200);
}

function bp_rest_admin_customer_delete(WP_REST_Request $req) {
  $id = (int)$req['id'];
  if ($id <= 0) return new WP_REST_Response(['status' => 'error', 'message' => 'Invalid id'], 400);

  $existing = BP_CustomerModel::find($id);
  if (!$existing) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'Customer not found'], 404);
  }

  $table = BP_CustomerModel::table();
  $deleted = $GLOBALS['wpdb']->delete($table, ['id' => $id], ['%d']);
  if ($deleted === false) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'Delete failed'], 500);
  }

  return new WP_REST_Response(['status' => 'success', 'message' => 'Customer deleted'], 200);
}

function bp_rest_admin_promo_codes_list(WP_REST_Request $req) {
  $args = [
    'q' => sanitize_text_field($req->get_param('q') ?? ''),
    'is_active' => $req->get_param('is_active') !== null ? (int)$req->get_param('is_active') : null,
  ];

  $rows = BP_PromoCodeModel::all($args);
  return new WP_REST_Response(['status' => 'success', 'data' => ($rows ?: [])], 200);
}

function bp_rest_admin_promo_codes_get(WP_REST_Request $req) {
  $id = (int)$req['id'];
  if ($id <= 0) return new WP_REST_Response(['status' => 'error', 'message' => 'Invalid id'], 400);
  $row = BP_PromoCodeModel::find($id);
  if (!$row) return new WP_REST_Response(['status' => 'error', 'message' => 'Not found'], 404);
  return new WP_REST_Response(['status' => 'success', 'data' => $row], 200);
}

function bp_rest_admin_promo_codes_create(WP_REST_Request $req) {
  $p = $req->get_json_params();
  if (!is_array($p)) $p = [];

  $code = strtoupper(sanitize_text_field($p['code'] ?? ''));
  if ($code === '') return new WP_REST_Response(['status' => 'error', 'message' => 'Code required'], 400);

  $existing = BP_PromoCodeModel::find_by_code($code);
  if ($existing) return new WP_REST_Response(['status' => 'error', 'message' => 'Code already exists'], 409);

  $id = BP_PromoCodeModel::save([
    'code' => $code,
    'type' => sanitize_text_field($p['type'] ?? 'percent'),
    'amount' => $p['amount'] ?? 0,
    'starts_at' => sanitize_text_field($p['starts_at'] ?? ''),
    'ends_at' => sanitize_text_field($p['ends_at'] ?? ''),
    'max_uses' => array_key_exists('max_uses', $p) ? $p['max_uses'] : null,
    'min_total' => array_key_exists('min_total', $p) ? $p['min_total'] : null,
    'is_active' => array_key_exists('is_active', $p) ? (int)!empty($p['is_active']) : 1,
  ]);

  $row = $id ? BP_PromoCodeModel::find($id) : null;
  if (!$row) return new WP_REST_Response(['status' => 'error', 'message' => 'Create failed'], 500);
  return new WP_REST_Response(['status' => 'success', 'data' => $row], 201);
}

function bp_rest_admin_promo_codes_update(WP_REST_Request $req) {
  $id = (int)$req['id'];
  if ($id <= 0) return new WP_REST_Response(['status' => 'error', 'message' => 'Invalid id'], 400);

  $existing = BP_PromoCodeModel::find($id);
  if (!$existing) return new WP_REST_Response(['status' => 'error', 'message' => 'Not found'], 404);

  $p = $req->get_json_params();
  if (!is_array($p)) $p = [];

  $code = array_key_exists('code', $p) ? strtoupper(sanitize_text_field($p['code'] ?? '')) : null;
  if ($code !== null && $code === '') return new WP_REST_Response(['status' => 'error', 'message' => 'Code required'], 400);
  if ($code !== null && $code !== strtoupper((string)($existing['code'] ?? ''))) {
    $dupe = BP_PromoCodeModel::find_by_code($code);
    if ($dupe && (int)$dupe['id'] !== $id) {
      return new WP_REST_Response(['status' => 'error', 'message' => 'Code already exists'], 409);
    }
  }

  $payload = ['id' => $id];
  if ($code !== null) $payload['code'] = $code;
  if (array_key_exists('type', $p)) $payload['type'] = sanitize_text_field($p['type'] ?? 'percent');
  if (array_key_exists('amount', $p)) $payload['amount'] = $p['amount'];
  if (array_key_exists('starts_at', $p)) $payload['starts_at'] = sanitize_text_field($p['starts_at'] ?? '');
  if (array_key_exists('ends_at', $p)) $payload['ends_at'] = sanitize_text_field($p['ends_at'] ?? '');
  if (array_key_exists('max_uses', $p)) $payload['max_uses'] = $p['max_uses'];
  if (array_key_exists('min_total', $p)) $payload['min_total'] = $p['min_total'];
  if (array_key_exists('is_active', $p)) $payload['is_active'] = (int)!empty($p['is_active']);

  BP_PromoCodeModel::save($payload);
  $row = BP_PromoCodeModel::find($id);
  return new WP_REST_Response(['status' => 'success', 'data' => $row], 200);
}

function bp_rest_admin_promo_codes_delete(WP_REST_Request $req) {
  $id = (int)$req['id'];
  if ($id <= 0) return new WP_REST_Response(['status' => 'error', 'message' => 'Invalid id'], 400);
  $ok = BP_PromoCodeModel::delete($id);
  if (!$ok) return new WP_REST_Response(['status' => 'error', 'message' => 'Delete failed'], 500);
  return new WP_REST_Response(['status' => 'success'], 200);
}

function bp_rest_admin_promo_codes_duplicate(WP_REST_Request $req) {
  $id = (int)$req['id'];
  if ($id <= 0) return new WP_REST_Response(['status' => 'error', 'message' => 'Invalid id'], 400);
  $row = BP_PromoCodeModel::find($id);
  if (!$row) return new WP_REST_Response(['status' => 'error', 'message' => 'Not found'], 404);

  $base = strtoupper((string)($row['code'] ?? ''));
  if ($base === '') $base = 'PROMO';
  $new = $base . '-COPY';
  $i = 2;
  while (BP_PromoCodeModel::find_by_code($new)) {
    $new = $base . "-COPY{$i}";
    $i++;
    if ($i > 50) break;
  }

  $newId = BP_PromoCodeModel::save([
    'code' => $new,
    'type' => $row['type'] ?? 'percent',
    'amount' => $row['amount'] ?? 0,
    'starts_at' => $row['starts_at'] ?? null,
    'ends_at' => $row['ends_at'] ?? null,
    'max_uses' => $row['max_uses'] ?? null,
    'min_total' => $row['min_total'] ?? null,
    'is_active' => 1,
  ]);

  $created = $newId ? BP_PromoCodeModel::find($newId) : null;
  if (!$created) return new WP_REST_Response(['status' => 'error', 'message' => 'Duplicate failed'], 500);
  return new WP_REST_Response(['status' => 'success', 'data' => $created], 201);
}

function bp_rest_admin_audit_logs_list(WP_REST_Request $req) {
  $args = [
    'page' => absint($req->get_param('page') ?? 1),
    'per_page' => absint($req->get_param('per_page') ?? 50),
    'q' => sanitize_text_field($req->get_param('q') ?? ''),
    'event' => sanitize_text_field($req->get_param('event') ?? ''),
    'actor_type' => sanitize_text_field($req->get_param('actor_type') ?? ''),
    'actor_wp_user_id' => absint($req->get_param('actor_wp_user_id') ?? 0),
    'booking_id' => absint($req->get_param('booking_id') ?? 0),
    'customer_id' => absint($req->get_param('customer_id') ?? 0),
    'date_from' => sanitize_text_field($req->get_param('date_from') ?? ''),
    'date_to' => sanitize_text_field($req->get_param('date_to') ?? ''),
  ];

  $data = BP_AuditModel::list_paged($args);
  return new WP_REST_Response(['status' => 'success', 'data' => $data], 200);
}

function bp_rest_admin_audit_logs_meta(WP_REST_Request $req) {
  $events = class_exists('BP_AuditModel') ? BP_AuditModel::distinct_events() : [];
  return new WP_REST_Response([
    'status' => 'success',
    'data' => [
      'events' => $events,
      'actor_types' => ['admin', 'customer', 'system'],
    ],
  ], 200);
}

function bp_rest_admin_audit_logs_clear(WP_REST_Request $req) {
  global $wpdb;
  if (!class_exists('BP_AuditModel')) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'Audit model missing'], 500);
  }
  $t = BP_AuditModel::table();
  $ok = $wpdb->query("TRUNCATE TABLE {$t}");
  if ($ok === false) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'Clear failed'], 500);
  }
  return new WP_REST_Response(['status' => 'success'], 200);
}

function bp_rest_admin_audit_logs_export(WP_REST_Request $req) {
  if (!class_exists('BP_AuditModel')) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'Audit model missing'], 500);
  }

  $limit = max(1, min(5000, absint($req->get_param('limit') ?? 2000)));
  $args = [
    'limit' => $limit,
    'q' => sanitize_text_field($req->get_param('q') ?? ''),
    'event' => sanitize_text_field($req->get_param('event') ?? ''),
    'actor_type' => sanitize_text_field($req->get_param('actor_type') ?? ''),
    'actor_wp_user_id' => absint($req->get_param('actor_wp_user_id') ?? 0),
    'booking_id' => absint($req->get_param('booking_id') ?? 0),
    'customer_id' => absint($req->get_param('customer_id') ?? 0),
    'date_from' => sanitize_text_field($req->get_param('date_from') ?? ''),
    'date_to' => sanitize_text_field($req->get_param('date_to') ?? ''),
  ];

  $rows = BP_AuditModel::list($args);

  $fh = fopen('php://temp', 'w+');
  if (!$fh) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'Export failed'], 500);
  }

  fputcsv($fh, ['id','created_at','event','actor_type','actor','actor_ip','booking_id','customer_id','meta']);
  foreach ($rows as $r) {
    $actor = $r['actor_wp_display_name']
      ?: ($r['customer_first_name'] || $r['customer_last_name'] ? trim(($r['customer_first_name'] ?? '') . ' ' . ($r['customer_last_name'] ?? '')) : '')
      ?: ($r['actor_type'] ?? '');

    fputcsv($fh, [
      (string)($r['id'] ?? ''),
      (string)($r['created_at'] ?? ''),
      (string)($r['event'] ?? ''),
      (string)($r['actor_type'] ?? ''),
      (string)$actor,
      (string)($r['actor_ip'] ?? ''),
      (string)($r['booking_id'] ?? ''),
      (string)($r['customer_id'] ?? ''),
      (string)($r['meta'] ?? ''),
    ]);
  }

  rewind($fh);
  $csv = stream_get_contents($fh);
  fclose($fh);

  $resp = new WP_REST_Response($csv, 200);
  $filename = 'bookpoint-audit-log-' . gmdate('Ymd-His') . '.csv';
  $resp->header('Content-Type', 'text/csv; charset=utf-8');
  $resp->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
  return $resp;
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
    if (class_exists('BP_AuditHelper')) {
      BP_AuditHelper::log('tools_sync_relations', ['actor_type' => 'admin', 'meta' => $result]);
    }
    return new WP_REST_Response(['status' => 'success', 'message' => 'Relations synced', 'data' => $result], 200);
  }

  if ($action === 'generate_demo') {
    if (!class_exists('BP_DemoHelper')) {
      return new WP_REST_Response(['status' => 'error', 'message' => 'Demo helper missing'], 500);
    }
    $body = $req->get_json_params() ?: [];
    $services = max(0, min(50, absint($body['services'] ?? 3)));
    $agents = max(0, min(50, absint($body['agents'] ?? 3)));
    $customers = max(0, min(200, absint($body['customers'] ?? 5)));
    $bookings = max(0, min(500, absint($body['bookings'] ?? 10)));
    $result = BP_DemoHelper::generate($services, $agents, $customers, $bookings);
    if (class_exists('BP_AuditHelper')) {
      BP_AuditHelper::log('tools_demo_generated', ['actor_type' => 'admin', 'meta' => $result]);
    }
    return new WP_REST_Response(['status' => 'success', 'message' => 'Demo data generated', 'data' => $result], 200);
  }

  if ($action === 'reset_cache') {
    if (function_exists('wp_cache_flush')) {
      wp_cache_flush();
    }
    if (class_exists('BP_AuditHelper')) {
      BP_AuditHelper::log('tools_cache_reset', ['actor_type' => 'admin']);
    }
    return new WP_REST_Response(['status' => 'success', 'message' => 'Cache cleared'], 200);
  }

  if ($action === 'run_migrations') {
    if (!class_exists('BP_MigrationsHelper')) {
      return new WP_REST_Response(['status' => 'error', 'message' => 'Migrations helper missing'], 500);
    }
    BP_MigrationsHelper::create_tables();
    if (class_exists('BP_Locations_Migrations_Helper')) {
      BP_Locations_Migrations_Helper::ensure_tables();
    }
    if (class_exists('BP_AuditHelper')) {
      BP_AuditHelper::log('tools_migrations_run', ['actor_type' => 'admin']);
    }
    return new WP_REST_Response(['status' => 'success', 'message' => 'Migrations ran'], 200);
  }

  if ($action === 'email_test') {
    $body = $req->get_json_params() ?: [];
    $to = sanitize_email($body['to'] ?? get_option('admin_email'));
    if (!$to || !is_email($to)) {
      return new WP_REST_Response(['status' => 'error', 'message' => 'Invalid email'], 400);
    }

    $ok = false;
    if (class_exists('BP_EmailHelper')) {
      $ok = BP_EmailHelper::send($to, 'BookPoint Test Email', '<p>This is a test email from BookPoint.</p>');
    } else {
      $ok = wp_mail($to, 'BookPoint Test Email', 'This is a test email from BookPoint.');
    }

    if (class_exists('BP_AuditHelper')) {
      BP_AuditHelper::log('tools_email_test', ['actor_type' => 'admin', 'meta' => ['to' => $to, 'ok' => $ok]]);
    }

    return new WP_REST_Response(['status' => 'success', 'message' => ($ok ? 'Test email sent' : 'Test email failed'), 'data' => ['ok' => (bool)$ok, 'to' => $to]], 200);
  }

  if ($action === 'webhook_test') {
    $body = $req->get_json_params() ?: [];
    $event = sanitize_text_field($body['event'] ?? 'booking_created');

    if (!class_exists('BP_WebhookHelper')) {
      return new WP_REST_Response(['status' => 'error', 'message' => 'Webhook helper missing'], 500);
    }

    $payload = [
      'booking_id' => 999999,
      'status' => 'test',
      'start_datetime' => current_time('mysql'),
      'end_datetime' => current_time('mysql'),
    ];

    BP_WebhookHelper::fire($event, $payload);

    if (class_exists('BP_AuditHelper')) {
      BP_AuditHelper::log('tools_webhook_test', ['actor_type' => 'admin', 'meta' => ['event' => $event]]);
    }

    return new WP_REST_Response(['status' => 'success', 'message' => 'Webhook fired', 'data' => ['event' => $event]], 200);
  }

  return new WP_REST_Response(['status' => 'error', 'message' => 'Unknown tool action'], 400);
}

function bp_rest_admin_tools_report(WP_REST_Request $req) {
  global $wpdb;

  $theme = wp_get_theme();
  $env_type = function_exists('wp_get_environment_type') ? wp_get_environment_type() : '';

  $data = [
    'generated_at' => current_time('mysql'),
    'site_url' => site_url(),
    'home_url' => home_url(),
    'wp_version' => get_bloginfo('version'),
    'php_version' => PHP_VERSION,
    'mysql_version' => method_exists($wpdb, 'db_version') ? $wpdb->db_version() : '',
    'environment' => $env_type,
    'timezone' => wp_timezone_string(),
    'locale' => get_locale(),
    'plugin_version' => class_exists('BP_Plugin') ? BP_Plugin::VERSION : '',
    'db_version' => (string)get_option('BP_db_version', ''),
    'theme' => [
      'name' => $theme ? $theme->get('Name') : '',
      'version' => $theme ? $theme->get('Version') : '',
    ],
    'rest_url' => esc_url_raw(rest_url('bp/v1')),
  ];

  // Inline table status (same as tools/status, but report should be self-contained)
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
  foreach ($tables as $t) {
    $full = $wpdb->prefix . $t;
    $exists[$t] = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $full)) === $full);
  }
  $data['tables'] = $exists;

  return new WP_REST_Response(['status' => 'success', 'data' => $data], 200);
}

function bp_rest_admin_tools_export_settings(WP_REST_Request $req) {
  global $wpdb;

  $table = $wpdb->prefix . 'bp_settings';

  $rows = $wpdb->get_results("SELECT setting_key, setting_value FROM {$table}", ARRAY_A) ?: [];
  $settings = [];
  foreach ($rows as $r) {
    $settings[(string)$r['setting_key']] = maybe_unserialize($r['setting_value']);
  }

  $payload = [
    'plugin' => 'bookpoint',
    'exported_at' => current_time('mysql'),
    'bp_settings' => $settings,
    'wp_options' => [
      'bp_settings' => get_option('bp_settings', []),
      'bp_booking_form_design' => get_option('bp_booking_form_design', null),
    ],
    'options' => [
      'bp_remove_data_on_uninstall' => (int)get_option('bp_remove_data_on_uninstall', 0),
    ],
  ];

  $resp = new WP_REST_Response(wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 200);
  $filename = 'bookpoint-settings-' . gmdate('Y-m-d') . '.json';
  $resp->header('Content-Type', 'application/json; charset=UTF-8');
  $resp->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
  return $resp;
}

function bp_rest_admin_tools_import_settings(WP_REST_Request $req) {
  $data = $req->get_json_params();
  if (!is_array($data) || ($data['plugin'] ?? '') !== 'bookpoint') {
    return new WP_REST_Response(['status' => 'error', 'message' => 'Invalid file'], 400);
  }

  $settings = $data['bp_settings'] ?? null;
  if (!is_array($settings)) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'Missing settings'], 400);
  }

  if (!class_exists('BP_SettingsHelper')) {
    return new WP_REST_Response(['status' => 'error', 'message' => 'Settings helper missing'], 500);
  }

  $allowed_prefixes = ['bp_', 'payments_', 'stripe_', 'webhooks_', 'emails_', 'tpl_', 'booking_', 'portal_'];
  $applied = 0;
  foreach ($settings as $k => $v) {
    $k = (string)$k;
    $ok = false;
    foreach ($allowed_prefixes as $p) {
      if (strpos($k, $p) === 0) { $ok = true; break; }
    }
    if (!$ok) continue;

    BP_SettingsHelper::set($k, $v);
    $applied++;
  }

  $wp_options = $data['wp_options'] ?? null;
  if (is_array($wp_options)) {
    if (isset($wp_options['bp_settings']) && is_array($wp_options['bp_settings'])) {
      BP_SettingsHelper::set_all($wp_options['bp_settings']);
    }
    if (array_key_exists('bp_booking_form_design', $wp_options) && is_array($wp_options['bp_booking_form_design'])) {
      update_option('bp_booking_form_design', $wp_options['bp_booking_form_design'], false);
    }
  }

  if (isset($data['options']['bp_remove_data_on_uninstall'])) {
    update_option('bp_remove_data_on_uninstall', (int)$data['options']['bp_remove_data_on_uninstall'], false);
  }

  if (class_exists('BP_AuditHelper')) {
    BP_AuditHelper::log('tools_settings_imported', ['actor_type' => 'admin', 'meta' => ['applied' => $applied]]);
  }

  return new WP_REST_Response(['status' => 'success', 'message' => 'Settings imported', 'data' => ['applied' => $applied]], 200);
}
