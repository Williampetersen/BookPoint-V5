<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_ServiceExtraModel {

  public static function table(): string {
    return pointlybooking_table('service_extras');
  }

  public static function map_table_services(): string {
    return pointlybooking_table('extra_services');
  }

  public static function get_service_ids(int $extra_id): array {
    global $wpdb;
    $t = self::map_table_services();
    $ids = $wpdb->get_col(
      pointlybooking_prepare_query_with_identifiers(
        "SELECT service_id FROM %i WHERE extra_id=%d",
        [$t],
        [$extra_id]
      )
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
    $t = self::table();
    $services = pointlybooking_table('services');

    $q = trim((string)($args['q'] ?? ''));
    $service_id = (int)($args['service_id'] ?? 0);
    $is_active = isset($args['is_active']) && $args['is_active'] !== '' ? (int)$args['is_active'] : null;

    $where_clauses = ['1=1'];
    $params = [];

    if ($q !== '') {
      $where_clauses[] = '(e.name LIKE %s)';
      $params[] = '%' . $wpdb->esc_like($q) . '%';
    }
    if ($service_id > 0) {
      $where_clauses[] = 'e.service_id = %d';
      $params[] = $service_id;
    }
    if ($is_active !== null) {
      $where_clauses[] = 'e.is_active = %d';
      $params[] = $is_active;
    }

    $params[] = 500;
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
    $sql = "SELECT e.*, s.name AS service_name
        FROM %i e
        LEFT JOIN %i s ON s.id = e.service_id
        " . $where_sql . "
        ORDER BY e.sort_order ASC, e.id DESC
        LIMIT %d";
    return $wpdb->get_results(
      pointlybooking_prepare_query_with_identifiers(
        $sql,
        [$t, $services],
        $params
      ),
      ARRAY_A
    );
  }

  public static function by_service(int $service_id, bool $only_active = true): array {
    global $wpdb;
    $t = self::table();
    $where_clauses = ['service_id = %d'];
    $params = [$service_id];

    if ($only_active) {
      $where_clauses[] = 'is_active = 1';
    }

    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
    $sql = "SELECT * FROM %i " . $where_sql . " ORDER BY sort_order ASC, id ASC";
    return $wpdb->get_results(
      pointlybooking_prepare_query_with_identifiers(
        $sql,
        [$t],
        $params
      ),
      ARRAY_A
    );
  }

  public static function find(int $id): ?array {
    global $wpdb;
    $t = self::table();
    $row = $wpdb->get_row(
      pointlybooking_prepare_query_with_identifiers(
        "SELECT * FROM %i WHERE id = %d",
        [$t],
        [$id]
      ),
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
