<?php
defined('ABSPATH') || exit;

final class BP_EmailHelper {

  public static function send(string $to, string $subject, string $html_body) : bool {
    $to = sanitize_email($to);
    if ($to === '') return false;

    $from_name  = BP_SettingsHelper::get_with_default('bp_email_from_name');
    $from_email = BP_SettingsHelper::get_with_default('bp_email_from_email');

    $from_name  = sanitize_text_field($from_name ?: get_bloginfo('name'));
    $from_email = sanitize_email($from_email ?: get_option('admin_email'));

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

    return wp_mail($to, $subject, $body, $headers);
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
}
