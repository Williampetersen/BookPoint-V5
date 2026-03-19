<?php
defined('ABSPATH') || exit;
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- This file's wpdb SQL paths interpolate only hardcoded plugin table names with a sanitized WordPress prefix; dynamic values remain prepared or static by design.

function pointlybooking_install_field_values_table() : void {
  global $wpdb;
  $charset_collate = $wpdb->get_charset_collate();
  $field_values_table = $wpdb->prefix . 'pointlybooking_field_values';

  $sql = "CREATE TABLE {$field_values_table} (
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
  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema management or uninstall cleanup is intentional here and cannot be cached.
  dbDelta($sql);
}

class POINTLYBOOKING_FieldValuesHelper {
  private static function is_safe_sql_identifier(string $identifier): bool {
    return preg_match('/^[A-Za-z0-9_]+$/', $identifier) === 1;
  }

  public static function upsert($entity_type, $entity_id, $field_id, $field_key, $scope, $value){
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names in this function are validated local plugin table names built from hardcoded plugin suffixes.
    global $wpdb;
    $field_values_table = $wpdb->prefix . 'pointlybooking_field_values';

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
    if (!self::is_safe_sql_identifier($field_values_table)) {
      return 0;
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $existing_id = (int) $wpdb->get_var(
      $wpdb->prepare(
        "SELECT id FROM {$field_values_table}
         WHERE entity_type=%s AND entity_id=%d AND field_id=%d
         LIMIT 1",
        $entity_type,
        $entity_id,
        $field_id
      )
    );

    if ($existing_id > 0) {
      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
      $wpdb->update($field_values_table, [
        'value_long' => $value,
        'updated_at' => $now,
      ], ['id'=>$existing_id], ['%s','%s'], ['%d']);
      return $existing_id;
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $wpdb->insert($field_values_table, [
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
    $t = $wpdb->prefix . 'pointlybooking_field_values';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $wpdb->delete($t, [
      'entity_type'=>sanitize_text_field($entity_type),
      'entity_id'=>(int)$entity_id,
    ], ['%s','%d']);
  }

  public static function get_for_entity($entity_type, $entity_id){
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names in this function are validated local plugin table names built from hardcoded plugin suffixes.
    global $wpdb;
    $field_values_table = $wpdb->prefix . 'pointlybooking_field_values';
    if (!self::is_safe_sql_identifier($field_values_table)) {
      return [];
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT field_id, field_key, scope, value_long
         FROM {$field_values_table}
         WHERE entity_type=%s AND entity_id=%d
         ORDER BY id ASC",
        sanitize_text_field($entity_type),
        (int) $entity_id
      ),
      ARRAY_A
    ) ?: [];
    return $rows;
  }
}
