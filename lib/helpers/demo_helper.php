<?php
defined('ABSPATH') || exit;

final class BP_DemoHelper {

  public static function generate(int $services, int $agents, int $customers, int $bookings) : array {
    $services = max(1, min(50, $services));
    $agents = max(1, min(50, $agents));
    $customers = max(1, min(200, $customers));
    $bookings = max(1, min(500, $bookings));

    $service_ids = [];
    $agent_ids = [];
    $customer_ids = [];
    $booking_ids = [];

    for ($i = 1; $i <= $agents; $i++) {
      $agent_ids[] = BP_AgentModel::create([
        'first_name' => 'Agent',
        'last_name' => (string)$i,
        'email' => 'agent' . $i . '@example.test',
        'phone' => '+45000000' . str_pad((string)$i, 2, '0', STR_PAD_LEFT),
        'is_active' => 1,
        'schedule_json' => null,
      ]);
    }

    $durations = [30, 45, 60, 90];
    for ($i = 1; $i <= $services; $i++) {
      $service_ids[] = BP_ServiceModel::create([
        'name' => 'Service ' . $i,
        'description' => 'Demo service ' . $i,
        'duration_minutes' => $durations[array_rand($durations)],
        'price_cents' => (int)(1000 + $i * 100),
        'currency' => 'USD',
        'is_active' => 1,
        'capacity' => 1,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
        'use_global_schedule' => 1,
        'schedule_json' => null,
      ]);
    }

    if (class_exists('BP_ServiceAgentModel') && !empty($agent_ids)) {
      foreach ($service_ids as $sid) {
        $pick = array_rand(array_flip($agent_ids), min(count($agent_ids), max(1, random_int(1, min(3, count($agent_ids))))));
        $pick_ids = is_array($pick) ? $pick : [$pick];
        BP_ServiceAgentModel::set_agents_for_service((int)$sid, $pick_ids);
      }
    }

    for ($i = 1; $i <= $customers; $i++) {
      $customer_ids[] = BP_CustomerModel::create([
        'first_name' => 'Customer',
        'last_name' => (string)$i,
        'email' => 'customer' . $i . '@example.test',
        'phone' => '+45111111' . str_pad((string)$i, 2, '0', STR_PAD_LEFT),
      ]);
    }

    for ($i = 1; $i <= $bookings; $i++) {
      $sid = (int)$service_ids[array_rand($service_ids)];
      $aid = !empty($agent_ids) ? (int)$agent_ids[array_rand($agent_ids)] : 0;
      $cid = (int)$customer_ids[array_rand($customer_ids)];

      $service = BP_ServiceModel::find($sid);
      $dur = (int)($service['duration_minutes'] ?? 60);

      $day_offset = random_int(0, 13);
      $hour = random_int(9, 16);
      $minute = [0, 15, 30, 45][array_rand([0, 1, 2, 3])];

      $start = strtotime(date('Y-m-d', strtotime("+{$day_offset} days")) . sprintf(' %02d:%02d:00', $hour, $minute));
      $end = $start + ($dur * 60);

      $status = ['pending', 'confirmed', 'cancelled'][array_rand([0, 1, 2])];

      $bid = BP_BookingModel::create([
        'service_id' => $sid,
        'customer_id' => $cid,
        'agent_id' => $aid ?: null,
        'start_datetime' => date('Y-m-d H:i:s', $start),
        'end_datetime' => date('Y-m-d H:i:s', $end),
        'status' => $status,
        'notes' => '',
      ]);

      if ($bid) {
        BP_BookingModel::rotate_manage_token((int)$bid);
        $booking_ids[] = $bid;
      }
    }

    return [
      'services_created' => count($service_ids),
      'agents_created' => count($agent_ids),
      'customers_created' => count($customer_ids),
      'bookings_created' => count($booking_ids),
    ];
  }
}
