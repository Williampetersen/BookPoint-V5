<?php
defined('ABSPATH') || exit;
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- This file's wpdb SQL paths interpolate only hardcoded plugin table names with a sanitized WordPress prefix; dynamic values remain prepared or static by design.

final class POINTLYBOOKING_ScheduleHelper {

  public static function future_days_limit() : int {
    $n = (int) POINTLYBOOKING_SettingsHelper::get_with_default('pointlybooking_future_days_limit');
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

  private static function valid_hhmm(string $value): bool {
    return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
  }

  private static function parse_hhmm_range(string $value): ?array {
    if (!preg_match('/^((?:[01]\d|2[0-3]):[0-5]\d)\-((?:[01]\d|2[0-3]):[0-5]\d)$/', $value, $m)) {
      return null;
    }

    $open = $m[1];
    $close = $m[2];
    if (self::to_minutes($close) <= self::to_minutes($open)) {
      return null;
    }

    return [$open, $close];
  }

  public static function get_day_schedule(string $date_ymd) : array {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_ymd)) return [];

    $dt = new DateTime($date_ymd, wp_timezone());
    $weekday = (int)$dt->format('w');

    $raw = (string)POINTLYBOOKING_SettingsHelper::get_with_default('pointlybooking_schedule_' . $weekday);
    $raw = trim($raw);
    if ($raw === '') return [];

    $parsed = self::parse_hhmm_range($raw);
    if ($parsed === null) return [];
    [$open, $close] = $parsed;
    return ['open' => $open, 'close' => $close];
  }

  public static function get_break_ranges() : array {
    $raw = (string)POINTLYBOOKING_SettingsHelper::get_with_default('pointlybooking_breaks');
    $raw = trim($raw);
    if ($raw === '') return [];

    $parts = array_map('trim', explode(',', $raw));
    $ranges = [];

    foreach ($parts as $p) {
      $parsed = self::parse_hhmm_range($p);
      if ($parsed === null) continue;
      [$a, $b] = $parsed;
      $ranges[] = ['start' => $a, 'end' => $b];
    }

    return $ranges;
  }

  private static function schedule_table(): string {
    global $wpdb;
    return $wpdb->prefix . 'pointlybooking_schedules';
  }

  private static function schedule_settings_table(): string {
    global $wpdb;
    return $wpdb->prefix . 'pointlybooking_schedule_settings';
  }

  private static function table_exists(string $table): bool {
    return pointlybooking_db_table_exists($table);
  }

  private static function is_safe_sql_identifier(string $identifier): bool {
    return preg_match('/^[A-Za-z0-9_]+$/', $identifier) === 1;
  }

  public static function get_schedule_settings(): array {
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names in this function are validated local plugin table names built from hardcoded plugin suffixes.
    global $wpdb;
    $schedule_settings_table = $wpdb->prefix . 'pointlybooking_schedule_settings';

    $defaults = [
      'slot_interval_minutes' => (int)POINTLYBOOKING_SettingsHelper::get('slot_interval_minutes', 30),
      'timezone' => (string)(POINTLYBOOKING_SettingsHelper::get('timezone', '') ?: 'Europe/Copenhagen'),
    ];

    if (!self::table_exists($schedule_settings_table)) return $defaults;
    if (!self::is_safe_sql_identifier($schedule_settings_table)) return $defaults;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $row = $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM {$schedule_settings_table} WHERE id=%d", 1),
      ARRAY_A
    );
    if (!$row) {
      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
      $wpdb->insert($schedule_settings_table, [
        'id' => 1,
        'slot_interval_minutes' => $defaults['slot_interval_minutes'],
        'timezone' => $defaults['timezone'],
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
      ], ['%d','%d','%s','%s','%s']);
      return $defaults;
    }

    return [
      'slot_interval_minutes' => (int)($row['slot_interval_minutes'] ?? $defaults['slot_interval_minutes']),
      'timezone' => (string)($row['timezone'] ?? $defaults['timezone']),
    ];
  }

  public static function set_schedule_settings(int $slot_interval_minutes, string $timezone): bool {
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names in this function are validated local plugin table names built from hardcoded plugin suffixes.
    global $wpdb;
    $schedule_settings_table = $wpdb->prefix . 'pointlybooking_schedule_settings';
    if (!self::table_exists($schedule_settings_table)) return false;
    if (!self::is_safe_sql_identifier($schedule_settings_table)) return false;

    $slot_interval_minutes = max(5, min(120, $slot_interval_minutes));
    $timezone = trim($timezone) ?: 'Europe/Copenhagen';

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $exists = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$schedule_settings_table} WHERE id=%d", 1));
    $now = current_time('mysql');
    if ($exists > 0) {
      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
      $updated = $wpdb->update($schedule_settings_table, [
        'slot_interval_minutes' => $slot_interval_minutes,
        'timezone' => $timezone,
        'updated_at' => $now,
      ], ['id' => 1], ['%d','%s','%s'], ['%d']);
      return ($updated !== false);
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $inserted = $wpdb->insert($schedule_settings_table, [
      'id' => 1,
      'slot_interval_minutes' => $slot_interval_minutes,
      'timezone' => $timezone,
      'created_at' => $now,
      'updated_at' => $now,
    ], ['%d','%d','%s','%s','%s']);

    return ($inserted !== false);
  }

  public static function get_slot_interval(): int {
    $settings = self::get_schedule_settings();
    $n = (int)($settings['slot_interval_minutes'] ?? 30);
    return max(5, min(120, $n));
  }

  public static function get_timezone(): string {
    $settings = self::get_schedule_settings();
    return (string)($settings['timezone'] ?? 'Europe/Copenhagen');
  }

  private static function normalize_breaks($breaks): array {
    if (!is_array($breaks)) return [];
    $out = [];
    foreach ($breaks as $b) {
      $st = isset($b['start']) ? $b['start'] : ($b['start_time'] ?? '');
      $et = isset($b['end']) ? $b['end'] : ($b['end_time'] ?? '');
      $st = substr(trim((string)$st), 0, 5);
      $et = substr(trim((string)$et), 0, 5);
      if (!self::valid_hhmm($st) || !self::valid_hhmm($et)) continue;
      if (self::to_minutes($et) <= self::to_minutes($st)) continue;
      $out[] = ['start' => $st, 'end' => $et];
    }
    return $out;
  }

  private static function get_schedule_rows(int $agent_id, int $weekday): array {
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names in this function are validated local plugin table names built from hardcoded plugin suffixes.
    global $wpdb;
    $schedule_table = $wpdb->prefix . 'pointlybooking_schedules';
    if (!self::table_exists($schedule_table)) return [];
    if (!self::is_safe_sql_identifier($schedule_table)) return [];

    $rows = [];
    if ($agent_id > 0) {
      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
      $rows = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM {$schedule_table} WHERE agent_id=%d AND day_of_week=%d ORDER BY start_time ASC", $agent_id, $weekday),
        ARRAY_A
      ) ?: [];
    }

    if (empty($rows)) {
      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
      $rows = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM {$schedule_table} WHERE agent_id IS NULL AND day_of_week=%d ORDER BY start_time ASC", $weekday),
        ARRAY_A
      ) ?: [];
    }

    return $rows;
  }

  public static function get_day_windows(int $agent_id, string $date_ymd): array {
    $weekday = self::weekday_from_date($date_ymd);
    $rows = self::get_schedule_rows($agent_id, $weekday);

    $windows = [];
    foreach ($rows as $r) {
      if ((int)($r['is_enabled'] ?? 1) !== 1) continue;
      $st = substr((string)($r['start_time'] ?? ''), 0, 5);
      $et = substr((string)($r['end_time'] ?? ''), 0, 5);
      if (!self::valid_hhmm($st) || !self::valid_hhmm($et)) continue;
      if (self::to_minutes($et) <= self::to_minutes($st)) continue;

      $breaks = [];
      $raw = $r['breaks_json'] ?? null;
      if ($raw) {
        $decoded = json_decode((string)$raw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
          $breaks = self::normalize_breaks($decoded);
        }
      }

      $windows[] = [
        'start_time' => $st,
        'end_time' => $et,
        'breaks' => $breaks,
      ];
    }

    if (!empty($windows)) return $windows;

    // Legacy fallback: per-agent working hours + date-based breaks
    $legacy_hours = self::get_working_hours($agent_id, $weekday);
    $legacy_breaks = self::get_breaks($agent_id, $date_ymd);
    if (!empty($legacy_hours)) {
      foreach ($legacy_hours as $h) {
        $windows[] = [
          'start_time' => substr((string)($h['start_time'] ?? ''), 0, 5),
          'end_time' => substr((string)($h['end_time'] ?? ''), 0, 5),
          'breaks' => array_map(function($b){
            return [
              'start' => substr((string)$b['start_time'], 0, 5),
              'end' => substr((string)$b['end_time'], 0, 5),
            ];
          }, $legacy_breaks),
        ];
      }
      return $windows;
    }

    // Global fallback from settings schedule
    $day = self::get_day_schedule($date_ymd);
    if (empty($day)) return [];
    return [[
      'start_time' => $day['open'],
      'end_time' => $day['close'],
      'breaks' => self::get_break_ranges(),
    ]];
  }

  public static function time_in_break(string $time_hm) : bool {
    if (!self::valid_hhmm($time_hm)) return false;

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

    $parsed = self::parse_hhmm_range($raw);
    if ($parsed === null) return [];
    [$open, $close] = $parsed;
    return ['open' => $open, 'close' => $close];
  }

  // weekday: 1=Mon .. 7=Sun
  public static function weekday_from_date(string $date) : int {
    $ts = strtotime($date);
    return $ts ? (int)gmdate('N', $ts) : 1;
  }

  public static function get_working_hours(int $agent_id, int $weekday) : array {
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names in this function are validated local plugin table names built from hardcoded plugin suffixes.
    global $wpdb;
    $working_hours_table = $wpdb->prefix . 'pointlybooking_agent_working_hours';

    // Prefer new schedules table if available
    $sched_rows = self::get_schedule_rows($agent_id, $weekday);
    if (!empty($sched_rows)) {
      $out = [];
      foreach ($sched_rows as $r) {
        if ((int)($r['is_enabled'] ?? 1) !== 1) continue;
        $st = substr((string)($r['start_time'] ?? ''), 0, 5);
        $et = substr((string)($r['end_time'] ?? ''), 0, 5);
        if (!self::valid_hhmm($st) || !self::valid_hhmm($et)) continue;
        $out[] = ['start_time' => $st, 'end_time' => $et];
      }
      if (!empty($out)) return $out;
    }

    $table_exists = pointlybooking_db_table_exists($working_hours_table);
    if (!$table_exists) {
      return [
        ['start_time' => '08:00', 'end_time' => '20:00'],
      ];
    }
    if (!self::is_safe_sql_identifier($working_hours_table)) {
      return [
        ['start_time' => '08:00', 'end_time' => '20:00'],
      ];
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $rows = $wpdb->get_results(
      $wpdb->prepare("SELECT start_time, end_time
         FROM {$working_hours_table}
         WHERE agent_id=%d AND weekday=%d AND is_enabled=1
         ORDER BY start_time ASC", $agent_id, $weekday),
      ARRAY_A
    );

    if (!empty($rows)) return $rows;

    return [
      ['start_time' => '08:00', 'end_time' => '20:00'],
    ];
  }

  public static function get_agent_weekly_schedule(int $agent_id) : array {
    $days = [
      1 => 'mon',
      2 => 'tue',
      3 => 'wed',
      4 => 'thu',
      5 => 'fri',
      6 => 'sat',
      7 => 'sun',
    ];

    $schedule = [];
    foreach ($days as $weekday => $key) {
      $rows = self::get_working_hours($agent_id, $weekday);
      $windows = [];
      foreach ($rows as $r) {
        $windows[] = [
          'start' => substr($r['start_time'], 0, 5),
          'end' => substr($r['end_time'], 0, 5),
        ];
      }
      $schedule[$key] = $windows;
    }

    return $schedule;
  }

  public static function get_breaks(int $agent_id, string $date) : array {
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names in this function are validated local plugin table names built from hardcoded plugin suffixes.
    global $wpdb;
    $breaks_table = $wpdb->prefix . 'pointlybooking_agent_breaks';

    $table_exists = pointlybooking_db_table_exists($breaks_table);
    if (!$table_exists) return [];
    if (!self::is_safe_sql_identifier($breaks_table)) return [];

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $rows = $wpdb->get_results(
      $wpdb->prepare("SELECT start_time, end_time
         FROM {$breaks_table}
         WHERE agent_id=%d AND break_date=%s
         ORDER BY start_time ASC", $agent_id, $date),
      ARRAY_A
    );

    return $rows ?: [];
  }

  public static function time_to_seconds(string $hms) : int {
    if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $hms)) $hms .= ':00';
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $hms)) return 0;
    [$h, $m, $s] = array_map('intval', explode(':', $hms));
    return $h * 3600 + $m * 60 + $s;
  }

  public static function to_minutes(string $hhmm) : int {
    $hhmm = substr((string)$hhmm, 0, 5);
    [$h, $m] = array_pad(explode(':', $hhmm), 2, 0);
    return ((int)$h) * 60 + (int)$m;
  }

  public static function to_hhmm(int $minutes) : string {
    $h = (int)floor($minutes / 60);
    $m = $minutes % 60;
    return str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':' . str_pad((string)$m, 2, '0', STR_PAD_LEFT);
  }

  public static function is_date_closed(string $date, int $agent_id = 0) : bool {
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names in this function are validated local plugin table names built from hardcoded plugin suffixes.
    global $wpdb;
    $holidays_table = $wpdb->prefix . 'pointlybooking_holidays';

    $date = substr($date, 0, 10);
    if (!self::is_safe_sql_identifier($holidays_table)) return false;

    $agent_id = (int)$agent_id;
    if ($agent_id > 0) {
      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
      $count = (int)$wpdb->get_var(
        $wpdb->prepare(
          "SELECT COUNT(*)
         FROM {$holidays_table}
         WHERE (is_enabled=1 OR is_enabled IS NULL)
           AND (agent_id IS NULL OR agent_id = %d)
           AND (
             (%s BETWEEN start_date AND end_date)
             OR (
               (is_recurring=1 OR is_recurring_yearly=1)
               AND (
                 DATE_FORMAT(%s, '%%m-%%d') BETWEEN DATE_FORMAT(start_date,'%%m-%%d') AND DATE_FORMAT(end_date,'%%m-%%d')
               )
             )
            )",
          $agent_id,
          $date,
          $date
        )
      );
    } else {
      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
      $count = (int)$wpdb->get_var(
        $wpdb->prepare(
          "SELECT COUNT(*)
         FROM {$holidays_table}
         WHERE (is_enabled=1 OR is_enabled IS NULL)
           AND agent_id IS NULL
           AND (
             (%s BETWEEN start_date AND end_date)
             OR (
               (is_recurring=1 OR is_recurring_yearly=1)
               AND (
                 DATE_FORMAT(%s, '%%m-%%d') BETWEEN DATE_FORMAT(start_date,'%%m-%%d') AND DATE_FORMAT(end_date,'%%m-%%d')
               )
             )
            )",
          $date,
          $date
        )
      );
    }

    return $count > 0;
  }

  public static function get_service_rules(int $service_id) : array {
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names in this function are validated local plugin table names built from hardcoded plugin suffixes.
    global $wpdb;
    $services_table = $wpdb->prefix . 'pointlybooking_services';
    if (!self::is_safe_sql_identifier($services_table)) {
      return [
        'duration' => 30,
        'buffer_before' => 0,
        'buffer_after' => 0,
        'capacity' => 1,
        'occupied_min' => 30,
      ];
    }

    $cols = pointlybooking_db_table_columns($services_table);
    $has_duration_minutes = in_array('duration_minutes', $cols, true);
    $has_duration = in_array('duration', $cols, true);
    $has_buffer_before_minutes = in_array('buffer_before_minutes', $cols, true);
    $has_buffer_after_minutes = in_array('buffer_after_minutes', $cols, true);
    $has_buffer_before = in_array('buffer_before', $cols, true);
    $has_buffer_after = in_array('buffer_after', $cols, true);

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $row = $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM {$services_table} WHERE id=%d", $service_id),
      ARRAY_A
    );

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
    if (self::is_date_closed($date, $agent_id)) return false;

    $windows = self::get_day_windows($agent_id, $date);
    if (empty($windows)) return false;

    $startSec = self::time_to_seconds($start_time);
    $endSec   = $startSec + ($duration_min * 60);

    foreach ($windows as $w) {
      $ws = self::time_to_seconds($w['start_time']);
      $we = self::time_to_seconds($w['end_time']);
      if ($startSec < $ws || $endSec > $we) continue;

      $blocked = false;
      $breaks = $w['breaks'] ?? [];
      foreach ($breaks as $b) {
        $bs = self::time_to_seconds($b['start']);
        $be = self::time_to_seconds($b['end']);
        if ($startSec < $be && $endSec > $bs) { $blocked = true; break; }
      }
      if ($blocked) continue;

      return true;
    }

    return false;
  }

  public static function build_unavailable_blocks(int $agent_id, string $from, string $to) : array {
    $blocks = [];
    $cur = strtotime($from);
    $end = strtotime($to);

    while ($cur <= $end) {
      $date = gmdate('Y-m-d', $cur);
      if (self::is_date_closed($date, $agent_id)) {
        $blocks[] = ['start' => $date . 'T00:00:00', 'end' => $date . 'T23:59:59'];
      } else {
        $windows = self::get_day_windows($agent_id, $date);

        if (empty($windows)) {
        $blocks[] = ['start' => $date . 'T00:00:00', 'end' => $date . 'T23:59:59'];
        } else {
          $intervals = [];
          foreach ($windows as $w) {
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

          foreach ($windows as $w) {
            foreach (($w['breaks'] ?? []) as $b) {
              $blocks[] = [
                'start' => $date . 'T' . substr($b['start'], 0, 5) . ':00',
                'end'   => $date . 'T' . substr($b['end'], 0, 5) . ':00',
              ];
            }
          }
        }
      }

      $cur = strtotime('+1 day', $cur);
    }

    return $blocks;
  }
}

