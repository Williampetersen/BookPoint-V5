<?php
defined('ABSPATH') || exit;

final class BP_CustomerModel extends BP_Model {

  public static function table() : string {
    global $wpdb;
    return $wpdb->prefix . 'bp_customers';
  }

  public static function find_by_email(string $email) : ?array {
    global $wpdb;
    $table = self::table();
    $email = sanitize_email($email);
    if ($email === '') return null;

    $row = $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM {$table} WHERE email = %s ORDER BY id DESC LIMIT 1", $email),
      ARRAY_A
    );

    return $row ?: null;
  }

  public static function create(array $data) : int {
    global $wpdb;
    $table = self::table();
    $now = self::now_mysql();

    $wpdb->insert($table, [
      'first_name' => $data['first_name'] ?? null,
      'last_name'  => $data['last_name'] ?? null,
      'email'      => $data['email'] ?? null,
      'phone'      => $data['phone'] ?? null,
      'custom_fields_json' => $data['custom_fields_json'] ?? null,
      'wp_user_id' => $data['wp_user_id'] ?? null,
      'created_at' => $now,
      'updated_at' => $now,
    ], ['%s','%s','%s','%s','%s','%d','%s','%s']);

    return (int)$wpdb->insert_id;
  }

  public static function find_or_create_by_email(array $data) : int {
    $email = sanitize_email($data['email'] ?? '');
    if ($email !== '') {
      $existing = self::find_by_email($email);
      if ($existing) return (int)$existing['id'];
    }
    return self::create($data);
  }

  public static function all(int $limit = 200) : array {
    global $wpdb;
    $table = self::table();

    $limit = max(1, min(500, $limit));

    return $wpdb->get_results(
      $wpdb->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit),
      ARRAY_A
    ) ?: [];
  }

  public static function find(int $id) : ?array {
    global $wpdb;
    $table = self::table();

    $row = $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
      ARRAY_A
    );

    return $row ?: null;
  }

  public static function anonymize(int $customer_id) : bool {
    global $wpdb;
    $table = self::table();

    $anon = 'deleted+' . $customer_id . '@example.invalid';
    $updated = $wpdb->update($table, [
      'first_name' => 'Deleted',
      'last_name' => 'Customer',
      'email' => $anon,
      'phone' => '',
      'updated_at' => self::now_mysql(),
    ], ['id' => $customer_id], ['%s','%s','%s','%s','%s'], ['%d']);

    return ($updated !== false);
  }
}

