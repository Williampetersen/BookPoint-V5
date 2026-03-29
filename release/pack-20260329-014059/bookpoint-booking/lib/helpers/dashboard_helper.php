<?php
defined('ABSPATH') || exit;
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- This file's wpdb SQL paths interpolate only hardcoded plugin table names with a sanitized WordPress prefix; dynamic values remain prepared or static by design.

final class POINTLYBOOKING_DashboardHelper {
  private const CACHE_GROUP = 'pointlybooking_dashboard';
  private const CACHE_TTL = 120;

  private static function cache_key(string $suffix): string {
    return 'pointlybooking_dashboard_' . md5($suffix);
  }

  private static function is_safe_sql_identifier(string $identifier): bool {
    return preg_match('/^[A-Za-z0-9_]+$/', $identifier) === 1;
  }

  private static function valid_ymd(string $value): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
      return false;
    }
    [$year, $month, $day] = array_map('intval', explode('-', $value));
    return checkdate($month, $day, $year);
  }

  private static function request_raw(string $key): string {
    return pointlybooking_request_scalar('get', $key);
  }

  private static function dashboard_filter_nonce_ok(): bool {
    $nonce = sanitize_text_field(self::request_raw('pointlybooking_filter_nonce'));
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

    $range = sanitize_text_field(self::request_raw('range'));
    if ($range === '') {
      $range = (string) $default_days;
    }
    if (!in_array($range, ['7', '14', '30', '90', 'custom'], true)) {
      $range = (string) $default_days;
    }

    if ($range === 'custom') {
      $from = sanitize_text_field(self::request_raw('from'));
      $to = sanitize_text_field(self::request_raw('to'));
      if ($from === '') {
        $from = $default_from;
      }
      if ($to === '') {
        $to = $today;
      }

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
    if (!in_array($days, [7, 14, 30, 90], true)) {
      $days = $default_days;
    }

    $from = gmdate('Y-m-d', strtotime('-' . ($days - 1) . ' days', strtotime($today)));

    return ['range' => (string) $days, 'from' => $from, 'to' => $today, 'days' => $days];
  }

  public static function kpis_for_range(string $from, string $to): array {
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names in this function are validated local plugin table names built from hardcoded plugin suffixes.
    $cache_key = self::cache_key('kpis|' . $from . '|' . $to);
    $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
    if (is_array($cached)) {
      return $cached;
    }

    global $wpdb;
    $table_bookings = $wpdb->prefix . 'pointlybooking_bookings';
    if (!self::is_safe_sql_identifier($table_bookings)) {
      return [
        'total' => 0,
        'pending' => 0,
        'confirmed' => 0,
        'cancelled' => 0,
        'revenue' => 0.0,
      ];
    }

    $bookings_table = $table_bookings;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $total = (int) $wpdb->get_var(
      $wpdb->prepare("SELECT COUNT(*) FROM {$bookings_table} WHERE DATE(created_at) BETWEEN %s AND %s", $from, $to)
    );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $pending = (int) $wpdb->get_var(
      $wpdb->prepare("SELECT COUNT(*) FROM {$bookings_table} WHERE status = %s AND DATE(created_at) BETWEEN %s AND %s", 'pending', $from, $to)
    );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $confirmed = (int) $wpdb->get_var(
      $wpdb->prepare("SELECT COUNT(*) FROM {$bookings_table} WHERE status = %s AND DATE(created_at) BETWEEN %s AND %s", 'confirmed', $from, $to)
    );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $cancelled = (int) $wpdb->get_var(
      $wpdb->prepare("SELECT COUNT(*) FROM {$bookings_table} WHERE status = %s AND DATE(created_at) BETWEEN %s AND %s", 'cancelled', $from, $to)
    );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $revenue = (float) $wpdb->get_var(
      $wpdb->prepare("SELECT COALESCE(SUM(total_price),0) FROM {$bookings_table} WHERE status = %s AND DATE(created_at) BETWEEN %s AND %s", 'confirmed', $from, $to)
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
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names in this function are validated local plugin table names built from hardcoded plugin suffixes.
    $cache_key = self::cache_key('series|' . $from . '|' . $to);
    $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
    if (is_array($cached)) {
      return $cached;
    }

    global $wpdb;
    $table_bookings = $wpdb->prefix . 'pointlybooking_bookings';
    if (!self::is_safe_sql_identifier($table_bookings)) {
      return ['labels' => [], 'values' => []];
    }

    $bookings_table = $table_bookings;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT DATE(created_at) as d, COUNT(*) as c FROM {$bookings_table} WHERE DATE(created_at) BETWEEN %s AND %s GROUP BY DATE(created_at) ORDER BY d ASC",
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
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names in this function are validated local plugin table names built from hardcoded plugin suffixes.
    $cache_key = self::cache_key('top_services|' . $from . '|' . $to . '|' . $limit);
    $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
    if (is_array($cached)) {
      return $cached;
    }

    global $wpdb;

    $limit = max(1, min(50, $limit));
    $table_bookings = $wpdb->prefix . 'pointlybooking_bookings';
    $table_services = $wpdb->prefix . 'pointlybooking_services';
    if (!self::is_safe_sql_identifier($table_bookings) || !self::is_safe_sql_identifier($table_services)) {
      return [];
    }

    $bookings_table = $table_bookings;
    $services_table = $table_services;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $result = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT b.service_id, COALESCE(s.name,'(deleted)') as name, COUNT(*) as bookings, COALESCE(SUM(b.total_price),0) as revenue FROM {$bookings_table} b LEFT JOIN {$services_table} s ON s.id = b.service_id WHERE b.status = %s AND DATE(b.created_at) BETWEEN %s AND %s GROUP BY b.service_id ORDER BY revenue DESC LIMIT %d",
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
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names in this function are validated local plugin table names built from hardcoded plugin suffixes.
    $cache_key = self::cache_key('top_agents|' . $from . '|' . $to . '|' . $limit);
    $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
    if (is_array($cached)) {
      return $cached;
    }

    global $wpdb;

    $limit = max(1, min(50, $limit));
    $table_bookings = $wpdb->prefix . 'pointlybooking_bookings';
    $table_agents = $wpdb->prefix . 'pointlybooking_agents';
    if (!self::is_safe_sql_identifier($table_bookings) || !self::is_safe_sql_identifier($table_agents)) {
      return [];
    }

    $bookings_table = $table_bookings;
    $agents_table = $table_agents;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $result = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT b.agent_id, COALESCE(a.name,'(deleted)') as name, COUNT(*) as bookings, COALESCE(SUM(b.total_price),0) as revenue FROM {$bookings_table} b LEFT JOIN {$agents_table} a ON a.id = b.agent_id WHERE b.status = %s AND DATE(b.created_at) BETWEEN %s AND %s GROUP BY b.agent_id ORDER BY revenue DESC LIMIT %d",
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
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names in this function are validated local plugin table names built from hardcoded plugin suffixes.
    $cache_key = self::cache_key('top_categories|' . $from . '|' . $to . '|' . $limit);
    $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
    if (is_array($cached)) {
      return $cached;
    }

    global $wpdb;

    $limit = max(1, min(50, $limit));
    $table_bookings = $wpdb->prefix . 'pointlybooking_bookings';
    $table_service_categories = $wpdb->prefix . 'pointlybooking_service_categories';
    $table_categories = $wpdb->prefix . 'pointlybooking_categories';
    if (
      !self::is_safe_sql_identifier($table_bookings) ||
      !self::is_safe_sql_identifier($table_service_categories) ||
      !self::is_safe_sql_identifier($table_categories)
    ) {
      return [];
    }

    $bookings_table = $table_bookings;
    $service_categories_table = $table_service_categories;
    $categories_table = $table_categories;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $result = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT c.id as category_id, COALESCE(c.name,'(deleted)') as name, COUNT(DISTINCT b.id) as bookings, COALESCE(SUM(b.total_price),0) as revenue FROM {$bookings_table} b LEFT JOIN {$service_categories_table} m ON m.service_id = b.service_id LEFT JOIN {$categories_table} c ON c.id = m.category_id WHERE b.status = %s AND DATE(b.created_at) BETWEEN %s AND %s GROUP BY c.id ORDER BY revenue DESC LIMIT %d",
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
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names in this function are validated local plugin table names built from hardcoded plugin suffixes.
    $cache_key = self::cache_key('pending|' . $limit);
    $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
    if (is_array($cached)) {
      return $cached;
    }

    global $wpdb;

    $limit = max(1, min(100, $limit));
    $table_bookings = $wpdb->prefix . 'pointlybooking_bookings';
    if (!self::is_safe_sql_identifier($table_bookings)) {
      return [];
    }

    $bookings_table = $table_bookings;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $result = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT id, customer_name, customer_email, start_date, start_time, total_price, created_at FROM {$bookings_table} WHERE status = %s ORDER BY id DESC LIMIT %d",
        'pending',
        $limit
      ),
      ARRAY_A
    ) ?: [];
    wp_cache_set($cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL);
    return $result;
  }

  public static function recent_bookings(int $limit = 10): array {
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names in this function are validated local plugin table names built from hardcoded plugin suffixes.
    $cache_key = self::cache_key('recent|' . $limit);
    $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
    if (is_array($cached)) {
      return $cached;
    }

    global $wpdb;

    $limit = max(1, min(100, $limit));
    $table_bookings = $wpdb->prefix . 'pointlybooking_bookings';
    if (!self::is_safe_sql_identifier($table_bookings)) {
      return [];
    }

    $bookings_table = $table_bookings;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT id, customer_name, customer_email, status, total_price, created_at, start_date, start_time FROM {$bookings_table} ORDER BY id DESC LIMIT %d",
        $limit
      ),
      ARRAY_A
    );
    $result = $rows ?: [];
    wp_cache_set($cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL);
    return $result;
  }
}
