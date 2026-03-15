<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_AdminAuditController extends POINTLYBOOKING_Controller {
  // phpcs:disable WordPress.Security.NonceVerification.Recommended
  private function sanitize_ymd(string $value): string {
    $value = sanitize_text_field($value);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
      return '';
    }
    [$year, $month, $day] = array_map('intval', explode('-', $value));
    return checkdate($month, $day, $year) ? $value : '';
  }

  public function index() : void {
    $this->require_cap('pointlybooking_manage_settings');
    $has_filter_nonce = $this->has_valid_admin_filter_nonce();
    $events = POINTLYBOOKING_AuditModel::distinct_events();

    $event = $has_filter_nonce ? sanitize_key(wp_unslash($_GET['event'] ?? '')) : '';
    if ($event !== '' && !in_array($event, $events, true)) {
      $event = '';
    }

    $actor_type = $has_filter_nonce ? sanitize_key(wp_unslash($_GET['actor_type'] ?? '')) : '';
    if (!in_array($actor_type, ['', 'admin', 'customer', 'system'], true)) {
      $actor_type = '';
    }

    $date_from = $has_filter_nonce ? $this->sanitize_ymd(sanitize_text_field((string) wp_unslash($_GET['date_from'] ?? ''))) : '';
    $date_to = $has_filter_nonce ? $this->sanitize_ymd(sanitize_text_field((string) wp_unslash($_GET['date_to'] ?? ''))) : '';
    if ($date_from !== '' && $date_to !== '' && strtotime($date_from) > strtotime($date_to)) {
      [$date_from, $date_to] = [$date_to, $date_from];
    }

    $filters = [
      'event' => $event,
      'actor_type' => $actor_type,
      'booking_id' => $has_filter_nonce ? absint(wp_unslash($_GET['booking_id'] ?? 0)) : 0,
      'customer_id' => $has_filter_nonce ? absint(wp_unslash($_GET['customer_id'] ?? 0)) : 0,
      'date_from' => $date_from,
      'date_to' => $date_to,
    ];

    $page = $has_filter_nonce ? max(1, absint(wp_unslash($_GET['paged'] ?? 1))) : 1;
    $per_page = $has_filter_nonce ? max(1, min(200, absint(wp_unslash($_GET['per_page'] ?? 50)))) : 50;

    $paged = POINTLYBOOKING_AuditModel::list_paged(array_merge($filters, [
      'page' => $page,
      'per_page' => $per_page,
    ]));
    $items = $paged['items'] ?? [];

    $this->render('admin/audit_index', [
      'items' => $items,
      'pagination' => $paged,
      'filters' => $filters,
      'events' => $events,
    ]);
  }
  // phpcs:enable WordPress.Security.NonceVerification.Recommended
}
