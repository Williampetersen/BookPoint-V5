<?php
defined('ABSPATH') || exit;

final class BP_CategoryModel {

  public static function table(): string {
    global $wpdb;
    return $wpdb->prefix . 'bp_categories';
  }

  public static function all(array $args = []): array {
    global $wpdb;
    $t = self::table();

    $q = trim((string)($args['q'] ?? ''));
    $is_active = isset($args['is_active']) && $args['is_active'] !== '' ? (int)$args['is_active'] : null;

    $where = "WHERE 1=1";
    $params = [];

    if ($q !== '') {
      $where .= " AND (name LIKE %s)";
      $params[] = '%' . $wpdb->esc_like($q) . '%';
    }
    if ($is_active !== null) {
      $where .= " AND is_active = %d";
      $params[] = $is_active;
    }

    $sql = "SELECT * FROM {$t} {$where} ORDER BY sort_order ASC, id DESC LIMIT 500";
    return $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
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
