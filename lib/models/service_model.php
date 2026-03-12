<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_ServiceModel extends POINTLYBOOKING_Model {

  public static function table() : string {
    return pointlybooking_table('services');
  }

  public static function map_table_categories(): string {
    return pointlybooking_table('service_categories');
  }

  public static function get_category_ids(int $service_id): array {
    global $wpdb;
    $t = self::map_table_categories();
    $ids = $wpdb->get_col(
      pointlybooking_prepare_query_with_identifiers(
        "SELECT category_id FROM %i WHERE service_id=%d",
        [$t],
        [$service_id]
      )
    );
    return array_map('intval', $ids ?: []);
  }

  public static function set_categories(int $service_id, array $category_ids): void {
    global $wpdb;
    $t = self::map_table_categories();

    $service_id = (int)$service_id;
    $category_ids = array_values(array_unique(array_filter(array_map('intval', $category_ids))));
    $wpdb->delete($t, ['service_id' => $service_id], ['%d']);

    foreach ($category_ids as $cid) {
      $wpdb->insert($t, ['service_id' => $service_id, 'category_id' => $cid], ['%d','%d']);
    }
  }

  public static function validate(array $data) : array {
    $errors = [];

    $name = trim((string)($data['name'] ?? ''));
    if ($name === '') {
      $errors['name'] = __('Service name is required.', 'bookpoint-booking');
    }

    $duration = (int)($data['duration_minutes'] ?? 0);
    if ($duration < 5 || $duration > 1440) {
      $errors['duration_minutes'] = __('Duration must be between 5 and 1440 minutes.', 'bookpoint-booking');
    }

    $price_cents = (int)($data['price_cents'] ?? 0);
    if ($price_cents < 0) {
      $errors['price_cents'] = __('Price must be 0 or more.', 'bookpoint-booking');
    }

    $currency = strtoupper(trim((string)($data['currency'] ?? 'USD')));
    if (!preg_match('/^[A-Z]{3}$/', $currency)) {
      $errors['currency'] = __('Currency must be a 3-letter code like USD.', 'bookpoint-booking');
    }

    // Step 15: Service-based availability validation
    $buffer_before = (int)($data['buffer_before_minutes'] ?? 0);
    $buffer_after  = (int)($data['buffer_after_minutes'] ?? 0);
    $capacity      = (int)($data['capacity'] ?? 1);

    if ($buffer_before < 0 || $buffer_before > 240) {
      $errors['buffer_before_minutes'] = __('Buffer before must be 0-240 minutes.', 'bookpoint-booking');
    }
    if ($buffer_after < 0 || $buffer_after > 240) {
      $errors['buffer_after_minutes'] = __('Buffer after must be 0-240 minutes.', 'bookpoint-booking');
    }
    if ($capacity < 1 || $capacity > 50) {
      $errors['capacity'] = __('Capacity must be between 1 and 50.', 'bookpoint-booking');
    }

    return $errors;
  }

  public static function all(array $args = []) : array {
    global $wpdb;
    $table = self::table();

    $is_active = isset($args['is_active']) && $args['is_active'] !== '' ? (int)$args['is_active'] : null;
    $cache_key = 'pointlybooking_services_all_' . ($is_active === null ? 'all' : ('active_' . $is_active));
    $cached = get_transient($cache_key);
    if (is_array($cached)) return $cached;

    $where_clauses = ['1=1'];
    $params = [];
    if ($is_active !== null) {
      $where_clauses[] = 'is_active = %d';
      $params[] = $is_active;
    }
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
    $sql = "SELECT * FROM %i " . $where_sql . " ORDER BY id DESC";
    $list = $wpdb->get_results(
      pointlybooking_prepare_query_with_identifiers(
        $sql,
        [$table],
        $params
      ),
      ARRAY_A
    );
    $list = $list ?: [];
    set_transient($cache_key, $list, 5 * MINUTE_IN_SECONDS);
    return $list;
  }

  public static function find(int $id) : ?array {
    global $wpdb;
    $table = self::table();
    $row = $wpdb->get_row(
      pointlybooking_prepare_query_with_identifiers(
        "SELECT * FROM %i WHERE id = %d",
        [$table],
        [$id]
      ),
      ARRAY_A
    );
    return $row ?: null;
  }

  public static function create(array $data) : int {
    global $wpdb;
    $table = self::table();
    $now = self::now_mysql();

    $wpdb->insert($table, [
      'name' => $data['name'],
      'description' => $data['description'] ?? null,
      'category_id' => (int)($data['category_id'] ?? 0),
      'image_id' => (int)($data['image_id'] ?? 0),
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
      '%s','%s','%d','%d','%d','%d','%s','%d','%d','%s','%d','%d','%d','%s','%s'
    ]);

    $id = (int)$wpdb->insert_id;
    delete_transient('pointlybooking_services_all_all');
    delete_transient('pointlybooking_services_all_active_1');
    delete_transient('pointlybooking_services_all_active_0');
    return $id;
  }

  public static function update(int $id, array $data) : bool {
    global $wpdb;
    $table = self::table();

    $updated = $wpdb->update($table, [
      'name' => $data['name'],
      'description' => $data['description'] ?? null,
      'category_id' => (int)($data['category_id'] ?? 0),
      'image_id' => (int)($data['image_id'] ?? 0),
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
      '%s','%s','%d','%d','%d','%d','%s','%d','%d','%s','%d','%d','%d','%s'
    ], [
      '%d'
    ]);

    $ok = ($updated !== false);
    if ($ok) {
      delete_transient('pointlybooking_services_all_all');
      delete_transient('pointlybooking_services_all_active_1');
      delete_transient('pointlybooking_services_all_active_0');
    }
    return $ok;
  }

  public static function delete(int $id) : bool {
    global $wpdb;
    $table = self::table();
    $deleted = $wpdb->delete($table, ['id' => $id], ['%d']);
    $ok = ($deleted !== false);
    if ($ok) {
      delete_transient('pointlybooking_services_all_all');
      delete_transient('pointlybooking_services_all_active_1');
      delete_transient('pointlybooking_services_all_active_0');
    }
    return $ok;
  }
}
