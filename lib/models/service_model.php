<?php
defined('ABSPATH') || exit;

final class BP_ServiceModel extends BP_Model {

  public static function table() : string {
    global $wpdb;
    return $wpdb->prefix . 'bp_services';
  }

  public static function validate(array $data) : array {
    $errors = [];

    $name = trim((string)($data['name'] ?? ''));
    if ($name === '') {
      $errors['name'] = __('Service name is required.', 'bookpoint');
    }

    $duration = (int)($data['duration_minutes'] ?? 0);
    if ($duration < 5 || $duration > 1440) {
      $errors['duration_minutes'] = __('Duration must be between 5 and 1440 minutes.', 'bookpoint');
    }

    $price_cents = (int)($data['price_cents'] ?? 0);
    if ($price_cents < 0) {
      $errors['price_cents'] = __('Price must be 0 or more.', 'bookpoint');
    }

    $currency = strtoupper(trim((string)($data['currency'] ?? 'USD')));
    if (!preg_match('/^[A-Z]{3}$/', $currency)) {
      $errors['currency'] = __('Currency must be a 3-letter code like USD.', 'bookpoint');
    }

    // Step 15: Service-based availability validation
    $buffer_before = (int)($data['buffer_before_minutes'] ?? 0);
    $buffer_after  = (int)($data['buffer_after_minutes'] ?? 0);
    $capacity      = (int)($data['capacity'] ?? 1);

    if ($buffer_before < 0 || $buffer_before > 240) {
      $errors['buffer_before_minutes'] = __('Buffer before must be 0-240 minutes.', 'bookpoint');
    }
    if ($buffer_after < 0 || $buffer_after > 240) {
      $errors['buffer_after_minutes'] = __('Buffer after must be 0-240 minutes.', 'bookpoint');
    }
    if ($capacity < 1 || $capacity > 50) {
      $errors['capacity'] = __('Capacity must be between 1 and 50.', 'bookpoint');
    }

    return $errors;
  }

  public static function all() : array {
    global $wpdb;
    $table = self::table();
    return $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A) ?: [];
  }

  public static function find(int $id) : ?array {
    global $wpdb;
    $table = self::table();
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
    return $row ?: null;
  }

  public static function create(array $data) : int {
    global $wpdb;
    $table = self::table();
    $now = self::now_mysql();

    $wpdb->insert($table, [
      'name' => $data['name'],
      'description' => $data['description'] ?? null,
      'duration_minutes' => (int)$data['duration_minutes'],
      'price_cents' => (int)$data['price_cents'],
      'currency' => $data['currency'],
      'is_active' => (int)$data['is_active'],
      'use_global_schedule' => (int)($data['use_global_schedule'] ?? 1),
      'schedule_json' => $data['schedule_json'] ?? null,
      'buffer_before_minutes' => (int)($data['buffer_before_minutes'] ?? 0),
      'buffer_after_minutes' => (int)($data['buffer_after_minutes'] ?? 0),
      'capacity' => (int)($data['capacity'] ?? 1),
      'created_at' => $now,
      'updated_at' => $now,
    ], [
      '%s','%s','%d','%d','%s','%d','%d','%s','%d','%d','%d','%s','%s'
    ]);

    return (int)$wpdb->insert_id;
  }

  public static function update(int $id, array $data) : bool {
    global $wpdb;
    $table = self::table();

    $updated = $wpdb->update($table, [
      'name' => $data['name'],
      'description' => $data['description'] ?? null,
      'duration_minutes' => (int)$data['duration_minutes'],
      'price_cents' => (int)$data['price_cents'],
      'currency' => $data['currency'],
      'is_active' => (int)$data['is_active'],
      'use_global_schedule' => (int)($data['use_global_schedule'] ?? 1),
      'schedule_json' => $data['schedule_json'] ?? null,
      'buffer_before_minutes' => (int)($data['buffer_before_minutes'] ?? 0),
      'buffer_after_minutes' => (int)($data['buffer_after_minutes'] ?? 0),
      'capacity' => (int)($data['capacity'] ?? 1),
      'updated_at' => self::now_mysql(),
    ], [
      'id' => $id
    ], [
      '%s','%s','%d','%d','%s','%d','%d','%s','%d','%d','%d','%s'
    ], [
      '%d'
    ]);

    return ($updated !== false);
  }

  public static function delete(int $id) : bool {
    global $wpdb;
    $table = self::table();
    $deleted = $wpdb->delete($table, ['id' => $id], ['%d']);
    return ($deleted !== false);
  }
}

