<?php
defined('ABSPATH') || exit;

final class BP_Notifications_Helper {

  private const WORKFLOW_TABLE = 'bp_workflows';
  private const ACTION_TABLE = 'bp_workflow_actions';
  private const LOG_TABLE = 'bp_workflow_logs';
  public const CRON_HOOK = 'bp_run_workflow_event';
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

    $booking = BP_BookingModel::find($booking_id);
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

    $page = max(1, (int)($filters['page'] ?? 1));
    $per = min(50, max(10, (int)($filters['per'] ?? 20)));
    $offset = ($page - 1) * $per;

    $where_sql = implode(' AND ', $where);

    $sql_count = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
    $count_sql = self::prepare_sql($sql_count, $params);
    $total = (int)$wpdb->get_var($count_sql);

    $action_table = self::table(self::ACTION_TABLE);
    $sql_items = "SELECT w.*, (\n      SELECT COUNT(*) FROM {$action_table} a WHERE a.workflow_id = w.id\n    ) as actions_count\n    FROM {$table} w\n    WHERE {$where_sql}\n    ORDER BY w.updated_at DESC, w.id DESC\n    LIMIT %d OFFSET %d";

    $items_params = array_merge($params, [$per, $offset]);
    $items_sql = self::prepare_sql($sql_items, $items_params);
    $rows = $wpdb->get_results($items_sql, ARRAY_A) ?: [];

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
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $workflow_id), ARRAY_A);
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

    $sort_order = (int)$wpdb->get_var($wpdb->prepare(
      "SELECT IFNULL(MAX(sort_order), 0) + 1 FROM {$table} WHERE workflow_id = %d",
      $workflow_id
    ));

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
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $action_id), ARRAY_A);
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
    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM {$table} WHERE event_key = %s AND status = 'active' ORDER BY updated_at DESC",
      $event_key
    ), ARRAY_A);
    return $rows ?: [];
  }

  private static function fetch_actions(int $workflow_id): array {
    global $wpdb;
    $table = self::table(self::ACTION_TABLE);
    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM {$table} WHERE workflow_id = %d ORDER BY sort_order ASC",
      $workflow_id
    ), ARRAY_A);
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
    $subject = self::render_template_value($config['subject'] ?? __('Booking Notification', 'bookpoint'), $context);
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

    $sent = BP_EmailHelper::send($to, $subject, $body, [
      'from_name' => $from_name,
      'from_email' => $from_email,
      'attachments' => $attachments,
    ]);

    foreach ($attachments as $path) {
      @unlink($path);
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
    $service = BP_ServiceModel::find((int)($booking['service_id'] ?? 0)) ?: [];
    $customer = $booking['customer_id'] ? BP_CustomerModel::find((int)$booking['customer_id']) : [];
    $agent = $booking['agent_id'] ? BP_AgentModel::find((int)$booking['agent_id']) : [];

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
    $service_name = $service_name === '' ? __('Service', 'bookpoint') : $service_name;

    $context = [
      'booking_id' => (int)($booking['id'] ?? 0),
      'booking_status' => (string)($booking['status'] ?? ''),
      'booking_notes' => (string)($booking['notes'] ?? ''),
      'start_datetime' => $start,
      'end_datetime' => $end,
      'start_date' => $start_ts ? date('Y-m-d', $start_ts) : '',
      'start_time' => $start_ts ? date('H:i', $start_ts) : '',
      'end_date' => $end_ts ? date('Y-m-d', $end_ts) : '',
      'end_time' => $end_ts ? date('H:i', $end_ts) : '',
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
        'label' => __('Appointment', 'bookpoint'),
        'variables' => [
          ['key' => 'booking_id', 'label' => __('Booking ID', 'bookpoint')],
          ['key' => 'booking_status', 'label' => __('Status', 'bookpoint')],
          ['key' => 'start_date', 'label' => __('Start date', 'bookpoint')],
          ['key' => 'start_time', 'label' => __('Start time', 'bookpoint')],
          ['key' => 'end_date', 'label' => __('End date', 'bookpoint')],
          ['key' => 'end_time', 'label' => __('End time', 'bookpoint')],
          ['key' => 'booking_duration', 'label' => __('Duration (minutes)', 'bookpoint')],
        ],
      ],
      [
        'label' => __('Customer', 'bookpoint'),
        'variables' => [
          ['key' => 'customer_name', 'label' => __('Name', 'bookpoint')],
          ['key' => 'customer_email', 'label' => __('Email', 'bookpoint')],
          ['key' => 'customer_phone', 'label' => __('Phone', 'bookpoint')],
        ],
      ],
      [
        'label' => __('Agent', 'bookpoint'),
        'variables' => [
          ['key' => 'agent_name', 'label' => __('Name', 'bookpoint')],
          ['key' => 'agent_email', 'label' => __('Email', 'bookpoint')],
          ['key' => 'agent_phone', 'label' => __('Phone', 'bookpoint')],
        ],
      ],
      [
        'label' => __('Service', 'bookpoint'),
        'variables' => [
          ['key' => 'service_name', 'label' => __('Name', 'bookpoint')],
          ['key' => 'service_duration', 'label' => __('Duration (minutes)', 'bookpoint')],
        ],
      ],
      [
        'label' => __('Pricing', 'bookpoint'),
        'variables' => [
          ['key' => 'subtotal', 'label' => __('Subtotal', 'bookpoint')],
          ['key' => 'discount', 'label' => __('Discount', 'bookpoint')],
          ['key' => 'tax', 'label' => __('Tax', 'bookpoint')],
          ['key' => 'total', 'label' => __('Total', 'bookpoint')],
          ['key' => 'promo_code', 'label' => __('Promo code', 'bookpoint')],
        ],
      ],
      [
        'label' => __('Links', 'bookpoint'),
        'variables' => [
          ['key' => 'manage_booking_url_customer', 'label' => __('Manage booking (customer)', 'bookpoint')],
          ['key' => 'manage_booking_url_agent', 'label' => __('Manage booking (agent)', 'bookpoint')],
        ],
      ],
    ];

    $custom = self::active_custom_fields();
    if ($custom) {
      $group = [
        'label' => __('Custom fields', 'bookpoint'),
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
    if (!class_exists('BP_FormFieldModel')) {
      return [];
    }
    return BP_FormFieldModel::active_fields('booking');
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
        ? add_query_arg(['bp_manage_booking' => 1, 'key' => $manage_key], home_url('/'))
        : '',
      'manage_booking_url_agent' => $id ? admin_url("admin.php?page=bp_bookings&view={$id}") : '',
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
    $summary = sanitize_text_field($service['name'] ?? __('Booking', 'bookpoint'));
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

    $tmp = function_exists('wp_tempnam') ? wp_tempnam(sys_get_temp_dir(), 'bp-wf-') : tempnam(sys_get_temp_dir(), 'bp-wf-');
    if (!$tmp) {
      return null;
    }

    file_put_contents($tmp, $ics);
    return $tmp;
  }

  private static function render_template_value(string $value, array $context): string {
    return bp_render_template($value, $context);
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

  private static function prepare_sql(string $sql, array $params = []): string {
    global $wpdb;
    if (empty($params)) {
      return $sql;
    }
    return $wpdb->prepare($sql, ...$params);
  }
}

add_action(BP_Notifications_Helper::CRON_HOOK, function($workflow_id, $payload_json) {
  BP_Notifications_Helper::handle_scheduled_event((int)$workflow_id, (string)$payload_json);
}, 10, 2);

function bp_run_workflows(string $event_key, array $payload): void {
  BP_Notifications_Helper::run_workflows($event_key, $payload);
}

function bp_render_template(string $text, array $context): string {
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
