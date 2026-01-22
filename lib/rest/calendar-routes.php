<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function(){

  register_rest_route('bp/v1', '/admin/calendar', [
    'methods' => 'GET',
    'callback' => function(WP_REST_Request $req){

      if (!current_user_can('bp_manage_bookings') && !current_user_can('administrator')) {
        return new WP_REST_Response(['status'=>'error','message'=>'Forbidden'], 403);
      }

      $start = sanitize_text_field($req->get_param('start') ?? '');
      $end   = sanitize_text_field($req->get_param('end') ?? '');

      // validate YYYY-MM-DD
      if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        return new WP_REST_Response(['status'=>'error','message'=>'Invalid date range'], 400);
      }

      global $wpdb;
      $t = $wpdb->prefix . 'bp_bookings';

      $rows = $wpdb->get_results($wpdb->prepare("
        SELECT
          id,
          start_datetime,
          end_datetime,
          status,
          customer_name,
          customer_email,
          service_name,
          agent_name
        FROM {$t}
        WHERE start_datetime >= %s
          AND start_datetime < %s
        ORDER BY start_datetime ASC
        LIMIT 2000
      ", $start . ' 00:00:00', $end . ' 23:59:59'), ARRAY_A) ?: [];

      $events = [];
      foreach($rows as $r){
        $events[] = [
          'id' => (int)$r['id'],
          'title' => trim(($r['service_name'] ?: 'Booking') . ' • ' . ($r['customer_name'] ?: 'Customer')),
          'start' => $r['start_datetime'],
          'end'   => $r['end_datetime'],
          'status'=> $r['status'] ?: 'pending',
          'customer_name' => $r['customer_name'],
          'customer_email'=> $r['customer_email'],
          'service_name'  => $r['service_name'],
          'agent_name'    => $r['agent_name'],
        ];
      }

      return new WP_REST_Response(['status'=>'success','data'=>$events], 200);
    }
  ]);

});
