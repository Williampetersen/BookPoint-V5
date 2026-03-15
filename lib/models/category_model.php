<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_CategoryModel {

  private static function is_safe_sql_identifier(string $identifier): bool {
    return preg_match('/^[A-Za-z0-9_]+$/', $identifier) === 1;
  }

  private static function quote_sql_identifier(string $identifier): string {
    return '`' . $identifier . '`';
  }

  public static function table(): string {
    return pointlybooking_table('categories');
  }

  public static function all(array $args = []): array {
    global $wpdb;
    $categories_table = $wpdb->prefix . 'pointlybooking_categories';
    if (!self::is_safe_sql_identifier($categories_table)) {
      return [];
    }

    $q = trim((string)($args['q'] ?? ''));
    $is_active = isset($args['is_active']) && $args['is_active'] !== '' ? (int)$args['is_active'] : null;
    $like = '%' . $wpdb->esc_like($q) . '%';
    $apply_q_filter = ($q !== '') ? 1 : 0;
    $apply_active_filter = ($is_active !== null) ? 1 : 0;
    $active_value = ($is_active !== null) ? $is_active : 0;

    return $wpdb->get_results(
      $wpdb->prepare(
        "SELECT * FROM {$categories_table}
        WHERE (%d = 0 OR name LIKE %s)
          AND (%d = 0 OR is_active = %d)
        ORDER BY sort_order ASC, id DESC
        LIMIT %d",
        $apply_q_filter,
        $like,
        $apply_active_filter,
        $active_value,
        500
      ),
      ARRAY_A
    );
  }

  public static function find(int $id): ?array {
    global $wpdb;
    $categories_table = $wpdb->prefix . 'pointlybooking_categories';
    if (!self::is_safe_sql_identifier($categories_table)) {
      return null;
    }
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$categories_table} WHERE id = %d", $id), ARRAY_A);
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

