<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {

  // ---------- Catalog CRUD ----------
  register_rest_route('bp/v1', '/admin/categories', [
    ['methods'=>'GET', 'callback'=>'bp_rest_admin_categories_list', 'permission_callback'=>'bp_rest_can_manage_catalog'],
    ['methods'=>'POST','callback'=>'bp_rest_admin_categories_create','permission_callback'=>'bp_rest_can_manage_catalog'],
  ]);
  register_rest_route('bp/v1', '/admin/categories/(?P<id>\d+)', [
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
  }
  return new WP_REST_Response(['status'=>'success','data'=>$rows], 200);
}

function bp_rest_admin_categories_create(WP_REST_Request $req) {
  global $wpdb;
  $t = $wpdb->prefix . 'bp_categories';
  $b = $req->get_json_params() ?: [];

  $name = sanitize_text_field($b['name'] ?? '');
  if ($name === '') return new WP_REST_Response(['status'=>'error','message'=>'Name is required'], 400);

  $image_id = (int)($b['image_id'] ?? 0);
  $sort_order = (int)($b['sort_order'] ?? 0);

  $ok = $wpdb->insert($t, [
    'name'=>$name,
    'image_id'=>$image_id,
    'sort_order'=>$sort_order
  ], ['%s','%d','%d']);

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
  if (isset($b['image_id'])) { $u['image_id']=(int)$b['image_id']; $f[]='%d'; }
  if (isset($b['sort_order'])) { $u['sort_order']=(int)$b['sort_order']; $f[]='%d'; }

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
  $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id=%d", $id), ARRAY_A);

  if (!$row) return new WP_REST_Response(['status'=>'error','message'=>'Service not found'], 404);

  $row['image_id'] = (int)($row['image_id'] ?? 0);
  $row['image_url'] = bp_img_url($row['image_id'], 'medium');
  $row['sort_order'] = (int)($row['sort_order'] ?? 0);
  $row['duration_minutes'] = (int)($row['duration'] ?? 30);
  $row['price_cents'] = isset($row['price']) ? (int)(floatval($row['price']) * 100) : 0;
  $row['buffer_before'] = (int)($row['buffer_before'] ?? 0);
  $row['buffer_after'] = (int)($row['buffer_after'] ?? 0);
  $row['capacity'] = (int)($row['capacity'] ?? 1);

  return new WP_REST_Response(['status'=>'success','data'=>$row], 200);
}

function bp_rest_admin_services_create(WP_REST_Request $req) {
  global $wpdb;
  $t = $wpdb->prefix . 'bp_services';
  $b = $req->get_json_params() ?: [];

  $name = sanitize_text_field($b['name'] ?? '');
  if ($name === '') return new WP_REST_Response(['status'=>'error','message'=>'Name is required'], 400);

  $duration = max(5, (int)($b['duration'] ?? 30));
  $price = (float)($b['price'] ?? 0);
  $image_id = (int)($b['image_id'] ?? 0);
  $sort_order = (int)($b['sort_order'] ?? 0);

  $buffer_before = max(0, (int)($b['buffer_before'] ?? 0));
  $buffer_after  = max(0, (int)($b['buffer_after'] ?? 0));
  $capacity      = max(1, (int)($b['capacity'] ?? 1));

  $ok = $wpdb->insert($t, [
    'name'=>$name,
    'duration'=>$duration,
    'price'=>$price,
    'image_id'=>$image_id,
    'sort_order'=>$sort_order,
    'buffer_before'=>$buffer_before,
    'buffer_after'=>$buffer_after,
    'capacity'=>$capacity,
  ], ['%s','%d','%f','%d','%d','%d','%d','%d']);

  if (!$ok) return new WP_REST_Response(['status'=>'error','message'=>'Insert failed'], 500);
  return new WP_REST_Response(['status'=>'success','data'=>['id'=>$wpdb->insert_id]], 200);
}

function bp_rest_admin_services_patch(WP_REST_Request $req) {
  global $wpdb;
  $id = (int)$req['id'];
  if ($id<=0) return new WP_REST_Response(['status'=>'error','message'=>'Invalid id'], 400);

  $t = $wpdb->prefix . 'bp_services';
  $b = $req->get_json_params() ?: [];

  $u = []; $f = [];
  if (isset($b['name'])) { $u['name']=sanitize_text_field($b['name']); $f[]='%s'; }
  if (isset($b['duration_minutes'])) { $u['duration']=max(5,(int)$b['duration_minutes']); $f[]='%d'; }
  if (isset($b['duration'])) { $u['duration']=max(5,(int)$b['duration']); $f[]='%d'; }
  if (isset($b['price_cents'])) { $u['price']=floatval($b['price_cents']) / 100; $f[]='%f'; }
  if (isset($b['price'])) { $u['price']=(float)$b['price']; $f[]='%f'; }
  if (isset($b['image_id'])) { $u['image_id']=(int)$b['image_id']; $f[]='%d'; }
  if (isset($b['sort_order'])) { $u['sort_order']=(int)$b['sort_order']; $f[]='%d'; }
  if (isset($b['is_active'])) { $u['is_active']=(int)$b['is_active']; $f[]='%d'; }

  if (isset($b['buffer_before'])) { $u['buffer_before']=max(0,(int)$b['buffer_before']); $f[]='%d'; }
  if (isset($b['buffer_after']))  { $u['buffer_after']=max(0,(int)$b['buffer_after']); $f[]='%d'; }
  if (isset($b['capacity']))      { $u['capacity']=max(1,(int)$b['capacity']); $f[]='%d'; }

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
  }
  return new WP_REST_Response(['status'=>'success','data'=>$rows], 200);
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

  $ok = $wpdb->insert($t, [
    'name'=>$name,
    'price'=>$price,
    'image_id'=>$image_id,
    'sort_order'=>$sort_order,
  ], ['%s','%f','%d','%d']);

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
  }
  return new WP_REST_Response(['status'=>'success','data'=>$rows], 200);
}

function bp_rest_admin_agents_create(WP_REST_Request $req) {
  global $wpdb;
  $t = $wpdb->prefix . 'bp_agents';
  $b = $req->get_json_params() ?: [];

  $name = sanitize_text_field($b['name'] ?? '');
  if ($name === '') return new WP_REST_Response(['status'=>'error','message'=>'Name is required'], 400);

  $image_id = (int)($b['image_id'] ?? 0);

  $ok = $wpdb->insert($t, [
    'name'=>$name,
    'image_id'=>$image_id
  ], ['%s','%d']);

  if (!$ok) return new WP_REST_Response(['status'=>'error','message'=>'Insert failed'], 500);
  return new WP_REST_Response(['status'=>'success','data'=>['id'=>$wpdb->insert_id]], 200);
}

function bp_rest_admin_agents_patch(WP_REST_Request $req) {
  global $wpdb;
  $id = (int)$req['id'];
  if ($id<=0) return new WP_REST_Response(['status'=>'error','message'=>'Invalid id'], 400);

  $t = $wpdb->prefix . 'bp_agents';
  $b = $req->get_json_params() ?: [];

  $u = []; $f = [];
  if (isset($b['name'])) { $u['name']=sanitize_text_field($b['name']); $f[]='%s'; }
  if (isset($b['image_id'])) { $u['image_id']=(int)$b['image_id']; $f[]='%d'; }

  if (!$u) return new WP_REST_Response(['status'=>'success','data'=>['updated'=>false]], 200);
  $ok = $wpdb->update($t, $u, ['id'=>$id], $f, ['%d']);
  if ($ok === false) return new WP_REST_Response(['status'=>'error','message'=>'Update failed'], 500);

  return new WP_REST_Response(['status'=>'success','data'=>['updated'=>true]], 200);
}

function bp_rest_admin_agents_delete(WP_REST_Request $req) {
  global $wpdb;
  $id = (int)$req['id'];
  $t = $wpdb->prefix . 'bp_agents';

  $wpdb->delete($wpdb->prefix.'bp_agent_services', ['agent_id'=>$id], ['%d']);
  $wpdb->delete($t, ['id'=>$id], ['%d']);

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
