<?php
defined('ABSPATH') || exit;

final class BP_ScheduleHelper {

  public static function future_days_limit() : int {
    $n = (int) BP_SettingsHelper::get_with_default('bp_future_days_limit');
    return max(1, min(365, $n));
  }

  public static function is_date_allowed(string $date_ymd) : bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_ymd)) return false;

    $today = (new DateTime('now', wp_timezone()))->format('Y-m-d');
    if ($date_ymd < $today) return false;

    $limit_days = self::future_days_limit();
    $max = (new DateTime('now', wp_timezone()));
    $max->modify('+' . $limit_days . ' days');
    $max_ymd = $max->format('Y-m-d');

    return ($date_ymd <= $max_ymd);
  }

  public static function get_day_schedule(string $date_ymd) : array {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_ymd)) return [];

    $dt = new DateTime($date_ymd, wp_timezone());
    $weekday = (int)$dt->format('w');

    $raw = (string)BP_SettingsHelper::get_with_default('bp_schedule_' . $weekday);
    $raw = trim($raw);
    if ($raw === '') return [];

    if (!preg_match('/^\d{2}:\d{2}\-\d{2}:\d{2}$/', $raw)) return [];

    [$open, $close] = explode('-', $raw);
    return ['open' => $open, 'close' => $close];
  }

  public static function get_break_ranges() : array {
    $raw = (string)BP_SettingsHelper::get_with_default('bp_breaks');
    $raw = trim($raw);
    if ($raw === '') return [];

    $parts = array_map('trim', explode(',', $raw));
    $ranges = [];

    foreach ($parts as $p) {
      if (!preg_match('/^\d{2}:\d{2}\-\d{2}:\d{2}$/', $p)) continue;
      [$a, $b] = explode('-', $p);
      $ranges[] = ['start' => $a, 'end' => $b];
    }

    return $ranges;
  }

  public static function time_in_break(string $time_hm) : bool {
    if (!preg_match('/^\d{2}:\d{2}$/', $time_hm)) return false;

    foreach (self::get_break_ranges() as $r) {
      if ($time_hm >= $r['start'] && $time_hm < $r['end']) {
        return true;
      }
    }
    return false;
  }

  // Step 15: Service-based schedule override support
  public static function get_service_day_schedule(array $service, string $date_ymd) : array {
    $use_global = (int)($service['use_global_schedule'] ?? 1) === 1;
    if ($use_global) {
      return self::get_day_schedule($date_ymd);
    }

    $json = (string)($service['schedule_json'] ?? '');
    if ($json === '') return self::get_day_schedule($date_ymd);

    $data = json_decode($json, true);
    if (!is_array($data)) return self::get_day_schedule($date_ymd);

    $dt = new DateTime($date_ymd, wp_timezone());
    $weekday = (int)$dt->format('w');

    $raw = isset($data[(string)$weekday]) ? (string)$data[(string)$weekday] : '';
    $raw = trim($raw);
    if ($raw === '') return [];

    if (!preg_match('/^\d{2}:\d{2}\-\d{2}:\d{2}$/', $raw)) return [];

    [$open, $close] = explode('-', $raw);
    return ['open' => $open, 'close' => $close];
  }

  // weekday: 1=Mon .. 7=Sun
  public static function weekday_from_date(string $date) : int {
    $ts = strtotime($date);
    return $ts ? (int)date('N', $ts) : 1;
  }

  public static function get_working_hours(int $agent_id, int $weekday) : array {
    global $wpdb;
    $t = $wpdb->prefix . 'bp_agent_working_hours';

    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT start_time, end_time
       FROM {$t}
       WHERE agent_id=%d AND weekday=%d AND is_enabled=1
       ORDER BY start_time ASC",
      $agent_id, $weekday
    ), ARRAY_A);

    return $rows ?: [];
  }

  public static function get_breaks(int $agent_id, string $date) : array {
    global $wpdb;
    $t = $wpdb->prefix . 'bp_agent_breaks';

    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT start_time, end_time
       FROM {$t}
       WHERE agent_id=%d AND break_date=%s
       ORDER BY start_time ASC",
      $agent_id, $date
    ), ARRAY_A);

    return $rows ?: [];
  }

  public static function time_to_seconds(string $hms) : int {
    if (preg_match('/^\d{2}:\d{2}$/', $hms)) $hms .= ':00';
    if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $hms)) return 0;
    [$h, $m, $s] = array_map('intval', explode(':', $hms));
    return $h * 3600 + $m * 60 + $s;
  }

  public static function is_date_closed(string $date) : bool {
    global $wpdb;
    $t = $wpdb->prefix . 'bp_holidays';

    $date = substr($date, 0, 10);

    $count = (int)$wpdb->get_var($wpdb->prepare("
      SELECT COUNT(*)
      FROM {$t}
      WHERE is_enabled=1
        AND (
          (%s BETWEEN start_date AND end_date)
          OR (
            is_recurring_yearly=1
            AND (
              DATE_FORMAT(%s, '%%m-%%d') BETWEEN DATE_FORMAT(start_date,'%%m-%%d') AND DATE_FORMAT(end_date,'%%m-%%d')
            )
          )
        )
    ", $date, $date));

    return $count > 0;
  }

  public static function get_service_rules(int $service_id) : array {
    global $wpdb;
    $t = $wpdb->prefix . 'bp_services';

    $cols = $wpdb->get_col("SHOW COLUMNS FROM {$t}") ?: [];
    $has_duration_minutes = in_array('duration_minutes', $cols, true);
    $has_duration = in_array('duration', $cols, true);
    $has_buffer_before_minutes = in_array('buffer_before_minutes', $cols, true);
    $has_buffer_after_minutes = in_array('buffer_after_minutes', $cols, true);
    $has_buffer_before = in_array('buffer_before', $cols, true);
    $has_buffer_after = in_array('buffer_after', $cols, true);

    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id=%d", $service_id), ARRAY_A);

    if (!$row) $row = [];

    $duration = $has_duration_minutes
      ? (int)($row['duration_minutes'] ?? 30)
      : ($has_duration ? (int)($row['duration'] ?? 30) : 30);

    $buffer_before = $has_buffer_before_minutes
      ? (int)($row['buffer_before_minutes'] ?? 0)
      : ($has_buffer_before ? (int)($row['buffer_before'] ?? 0) : 0);

    $buffer_after = $has_buffer_after_minutes
      ? (int)($row['buffer_after_minutes'] ?? 0)
      : ($has_buffer_after ? (int)($row['buffer_after'] ?? 0) : 0);

    $capacity = (int)($row['capacity'] ?? 1);

    $row = [
      'duration' => max(5, $duration),
      'buffer_before' => max(0, $buffer_before),
      'buffer_after' => max(0, $buffer_after),
      'capacity' => max(1, $capacity),
    ];

    $row['occupied_min'] = $row['duration'] + $row['buffer_before'] + $row['buffer_after'];

    return $row;
  }

  public static function is_within_schedule(int $agent_id, string $date, string $start_time, int $duration_min) : bool {
    if (self::is_date_closed($date)) return false;
    $weekday = self::weekday_from_date($date);

    $work = self::get_working_hours($agent_id, $weekday);
    if (empty($work)) return false;

    $breaks = self::get_breaks($agent_id, $date);

    $startSec = self::time_to_seconds($start_time);
    $endSec   = $startSec + ($duration_min * 60);

    $fitsWorking = false;
    foreach ($work as $w) {
      $ws = self::time_to_seconds($w['start_time']);
      $we = self::time_to_seconds($w['end_time']);
      if ($startSec >= $ws && $endSec <= $we) { $fitsWorking = true; break; }
    }
    if (!$fitsWorking) return false;

    foreach ($breaks as $b) {
      $bs = self::time_to_seconds($b['start_time']);
      $be = self::time_to_seconds($b['end_time']);
      if ($startSec < $be && $endSec > $bs) return false;
    }

    return true;
  }

  public static function build_unavailable_blocks(int $agent_id, string $from, string $to) : array {
    $blocks = [];
    $cur = strtotime($from);
    $end = strtotime($to);

    while ($cur <= $end) {
      $date = date('Y-m-d', $cur);
      $weekday = self::weekday_from_date($date);
      $work = self::get_working_hours($agent_id, $weekday);
      $breaks = self::get_breaks($agent_id, $date);

      if (empty($work)) {
        $blocks[] = ['start' => $date . 'T00:00:00', 'end' => $date . 'T23:59:59'];
      } else {
        $intervals = [];
        foreach ($work as $w) {
          $intervals[] = [self::time_to_seconds($w['start_time']), self::time_to_seconds($w['end_time'])];
        }
        usort($intervals, fn($a, $b) => $a[0] <=> $b[0]);

        if ($intervals[0][0] > 0) {
          $blocks[] = ['start' => $date . 'T00:00:00', 'end' => $date . 'T' . gmdate('H:i:s', $intervals[0][0])];
        }

        for ($i = 0; $i < count($intervals) - 1; $i++) {
          if ($intervals[$i][1] < $intervals[$i + 1][0]) {
            $blocks[] = [
              'start' => $date . 'T' . gmdate('H:i:s', $intervals[$i][1]),
              'end'   => $date . 'T' . gmdate('H:i:s', $intervals[$i + 1][0]),
            ];
          }
        }

        $lastEnd = $intervals[count($intervals) - 1][1];
        if ($lastEnd < 86400) {
          $blocks[] = ['start' => $date . 'T' . gmdate('H:i:s', $lastEnd), 'end' => $date . 'T23:59:59'];
        }

        foreach ($breaks as $b) {
          $blocks[] = [
            'start' => $date . 'T' . substr($b['start_time'], 0, 8),
            'end'   => $date . 'T' . substr($b['end_time'], 0, 8),
          ];
        }
      }

      $cur = strtotime('+1 day', $cur);
    }

    return $blocks;
  }
}
