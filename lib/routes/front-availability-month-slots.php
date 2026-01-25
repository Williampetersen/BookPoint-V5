<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
  register_rest_route('bp/v1', '/front/availability/month-slots', [
    'methods'  => 'POST',
    'callback' => 'bp_front_availability_month_slots',
    'permission_callback' => '__return_true',
  ]);
});

function bp_front_availability_month_slots(WP_REST_Request $req) {
  $p = $req->get_json_params();
  if (!is_array($p)) $p = [];

  $service_id  = intval($p['service_id'] ?? 0);
  $agent_id    = intval($p['agent_id'] ?? 0);
  $location_id = intval($p['location_id'] ?? 0);
  $interval    = max(5, intval($p['interval'] ?? 30));
  $month       = sanitize_text_field($p['month'] ?? '');

  if (!$service_id || !preg_match('/^\d{4}-\d{2}$/', $month)) {
    return new WP_REST_Response(['ok' => false, 'message' => 'Invalid request'], 400);
  }

  $cache_key = 'bp_av_ms_' . md5(json_encode([$service_id, $agent_id, $location_id, $interval, $month]));
  $cached = get_transient($cache_key);
  if ($cached !== false) {
    return new WP_REST_Response(['ok' => true, 'data' => $cached], 200);
  }

  if (!function_exists('bp_rest_public_availability_slots')) {
    return new WP_REST_Response(['ok' => false, 'message' => 'Availability handler missing'], 500);
  }

  $start_ts = strtotime($month . '-01 00:00:00');
  if ($start_ts === false) {
    return new WP_REST_Response(['ok' => false, 'message' => 'Invalid request'], 400);
  }
  $days_in_month = intval(date('t', $start_ts));

  $days = [];
  $slots_by_day = [];

  for ($d = 1; $d <= $days_in_month; $d++) {
    $date = sprintf('%s-%02d', $month, $d);

    $r = new WP_REST_Request('GET', '/bp/v1/public/availability-slots');
    $r->set_param('service_id', $service_id);
    $r->set_param('agent_id', $agent_id);
    $r->set_param('date', $date);
    $resp = bp_rest_public_availability_slots($r);

    $times = [];
    if ($resp instanceof WP_REST_Response) {
      $payload = $resp->get_data();
      $slots_raw = $payload['data'] ?? [];
      if (is_array($slots_raw)) {
        foreach ($slots_raw as $s) {
          $raw = $s['time'] ?? ($s['start'] ?? ($s['start_time'] ?? ''));
          $ts = $raw ? strtotime($raw) : false;
          if ($ts) $times[] = date('H:i', $ts);
        }
      }
    }

    $times = array_values(array_unique($times));
    sort($times);

    $slots_by_day[$date] = $times;
    $days[] = [
      'date' => $date,
      'has_slots' => count($times) > 0,
      'count' => count($times),
    ];
  }

  $payload = [
    'month' => $month,
    'days' => $days,
    'slots_by_day' => $slots_by_day,
  ];

  set_transient($cache_key, $payload, 300);
  return new WP_REST_Response(['ok' => true, 'data' => $payload], 200);
}

