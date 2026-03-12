<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function(){
  register_rest_route('pointly-booking/v1', '/admin/field-values', [
    'methods'=>'GET',
    'callback'=>'pointlybooking_admin_get_field_values',
    'permission_callback'=>function(){
      return current_user_can('pointlybooking_manage_bookings') || current_user_can('pointlybooking_manage_settings');
    }
  ]);
});

function pointlybooking_admin_get_field_values(WP_REST_Request $req){
  $entity_type = sanitize_text_field($req->get_param('entity_type') ?? '');
  $entity_id = (int)($req->get_param('entity_id') ?? 0);

  if (!in_array($entity_type, ['booking','customer'], true) || $entity_id<=0) {
    return new WP_REST_Response(['status'=>'error','message'=>'Invalid entity'], 400);
  }

  if (!class_exists('POINTLYBOOKING_FieldValuesHelper')) {
    return new WP_REST_Response(['status'=>'error','message'=>'Missing helper'], 500);
  }

  $rows = POINTLYBOOKING_FieldValuesHelper::get_for_entity($entity_type, $entity_id);
  return new WP_REST_Response(['status'=>'success','data'=>$rows], 200);
}
