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

function pointlybooking_rest_public_categories() {
  global $wpdb;
  $t = $wpdb->prefix . 'pointlybooking_categories';
  $rows = $wpdb->get_results(
    pointlybooking_prepare_query_with_identifiers(
      "SELECT id,name,image_id,sort_order FROM %i ORDER BY sort_order ASC, id DESC",
      [$t]
    ),
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

  $category_id = (int)($req->get_param('category_id') ?? 0);

  $cols = $wpdb->get_col(pointlybooking_prepare_query_with_identifiers("SHOW COLUMNS FROM %i", [$t])) ?: [];
  $has_is_active = in_array('is_active', $cols, true);
  $has_category_col = in_array('category_id', $cols, true);
  $has_price_cents = in_array('price_cents', $cols, true);
  $has_duration_minutes = in_array('duration_minutes', $cols, true);
  $has_buffer_before_minutes = in_array('buffer_before_minutes', $cols, true);
  $has_buffer_after_minutes = in_array('buffer_after_minutes', $cols, true);
  $has_buffer_before = in_array('buffer_before', $cols, true);
  $has_buffer_after = in_array('buffer_after', $cols, true);

  $rel_exists = (string)$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t_rel)) === $t_rel;

  $has_rel = $rel_exists ? (int)$wpdb->get_var(pointlybooking_prepare_query_with_identifiers("SELECT COUNT(*) FROM %i", [$t_rel])) : 0;
  if ($category_id > 0 && $has_rel === 0 && !$has_category_col) {
    $category_id = 0;
  }

  if ($category_id > 0) {
    $rows = [];

    if ($rel_exists) {
      if ($has_is_active) {
        $rows = $wpdb->get_results(
          pointlybooking_prepare_query_with_identifiers(
            "SELECT s.*
            FROM %i s
            INNER JOIN %i sc ON sc.service_id=s.id
            WHERE sc.category_id=%d AND s.is_active=1
            ORDER BY s.id DESC",
            [$t, $t_rel],
            [$category_id]
          ),
          ARRAY_A
        ) ?: [];
      } else {
        $rows = $wpdb->get_results(
          pointlybooking_prepare_query_with_identifiers(
            "SELECT s.*
            FROM %i s
            INNER JOIN %i sc ON sc.service_id=s.id
            WHERE sc.category_id=%d
            ORDER BY s.id DESC",
            [$t, $t_rel],
            [$category_id]
          ),
          ARRAY_A
        ) ?: [];
      }
    }

    if (empty($rows) && $has_category_col) {
      if ($has_is_active) {
        $rows = $wpdb->get_results(
          pointlybooking_prepare_query_with_identifiers(
            "SELECT *
            FROM %i
            WHERE category_id=%d AND is_active=1
            ORDER BY id DESC",
            [$t],
            [$category_id]
          ),
          ARRAY_A
        ) ?: [];
      } else {
        $rows = $wpdb->get_results(
          pointlybooking_prepare_query_with_identifiers(
            "SELECT *
            FROM %i
            WHERE category_id=%d
            ORDER BY id DESC",
            [$t],
            [$category_id]
          ),
          ARRAY_A
        ) ?: [];
      }
    }
  } else {
    if ($has_is_active) {
      $rows = $wpdb->get_results(
        pointlybooking_prepare_query_with_identifiers(
          "SELECT * FROM %i WHERE is_active=1 ORDER BY id DESC",
          [$t]
        ),
        ARRAY_A
      ) ?: [];
    } else {
      $rows = $wpdb->get_results(
        pointlybooking_prepare_query_with_identifiers(
          "SELECT * FROM %i ORDER BY id DESC",
          [$t]
        ),
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

  $cols = $wpdb->get_col(pointlybooking_prepare_query_with_identifiers("SHOW COLUMNS FROM %i", [$t])) ?: [];
  $select = ['e.id', 'e.name', 'e.price', 'e.image_id', 'e.sort_order'];
  if (in_array('description', $cols, true)) $select[] = 'e.description';
  if (in_array('desc', $cols, true)) $select[] = 'e.desc';
  if (in_array('details', $cols, true)) $select[] = 'e.details';

  $rows = $wpdb->get_results(
    pointlybooking_prepare_query_with_identifiers(
      "SELECT " . implode(', ', $select) . "
      FROM %i e
      INNER JOIN %i es ON es.extra_id=e.id
      WHERE es.service_id=%d
      ORDER BY e.sort_order ASC, e.id DESC",
      [$t, $t_rel],
      [$service_id]
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

  $cols = $wpdb->get_col(pointlybooking_prepare_query_with_identifiers("SHOW COLUMNS FROM %i", [$t])) ?: [];
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
        pointlybooking_prepare_query_with_identifiers(
          "SELECT a.*
          FROM %i a
          INNER JOIN %i r ON r.agent_id=a.id
          WHERE r.service_id=%d AND a.is_active=1
          ORDER BY a.id DESC",
          [$t, $t_rel],
          [$service_id]
        ),
        ARRAY_A
      ) ?: [];
    } else {
      $rows = $wpdb->get_results(
        pointlybooking_prepare_query_with_identifiers(
          "SELECT a.*
          FROM %i a
          INNER JOIN %i r ON r.agent_id=a.id
          WHERE r.service_id=%d
          ORDER BY a.id DESC",
          [$t, $t_rel],
          [$service_id]
        ),
        ARRAY_A
      ) ?: [];
    }
  }

  if (empty($rows) && $has_service_col) {
    if ($has_is_active) {
      $rows = $wpdb->get_results(
        pointlybooking_prepare_query_with_identifiers(
          "SELECT *
          FROM %i
          WHERE service_id=%d AND is_active=1
          ORDER BY id DESC",
          [$t],
          [$service_id]
        ),
        ARRAY_A
      ) ?: [];
    } else {
      $rows = $wpdb->get_results(
        pointlybooking_prepare_query_with_identifiers(
          "SELECT *
          FROM %i
          WHERE service_id=%d
          ORDER BY id DESC",
          [$t],
          [$service_id]
        ),
        ARRAY_A
      ) ?: [];
    }
  }

  if (empty($rows) && !$has_service_col) {
    if ($has_is_active) {
      $rows = $wpdb->get_results(
        pointlybooking_prepare_query_with_identifiers(
          "SELECT * FROM %i WHERE is_active=1 ORDER BY id DESC",
          [$t]
        ),
        ARRAY_A
      ) ?: [];
    } else {
      $rows = $wpdb->get_results(
        pointlybooking_prepare_query_with_identifiers(
          "SELECT * FROM %i ORDER BY id DESC",
          [$t]
        ),
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
