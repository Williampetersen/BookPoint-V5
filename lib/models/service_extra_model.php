<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_ServiceExtraModel {

  private static function is_safe_sql_identifier(string $identifier): bool {
    return preg_match('/^[A-Za-z0-9_]+$/', $identifier) === 1;
  }

  private static function quote_sql_identifier(string $identifier): string {
    return '`' . $identifier . '`';
  }

  public static function table(): string {
    return pointlybooking_table('service_extras');
  }

  public static function map_table_services(): string {
    return pointlybooking_table('extra_services');
  }

  public static function get_service_ids(int $extra_id): array {
    global $wpdb;
    $extra_services_table = $wpdb->prefix . 'pointlybooking_extra_services';
    if (!self::is_safe_sql_identifier($extra_services_table)) {
      return [];
    }
    $ids = $wpdb->get_col(
      $wpdb->prepare("SELECT service_id FROM {$extra_services_table} WHERE extra_id=%d", $extra_id)
    );
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
    $t = $wpdb->prefix . 'pointlybooking_service_extras';
    $services = $wpdb->prefix . 'pointlybooking_services';
    if (!self::is_safe_sql_identifier($t) || !self::is_safe_sql_identifier($services)) {
      return [];
    }
    $extras_table = $t;
    $services_table = $services;

    $q = trim((string)($args['q'] ?? ''));
    $service_id = (int)($args['service_id'] ?? 0);
    $is_active = isset($args['is_active']) && $args['is_active'] !== '' ? (int)$args['is_active'] : null;
    $like = '%' . $wpdb->esc_like($q) . '%';
    $apply_q_filter = ($q !== '') ? 1 : 0;
    $apply_service_filter = ($service_id > 0) ? 1 : 0;
    $service_value = ($service_id > 0) ? $service_id : 0;
    $apply_active_filter = ($is_active !== null) ? 1 : 0;
    $active_value = ($is_active !== null) ? $is_active : 0;

    return $wpdb->get_results(
      $wpdb->prepare(
        "SELECT e.*, s.name AS service_name
        FROM {$extras_table} e
        LEFT JOIN {$services_table} s ON s.id = e.service_id
        WHERE (%d = 0 OR e.name LIKE %s)
          AND (%d = 0 OR e.service_id = %d)
          AND (%d = 0 OR e.is_active = %d)
        ORDER BY e.sort_order ASC, e.id DESC
        LIMIT %d",
        $apply_q_filter,
        $like,
        $apply_service_filter,
        $service_value,
        $apply_active_filter,
        $active_value,
        500
      ),
      ARRAY_A
    );
  }

  public static function by_service(int $service_id, bool $only_active = true): array {
    global $wpdb;
    $service_extras_table = $wpdb->prefix . 'pointlybooking_service_extras';
    if (!self::is_safe_sql_identifier($service_extras_table)) {
      return [];
    }
    $active_only = $only_active ? 1 : 0;
    return $wpdb->get_results(
      $wpdb->prepare(
        "SELECT * FROM {$service_extras_table}
        WHERE service_id = %d
          AND (%d = 0 OR is_active = 1)
        ORDER BY sort_order ASC, id ASC",
        $service_id,
        $active_only
      ),
      ARRAY_A
    );
  }

  public static function find(int $id): ?array {
    global $wpdb;
    $service_extras_table = $wpdb->prefix . 'pointlybooking_service_extras';
    if (!self::is_safe_sql_identifier($service_extras_table)) {
      return null;
    }
    $row = $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM {$service_extras_table} WHERE id = %d", $id),
      ARRAY_A
    );
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

