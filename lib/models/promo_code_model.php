<?php
defined('ABSPATH') || exit;

final class BP_PromoCodeModel {

  public static function table(): string {
    global $wpdb;
    return $wpdb->prefix . 'bp_promo_codes';
  }

  public static function all(array $args = []): array {
    global $wpdb;
    $t = self::table();

    $q = trim((string)($args['q'] ?? ''));
    $is_active = isset($args['is_active']) && $args['is_active'] !== '' ? (int)$args['is_active'] : null;

    $where = "WHERE 1=1";
    $params = [];

    if ($q !== '') {
      $where .= " AND code LIKE %s";
      $params[] = '%' . $wpdb->esc_like($q) . '%';
    }
    if ($is_active !== null) {
      $where .= " AND is_active = %d";
      $params[] = $is_active;
    }

    $sql = "SELECT * FROM {$t} {$where} ORDER BY id DESC LIMIT 500";
    return $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
  }

  public static function find(int $id): ?array {
    global $wpdb;
    $t = self::table();
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id=%d", $id), ARRAY_A);
    return $row ?: null;
  }

  public static function find_by_code(string $code): ?array {
    global $wpdb;
    $t = self::table();
    $code = strtoupper(trim($code));
    if ($code === '') return null;

    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE code=%s LIMIT 1", $code), ARRAY_A);
    return $row ?: null;
  }

  public static function save(array $data): int {
    global $wpdb;
    $t = self::table();

    $code = strtoupper(sanitize_text_field($data['code'] ?? ''));
    $type = sanitize_text_field($data['type'] ?? 'percent');
    if (!in_array($type, ['percent','fixed'], true)) $type = 'percent';

    $payload = [
      'code' => $code,
      'type' => $type,
      'amount' => (float)($data['amount'] ?? 0),
      'starts_at' => !empty($data['starts_at']) ? sanitize_text_field($data['starts_at']) : null,
      'ends_at' => !empty($data['ends_at']) ? sanitize_text_field($data['ends_at']) : null,
      'max_uses' => ($data['max_uses'] !== '' && isset($data['max_uses'])) ? (int)$data['max_uses'] : null,
      'min_total' => ($data['min_total'] !== '' && isset($data['min_total'])) ? (float)$data['min_total'] : null,
      'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
      'updated_at' => current_time('mysql'),
    ];

    $id = (int)($data['id'] ?? 0);

    if ($id > 0) {
      $wpdb->update($t, $payload, ['id'=>$id], null, ['%d']);
      return $id;
    }

    $payload['created_at'] = current_time('mysql');
    $wpdb->insert($t, $payload);
    return (int)$wpdb->insert_id;
  }

  public static function delete(int $id): bool {
    global $wpdb;
    $t = self::table();
    return (bool)$wpdb->delete($t, ['id'=>$id], ['%d']);
  }

  public static function increment_use(int $id): void {
    global $wpdb;
    $t = self::table();
    $wpdb->query($wpdb->prepare("UPDATE {$t} SET uses_count = uses_count + 1 WHERE id=%d", $id));
  }
}
