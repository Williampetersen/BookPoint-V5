<?php
defined('ABSPATH') || exit;

final class BP_ServiceExtraModel {

  public static function table(): string {
    global $wpdb;
    return $wpdb->prefix . 'bp_service_extras';
  }

  public static function map_table_services(): string {
    global $wpdb;
    return $wpdb->prefix . 'bp_extra_services';
  }

  public static function get_service_ids(int $extra_id): array {
    global $wpdb;
    $t = self::map_table_services();
    $ids = $wpdb->get_col($wpdb->prepare("SELECT service_id FROM {$t} WHERE extra_id=%d", $extra_id));
    return array_map('intval', $ids ?: []);
  }

  public static function set_services(int $extra_id, array $service_ids): void {
    global $wpdb;
    $t = self::map_table_services();

    $extra_id = (int)$extra_id;
    $service_ids = array_values(array_unique(array_filter(array_map('intval', $service_ids))));
    $wpdb->delete($t, ['extra_id' => $extra_id], ['%d']);

    foreach ($service_ids as $sid) {
      $wpdb->insert($t, ['extra_id' => $extra_id, 'service_id' => $sid], ['%d','%d']);
    }
  }

  public static function all(array $args = []): array {
    global $wpdb;
    $t = self::table();
    $services = $wpdb->prefix . 'bp_services';

    $q = trim((string)($args['q'] ?? ''));
    $service_id = (int)($args['service_id'] ?? 0);
    $is_active = isset($args['is_active']) && $args['is_active'] !== '' ? (int)$args['is_active'] : null;

    $where = "WHERE 1=1";
    $params = [];

    if ($q !== '') {
      $where .= " AND (e.name LIKE %s)";
      $params[] = '%' . $wpdb->esc_like($q) . '%';
    }
    if ($service_id > 0) {
      $where .= " AND e.service_id = %d";
      $params[] = $service_id;
    }
    if ($is_active !== null) {
      $where .= " AND e.is_active = %d";
      $params[] = $is_active;
    }

    $sql = "
      SELECT e.*, s.name AS service_name
      FROM {$t} e
      LEFT JOIN {$services} s ON s.id = e.service_id
      {$where}
      ORDER BY e.sort_order ASC, e.id DESC
      LIMIT 500
    ";

    return $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
  }

  public static function by_service(int $service_id, bool $only_active = true): array {
    global $wpdb;
    $t = self::table();
    $where = "WHERE service_id = %d";
    $params = [$service_id];

    if ($only_active) {
      $where .= " AND is_active = 1";
    }

    $sql = "SELECT * FROM {$t} {$where} ORDER BY sort_order ASC, id ASC";
    return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
  }

  public static function find(int $id): ?array {
    global $wpdb;
    $t = self::table();
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id = %d", $id), ARRAY_A);
    return $row ?: null;
  }

  public static function save(array $data): int {
    global $wpdb;
    $t = self::table();

    $payload = [
      'service_id' => (int)($data['service_id'] ?? 0),
      'name' => sanitize_text_field($data['name'] ?? ''),
      'description' => sanitize_textarea_field($data['description'] ?? ''),
      'price' => (float)($data['price'] ?? 0),
      'duration_min' => isset($data['duration_min']) && $data['duration_min'] !== '' ? (int)$data['duration_min'] : null,
      'image_id' => isset($data['image_id']) ? (int)$data['image_id'] : 0,
      'sort_order' => (int)($data['sort_order'] ?? 0),
      'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
      'updated_at' => current_time('mysql'),
    ];

    $id = (int)($data['id'] ?? 0);

    if ($id > 0) {
      $wpdb->update($t, $payload, ['id' => $id], ['%d','%s','%s','%f','%d','%d','%d','%d','%s'], ['%d']);
      return $id;
    }

    $payload['created_at'] = current_time('mysql');
    $wpdb->insert($t, $payload, ['%d','%s','%s','%f','%d','%d','%d','%d','%s','%s']);
    return (int)$wpdb->insert_id;
  }

  public static function delete(int $id): bool {
    global $wpdb;
    $t = self::table();
    return (bool)$wpdb->delete($t, ['id' => $id], ['%d']);
  }
}
