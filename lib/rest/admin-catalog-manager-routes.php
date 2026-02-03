<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {

  // ---------- Catalog CRUD ----------
  register_rest_route('bp/v1', '/admin/categories', [
    ['methods'=>'GET', 'callback'=>'bp_rest_admin_categories_list', 'permission_callback'=>'bp_rest_can_manage_catalog'],
    ['methods'=>'POST','callback'=>'bp_rest_admin_categories_create','permission_callback'=>'bp_rest_can_manage_catalog'],
  ]);
  register_rest_route('bp/v1', '/admin/categories/(?P<id>\d+)', [
    ['methods'=>'GET',  'callback'=>'bp_rest_admin_categories_get',  'permission_callback'=>'bp_rest_can_manage_catalog'],
    ['methods'=>'PATCH','callback'=>'bp_rest_admin_categories_patch','permission_callback'=>'bp_rest_can_manage_catalog'],
    ['methods'=>'DELETE','callback'=>'bp_rest_admin_categories_delete','permission_callback'=>'bp_rest_can_manage_catalog'],
  ]);

  register_rest_route('bp/v1', '/admin/services', [
    ['methods'=>'POST','callback'=>'bp_rest_admin_services_create','permission_callback'=>'bp_rest_can_manage_catalog'],
  ]);
  register_rest_route('bp/v1', '/admin/services/(?P<id>\d+)', [
    ['methods'=>'GET','callback'=>'bp_rest_admin_services_get','permission_callback'=>'bp_rest_can_manage_catalog'],
    ['methods'=>'PATCH','callback'=>'bp_rest_admin_services_patch','permission_callback'=>'bp_rest_can_manage_catalog'],
    ['methods'=>'DELETE','callback'=>'bp_rest_admin_services_delete','permission_callback'=>'bp_rest_can_manage_catalog'],
  ]);

  // ⚠️ If your extras table is not bp_service_extras, change only the TABLE NAME in helpers below.
  register_rest_route('bp/v1', '/admin/extras', [
    ['methods'=>'GET', 'callback'=>'bp_rest_admin_extras_list', 'permission_callback'=>'bp_rest_can_manage_catalog'],
    ['methods'=>'POST','callback'=>'bp_rest_admin_extras_create','permission_callback'=>'bp_rest_can_manage_catalog'],
  ]);
  register_rest_route('bp/v1', '/admin/extras/(?P<id>\d+)', [
    ['methods'=>'GET',  'callback'=>'bp_rest_admin_extras_get',  'permission_callback'=>'bp_rest_can_manage_catalog'],
    ['methods'=>'PATCH','callback'=>'bp_rest_admin_extras_patch','permission_callback'=>'bp_rest_can_manage_catalog'],
    ['methods'=>'DELETE','callback'=>'bp_rest_admin_extras_delete','permission_callback'=>'bp_rest_can_manage_catalog'],
  ]);

  register_rest_route('bp/v1', '/admin/agents', [
    // you already have GET /admin/agents in A2; keep it or use this one (this returns image too)
    ['methods'=>'GET', 'callback'=>'bp_rest_admin_agents_list_full', 'permission_callback'=>'bp_rest_can_manage_catalog'],
    ['methods'=>'POST','callback'=>'bp_rest_admin_agents_create', 'permission_callback'=>'bp_rest_can_manage_catalog'],
  ]);
  register_rest_route('bp/v1', '/admin/agents-full', [
    ['methods'=>'GET', 'callback'=>'bp_rest_admin_agents_list_full', 'permission_callback'=>'bp_rest_can_manage_catalog'],
  ]);
  register_rest_route('bp/v1', '/admin/agents/(?P<id>\d+)', [
    ['methods'=>'GET','callback'=>'bp_rest_admin_agents_get', 'permission_callback'=>'bp_rest_can_manage_catalog'],
    ['methods'=>'PATCH','callback'=>'bp_rest_admin_agents_patch', 'permission_callback'=>'bp_rest_can_manage_catalog'],
    ['methods'=>'DELETE','callback'=>'bp_rest_admin_agents_delete','permission_callback'=>'bp_rest_can_manage_catalog'],
  ]);

  // ---------- Relations ----------
  register_rest_route('bp/v1', '/admin/services/(?P<id>\d+)/categories', [
    ['methods'=>'PUT','callback'=>'bp_rest_admin_service_set_categories','permission_callback'=>'bp_rest_can_manage_catalog'],
    ['methods'=>'GET','callback'=>'bp_rest_admin_service_get_categories','permission_callback'=>'bp_rest_can_manage_catalog'],
  ]);

  register_rest_route('bp/v1', '/admin/extras/(?P<id>\d+)/services', [
    ['methods'=>'PUT','callback'=>'bp_rest_admin_extra_set_services','permission_callback'=>'bp_rest_can_manage_catalog'],
    ['methods'=>'GET','callback'=>'bp_rest_admin_extra_get_services','permission_callback'=>'bp_rest_can_manage_catalog'],
  ]);

  register_rest_route('bp/v1', '/admin/agents/(?P<id>\d+)/services', [
    ['methods'=>'PUT','callback'=>'bp_rest_admin_agent_set_services','permission_callback'=>'bp_rest_can_manage_catalog'],
    ['methods'=>'GET','callback'=>'bp_rest_admin_agent_get_services','permission_callback'=>'bp_rest_can_manage_catalog'],
  ]);
});

function bp_rest_can_manage_catalog() {
  return current_user_can('bp_manage_services')
    || current_user_can('bp_manage_agents')
    || current_user_can('bp_manage_settings')
    || current_user_can('manage_options');
}

// ---------- Helpers ----------
function bp_img_url($image_id, $size = 'thumbnail') {
  $id = (int)$image_id;
  if ($id <= 0) return '';
  $url = wp_get_attachment_image_url($id, $size);
  return $url ? $url : '';
}

function bp_table_columns(string $table): array {
  static $cache = [];
  if (isset($cache[$table])) return $cache[$table];

  global $wpdb;
  $cols = $wpdb->get_col("DESC {$table}", 0);
  if (!is_array($cols)) $cols = [];
  $cache[$table] = $cols;
  return $cols;
}

function bp_services_schema(): array {
  static $schema = null;
  if (is_array($schema)) return $schema;

  global $wpdb;
  $t = $wpdb->prefix . 'bp_services';
  $cols = bp_table_columns($t);
  $has = function (string $col) use ($cols): bool {
    return in_array($col, $cols, true);
  };

  $schema = [
    'duration_minutes' => $has('duration_minutes'),
    'duration' => $has('duration'),
    'price_cents' => $has('price_cents'),
    'price' => $has('price'),
    'currency' => $has('currency'),
    'is_active' => $has('is_active'),
    'created_at' => $has('created_at'),
    'updated_at' => $has('updated_at'),
    'description' => $has('description'),
    'image_id' => $has('image_id'),
    'sort_order' => $has('sort_order'),
    'buffer_before' => $has('buffer_before'),
    'buffer_after' => $has('buffer_after'),
    'buffer_before_minutes' => $has('buffer_before_minutes'),
    'buffer_after_minutes' => $has('buffer_after_minutes'),
    'capacity' => $has('capacity'),
  ];

  return $schema;
}

function bp_clean_int_array($arr) {
  if (!is_array($arr)) return [];
  $out = [];
  foreach ($arr as $v) {
    $i = (int)$v;
    if ($i > 0) $out[] = $i;
  }
  $out = array_values(array_unique($out));
  return $out;
}

function bp_bool01($v) {
  return !empty($v) ? 1 : 0;
}

function bp_table_has_col(string $table, string $col) : bool {
  // Defensive: this plugin has had multiple schema iterations across installs.
  // Only read/write optional columns if they exist.
  static $cache = [];
  $key = $table . '|' . $col;
  if (array_key_exists($key, $cache)) return (bool)$cache[$key];

  global $wpdb;
  $exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $col));
  $cache[$key] = !empty($exists);
  return (bool)$cache[$key];
}

// ---------- CATEGORIES ----------
function bp_rest_admin_categories_list(WP_REST_Request $req) {
  global $wpdb;
  $t = $wpdb->prefix . 'bp_categories';
  $t_rel = $wpdb->prefix . 'bp_service_categories';
  $rows = $wpdb->get_results("SELECT c.*, COUNT(r.service_id) AS services_count FROM {$t} c LEFT JOIN {$t_rel} r ON r.category_id = c.id GROUP BY c.id ORDER BY c.sort_order ASC, c.id DESC", ARRAY_A) ?: [];

  foreach ($rows as &$r) {
    $r['image_id'] = (int)($r['image_id'] ?? 0);
    $r['image_url'] = bp_img_url($r['image_id'], 'medium');
    $r['sort_order'] = (int)($r['sort_order'] ?? 0);
    $r['services_count'] = (int)($r['services_count'] ?? 0);
    // Some installs have is_active/description columns (legacy screens rely on them).
    $r['is_active'] = isset($r['is_active']) ? (int)$r['is_active'] : 1;
    $r['description'] = (string)($r['description'] ?? '');
  }
  return new WP_REST_Response(['status'=>'success','data'=>$rows], 200);
}

function bp_rest_admin_categories_get(WP_REST_Request $req) {
  global $wpdb;
  $id = (int)$req['id'];
  if ($id <= 0) return new WP_REST_Response(['status'=>'error','message'=>'Invalid id'], 400);

  $t = $wpdb->prefix . 'bp_categories';
  $t_rel = $wpdb->prefix . 'bp_service_categories';

  $row = $wpdb->get_row(
    $wpdb->prepare(
      "SELECT c.*, COUNT(r.service_id) AS services_count
       FROM {$t} c
       LEFT JOIN {$t_rel} r ON r.category_id = c.id
       WHERE c.id=%d
       GROUP BY c.id",
      $id
    ),
    ARRAY_A
  );

  if (!$row) return new WP_REST_Response(['status'=>'error','message'=>'Category not found'], 404);

  $row['image_id'] = (int)($row['image_id'] ?? 0);
  $row['image_url'] = bp_img_url($row['image_id'], 'medium');
  $row['sort_order'] = (int)($row['sort_order'] ?? 0);
  $row['services_count'] = (int)($row['services_count'] ?? 0);
  $row['is_active'] = isset($row['is_active']) ? (int)$row['is_active'] : 1;
  $row['description'] = (string)($row['description'] ?? '');

  return new WP_REST_Response(['status'=>'success','data'=>$row], 200);
}

function bp_rest_admin_categories_create(WP_REST_Request $req) {
  global $wpdb;
  $t = $wpdb->prefix . 'bp_categories';
  $b = $req->get_json_params() ?: [];

  $name = sanitize_text_field($b['name'] ?? '');
  if ($name === '') return new WP_REST_Response(['status'=>'error','message'=>'Name is required'], 400);

  $description = sanitize_textarea_field($b['description'] ?? '');
  $image_id = (int)($b['image_id'] ?? 0);
  $sort_order = (int)($b['sort_order'] ?? 0);
  $is_active = isset($b['is_active']) ? bp_bool01($b['is_active']) : 1;

  $ok = $wpdb->insert($t, [
    'name'=>$name,
    'description'=>$description,
    'image_id'=>$image_id,
    'sort_order'=>$sort_order,
    'is_active'=>$is_active,
  ], ['%s','%s','%d','%d','%d']);

  if (!$ok) return new WP_REST_Response(['status'=>'error','message'=>'Insert failed'], 500);
  return new WP_REST_Response(['status'=>'success','data'=>['id'=>$wpdb->insert_id]], 200);
}

function bp_rest_admin_categories_patch(WP_REST_Request $req) {
  global $wpdb;
  $id = (int)$req['id'];
  if ($id<=0) return new WP_REST_Response(['status'=>'error','message'=>'Invalid id'], 400);

  $t = $wpdb->prefix . 'bp_categories';
  $b = $req->get_json_params() ?: [];

  $u = []; $f = [];
  if (isset($b['name'])) { $u['name']=sanitize_text_field($b['name']); $f[]='%s'; }
  if (isset($b['description'])) { $u['description']=sanitize_textarea_field($b['description']); $f[]='%s'; }
  if (isset($b['image_id'])) { $u['image_id']=(int)$b['image_id']; $f[]='%d'; }
  if (isset($b['sort_order'])) { $u['sort_order']=(int)$b['sort_order']; $f[]='%d'; }
  if (isset($b['is_active'])) { $u['is_active']=bp_bool01($b['is_active']); $f[]='%d'; }

  if (!$u) return new WP_REST_Response(['status'=>'success','data'=>['updated'=>false]], 200);
  $ok = $wpdb->update($t, $u, ['id'=>$id], $f, ['%d']);
  if ($ok === false) return new WP_REST_Response(['status'=>'error','message'=>'Update failed'], 500);

  return new WP_REST_Response(['status'=>'success','data'=>['updated'=>true]], 200);
}

function bp_rest_admin_categories_delete(WP_REST_Request $req) {
  global $wpdb;
  $id = (int)$req['id'];
  $t = $wpdb->prefix . 'bp_categories';

  $rel = $wpdb->prefix . 'bp_service_categories';
  $wpdb->delete($rel, ['category_id'=>$id], ['%d']);

  $wpdb->delete($t, ['id'=>$id], ['%d']);
  return new WP_REST_Response(['status'=>'success','data'=>['deleted'=>true]], 200);
}

// ---------- SERVICES ----------
function bp_rest_admin_services_get(WP_REST_Request $req) {
  global $wpdb;
  $id = (int)$req['id'];
  if ($id <= 0) return new WP_REST_Response(['status'=>'error','message'=>'Invalid id'], 400);

  $t = $wpdb->prefix . 'bp_services';
  $schema = bp_services_schema();
  $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id=%d", $id), ARRAY_A);

  if (!$row) return new WP_REST_Response(['status'=>'error','message'=>'Service not found'], 404);

  $duration = 30;
  if ($schema['duration_minutes']) {
    $duration = (int)($row['duration_minutes'] ?? 30);
  } elseif ($schema['duration']) {
    $duration = (int)($row['duration'] ?? 30);
  }

  $priceCents = 0;
  if ($schema['price_cents']) {
    $priceCents = (int)($row['price_cents'] ?? 0);
  } elseif ($schema['price']) {
    $priceCents = (int)round(((float)($row['price'] ?? 0)) * 100);
  }

  $bufferBefore = 0;
  $bufferAfter = 0;
  if ($schema['buffer_before_minutes']) $bufferBefore = (int)($row['buffer_before_minutes'] ?? 0);
  elseif ($schema['buffer_before']) $bufferBefore = (int)($row['buffer_before'] ?? 0);
  if ($schema['buffer_after_minutes']) $bufferAfter = (int)($row['buffer_after_minutes'] ?? 0);
  elseif ($schema['buffer_after']) $bufferAfter = (int)($row['buffer_after'] ?? 0);

  $row['image_id'] = (int)($row['image_id'] ?? 0);
  $row['image_url'] = bp_img_url($row['image_id'], 'medium');
  $row['sort_order'] = (int)($row['sort_order'] ?? 0);
  $row['duration_minutes'] = $duration;
  $row['duration'] = $duration;
  $row['price_cents'] = $priceCents;
  $row['price'] = $priceCents / 100;
  $row['buffer_before'] = $bufferBefore;
  $row['buffer_after'] = $bufferAfter;
  $row['capacity'] = (int)($row['capacity'] ?? 1);

  return new WP_REST_Response(['status'=>'success','data'=>$row], 200);
}

function bp_rest_admin_services_create(WP_REST_Request $req) {
  global $wpdb;
  $t = $wpdb->prefix . 'bp_services';
  $schema = bp_services_schema();
  $b = $req->get_json_params() ?: [];

  $name = sanitize_text_field($b['name'] ?? '');
  if ($name === '') return new WP_REST_Response(['status'=>'error','message'=>'Name is required'], 400);

  $duration = (int)($b['duration_minutes'] ?? ($b['duration'] ?? 30));
  $duration = max(5, $duration);

  $priceCents = 0;
  if (isset($b['price_cents'])) {
    $priceCents = (int)$b['price_cents'];
  } elseif (isset($b['price'])) {
    $priceCents = (int)round(((float)$b['price']) * 100);
  }
  $priceCents = max(0, $priceCents);

  $image_id = (int)($b['image_id'] ?? 0);
  $sort_order = (int)($b['sort_order'] ?? 0);

  $buffer_before = max(0, (int)($b['buffer_before_minutes'] ?? ($b['buffer_before'] ?? 0)));
  $buffer_after  = max(0, (int)($b['buffer_after_minutes'] ?? ($b['buffer_after'] ?? 0)));
  $capacity      = max(1, (int)($b['capacity'] ?? 1));

  $now = current_time('mysql');
  $insert = ['name' => $name];
  $formats = ['%s'];

  if ($schema['duration_minutes']) { $insert['duration_minutes'] = $duration; $formats[] = '%d'; }
  elseif ($schema['duration']) { $insert['duration'] = $duration; $formats[] = '%d'; }

  if ($schema['price_cents']) { $insert['price_cents'] = $priceCents; $formats[] = '%d'; }
  elseif ($schema['price']) { $insert['price'] = $priceCents / 100; $formats[] = '%f'; }

  if ($schema['currency'] && isset($b['currency'])) { $insert['currency'] = sanitize_key((string)$b['currency']); $formats[] = '%s'; }
  if ($schema['description'] && isset($b['description'])) { $insert['description'] = wp_kses_post((string)$b['description']); $formats[] = '%s'; }
  if ($schema['is_active'] && isset($b['is_active'])) { $insert['is_active'] = (int)$b['is_active']; $formats[] = '%d'; }

  if ($schema['image_id']) { $insert['image_id'] = $image_id; $formats[] = '%d'; }
  if ($schema['sort_order']) { $insert['sort_order'] = $sort_order; $formats[] = '%d'; }

  if ($schema['buffer_before_minutes']) { $insert['buffer_before_minutes'] = $buffer_before; $formats[] = '%d'; }
  elseif ($schema['buffer_before']) { $insert['buffer_before'] = $buffer_before; $formats[] = '%d'; }
  if ($schema['buffer_after_minutes']) { $insert['buffer_after_minutes'] = $buffer_after; $formats[] = '%d'; }
  elseif ($schema['buffer_after']) { $insert['buffer_after'] = $buffer_after; $formats[] = '%d'; }
  if ($schema['capacity']) { $insert['capacity'] = $capacity; $formats[] = '%d'; }

  if ($schema['created_at']) { $insert['created_at'] = $now; $formats[] = '%s'; }
  if ($schema['updated_at']) { $insert['updated_at'] = $now; $formats[] = '%s'; }

  $ok = $wpdb->insert($t, $insert, $formats);

  if (!$ok) return new WP_REST_Response(['status'=>'error','message'=>'Insert failed'], 500);
  return new WP_REST_Response(['status'=>'success','data'=>['id'=>$wpdb->insert_id]], 200);
}

function bp_rest_admin_services_patch(WP_REST_Request $req) {
  global $wpdb;
  $id = (int)$req['id'];
  if ($id<=0) return new WP_REST_Response(['status'=>'error','message'=>'Invalid id'], 400);

  $t = $wpdb->prefix . 'bp_services';
  $schema = bp_services_schema();
  $b = $req->get_json_params() ?: [];

  $u = []; $f = [];
  if (isset($b['name'])) { $u['name']=sanitize_text_field($b['name']); $f[]='%s'; }

  if (isset($b['duration_minutes']) || isset($b['duration'])) {
    $dur = isset($b['duration_minutes']) ? (int)$b['duration_minutes'] : (int)$b['duration'];
    $dur = max(5, $dur);
    if ($schema['duration_minutes']) { $u['duration_minutes'] = $dur; $f[] = '%d'; }
    elseif ($schema['duration']) { $u['duration'] = $dur; $f[] = '%d'; }
  }

  if (isset($b['price_cents']) || isset($b['price'])) {
    $pc = isset($b['price_cents']) ? (int)$b['price_cents'] : (int)round(((float)$b['price']) * 100);
    $pc = max(0, $pc);
    if ($schema['price_cents']) { $u['price_cents'] = $pc; $f[] = '%d'; }
    elseif ($schema['price']) { $u['price'] = $pc / 100; $f[] = '%f'; }
  }

  if (isset($b['description']) && $schema['description']) { $u['description'] = wp_kses_post((string)$b['description']); $f[] = '%s'; }
  if (isset($b['currency']) && $schema['currency']) { $u['currency'] = sanitize_key((string)$b['currency']); $f[] = '%s'; }
  if (isset($b['image_id']) && $schema['image_id']) { $u['image_id']=(int)$b['image_id']; $f[]='%d'; }
  if (isset($b['sort_order']) && $schema['sort_order']) { $u['sort_order']=(int)$b['sort_order']; $f[]='%d'; }
  if (isset($b['is_active']) && $schema['is_active']) { $u['is_active']=(int)$b['is_active']; $f[]='%d'; }

  if (isset($b['buffer_before_minutes']) || isset($b['buffer_before'])) {
    $bb = isset($b['buffer_before_minutes']) ? (int)$b['buffer_before_minutes'] : (int)$b['buffer_before'];
    $bb = max(0, $bb);
    if ($schema['buffer_before_minutes']) { $u['buffer_before_minutes'] = $bb; $f[] = '%d'; }
    elseif ($schema['buffer_before']) { $u['buffer_before'] = $bb; $f[] = '%d'; }
  }
  if (isset($b['buffer_after_minutes']) || isset($b['buffer_after'])) {
    $ba = isset($b['buffer_after_minutes']) ? (int)$b['buffer_after_minutes'] : (int)$b['buffer_after'];
    $ba = max(0, $ba);
    if ($schema['buffer_after_minutes']) { $u['buffer_after_minutes'] = $ba; $f[] = '%d'; }
    elseif ($schema['buffer_after']) { $u['buffer_after'] = $ba; $f[] = '%d'; }
  }
  if (isset($b['capacity']) && $schema['capacity']) { $u['capacity']=max(1,(int)$b['capacity']); $f[]='%d'; }

  if ($schema['updated_at']) { $u['updated_at'] = current_time('mysql'); $f[] = '%s'; }

  if (!$u) return new WP_REST_Response(['status'=>'success','data'=>['updated'=>false]], 200);
  $ok = $wpdb->update($t, $u, ['id'=>$id], $f, ['%d']);
  if ($ok === false) return new WP_REST_Response(['status'=>'error','message'=>'Update failed'], 500);

  return new WP_REST_Response(['status'=>'success','data'=>['updated'=>true]], 200);
}

function bp_rest_admin_services_delete(WP_REST_Request $req) {
  global $wpdb;
  $id = (int)$req['id'];
  $t = $wpdb->prefix . 'bp_services';

  $wpdb->delete($wpdb->prefix.'bp_service_categories', ['service_id'=>$id], ['%d']);
  $wpdb->delete($wpdb->prefix.'bp_agent_services', ['service_id'=>$id], ['%d']);
  $wpdb->delete($wpdb->prefix.'bp_extra_services', ['service_id'=>$id], ['%d']);

  $wpdb->delete($t, ['id'=>$id], ['%d']);
  return new WP_REST_Response(['status'=>'success','data'=>['deleted'=>true]], 200);
}

// ---------- EXTRAS ----------
function bp_extras_table() {
  return 'bp_service_extras';
}

function bp_rest_admin_extras_list(WP_REST_Request $req) {
  global $wpdb;
  $t = $wpdb->prefix . bp_extras_table();

  $rows = $wpdb->get_results("SELECT * FROM {$t} ORDER BY sort_order ASC, id DESC", ARRAY_A) ?: [];
  foreach ($rows as &$r) {
    $r['image_id'] = (int)($r['image_id'] ?? 0);
    $r['image_url'] = bp_img_url($r['image_id'], 'medium');
    $r['sort_order'] = (int)($r['sort_order'] ?? 0);
    $r['price'] = isset($r['price']) ? (float)$r['price'] : 0.0;
    if (isset($r['is_active'])) $r['is_active'] = (int)$r['is_active'];
    if (isset($r['description'])) $r['description'] = (string)($r['description'] ?? '');
  }
  return new WP_REST_Response(['status'=>'success','data'=>$rows], 200);
}

function bp_rest_admin_extras_get(WP_REST_Request $req) {
  global $wpdb;
  $id = (int)$req['id'];
  if ($id<=0) return new WP_REST_Response(['status'=>'error','message'=>'Invalid id'], 400);

  $t = $wpdb->prefix . bp_extras_table();
  $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id=%d", $id), ARRAY_A);
  if (!$row) return new WP_REST_Response(['status'=>'error','message'=>'Extra not found'], 404);

  $row['image_id'] = (int)($row['image_id'] ?? 0);
  $row['image_url'] = bp_img_url($row['image_id'], 'medium');
  $row['sort_order'] = (int)($row['sort_order'] ?? 0);
  $row['price'] = isset($row['price']) ? (float)$row['price'] : 0.0;
  if (isset($row['is_active'])) $row['is_active'] = (int)$row['is_active'];
  if (isset($row['description'])) $row['description'] = (string)($row['description'] ?? '');

  return new WP_REST_Response(['status'=>'success','data'=>$row], 200);
}

function bp_rest_admin_extras_create(WP_REST_Request $req) {
  global $wpdb;
  $t = $wpdb->prefix . bp_extras_table();
  $b = $req->get_json_params() ?: [];

  $name = sanitize_text_field($b['name'] ?? '');
  if ($name === '') return new WP_REST_Response(['status'=>'error','message'=>'Name is required'], 400);

  $price = (float)($b['price'] ?? 0);
  $image_id = (int)($b['image_id'] ?? 0);
  $sort_order = (int)($b['sort_order'] ?? 0);

  $data = [
    'name'=>$name,
    'price'=>$price,
    'image_id'=>$image_id,
    'sort_order'=>$sort_order,
  ];
  $formats = ['%s','%f','%d','%d'];

  // Optional columns (vary by install)
  if (bp_table_has_col($t, 'description')) {
    $data['description'] = sanitize_textarea_field($b['description'] ?? '');
    $formats[] = '%s';
  }
  if (bp_table_has_col($t, 'is_active') && isset($b['is_active'])) {
    $data['is_active'] = bp_bool01($b['is_active']);
    $formats[] = '%d';
  }

  $ok = $wpdb->insert($t, $data, $formats);

  if (!$ok) return new WP_REST_Response(['status'=>'error','message'=>'Insert failed'], 500);
  return new WP_REST_Response(['status'=>'success','data'=>['id'=>$wpdb->insert_id]], 200);
}

function bp_rest_admin_extras_patch(WP_REST_Request $req) {
  global $wpdb;
  $id = (int)$req['id'];
  if ($id<=0) return new WP_REST_Response(['status'=>'error','message'=>'Invalid id'], 400);

  $t = $wpdb->prefix . bp_extras_table();
  $b = $req->get_json_params() ?: [];

  $u = []; $f = [];
  if (isset($b['name'])) { $u['name']=sanitize_text_field($b['name']); $f[]='%s'; }
  if (isset($b['price'])) { $u['price']=(float)$b['price']; $f[]='%f'; }
  if (isset($b['image_id'])) { $u['image_id']=(int)$b['image_id']; $f[]='%d'; }
  if (isset($b['sort_order'])) { $u['sort_order']=(int)$b['sort_order']; $f[]='%d'; }
  if (bp_table_has_col($t, 'description') && isset($b['description'])) { $u['description']=sanitize_textarea_field($b['description']); $f[]='%s'; }
  if (bp_table_has_col($t, 'is_active') && isset($b['is_active'])) { $u['is_active']=bp_bool01($b['is_active']); $f[]='%d'; }

  if (!$u) return new WP_REST_Response(['status'=>'success','data'=>['updated'=>false]], 200);
  $ok = $wpdb->update($t, $u, ['id'=>$id], $f, ['%d']);
  if ($ok === false) return new WP_REST_Response(['status'=>'error','message'=>'Update failed'], 500);

  return new WP_REST_Response(['status'=>'success','data'=>['updated'=>true]], 200);
}

function bp_rest_admin_extras_delete(WP_REST_Request $req) {
  global $wpdb;
  $id = (int)$req['id'];
  $t = $wpdb->prefix . bp_extras_table();

  $wpdb->delete($wpdb->prefix.'bp_extra_services', ['extra_id'=>$id], ['%d']);
  $wpdb->delete($t, ['id'=>$id], ['%d']);

  return new WP_REST_Response(['status'=>'success','data'=>['deleted'=>true]], 200);
}

// ---------- AGENTS (full CRUD with image + service mapping) ----------
function bp_rest_admin_agents_list_full(WP_REST_Request $req) {
  global $wpdb;
  $t = $wpdb->prefix . 'bp_agents';
  $t_rel = $wpdb->prefix . 'bp_agent_services';
  $rows = $wpdb->get_results("SELECT a.*, COUNT(r.service_id) AS services_count FROM {$t} a LEFT JOIN {$t_rel} r ON r.agent_id = a.id GROUP BY a.id ORDER BY a.id DESC", ARRAY_A) ?: [];
  foreach ($rows as &$r) {
    $r['image_id'] = (int)($r['image_id'] ?? 0);
    $r['image_url'] = bp_img_url($r['image_id'], 'medium');
    $r['services_count'] = (int)($r['services_count'] ?? 0);
    $r['is_active'] = isset($r['is_active']) ? (int)$r['is_active'] : 1;
    $r['schedule_json'] = (string)($r['schedule_json'] ?? '');
    // Convenience display name for UI: keep legacy `name` if present, else use first/last.
    $display = trim((string)($r['first_name'] ?? '') . ' ' . (string)($r['last_name'] ?? ''));
    if ($display === '' && !empty($r['name'])) $display = (string)$r['name'];
    $r['name'] = $display;
  }
  return new WP_REST_Response(['status'=>'success','data'=>$rows], 200);
}

function bp_rest_admin_agents_get(WP_REST_Request $req) {
  global $wpdb;
  $id = (int)$req['id'];
  if ($id <= 0) return new WP_REST_Response(['status'=>'error','message'=>'Invalid id'], 400);

  $t = $wpdb->prefix . 'bp_agents';
  $t_rel = $wpdb->prefix . 'bp_agent_services';

  $row = $wpdb->get_row(
    $wpdb->prepare(
      "SELECT a.*, COUNT(r.service_id) AS services_count
       FROM {$t} a
       LEFT JOIN {$t_rel} r ON r.agent_id = a.id
       WHERE a.id=%d
       GROUP BY a.id",
      $id
    ),
    ARRAY_A
  );

  if (!$row) return new WP_REST_Response(['status'=>'error','message'=>'Agent not found'], 404);

  $row['id'] = (int)($row['id'] ?? 0);
  $row['image_id'] = (int)($row['image_id'] ?? 0);
  $row['image_url'] = bp_img_url($row['image_id'], 'medium');
  $row['services_count'] = (int)($row['services_count'] ?? 0);
  $row['is_active'] = isset($row['is_active']) ? (int)$row['is_active'] : 1;
  $row['schedule_json'] = (string)($row['schedule_json'] ?? '');

  $display = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
  if ($display === '' && !empty($row['name'])) $display = (string)$row['name'];
  $row['name'] = $display;

  return new WP_REST_Response(['status'=>'success','data'=>$row], 200);
}

function bp_rest_admin_agents_create(WP_REST_Request $req) {
  global $wpdb;
  $t = $wpdb->prefix . 'bp_agents';
  $b = $req->get_json_params() ?: [];

  $first_name = sanitize_text_field($b['first_name'] ?? '');
  $last_name  = sanitize_text_field($b['last_name'] ?? '');
  $legacy_name = sanitize_text_field($b['name'] ?? '');
  if ($first_name === '' && $last_name === '' && $legacy_name === '') {
    return new WP_REST_Response(['status'=>'error','message'=>'Name is required'], 400);
  }

  $email = sanitize_email($b['email'] ?? '');
  $phone = sanitize_text_field($b['phone'] ?? '');
  $is_active = isset($b['is_active']) ? bp_bool01($b['is_active']) : 1;
  $schedule_json = isset($b['schedule_json']) ? trim((string)$b['schedule_json']) : null;
  if ($schedule_json === '') $schedule_json = null;

  $now = current_time('mysql');

  $data = [
    'first_name' => $first_name !== '' ? $first_name : null,
    'last_name'  => $last_name !== '' ? $last_name : null,
    'email'      => $email !== '' ? $email : null,
    'phone'      => $phone !== '' ? $phone : null,
    'is_active'  => (int)$is_active,
    'schedule_json' => $schedule_json,
    'created_at' => $now,
    'updated_at' => $now,
  ];
  $formats = ['%s','%s','%s','%s','%d','%s','%s','%s'];

  // Some installs may have legacy columns.
  if (bp_table_has_col($t, 'image_id')) {
    $data['image_id'] = (int)($b['image_id'] ?? 0);
    $formats[] = '%d';
  }
  if (bp_table_has_col($t, 'name') && $legacy_name !== '') {
    $data['name'] = $legacy_name;
    $formats[] = '%s';
  }

  $ok = $wpdb->insert($t, $data, $formats);

  if (!$ok) return new WP_REST_Response(['status'=>'error','message'=>'Insert failed'], 500);
  delete_transient('bp_agents_all_all');
  delete_transient('bp_agents_all_active');
  return new WP_REST_Response(['status'=>'success','data'=>['id'=>$wpdb->insert_id]], 200);
}

function bp_rest_admin_agents_patch(WP_REST_Request $req) {
  global $wpdb;
  $id = (int)$req['id'];
  if ($id<=0) return new WP_REST_Response(['status'=>'error','message'=>'Invalid id'], 400);

  $t = $wpdb->prefix . 'bp_agents';
  $b = $req->get_json_params() ?: [];

  $u = []; $f = [];
  if (isset($b['first_name'])) { $u['first_name']=sanitize_text_field($b['first_name']); $f[]='%s'; }
  if (isset($b['last_name'])) { $u['last_name']=sanitize_text_field($b['last_name']); $f[]='%s'; }
  if (isset($b['email'])) { $u['email']=sanitize_email($b['email']); $f[]='%s'; }
  if (isset($b['phone'])) { $u['phone']=sanitize_text_field($b['phone']); $f[]='%s'; }
  if (isset($b['is_active'])) { $u['is_active']=bp_bool01($b['is_active']); $f[]='%d'; }
  if (isset($b['schedule_json'])) {
    $sj = trim((string)$b['schedule_json']);
    $u['schedule_json'] = $sj === '' ? null : $sj;
    $f[]='%s';
  }
  if (isset($b['image_id']) && bp_table_has_col($t, 'image_id')) { $u['image_id']=(int)$b['image_id']; $f[]='%d'; }
  if (isset($b['name']) && bp_table_has_col($t, 'name')) { $u['name']=sanitize_text_field($b['name']); $f[]='%s'; }

  if (bp_table_has_col($t, 'updated_at')) {
    $u['updated_at'] = current_time('mysql');
    $f[] = '%s';
  }

  if (!$u) return new WP_REST_Response(['status'=>'success','data'=>['updated'=>false]], 200);
  $ok = $wpdb->update($t, $u, ['id'=>$id], $f, ['%d']);
  if ($ok === false) return new WP_REST_Response(['status'=>'error','message'=>'Update failed'], 500);

  delete_transient('bp_agents_all_all');
  delete_transient('bp_agents_all_active');
  return new WP_REST_Response(['status'=>'success','data'=>['updated'=>true]], 200);
}

function bp_rest_admin_agents_delete(WP_REST_Request $req) {
  global $wpdb;
  $id = (int)$req['id'];
  $t = $wpdb->prefix . 'bp_agents';

  $wpdb->delete($wpdb->prefix.'bp_agent_services', ['agent_id'=>$id], ['%d']);
  $wpdb->delete($t, ['id'=>$id], ['%d']);

  delete_transient('bp_agents_all_all');
  delete_transient('bp_agents_all_active');
  return new WP_REST_Response(['status'=>'success','data'=>['deleted'=>true]], 200);
}

// ---------- RELATIONS: Service <-> Categories ----------
function bp_rest_admin_service_get_categories(WP_REST_Request $req) {
  global $wpdb;
  $service_id = (int)$req['id'];
  $t = $wpdb->prefix.'bp_service_categories';
  $ids = $wpdb->get_col($wpdb->prepare("SELECT category_id FROM {$t} WHERE service_id=%d", $service_id)) ?: [];
  $ids = array_map('intval', $ids);
  return new WP_REST_Response(['status'=>'success','data'=>$ids], 200);
}

function bp_rest_admin_service_set_categories(WP_REST_Request $req) {
  global $wpdb;
  $service_id = (int)$req['id'];
  if ($service_id<=0) return new WP_REST_Response(['status'=>'error','message'=>'Invalid id'], 400);

  $body = $req->get_json_params() ?: [];
  $category_ids = bp_clean_int_array($body['category_ids'] ?? []);

  $t = $wpdb->prefix.'bp_service_categories';
  $wpdb->delete($t, ['service_id'=>$service_id], ['%d']);
  foreach ($category_ids as $cid) {
    $wpdb->insert($t, ['service_id'=>$service_id,'category_id'=>$cid], ['%d','%d']);
  }

  return new WP_REST_Response(['status'=>'success','data'=>['saved'=>true,'category_ids'=>$category_ids]], 200);
}

// ---------- RELATIONS: Extra <-> Services ----------
function bp_rest_admin_extra_get_services(WP_REST_Request $req) {
  global $wpdb;
  $extra_id = (int)$req['id'];
  $t = $wpdb->prefix.'bp_extra_services';
  $ids = $wpdb->get_col($wpdb->prepare("SELECT service_id FROM {$t} WHERE extra_id=%d", $extra_id)) ?: [];
  $ids = array_map('intval', $ids);
  return new WP_REST_Response(['status'=>'success','data'=>$ids], 200);
}

function bp_rest_admin_extra_set_services(WP_REST_Request $req) {
  global $wpdb;
  $extra_id = (int)$req['id'];
  if ($extra_id<=0) return new WP_REST_Response(['status'=>'error','message'=>'Invalid id'], 400);

  $body = $req->get_json_params() ?: [];
  $service_ids = bp_clean_int_array($body['service_ids'] ?? []);

  $t = $wpdb->prefix.'bp_extra_services';
  $wpdb->delete($t, ['extra_id'=>$extra_id], ['%d']);
  foreach ($service_ids as $sid) {
    $wpdb->insert($t, ['extra_id'=>$extra_id,'service_id'=>$sid], ['%d','%d']);
  }

  return new WP_REST_Response(['status'=>'success','data'=>['saved'=>true,'service_ids'=>$service_ids]], 200);
}

// ---------- RELATIONS: Agent <-> Services ----------
function bp_rest_admin_agent_get_services(WP_REST_Request $req) {
  global $wpdb;
  $agent_id = (int)$req['id'];
  $t = $wpdb->prefix.'bp_agent_services';
  $ids = $wpdb->get_col($wpdb->prepare("SELECT service_id FROM {$t} WHERE agent_id=%d", $agent_id)) ?: [];
  $ids = array_map('intval', $ids);
  return new WP_REST_Response(['status'=>'success','data'=>$ids], 200);
}

function bp_rest_admin_agent_set_services(WP_REST_Request $req) {
  global $wpdb;
  $agent_id = (int)$req['id'];
  if ($agent_id<=0) return new WP_REST_Response(['status'=>'error','message'=>'Invalid id'], 400);

  $body = $req->get_json_params() ?: [];
  $service_ids = bp_clean_int_array($body['service_ids'] ?? []);

  $t = $wpdb->prefix.'bp_agent_services';
  $wpdb->delete($t, ['agent_id'=>$agent_id], ['%d']);
  foreach ($service_ids as $sid) {
    $wpdb->insert($t, ['agent_id'=>$agent_id,'service_id'=>$sid], ['%d','%d']);
  }

  return new WP_REST_Response(['status'=>'success','data'=>['saved'=>true,'service_ids'=>$service_ids]], 200);
}
