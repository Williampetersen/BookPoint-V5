<?php
defined('ABSPATH') || exit;

final class BP_AdminAuditController extends BP_Controller {

  public function index() : void {
    $this->require_cap('bp_manage_settings');

    $filters = [
      'event' => sanitize_text_field($_GET['event'] ?? ''),
      'actor_type' => sanitize_text_field($_GET['actor_type'] ?? ''),
      'booking_id' => absint($_GET['booking_id'] ?? 0),
      'customer_id' => absint($_GET['customer_id'] ?? 0),
      'date_from' => sanitize_text_field($_GET['date_from'] ?? ''),
      'date_to' => sanitize_text_field($_GET['date_to'] ?? ''),
    ];

    $paged = BP_AuditModel::list_paged(array_merge($filters, [
      'page' => absint($_GET['paged'] ?? 1),
      'per_page' => absint($_GET['per_page'] ?? 50),
    ]));
    $items = $paged['items'] ?? [];
    $events = BP_AuditModel::distinct_events();

    $this->render('admin/audit_index', [
      'items' => $items,
      'pagination' => $paged,
      'filters' => $filters,
      'events' => $events,
    ]);
  }
}
