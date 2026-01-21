<?php
defined('ABSPATH') || exit;

final class BP_ServiceAgentModel {

  public static function table() : string {
    global $wpdb;
    return $wpdb->prefix . 'bp_service_agents';
  }

  public static function set_agents_for_service(int $service_id, array $agent_ids) : void {
    global $wpdb;
    $table = self::table();

    if ($service_id <= 0) return;

    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    if ($exists !== $table && class_exists('BP_MigrationsHelper')) {
      BP_MigrationsHelper::create_tables();
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

    delete_transient('bp_service_agents_' . $service_id);
  }

  public static function get_agent_ids_for_service(int $service_id) : array {
    global $wpdb;
    $table = self::table();

    $cache_key = 'bp_service_agents_' . $service_id;
    $cached = get_transient($cache_key);
    if (is_array($cached)) return $cached;

    $rows = $wpdb->get_col($wpdb->prepare(
      "SELECT agent_id FROM {$table} WHERE service_id = %d",
      $service_id
    ));

    $ids = array_values(array_unique(array_map('intval', $rows ?: [])));
    set_transient($cache_key, $ids, 5 * MINUTE_IN_SECONDS);
    return $ids;
  }

  public static function get_agents_for_service(int $service_id) : array {
    $ids = self::get_agent_ids_for_service($service_id);
    if (empty($ids)) return [];

    $agents = [];
    foreach ($ids as $id) {
      $a = BP_AgentModel::find($id);
      if ($a && (int)$a['is_active'] === 1) $agents[] = $a;
    }
    return $agents;
  }
}
