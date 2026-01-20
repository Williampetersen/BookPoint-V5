<?php
defined('ABSPATH') || exit;

final class BP_AgentModel extends BP_Model {

  public static function table() : string {
    global $wpdb;
    return $wpdb->prefix . 'bp_agents';
  }

  public static function all(int $limit = 500, bool $only_active = false) : array {
    global $wpdb;
    $table = self::table();
    $limit = max(1, min(1000, $limit));

    if ($only_active) {
      return $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM {$table} WHERE is_active = 1 ORDER BY id DESC LIMIT %d", $limit),
        ARRAY_A
      ) ?: [];
    }

    return $wpdb->get_results(
      $wpdb->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit),
      ARRAY_A
    ) ?: [];
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
      'is_active'  => (int)($data['is_active'] ?? 1),
      'schedule_json' => $data['schedule_json'] ?? null,
      'created_at' => $now,
      'updated_at' => $now,
    ], ['%s','%s','%s','%s','%d','%s','%s','%s']);

    return (int)$wpdb->insert_id;
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
      'is_active'  => (int)($data['is_active'] ?? 1),
      'schedule_json' => $data['schedule_json'] ?? null,
      'updated_at' => $now,
    ], ['id' => $id], ['%s','%s','%s','%s','%d','%s','%s'], ['%d']);

    return ($updated !== false);
  }

  public static function delete(int $id) : bool {
    global $wpdb;
    $table = self::table();
    $deleted = $wpdb->delete($table, ['id' => $id], ['%d']);
    return ($deleted !== false);
  }

  public static function display_name(array $a) : string {
    $name = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
    return $name !== '' ? $name : ('#' . (int)($a['id'] ?? 0));
  }
}
