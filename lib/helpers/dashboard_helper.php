<?php
defined('ABSPATH') || exit;

final class BP_DashboardHelper {

  public static function range(): array {
    $range = sanitize_text_field($_GET['range'] ?? '14');
    $today = current_time('Y-m-d');

    if ($range === 'custom') {
      $from = sanitize_text_field($_GET['from'] ?? '');
      $to   = sanitize_text_field($_GET['to'] ?? $today);

      if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d', strtotime('-13 days', strtotime($today)));
      if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = $today;

      if (strtotime($from) > strtotime($to)) {
        $tmp = $from; $from = $to; $to = $tmp;
      }

      return ['range' => 'custom', 'from' => $from, 'to' => $to, 'days' => max(1, (int)((strtotime($to) - strtotime($from)) / 86400) + 1)];
    }

    $days = (int)$range;
    if (!in_array($days, [7, 14, 30], true)) $days = 14;

    $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days', strtotime($today)));

    return ['range' => (string)$days, 'from' => $from, 'to' => $today, 'days' => $days];
  }

  public static function kpis_for_range(string $from, string $to): array {
    global $wpdb;
    $t = $wpdb->prefix . 'bp_bookings';

    $total = (int)$wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM {$t} WHERE DATE(created_at) BETWEEN %s AND %s",
      $from, $to
    ));

    $pending = (int)$wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM {$t} WHERE status='pending' AND DATE(created_at) BETWEEN %s AND %s",
      $from, $to
    ));

    $confirmed = (int)$wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM {$t} WHERE status='confirmed' AND DATE(created_at) BETWEEN %s AND %s",
      $from, $to
    ));

    $cancelled = (int)$wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM {$t} WHERE status='cancelled' AND DATE(created_at) BETWEEN %s AND %s",
      $from, $to
    ));

    $revenue = (float)$wpdb->get_var($wpdb->prepare(
      "SELECT COALESCE(SUM(total_price),0) FROM {$t}
       WHERE status='confirmed' AND DATE(created_at) BETWEEN %s AND %s",
      $from, $to
    ));

    return [
      'total' => $total,
      'pending' => $pending,
      'confirmed' => $confirmed,
      'cancelled' => $cancelled,
      'revenue' => round($revenue, 2),
    ];
  }

  public static function bookings_series_for_range(string $from, string $to): array {
    global $wpdb;
    $t = $wpdb->prefix . 'bp_bookings';

    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT DATE(created_at) as d, COUNT(*) as c
       FROM {$t}
       WHERE DATE(created_at) BETWEEN %s AND %s
       GROUP BY DATE(created_at)
       ORDER BY d ASC",
      $from, $to
    ), ARRAY_A);

    $map = [];
    foreach ($rows as $r) $map[$r['d']] = (int)$r['c'];

    $labels = [];
    $values = [];
    $cur = strtotime($from);
    $end = strtotime($to);

    while ($cur <= $end) {
      $d = date('Y-m-d', $cur);
      $labels[] = $d;
      $values[] = $map[$d] ?? 0;
      $cur = strtotime('+1 day', $cur);
    }

    return ['labels' => $labels, 'values' => $values];
  }

  public static function top_services(string $from, string $to, int $limit = 5): array {
    global $wpdb;
    $t = $wpdb->prefix . 'bp_bookings';
    $s = $wpdb->prefix . 'bp_services';

    return $wpdb->get_results($wpdb->prepare(
      "SELECT b.service_id, COALESCE(s.name,'(deleted)') as name,
              COUNT(*) as bookings, COALESCE(SUM(b.total_price),0) as revenue
       FROM {$t} b
       LEFT JOIN {$s} s ON s.id = b.service_id
       WHERE b.status='confirmed' AND DATE(b.created_at) BETWEEN %s AND %s
       GROUP BY b.service_id
       ORDER BY revenue DESC
       LIMIT %d",
      $from, $to, $limit
    ), ARRAY_A) ?: [];
  }

  public static function top_agents(string $from, string $to, int $limit = 5): array {
    global $wpdb;
    $t = $wpdb->prefix . 'bp_bookings';
    $a = $wpdb->prefix . 'bp_agents';

    return $wpdb->get_results($wpdb->prepare(
      "SELECT b.agent_id, COALESCE(a.name,'(deleted)') as name,
              COUNT(*) as bookings, COALESCE(SUM(b.total_price),0) as revenue
       FROM {$t} b
       LEFT JOIN {$a} a ON a.id = b.agent_id
       WHERE b.status='confirmed' AND DATE(b.created_at) BETWEEN %s AND %s
       GROUP BY b.agent_id
       ORDER BY revenue DESC
       LIMIT %d",
      $from, $to, $limit
    ), ARRAY_A) ?: [];
  }

  public static function top_categories(string $from, string $to, int $limit = 5): array {
    global $wpdb;
    $b = $wpdb->prefix . 'bp_bookings';
    $m = $wpdb->prefix . 'bp_service_categories';
    $c = $wpdb->prefix . 'bp_categories';

    return $wpdb->get_results($wpdb->prepare(
      "SELECT c.id as category_id, COALESCE(c.name,'(deleted)') as name,
              COUNT(DISTINCT b.id) as bookings,
              COALESCE(SUM(b.total_price),0) as revenue
       FROM {$b} b
       LEFT JOIN {$m} m ON m.service_id = b.service_id
       LEFT JOIN {$c} c ON c.id = m.category_id
       WHERE b.status='confirmed' AND DATE(b.created_at) BETWEEN %s AND %s
       GROUP BY c.id
       ORDER BY revenue DESC
       LIMIT %d",
      $from, $to, $limit
    ), ARRAY_A) ?: [];
  }

  public static function pending_bookings(int $limit = 8): array {
    global $wpdb;
    $t = $wpdb->prefix . 'bp_bookings';

    return $wpdb->get_results($wpdb->prepare(
      "SELECT id, customer_name, customer_email, start_date, start_time, total_price, created_at
       FROM {$t}
       WHERE status='pending'
       ORDER BY id DESC
       LIMIT %d",
      $limit
    ), ARRAY_A) ?: [];
  }

  public static function recent_bookings(int $limit = 10): array {
    global $wpdb;
    $t = $wpdb->prefix . 'bp_bookings';

    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT id, customer_name, customer_email, status, total_price, created_at, start_date, start_time
       FROM {$t}
       ORDER BY id DESC
       LIMIT %d",
      $limit
    ), ARRAY_A);

    return $rows ?: [];
  }
}
