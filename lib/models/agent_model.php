<?php
defined('ABSPATH') || exit;

final class BP_AgentModel extends BP_Model {

  public static function table() : string {
    global $wpdb;
    return $wpdb->prefix . 'bp_agents';
  }

  public static function services_table(): string {
    global $wpdb;
    return $wpdb->prefix . 'bp_agent_services';
  }

  public static function all(int $limit = 500, bool $only_active = false) : array {
    global $wpdb;
    $table = self::table();
    $limit = max(1, min(1000, $limit));

    $cache_key = 'bp_agents_all_' . ($only_active ? 'active' : 'all');
    $cached = get_transient($cache_key);
    if (is_array($cached)) return $cached;

    if ($only_active) {
      $list = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM {$table} WHERE is_active = 1 ORDER BY id DESC LIMIT %d", $limit),
        ARRAY_A
      ) ?: [];
      set_transient($cache_key, $list, 5 * MINUTE_IN_SECONDS);
      return $list;
    }

    $list = $wpdb->get_results(
      $wpdb->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit),
      ARRAY_A
    ) ?: [];
    set_transient($cache_key, $list, 5 * MINUTE_IN_SECONDS);
    return $list;
  }

  public static function find(int $id) : ?array {
    global $wpdb;
    $table = self::table();

    $row = $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
      ARRAY_A
    );

    return $row ?: null;
  }

  public static function create(array $data) : int {
    global $wpdb;
    $table = self::table();
    $now = self::now_mysql();

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
    delete_transient('bp_agents_all_all');
    delete_transient('bp_agents_all_active');
    return $id;
  }

  public static function update(int $id, array $data) : bool {
    global $wpdb;
    $table = self::table();
    $now = self::now_mysql();

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
      delete_transient('bp_agents_all_all');
      delete_transient('bp_agents_all_active');
    }
    return $ok;
  }

  public static function delete(int $id) : bool {
    global $wpdb;
    $table = self::table();
    $deleted = $wpdb->delete($table, ['id' => $id], ['%d']);
    $ok = ($deleted !== false);
    if ($ok) {
      delete_transient('bp_agents_all_all');
      delete_transient('bp_agents_all_active');
    }
    return $ok;
  }

  public static function display_name(array $a) : string {
    $name = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
    return $name !== '' ? $name : ('#' . (int)($a['id'] ?? 0));
  }

  public static function get_service_ids_for_agent(int $agent_id): array {
    global $wpdb;
    $t = self::services_table();
    $rows = $wpdb->get_col($wpdb->prepare(
      "SELECT service_id FROM {$t} WHERE agent_id = %d",
      $agent_id
    ));
    return array_map('intval', $rows ?: []);
  }

  public static function set_services_for_agent(int $agent_id, array $service_ids): void {
    global $wpdb;
    $t = self::services_table();

    $agent_id = (int)$agent_id;
    if ($agent_id <= 0) return;

    $service_ids = array_values(array_unique(array_filter(array_map('intval', $service_ids))));

    $wpdb->delete($t, ['agent_id' => $agent_id], ['%d']);

    foreach ($service_ids as $sid) {
      if ($sid <= 0) continue;
      $wpdb->insert($t, [
        'agent_id' => $agent_id,
        'service_id' => $sid,
      ], ['%d','%d']);
    }
  }
}
