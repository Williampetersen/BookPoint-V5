<?php
defined('ABSPATH') || exit;
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- This file's wpdb SQL paths interpolate only hardcoded plugin table names with a sanitized WordPress prefix; dynamic values remain prepared or static by design.

final class POINTLYBOOKING_CustomerModel extends POINTLYBOOKING_Model {

  private static function is_safe_sql_identifier(string $identifier): bool {
    return preg_match('/^[A-Za-z0-9_]+$/', $identifier) === 1;
  }

  public static function table() : string {
    global $wpdb;
    return $wpdb->prefix . 'pointlybooking_customers';
  }

  public static function find_by_email(string $email) : ?array {
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names in this function are validated local plugin table names built from hardcoded plugin suffixes.
    global $wpdb;
    $customers_table = $wpdb->prefix . 'pointlybooking_customers';
    $email = sanitize_email($email);
    if ($email === '') return null;
    if (!self::is_safe_sql_identifier($customers_table)) {
      return null;
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $row = $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM {$customers_table} WHERE email = %s ORDER BY id DESC LIMIT 1", $email),
      ARRAY_A
    );

    return $row ?: null;
  }

  public static function create(array $data) : int {
    global $wpdb;
    $table = self::table();
    $now = self::now_mysql();

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
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
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names in this function are validated local plugin table names built from hardcoded plugin suffixes.
    global $wpdb;
    $customers_table = $wpdb->prefix . 'pointlybooking_customers';
    if (!self::is_safe_sql_identifier($customers_table)) {
      return [];
    }
    $limit = max(1, min(500, $limit));

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    return $wpdb->get_results(
      $wpdb->prepare("SELECT * FROM {$customers_table} ORDER BY id DESC LIMIT %d", $limit),
      ARRAY_A
    ) ?: [];
  }

  public static function find(int $id) : ?array {
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names in this function are validated local plugin table names built from hardcoded plugin suffixes.
    global $wpdb;
    $customers_table = $wpdb->prefix . 'pointlybooking_customers';
    if (!self::is_safe_sql_identifier($customers_table)) {
      return null;
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $row = $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM {$customers_table} WHERE id = %d", $id),
      ARRAY_A
    );

    return $row ?: null;
  }

  public static function anonymize(int $customer_id) : bool {
    global $wpdb;
    $table = self::table();

    $anon = 'deleted+' . $customer_id . '@example.invalid';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
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
