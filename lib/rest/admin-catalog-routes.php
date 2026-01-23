<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {

  // Services list for dropdown
  register_rest_route('bp/v1', '/admin/services', [
    'methods'  => 'GET',
    'callback' => 'bp_rest_admin_services_list',
    'permission_callback' => function () {
      return current_user_can('bp_manage_bookings')
        || current_user_can('bp_manage_services')
        || current_user_can('bp_manage_settings')
        || current_user_can('manage_options');
    },
  ]);

  // Availability slots for reschedule picker
  // GET /bp/v1/admin/availability/slots?service_id=1&agent_id=2&date=YYYY-MM-DD
  register_rest_route('bp/v1', '/admin/availability/slots', [
    'methods'  => 'GET',
    'callback' => 'bp_rest_admin_availability_slots',
    'permission_callback' => function () {
      return current_user_can('bp_manage_bookings');
    },
    'args' => [
      'service_id' => ['required' => true],
      'agent_id'   => ['required' => true],
      'date'       => ['required' => true],
      'exclude_booking_id' => ['required' => false],
    ],
  ]);
});

function bp_rest_admin_services_list(WP_REST_Request $req) {
  global $wpdb;
  $t = $wpdb->prefix . 'bp_services';

  $where = '1=1';
  $params = [];
  if ($req->get_param('q')) {
    $where .= ' AND name LIKE %s';
    $params[] = '%' . $wpdb->esc_like(sanitize_text_field($req->get_param('q'))) . '%';
  }

  $sql = "SELECT * FROM {$t} WHERE {$where} ORDER BY sort_order ASC, id DESC";
  $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
  $rows = $rows ?: [];

  foreach ($rows as &$r) {
    $r['image_id'] = (int)($r['image_id'] ?? 0);
    $r['image_url'] = bp_img_url($r['image_id'], 'medium');
    $r['sort_order'] = (int)($r['sort_order'] ?? 0);
    $r['duration'] = (int)($r['duration'] ?? 30);
    $r['duration_minutes'] = (int)($r['duration_minutes'] ?? 30);
    $r['price'] = isset($r['price']) ? (float)$r['price'] : 0.0;
    $r['buffer_before'] = (int)($r['buffer_before'] ?? 0);
    $r['buffer_after']  = (int)($r['buffer_after'] ?? 0);
    $r['capacity']      = (int)($r['capacity'] ?? 1);
  }

  return new WP_REST_Response(['status'=>'success','data'=>$rows], 200);
}

/**
 * Basic availability generator:
 * - Assumes working hours 09:00-17:00 for now (we will make admin-config later)
 * - Excludes conflicts for the agent
 * - Produces slots every 15 minutes
 */
function bp_rest_admin_availability_slots(WP_REST_Request $req) {
  global $wpdb;

  $service_id = (int)$req->get_param('service_id');
  $agent_id   = (int)$req->get_param('agent_id');
  $date       = sanitize_text_field($req->get_param('date'));
  $exclude_id = (int)($req->get_param('exclude_booking_id') ?: 0);

  if ($service_id <= 0 || $agent_id < 0) return new WP_REST_Response(['status'=>'error','message'=>'Invalid service_id/agent_id'], 400);
  $date = substr($date, 0, 10);
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return new WP_REST_Response(['status'=>'error','message'=>'Invalid date'], 400);

  $t_book = $wpdb->prefix . 'bp_bookings';
  $t_srv  = $wpdb->prefix . 'bp_services';

  $rules = BP_ScheduleHelper::get_service_rules($service_id);
  $dur = (int)$rules['duration'];
  $occupied = (int)$rules['occupied_min'];
  $capacity = (int)$rules['capacity'];

  if (BP_ScheduleHelper::is_date_closed($date, $agent_id)) {
    return new WP_REST_Response(['status'=>'success','data'=>[
      'date'=>$date,
      'service_id'=>$service_id,
      'agent_id'=>$agent_id,
      'duration_min'=>$dur,
      'buffer_before'=>$rules['buffer_before'],
      'buffer_after'=>$rules['buffer_after'],
      'capacity'=>$capacity,
      'slots'=>[]
    ]], 200);
  }

  // helper to create slots for a specific agent
  $make_slots_for_agent = function(int $aId) use ($wpdb, $date, $occupied, $capacity, $exclude_id) {
    $weekday = BP_ScheduleHelper::weekday_from_date($date);
    $work = BP_ScheduleHelper::get_working_hours($aId, $weekday);
    if (empty($work)) return [];

    $breaks = BP_ScheduleHelper::get_breaks($aId, $date);

    // Busy windows from existing bookings
    $t_book = $wpdb->prefix . 'bp_bookings';
    $t_srv  = $wpdb->prefix . 'bp_services';

    $params = [$date, $aId];
    $exclude_sql = '';
    if ($exclude_id > 0) { $exclude_sql = ' AND b.id <> %d '; $params[] = $exclude_id; }

    $rows = $wpdb->get_results($wpdb->prepare("
      SELECT
        STR_TO_DATE(CONCAT(b.start_date,' ',b.start_time), '%%Y-%%m-%%d %%H:%%i:%%s') as start_dt,
        DATE_ADD(
          STR_TO_DATE(CONCAT(b.start_date,' ',b.start_time), '%%Y-%%m-%%d %%H:%%i:%%s'),
          INTERVAL (COALESCE(s.duration,30) + COALESCE(s.buffer_before,0) + COALESCE(s.buffer_after,0)) MINUTE
        ) as end_dt
      FROM {$t_book} b
      LEFT JOIN {$t_srv} s ON s.id = b.service_id
      WHERE b.start_date = %s
        AND b.agent_id = %d
        {$exclude_sql}
        AND b.status IN ('pending','confirmed')
      ORDER BY b.start_time ASC
    ", $params), ARRAY_A);

    $busy = [];
    foreach ($rows as $r) {
      $busy[] = ['start'=>strtotime($r['start_dt']), 'end'=>strtotime($r['end_dt'])];
    }

    $breakBusy = [];
    foreach ($breaks as $b) {
      $bs = strtotime($date.' '.$b['start_time']);
      $be = strtotime($date.' '.$b['end_time']);
      if ($bs && $be) $breakBusy[] = ['start'=>$bs, 'end'=>$be];
    }

    // Add breaks to busy for capacity=1 conflict checks
    foreach ($breakBusy as $bb) {
      $busy[] = $bb;
    }

    $conflicts = function($start, $end) use ($busy) {
      foreach ($busy as $b) {
        if ($start < $b['end'] && $end > $b['start']) return true;
      }
      return false;
    };

    $conflictsBreak = function($start, $end) use ($breakBusy) {
      foreach ($breakBusy as $b) {
        if ($start < $b['end'] && $end > $b['start']) return true;
      }
      return false;
    };

    $slots = [];
    $step = 15 * 60;

    // For each working interval, generate slots
    foreach ($work as $w) {
      $ws = strtotime($date.' '.$w['start_time']);
      $we = strtotime($date.' '.$w['end_time']);
      if (!$ws || !$we || $we <= $ws) continue;

      for ($t = $ws; $t + ($occupied*60) <= $we; $t += $step) {
        $slot_end = $t + ($occupied*60);

        if ($capacity <= 1) {
          if (!$conflicts($t, $slot_end)) {
            $slots[] = [
              'time' => date('H:i', $t),
              'start' => date('Y-m-d\TH:i:s', $t),
              'end'   => date('Y-m-d\TH:i:s', $slot_end),
              'agent_id' => $aId,
            ];
          }
        } else {
          if ($conflictsBreak($t, $slot_end)) {
            continue;
          }
          $start_iso_full = date('Y-m-d\TH:i:s', $t);
          $end_iso_full   = date('Y-m-d\TH:i:s', $slot_end);

          $overlapCount = (int)$wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$t_book} b
            LEFT JOIN {$t_srv} s ON s.id=b.service_id
            WHERE b.start_date=%s
              AND b.agent_id=%d
              {$exclude_sql}
              AND b.status IN ('pending','confirmed')
              AND (
                STR_TO_DATE(CONCAT(b.start_date,' ',b.start_time), '%%Y-%%m-%%d %%H:%%i:%%s')
                < STR_TO_DATE(%s, '%%Y-%%m-%%dT%%H:%%i:%%s')
              )
              AND (
                DATE_ADD(
                  STR_TO_DATE(CONCAT(b.start_date,' ',b.start_time), '%%Y-%%m-%%d %%H:%%i:%%s'),
                  INTERVAL (COALESCE(s.duration,30)
                            + COALESCE(s.buffer_before,0)
                            + COALESCE(s.buffer_after,0)) MINUTE
                )
                > STR_TO_DATE(%s, '%%Y-%%m-%%dT%%H:%%i:%%s')
              )
          ", $date, $aId, $end_iso_full, $start_iso_full));

          if ($overlapCount < $capacity) {
            $slots[] = [
              'time' => date('H:i', $t),
              'start' => date('Y-m-d\TH:i:s', $t),
              'end'   => date('Y-m-d\TH:i:s', $slot_end),
              'agent_id' => $aId,
            ];
          }
        }
      }
    }

    return $slots;
  };

  // Any agent: merge slots across agents
  if ($agent_id === 0) {
    $t_agents = $wpdb->prefix . 'bp_agents';
    $agent_rows = $wpdb->get_results("SELECT id, name FROM {$t_agents} ORDER BY name ASC", ARRAY_A) ?: [];

    $all = [];
    foreach ($agent_rows as $ar) {
      $aId = (int)$ar['id'];
      $slots = $make_slots_for_agent($aId);
      foreach ($slots as $s) {
        $s['agent_name'] = $ar['name'];
        $all[] = $s;
      }
    }

    // sort by time then agent
    usort($all, function($a,$b){
      if ($a['start'] === $b['start']) return ($a['agent_id'] <=> $b['agent_id']);
      return strcmp($a['start'], $b['start']);
    });

    return new WP_REST_Response(['status'=>'success','data'=>[
      'date'=>$date,
      'service_id'=>$service_id,
      'agent_id'=>0,
      'duration_min'=>$dur,
      'buffer_before'=>$rules['buffer_before'],
      'buffer_after'=>$rules['buffer_after'],
      'capacity'=>$capacity,
      'slots'=>$all
    ]], 200);
  }

  // Specific agent
  $slots = $make_slots_for_agent($agent_id);

  return new WP_REST_Response(['status'=>'success','data'=>[
    'date'=>$date,
    'service_id'=>$service_id,
    'agent_id'=>$agent_id,
    'duration_min'=>$dur,
    'buffer_before'=>$rules['buffer_before'],
    'buffer_after'=>$rules['buffer_after'],
    'capacity'=>$capacity,
    'slots'=>$slots
  ]], 200);
}
