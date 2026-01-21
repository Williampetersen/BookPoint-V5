<?php
defined('ABSPATH') || exit;

final class BP_AuditHelper {

  public static function log(string $event, array $data = []) : void {
    global $wpdb;

    $table = $wpdb->prefix . 'bp_audit_log';

    $actor_type = $data['actor_type'] ?? (is_user_logged_in() ? 'admin' : 'customer');
    $wp_user_id = is_user_logged_in() ? get_current_user_id() : null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    $wpdb->insert($table, [
      'event' => $event,
      'actor_type' => $actor_type,
      'actor_wp_user_id' => $wp_user_id,
      'actor_ip' => $ip,
      'booking_id' => isset($data['booking_id']) ? (int)$data['booking_id'] : null,
      'customer_id' => isset($data['customer_id']) ? (int)$data['customer_id'] : null,
      'meta' => isset($data['meta']) ? wp_json_encode($data['meta']) : null,
      'created_at' => current_time('mysql'),
    ], ['%s','%s','%d','%s','%d','%d','%s','%s']);
  }
}
