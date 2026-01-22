<?php
defined('ABSPATH') || exit;

final class BP_FormFieldModel {

  public static function table(): string {
    global $wpdb;
    return $wpdb->prefix . 'bp_form_fields';
  }

  public static function all(string $scope, array $args = []): array {
    global $wpdb;
    $t = self::table();

    $q = trim((string)($args['q'] ?? ''));
    $is_active = isset($args['is_active']) && $args['is_active'] !== '' ? (int)$args['is_active'] : null;

    $where = "WHERE scope = %s";
    $params = [$scope];

    if ($q !== '') {
      $where .= " AND (label LIKE %s OR field_key LIKE %s OR name_key LIKE %s)";
      $params[] = '%' . $wpdb->esc_like($q) . '%';
      $params[] = '%' . $wpdb->esc_like($q) . '%';
      $params[] = '%' . $wpdb->esc_like($q) . '%';
    }
    if ($is_active !== null) {
      $where .= " AND (CASE WHEN (field_key IS NULL OR field_key = '') THEN is_active ELSE is_enabled END) = %d";
      $params[] = $is_active;
    }

    $sql = "SELECT * FROM {$t} {$where} ORDER BY sort_order ASC, id ASC LIMIT 500";
    return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
  }

  public static function find(int $id): ?array {
    global $wpdb;
    $t = self::table();
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id=%d", $id), ARRAY_A);
    return $row ?: null;
  }

  public static function active_fields(string $scope): array {
    return self::all($scope, ['is_active' => 1]);
  }

  public static function save(array $data): int {
    global $wpdb;
    $t = self::table();

    $scope = sanitize_text_field($data['scope'] ?? 'form');
    if (!in_array($scope, ['form','customer','booking'], true)) $scope = 'form';

    $name_key = sanitize_key($data['name_key'] ?? '');
    $field_key = sanitize_key($data['field_key'] ?? '') ?: $name_key;
    if ($name_key === '' && $field_key !== '') $name_key = $field_key;
    $type = sanitize_text_field($data['type'] ?? 'text');
    $allowed_types = ['text','email','tel','textarea','select','checkbox','radio','date'];
    if (!in_array($type, $allowed_types, true)) $type = 'text';

    $options_json = null;
    if (!empty($data['options_raw'])) {
      $raw = trim((string)$data['options_raw']);

      $decoded = json_decode($raw, true);
      if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $options_json = wp_json_encode(array_values($decoded));
      } else {
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $lines = array_values(array_filter(array_map('trim', $lines)));
        $options_json = $lines ? wp_json_encode($lines) : null;
      }
    }

    $payload = [
      'scope' => $scope,
      'label' => sanitize_text_field($data['label'] ?? ''),
      'field_key' => $field_key,
      'name_key' => $name_key,
      'type' => $type,
      'step_key' => sanitize_text_field($data['step_key'] ?? 'details'),
      'placeholder' => sanitize_text_field($data['placeholder'] ?? ''),
      'options' => $options_json,
      'options_json' => $options_json,
      'required' => !empty($data['required']) ? 1 : (!empty($data['is_required']) ? 1 : 0),
      'is_required' => !empty($data['is_required']) ? 1 : (!empty($data['required']) ? 1 : 0),
      'sort_order' => (int)($data['sort_order'] ?? 0),
      'is_active' => !empty($data['is_active']) ? 1 : (!empty($data['is_enabled']) ? 1 : 0),
      'is_enabled' => !empty($data['is_enabled']) ? 1 : (!empty($data['is_active']) ? 1 : 0),
      'show_in_wizard' => !empty($data['show_in_wizard']) ? 1 : 0,
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
}
