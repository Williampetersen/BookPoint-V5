<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_PromoCodeModel {

  private static function is_safe_sql_identifier(string $identifier): bool {
    return preg_match('/^[A-Za-z0-9_]+$/', $identifier) === 1;
  }

  private static function quote_sql_identifier(string $identifier): string {
    return '`' . $identifier . '`';
  }

  public static function table(): string {
    return pointlybooking_table('promo_codes');
  }

  public static function all(array $args = []): array {
    global $wpdb;
    $promo_codes_table = $wpdb->prefix . 'pointlybooking_promo_codes';
    if (!self::is_safe_sql_identifier($promo_codes_table)) {
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
        "SELECT * FROM {$promo_codes_table}
        WHERE (%d = 0 OR code LIKE %s)
          AND (%d = 0 OR is_active = %d)
        ORDER BY id DESC
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
    $promo_codes_table = $wpdb->prefix . 'pointlybooking_promo_codes';
    if (!self::is_safe_sql_identifier($promo_codes_table)) {
      return null;
    }
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$promo_codes_table} WHERE id=%d", $id), ARRAY_A);
    return $row ?: null;
  }

  public static function find_by_code(string $code): ?array {
    global $wpdb;
    $promo_codes_table = $wpdb->prefix . 'pointlybooking_promo_codes';
    $code = strtoupper(trim($code));
    if ($code === '') return null;
    if (!self::is_safe_sql_identifier($promo_codes_table)) {
      return null;
    }

    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$promo_codes_table} WHERE code=%s LIMIT 1", $code), ARRAY_A);
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
    $promo_codes_table = $wpdb->prefix . 'pointlybooking_promo_codes';
    if (!self::is_safe_sql_identifier($promo_codes_table)) {
      return;
    }
    $wpdb->query($wpdb->prepare("UPDATE {$promo_codes_table} SET uses_count = uses_count + 1 WHERE id=%d", $id));
  }
}

