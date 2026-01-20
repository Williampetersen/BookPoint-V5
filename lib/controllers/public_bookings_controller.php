<?php
defined('ABSPATH') || exit;

final class BP_PublicBookingsController extends BP_Controller {

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

  private function verify_public_nonce() : bool {
    $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field($_POST['_wpnonce']) : '';
    return wp_verify_nonce($nonce, 'bp_public');
  }

  public function slots() : void {
    if (!$this->verify_public_nonce()) {
      $this->json_error('BP_BAD_NONCE', __('Security check failed.', 'bookpoint'));
    }

    $service_id = absint($_POST['service_id'] ?? 0);
    $date       = sanitize_text_field($_POST['date'] ?? '');
    // Step 16: Get agent_id from request
    $agent_id   = absint($_POST['agent_id'] ?? 0);

    if ($service_id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
      $this->json_error('BP_INVALID_PARAMS', __('Invalid parameters.', 'bookpoint'));
    }

    // Step 14: Validate date is within booking window
    if (!BP_ScheduleHelper::is_date_allowed($date)) {
      $this->json_error('BP_DATE_OUT_OF_RANGE', __('Booking date is outside allowed range.', 'bookpoint'));
    }

    // Get service first for step 15 (service-based schedule)
    $service = BP_ServiceModel::find($service_id);
    if (!$service) {
      $this->json_error('BP_SERVICE_NOT_FOUND', __('Service not found.', 'bookpoint'));
    }

    // Step 15: Get service-specific or global schedule
    $day_schedule = BP_ScheduleHelper::get_service_day_schedule($service, $date);
    if (empty($day_schedule)) {
      $this->json_success([
        'slots' => [],
        'timezone' => wp_timezone_string(),
      ]);
      return;
    }

    $duration = (int)$service['duration_minutes'];

    // Get slot interval
    $interval = (int)BP_SettingsHelper::get_with_default('bp_slot_interval_minutes');

    // Step 14: Get break times
    $breaks = BP_ScheduleHelper::get_break_ranges();

    // Step 14: Generate slots with day schedule and breaks
    $slots = BP_AvailabilityHelper::generate_slots_for_date(
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
    $slots = BP_AvailabilityHelper::remove_unavailable_slots(
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
    if (!$this->verify_public_nonce()) {
      $this->json_error('BP_BAD_NONCE', __('Security check failed.', 'bookpoint'));
    }

    // basic spam honeypot (hidden field)
    $hp = (string)($_POST['BP_hp'] ?? '');
    if ($hp !== '') {
      $this->json_error('BP_SPAM', __('Spam detected.', 'bookpoint'));
    }

    $service_id = absint($_POST['service_id'] ?? 0);
    $date       = sanitize_text_field($_POST['date'] ?? '');
    $time       = sanitize_text_field($_POST['time'] ?? '');
    // Step 16: Get agent_id from submission
    $agent_id   = absint($_POST['agent_id'] ?? 0);

    $first_name = sanitize_text_field($_POST['first_name'] ?? '');
    $last_name  = sanitize_text_field($_POST['last_name'] ?? '');
    $email      = sanitize_email($_POST['email'] ?? '');
    $phone      = sanitize_text_field($_POST['phone'] ?? '');
    $notes      = wp_kses_post($_POST['notes'] ?? '');

    if ($service_id <= 0) {
      $this->json_error('BP_INVALID_SERVICE', __('Invalid service.', 'bookpoint'));
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
      $this->json_error('BP_INVALID_DATE_TIME', __('Invalid date or time.', 'bookpoint'));
    }

    // Load service to know duration
    $service = BP_ServiceModel::find($service_id);
    if (!$service || (int)$service['is_active'] !== 1) {
      $this->json_error('BP_SERVICE_NOT_FOUND', __('Service not found.', 'bookpoint'));
    }

    $duration = (int)$service['duration_minutes'];
    if ($duration < 5) $duration = 60;

    // Build start/end datetime in WP timezone as mysql string
    $start_ts = strtotime($date . ' ' . $time);
    if (!$start_ts) {
      $this->json_error('BP_INVALID_DATE_TIME', __('Invalid date or time.', 'bookpoint'));
    }
    $end_ts = $start_ts + ($duration * 60);

    $start_dt = date('Y-m-d H:i:s', $start_ts);
    $end_dt   = date('Y-m-d H:i:s', $end_ts);

    // Step 15: Overlap protection with buffers and capacity
    $capacity = (int)($service['capacity'] ?? 1);
    $buf_before = (int)($service['buffer_before_minutes'] ?? 0);
    $buf_after  = (int)($service['buffer_after_minutes'] ?? 0);

    $start_dt_adj = date('Y-m-d H:i:s', $start_ts - ($buf_before * 60));
    $end_dt_adj   = date('Y-m-d H:i:s', $end_ts + ($buf_after * 60));

    // Step 16: Pass agent_id to availability check
    if (!BP_AvailabilityHelper::is_slot_available($service_id, $start_dt_adj, $end_dt_adj, $capacity, $agent_id)) {
      $this->json_error('BP_NOT_AVAILABLE', __('This time is no longer available. Please choose another slot.', 'bookpoint'));
    }

    // Create/find customer
    $customer_id = BP_CustomerModel::find_or_create_by_email([
      'first_name' => $first_name,
      'last_name'  => $last_name,
      'email'      => $email ?: null,
      'phone'      => $phone ?: null,
      'wp_user_id' => is_user_logged_in() ? get_current_user_id() : null,
    ]);

    // Create booking with agent_id
    $booking_id = BP_BookingModel::create([
      'service_id'     => $service_id,
      'customer_id'    => $customer_id,
      'agent_id'       => $agent_id > 0 ? $agent_id : null,
      'start_datetime' => $start_dt,
      'end_datetime'   => $end_dt,
      'status'         => 'pending',
      'notes'          => $notes ?: null,
    ]);

    if ($booking_id <= 0) {
      $this->json_error('BP_CREATE_FAILED', __('Could not create booking.', 'bookpoint'));
    }

    // Fetch manage key for response
    $booking = BP_BookingModel::find_by_manage_key(''); // not usable; we need to read inserted row
    // Quick read:
    global $wpdb;
    $table = $wpdb->prefix . 'bp_bookings';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $booking_id), ARRAY_A);
    $manage_key = $row['manage_key'] ?? '';

    $manage_url = add_query_arg([
      'bp_manage_booking' => 1,
      'key' => $manage_key,
    ], home_url('/'));

    // Send email notifications if enabled
    $email_enabled = (int)BP_SettingsHelper::get_with_default('bp_email_enabled') === 1;

    if ($email_enabled && $row) {
      // Build customer array for template
      $customer_arr = [
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'email'      => $email,
        'phone'      => $phone,
      ];

      // Send to customer if email exists
      if ($email) {
        BP_EmailHelper::send(
          $email,
          BP_EmailHelper::customer_booking_subject(),
          BP_EmailHelper::customer_template($row, $service, $customer_arr, $manage_url)
        );
      }

      // Send to admin
      $admin_email = BP_SettingsHelper::get_with_default('bp_admin_email');
      if ($admin_email) {
        BP_EmailHelper::send(
          $admin_email,
          BP_EmailHelper::admin_booking_subject(),
          BP_EmailHelper::admin_template($row, $service, $customer_arr, $manage_url)
        );
      }
    }

    $this->json_success([
      'booking_id' => $booking_id,
      'manage_url' => $manage_url,
    ], __('Booking created successfully.', 'bookpoint'));
  }

  public function render_manage_page() : void {
    $key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
    $booking = BP_BookingModel::find_by_manage_key($key);

    $service = null;
    if ($booking) {
      $service = BP_ServiceModel::find((int)$booking['service_id']);
    }

    $message = '';
    if (isset($_GET['cancelled']) && $_GET['cancelled'] == '1') {
      $message = __('✅ Booking cancelled successfully.', 'bookpoint');
    }

    $cancel_url = '';
    if ($booking) {
      $cancel_url = wp_nonce_url(
        add_query_arg([
          'BP_manage_booking' => 1,
          'key' => $booking['manage_key'],
          'BP_action' => 'cancel',
        ], home_url('/')),
        'BP_manage_booking'
      );
    }

    // Render view
    $this->render('public/manage_booking', [
      'booking' => $booking,
      'service' => $service,
      'cancel_url' => $cancel_url,
      'message' => $message,
    ]);
  }

  public function handle_manage_actions() : void {
    if (!isset($_GET['BP_action']) || $_GET['BP_action'] !== 'cancel') return;

    $key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';

    if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'BP_manage_booking')) {
      wp_die(esc_html__('Security check failed.', 'bookpoint'));
    }

    $booking = BP_BookingModel::find_by_manage_key($key);
    if (!$booking) {
      wp_die(esc_html__('Booking not found.', 'bookpoint'));
    }

    BP_BookingModel::cancel_by_key($key);

    wp_safe_redirect(add_query_arg([
      'BP_manage_booking' => 1,
      'key' => $key,
      'cancelled' => 1,
    ], home_url('/')));
    exit;
  }
}

