<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_DashboardHelper {
  private const CACHE_GROUP = 'pointlybooking_dashboard';
  private const CACHE_TTL = 120;

  private static function cache_key(string $suffix): string {
    return 'pointlybooking_dashboard_' . md5($suffix);
  }

  private static function valid_ymd(string $value): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
      return false;
    }
    $ts = strtotime($value . ' 00:00:00');
    return $ts !== false;
  }

  private static function dashboard_filter_nonce_ok(): bool {
    $nonce = sanitize_text_field(wp_unslash($_GET['pointlybooking_filter_nonce'] ?? ''));
    if ($nonce === '') {
      return false;
    }

    return (bool) wp_verify_nonce($nonce, 'pointlybooking_dashboard_filter');
  }

  public static function range(): array {
    $today = gmdate('Y-m-d');
    $default_days = 14;
    $default_from = gmdate('Y-m-d', strtotime('-13 days', strtotime($today)));

    if (!self::dashboard_filter_nonce_ok()) {
      return [
        'range' => (string) $default_days,
        'from' => $default_from,
        'to' => $today,
        'days' => $default_days,
      ];
    }

    $range = sanitize_text_field(wp_unslash($_GET['range'] ?? (string) $default_days));

    if ($range === 'custom') {
      $from = sanitize_text_field(wp_unslash($_GET['from'] ?? $default_from));
      $to = sanitize_text_field(wp_unslash($_GET['to'] ?? $today));

      if (!self::valid_ymd($from)) {
        $from = $default_from;
      }
      if (!self::valid_ymd($to)) {
        $to = $today;
      }

      if (strtotime($from) > strtotime($to)) {
        $tmp = $from;
        $from = $to;
        $to = $tmp;
      }

      $days = max(1, (int) ((strtotime($to) - strtotime($from)) / DAY_IN_SECONDS) + 1);
      $days = min(366, $days);

      return ['range' => 'custom', 'from' => $from, 'to' => $to, 'days' => $days];
    }

    $days = (int) $range;
    if (!in_array($days, [7, 14, 30], true)) {
      $days = $default_days;
    }

    $from = gmdate('Y-m-d', strtotime('-' . ($days - 1) . ' days', strtotime($today)));

    return ['range' => (string) $days, 'from' => $from, 'to' => $today, 'days' => $days];
  }

  public static function kpis_for_range(string $from, string $to): array {
    $cache_key = self::cache_key('kpis|' . $from . '|' . $to);
    $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
    if (is_array($cached)) {
      return $cached;
    }

    global $wpdb;
    $table_bookings = pointlybooking_table('bookings');

    $total = (int) $wpdb->get_var(
      $wpdb->prepare(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is generated from prefix + hardcoded suffix via pointlybooking_table().
        "SELECT COUNT(*) FROM {$table_bookings} WHERE DATE(created_at) BETWEEN %s AND %s",
        $from,
        $to
      )
    );

    $pending = (int) $wpdb->get_var(
      $wpdb->prepare(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is generated from prefix + hardcoded suffix via pointlybooking_table().
        "SELECT COUNT(*) FROM {$table_bookings} WHERE status = %s AND DATE(created_at) BETWEEN %s AND %s",
        'pending',
        $from,
        $to
      )
    );

    $confirmed = (int) $wpdb->get_var(
      $wpdb->prepare(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is generated from prefix + hardcoded suffix via pointlybooking_table().
        "SELECT COUNT(*) FROM {$table_bookings} WHERE status = %s AND DATE(created_at) BETWEEN %s AND %s",
        'confirmed',
        $from,
        $to
      )
    );

    $cancelled = (int) $wpdb->get_var(
      $wpdb->prepare(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is generated from prefix + hardcoded suffix via pointlybooking_table().
        "SELECT COUNT(*) FROM {$table_bookings} WHERE status = %s AND DATE(created_at) BETWEEN %s AND %s",
        'cancelled',
        $from,
        $to
      )
    );

    $revenue = (float) $wpdb->get_var(
      $wpdb->prepare(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is generated from prefix + hardcoded suffix via pointlybooking_table().
        "SELECT COALESCE(SUM(total_price),0) FROM {$table_bookings} WHERE status = %s AND DATE(created_at) BETWEEN %s AND %s",
        'confirmed',
        $from,
        $to
      )
    );

    $result = [
      'total' => $total,
      'pending' => $pending,
      'confirmed' => $confirmed,
      'cancelled' => $cancelled,
      'revenue' => round($revenue, 2),
    ];
    wp_cache_set($cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL);
    return $result;
  }

  public static function bookings_series_for_range(string $from, string $to): array {
    $cache_key = self::cache_key('series|' . $from . '|' . $to);
    $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
    if (is_array($cached)) {
      return $cached;
    }

    global $wpdb;
    $table_bookings = pointlybooking_table('bookings');

    $rows = $wpdb->get_results(
      $wpdb->prepare(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is generated from prefix + hardcoded suffix via pointlybooking_table().
        "SELECT DATE(created_at) as d, COUNT(*) as c FROM {$table_bookings} WHERE DATE(created_at) BETWEEN %s AND %s GROUP BY DATE(created_at) ORDER BY d ASC",
        $from,
        $to
      ),
      ARRAY_A
    );

    $map = [];
    foreach ($rows as $row) {
      $map[$row['d']] = (int) $row['c'];
    }

    $labels = [];
    $values = [];
    $cur = strtotime($from);
    $end = strtotime($to);

    while ($cur <= $end) {
      $date_key = gmdate('Y-m-d', $cur);
      $labels[] = $date_key;
      $values[] = $map[$date_key] ?? 0;
      $cur = strtotime('+1 day', $cur);
    }

    $result = ['labels' => $labels, 'values' => $values];
    wp_cache_set($cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL);
    return $result;
  }

  public static function top_services(string $from, string $to, int $limit = 5): array {
    $cache_key = self::cache_key('top_services|' . $from . '|' . $to . '|' . $limit);
    $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
    if (is_array($cached)) {
      return $cached;
    }

    global $wpdb;

    $limit = max(1, min(50, $limit));
    $table_bookings = pointlybooking_table('bookings');
    $table_services = pointlybooking_table('services');

    $result = $wpdb->get_results(
      $wpdb->prepare(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table names are generated from prefix + hardcoded suffixes via pointlybooking_table().
        "SELECT b.service_id, COALESCE(s.name,'(deleted)') as name, COUNT(*) as bookings, COALESCE(SUM(b.total_price),0) as revenue FROM {$table_bookings} b LEFT JOIN {$table_services} s ON s.id = b.service_id WHERE b.status = %s AND DATE(b.created_at) BETWEEN %s AND %s GROUP BY b.service_id ORDER BY revenue DESC LIMIT %d",
        'confirmed',
        $from,
        $to,
        $limit
      ),
      ARRAY_A
    ) ?: [];
    wp_cache_set($cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL);
    return $result;
  }

  public static function top_agents(string $from, string $to, int $limit = 5): array {
    $cache_key = self::cache_key('top_agents|' . $from . '|' . $to . '|' . $limit);
    $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
    if (is_array($cached)) {
      return $cached;
    }

    global $wpdb;

    $limit = max(1, min(50, $limit));
    $table_bookings = pointlybooking_table('bookings');
    $table_agents = pointlybooking_table('agents');

    $result = $wpdb->get_results(
      $wpdb->prepare(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table names are generated from prefix + hardcoded suffixes via pointlybooking_table().
        "SELECT b.agent_id, COALESCE(a.name,'(deleted)') as name, COUNT(*) as bookings, COALESCE(SUM(b.total_price),0) as revenue FROM {$table_bookings} b LEFT JOIN {$table_agents} a ON a.id = b.agent_id WHERE b.status = %s AND DATE(b.created_at) BETWEEN %s AND %s GROUP BY b.agent_id ORDER BY revenue DESC LIMIT %d",
        'confirmed',
        $from,
        $to,
        $limit
      ),
      ARRAY_A
    ) ?: [];
    wp_cache_set($cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL);
    return $result;
  }

  public static function top_categories(string $from, string $to, int $limit = 5): array {
    $cache_key = self::cache_key('top_categories|' . $from . '|' . $to . '|' . $limit);
    $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
    if (is_array($cached)) {
      return $cached;
    }

    global $wpdb;

    $limit = max(1, min(50, $limit));
    $table_bookings = pointlybooking_table('bookings');
    $table_service_categories = pointlybooking_table('service_categories');
    $table_categories = pointlybooking_table('categories');

    $result = $wpdb->get_results(
      $wpdb->prepare(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table names are generated from prefix + hardcoded suffixes via pointlybooking_table().
        "SELECT c.id as category_id, COALESCE(c.name,'(deleted)') as name, COUNT(DISTINCT b.id) as bookings, COALESCE(SUM(b.total_price),0) as revenue FROM {$table_bookings} b LEFT JOIN {$table_service_categories} m ON m.service_id = b.service_id LEFT JOIN {$table_categories} c ON c.id = m.category_id WHERE b.status = %s AND DATE(b.created_at) BETWEEN %s AND %s GROUP BY c.id ORDER BY revenue DESC LIMIT %d",
        'confirmed',
        $from,
        $to,
        $limit
      ),
      ARRAY_A
    ) ?: [];
    wp_cache_set($cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL);
    return $result;
  }

  public static function pending_bookings(int $limit = 8): array {
    $cache_key = self::cache_key('pending|' . $limit);
    $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
    if (is_array($cached)) {
      return $cached;
    }

    global $wpdb;

    $limit = max(1, min(100, $limit));
    $table_bookings = pointlybooking_table('bookings');

    $result = $wpdb->get_results(
      $wpdb->prepare(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is generated from prefix + hardcoded suffix via pointlybooking_table().
        "SELECT id, customer_name, customer_email, start_date, start_time, total_price, created_at FROM {$table_bookings} WHERE status = %s ORDER BY id DESC LIMIT %d",
        'pending',
        $limit
      ),
      ARRAY_A
    ) ?: [];
    wp_cache_set($cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL);
    return $result;
  }

  public static function recent_bookings(int $limit = 10): array {
    $cache_key = self::cache_key('recent|' . $limit);
    $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
    if (is_array($cached)) {
      return $cached;
    }

    global $wpdb;

    $limit = max(1, min(100, $limit));
    $table_bookings = pointlybooking_table('bookings');

    $rows = $wpdb->get_results(
      $wpdb->prepare(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is generated from prefix + hardcoded suffix via pointlybooking_table().
        "SELECT id, customer_name, customer_email, status, total_price, created_at, start_date, start_time FROM {$table_bookings} ORDER BY id DESC LIMIT %d",
        $limit
      ),
      ARRAY_A
    );
    $result = $rows ?: [];
    wp_cache_set($cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL);
    return $result;
  }
}
