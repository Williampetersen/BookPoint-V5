<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
  register_rest_route('bp/v1', '/front/availability/month', [
    'methods'  => 'POST',
    'callback' => 'bp_front_availability_month',
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('bp/v1', '/front/availability/day', [
    'methods'  => 'POST',
    'callback' => 'bp_front_availability_day',
    'permission_callback' => '__return_true',
  ]);
});

function bp_front_availability_month(WP_REST_Request $req) {
  $p = $req->get_json_params();
  if (!is_array($p)) $p = [];

  $service_id  = isset($p['service_id']) ? (int)$p['service_id'] : 0;
  $agent_id    = isset($p['agent_id']) ? (int)$p['agent_id'] : 0;
  $location_id = isset($p['location_id']) ? (int)$p['location_id'] : 0;
  $month       = isset($p['month']) ? sanitize_text_field($p['month']) : '';

  if (!$month || !preg_match('/^\d{4}-\d{2}$/', $month)) {
    return new WP_REST_Response(['ok' => false, 'message' => 'Invalid month'], 400);
  }
  if ($service_id <= 0 || $agent_id <= 0) {
    return new WP_REST_Response(['ok' => false, 'message' => 'service_id and agent_id are required'], 400);
  }

  $cache_key = 'bp_av_month_' . md5(json_encode([$service_id, $agent_id, $location_id, $month]));
  $cached = get_transient($cache_key);
  if ($cached !== false) {
    return new WP_REST_Response(['ok' => true, 'data' => $cached], 200);
  }

  if (!function_exists('bp_rest_public_availability_slots')) {
    return new WP_REST_Response(['ok' => false, 'message' => 'Availability handler missing'], 500);
  }

  $start = $month . '-01';
  $start_ts = strtotime($start . ' 00:00:00');
  if ($start_ts === false) {
    return new WP_REST_Response(['ok' => false, 'message' => 'Invalid month'], 400);
  }
  $days_in_month = (int)date('t', $start_ts);

  $days = [];
  for ($d = 1; $d <= $days_in_month; $d++) {
    $date = sprintf('%s-%02d', $month, $d);

    $r = new WP_REST_Request('GET', '/bp/v1/public/availability-slots');
    $r->set_param('service_id', $service_id);
    $r->set_param('agent_id', $agent_id);
    $r->set_param('date', $date);
    $resp = bp_rest_public_availability_slots($r);

    $count = 0;
    if ($resp instanceof WP_REST_Response) {
      $payload = $resp->get_data();
      $slots = $payload['data'] ?? [];
      if (is_array($slots)) $count = count($slots);
    }

    $days[] = [
      'date' => $date,
      'has_slots' => $count > 0,
      'count' => $count,
    ];
  }

  $payload = [
    'month' => $month,
    'days' => $days,
  ];

  set_transient($cache_key, $payload, 60);
  return new WP_REST_Response(['ok' => true, 'data' => $payload], 200);
}

function bp_front_availability_day(WP_REST_Request $req) {
  $p = $req->get_json_params();
  if (!is_array($p)) $p = [];

  $service_id  = isset($p['service_id']) ? (int)$p['service_id'] : 0;
  $agent_id    = isset($p['agent_id']) ? (int)$p['agent_id'] : 0;
  $location_id = isset($p['location_id']) ? (int)$p['location_id'] : 0;
  $date        = isset($p['date']) ? sanitize_text_field($p['date']) : '';

  if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    return new WP_REST_Response(['ok' => false, 'message' => 'Invalid date'], 400);
  }
  if ($service_id <= 0 || $agent_id <= 0) {
    return new WP_REST_Response(['ok' => false, 'message' => 'service_id and agent_id are required'], 400);
  }

  $cache_key = 'bp_av_day_' . md5(json_encode([$service_id, $agent_id, $location_id, $date]));
  $cached = get_transient($cache_key);
  if ($cached !== false) {
    return new WP_REST_Response(['ok' => true, 'slots' => $cached], 200);
  }

  if (!function_exists('bp_rest_public_availability_slots')) {
    return new WP_REST_Response(['ok' => false, 'message' => 'Availability handler missing'], 500);
  }

  $r = new WP_REST_Request('GET', '/bp/v1/public/availability-slots');
  $r->set_param('service_id', $service_id);
  $r->set_param('agent_id', $agent_id);
  $r->set_param('date', $date);
  $resp = bp_rest_public_availability_slots($r);

  $normalized = [];
  if ($resp instanceof WP_REST_Response) {
    $payload = $resp->get_data();
    $slots = $payload['data'] ?? [];
    if (is_array($slots)) {
      foreach ($slots as $s) {
        $start = $s['start_time'] ?? ($s['start'] ?? ($s['time'] ?? ''));
        $end = $s['end_time'] ?? ($s['end'] ?? '');
        $start_ts = strtotime($start);
        $end_ts = strtotime($end);
        if ($start_ts) {
          $normalized[] = [
            'time' => date('H:i', $start_ts),
            'label' => $s['label'] ?? (date('H:i', $start_ts) . ($end_ts ? ' - ' . date('H:i', $end_ts) : '')),
            'start' => date('H:i', $start_ts),
            'end' => $end_ts ? date('H:i', $end_ts) : '',
          ];
        }
      }
    }
  }

  set_transient($cache_key, $normalized, 60);
  return new WP_REST_Response(['ok' => true, 'slots' => $normalized], 200);
}
