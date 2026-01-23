<?php
defined('ABSPATH') || exit;

final class BP_AvailabilityHelper {

  public static function generate_slots_for_date(
    string $date_ymd,
    int $interval_minutes = 15,
    string $open = '09:00',
    string $close = '17:00',
    array $breaks = []
  ) : array {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_ymd)) return [];

    $interval_minutes = max(5, min(120, $interval_minutes));

    $start_ts = strtotime($date_ymd . ' ' . $open);
    $end_ts   = strtotime($date_ymd . ' ' . $close);

    if (!$start_ts || !$end_ts || $end_ts <= $start_ts) return [];

    $slots = [];
    for ($t = $start_ts; $t < $end_ts; $t += $interval_minutes * 60) {
      $hm = date('H:i', $t);

      $in_break = false;
      foreach ($breaks as $br) {
        if ($hm >= $br['start'] && $hm < $br['end']) {
          $in_break = true;
          break;
        }
      }

      if (!$in_break) {
        $slots[] = $hm;
      }
    }
    return $slots;
  }

  public static function is_slot_available(int $service_id, string $start_dt, string $end_dt, int $capacity = 1, int $agent_id = 0) : bool {
    $capacity = max(1, min(50, $capacity));
    $count = self::overlapping_count($service_id, $start_dt, $end_dt, $agent_id);
    return ($count < $capacity);
  }

  // Step 15: Count overlapping bookings
  public static function overlapping_count(int $service_id, string $start_dt, string $end_dt, int $agent_id = 0) : int {
    global $wpdb;
    $table = $wpdb->prefix . 'bp_bookings';

    // Step 16: Filter by agent_id if provided
    if ($agent_id > 0) {
      return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table}
         WHERE service_id = %d AND agent_id = %d
           AND status != 'cancelled'
           AND start_datetime < %s
           AND end_datetime > %s",
        $service_id, $agent_id, $end_dt, $start_dt
      ));
    }

    return (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM {$table}
       WHERE service_id = %d
         AND status != 'cancelled'
         AND start_datetime < %s
         AND end_datetime > %s",
      $service_id, $end_dt, $start_dt
    ));
  }

  public static function overlapping_count_excluding_booking(int $service_id, string $start_dt, string $end_dt, int $agent_id = 0, int $exclude_booking_id = 0) : int {
    global $wpdb;
    $table = $wpdb->prefix . 'bp_bookings';

    if ($agent_id > 0) {
      return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table}
         WHERE service_id = %d AND agent_id = %d
           AND id != %d
           AND status != 'cancelled'
           AND start_datetime < %s
           AND end_datetime > %s",
        $service_id, $agent_id, $exclude_booking_id, $end_dt, $start_dt
      ));
    }

    return (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM {$table}
       WHERE service_id = %d
         AND id != %d
         AND status != 'cancelled'
         AND start_datetime < %s
         AND end_datetime > %s",
      $service_id, $exclude_booking_id, $end_dt, $start_dt
    ));
  }

  public static function is_slot_available_excluding_booking(int $service_id, string $start_dt, string $end_dt, int $capacity = 1, int $agent_id = 0, int $exclude_booking_id = 0) : bool {
    $capacity = max(1, min(50, $capacity));
    $count = self::overlapping_count_excluding_booking($service_id, $start_dt, $end_dt, $agent_id, $exclude_booking_id);
    return ($count < $capacity);
  }

  public static function get_available_slots_for_date(int $service_id, string $date_ymd, int $duration_minutes, int $agent_id = 0, int $exclude_booking_id = 0) : array {
    $service = BP_ServiceModel::find($service_id);
    if (!$service) return [];

    $day_schedule = BP_ScheduleHelper::get_service_day_schedule($service, $date_ymd);
    if (empty($day_schedule)) return [];

    $interval = BP_ScheduleHelper::get_slot_interval();
    $breaks = BP_ScheduleHelper::get_break_ranges();

    $slots = self::generate_slots_for_date(
      $date_ymd,
      $interval,
      $day_schedule['open'],
      $day_schedule['close'],
      $breaks
    );

    $capacity = (int)($service['capacity'] ?? 1);
    $buf_before = (int)($service['buffer_before_minutes'] ?? 0);
    $buf_after  = (int)($service['buffer_after_minutes'] ?? 0);

    $out = [];
    foreach ($slots as $hm) {
      $start_ts = strtotime($date_ymd . ' ' . $hm);
      if (!$start_ts) continue;

      $start_ts_adj = $start_ts - ($buf_before * 60);
      $end_ts_adj   = $start_ts + ($duration_minutes * 60) + ($buf_after * 60);

      $start_dt = date('Y-m-d H:i:s', $start_ts_adj);
      $end_dt   = date('Y-m-d H:i:s', $end_ts_adj);

      if (self::is_slot_available_excluding_booking($service_id, $start_dt, $end_dt, $capacity, $agent_id, $exclude_booking_id)) {
        $out[] = [
          'start' => date('Y-m-d H:i:s', $start_ts),
          'end' => date('Y-m-d H:i:s', $start_ts + ($duration_minutes * 60)),
          'label' => date('H:i', $start_ts),
        ];
      }
    }

    return $out;
  }

  public static function get_timeslots_for_date(int $service_id, string $date_ymd, int $agent_id = 0) : array {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_ymd)) return [];

    $service = BP_ServiceModel::find($service_id);
    if (!$service) return [];

    if (!class_exists('BP_ScheduleHelper')) return [];
    if (BP_ScheduleHelper::is_date_closed($date_ymd, $agent_id)) return [];

    $rules = BP_ScheduleHelper::get_service_rules($service_id);
    $interval = BP_ScheduleHelper::get_slot_interval();
    $windows = BP_ScheduleHelper::get_day_windows($agent_id, $date_ymd);
    if (empty($windows)) return [];

    $duration_minutes = (int)$rules['duration'];
    $buf_before = (int)$rules['buffer_before'];
    $buf_after  = (int)$rules['buffer_after'];
    $capacity   = (int)$rules['capacity'];

    $slots = [];
    foreach ($windows as $w) {
      $st = $w['start_time'] ?? '';
      $et = $w['end_time'] ?? '';
      if (!preg_match('/^\d{2}:\d{2}$/', $st) || !preg_match('/^\d{2}:\d{2}$/', $et)) continue;

      $start_ts = strtotime($date_ymd . ' ' . $st);
      $end_ts   = strtotime($date_ymd . ' ' . $et);
      if (!$start_ts || !$end_ts || $end_ts <= $start_ts) continue;

      $breaks = $w['breaks'] ?? [];

      for ($t = $start_ts; $t + ($duration_minutes * 60) <= $end_ts; $t += $interval * 60) {
        $hm = date('H:i', $t);

        // Skip if overlaps break
        $slot_start_min = BP_ScheduleHelper::to_minutes($hm);
        $slot_end_min = $slot_start_min + $duration_minutes;
        $in_break = false;
        foreach ($breaks as $br) {
          $bs = BP_ScheduleHelper::to_minutes($br['start'] ?? '');
          $be = BP_ScheduleHelper::to_minutes($br['end'] ?? '');
          if ($slot_start_min < $be && $slot_end_min > $bs) { $in_break = true; break; }
        }
        if ($in_break) continue;

        $start_ts_adj = $t - ($buf_before * 60);
        $end_ts_adj   = $t + ($duration_minutes * 60) + ($buf_after * 60);

        $start_dt = date('Y-m-d H:i:s', $start_ts_adj);
        $end_dt   = date('Y-m-d H:i:s', $end_ts_adj);

        if (self::is_slot_available($service_id, $start_dt, $end_dt, $capacity, $agent_id)) {
          $slots[] = $hm;
        }
      }
    }

    $slots = array_values(array_unique($slots));
    sort($slots);
    return $slots;
  }

  public static function remove_unavailable_slots(
    int $service_id,
    string $date_ymd,
    array $slots,
    int $duration_minutes,
    int $capacity = 1,
    int $buffer_before = 0,
    int $buffer_after = 0,
    int $agent_id = 0
  ) : array {

    $out = [];
    $capacity = max(1, min(50, $capacity));
    $buffer_before = max(0, min(240, $buffer_before));
    $buffer_after  = max(0, min(240, $buffer_after));

    foreach ($slots as $hm) {
      $start_ts = strtotime($date_ymd . ' ' . $hm);
      if (!$start_ts) continue;

      $start_ts_adj = $start_ts - ($buffer_before * 60);
      $end_ts_adj   = $start_ts + ($duration_minutes * 60) + ($buffer_after * 60);

      $start_dt = date('Y-m-d H:i:s', $start_ts_adj);
      $end_dt   = date('Y-m-d H:i:s', $end_ts_adj);

      if (self::is_slot_available($service_id, $start_dt, $end_dt, $capacity, $agent_id)) {
        $out[] = $hm;
      }
    }

    return $out;
  }
}

