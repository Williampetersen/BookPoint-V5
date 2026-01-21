<?php
defined('ABSPATH') || exit;

function bp_install_field_values_table() : void {
  global $wpdb;
  $charset_collate = $wpdb->get_charset_collate();
  $t = $wpdb->prefix . 'bp_field_values';

  $sql = "CREATE TABLE {$t} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type VARCHAR(20) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    field_id BIGINT UNSIGNED NOT NULL,
    field_key VARCHAR(80) NOT NULL,
    scope VARCHAR(20) NOT NULL,
    value_long LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    PRIMARY KEY (id),
    KEY idx_entity (entity_type, entity_id),
    KEY idx_field (field_id),
    KEY idx_scope (scope),
    KEY idx_field_key (field_key)
  ) {$charset_collate};";

  require_once ABSPATH . 'wp-admin/includes/upgrade.php';
  dbDelta($sql);
}

class BP_FieldValuesHelper {

  public static function upsert($entity_type, $entity_id, $field_id, $field_key, $scope, $value){
    global $wpdb;
    $t = $wpdb->prefix . 'bp_field_values';

    $entity_type = sanitize_text_field($entity_type);
    $scope = sanitize_text_field($scope);
    $field_key = sanitize_key($field_key);
    $entity_id = (int)$entity_id;
    $field_id = (int)$field_id;

    if (is_array($value) || is_object($value)) {
      $value = wp_json_encode($value);
    } else {
      $value = (string)$value;
    }

    $now = current_time('mysql');

    $existing_id = (int)$wpdb->get_var($wpdb->prepare("
      SELECT id FROM {$t}
      WHERE entity_type=%s AND entity_id=%d AND field_id=%d
      LIMIT 1
    ", $entity_type, $entity_id, $field_id));

    if ($existing_id > 0) {
      $wpdb->update($t, [
        'value_long' => $value,
        'updated_at' => $now,
      ], ['id'=>$existing_id], ['%s','%s'], ['%d']);
      return $existing_id;
    }

    $wpdb->insert($t, [
      'entity_type' => $entity_type,
      'entity_id'   => $entity_id,
      'field_id'    => $field_id,
      'field_key'   => $field_key,
      'scope'       => $scope,
      'value_long'  => $value,
      'created_at'  => $now,
      'updated_at'  => $now,
    ], ['%s','%d','%d','%s','%s','%s','%s','%s']);

    return (int)$wpdb->insert_id;
  }

  public static function delete_for_entity($entity_type, $entity_id){
    global $wpdb;
    $t = $wpdb->prefix . 'bp_field_values';
    $wpdb->delete($t, [
      'entity_type'=>sanitize_text_field($entity_type),
      'entity_id'=>(int)$entity_id,
    ], ['%s','%d']);
  }

  public static function get_for_entity($entity_type, $entity_id){
    global $wpdb;
    $t = $wpdb->prefix . 'bp_field_values';
    $rows = $wpdb->get_results($wpdb->prepare("
      SELECT field_id, field_key, scope, value_long
      FROM {$t}
      WHERE entity_type=%s AND entity_id=%d
      ORDER BY id ASC
    ", sanitize_text_field($entity_type), (int)$entity_id), ARRAY_A) ?: [];
    return $rows;
  }
}
