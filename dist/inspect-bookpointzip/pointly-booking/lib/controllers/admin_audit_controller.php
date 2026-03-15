<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_AdminAuditController extends POINTLYBOOKING_Controller {

  public function index() : void {
    $this->require_cap('pointlybooking_manage_settings');
    $has_filter_nonce = $this->has_valid_admin_filter_nonce();

    $filters = [
      'event' => $has_filter_nonce ? sanitize_text_field(wp_unslash($_GET['event'] ?? '')) : '',
      'actor_type' => $has_filter_nonce ? sanitize_text_field(wp_unslash($_GET['actor_type'] ?? '')) : '',
      'booking_id' => $has_filter_nonce ? absint(wp_unslash($_GET['booking_id'] ?? 0)) : 0,
      'customer_id' => $has_filter_nonce ? absint(wp_unslash($_GET['customer_id'] ?? 0)) : 0,
      'date_from' => $has_filter_nonce ? sanitize_text_field(wp_unslash($_GET['date_from'] ?? '')) : '',
      'date_to' => $has_filter_nonce ? sanitize_text_field(wp_unslash($_GET['date_to'] ?? '')) : '',
    ];

    $paged = POINTLYBOOKING_AuditModel::list_paged(array_merge($filters, [
      'page' => $has_filter_nonce ? absint(wp_unslash($_GET['paged'] ?? 1)) : 1,
      'per_page' => $has_filter_nonce ? absint(wp_unslash($_GET['per_page'] ?? 50)) : 50,
    ]));
    $items = $paged['items'] ?? [];
    $events = POINTLYBOOKING_AuditModel::distinct_events();

    $this->render('admin/audit_index', [
      'items' => $items,
      'pagination' => $paged,
      'filters' => $filters,
      'events' => $events,
    ]);
  }
}
