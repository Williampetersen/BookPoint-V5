<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_Notifications_Helper {

  private const WORKFLOW_TABLE = 'pointlybooking_workflows';
  private const ACTION_TABLE = 'pointlybooking_workflow_actions';
  private const LOG_TABLE = 'pointlybooking_workflow_logs';
  public const CRON_HOOK = 'pointlybooking_run_workflow_event';
  private const EVENTS = [
    'booking_created',
    'booking_updated',
    'booking_confirmed',
    'booking_cancelled',
    'customer_created',
  ];
  private static string $last_error = '';

  public static function last_error(): string {
    return self::$last_error;
  }

  public static function ensure_tables(): void {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset = $wpdb->get_charset_collate();
    $workflows = self::table(self::WORKFLOW_TABLE);
    $actions = self::table(self::ACTION_TABLE);
    $logs = self::table(self::LOG_TABLE);

    dbDelta("
      CREATE TABLE {$workflows} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        event_key VARCHAR(80) NOT NULL,
        is_conditional TINYINT(1) NOT NULL DEFAULT 0,
        conditions_json LONGTEXT NULL,
        has_time_offset TINYINT(1) NOT NULL DEFAULT 0,
        time_offset_minutes INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        PRIMARY KEY (id),
        KEY event_key (event_key),
        KEY status (status)
      ) {$charset};
    ");

    dbDelta("
      CREATE TABLE {$actions} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        workflow_id BIGINT UNSIGNED NOT NULL,
        type VARCHAR(40) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        config_json LONGTEXT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        PRIMARY KEY (id),
        KEY workflow_id (workflow_id),
        KEY sort_order (sort_order)
      ) {$charset};
    ");

    dbDelta("
      CREATE TABLE {$logs} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        workflow_id BIGINT UNSIGNED NOT NULL,
        event_key VARCHAR(80) NOT NULL,
        entity_type VARCHAR(40) NOT NULL,
        entity_id BIGINT UNSIGNED NULL,
        status VARCHAR(20) NOT NULL,
        message LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY workflow_id (workflow_id),
        KEY event_key (event_key),
        KEY entity_type (entity_type),
        KEY entity_id (entity_id)
      ) {$charset};
    ");
  }

  public static function allowed_events(): array {
    return self::EVENTS;
  }

  public static function run_workflows_for_event(string $event_key, int $booking_id): void {
    if (!in_array($event_key, self::EVENTS, true)) {
      return;
    }

    $booking = POINTLYBOOKING_BookingModel::find($booking_id);
    if (!$booking) {
      return;
    }

    $payload = self::build_payload_from_booking($event_key, $booking);
    if (!$payload) {
      return;
    }

    self::run_workflows($event_key, $payload);
  }

  public static function run_workflows(string $event_key, array $payload): void {
    $workflows = self::fetch_workflows($event_key);
    foreach ($workflows as $workflow) {
      if (!empty($workflow['has_time_offset']) && (int)($workflow['time_offset_minutes'] ?? 0) > 0) {
        self::schedule_workflow($workflow, $payload);
        continue;
      }
      self::execute_workflow($workflow, $payload);
    }
  }

  public static function handle_scheduled_event(int $workflow_id, string $payload_json): void {
    $workflow = self::get_workflow($workflow_id);
    if (!$workflow) {
      return;
    }

    $payload = json_decode($payload_json, true);
    if (!is_array($payload)) {
      return;
    }

    self::execute_workflow($workflow, $payload);
  }

  public static function list_workflows(array $filters = []): array {
    global $wpdb;
    $table = self::table(self::WORKFLOW_TABLE);

    $where = ['1=1'];
    $params = [];

    $status = $filters['status'] ?? 'all';
    if ($status !== 'all' && in_array($status, ['active', 'disabled'], true)) {
      $where[] = 'status = %s';
      $params[] = $status;
    }

    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
      $where[] = 'name LIKE %s';
      $params[] = '%' . $wpdb->esc_like($search) . '%';
    }

    $event = trim((string)($filters['event'] ?? ''));
    if ($event !== '' && in_array($event, self::EVENTS, true)) {
      $where[] = 'event_key = %s';
      $params[] = $event;
    }
    $where[] = '%d = %d';
    $params[] = 1;
    $params[] = 1;

    $page = max(1, (int)($filters['page'] ?? 1));
    $per = min(50, max(10, (int)($filters['per'] ?? 20)));
    $offset = ($page - 1) * $per;

    $where_sql = implode(' AND ', $where);
    $total_query = "SELECT COUNT(*) FROM %i WHERE " . $where_sql;
    $rows_query = "SELECT w.*,
      (\n        SELECT COUNT(*) FROM %i a WHERE a.workflow_id = w.id\n      ) as actions_count,
      (\n        SELECT MAX(l.created_at) FROM %i l WHERE l.workflow_id = w.id\n      ) as last_run_at,
      (\n        SELECT l.status FROM %i l WHERE l.workflow_id = w.id ORDER BY l.id DESC LIMIT 1\n      ) as last_run_status
    FROM %i w
    WHERE " . $where_sql . "
    ORDER BY w.updated_at DESC, w.id DESC
    LIMIT %d OFFSET %d";

    $total = (int)$wpdb->get_var(
      pointlybooking_prepare_query_with_identifiers(
        $total_query,
        [$table],
        $params
      )
    );

    $action_table = self::table(self::ACTION_TABLE);
    $log_table = self::table(self::LOG_TABLE);

    $rows = $wpdb->get_results(
      pointlybooking_prepare_query_with_identifiers(
        $rows_query,
        [$action_table, $log_table, $log_table, $table],
        array_merge($params, [$per, $offset])
      ),
      ARRAY_A
    ) ?: [];

    return [
      'items' => $rows,
      'total' => $total,
      'page' => $page,
      'per_page' => $per,
    ];
  }

  public static function get_workflow(int $workflow_id): ?array {
    global $wpdb;
    $table = self::table(self::WORKFLOW_TABLE);
    $row = $wpdb->get_row(
      pointlybooking_prepare_query_with_identifiers(
        "SELECT * FROM %i WHERE id = %d",
        [$table],
        [$workflow_id]
      ),
      ARRAY_A
    );
    if (!$row) {
      return null;
    }

    $row['actions'] = self::fetch_actions($workflow_id);
    return $row;
  }

  public static function create_workflow(array $data) {
    global $wpdb;
    $table = self::table(self::WORKFLOW_TABLE);

    self::ensure_tables();
    self::$last_error = '';

    $payload = [
      'name' => sanitize_text_field($data['name'] ?? 'Untitled workflow'),
      'status' => in_array($data['status'] ?? 'active', ['active', 'disabled'], true) ? $data['status'] : 'active',
      'event_key' => in_array($data['event_key'] ?? '', self::EVENTS, true) ? $data['event_key'] : self::EVENTS[0],
      'is_conditional' => !empty($data['is_conditional']) ? 1 : 0,
      'conditions_json' => !empty($data['conditions']) ? wp_json_encode($data['conditions']) : null,
      'has_time_offset' => !empty($data['has_time_offset']) ? 1 : 0,
      'time_offset_minutes' => max(0, (int)($data['time_offset_minutes'] ?? 0)),
      'created_at' => current_time('mysql'),
      'updated_at' => current_time('mysql'),
    ];

    $formats = ['%s','%s','%s','%d','%s','%d','%d','%s','%s'];
    $ok = $wpdb->insert($table, $payload, $formats);
    $id = (int)$wpdb->insert_id;
    if (!$ok || !$id) {
      self::$last_error = $wpdb->last_error ? $wpdb->last_error : 'DB insert failed';
      return null;
    }
    return self::get_workflow($id);
  }

  public static function update_workflow(int $workflow_id, array $data): bool {
    global $wpdb;
    $table = self::table(self::WORKFLOW_TABLE);

    $payload = [];
    if (isset($data['name'])) {
      $payload['name'] = sanitize_text_field($data['name']);
    }
    if (isset($data['status']) && in_array($data['status'], ['active', 'disabled'], true)) {
      $payload['status'] = $data['status'];
    }
    if (isset($data['event_key']) && in_array($data['event_key'], self::EVENTS, true)) {
      $payload['event_key'] = $data['event_key'];
    }
    if (isset($data['is_conditional'])) {
      $payload['is_conditional'] = !empty($data['is_conditional']) ? 1 : 0;
    }
    if (array_key_exists('conditions', $data)) {
      $payload['conditions_json'] = !empty($data['conditions']) ? wp_json_encode($data['conditions']) : null;
    }
    if (array_key_exists('has_time_offset', $data)) {
      $payload['has_time_offset'] = !empty($data['has_time_offset']) ? 1 : 0;
    }
    if (isset($data['time_offset_minutes'])) {
      $payload['time_offset_minutes'] = max(0, (int)$data['time_offset_minutes']);
    }

    if (empty($payload)) {
      return false;
    }

    $payload['updated_at'] = current_time('mysql');

    $wpdb->update($table, $payload, ['id' => $workflow_id], null, ['%d']);
    return $wpdb->rows_affected !== false;
  }

  public static function delete_workflow(int $workflow_id): bool {
    global $wpdb;
    $workflow_table = self::table(self::WORKFLOW_TABLE);
    $action_table = self::table(self::ACTION_TABLE);
    $log_table = self::table(self::LOG_TABLE);

    $wpdb->delete($action_table, ['workflow_id' => $workflow_id], ['%d']);
    $wpdb->delete($log_table, ['workflow_id' => $workflow_id], ['%d']);

    return (bool)$wpdb->delete($workflow_table, ['id' => $workflow_id], ['%d']);
  }

  public static function create_action(int $workflow_id, array $data): ?array {
    global $wpdb;
    $table = self::table(self::ACTION_TABLE);

    $config = isset($data['config']) && is_array($data['config']) ? $data['config'] : [];

    $sort_order = (int)$wpdb->get_var(
      pointlybooking_prepare_query_with_identifiers(
        "SELECT IFNULL(MAX(sort_order), 0) + 1 FROM %i WHERE workflow_id = %d",
        [$table],
        [$workflow_id]
      )
    );

    $payload = [
      'workflow_id' => $workflow_id,
      'type' => sanitize_text_field($data['type'] ?? 'send_email'),
      'status' => in_array($data['status'] ?? 'active', ['active', 'disabled'], true) ? $data['status'] : 'active',
      'config_json' => wp_json_encode($config),
      'sort_order' => $sort_order,
      'created_at' => current_time('mysql'),
      'updated_at' => current_time('mysql'),
    ];

    $formats = ['%d','%s','%s','%s','%d','%s','%s'];
    $wpdb->insert($table, $payload, $formats);
    $id = (int)$wpdb->insert_id;
    return $id ? self::get_action($id) : null;
  }

  public static function update_action(int $action_id, array $data): bool {
    global $wpdb;
    $table = self::table(self::ACTION_TABLE);

    $payload = [];
    if (isset($data['type'])) {
      $payload['type'] = sanitize_text_field($data['type']);
    }
    if (isset($data['status']) && in_array($data['status'], ['active', 'disabled'], true)) {
      $payload['status'] = $data['status'];
    }
    if (array_key_exists('config', $data) && is_array($data['config'])) {
      $payload['config_json'] = wp_json_encode($data['config']);
    }
    if (isset($data['sort_order'])) {
      $payload['sort_order'] = (int)$data['sort_order'];
    }
    if (empty($payload)) {
      return false;
    }

    $payload['updated_at'] = current_time('mysql');

    $wpdb->update($table, $payload, ['id' => $action_id], null, ['%d']);
    return $wpdb->rows_affected !== false;
  }

  public static function delete_action(int $action_id): bool {
    global $wpdb;
    $table = self::table(self::ACTION_TABLE);
    return (bool)$wpdb->delete($table, ['id' => $action_id], ['%d']);
  }

  public static function get_action(int $action_id): ?array {
    global $wpdb;
    $table = self::table(self::ACTION_TABLE);
    $row = $wpdb->get_row(
      pointlybooking_prepare_query_with_identifiers(
        "SELECT * FROM %i WHERE id = %d",
        [$table],
        [$action_id]
      ),
      ARRAY_A
    );
    if (!$row) {
      return null;
    }

    $row['config'] = self::decode_json($row['config_json'] ?? '{}');
    return $row;
  }

  public static function test_action(int $action_id, array $payload = []): bool {
    $action = self::get_action($action_id);
    if (!$action) {
      return false;
    }

    $workflow = self::get_workflow((int)$action['workflow_id']);
    if (!$workflow) {
      return false;
    }

    if (empty($payload)) {
      $payload = $workflow['payload'] ?? [];
    }

    return self::execute_action($workflow, $action, $payload);
  }

  private static function fetch_workflows(string $event_key): array {
    global $wpdb;
    $table = self::table(self::WORKFLOW_TABLE);
    $rows = $wpdb->get_results(
      pointlybooking_prepare_query_with_identifiers(
        "SELECT * FROM %i WHERE event_key = %s AND status = 'active' ORDER BY updated_at DESC",
        [$table],
        [$event_key]
      ),
      ARRAY_A
    );
    return $rows ?: [];
  }

  private static function fetch_actions(int $workflow_id): array {
    global $wpdb;
    $table = self::table(self::ACTION_TABLE);
    $rows = $wpdb->get_results(
      pointlybooking_prepare_query_with_identifiers(
        "SELECT * FROM %i WHERE workflow_id = %d ORDER BY sort_order ASC",
        [$table],
        [$workflow_id]
      ),
      ARRAY_A
    );
    if (!$rows) {
      return [];
    }
    return array_map(function($row) {
      $row['config'] = self::decode_json($row['config_json'] ?? '{}');
      return $row;
    }, $rows);
  }

  private static function execute_workflow(array $workflow, array $payload): void {
    $actions = self::fetch_actions((int)$workflow['id']);
    foreach ($actions as $action) {
      self::execute_action($workflow, $action, $payload);
    }
  }

  private static function execute_action(array $workflow, array $action, array $payload): bool {
    $payload = self::ensure_context($payload, $workflow['event_key']);
    if ($action['type'] !== 'send_email') {
      self::log(
        (int)$workflow['id'],
        $workflow['event_key'],
        $payload['entity_type'] ?? '',
        $payload['entity_id'] ?? 0,
        'failed',
        'Unsupported action type'
      );
      return false;
    }

    return self::execute_send_email_action((int)$workflow['id'], $workflow, $action, $payload);
  }

  private static function execute_send_email_action(int $workflow_id, array $workflow, array $action, array $payload): bool {
    $event = $workflow['event_key'];
    $entity_type = $payload['entity_type'] ?? '';
    $entity_id = (int)($payload['entity_id'] ?? 0);

    $config = $action['config'] ?? [];
    $context = $payload['context'] ?? [];

    $to = self::render_template_value($config['to'] ?? '', $context);
    $subject = self::render_template_value($config['subject'] ?? __('Booking Notification', 'bookpoint-booking'), $context);
    $body = self::render_template_value($config['body'] ?? '', $context);
    $from_name = $config['from_name'] ?? null;
    $from_email = $config['from_email'] ?? null;

    if ($to === '') {
      self::log($workflow_id, $event, $entity_type, $entity_id, 'failed', 'Recipient missing');
      return false;
    }

    $attachments = [];
    if (!empty($config['attach_ics'])) {
      $ics = self::build_ics_attachment($payload);
      if ($ics) {
        $attachments[] = $ics;
      }
    }

    $sent = POINTLYBOOKING_EmailHelper::send($to, $subject, $body, [
      'from_name' => $from_name,
      'from_email' => $from_email,
      'attachments' => $attachments,
    ]);

    foreach ($attachments as $path) {
      if (!is_string($path) || $path === '') {
        continue;
      }
      if (function_exists('wp_delete_file')) {
        wp_delete_file($path);
      }
    }

    self::log(
      $workflow_id,
      $event,
      $entity_type,
      $entity_id,
      $sent ? 'success' : 'failed',
      $sent ? 'Email dispatched' : 'Email failed'
    );

    return $sent;
  }

  private static function schedule_workflow(array $workflow, array $payload): void {
    $minutes = max(0, (int)($workflow['time_offset_minutes'] ?? 0));
    if ($minutes <= 0) {
      self::execute_workflow($workflow, $payload);
      return;
    }

    $args = [
      (int)$workflow['id'],
      wp_json_encode($payload),
    ];

    $timestamp = time() + ($minutes * 60);
    wp_schedule_single_event($timestamp, self::CRON_HOOK, $args);
  }

  private static function ensure_context(array $payload, string $event_key): array {
    if (!isset($payload['context'])) {
      $payload['context'] = self::build_context($payload);
    }
    if (!isset($payload['event_key'])) {
      $payload['event_key'] = $event_key;
    }
    return $payload;
  }

  public static function build_payload_from_booking(string $event_key, array $booking): ?array {
    $service = POINTLYBOOKING_ServiceModel::find((int)($booking['service_id'] ?? 0)) ?: [];
    $customer = $booking['customer_id'] ? POINTLYBOOKING_CustomerModel::find((int)$booking['customer_id']) : [];
    $agent = $booking['agent_id'] ? POINTLYBOOKING_AgentModel::find((int)$booking['agent_id']) : [];

    $pricing = self::build_pricing($booking, $service);
    $links = self::build_links($booking);
    $custom_fields = self::collect_custom_fields($booking);

    $payload = [
      'event_key' => $event_key,
      'entity_type' => 'booking',
      'entity_id' => (int)($booking['id'] ?? 0),
      'booking' => $booking,
      'service' => $service,
      'customer' => $customer,
      'agent' => $agent,
      'pricing' => $pricing,
      'links' => $links,
      'custom_fields' => $custom_fields,
    ];

    $payload['context'] = self::build_context($payload);
    return $payload;
  }

  private static function build_context(array $payload): array {
    $booking = $payload['booking'] ?? [];
    $service = $payload['service'] ?? [];
    $customer = $payload['customer'] ?? [];
    $agent = $payload['agent'] ?? [];
    $pricing = $payload['pricing'] ?? [];
    $links = $payload['links'] ?? [];
    $custom_fields = $payload['custom_fields'] ?? [];

    $start = $booking['start_datetime'] ?? '';
    $end = $booking['end_datetime'] ?? '';
    $start_ts = $start ? strtotime($start) : false;
    $end_ts = $end ? strtotime($end) : false;
    $duration = (int)(($end_ts && $start_ts) ? max(0, ($end_ts - $start_ts) / 60) : 0);

    $customer_name = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
    if ($customer_name === '' && !empty($customer['name'])) {
      $customer_name = (string)$customer['name'];
    }

    $agent_name = trim(($agent['first_name'] ?? '') . ' ' . ($agent['last_name'] ?? ''));
    if ($agent_name === '' && !empty($agent['name'])) {
      $agent_name = (string)$agent['name'];
    }

    $service_name = trim((string)($service['name'] ?? ''));
    $service_name = $service_name === '' ? __('Service', 'bookpoint-booking') : $service_name;

    $context = [
      'site_name' => (string)get_bloginfo('name'),
      'site_url' => (string)home_url('/'),
      'admin_email' => (string)get_option('admin_email'),
      'booking_id' => (int)($booking['id'] ?? 0),
      'booking_status' => (string)($booking['status'] ?? ''),
      'booking_notes' => (string)($booking['notes'] ?? ''),
      'start_datetime' => $start,
      'end_datetime' => $end,
      'start_date' => $start_ts ? gmdate('Y-m-d', $start_ts) : '',
      'start_time' => $start_ts ? gmdate('H:i', $start_ts) : '',
      'end_date' => $end_ts ? gmdate('Y-m-d', $end_ts) : '',
      'end_time' => $end_ts ? gmdate('H:i', $end_ts) : '',
      'booking_duration' => $duration,
      'customer_name' => $customer_name,
      'customer_email' => (string)($customer['email'] ?? ''),
      'customer_phone' => (string)($customer['phone'] ?? ''),
      'agent_name' => $agent_name,
      'agent_email' => (string)($agent['email'] ?? ''),
      'agent_phone' => (string)($agent['phone'] ?? ''),
      'service_name' => $service_name,
      'service_duration' => (int)($service['duration_minutes'] ?? $booking['duration_minutes'] ?? 0),
      'subtotal' => (string)($pricing['subtotal'] ?? ''),
      'discount' => (string)($pricing['discount_total'] ?? ''),
      'tax' => (string)($pricing['tax_total'] ?? ''),
      'total' => (string)($pricing['total'] ?? ''),
      'promo_code' => (string)($pricing['promo_code'] ?? ''),
    ];

    foreach ($links as $key => $value) {
      $context[$key] = $value;
    }

    foreach ($custom_fields as $slug => $value) {
      $context['field_' . sanitize_key($slug)] = $value;
    }

    return $context;
  }

  public static function smart_variables(string $event_key): array {
    return self::smart_variables_data();
  }

  private static function smart_variables_data(): array {
    $vars = [
      [
        'label' => __('Site', 'bookpoint-booking'),
        'variables' => [
          ['key' => 'site_name', 'label' => __('Site name', 'bookpoint-booking')],
          ['key' => 'site_url', 'label' => __('Site URL', 'bookpoint-booking')],
          ['key' => 'admin_email', 'label' => __('Admin email', 'bookpoint-booking')],
        ],
      ],
      [
        'label' => __('Appointment', 'bookpoint-booking'),
        'variables' => [
          ['key' => 'booking_id', 'label' => __('Booking ID', 'bookpoint-booking')],
          ['key' => 'booking_status', 'label' => __('Status', 'bookpoint-booking')],
          ['key' => 'start_date', 'label' => __('Start date', 'bookpoint-booking')],
          ['key' => 'start_time', 'label' => __('Start time', 'bookpoint-booking')],
          ['key' => 'end_date', 'label' => __('End date', 'bookpoint-booking')],
          ['key' => 'end_time', 'label' => __('End time', 'bookpoint-booking')],
          ['key' => 'booking_duration', 'label' => __('Duration (minutes)', 'bookpoint-booking')],
        ],
      ],
      [
        'label' => __('Customer', 'bookpoint-booking'),
        'variables' => [
          ['key' => 'customer_name', 'label' => __('Name', 'bookpoint-booking')],
          ['key' => 'customer_email', 'label' => __('Email', 'bookpoint-booking')],
          ['key' => 'customer_phone', 'label' => __('Phone', 'bookpoint-booking')],
        ],
      ],
      [
        'label' => __('Agent', 'bookpoint-booking'),
        'variables' => [
          ['key' => 'agent_name', 'label' => __('Name', 'bookpoint-booking')],
          ['key' => 'agent_email', 'label' => __('Email', 'bookpoint-booking')],
          ['key' => 'agent_phone', 'label' => __('Phone', 'bookpoint-booking')],
        ],
      ],
      [
        'label' => __('Service', 'bookpoint-booking'),
        'variables' => [
          ['key' => 'service_name', 'label' => __('Name', 'bookpoint-booking')],
          ['key' => 'service_duration', 'label' => __('Duration (minutes)', 'bookpoint-booking')],
        ],
      ],
      [
        'label' => __('Pricing', 'bookpoint-booking'),
        'variables' => [
          ['key' => 'subtotal', 'label' => __('Subtotal', 'bookpoint-booking')],
          ['key' => 'discount', 'label' => __('Discount', 'bookpoint-booking')],
          ['key' => 'tax', 'label' => __('Tax', 'bookpoint-booking')],
          ['key' => 'total', 'label' => __('Total', 'bookpoint-booking')],
          ['key' => 'promo_code', 'label' => __('Promo code', 'bookpoint-booking')],
        ],
      ],
      [
        'label' => __('Links', 'bookpoint-booking'),
        'variables' => [
          ['key' => 'manage_booking_url_customer', 'label' => __('Manage booking (customer)', 'bookpoint-booking')],
          ['key' => 'manage_booking_url_agent', 'label' => __('Manage booking (agent)', 'bookpoint-booking')],
        ],
      ],
    ];

    $custom = self::active_custom_fields();
    if ($custom) {
      $group = [
        'label' => __('Custom fields', 'bookpoint-booking'),
        'variables' => [],
      ];
      foreach ($custom as $field) {
        $slug = $field['field_key'] ?: $field['name_key'] ?: 'field_' . $field['id'];
        $group['variables'][] = [
          'key' => 'field_' . sanitize_key($slug),
          'label' => $field['label'] ?: $slug,
        ];
      }
      $vars[] = $group;
    }

    return $vars;
  }

  private static function active_custom_fields(): array {
    if (!class_exists('POINTLYBOOKING_FormFieldModel')) {
      return [];
    }
    return POINTLYBOOKING_FormFieldModel::active_fields('booking');
  }

  private static function build_pricing(array $booking, array $service): array {
    $service_price = null;
    if (isset($service['price_cents'])) {
      $service_price = ((float)$service['price_cents']) / 100;
    } elseif (isset($service['price'])) {
      $service_price = (float)$service['price'];
    }

    return [
      'subtotal' => $booking['subtotal'] ?? $service_price,
      'discount_total' => $booking['discount_total'] ?? 0,
      'tax_total' => $booking['tax_total'] ?? 0,
      'total' => $booking['total_price'] ?? $service_price,
      'promo_code' => $booking['promo_code'] ?? '',
    ];
  }

  private static function build_links(array $booking): array {
    $id = (int)($booking['id'] ?? 0);
    $manage_key = (string)($booking['manage_key'] ?? '');

    return [
      'manage_booking_url_customer' => $manage_key
        ? add_query_arg(['pointlybooking_manage_booking' => 1, 'key' => $manage_key], home_url('/'))
        : '',
      'manage_booking_url_agent' => $id ? admin_url("admin.php?page=pointlybooking_bookings&view={$id}") : '',
    ];
  }

  private static function collect_custom_fields(array $booking): array {
    $fields = [];
    foreach (['customer_fields_json', 'booking_fields_json', 'custom_fields_json'] as $column) {
      if (empty($booking[$column])) {
        continue;
      }
      $decoded = self::decode_json($booking[$column]);
      if (!is_array($decoded)) {
        continue;
      }
      foreach ($decoded as $key => $value) {
        if ($key === '') {
          continue;
        }
        $fields[$key] = $value;
      }
    }
    return $fields;
  }

  private static function decode_json($value): array {
    if (!$value) {
      return [];
    }
    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? $decoded : [];
  }

  private static function build_ics_attachment(array $payload): ?string {
    $booking = $payload['booking'] ?? [];
    $service = $payload['service'] ?? [];
    $start = $booking['start_datetime'] ?? '';
    $end = $booking['end_datetime'] ?? '';
    $start_ts = $start ? strtotime($start) : false;
    $end_ts = $end ? strtotime($end) : false;
    if (!$start_ts || !$end_ts) {
      return null;
    }

    $uid = uniqid('bp-wf-', true);
    $summary = sanitize_text_field($service['name'] ?? __('Booking', 'bookpoint-booking'));
    $description = sanitize_text_field($payload['context']['booking_status'] ?? '');

    $ics = "BEGIN:VCALENDAR\\r\\n";
    $ics .= "VERSION:2.0\\r\\n";
    $ics .= "PRODID:-//BookPoint//EN\\r\\n";
    $ics .= "METHOD:PUBLISH\\r\\n";
    $ics .= "BEGIN:VEVENT\\r\\n";
    $ics .= "UID:{$uid}\\r\\n";
    $ics .= "DTSTAMP:" . gmdate('Ymd\\THis\\Z') . "\\r\\n";
    $ics .= "DTSTART:" . gmdate('Ymd\\THis\\Z', $start_ts) . "\\r\\n";
    $ics .= "DTEND:" . gmdate('Ymd\\THis\\Z', $end_ts) . "\\r\\n";
    $ics .= "SUMMARY:{$summary}\\r\\n";
    if ($description !== '') {
      $ics .= "DESCRIPTION:{$description}\\r\\n";
    }
    $ics .= "END:VEVENT\\r\\n";
    $ics .= "END:VCALENDAR\\r\\n";

    $tmp = function_exists('wp_tempnam') ? wp_tempnam(sys_get_temp_dir(), 'pointlybooking-wf-') : tempnam(sys_get_temp_dir(), 'pointlybooking-wf-');
    if (!$tmp) {
      return null;
    }

    if (file_put_contents($tmp, $ics) === false) {
      if (function_exists('wp_delete_file')) {
        wp_delete_file($tmp);
      }
      return null;
    }
    return $tmp;
  }

  private static function render_template_value(string $value, array $context): string {
    return pointlybooking_render_template($value, $context);
  }

  private static function log(int $workflow_id, string $event_key, string $entity_type, ?int $entity_id, string $status, string $message = ''): void {
    global $wpdb;
    $table = self::table(self::LOG_TABLE);
    $wpdb->insert($table, [
      'workflow_id' => $workflow_id,
      'event_key' => $event_key,
      'entity_type' => $entity_type,
      'entity_id' => $entity_id,
      'status' => $status,
      'message' => $message,
      'created_at' => current_time('mysql'),
    ], ['%d','%s','%s','%d','%s','%s','%s']);
  }

  public static function table(string $key): string {
    global $wpdb;
    return $wpdb->prefix . $key;
  }
}

add_action(POINTLYBOOKING_Notifications_Helper::CRON_HOOK, function($workflow_id, $payload_json) {
  POINTLYBOOKING_Notifications_Helper::handle_scheduled_event((int)$workflow_id, (string)$payload_json);
}, 10, 2);

function pointlybooking_run_workflows(string $event_key, array $payload): void {
  POINTLYBOOKING_Notifications_Helper::run_workflows($event_key, $payload);
}

function pointlybooking_render_template(string $text, array $context): string {
  if ($text === '') {
    return '';
  }

  return preg_replace_callback('/{{\s*([^}]+)\s*}}/', function($matches) use ($context) {
    $key = trim($matches[1]);
    if ($key === '') {
      return '';
    }
    return isset($context[$key]) ? (string)$context[$key] : '';
  }, $text);
}

