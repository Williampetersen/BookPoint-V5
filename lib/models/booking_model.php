<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_BookingModel extends POINTLYBOOKING_Model {

  public static function table() : string {
    global $wpdb;
    return $wpdb->prefix . 'pointlybooking_bookings';
  }

  public static function create(array $data) : int {
    global $wpdb;
    $table = self::table();
    $now = self::now_mysql();

    $manage_key = bin2hex(random_bytes(32));

    // Step 16: Include agent_id if specified
    $agent_id = isset($data['agent_id']) && $data['agent_id'] > 0 ? (int)$data['agent_id'] : null;

    $default_status = $data['status'] ?? null;
    if (!$default_status && class_exists('POINTLYBOOKING_SettingsHelper')) {
      $default_status = POINTLYBOOKING_SettingsHelper::get('pointlybooking_default_booking_status', 'pending');
    }
    $default_status = sanitize_key((string)($default_status ?: 'pending'));
    if (!in_array($default_status, ['pending', 'pending_payment', 'confirmed', 'cancelled', 'completed', 'failed_payment'], true)) {
      $default_status = 'pending';
    }

    $payment_currency = $data['payment_currency'] ?? ($data['currency'] ?? null);
    $payment_amount = isset($data['payment_amount'])
      ? (float)$data['payment_amount']
      : (isset($data['total_price']) ? (float)$data['total_price'] : 0);

    $payload = [
      'service_id'      => (int)$data['service_id'],
      'customer_id'     => (int)$data['customer_id'],
      'agent_id'        => $agent_id,
      'start_datetime'  => $data['start_datetime'],
      'end_datetime'    => $data['end_datetime'],
      'status'          => $default_status,
      'notes'           => $data['notes'] ?? null,
      'payment_method'  => $data['payment_method'] ?? null,
      'payment_status'  => $data['payment_status'] ?? null,
      'payment_provider_ref' => $data['payment_provider_ref'] ?? null,
      'payment_amount' => $payment_amount,
      'payment_currency' => $payment_currency,
      'currency'        => $data['currency'] ?? null,
      'total_price'     => isset($data['total_price']) ? (float)$data['total_price'] : 0,
      'customer_fields_json' => $data['customer_fields_json'] ?? null,
      'booking_fields_json' => $data['booking_fields_json'] ?? null,
      'custom_fields_json' => $data['custom_fields_json'] ?? null,
      'manage_key'      => $manage_key,
      'created_at'      => $now,
      'updated_at'      => $now,
    ];

    $wpdb->insert(
      $table,
      $payload,
      ['%d','%d','%d','%s','%s','%s','%s','%s','%s','%s','%f','%s','%s','%f','%s','%s','%s','%s','%s','%s']
    );
    $booking_id = (int)$wpdb->insert_id;

    // Notifications: trigger workflows for booking_created
    if ($booking_id) {
      if (class_exists('POINTLYBOOKING_Notifications_Helper')) {
        POINTLYBOOKING_Notifications_Helper::run_workflows_for_event('booking_created', $booking_id);
      }
    }
    return $booking_id;
  }

  public static function find_by_manage_key(string $key) : ?array {
    global $wpdb;
    $table = self::table();

    $key = preg_replace('/[^a-f0-9]/', '', strtolower($key));
    if (strlen($key) !== 64) return null;

    $row = $wpdb->get_row(
      pointlybooking_prepare_query_with_identifiers(
        "SELECT * FROM %i WHERE manage_key = %s LIMIT 1",
        [$table],
        [$key]
      ),
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

    $bookings = pointlybooking_table('bookings');
    $services = pointlybooking_table('services');
    $customers = pointlybooking_table('customers');

    $limit = max(1, min(500, $limit));

    return $wpdb->get_results(
      pointlybooking_prepare_query_with_identifiers(
        "SELECT
          b.*,
          s.name AS service_name,
          c.first_name AS customer_first_name,
          c.last_name AS customer_last_name,
          c.email AS customer_email
        FROM %i b
        LEFT JOIN %i s ON s.id = b.service_id
        LEFT JOIN %i c ON c.id = b.customer_id
        ORDER BY b.id DESC
        LIMIT %d",
        [$bookings, $services, $customers],
        [$limit]
      ),
      ARRAY_A
    ) ?: [];
  }

  public static function admin_list(array $args = []) : array {
    global $wpdb;

    $b = pointlybooking_table('bookings');
    $s = pointlybooking_table('services');
    $c = pointlybooking_table('customers');
    $a = pointlybooking_table('agents');

    $q = trim((string)($args['q'] ?? ''));
    $status = trim((string)($args['status'] ?? ''));
    $service_id = absint($args['service_id'] ?? 0);
    $agent_id = absint($args['agent_id'] ?? 0);
    $date_from = trim((string)($args['date_from'] ?? ''));
    $date_to = trim((string)($args['date_to'] ?? ''));

    $where_clauses = ['1=1'];
    $params = [];

    if ($status !== '' && in_array($status, ['pending','confirmed','cancelled'], true)) {
      $where_clauses[] = 'b.status = %s';
      $params[] = $status;
    }
    if ($service_id > 0) {
      $where_clauses[] = 'b.service_id = %d';
      $params[] = $service_id;
    }
    if ($agent_id > 0) {
      $where_clauses[] = 'b.agent_id = %d';
      $params[] = $agent_id;
    }
    if ($date_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
      $where_clauses[] = 'b.start_datetime >= %s';
      $params[] = $date_from . ' 00:00:00';
    }
    if ($date_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
      $where_clauses[] = 'b.start_datetime <= %s';
      $params[] = $date_to . ' 23:59:59';
    }

    if ($q !== '') {
      $like = '%' . $wpdb->esc_like($q) . '%';
      $where_clauses[] = "(
        s.name LIKE %s OR
        c.first_name LIKE %s OR c.last_name LIKE %s OR
        c.email LIKE %s OR c.phone LIKE %s OR
        a.first_name LIKE %s OR a.last_name LIKE %s
      )";
      array_push($params, $like, $like, $like, $like, $like, $like, $like);
    }
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

    $sql = "SELECT
          b.*,
          s.name AS service_name,
          CONCAT(IFNULL(c.first_name,''),' ',IFNULL(c.last_name,'')) AS customer_name,
          c.email AS customer_email,
          c.phone AS customer_phone,
          CONCAT(IFNULL(a.first_name,''),' ',IFNULL(a.last_name,'')) AS agent_name
        FROM %i b
        LEFT JOIN %i s ON s.id = b.service_id
        LEFT JOIN %i c ON c.id = b.customer_id
        LEFT JOIN %i a ON a.id = b.agent_id
        " . $where_sql . "
        ORDER BY b.start_datetime DESC
        LIMIT 500";
    return $wpdb->get_results(
      pointlybooking_prepare_query_with_identifiers(
        $sql,
        [$b, $s, $c, $a],
        $params
      ),
      ARRAY_A
    ) ?: [];
  }

  public static function admin_list_paged(array $args = []) : array {
    global $wpdb;

    $page = max(1, absint($args['page'] ?? 1));
    $per_page = max(10, min(200, absint($args['per_page'] ?? 50)));
    $offset = ($page - 1) * $per_page;

    $b = pointlybooking_table('bookings');
    $s = pointlybooking_table('services');
    $c = pointlybooking_table('customers');
    $a = pointlybooking_table('agents');

    $q = trim((string)($args['q'] ?? ''));
    $status = trim((string)($args['status'] ?? ''));
    $service_id = absint($args['service_id'] ?? 0);
    $agent_id = absint($args['agent_id'] ?? 0);
    $date_from = trim((string)($args['date_from'] ?? ''));
    $date_to = trim((string)($args['date_to'] ?? ''));

    $where_clauses = ['1=1'];
    $params = [];

    if ($status !== '' && in_array($status, ['pending','confirmed','cancelled'], true)) {
      $where_clauses[] = 'b.status = %s';
      $params[] = $status;
    }
    if ($service_id > 0) {
      $where_clauses[] = 'b.service_id = %d';
      $params[] = $service_id;
    }
    if ($agent_id > 0) {
      $where_clauses[] = 'b.agent_id = %d';
      $params[] = $agent_id;
    }
    if ($date_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
      $where_clauses[] = 'b.start_datetime >= %s';
      $params[] = $date_from . ' 00:00:00';
    }
    if ($date_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
      $where_clauses[] = 'b.start_datetime <= %s';
      $params[] = $date_to . ' 23:59:59';
    }

    if ($q !== '') {
      $like = '%' . $wpdb->esc_like($q) . '%';
      $where_clauses[] = "(
        s.name LIKE %s OR
        c.first_name LIKE %s OR c.last_name LIKE %s OR
        c.email LIKE %s OR c.phone LIKE %s OR
        a.first_name LIKE %s OR a.last_name LIKE %s
      )";
      array_push($params, $like, $like, $like, $like, $like, $like, $like);
    }
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

    $total_sql = "SELECT COUNT(*) FROM %i b
        LEFT JOIN %i s ON s.id = b.service_id
        LEFT JOIN %i c ON c.id = b.customer_id
        LEFT JOIN %i a ON a.id = b.agent_id
        " . $where_sql;
    $total = (int)$wpdb->get_var(
      pointlybooking_prepare_query_with_identifiers(
        $total_sql,
        [$b, $s, $c, $a],
        $params
      )
    );

    $items_sql = "SELECT
          b.*,
          s.name AS service_name,
          CONCAT(IFNULL(c.first_name,''),' ',IFNULL(c.last_name,'')) AS customer_name,
          c.email AS customer_email,
          c.phone AS customer_phone,
          CONCAT(IFNULL(a.first_name,''),' ',IFNULL(a.last_name,'')) AS agent_name
        FROM %i b
        LEFT JOIN %i s ON s.id = b.service_id
        LEFT JOIN %i c ON c.id = b.customer_id
        LEFT JOIN %i a ON a.id = b.agent_id
        " . $where_sql . "
        ORDER BY b.start_datetime DESC
        LIMIT %d OFFSET %d";
    $items = $wpdb->get_results(
      pointlybooking_prepare_query_with_identifiers(
        $items_sql,
        [$b, $s, $c, $a],
        array_merge($params, [$per_page, $offset])
      ),
      ARRAY_A
    ) ?: [];

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

    $allowed = ['pending','pending_payment','confirmed','cancelled','completed','failed_payment'];
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

    // Notifications: trigger workflows for booking_updated and status-specific events
    if ($updated !== false && class_exists('POINTLYBOOKING_Notifications_Helper')) {
      POINTLYBOOKING_Notifications_Helper::run_workflows_for_event('booking_updated', $id);
      if ($status === 'confirmed') {
        POINTLYBOOKING_Notifications_Helper::run_workflows_for_event('booking_confirmed', $id);
      } elseif ($status === 'cancelled') {
        POINTLYBOOKING_Notifications_Helper::run_workflows_for_event('booking_cancelled', $id);
      }
    }
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
      pointlybooking_prepare_query_with_identifiers(
        "SELECT * FROM %i WHERE id = %d",
        [$table],
        [$id]
      ),
      ARRAY_A
    );

    return $row ?: null;
  }

  public static function find_by_customer_email(string $email) : array {
    global $wpdb;

    $b = pointlybooking_table('bookings');
    $c = pointlybooking_table('customers');

    $email = sanitize_email($email);
    if ($email === '') return [];

    return $wpdb->get_results(
      pointlybooking_prepare_query_with_identifiers(
        "SELECT b.*
        FROM %i b
        INNER JOIN %i c ON c.id = b.customer_id
        WHERE c.email = %s
        ORDER BY b.start_datetime DESC
        LIMIT 200",
        [$b, $c],
        [$email]
      ),
      ARRAY_A
    ) ?: [];
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
      pointlybooking_prepare_query_with_identifiers(
        "SELECT * FROM %i WHERE customer_id = %d ORDER BY start_datetime DESC",
        [$table],
        [$customer_id]
      ),
      ARRAY_A
    ) ?: [];
  }
}
