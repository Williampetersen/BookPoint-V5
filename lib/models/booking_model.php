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

  public static function rotate_manage_token(int $booking_id, int $days_valid = 30) : ?string {
    $token = bin2hex(random_bytes(20));
    $ok = self::set_manage_token($booking_id, $token);
    return $ok ? $token : null;
  }

  public static function set_manage_token(int $booking_id, string $token) : bool {
    global $wpdb;
    $table = self::table();

    $updated = $wpdb->update(
      $table,
      [
        'manage_key' => $token,
        'updated_at' => self::now_mysql(),
      ],
      ['id' => $booking_id],
      ['%s','%s'],
      ['%d']
    );

    return ($updated !== false);
  }

  public static function mark_token_used(int $booking_id) : void {
    global $wpdb;
    $table = self::table();

    $wpdb->update(
      $table,
      ['manage_token_last_used_at' => current_time('mysql')],
      ['id' => $booking_id],
      ['%s'],
      ['%d']
    );
  }

  public static function update_times_public(int $id, string $start_dt, string $end_dt) : bool {
    return self::update_times($id, $start_dt, $end_dt);
  }

  public static function update_times(int $id, string $start_dt, string $end_dt) : bool {
    global $wpdb;
    $table = self::table();

    $updated = $wpdb->update(
      $table,
      [
        'start_datetime' => $start_dt,
        'end_datetime' => $end_dt,
        'updated_at' => self::now_mysql(),
      ],
      ['id' => $id],
      ['%s','%s','%s'],
      ['%d']
    );

    return ($updated !== false);
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

  public static function admin_list(array $args = []) : array {
    global $wpdb;

    $b = $wpdb->prefix . 'bp_bookings';
    $s = $wpdb->prefix . 'bp_services';
    $c = $wpdb->prefix . 'bp_customers';
    $a = $wpdb->prefix . 'bp_agents';

    $q = trim((string)($args['q'] ?? ''));
    $status = trim((string)($args['status'] ?? ''));
    $service_id = absint($args['service_id'] ?? 0);
    $agent_id = absint($args['agent_id'] ?? 0);
    $date_from = trim((string)($args['date_from'] ?? ''));
    $date_to = trim((string)($args['date_to'] ?? ''));

    $where = 'WHERE 1=1';
    $params = [];

    if ($status !== '' && in_array($status, ['pending','confirmed','cancelled'], true)) {
      $where .= ' AND b.status = %s';
      $params[] = $status;
    }
    if ($service_id > 0) {
      $where .= ' AND b.service_id = %d';
      $params[] = $service_id;
    }
    if ($agent_id > 0) {
      $where .= ' AND b.agent_id = %d';
      $params[] = $agent_id;
    }
    if ($date_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
      $where .= ' AND b.start_datetime >= %s';
      $params[] = $date_from . ' 00:00:00';
    }
    if ($date_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
      $where .= ' AND b.start_datetime <= %s';
      $params[] = $date_to . ' 23:59:59';
    }

    if ($q !== '') {
      $like = '%' . $wpdb->esc_like($q) . '%';
      $where .= " AND (
        s.name LIKE %s OR
        c.first_name LIKE %s OR c.last_name LIKE %s OR
        c.email LIKE %s OR c.phone LIKE %s OR
        a.first_name LIKE %s OR a.last_name LIKE %s
      )";
      array_push($params, $like, $like, $like, $like, $like, $like, $like);
    }

    $sql = "
      SELECT
        b.*,
        s.name AS service_name,
        CONCAT(IFNULL(c.first_name,''),' ',IFNULL(c.last_name,'')) AS customer_name,
        c.email AS customer_email,
        c.phone AS customer_phone,
        CONCAT(IFNULL(a.first_name,''),' ',IFNULL(a.last_name,'')) AS agent_name
      FROM {$b} b
      LEFT JOIN {$s} s ON s.id = b.service_id
      LEFT JOIN {$c} c ON c.id = b.customer_id
      LEFT JOIN {$a} a ON a.id = b.agent_id
      {$where}
      ORDER BY b.start_datetime DESC
      LIMIT 500
    ";

    $prepared = !empty($params) ? $wpdb->prepare($sql, $params) : $sql;
    return $wpdb->get_results($prepared, ARRAY_A) ?: [];
  }

  public static function admin_list_paged(array $args = []) : array {
    global $wpdb;

    $page = max(1, absint($args['page'] ?? 1));
    $per_page = max(10, min(200, absint($args['per_page'] ?? 50)));
    $offset = ($page - 1) * $per_page;

    $b = $wpdb->prefix . 'bp_bookings';
    $s = $wpdb->prefix . 'bp_services';
    $c = $wpdb->prefix . 'bp_customers';
    $a = $wpdb->prefix . 'bp_agents';

    $q = trim((string)($args['q'] ?? ''));
    $status = trim((string)($args['status'] ?? ''));
    $service_id = absint($args['service_id'] ?? 0);
    $agent_id = absint($args['agent_id'] ?? 0);
    $date_from = trim((string)($args['date_from'] ?? ''));
    $date_to = trim((string)($args['date_to'] ?? ''));

    $where = 'WHERE 1=1';
    $params = [];

    if ($status !== '' && in_array($status, ['pending','confirmed','cancelled'], true)) {
      $where .= ' AND b.status = %s';
      $params[] = $status;
    }
    if ($service_id > 0) {
      $where .= ' AND b.service_id = %d';
      $params[] = $service_id;
    }
    if ($agent_id > 0) {
      $where .= ' AND b.agent_id = %d';
      $params[] = $agent_id;
    }
    if ($date_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
      $where .= ' AND b.start_datetime >= %s';
      $params[] = $date_from . ' 00:00:00';
    }
    if ($date_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
      $where .= ' AND b.start_datetime <= %s';
      $params[] = $date_to . ' 23:59:59';
    }

    if ($q !== '') {
      $like = '%' . $wpdb->esc_like($q) . '%';
      $where .= " AND (
        s.name LIKE %s OR
        c.first_name LIKE %s OR c.last_name LIKE %s OR
        c.email LIKE %s OR c.phone LIKE %s OR
        a.first_name LIKE %s OR a.last_name LIKE %s
      )";
      array_push($params, $like, $like, $like, $like, $like, $like, $like);
    }

    $count_sql = "SELECT COUNT(*) FROM {$b} b
      LEFT JOIN {$s} s ON s.id = b.service_id
      LEFT JOIN {$c} c ON c.id = b.customer_id
      LEFT JOIN {$a} a ON a.id = b.agent_id
      {$where}";

    $list_sql = "
      SELECT
        b.*,
        s.name AS service_name,
        CONCAT(IFNULL(c.first_name,''),' ',IFNULL(c.last_name,'')) AS customer_name,
        c.email AS customer_email,
        c.phone AS customer_phone,
        CONCAT(IFNULL(a.first_name,''),' ',IFNULL(a.last_name,'')) AS agent_name
      FROM {$b} b
      LEFT JOIN {$s} s ON s.id = b.service_id
      LEFT JOIN {$c} c ON c.id = b.customer_id
      LEFT JOIN {$a} a ON a.id = b.agent_id
      {$where}
      ORDER BY b.start_datetime DESC
      LIMIT %d OFFSET %d
    ";

    $count_prepared = !empty($params) ? $wpdb->prepare($count_sql, $params) : $count_sql;
    $total = (int)$wpdb->get_var($count_prepared);

    $list_params = array_merge($params, [$per_page, $offset]);
    $items = $wpdb->get_results($wpdb->prepare($list_sql, $list_params), ARRAY_A) ?: [];

    return [
      'items' => $items,
      'total' => $total,
      'page' => $page,
      'per_page' => $per_page,
    ];
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

  public static function update_notes(int $id, string $notes) : bool {
    global $wpdb;
    $table = self::table();

    $updated = $wpdb->update(
      $table,
      [
        'notes' => $notes,
        'updated_at' => current_time('mysql'),
      ],
      ['id' => $id],
      ['%s','%s'],
      ['%d']
    );

    return ($updated !== false);
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

  public static function find_by_customer_email(string $email) : array {
    global $wpdb;

    $b = $wpdb->prefix . 'bp_bookings';
    $c = $wpdb->prefix . 'bp_customers';

    $email = sanitize_email($email);
    if ($email === '') return [];

    $sql = "
      SELECT b.*
      FROM {$b} b
      INNER JOIN {$c} c ON c.id = b.customer_id
      WHERE c.email = %s
      ORDER BY b.start_datetime DESC
      LIMIT 200
    ";

    return $wpdb->get_results($wpdb->prepare($sql, $email), ARRAY_A) ?: [];
  }

  public static function detach_customer(int $customer_id) : void {
    global $wpdb;
    $table = self::table();

    $wpdb->update(
      $table,
      ['customer_id' => null],
      ['customer_id' => $customer_id],
      ['%d'],
      ['%d']
    );
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

