<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_AdminAuditController extends POINTLYBOOKING_Controller {

  public function index() : void {
    $this->require_cap('pointlybooking_manage_settings');

    $filters = [
      'event' => sanitize_text_field(wp_unslash($_GET['event'] ?? '')),
      'actor_type' => sanitize_text_field(wp_unslash($_GET['actor_type'] ?? '')),
      'booking_id' => absint(wp_unslash($_GET['booking_id'] ?? 0)),
      'customer_id' => absint(wp_unslash($_GET['customer_id'] ?? 0)),
      'date_from' => sanitize_text_field(wp_unslash($_GET['date_from'] ?? '')),
      'date_to' => sanitize_text_field(wp_unslash($_GET['date_to'] ?? '')),
    ];

    $paged = POINTLYBOOKING_AuditModel::list_paged(array_merge($filters, [
      'page' => absint(wp_unslash($_GET['paged'] ?? 1)),
      'per_page' => absint(wp_unslash($_GET['per_page'] ?? 50)),
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
