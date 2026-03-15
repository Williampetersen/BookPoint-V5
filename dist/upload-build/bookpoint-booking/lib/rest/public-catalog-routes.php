<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {

  // Categories list (enabled only - if you have status column; otherwise all)
  register_rest_route('pointly-booking/v1', '/public/categories', [
    'methods'  => 'GET',
    'callback' => 'pointlybooking_rest_public_categories',
    'permission_callback' => '__return_true',
  ]);

  // Services (optional filter by category_id)
  register_rest_route('pointly-booking/v1', '/public/services', [
    'methods'  => 'GET',
    'callback' => 'pointlybooking_rest_public_services',
    'permission_callback' => '__return_true',
  ]);

  // Extras (filter by service_id)
  register_rest_route('pointly-booking/v1', '/public/extras', [
    'methods'  => 'GET',
    'callback' => 'pointlybooking_rest_public_extras',
    'permission_callback' => '__return_true',
  ]);

  // Agents (filter by service_id)
  register_rest_route('pointly-booking/v1', '/public/agents', [
    'methods'  => 'GET',
    'callback' => 'pointlybooking_rest_public_agents',
    'permission_callback' => '__return_true',
  ]);
});

function pointlybooking_public_img_url($id, $size = 'medium') {
  $id = (int)$id;
  if ($id <= 0) return '';
  $u = wp_get_attachment_image_url($id, $size);
  return $u ? $u : '';
}

function pointlybooking_public_catalog_quote_table(string $table): string {
  return '`' . str_replace('`', '``', $table) . '`';
}

function pointlybooking_rest_public_categories() {
  global $wpdb;
  $t = $wpdb->prefix . 'pointlybooking_categories';
  if (!preg_match('/^[A-Za-z0-9_]+$/', $t)) {
    return new WP_REST_Response(['status' => 'success', 'data' => []], 200);
  }

  $quoted_table = pointlybooking_public_catalog_quote_table($t);
  $rows = $wpdb->get_results(
    "SELECT id,name,image_id,sort_order FROM {$quoted_table} ORDER BY sort_order ASC, id DESC",
    ARRAY_A
  ) ?: [];
  foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['image_id'] = (int)($r['image_id'] ?? 0);
    $r['image_url'] = pointlybooking_public_img_url($r['image_id'], 'medium');
    $r['sort_order'] = (int)($r['sort_order'] ?? 0);
  }
  return new WP_REST_Response(['status' => 'success', 'data' => $rows], 200);
}

function pointlybooking_rest_public_services(WP_REST_Request $req) {
  global $wpdb;
  $t = $wpdb->prefix . 'pointlybooking_services';
  $t_rel = $wpdb->prefix . 'pointlybooking_service_categories';
  if (!preg_match('/^[A-Za-z0-9_]+$/', $t) || !preg_match('/^[A-Za-z0-9_]+$/', $t_rel)) {
    return new WP_REST_Response(['status' => 'success', 'data' => []], 200);
  }

  $category_id = (int)($req->get_param('category_id') ?? 0);
  $quoted_services = pointlybooking_public_catalog_quote_table($t);
  $quoted_rel = pointlybooking_public_catalog_quote_table($t_rel);

  $cols = $wpdb->get_col("SHOW COLUMNS FROM {$quoted_services}") ?: [];
  $has_is_active = in_array('is_active', $cols, true);
  $has_category_col = in_array('category_id', $cols, true);
  $has_price_cents = in_array('price_cents', $cols, true);
  $has_duration_minutes = in_array('duration_minutes', $cols, true);
  $has_buffer_before_minutes = in_array('buffer_before_minutes', $cols, true);
  $has_buffer_after_minutes = in_array('buffer_after_minutes', $cols, true);
  $has_buffer_before = in_array('buffer_before', $cols, true);
  $has_buffer_after = in_array('buffer_after', $cols, true);

  $rel_exists = (string)$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t_rel)) === $t_rel;

  $has_rel = $rel_exists ? (int)$wpdb->get_var("SELECT COUNT(*) FROM {$quoted_rel}") : 0;
  if ($category_id > 0 && $has_rel === 0 && !$has_category_col) {
    $category_id = 0;
  }

  if ($category_id > 0) {
    $rows = [];

    if ($rel_exists) {
      if ($has_is_active) {
        $rows = $wpdb->get_results(
          $wpdb->prepare(
            "SELECT s.*
            FROM {$quoted_services} s
            INNER JOIN {$quoted_rel} sc ON sc.service_id=s.id
            WHERE sc.category_id=%d AND s.is_active=1
            ORDER BY s.id DESC",
            $category_id
          ),
          ARRAY_A
        ) ?: [];
      } else {
        $rows = $wpdb->get_results(
          $wpdb->prepare(
            "SELECT s.*
            FROM {$quoted_services} s
            INNER JOIN {$quoted_rel} sc ON sc.service_id=s.id
            WHERE sc.category_id=%d
            ORDER BY s.id DESC",
            $category_id
          ),
          ARRAY_A
        ) ?: [];
      }
    }

    if (empty($rows) && $has_category_col) {
      if ($has_is_active) {
        $rows = $wpdb->get_results(
          $wpdb->prepare(
            "SELECT *
            FROM {$quoted_services}
            WHERE category_id=%d AND is_active=1
            ORDER BY id DESC",
            $category_id
          ),
          ARRAY_A
        ) ?: [];
      } else {
        $rows = $wpdb->get_results(
          $wpdb->prepare(
            "SELECT *
            FROM {$quoted_services}
            WHERE category_id=%d
            ORDER BY id DESC",
            $category_id
          ),
          ARRAY_A
        ) ?: [];
      }
    }
  } else {
    if ($has_is_active) {
      $rows = $wpdb->get_results(
        "SELECT * FROM {$quoted_services} WHERE is_active=1 ORDER BY id DESC",
        ARRAY_A
      ) ?: [];
    } else {
      $rows = $wpdb->get_results(
        "SELECT * FROM {$quoted_services} ORDER BY id DESC",
        ARRAY_A
      ) ?: [];
    }
  }

  foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['duration'] = $has_duration_minutes
      ? (int)($r['duration_minutes'] ?? 30)
      : (int)($r['duration'] ?? 30);

    $r['price'] = $has_price_cents
      ? ((int)($r['price_cents'] ?? 0)) / 100
      : (float)($r['price'] ?? 0);

    $r['image_id'] = (int)($r['image_id'] ?? 0);
    $r['image_url'] = pointlybooking_public_img_url($r['image_id'], 'medium');
    $r['sort_order'] = (int)($r['sort_order'] ?? 0);
    $r['buffer_before'] = $has_buffer_before_minutes
      ? (int)($r['buffer_before_minutes'] ?? 0)
      : (int)($r['buffer_before'] ?? 0);
    $r['buffer_after'] = $has_buffer_after_minutes
      ? (int)($r['buffer_after_minutes'] ?? 0)
      : (int)($r['buffer_after'] ?? 0);
    $r['capacity'] = (int)($r['capacity'] ?? 1);
  }

  return new WP_REST_Response(['status' => 'success', 'data' => $rows], 200);
}

function pointlybooking_extras_table_public() {
  // Change this if your extras table name is different.
  return 'pointlybooking_service_extras';
}

function pointlybooking_rest_public_extras(WP_REST_Request $req) {
  global $wpdb;
  $service_id = (int)($req->get_param('service_id') ?? 0);
  if ($service_id <= 0) return new WP_REST_Response(['status' => 'success', 'data' => []], 200);

  $t = pointlybooking_table('service_extras');
  $t_rel = pointlybooking_table('extra_services');
  if (!preg_match('/^[A-Za-z0-9_]+$/', $t) || !preg_match('/^[A-Za-z0-9_]+$/', $t_rel)) {
    return new WP_REST_Response(['status' => 'success', 'data' => []], 200);
  }

  $quoted_extras = pointlybooking_public_catalog_quote_table($t);
  $quoted_rel = pointlybooking_public_catalog_quote_table($t_rel);

  $rows = $wpdb->get_results(
    $wpdb->prepare(
      "SELECT e.*
      FROM {$quoted_extras} e
      INNER JOIN {$quoted_rel} es ON es.extra_id=e.id
      WHERE es.service_id=%d
      ORDER BY e.sort_order ASC, e.id DESC",
      $service_id
    ),
    ARRAY_A
  ) ?: [];

  foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['price'] = (float)($r['price'] ?? 0);
    $r['image_id'] = (int)($r['image_id'] ?? 0);
    $r['image_url'] = pointlybooking_public_img_url($r['image_id'], 'medium');
    $r['sort_order'] = (int)($r['sort_order'] ?? 0);
  }

  return new WP_REST_Response(['status' => 'success', 'data' => $rows], 200);
}

function pointlybooking_rest_public_agents(WP_REST_Request $req) {
  global $wpdb;
  $service_id = (int)($req->get_param('service_id') ?? 0);
  if ($service_id <= 0) return new WP_REST_Response(['status' => 'success', 'data' => []], 200);

  $t = $wpdb->prefix . 'pointlybooking_agents';
  $t_rel = $wpdb->prefix . 'pointlybooking_agent_services';
  if (!preg_match('/^[A-Za-z0-9_]+$/', $t) || !preg_match('/^[A-Za-z0-9_]+$/', $t_rel)) {
    return new WP_REST_Response(['status' => 'success', 'data' => []], 200);
  }

  $quoted_agents = pointlybooking_public_catalog_quote_table($t);
  $quoted_rel = pointlybooking_public_catalog_quote_table($t_rel);

  $cols = $wpdb->get_col("SHOW COLUMNS FROM {$quoted_agents}") ?: [];
  $has_is_active = in_array('is_active', $cols, true);
  $has_service_col = in_array('service_id', $cols, true);
  $has_name_col = in_array('name', $cols, true);
  $has_first_name = in_array('first_name', $cols, true);
  $has_last_name = in_array('last_name', $cols, true);
  $rel_exists = (string)$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t_rel)) === $t_rel;

  $rows = [];

  if ($rel_exists) {
    if ($has_is_active) {
      $rows = $wpdb->get_results(
        $wpdb->prepare(
          "SELECT a.*
           FROM {$quoted_agents} a
           INNER JOIN {$quoted_rel} r ON r.agent_id=a.id
           WHERE r.service_id=%d AND a.is_active=1
           ORDER BY a.id DESC",
          $service_id
        ),
        ARRAY_A
      ) ?: [];
    } else {
      $rows = $wpdb->get_results(
        $wpdb->prepare(
          "SELECT a.*
           FROM {$quoted_agents} a
           INNER JOIN {$quoted_rel} r ON r.agent_id=a.id
           WHERE r.service_id=%d
           ORDER BY a.id DESC",
          $service_id
        ),
        ARRAY_A
      ) ?: [];
    }
  }

  if (empty($rows) && $has_service_col) {
    if ($has_is_active) {
      $rows = $wpdb->get_results(
        $wpdb->prepare(
          "SELECT *
           FROM {$quoted_agents}
           WHERE service_id=%d AND is_active=1
           ORDER BY id DESC",
          $service_id
        ),
        ARRAY_A
      ) ?: [];
    } else {
      $rows = $wpdb->get_results(
        $wpdb->prepare(
          "SELECT *
           FROM {$quoted_agents}
           WHERE service_id=%d
           ORDER BY id DESC",
          $service_id
        ),
        ARRAY_A
      ) ?: [];
    }
  }

  if (empty($rows) && !$has_service_col) {
    if ($has_is_active) {
      $rows = $wpdb->get_results(
        "SELECT * FROM {$quoted_agents} WHERE is_active=1 ORDER BY id DESC",
        ARRAY_A
      ) ?: [];
    } else {
      $rows = $wpdb->get_results(
        "SELECT * FROM {$quoted_agents} ORDER BY id DESC",
        ARRAY_A
      ) ?: [];
    }
  }

  foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['image_id'] = (int)($r['image_id'] ?? 0);
    $r['image_url'] = pointlybooking_public_img_url($r['image_id'], 'medium');

    if (!isset($r['name']) || $r['name'] === '') {
      if ($has_name_col) {
        $r['name'] = (string)($r['name'] ?? '');
      } else {
        $first = $has_first_name ? (string)($r['first_name'] ?? '') : '';
        $last = $has_last_name ? (string)($r['last_name'] ?? '') : '';
        $name = trim($first . ' ' . $last);
        $r['name'] = $name !== '' ? $name : ('#' . (int)$r['id']);
      }
    }
  }

  return new WP_REST_Response(['status' => 'success', 'data' => $rows], 200);
}
