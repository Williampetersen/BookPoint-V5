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
}
