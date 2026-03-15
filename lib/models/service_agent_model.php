<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_ServiceAgentModel {

  private static function is_safe_sql_identifier(string $identifier): bool {
    return preg_match('/^[A-Za-z0-9_]+$/', $identifier) === 1;
  }

  private static function quote_sql_identifier(string $identifier): string {
    return '`' . $identifier . '`';
  }

  public static function table() : string {
    global $wpdb;
    return $wpdb->prefix . 'pointlybooking_service_agents';
  }

  public static function set_agents_for_service(int $service_id, array $agent_ids) : void {
    global $wpdb;
    $table = $wpdb->prefix . 'pointlybooking_service_agents';

    if ($service_id <= 0) return;

    if (!pointlybooking_db_table_exists($table) && class_exists('POINTLYBOOKING_MigrationsHelper')) {
      POINTLYBOOKING_MigrationsHelper::create_tables();
    }

    $agent_ids = array_values(array_unique(array_filter(array_map('absint', $agent_ids))));

    $wpdb->delete($table, ['service_id' => $service_id], ['%d']);

    $now = current_time('mysql');
    foreach ($agent_ids as $aid) {
      $wpdb->insert($table, [
        'service_id' => $service_id,
        'agent_id' => $aid,
        'created_at' => $now,
      ], ['%d','%d','%s']);
    }

    delete_transient('pointlybooking_service_agents_' . $service_id);
  }

  public static function get_agent_ids_for_service(int $service_id) : array {
    global $wpdb;
    $service_agents_table = $wpdb->prefix . 'pointlybooking_service_agents';
    if (!self::is_safe_sql_identifier($service_agents_table)) {
      return [];
    }

    $cache_key = 'pointlybooking_service_agents_' . $service_id;
    $cached = get_transient($cache_key);
    if (is_array($cached)) return $cached;

    $rows = $wpdb->get_col(
      $wpdb->prepare("SELECT agent_id FROM {$service_agents_table} WHERE service_id = %d", $service_id)
    );

    $ids = array_values(array_unique(array_map('intval', $rows ?: [])));
    set_transient($cache_key, $ids, 5 * MINUTE_IN_SECONDS);
    return $ids;
  }

  public static function get_agents_for_service(int $service_id) : array {
    $ids = self::get_agent_ids_for_service($service_id);
    if (empty($ids)) return [];

    $agents = [];
    foreach ($ids as $id) {
      $a = POINTLYBOOKING_AgentModel::find($id);
      if ($a && (int)$a['is_active'] === 1) $agents[] = $a;
    }
    return $agents;
  }
}

