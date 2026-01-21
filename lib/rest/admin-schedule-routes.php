<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {
  register_rest_route('bp/v1', '/admin/schedule/unavailable', [
    'methods' => 'GET',
    'callback' => 'bp_rest_admin_unavailable_blocks',
    'permission_callback' => function () { return current_user_can('bp_manage_bookings'); },
    'args' => [
      'start' => ['required'=>true],
      'end'   => ['required'=>true],
      'agent_id' => ['required'=>true],
    ],
  ]);
});

function bp_rest_admin_unavailable_blocks(WP_REST_Request $req) {
  $start = sanitize_text_field($req->get_param('start'));
  $end   = sanitize_text_field($req->get_param('end'));
  $agent_id = (int)$req->get_param('agent_id');

  $from = substr($start,0,10);
  $to   = substr($end,0,10);

  if ($agent_id <= 0) {
    // only show unavailable blocks when a specific agent is selected
    return new WP_REST_Response(['status'=>'success','data'=>[]], 200);
  }

  $blocks = BP_ScheduleHelper::build_unavailable_blocks($agent_id, $from, $to);

  // FullCalendar background events
  $events = array_map(function($b){
    return [
      'start'=>$b['start'],
      'end'=>$b['end'],
      'display'=>'background',
    ];
  }, $blocks);

  return new WP_REST_Response(['status'=>'success','data'=>$events], 200);
}
