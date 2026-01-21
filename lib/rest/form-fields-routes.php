<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function(){

  // PUBLIC: wizard needs enabled fields
  register_rest_route('bp/v1', '/public/form-fields', [
    'methods' => 'GET',
    'callback' => function(){
      global $wpdb;
      $t = $wpdb->prefix . 'bp_form_fields';
      $rows = $wpdb->get_results("
        SELECT *
        FROM {$t}
        WHERE is_enabled=1 AND show_in_wizard=1
        ORDER BY scope ASC, sort_order ASC, id ASC
      ", ARRAY_A) ?: [];

      foreach($rows as &$r){
        $r['id'] = (int)$r['id'];
        $r['is_required'] = (int)$r['is_required'];
        $r['is_enabled'] = (int)$r['is_enabled'];
        $r['show_in_wizard'] = (int)$r['show_in_wizard'];
        $r['sort_order'] = (int)$r['sort_order'];
        $r['options'] = $r['options'] ? json_decode($r['options'], true) : null;
      }
      return new WP_REST_Response(['status'=>'success','data'=>$rows], 200);
    },
    'permission_callback' => '__return_true',
  ]);

  // ADMIN list
  register_rest_route('bp/v1', '/admin/form-fields', [
    'methods'=>'GET',
    'callback'=>function(WP_REST_Request $req){
      if (!current_user_can('bp_manage_settings') && !current_user_can('administrator')) {
        return new WP_REST_Response(['status'=>'error','message'=>'Forbidden'], 403);
      }

      global $wpdb;
      $t = $wpdb->prefix . 'bp_form_fields';
      $scope = sanitize_text_field($req->get_param('scope') ?? 'customer');
      if (!in_array($scope, ['customer','booking'], true)) $scope='customer';

      $rows = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$t}
        WHERE scope=%s
        ORDER BY sort_order ASC, id ASC
      ", $scope), ARRAY_A) ?: [];

      foreach($rows as &$r){
        $r['id'] = (int)$r['id'];
        $r['is_required'] = (int)$r['is_required'];
        $r['is_enabled'] = (int)$r['is_enabled'];
        $r['show_in_wizard'] = (int)$r['show_in_wizard'];
        $r['sort_order'] = (int)$r['sort_order'];
        $r['options'] = $r['options'] ? json_decode($r['options'], true) : null;
      }

      return new WP_REST_Response(['status'=>'success','data'=>$rows], 200);
    }
  ]);

  // ADMIN create
  register_rest_route('bp/v1', '/admin/form-fields', [
    'methods'=>'POST',
    'callback'=>function(WP_REST_Request $req){
      if (!current_user_can('bp_manage_settings') && !current_user_can('administrator')) {
        return new WP_REST_Response(['status'=>'error','message'=>'Forbidden'], 403);
      }

      global $wpdb;
      $t = $wpdb->prefix . 'bp_form_fields';
      $p = $req->get_json_params();
      if (!is_array($p)) $p=[];

      $field_key = sanitize_key($p['field_key'] ?? '');
      $label = sanitize_text_field($p['label'] ?? '');
      $type = sanitize_text_field($p['type'] ?? 'text');
      $scope = sanitize_text_field($p['scope'] ?? 'customer');
      $step_key = sanitize_text_field($p['step_key'] ?? 'details');

      if (!$field_key || !$label) return new WP_REST_Response(['status'=>'error','message'=>'Missing key/label'], 400);
      if (!in_array($scope, ['customer','booking'], true)) $scope='customer';

      $allowed_types = ['text','email','tel','textarea','number','date','select','checkbox'];
      if (!in_array($type, $allowed_types, true)) $type='text';

      $options = $p['options'] ?? null;
      $options_json = $options ? wp_json_encode($options) : null;

      $now = current_time('mysql');

      $ok = $wpdb->insert($t, [
        'field_key'=>$field_key,
        'label'=>$label,
        'type'=>$type,
        'scope'=>$scope,
        'step_key'=>$step_key,
        'placeholder'=>sanitize_text_field($p['placeholder'] ?? ''),
        'options'=>$options_json,
        'is_required'=>!empty($p['is_required']) ? 1 : 0,
        'is_enabled'=>!empty($p['is_enabled']) ? 1 : 0,
        'show_in_wizard'=>!empty($p['show_in_wizard']) ? 1 : 0,
        'sort_order'=>intval($p['sort_order'] ?? 0),
        'created_at'=>$now,
        'updated_at'=>$now,
      ]);

      if (!$ok) return new WP_REST_Response(['status'=>'error','message'=>'Insert failed'], 500);
      return new WP_REST_Response(['status'=>'success'], 200);
    },
    'permission_callback' => function() {
      return current_user_can('bp_manage_settings') || current_user_can('administrator');
    }
  ]);

  // ADMIN update
  register_rest_route('bp/v1', '/admin/form-fields/(?P<id>\d+)', [
    'methods'=>'PATCH',
    'callback'=>function(WP_REST_Request $req){
      if (!current_user_can('bp_manage_settings') && !current_user_can('administrator')) {
        return new WP_REST_Response(['status'=>'error','message'=>'Forbidden'], 403);
      }

      global $wpdb;
      $t = $wpdb->prefix . 'bp_form_fields';
      $id = (int)$req['id'];
      $p = $req->get_json_params();
      if (!is_array($p)) $p=[];

      $updates = [];
      if (array_key_exists('label',$p)) $updates['label'] = sanitize_text_field($p['label']);
      if (array_key_exists('type',$p)) $updates['type'] = sanitize_text_field($p['type']);
      if (array_key_exists('step_key',$p)) $updates['step_key'] = sanitize_text_field($p['step_key']);
      if (array_key_exists('placeholder',$p)) $updates['placeholder'] = sanitize_text_field($p['placeholder']);
      if (array_key_exists('is_required',$p)) $updates['is_required'] = !empty($p['is_required']) ? 1 : 0;
      if (array_key_exists('is_enabled',$p)) $updates['is_enabled'] = !empty($p['is_enabled']) ? 1 : 0;
      if (array_key_exists('show_in_wizard',$p)) $updates['show_in_wizard'] = !empty($p['show_in_wizard']) ? 1 : 0;
      if (array_key_exists('options',$p)) $updates['options'] = $p['options'] ? wp_json_encode($p['options']) : null;

      if (!$updates) return new WP_REST_Response(['status'=>'error','message'=>'No changes'], 400);

      $updates['updated_at'] = current_time('mysql');
      $ok = $wpdb->update($t, $updates, ['id'=>$id]);
      if ($ok === false) return new WP_REST_Response(['status'=>'error','message'=>'Update failed'], 500);

      return new WP_REST_Response(['status'=>'success'], 200);
    },
    'permission_callback' => function() {
      return current_user_can('bp_manage_settings') || current_user_can('administrator');
    }
  ]);

  // ADMIN delete
  register_rest_route('bp/v1', '/admin/form-fields/(?P<id>\d+)', [
    'methods'=>'DELETE',
    'callback'=>function(WP_REST_Request $req){
      if (!current_user_can('bp_manage_settings') && !current_user_can('administrator')) {
        return new WP_REST_Response(['status'=>'error','message'=>'Forbidden'], 403);
      }

      global $wpdb;
      $t = $wpdb->prefix . 'bp_form_fields';
      $id = (int)$req['id'];
      $wpdb->delete($t, ['id'=>$id], ['%d']);
      return new WP_REST_Response(['status'=>'success'], 200);
    },
    'permission_callback' => function() {
      return current_user_can('bp_manage_settings') || current_user_can('administrator');
    }
  ]);

  // ADMIN reseed defaults
  register_rest_route('bp/v1', '/admin/form-fields/reseed', [
    'methods'=>'POST',
    'callback'=>function(){
      if (!function_exists('bp_seed_default_form_fields')) {
        return new WP_REST_Response(['status'=>'error','message'=>'Seed function not found'], 500);
      }
      bp_seed_default_form_fields();
      return new WP_REST_Response(['status'=>'success','message'=>'Defaults reseeded'], 200);
    },
    'permission_callback' => function() {
      return current_user_can('bp_manage_settings') || current_user_can('administrator');
    }
  ]);

});
