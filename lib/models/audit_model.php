<?php
defined('ABSPATH') || exit;

final class BP_AuditModel {

  public static function table() : string {
    global $wpdb;
    return $wpdb->prefix . 'bp_audit_log';
  }

  public static function list(array $args = []) : array {
    global $wpdb;
    $t = self::table();

    $event = sanitize_text_field($args['event'] ?? '');
    $booking_id = absint($args['booking_id'] ?? 0);
    $customer_id = absint($args['customer_id'] ?? 0);
    $actor_type = sanitize_text_field($args['actor_type'] ?? '');
    $date_from = sanitize_text_field($args['date_from'] ?? '');
    $date_to = sanitize_text_field($args['date_to'] ?? '');

    $where = 'WHERE 1=1';
    $params = [];

    if ($event !== '') {
      $where .= ' AND event = %s';
      $params[] = $event;
    }
    if ($actor_type !== '' && in_array($actor_type, ['admin','customer','system'], true)) {
      $where .= ' AND actor_type = %s';
      $params[] = $actor_type;
    }
    if ($booking_id > 0) {
      $where .= ' AND booking_id = %d';
      $params[] = $booking_id;
    }
    if ($customer_id > 0) {
      $where .= ' AND customer_id = %d';
      $params[] = $customer_id;
    }
    if ($date_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
      $where .= ' AND created_at >= %s';
      $params[] = $date_from . ' 00:00:00';
    }
    if ($date_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
      $where .= ' AND created_at <= %s';
      $params[] = $date_to . ' 23:59:59';
    }

    $sql = "SELECT * FROM {$t} {$where} ORDER BY id DESC LIMIT 500";
    if (!empty($params)) {
      return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) ?: [];
    }

    return $wpdb->get_results($sql, ARRAY_A) ?: [];
  }

  public static function list_paged(array $args = []) : array {
    global $wpdb;
    $t = self::table();

    $page = max(1, absint($args['page'] ?? 1));
    $per_page = max(10, min(200, absint($args['per_page'] ?? 50)));
    $offset = ($page - 1) * $per_page;

    $event = sanitize_text_field($args['event'] ?? '');
    $booking_id = absint($args['booking_id'] ?? 0);
    $customer_id = absint($args['customer_id'] ?? 0);
    $actor_type = sanitize_text_field($args['actor_type'] ?? '');
    $date_from = sanitize_text_field($args['date_from'] ?? '');
    $date_to = sanitize_text_field($args['date_to'] ?? '');

    $where = 'WHERE 1=1';
    $params = [];

    if ($event !== '') {
      $where .= ' AND event = %s';
      $params[] = $event;
    }
    if ($actor_type !== '' && in_array($actor_type, ['admin','customer','system'], true)) {
      $where .= ' AND actor_type = %s';
      $params[] = $actor_type;
    }
    if ($booking_id > 0) {
      $where .= ' AND booking_id = %d';
      $params[] = $booking_id;
    }
    if ($customer_id > 0) {
      $where .= ' AND customer_id = %d';
      $params[] = $customer_id;
    }
    if ($date_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
      $where .= ' AND created_at >= %s';
      $params[] = $date_from . ' 00:00:00';
    }
    if ($date_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
      $where .= ' AND created_at <= %s';
      $params[] = $date_to . ' 23:59:59';
    }

    $count_sql = "SELECT COUNT(*) FROM {$t} {$where}";
    $list_sql = "SELECT * FROM {$t} {$where} ORDER BY id DESC LIMIT %d OFFSET %d";

    $count_prepared = !empty($params) ? $wpdb->prepare($count_sql, $params) : $count_sql;
    $total = (int)$wpdb->get_var($count_prepared);

    $list_params = array_merge($params, [$per_page, $offset]);
    $items = $wpdb->get_results($wpdb->prepare($list_sql, $list_params), ARRAY_A) ?: [];

    return [
      'items' => $items,
      'total' => $total,
      'page' => $page,
      'per_page' => $per_page,
    ];
  }

  public static function distinct_events() : array {
    global $wpdb;
    $t = self::table();
    $rows = $wpdb->get_col("SELECT DISTINCT event FROM {$t} ORDER BY event ASC");
    return array_values(array_filter(array_map('strval', $rows ?: [])));
  }
}
