<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_AuditModel {

  private static function build_where(array $args, array &$params) : string {
    global $wpdb;

    $event = sanitize_text_field($args['event'] ?? '');
    $booking_id = absint($args['booking_id'] ?? 0);
    $customer_id = absint($args['customer_id'] ?? 0);
    $actor_type = sanitize_text_field($args['actor_type'] ?? '');
    $actor_wp_user_id = absint($args['actor_wp_user_id'] ?? 0);
    $date_from = sanitize_text_field($args['date_from'] ?? '');
    $date_to = sanitize_text_field($args['date_to'] ?? '');
    $q = sanitize_text_field($args['q'] ?? '');

    $where = 'WHERE 1=1';
    $params = [];

    if ($event !== '') {
      $where .= ' AND t.event = %s';
      $params[] = $event;
    }
    if ($actor_type !== '' && in_array($actor_type, ['admin','customer','system'], true)) {
      $where .= ' AND t.actor_type = %s';
      $params[] = $actor_type;
    }
    if ($actor_wp_user_id > 0) {
      $where .= ' AND t.actor_wp_user_id = %d';
      $params[] = $actor_wp_user_id;
    }
    if ($booking_id > 0) {
      $where .= ' AND t.booking_id = %d';
      $params[] = $booking_id;
    }
    if ($customer_id > 0) {
      $where .= ' AND t.customer_id = %d';
      $params[] = $customer_id;
    }
    if ($date_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
      $where .= ' AND t.created_at >= %s';
      $params[] = $date_from . ' 00:00:00';
    }
    if ($date_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
      $where .= ' AND t.created_at <= %s';
      $params[] = $date_to . ' 23:59:59';
    }

    if ($q !== '') {
      $like = '%' . $wpdb->esc_like($q) . '%';
      $where .= " AND (
        t.event LIKE %s
        OR t.actor_type LIKE %s
        OR t.actor_ip LIKE %s
        OR t.meta LIKE %s
        OR u.display_name LIKE %s
        OR u.user_login LIKE %s
        OR u.user_email LIKE %s
        OR CONCAT(COALESCE(c.first_name,''),' ',COALESCE(c.last_name,'')) LIKE %s
        OR c.email LIKE %s
        OR c.phone LIKE %s
      )";
      // same $like repeated for each LIKE clause
      for ($i = 0; $i < 10; $i++) $params[] = $like;
    }
    $where .= ' AND %d = %d';
    $params[] = 1;
    $params[] = 1;

    return $where;
  }

  private static function base_from_sql() : string {
    global $wpdb;
    $t = self::table();
    $u = $wpdb->users;
    $c = pointlybooking_table('customers');

    return "FROM {$t} t
      LEFT JOIN {$u} u ON u.ID = t.actor_wp_user_id
      LEFT JOIN {$c} c ON c.id = t.customer_id";
  }

  public static function table() : string {
    return pointlybooking_table('audit_log');
  }

  public static function list(array $args = []) : array {
    global $wpdb;
    $limit = max(1, min(5000, absint($args['limit'] ?? 500)));
    $params = [];
    $where = self::build_where($args, $params);
    $from_sql = self::base_from_sql();

    $params[] = $limit;
    return $wpdb->get_results(
      $wpdb->prepare(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table names are generated from hardcoded suffixes via pointlybooking_table(); $where contains only hardcoded SQL fragments with placeholders.
        "SELECT
          t.*,
          u.display_name AS actor_wp_display_name,
          u.user_login AS actor_wp_login,
          u.user_email AS actor_wp_email,
          c.first_name AS customer_first_name,
          c.last_name AS customer_last_name,
          c.email AS customer_email,
          c.phone AS customer_phone
        {$from_sql}
        {$where}
        ORDER BY t.id DESC
        LIMIT %d",
        $params
      ),
      ARRAY_A
    ) ?: [];
  }

  public static function list_paged(array $args = []) : array {
    global $wpdb;
    $page = max(1, absint($args['page'] ?? 1));
    $per_page = max(10, min(200, absint($args['per_page'] ?? 50)));
    $offset = ($page - 1) * $per_page;

    $params = [];
    $where = self::build_where($args, $params);
    $from_sql = self::base_from_sql();

    $total = (int)$wpdb->get_var(
      $wpdb->prepare(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table names are generated from hardcoded suffixes via pointlybooking_table(); $where contains only hardcoded SQL fragments with placeholders.
        "SELECT COUNT(*) {$from_sql} {$where}",
        $params
      )
    );

    $items = $wpdb->get_results(
      $wpdb->prepare(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table names are generated from hardcoded suffixes via pointlybooking_table(); $where contains only hardcoded SQL fragments with placeholders.
        "SELECT
          t.*,
          u.display_name AS actor_wp_display_name,
          u.user_login AS actor_wp_login,
          u.user_email AS actor_wp_email,
          c.first_name AS customer_first_name,
          c.last_name AS customer_last_name,
          c.email AS customer_email,
          c.phone AS customer_phone
        {$from_sql}
        {$where}
        ORDER BY t.id DESC
        LIMIT %d OFFSET %d",
        array_merge($params, [$per_page, $offset])
      ),
      ARRAY_A
    ) ?: [];

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
