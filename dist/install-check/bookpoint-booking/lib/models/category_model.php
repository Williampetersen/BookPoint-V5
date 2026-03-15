<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_CategoryModel {

  public static function table(): string {
    return pointlybooking_table('categories');
  }

  private static function prepare_with_table(string $query, string $table, array $args = []): string {
    global $wpdb;
    if (method_exists($wpdb, 'has_cap') && $wpdb->has_cap('identifier_placeholders')) {
      return $wpdb->prepare($query, array_merge([$table], $args));
    }

    $safe_table = preg_replace('/[^A-Za-z0-9_]/', '', $table);
    $query = preg_replace('/%i/', '`' . $safe_table . '`', $query, 1);
    if (empty($args)) {
      return (string) $query;
    }

    return $wpdb->prepare($query, $args);
  }

  public static function all(array $args = []): array {
    global $wpdb;
    $t = self::table();

    $q = trim((string)($args['q'] ?? ''));
    $is_active = isset($args['is_active']) && $args['is_active'] !== '' ? (int)$args['is_active'] : null;
    $like = '%' . $wpdb->esc_like($q) . '%';
    $apply_q_filter = ($q !== '') ? 1 : 0;
    $apply_active_filter = ($is_active !== null) ? 1 : 0;
    $active_value = ($is_active !== null) ? $is_active : 0;

    $sql = "SELECT * FROM %i
      WHERE (%d = 0 OR name LIKE %s)
        AND (%d = 0 OR is_active = %d)
      ORDER BY sort_order ASC, id DESC
      LIMIT %d";

    return $wpdb->get_results(
      self::prepare_with_table($sql, $t, [
        $apply_q_filter,
        $like,
        $apply_active_filter,
        $active_value,
        500,
      ]),
      ARRAY_A
    );
  }

  public static function find(int $id): ?array {
    global $wpdb;
    $t = self::table();
    $row = $wpdb->get_row(self::prepare_with_table("SELECT * FROM %i WHERE id = %d", $t, [$id]), ARRAY_A);
    return $row ?: null;
  }

  public static function save(array $data): int {
    global $wpdb;
    $t = self::table();

    $payload = [
      'name' => sanitize_text_field($data['name'] ?? ''),
      'description' => sanitize_textarea_field($data['description'] ?? ''),
      'image_id' => isset($data['image_id']) ? (int)$data['image_id'] : null,
      'sort_order' => (int)($data['sort_order'] ?? 0),
      'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
      'updated_at' => current_time('mysql'),
    ];

    $id = (int)($data['id'] ?? 0);

    if ($id > 0) {
      $wpdb->update($t, $payload, ['id' => $id], ['%s','%s','%d','%d','%d','%s'], ['%d']);
      return $id;
    } else {
      $payload['created_at'] = current_time('mysql');
      $wpdb->insert($t, $payload, ['%s','%s','%d','%d','%d','%s','%s']);
      return (int)$wpdb->insert_id;
    }
  }

  public static function delete(int $id): bool {
    global $wpdb;
    $t = self::table();
    return (bool)$wpdb->delete($t, ['id' => $id], ['%d']);
  }
}
