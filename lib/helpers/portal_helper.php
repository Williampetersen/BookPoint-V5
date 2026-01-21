<?php
defined('ABSPATH') || exit;

final class BP_PortalHelper {

  private static function key(string $email, string $suffix) : string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return 'bp_portal_' . md5(strtolower(trim($email)) . '|' . $ip . '|' . $suffix);
  }

  public static function send_otp(string $email) : bool {
    if (!is_email($email)) return false;

    $otp = (string) random_int(100000, 999999);
    set_transient(self::key($email, 'otp'), password_hash($otp, PASSWORD_DEFAULT), 10 * MINUTE_IN_SECONDS);

    $subject = __('Your BookPoint login code', 'bookpoint');
    $body = '<p>' . esc_html__('Your login code is:', 'bookpoint') . ' <strong>' . esc_html($otp) . '</strong></p>'
          . '<p>' . esc_html__('It expires in 10 minutes.', 'bookpoint') . '</p>';

    return BP_EmailHelper::send($email, $subject, $body);
  }

  public static function verify_otp(string $email, string $otp) : ?string {
    $hash = get_transient(self::key($email, 'otp'));
    if (!$hash) return null;

    if (!password_verify($otp, $hash)) return null;

    $session = wp_generate_password(32, false, false);
    set_transient(self::key($email, 'session'), $session, 20 * MINUTE_IN_SECONDS);

    delete_transient(self::key($email, 'otp'));
    return $session;
  }

  public static function is_session_valid(string $email, string $session) : bool {
    $stored = get_transient(self::key($email, 'session'));
    return is_string($stored) && hash_equals($stored, $session);
  }
}
