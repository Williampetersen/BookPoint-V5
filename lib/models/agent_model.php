<?php
defined('ABSPATH') || exit;
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- This file's wpdb SQL paths interpolate only hardcoded plugin table names with a sanitized WordPress prefix; dynamic values remain prepared or static by design.

final class POINTLYBOOKING_AgentModel extends POINTLYBOOKING_Model {

  private static function is_safe_sql_identifier(string $identifier): bool {
    return preg_match('/^[A-Za-z0-9_]+$/', $identifier) === 1;
  }

  public static function table() : string {
    global $wpdb;
    return $wpdb->prefix . 'pointlybooking_agents';
  }

  public static function services_table(): string {
    global $wpdb;
    return $wpdb->prefix . 'pointlybooking_agent_services';
  }

  public static function all(int $limit = 500, bool $only_active = false) : array {
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names in this function are validated local plugin table names built from hardcoded plugin suffixes.
    global $wpdb;
    $agents_table = $wpdb->prefix . 'pointlybooking_agents';
    $limit = max(1, min(1000, $limit));
    if (!self::is_safe_sql_identifier($agents_table)) {
      return [];
    }

    $cache_key = 'pointlybooking_agents_all_' . ($only_active ? 'active' : 'all');
    $cached = get_transient($cache_key);
    if (is_array($cached)) return $cached;

    if ($only_active) {
      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
      $list = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM {$agents_table} WHERE is_active = 1 ORDER BY id DESC LIMIT %d", $limit),
        ARRAY_A
      ) ?: [];
      set_transient($cache_key, $list, 5 * MINUTE_IN_SECONDS);
      return $list;
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $list = $wpdb->get_results(
      $wpdb->prepare("SELECT * FROM {$agents_table} ORDER BY id DESC LIMIT %d", $limit),
      ARRAY_A
    ) ?: [];
    set_transient($cache_key, $list, 5 * MINUTE_IN_SECONDS);
    return $list;
  }

  public static function find(int $id) : ?array {
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names in this function are validated local plugin table names built from hardcoded plugin suffixes.
    global $wpdb;
    $agents_table = $wpdb->prefix . 'pointlybooking_agents';
    if (!self::is_safe_sql_identifier($agents_table)) {
      return null;
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $row = $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM {$agents_table} WHERE id = %d", $id),
      ARRAY_A
    );

    return $row ?: null;
  }

  public static function create(array $data) : int {
    global $wpdb;
    $table = self::table();
    $now = self::now_mysql();

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $wpdb->insert($table, [
      'first_name' => $data['first_name'] ?? null,
      'last_name'  => $data['last_name'] ?? null,
      'email'      => $data['email'] ?? null,
      'phone'      => $data['phone'] ?? null,
      'image_id'   => (int)($data['image_id'] ?? 0),
      'is_active'  => (int)($data['is_active'] ?? 1),
      'schedule_json' => $data['schedule_json'] ?? null,
      'created_at' => $now,
      'updated_at' => $now,
    ], ['%s','%s','%s','%s','%d','%d','%s','%s','%s']);

    $id = (int)$wpdb->insert_id;
    delete_transient('pointlybooking_agents_all_all');
    delete_transient('pointlybooking_agents_all_active');
    return $id;
  }

  public static function update(int $id, array $data) : bool {
    global $wpdb;
    $table = self::table();
    $now = self::now_mysql();

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $updated = $wpdb->update($table, [
      'first_name' => $data['first_name'] ?? null,
      'last_name'  => $data['last_name'] ?? null,
      'email'      => $data['email'] ?? null,
      'phone'      => $data['phone'] ?? null,
      'image_id'   => (int)($data['image_id'] ?? 0),
      'is_active'  => (int)($data['is_active'] ?? 1),
      'schedule_json' => $data['schedule_json'] ?? null,
      'updated_at' => $now,
    ], ['id' => $id], ['%s','%s','%s','%s','%d','%d','%s','%s'], ['%d']);

    $ok = ($updated !== false);
    if ($ok) {
      delete_transient('pointlybooking_agents_all_all');
      delete_transient('pointlybooking_agents_all_active');
    }
    return $ok;
  }

  public static function delete(int $id) : bool {
    global $wpdb;
    $table = self::table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $deleted = $wpdb->delete($table, ['id' => $id], ['%d']);
    $ok = ($deleted !== false);
    if ($ok) {
      delete_transient('pointlybooking_agents_all_all');
      delete_transient('pointlybooking_agents_all_active');
    }
    return $ok;
  }

  public static function display_name(array $a) : string {
    $name = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
    return $name !== '' ? $name : ('#' . (int)($a['id'] ?? 0));
  }

  public static function get_service_ids_for_agent(int $agent_id): array {
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names in this function are validated local plugin table names built from hardcoded plugin suffixes.
    global $wpdb;
    $agent_services_table = $wpdb->prefix . 'pointlybooking_agent_services';
    if (!self::is_safe_sql_identifier($agent_services_table)) {
      return [];
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $rows = $wpdb->get_col(
      $wpdb->prepare("SELECT service_id FROM {$agent_services_table} WHERE agent_id = %d", $agent_id)
    );
    return array_map('intval', $rows ?: []);
  }

  public static function set_services_for_agent(int $agent_id, array $service_ids): void {
    global $wpdb;
    $t = self::services_table();

    $agent_id = (int)$agent_id;
    if ($agent_id <= 0) return;

    $service_ids = array_values(array_unique(array_filter(array_map('intval', $service_ids))));

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $wpdb->delete($t, ['agent_id' => $agent_id], ['%d']);

    foreach ($service_ids as $sid) {
      if ($sid <= 0) continue;
      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
      $wpdb->insert($t, [
        'agent_id' => $agent_id,
        'service_id' => $sid,
      ], ['%d','%d']);
    }
  }
}
