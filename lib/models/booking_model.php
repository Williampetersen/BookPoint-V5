<?php
defined('ABSPATH') || exit;

final class BP_BookingModel extends BP_Model {

  public static function table() : string {
    global $wpdb;
    return $wpdb->prefix . 'bp_bookings';
  }

  public static function create(array $data) : int {
    global $wpdb;
    $table = self::table();
    $now = self::now_mysql();

    $manage_key = bin2hex(random_bytes(32));

    // Step 16: Include agent_id if specified
    $agent_id = isset($data['agent_id']) && $data['agent_id'] > 0 ? (int)$data['agent_id'] : null;

    $wpdb->insert($table, [
      'service_id'      => (int)$data['service_id'],
      'customer_id'     => (int)$data['customer_id'],
      'agent_id'        => $agent_id,
      'start_datetime'  => $data['start_datetime'],
      'end_datetime'    => $data['end_datetime'],
      'status'          => $data['status'] ?? 'pending',
      'notes'           => $data['notes'] ?? null,
      'manage_key'      => $manage_key,
      'created_at'      => $now,
      'updated_at'      => $now,
    ], ['%d','%d','%d','%s','%s','%s','%s','%s','%s','%s']);

    return (int)$wpdb->insert_id;
  }

  public static function find_by_manage_key(string $key) : ?array {
    global $wpdb;
    $table = self::table();

    $key = preg_replace('/[^a-f0-9]/', '', strtolower($key));
    if (strlen($key) !== 64) return null;

    $row = $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM {$table} WHERE manage_key = %s LIMIT 1", $key),
      ARRAY_A
    );

    return $row ?: null;
  }

  public static function cancel_by_key(string $key) : bool {
    global $wpdb;
    $table = self::table();

    $key = preg_replace('/[^a-f0-9]/', '', strtolower($key));
    if (strlen($key) !== 64) return false;

    $updated = $wpdb->update(
      $table,
      [
        'status' => 'cancelled',
        'updated_at' => self::now_mysql(),
      ],
      [
        'manage_key' => $key,
      ],
      ['%s','%s'],
      ['%s']
    );

    return ($updated !== false);
  }

  public static function all_with_relations(int $limit = 200) : array {
    global $wpdb;

    $bookings = $wpdb->prefix . 'bp_bookings';
    $services = $wpdb->prefix . 'bp_services';
    $customers = $wpdb->prefix . 'bp_customers';

    $limit = max(1, min(500, $limit));

    $sql = "
      SELECT
        b.*,
        s.name AS service_name,
        c.first_name AS customer_first_name,
        c.last_name AS customer_last_name,
        c.email AS customer_email
      FROM {$bookings} b
      LEFT JOIN {$services} s ON s.id = b.service_id
      LEFT JOIN {$customers} c ON c.id = b.customer_id
      ORDER BY b.id DESC
      LIMIT %d
    ";

    return $wpdb->get_results($wpdb->prepare($sql, $limit), ARRAY_A) ?: [];
  }

  public static function update_status(int $id, string $status) : bool {
    global $wpdb;
    $table = self::table();

    $allowed = ['pending','confirmed','cancelled','completed'];
    if (!in_array($status, $allowed, true)) return false;

    $updated = $wpdb->update(
      $table,
      [
        'status' => $status,
        'updated_at' => self::now_mysql(),
      ],
      ['id' => $id],
      ['%s','%s'],
      ['%d']
    );

    return ($updated !== false);
  }

  public static function find_by_customer(int $customer_id) : array {
    global $wpdb;
    $table = self::table();

    return $wpdb->get_results(
      $wpdb->prepare(
        "SELECT * FROM {$table} WHERE customer_id = %d ORDER BY start_datetime DESC",
        $customer_id
      ),
      ARRAY_A
    ) ?: [];
  }
}

