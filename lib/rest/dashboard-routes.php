<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function(){

  register_rest_route('bp/v1', '/admin/dashboard', [
    'methods'  => 'GET',
    'permission_callback' => function(){
      return current_user_can('administrator') || current_user_can('bp_manage_bookings') || current_user_can('bp_manage_settings');
    },
    'callback' => function(WP_REST_Request $req){
      global $wpdb;

      $t_bookings  = $wpdb->prefix . 'bp_bookings';
      $t_services  = $wpdb->prefix . 'bp_services';
      $t_agents    = $wpdb->prefix . 'bp_agents';

      // Safety: tables might differ on your install
      $tables = $wpdb->get_col("SHOW TABLES");
      $has_bookings = in_array($t_bookings, $tables, true);
      $has_services = in_array($t_services, $tables, true);
      $has_agents   = in_array($t_agents, $tables, true);

      $todayStart = current_time('Y-m-d') . ' 00:00:00';
      $todayEnd   = current_time('Y-m-d') . ' 23:59:59';
      $next7End   = date('Y-m-d 23:59:59', strtotime(current_time('Y-m-d') . ' +7 days'));

      // ---- KPI numbers ----
      $bookings_today = 0;
      $upcoming_7d = 0;
      $pending = 0;
      $services_count = 0;
      $agents_count = 0;

      if ($has_bookings) {
        // bookings today
        $bookings_today = (int) $wpdb->get_var($wpdb->prepare(
          "SELECT COUNT(*) FROM {$t_bookings} WHERE start_datetime BETWEEN %s AND %s",
          $todayStart, $todayEnd
        ));

        // upcoming 7 days
        $upcoming_7d = (int) $wpdb->get_var($wpdb->prepare(
          "SELECT COUNT(*) FROM {$t_bookings} WHERE start_datetime BETWEEN %s AND %s",
          $todayStart, $next7End
        ));

        // pending
        $pending = (int) $wpdb->get_var(
          "SELECT COUNT(*) FROM {$t_bookings} WHERE LOWER(status)='pending'"
        );
      }

      if ($has_services) {
        $services_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t_services}");
      }
      if ($has_agents) {
        $agents_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t_agents}");
      }

      // ---- Recent bookings ----
      $recent = [];
      if ($has_bookings) {
        $rows = $wpdb->get_results("
          SELECT id, start_datetime, end_datetime, status, customer_name, service_name, agent_name
          FROM {$t_bookings}
          ORDER BY start_datetime DESC
          LIMIT 10
        ", ARRAY_A) ?: [];

        foreach($rows as $r){
          $recent[] = [
            'id' => (int)$r['id'],
            'start' => $r['start_datetime'],
            'end'   => $r['end_datetime'],
            'status'=> $r['status'] ?: 'pending',
            'customer_name' => $r['customer_name'] ?: '',
            'service_name'  => $r['service_name'] ?: '',
            'agent_name'    => $r['agent_name'] ?: '',
          ];
        }
      }

      // ---- Weekly chart data (last 7 days) ----
      // simple counts per day (works without complex schema)
      $chart = [];
      if ($has_bookings) {
        for($i=6; $i>=0; $i--){
          $day = date('Y-m-d', strtotime(current_time('Y-m-d') . " -{$i} days"));
          $c = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$t_bookings} WHERE start_datetime BETWEEN %s AND %s",
            $day.' 00:00:00', $day.' 23:59:59'
          ));
          $chart[] = ['day' => $day, 'count' => $c];
        }
      }

      return new WP_REST_Response([
        'status' => 'success',
        'data' => [
          'kpi' => [
            'bookings_today' => $bookings_today,
            'upcoming_7d'    => $upcoming_7d,
            'pending'        => $pending,
            'services'       => $services_count,
            'agents'         => $agents_count,
          ],
          'recent' => $recent,
          'chart7' => $chart,
        ]
      ], 200);
    }
  ]);

});
