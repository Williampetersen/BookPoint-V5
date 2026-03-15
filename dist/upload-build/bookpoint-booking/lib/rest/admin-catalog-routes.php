<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {

  // Services list for dropdown
  register_rest_route('pointly-booking/v1', '/admin/services', [
    'methods'  => 'GET',
    'callback' => 'pointlybooking_rest_admin_services_list',
    'permission_callback' => function () {
      return current_user_can('pointlybooking_manage_bookings')
        || current_user_can('pointlybooking_manage_services')
        || current_user_can('pointlybooking_manage_settings')
        || current_user_can('manage_options');
    },
  ]);

  // Availability slots for reschedule picker
  // GET /pointly-booking/v1/admin/availability/slots?service_id=1&agent_id=2&date=YYYY-MM-DD
  register_rest_route('pointly-booking/v1', '/admin/availability/slots', [
    'methods'  => 'GET',
    'callback' => 'pointlybooking_rest_admin_availability_slots',
    'permission_callback' => function () {
      return current_user_can('pointlybooking_manage_bookings');
    },
    'args' => [
      'service_id' => ['required' => true],
      'agent_id'   => ['required' => true],
      'date'       => ['required' => true],
      'exclude_booking_id' => ['required' => false],
    ],
  ]);
});

function pointlybooking_rest_admin_services_list(WP_REST_Request $req) {
  global $wpdb;
  $t = pointlybooking_table('services');
  if (!preg_match('/^[A-Za-z0-9_]+$/', $t)) {
    return new WP_REST_Response(['status'=>'success','data'=>[]], 200);
  }

  $quoted_table = '`' . str_replace('`', '``', $t) . '`';
  $cols = $wpdb->get_col("SHOW COLUMNS FROM {$quoted_table}", 0);
  if (!is_array($cols)) $cols = [];
  $has = function(string $col) use ($cols): bool { return in_array($col, $cols, true); };

  $search = $req->get_param('q') ? '%' . $wpdb->esc_like(sanitize_text_field($req->get_param('q'))) . '%' : '';
  $has_search = $search !== '' ? 1 : 0;

  if ($has('sort_order')) {
    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT * FROM {$quoted_table} WHERE (%d = 0 OR name LIKE %s) ORDER BY sort_order ASC, id DESC",
        $has_search,
        $search
      ),
      ARRAY_A
    ) ?: [];
  } else {
    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT * FROM {$quoted_table} WHERE (%d = 0 OR name LIKE %s) ORDER BY id DESC",
        $has_search,
        $search
      ),
      ARRAY_A
    ) ?: [];
  }

  foreach ($rows as &$r) {
    $r['image_id'] = (int)($r['image_id'] ?? 0);
    $r['image_url'] = pointlybooking_img_url($r['image_id'], 'medium');
    $r['sort_order'] = (int)($r['sort_order'] ?? 0);

    $dur = 30;
    if ($has('duration_minutes')) $dur = (int)($r['duration_minutes'] ?? 30);
    elseif ($has('duration')) $dur = (int)($r['duration'] ?? 30);
    $r['duration_minutes'] = $dur;
    $r['duration'] = $dur;

    $priceCents = 0;
    if ($has('price_cents')) $priceCents = (int)($r['price_cents'] ?? 0);
    elseif (isset($r['price'])) $priceCents = (int)round(((float)$r['price']) * 100);
    $r['price_cents'] = $priceCents;
    $r['price'] = $priceCents / 100;

    $r['buffer_before'] = $has('buffer_before_minutes')
      ? (int)($r['buffer_before_minutes'] ?? 0)
      : (int)($r['buffer_before'] ?? 0);
    $r['buffer_after']  = $has('buffer_after_minutes')
      ? (int)($r['buffer_after_minutes'] ?? 0)
      : (int)($r['buffer_after'] ?? 0);
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
function pointlybooking_rest_admin_availability_slots(WP_REST_Request $req) {
  global $wpdb;

  $service_id = (int)$req->get_param('service_id');
  $agent_id   = (int)$req->get_param('agent_id');
  $date       = sanitize_text_field($req->get_param('date'));
  $exclude_id = (int)($req->get_param('exclude_booking_id') ?: 0);

  if ($service_id <= 0 || $agent_id < 0) return new WP_REST_Response(['status'=>'error','message'=>'Invalid service_id/agent_id'], 400);
  $date = substr($date, 0, 10);
  if (!function_exists('pointlybooking_is_valid_ymd') || !pointlybooking_is_valid_ymd($date)) {
    return new WP_REST_Response(['status'=>'error','message'=>'Invalid date'], 400);
  }

  $t_book = pointlybooking_table('bookings');
  $t_srv  = pointlybooking_table('services');
  if (!preg_match('/^[A-Za-z0-9_]+$/', $t_book) || !preg_match('/^[A-Za-z0-9_]+$/', $t_srv)) {
    return new WP_REST_Response(['status'=>'success','data'=>[
      'date'=>$date,
      'service_id'=>$service_id,
      'agent_id'=>$agent_id,
      'duration_min'=>0,
      'buffer_before'=>0,
      'buffer_after'=>0,
      'capacity'=>1,
      'slots'=>[]
    ]], 200);
  }

  $quoted_bookings = '`' . str_replace('`', '``', $t_book) . '`';
  $quoted_services = '`' . str_replace('`', '``', $t_srv) . '`';
  $sCols = $wpdb->get_col("SHOW COLUMNS FROM {$quoted_services}", 0);
  if (!is_array($sCols)) $sCols = [];
  $hasS = function(string $col) use ($sCols): bool { return in_array($col, $sCols, true); };

  $rules = POINTLYBOOKING_ScheduleHelper::get_service_rules($service_id);
  $dur = (int)$rules['duration'];
  $occupied = (int)$rules['occupied_min'];
  $capacity = (int)$rules['capacity'];

  if (POINTLYBOOKING_ScheduleHelper::is_date_closed($date, $agent_id)) {
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
  $make_slots_for_agent = function(int $aId) use ($wpdb, $date, $occupied, $capacity, $exclude_id, $hasS, $quoted_bookings, $quoted_services) {
    $weekday = POINTLYBOOKING_ScheduleHelper::weekday_from_date($date);
    $work = POINTLYBOOKING_ScheduleHelper::get_working_hours($aId, $weekday);
    if (empty($work)) return [];

    $breaks = POINTLYBOOKING_ScheduleHelper::get_breaks($aId, $date);

    if ($exclude_id > 0) {
      $rows = $wpdb->get_results(
        $wpdb->prepare(
          "SELECT
            b.start_date,
            b.start_time,
            s.*
          FROM {$quoted_bookings} b
          LEFT JOIN {$quoted_services} s ON s.id = b.service_id
          WHERE b.start_date = %s
            AND b.agent_id = %d
            AND b.id <> %d
            AND b.status IN ('pending','confirmed')
          ORDER BY b.start_time ASC",
          $date,
          $aId,
          $exclude_id
        ),
        ARRAY_A
      ) ?: [];
    } else {
      $rows = $wpdb->get_results(
        $wpdb->prepare(
          "SELECT
            b.start_date,
            b.start_time,
            s.*
          FROM {$quoted_bookings} b
          LEFT JOIN {$quoted_services} s ON s.id = b.service_id
          WHERE b.start_date = %s
            AND b.agent_id = %d
            AND b.status IN ('pending','confirmed')
          ORDER BY b.start_time ASC",
          $date,
          $aId
        ),
        ARRAY_A
      ) ?: [];
    }

    $busy_bookings = [];
    foreach ($rows as $r) {
      $start_at = strtotime((string) (($r['start_date'] ?? '') . ' ' . ($r['start_time'] ?? '')));
      if (!$start_at) {
        continue;
      }

      $service_duration = $hasS('duration_minutes')
        ? (int)($r['duration_minutes'] ?? 30)
        : ($hasS('duration') ? (int)($r['duration'] ?? 30) : 30);
      $service_buffer_before = $hasS('buffer_before_minutes')
        ? (int)($r['buffer_before_minutes'] ?? 0)
        : ($hasS('buffer_before') ? (int)($r['buffer_before'] ?? 0) : 0);
      $service_buffer_after = $hasS('buffer_after_minutes')
        ? (int)($r['buffer_after_minutes'] ?? 0)
        : ($hasS('buffer_after') ? (int)($r['buffer_after'] ?? 0) : 0);

      $busy_bookings[] = [
        'start' => $start_at,
        'end' => $start_at + (($service_duration + $service_buffer_before + $service_buffer_after) * 60),
      ];
    }
    $busy = $busy_bookings;

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
              'time' => gmdate('H:i', $t),
              'start' => gmdate('Y-m-d\TH:i:s', $t),
              'end'   => gmdate('Y-m-d\TH:i:s', $slot_end),
              'agent_id' => $aId,
            ];
          }
        } else {
          if ($conflictsBreak($t, $slot_end)) {
            continue;
          }
          $overlapCount = 0;
          foreach ($busy_bookings as $busy_window) {
            if ($t < $busy_window['end'] && $slot_end > $busy_window['start']) {
              $overlapCount++;
            }
          }

          if ($overlapCount < $capacity) {
            $slots[] = [
              'time' => gmdate('H:i', $t),
              'start' => gmdate('Y-m-d\TH:i:s', $t),
              'end'   => gmdate('Y-m-d\TH:i:s', $slot_end),
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
    $t_agents = $wpdb->prefix . 'pointlybooking_agents';
    if (!preg_match('/^[A-Za-z0-9_]+$/', $t_agents)) {
      return new WP_REST_Response(['status'=>'success','data'=>[
        'date'=>$date,
        'service_id'=>$service_id,
        'agent_id'=>0,
        'duration_min'=>$dur,
        'buffer_before'=>$rules['buffer_before'],
        'buffer_after'=>$rules['buffer_after'],
        'capacity'=>$capacity,
        'slots'=>[]
      ]], 200);
    }

    $quoted_agents = '`' . str_replace('`', '``', $t_agents) . '`';
    $agent_rows = $wpdb->get_results(
      "SELECT id, name FROM {$quoted_agents} ORDER BY name ASC",
      ARRAY_A
    ) ?: [];

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

