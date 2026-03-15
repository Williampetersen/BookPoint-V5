<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_PublicBookingsController extends POINTLYBOOKING_Controller {
  private static function is_valid_ymd(string $value): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
    [$year, $month, $day] = array_map('intval', explode('-', $value));
    return checkdate($month, $day, $year);
  }

  private static function is_valid_hm(string $value): bool {
    return (bool) preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value);
  }

  private static function parse_manage_datetime(string $value): ?int {
    $value = trim($value);
    if ($value === '') {
      return null;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}T(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $value)) {
      $value = str_replace('T', ' ', $value);
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2} (?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $value)) {
      return null;
    }

    $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));
    if (!$dt) {
      return null;
    }

    $errors = \DateTime::getLastErrors();
    if (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) {
      return null;
    }

    return $dt->getTimestamp();
  }

  private function json_success(array $data = [], string $message = '') : void {
    wp_send_json([
      'status'  => 'success',
      'message' => $message,
      'data'    => $data,
    ]);
  }

  private function json_error(string $code, string $message, array $data = []) : void {
    wp_send_json([
      'status'  => 'error',
      'code'    => $code,
      'message' => $message,
      'data'    => $data,
    ]);
  }

  public function slots() : void {
    if (!check_ajax_referer('pointlybooking_public', '_wpnonce', false)) {
      $this->json_error('pointlybooking_BAD_NONCE', __('Security check failed.', 'bookpoint-booking'));
    }

    $service_id = absint(wp_unslash($_POST['service_id'] ?? 0));
    $date       = sanitize_text_field(wp_unslash($_POST['date'] ?? ''));
    // Step 16: Get agent_id from request
    $agent_id   = absint(wp_unslash($_POST['agent_id'] ?? 0));

    if ($service_id <= 0 || !self::is_valid_ymd($date)) {
      $this->json_error('pointlybooking_INVALID_PARAMS', __('Invalid parameters.', 'bookpoint-booking'));
    }

    // Step 14: Validate date is within booking window
    if (!POINTLYBOOKING_ScheduleHelper::is_date_allowed($date)) {
      $this->json_error('pointlybooking_DATE_OUT_OF_RANGE', __('Booking date is outside allowed range.', 'bookpoint-booking'));
    }

    // Get service first for step 15 (service-based schedule)
    $service = POINTLYBOOKING_ServiceModel::find($service_id);
    if (!$service) {
      $this->json_error('pointlybooking_SERVICE_NOT_FOUND', __('Service not found.', 'bookpoint-booking'));
    }

    // Step 15: Get service-specific or global schedule
    $day_schedule = POINTLYBOOKING_ScheduleHelper::get_service_day_schedule($service, $date);
    if (empty($day_schedule)) {
      $this->json_success([
        'slots' => [],
        'timezone' => wp_timezone_string(),
      ]);
      return;
    }

    $duration = (int)$service['duration_minutes'];

    // Get slot interval
    $interval = (int)POINTLYBOOKING_SettingsHelper::get_with_default('pointlybooking_slot_interval_minutes');

    // Step 14: Get break times
    $breaks = POINTLYBOOKING_ScheduleHelper::get_break_ranges();

    // Step 14: Generate slots with day schedule and breaks
    $slots = POINTLYBOOKING_AvailabilityHelper::generate_slots_for_date(
      $date,
      $interval,
      $day_schedule['open'],
      $day_schedule['close'],
      $breaks
    );

    // Step 15: Remove already booked slots with buffers and capacity
    $capacity = (int)($service['capacity'] ?? 1);
    $buf_before = (int)($service['buffer_before_minutes'] ?? 0);
    $buf_after  = (int)($service['buffer_after_minutes'] ?? 0);

    // Step 16: Pass agent_id to availability check
    $slots = POINTLYBOOKING_AvailabilityHelper::remove_unavailable_slots(
      $service_id,
      $date,
      $slots,
      $duration,
      $capacity,
      $buf_before,
      $buf_after,
      $agent_id
    );

    $this->json_success([
      'slots' => $slots,
      'timezone' => wp_timezone_string(),
    ]);
  }

  public function submit() : void {
    if (!check_ajax_referer('pointlybooking_public', '_wpnonce', false)) {
      $this->json_error('pointlybooking_BAD_NONCE', __('Security check failed.', 'bookpoint-booking'));
    }

    // basic spam honeypot (hidden field)
    $hp = sanitize_text_field(wp_unslash($_POST['pointlybooking_hp'] ?? ''));
    if ($hp !== '') {
      $this->json_error('pointlybooking_SPAM', __('Spam detected.', 'bookpoint-booking'));
    }

    $service_id = absint(wp_unslash($_POST['service_id'] ?? 0));
    $date       = sanitize_text_field(wp_unslash($_POST['date'] ?? ''));
    $time       = sanitize_text_field(wp_unslash($_POST['time'] ?? ''));
    // Step 16: Get agent_id from submission
    $agent_id   = absint(wp_unslash($_POST['agent_id'] ?? 0));

    $first_name = sanitize_text_field(wp_unslash($_POST['first_name'] ?? ''));
    $last_name  = sanitize_text_field(wp_unslash($_POST['last_name'] ?? ''));
    $email      = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $phone      = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
    $notes      = wp_kses_post(wp_unslash($_POST['notes'] ?? ''));

    if ($service_id <= 0) {
      $this->json_error('pointlybooking_INVALID_SERVICE', __('Invalid service.', 'bookpoint-booking'));
    }

    if (!self::is_valid_ymd($date) || !self::is_valid_hm($time)) {
      $this->json_error('pointlybooking_INVALID_DATE_TIME', __('Invalid date or time.', 'bookpoint-booking'));
    }
    if ($email !== '' && !is_email($email)) {
      $this->json_error('pointlybooking_INVALID_EMAIL', __('Invalid email address.', 'bookpoint-booking'));
    }

    // Load service to know duration
    $service = POINTLYBOOKING_ServiceModel::find($service_id);
    if (!$service || (int)$service['is_active'] !== 1) {
      $this->json_error('pointlybooking_SERVICE_NOT_FOUND', __('Service not found.', 'bookpoint-booking'));
    }

    $duration = (int)$service['duration_minutes'];
    if ($duration < 5) $duration = 60;

    // Build start/end datetime in WP timezone as mysql string
    $start_ts = strtotime($date . ' ' . $time);
    if (!$start_ts) {
      $this->json_error('pointlybooking_INVALID_DATE_TIME', __('Invalid date or time.', 'bookpoint-booking'));
    }
    $end_ts = $start_ts + ($duration * 60);

    $start_dt = gmdate('Y-m-d H:i:s', $start_ts);
    $end_dt   = gmdate('Y-m-d H:i:s', $end_ts);

    // Step 15: Overlap protection with buffers and capacity
    $capacity = (int)($service['capacity'] ?? 1);
    $buf_before = (int)($service['buffer_before_minutes'] ?? 0);
    $buf_after  = (int)($service['buffer_after_minutes'] ?? 0);

    $start_dt_adj = gmdate('Y-m-d H:i:s', $start_ts - ($buf_before * 60));
    $end_dt_adj   = gmdate('Y-m-d H:i:s', $end_ts + ($buf_after * 60));

    // Step 16: Pass agent_id to availability check
    if (!POINTLYBOOKING_AvailabilityHelper::is_slot_available($service_id, $start_dt_adj, $end_dt_adj, $capacity, $agent_id)) {
      $this->json_error('pointlybooking_NOT_AVAILABLE', __('This time is no longer available. Please choose another slot.', 'bookpoint-booking'));
    }

    // Create/find customer
    $customer_id = POINTLYBOOKING_CustomerModel::find_or_create_by_email([
      'first_name' => $first_name,
      'last_name'  => $last_name,
      'email'      => $email ?: null,
      'phone'      => $phone ?: null,
      'wp_user_id' => is_user_logged_in() ? get_current_user_id() : null,
    ]);

    // Create booking with agent_id
    $booking_id = POINTLYBOOKING_BookingModel::create([
      'service_id'     => $service_id,
      'customer_id'    => $customer_id,
      'agent_id'       => $agent_id > 0 ? $agent_id : null,
      'start_datetime' => $start_dt,
      'end_datetime'   => $end_dt,
      'status'         => 'pending',
      'notes'          => $notes ?: null,
    ]);

    if ($booking_id <= 0) {
      $this->json_error('pointlybooking_CREATE_FAILED', __('Could not create booking.', 'bookpoint-booking'));
    }

    // Fetch booking for response
    $row = POINTLYBOOKING_BookingModel::find($booking_id);
    $manage_key = $row['manage_key'] ?? '';

    $manage_url = add_query_arg([
      'pointlybooking_manage_booking' => 1,
      'key' => $manage_key,
    ], home_url('/'));

    // Send email notifications if enabled
    $email_enabled = (int)POINTLYBOOKING_SettingsHelper::get_with_default('pointlybooking_email_enabled') === 1;

    if ($email_enabled && $row) {
      $customer_arr = [
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'email'      => $email,
        'phone'      => $phone,
      ];

      POINTLYBOOKING_EmailHelper::booking_created_customer($row, $service, $customer_arr);
      POINTLYBOOKING_EmailHelper::booking_created_admin($row, $service, $customer_arr);
    }

    if ($row) {
      POINTLYBOOKING_WebhookHelper::fire('booking_created', [
        'booking_id' => (int)$row['id'],
        'status' => (string)($row['status'] ?? ''),
        'old_status' => '',
        'service_id' => (int)($row['service_id'] ?? 0),
        'customer_id' => (int)($row['customer_id'] ?? 0),
        'agent_id' => (int)($row['agent_id'] ?? 0),
        'start_datetime' => (string)($row['start_datetime'] ?? ''),
        'end_datetime' => (string)($row['end_datetime'] ?? ''),
      ]);

      POINTLYBOOKING_AuditHelper::log('booking_created', [
        'actor_type' => 'customer',
        'booking_id' => (int)$row['id'],
        'customer_id' => (int)($row['customer_id'] ?? 0),
        'meta' => [
          'service_id' => (int)($row['service_id'] ?? 0),
          'agent_id' => (int)($row['agent_id'] ?? 0),
        ],
      ]);
    }

    $this->json_success([
      'booking_id' => $booking_id,
      'manage_url' => $manage_url,
    ], __('Booking created successfully.', 'bookpoint-booking'));
  }

  public function render_manage_page() : void {
    $key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
    $booking = POINTLYBOOKING_BookingModel::find_by_manage_key($key);

    $service = null;
    if ($booking) {
      $service = POINTLYBOOKING_ServiceModel::find((int)$booking['service_id']);
    }

    $message = '';
    $cancelled = isset($_GET['cancelled']) ? sanitize_text_field(wp_unslash($_GET['cancelled'])) : '';
    if ($cancelled === '1') {
      $message = __('Booking cancelled successfully.', 'bookpoint-booking');
    }

    $cancel_url = '';
    if ($booking) {
      $cancel_url = wp_nonce_url(
        add_query_arg([
          'pointlybooking_manage_booking' => 1,
          'key' => $booking['manage_key'],
          'pointlybooking_action' => 'cancel',
        ], home_url('/')),
        'pointlybooking_manage_booking'
      );
    }

    POINTLYBOOKING_Core_Plugin::enqueue_public_styles_only();

    wp_enqueue_script(
      'pointlybooking-manage',
      POINTLYBOOKING_PLUGIN_URL . 'public/manage-booking.js',
      [],
      POINTLYBOOKING_Core_Plugin::VERSION,
      true
    );
    wp_localize_script('pointlybooking-manage', 'pointlybooking_MANAGE', [
      'restUrl' => esc_url_raw(rest_url('pointly-booking/v1')),
      'i18n' => [
        'missingRestUrl' => __('Missing REST URL.', 'bookpoint-booking'),
        'unsupported' => __('This browser does not support required features.', 'bookpoint-booking'),
        'loadingSlots' => __('Loading available times...', 'bookpoint-booking'),
        'noSlots' => __('No available times for this date.', 'bookpoint-booking'),
        'loadError' => __('Could not load available times. Please try again.', 'bookpoint-booking'),
      ],
    ]);

    // Render view
    $this->render('public/manage_booking', [
      'booking' => $booking,
      'service' => $service,
      'cancel_url' => $cancel_url,
      'message' => $message,
    ]);
  }

  public function handle_manage_actions() : void {
    POINTLYBOOKING_Core_Plugin::rate_limit_or_block('manage_action', 30, 600);

    $request_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))) : '';
    if ($request_method === 'POST' && isset($_POST['pointlybooking_manage_action'])) {
      $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
      if (!wp_verify_nonce($nonce, 'pointlybooking_manage_booking')) {
        wp_die(esc_html__('Security check failed.', 'bookpoint-booking'));
      }

      $key = sanitize_text_field(wp_unslash($_POST['key'] ?? $_POST['pointlybooking_manage_token'] ?? ''));
      $booking = POINTLYBOOKING_BookingModel::find_by_manage_key($key);
      if (!$booking) {
        wp_die(esc_html__('Booking not found.', 'bookpoint-booking'));
      }

      $manage_action = sanitize_key(wp_unslash($_POST['pointlybooking_manage_action']));
      if ($manage_action === 'reschedule') {
        $new_start = sanitize_text_field(wp_unslash($_POST['pointlybooking_new_start'] ?? ''));
        $new_end   = sanitize_text_field(wp_unslash($_POST['pointlybooking_new_end'] ?? ''));

        $start_ts = self::parse_manage_datetime($new_start);
        $end_ts   = self::parse_manage_datetime($new_end);
        if ($start_ts === null || $end_ts === null || $end_ts <= $start_ts) {
          wp_safe_redirect(add_query_arg(['pointlybooking_manage_booking' => 1, 'key' => $key, 'error' => 'bad_time'], home_url('/')));
          exit;
        }

        $new_start = gmdate('Y-m-d H:i:s', $start_ts);
        $new_end = gmdate('Y-m-d H:i:s', $end_ts);

        $service = POINTLYBOOKING_ServiceModel::find((int)$booking['service_id']);
        if (!$service) {
          wp_safe_redirect(add_query_arg(['pointlybooking_manage_booking' => 1, 'key' => $key, 'error' => 'no_service'], home_url('/')));
          exit;
        }

        $capacity   = (int)($service['capacity'] ?? 1);
        $buf_before = (int)($service['buffer_before_minutes'] ?? 0);
        $buf_after  = (int)($service['buffer_after_minutes'] ?? 0);

        $start_adj = gmdate('Y-m-d H:i:s', $start_ts - ($buf_before * 60));
        $end_adj   = gmdate('Y-m-d H:i:s', $end_ts + ($buf_after * 60));

        $service_id = (int)$booking['service_id'];
        $agent_id   = (int)($booking['agent_id'] ?? 0);
        $exclude_id = (int)$booking['id'];

        $ok = POINTLYBOOKING_AvailabilityHelper::is_slot_available_excluding_booking(
          $service_id,
          $start_adj,
          $end_adj,
          $capacity,
          $agent_id,
          $exclude_id
        );

        if (!$ok) {
          wp_safe_redirect(add_query_arg(['pointlybooking_manage_booking' => 1, 'key' => $key, 'error' => 'not_available'], home_url('/')));
          exit;
        }

        POINTLYBOOKING_BookingModel::update_times_public($exclude_id, $new_start, $new_end);

        $new_token = POINTLYBOOKING_BookingModel::rotate_manage_token($exclude_id) ?: $key;
        POINTLYBOOKING_BookingModel::mark_token_used($exclude_id);

        $updated = POINTLYBOOKING_BookingModel::find($exclude_id);
        if ($updated) {
          POINTLYBOOKING_WebhookHelper::fire('booking_updated', [
            'booking_id' => (int)$updated['id'],
            'status' => (string)($updated['status'] ?? ''),
            'old_status' => '',
            'service_id' => (int)($updated['service_id'] ?? 0),
            'customer_id' => (int)($updated['customer_id'] ?? 0),
            'agent_id' => (int)($updated['agent_id'] ?? 0),
            'start_datetime' => (string)($updated['start_datetime'] ?? ''),
            'end_datetime' => (string)($updated['end_datetime'] ?? ''),
          ]);

          POINTLYBOOKING_AuditHelper::log('customer_rescheduled', [
            'actor_type' => 'customer',
            'booking_id' => (int)$updated['id'],
            'customer_id' => (int)($updated['customer_id'] ?? 0),
            'meta' => [
              'old_start' => (string)($booking['start_datetime'] ?? ''),
              'new_start' => (string)($updated['start_datetime'] ?? ''),
            ],
          ]);
        }

        wp_safe_redirect(add_query_arg(['pointlybooking_manage_booking' => 1, 'key' => $new_token, 'updated' => 1], home_url('/')));
        exit;
      }
    }

    $pointlybooking_action = isset($_GET['pointlybooking_action']) ? sanitize_key(wp_unslash($_GET['pointlybooking_action'])) : '';
    if ($pointlybooking_action !== 'cancel') return;

    $key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';

    $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
    if (!wp_verify_nonce($nonce, 'pointlybooking_manage_booking')) {
      wp_die(esc_html__('Security check failed.', 'bookpoint-booking'));
    }

    $booking = POINTLYBOOKING_BookingModel::find_by_manage_key($key);
    if (!$booking) {
      wp_die(esc_html__('Booking not found.', 'bookpoint-booking'));
    }

    POINTLYBOOKING_BookingModel::cancel_by_key($key);

    $new_token = POINTLYBOOKING_BookingModel::rotate_manage_token((int)$booking['id']) ?: $key;
    POINTLYBOOKING_BookingModel::mark_token_used((int)$booking['id']);

    $cancelled = POINTLYBOOKING_BookingModel::find((int)$booking['id']);
    if ($cancelled) {
      POINTLYBOOKING_WebhookHelper::fire('booking_cancelled', [
        'booking_id' => (int)$cancelled['id'],
        'status' => (string)($cancelled['status'] ?? ''),
        'old_status' => '',
        'service_id' => (int)($cancelled['service_id'] ?? 0),
        'customer_id' => (int)($cancelled['customer_id'] ?? 0),
        'agent_id' => (int)($cancelled['agent_id'] ?? 0),
        'start_datetime' => (string)($cancelled['start_datetime'] ?? ''),
        'end_datetime' => (string)($cancelled['end_datetime'] ?? ''),
      ]);

      POINTLYBOOKING_AuditHelper::log('customer_cancelled', [
        'actor_type' => 'customer',
        'booking_id' => (int)$cancelled['id'],
        'customer_id' => (int)($cancelled['customer_id'] ?? 0),
        'meta' => [
          'service_id' => (int)($cancelled['service_id'] ?? 0),
          'agent_id' => (int)($cancelled['agent_id'] ?? 0),
        ],
      ]);
    }

    wp_safe_redirect(add_query_arg([
      'pointlybooking_manage_booking' => 1,
      'key' => $new_token,
      'cancelled' => 1,
    ], home_url('/')));
    exit;
  }
}


