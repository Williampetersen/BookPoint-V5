<?php
defined('ABSPATH') || exit;

final class BP_EmailHelper {

  public static function admin_email() : string {
    return (string) get_option('admin_email');
  }

  public static function send(string $to, string $subject, string $html_body, array $options = []) : bool {
    $to = sanitize_email($to);
    if ($to === '') return false;

    $from_name  = BP_SettingsHelper::get_with_default('bp_email_from_name');
    $from_email = BP_SettingsHelper::get_with_default('bp_email_from_email');

    $from_name  = sanitize_text_field($from_name ?: get_bloginfo('name'));
    $from_email = sanitize_email($from_email ?: get_option('admin_email'));

    if (!empty($options['from_name'])) {
      $from_name = sanitize_text_field($options['from_name']);
    }
    if (!empty($options['from_email'])) {
      $from_email = sanitize_email($options['from_email']);
    }

    $headers = [
      'Content-Type: text/html; charset=UTF-8',
    ];

    if ($from_email) {
      $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
      $headers[] = 'Reply-To: ' . $from_email;
    }

    $subject = wp_strip_all_tags($subject);

    // Safe HTML: allow basic formatting
    $allowed = [
      'a' => ['href' => [], 'target' => [], 'rel' => []],
      'br' => [],
      'p' => [],
      'strong' => [],
      'em' => [],
      'ul' => [],
      'ol' => [],
      'li' => [],
      'span' => [],
    ];
    $body = wp_kses($html_body, $allowed);
    $attachments = [];
    if (!empty($options['attachments']) && is_array($options['attachments'])) {
      $attachments = array_values(array_filter($options['attachments'], 'is_string'));
    }

    return wp_mail($to, $subject, $body, $headers, $attachments);
  }

  public static function booking_created_customer(array $booking, array $service, array $customer) : void {
    if (empty($customer['email'])) return;

    $subject = sprintf(__('Your booking request: %s', 'bookpoint'), (string)($service['name'] ?? ''));
    $body = self::tpl_customer_created($booking, $service, $customer);

    self::send($customer['email'], $subject, $body);
  }

  public static function booking_created_admin(array $booking, array $service, array $customer) : void {
    $to = self::admin_email();
    if ($to === '') return;

    $subject = sprintf(__('New booking: %s', 'bookpoint'), (string)($service['name'] ?? ''));
    $body = self::tpl_admin_created($booking, $service, $customer);

    self::send($to, $subject, $body);
  }

  public static function booking_status_changed_customer(array $booking, array $service, array $customer, string $old, string $new) : void {
    if (empty($customer['email'])) return;

    $subject = sprintf(__('Booking status updated: %s', 'bookpoint'), (string)($service['name'] ?? ''));
    $body = self::tpl_customer_status($booking, $service, $customer, $old, $new);

    self::send($customer['email'], $subject, $body);
  }

  public static function customer_booking_subject() : string {
    return __('Your booking request', 'bookpoint');
  }

  public static function admin_booking_subject() : string {
    return __('New booking received', 'bookpoint');
  }

  public static function customer_template(array $booking, array $service, array $customer, string $manage_url) : string {
    $service_name = esc_html($service['name'] ?? '-');
    $start = esc_html($booking['start_datetime'] ?? '-');
    $end   = esc_html($booking['end_datetime'] ?? '-');

    $name = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
    $name = $name ? esc_html($name) : esc_html__('Customer', 'bookpoint');

    return "
      <p><strong>{$name}</strong>,</p>
      <p>" . esc_html__('Your booking was created successfully.', 'bookpoint') . "</p>
      <p><strong>" . esc_html__('Service:', 'bookpoint') . "</strong> {$service_name}<br>
      <strong>" . esc_html__('Start:', 'bookpoint') . "</strong> {$start}<br>
      <strong>" . esc_html__('End:', 'bookpoint') . "</strong> {$end}</p>
      <p><a href=\"" . esc_url($manage_url) . "\">" . esc_html__('Manage your booking', 'bookpoint') . "</a></p>
    ";
  }

  public static function admin_template(array $booking, array $service, array $customer, string $manage_url) : string {
    $service_name = esc_html($service['name'] ?? '-');
    $start = esc_html($booking['start_datetime'] ?? '-');
    $end   = esc_html($booking['end_datetime'] ?? '-');

    $name = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
    $name = $name ? esc_html($name) : esc_html__('(No name)', 'bookpoint');

    $email = esc_html($customer['email'] ?? '-');
    $phone = esc_html($customer['phone'] ?? '-');

    return "
      <p><strong>" . esc_html__('New booking received', 'bookpoint') . "</strong></p>
      <p><strong>" . esc_html__('Service:', 'bookpoint') . "</strong> {$service_name}<br>
      <strong>" . esc_html__('Start:', 'bookpoint') . "</strong> {$start}<br>
      <strong>" . esc_html__('End:', 'bookpoint') . "</strong> {$end}<br>
      <strong>" . esc_html__('Status:', 'bookpoint') . "</strong> " . esc_html($booking['status'] ?? '-') . "</p>

      <p><strong>" . esc_html__('Customer:', 'bookpoint') . "</strong> {$name}<br>
      <strong>" . esc_html__('Email:', 'bookpoint') . "</strong> {$email}<br>
      <strong>" . esc_html__('Phone:', 'bookpoint') . "</strong> {$phone}</p>

      <p><a href=\"" . esc_url($manage_url) . "\">" . esc_html__('Manage link', 'bookpoint') . "</a></p>
    ";
  }

  private static function tpl_customer_created(array $b, array $s, array $c) : string {
    $start = esc_html($b['start_datetime'] ?? '');
    $name  = esc_html(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')));
    $svc   = esc_html($s['name'] ?? '');

    return "
      <h2>Booking Received</h2>
      <p>Hi {$name},</p>
      <p>We received your booking request for <strong>{$svc}</strong>.</p>
      <p><strong>Date/Time:</strong> {$start}</p>
      <p>Status: <strong>Pending</strong></p>
      <p>We will confirm shortly.</p>
    ";
  }

  private static function tpl_admin_created(array $b, array $s, array $c) : string {
    $start = esc_html($b['start_datetime'] ?? '');
    $svc   = esc_html($s['name'] ?? '');
    $name  = esc_html(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')));
    $email = esc_html($c['email'] ?? '');
    $phone = esc_html($c['phone'] ?? '');

    return "
      <h2>New Booking</h2>
      <p><strong>Service:</strong> {$svc}</p>
      <p><strong>Date/Time:</strong> {$start}</p>
      <p><strong>Customer:</strong> {$name}</p>
      <p><strong>Email:</strong> {$email}</p>
      <p><strong>Phone:</strong> {$phone}</p>
      <p>Status: <strong>Pending</strong></p>
    ";
  }

  private static function tpl_customer_status(array $b, array $s, array $c, string $old, string $new) : string {
    $start = esc_html($b['start_datetime'] ?? '');
    $name  = esc_html(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')));
    $svc   = esc_html($s['name'] ?? '');
    $oldE = esc_html($old);
    $newE = esc_html($new);

    return "
      <h2>Booking Status Updated</h2>
      <p>Hi {$name},</p>
      <p>Your booking for <strong>{$svc}</strong> has been updated.</p>
      <p><strong>Date/Time:</strong> {$start}</p>
      <p><strong>Status:</strong> {$oldE} → <strong>{$newE}</strong></p>
    ";
  }
}
