<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_AuditModel {

  private static function is_safe_sql_identifier(string $identifier): bool {
    return preg_match('/^[A-Za-z0-9_]+$/', $identifier) === 1;
  }

  private static function quote_sql_identifier(string $identifier): string {
    return '`' . $identifier . '`';
  }

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

  public static function table() : string {
    return pointlybooking_table('audit_log');
  }

  public static function list(array $args = []) : array {
    global $wpdb;
    $limit = max(1, min(5000, absint($args['limit'] ?? 500)));
    $table = self::table();
    $users_table = $wpdb->users;
    $customers_table = pointlybooking_table('customers');
    if (
      !self::is_safe_sql_identifier($table)
      || !self::is_safe_sql_identifier($users_table)
      || !self::is_safe_sql_identifier($customers_table)
    ) {
      return [];
    }
    $quoted_table = self::quote_sql_identifier($table);
    $quoted_users = self::quote_sql_identifier($users_table);
    $quoted_customers = self::quote_sql_identifier($customers_table);

    $event = sanitize_text_field($args['event'] ?? '');
    $booking_id = absint($args['booking_id'] ?? 0);
    $customer_id = absint($args['customer_id'] ?? 0);
    $actor_type = sanitize_text_field($args['actor_type'] ?? '');
    $actor_wp_user_id = absint($args['actor_wp_user_id'] ?? 0);
    $date_from = sanitize_text_field($args['date_from'] ?? '');
    $date_to = sanitize_text_field($args['date_to'] ?? '');
    $q = sanitize_text_field($args['q'] ?? '');
    $event_value = $event !== '' ? $event : '';
    $actor_type_value = ($actor_type !== '' && in_array($actor_type, ['admin','customer','system'], true)) ? $actor_type : '';
    $date_from_value = ($date_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) ? ($date_from . ' 00:00:00') : '';
    $date_to_value = ($date_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) ? ($date_to . ' 23:59:59') : '';
    $like = $q !== '' ? ('%' . $wpdb->esc_like($q) . '%') : '';

    return $wpdb->get_results(
      $wpdb->prepare(
        "SELECT
          t.*,
          u.display_name AS actor_wp_display_name,
          u.user_login AS actor_wp_login,
          u.user_email AS actor_wp_email,
          c.first_name AS customer_first_name,
          c.last_name AS customer_last_name,
          c.email AS customer_email,
          c.phone AS customer_phone
        FROM {$quoted_table} t
        LEFT JOIN {$quoted_users} u ON u.ID = t.actor_wp_user_id
        LEFT JOIN {$quoted_customers} c ON c.id = t.customer_id
        WHERE (%d = 0 OR t.event = %s)
          AND (%d = 0 OR t.actor_type = %s)
          AND (%d = 0 OR t.actor_wp_user_id = %d)
          AND (%d = 0 OR t.booking_id = %d)
          AND (%d = 0 OR t.customer_id = %d)
          AND (%d = 0 OR t.created_at >= %s)
          AND (%d = 0 OR t.created_at <= %s)
          AND (%d = 0 OR (
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
          ))
        ORDER BY t.id DESC
        LIMIT %d",
        $event_value !== '' ? 1 : 0,
        $event_value,
        $actor_type_value !== '' ? 1 : 0,
        $actor_type_value,
        $actor_wp_user_id > 0 ? 1 : 0,
        $actor_wp_user_id,
        $booking_id > 0 ? 1 : 0,
        $booking_id,
        $customer_id > 0 ? 1 : 0,
        $customer_id,
        $date_from_value !== '' ? 1 : 0,
        $date_from_value,
        $date_to_value !== '' ? 1 : 0,
        $date_to_value,
        $like !== '' ? 1 : 0,
        $like,
        $like,
        $like,
        $like,
        $like,
        $like,
        $like,
        $like,
        $like,
        $like,
        $limit
      ),
      ARRAY_A
    ) ?: [];
  }

  public static function list_paged(array $args = []) : array {
    global $wpdb;
    $page = max(1, absint($args['page'] ?? 1));
    $per_page = max(10, min(200, absint($args['per_page'] ?? 50)));
    $offset = ($page - 1) * $per_page;
    $table = self::table();
    $users_table = $wpdb->users;
    $customers_table = pointlybooking_table('customers');
    if (
      !self::is_safe_sql_identifier($table)
      || !self::is_safe_sql_identifier($users_table)
      || !self::is_safe_sql_identifier($customers_table)
    ) {
      return [
        'items' => [],
        'total' => 0,
        'page' => $page,
        'per_page' => $per_page,
      ];
    }
    $quoted_table = self::quote_sql_identifier($table);
    $quoted_users = self::quote_sql_identifier($users_table);
    $quoted_customers = self::quote_sql_identifier($customers_table);

    $event = sanitize_text_field($args['event'] ?? '');
    $booking_id = absint($args['booking_id'] ?? 0);
    $customer_id = absint($args['customer_id'] ?? 0);
    $actor_type = sanitize_text_field($args['actor_type'] ?? '');
    $actor_wp_user_id = absint($args['actor_wp_user_id'] ?? 0);
    $date_from = sanitize_text_field($args['date_from'] ?? '');
    $date_to = sanitize_text_field($args['date_to'] ?? '');
    $q = sanitize_text_field($args['q'] ?? '');
    $event_value = $event !== '' ? $event : '';
    $actor_type_value = ($actor_type !== '' && in_array($actor_type, ['admin','customer','system'], true)) ? $actor_type : '';
    $date_from_value = ($date_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) ? ($date_from . ' 00:00:00') : '';
    $date_to_value = ($date_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) ? ($date_to . ' 23:59:59') : '';
    $like = $q !== '' ? ('%' . $wpdb->esc_like($q) . '%') : '';

    $total = (int)$wpdb->get_var(
      $wpdb->prepare(
        "SELECT COUNT(*)
        FROM {$quoted_table} t
        LEFT JOIN {$quoted_users} u ON u.ID = t.actor_wp_user_id
        LEFT JOIN {$quoted_customers} c ON c.id = t.customer_id
        WHERE (%d = 0 OR t.event = %s)
          AND (%d = 0 OR t.actor_type = %s)
          AND (%d = 0 OR t.actor_wp_user_id = %d)
          AND (%d = 0 OR t.booking_id = %d)
          AND (%d = 0 OR t.customer_id = %d)
          AND (%d = 0 OR t.created_at >= %s)
          AND (%d = 0 OR t.created_at <= %s)
          AND (%d = 0 OR (
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
          ))",
        $event_value !== '' ? 1 : 0,
        $event_value,
        $actor_type_value !== '' ? 1 : 0,
        $actor_type_value,
        $actor_wp_user_id > 0 ? 1 : 0,
        $actor_wp_user_id,
        $booking_id > 0 ? 1 : 0,
        $booking_id,
        $customer_id > 0 ? 1 : 0,
        $customer_id,
        $date_from_value !== '' ? 1 : 0,
        $date_from_value,
        $date_to_value !== '' ? 1 : 0,
        $date_to_value,
        $like !== '' ? 1 : 0,
        $like,
        $like,
        $like,
        $like,
        $like,
        $like,
        $like,
        $like,
        $like,
        $like
      )
    );

    $items = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT
          t.*,
          u.display_name AS actor_wp_display_name,
          u.user_login AS actor_wp_login,
          u.user_email AS actor_wp_email,
          c.first_name AS customer_first_name,
          c.last_name AS customer_last_name,
          c.email AS customer_email,
          c.phone AS customer_phone
        FROM {$quoted_table} t
        LEFT JOIN {$quoted_users} u ON u.ID = t.actor_wp_user_id
        LEFT JOIN {$quoted_customers} c ON c.id = t.customer_id
        WHERE (%d = 0 OR t.event = %s)
          AND (%d = 0 OR t.actor_type = %s)
          AND (%d = 0 OR t.actor_wp_user_id = %d)
          AND (%d = 0 OR t.booking_id = %d)
          AND (%d = 0 OR t.customer_id = %d)
          AND (%d = 0 OR t.created_at >= %s)
          AND (%d = 0 OR t.created_at <= %s)
          AND (%d = 0 OR (
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
          ))
        ORDER BY t.id DESC
        LIMIT %d OFFSET %d",
        $event_value !== '' ? 1 : 0,
        $event_value,
        $actor_type_value !== '' ? 1 : 0,
        $actor_type_value,
        $actor_wp_user_id > 0 ? 1 : 0,
        $actor_wp_user_id,
        $booking_id > 0 ? 1 : 0,
        $booking_id,
        $customer_id > 0 ? 1 : 0,
        $customer_id,
        $date_from_value !== '' ? 1 : 0,
        $date_from_value,
        $date_to_value !== '' ? 1 : 0,
        $date_to_value,
        $like !== '' ? 1 : 0,
        $like,
        $like,
        $like,
        $like,
        $like,
        $like,
        $like,
        $like,
        $like,
        $like,
        $per_page,
        $offset
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
    if (!self::is_safe_sql_identifier($t)) {
      return [];
    }
    $quoted_table = self::quote_sql_identifier($t);
    $rows = $wpdb->get_col(
      "SELECT DISTINCT event FROM {$quoted_table} ORDER BY event ASC"
    );
    return array_values(array_filter(array_map('strval', $rows ?: [])));
  }
}
