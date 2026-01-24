<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {
  $namespace = 'bp/v1';
  $base = '/admin/notifications';

  register_rest_route($namespace, $base . '/workflows', [
    'methods' => 'GET',
    'callback' => 'bp_rest_admin_notifications_workflows_list',
    'permission_callback' => 'bp_rest_admin_notifications_can_manage',
  ]);

  register_rest_route($namespace, $base . '/workflows', [
    'methods' => 'POST',
    'callback' => 'bp_rest_admin_notifications_workflow_create',
    'permission_callback' => 'bp_rest_admin_notifications_can_manage',
  ]);

  register_rest_route($namespace, $base . '/workflows/(?P<id>\d+)', [
    'methods' => 'GET',
    'callback' => 'bp_rest_admin_notifications_workflow_get',
    'permission_callback' => 'bp_rest_admin_notifications_can_manage',
  ]);

  register_rest_route($namespace, $base . '/workflows/(?P<id>\d+)', [
    'methods' => 'PUT',
    'callback' => 'bp_rest_admin_notifications_workflow_update',
    'permission_callback' => 'bp_rest_admin_notifications_can_manage',
  ]);

  register_rest_route($namespace, $base . '/workflows/(?P<id>\d+)', [
    'methods' => 'DELETE',
    'callback' => 'bp_rest_admin_notifications_workflow_delete',
    'permission_callback' => 'bp_rest_admin_notifications_can_manage',
  ]);

  register_rest_route($namespace, $base . '/workflows/(?P<id>\d+)/actions', [
    'methods' => 'POST',
    'callback' => 'bp_rest_admin_notifications_action_create',
    'permission_callback' => 'bp_rest_admin_notifications_can_manage',
  ]);

  register_rest_route($namespace, $base . '/actions/(?P<id>\d+)', [
    'methods' => 'PUT',
    'callback' => 'bp_rest_admin_notifications_action_update',
    'permission_callback' => 'bp_rest_admin_notifications_can_manage',
  ]);

  register_rest_route($namespace, $base . '/actions/(?P<id>\d+)', [
    'methods' => 'DELETE',
    'callback' => 'bp_rest_admin_notifications_action_delete',
    'permission_callback' => 'bp_rest_admin_notifications_can_manage',
  ]);

  register_rest_route($namespace, $base . '/actions/(?P<id>\d+)/test', [
    'methods' => 'POST',
    'callback' => 'bp_rest_admin_notifications_action_test',
    'permission_callback' => 'bp_rest_admin_notifications_can_manage',
  ]);

  register_rest_route($namespace, '/smart-variables', [
    'methods' => 'GET',
    'callback' => 'bp_rest_smart_variables',
    'permission_callback' => 'bp_rest_admin_notifications_can_manage',
  ]);
});

function bp_rest_admin_notifications_can_manage(): bool {
  return current_user_can('bp_manage_settings') || current_user_can('manage_options');
}

function bp_rest_admin_notifications_workflows_list(\WP_REST_Request $request) {
  $params = [
    'search' => $request->get_param('q') ?? '',
    'status' => $request->get_param('status') ?? 'all',
    'event' => $request->get_param('event') ?? '',
    'page' => (int)($request->get_param('page') ?? 1),
    'per' => (int)($request->get_param('per') ?? 20),
  ];

  $data = BP_Notifications_Helper::list_workflows($params);
  return rest_ensure_response([
    'status' => 'success',
    'data' => $data,
  ]);
}

function bp_rest_admin_notifications_workflow_create(\WP_REST_Request $request) {
  if (method_exists('BP_Notifications_Helper', 'ensure_tables')) {
    BP_Notifications_Helper::ensure_tables();
  }
  $body = $request->get_json_params();
  $workflow = BP_Notifications_Helper::create_workflow([
    'name' => $body['name'] ?? '',
    'status' => $body['status'] ?? 'active',
    'event_key' => $body['event_key'] ?? '',
    'is_conditional' => !empty($body['is_conditional']),
    'conditions' => $body['conditions'] ?? [],
    'has_time_offset' => !empty($body['has_time_offset']),
    'time_offset_minutes' => $body['time_offset_minutes'] ?? 0,
  ]);

  if (!$workflow) {
    $msg = method_exists('BP_Notifications_Helper', 'last_error') ? BP_Notifications_Helper::last_error() : '';
    if (!$msg) {
      $msg = __('Could not create workflow.', 'bookpoint');
    }
    return new \WP_Error('bp_notifications_error', $msg, ['status' => 500]);
  }

  return rest_ensure_response(['status' => 'success', 'data' => $workflow], 201);
}

function bp_rest_admin_notifications_workflow_get(\WP_REST_Request $request) {
  $id = (int)$request['id'];
  $workflow = BP_Notifications_Helper::get_workflow($id);
  if (!$workflow) {
    return new \WP_Error('bp_notifications_not_found', __('Workflow not found.', 'bookpoint'), ['status' => 404]);
  }
  return rest_ensure_response(['status' => 'success', 'data' => $workflow]);
}

function bp_rest_admin_notifications_workflow_update(\WP_REST_Request $request) {
  $id = (int)$request['id'];
  $body = $request->get_json_params();
  $updated = BP_Notifications_Helper::update_workflow($id, [
    'name' => $body['name'] ?? null,
    'status' => $body['status'] ?? null,
    'event_key' => $body['event_key'] ?? null,
    'is_conditional' => array_key_exists('is_conditional', $body) ? $body['is_conditional'] : null,
    'conditions' => $body['conditions'] ?? null,
    'has_time_offset' => array_key_exists('has_time_offset', $body) ? $body['has_time_offset'] : null,
    'time_offset_minutes' => $body['time_offset_minutes'] ?? null,
  ]);
  if (!$updated) {
    return new \WP_Error('bp_notifications_update_failed', __('Could not update workflow.', 'bookpoint'), ['status' => 500]);
  }
  return rest_ensure_response(['status' => 'success']);
}

function bp_rest_admin_notifications_workflow_delete(\WP_REST_Request $request) {
  $id = (int)$request['id'];
  $deleted = BP_Notifications_Helper::delete_workflow($id);
  if (!$deleted) {
    return new \WP_Error('bp_notifications_delete_failed', __('Could not delete workflow.', 'bookpoint'), ['status' => 500]);
  }
  return rest_ensure_response(['status' => 'success']);
}

function bp_rest_admin_notifications_action_create(\WP_REST_Request $request) {
  $workflow_id = (int)$request['id'];
  $body = $request->get_json_params();
  $action = BP_Notifications_Helper::create_action($workflow_id, [
    'type' => $body['type'] ?? 'send_email',
    'status' => $body['status'] ?? 'active',
    'config' => is_array($body['config'] ?? null) ? $body['config'] : [],
  ]);
  if (!$action) {
    return new \WP_Error('bp_notifications_action_failed', __('Could not create action.', 'bookpoint'), ['status' => 500]);
  }
  return rest_ensure_response(['status' => 'success', 'data' => $action], 201);
}

function bp_rest_admin_notifications_action_update(\WP_REST_Request $request) {
  $id = (int)$request['id'];
  $body = $request->get_json_params();
  $updated = BP_Notifications_Helper::update_action($id, [
    'type' => $body['type'] ?? null,
    'status' => $body['status'] ?? null,
    'config' => is_array($body['config'] ?? null) ? $body['config'] : null,
  ]);
  if (!$updated) {
    return new \WP_Error('bp_notifications_action_failed', __('Could not update action.', 'bookpoint'), ['status' => 500]);
  }
  return rest_ensure_response(['status' => 'success']);
}

function bp_rest_admin_notifications_action_delete(\WP_REST_Request $request) {
  $id = (int)$request['id'];
  $deleted = BP_Notifications_Helper::delete_action($id);
  if (!$deleted) {
    return new \WP_Error('bp_notifications_action_failed', __('Could not delete action.', 'bookpoint'), ['status' => 500]);
  }
  return rest_ensure_response(['status' => 'success']);
}

function bp_rest_admin_notifications_action_test(\WP_REST_Request $request) {
  $id = (int)$request['id'];
  $action = BP_Notifications_Helper::get_action($id);
  if (!$action) {
    return new \WP_Error('bp_notifications_action_not_found', __('Action not found.', 'bookpoint'), ['status' => 404]);
  }
  $workflow = BP_Notifications_Helper::get_workflow((int)$action['workflow_id']);
  if (!$workflow) {
    return new \WP_Error('bp_notifications_workflow_missing', __('Workflow missing.', 'bookpoint'), ['status' => 404]);
  }

  $booking_id = (int)($request->get_param('booking_id') ?? 0);
  $booking = $booking_id > 0 ? BP_BookingModel::find($booking_id) : bp_rest_admin_notifications_latest_booking_row();
  if (!$booking) {
    return new \WP_Error('bp_notifications_booking_missing', __('Booking is required for a test.', 'bookpoint'), ['status' => 400]);
  }

  $payload = BP_Notifications_Helper::build_payload_from_booking($workflow['event_key'], $booking);
  if (!$payload) {
    return new \WP_Error('bp_notifications_payload', __('Could not build payload for booking.', 'bookpoint'), ['status' => 500]);
  }

  $sent = BP_Notifications_Helper::test_action($id, $payload);
  return rest_ensure_response([
    'status' => 'success',
    'data' => ['sent' => $sent],
  ]);
}

function bp_rest_smart_variables(\WP_REST_Request $request) {
  $event = (string)($request->get_param('event_key') ?? 'booking_created');
  $event = in_array($event, BP_Notifications_Helper::allowed_events(), true) ? $event : 'booking_created';
  $variables = BP_Notifications_Helper::smart_variables($event);
  return rest_ensure_response(['status' => 'success', 'data' => $variables]);
}

function bp_rest_admin_notifications_latest_booking_row(): ?array {
  global $wpdb;
  $table = $wpdb->prefix . 'bp_bookings';
  $row = $wpdb->get_row("SELECT * FROM {$table} ORDER BY id DESC LIMIT 1", ARRAY_A);
  return $row ?: null;
}
