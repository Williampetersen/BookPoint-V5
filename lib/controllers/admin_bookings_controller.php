<?php
defined('ABSPATH') || exit;

final class BP_AdminBookingsController extends BP_Controller {

  public function index() : void {
    $this->require_cap('bp_manage_bookings');

    $args = [
      'q' => sanitize_text_field($_GET['q'] ?? ''),
      'status' => sanitize_text_field($_GET['status'] ?? ''),
      'service_id' => absint($_GET['service_id'] ?? 0),
      'agent_id' => absint($_GET['agent_id'] ?? 0),
      'date_from' => sanitize_text_field($_GET['date_from'] ?? ''),
      'date_to' => sanitize_text_field($_GET['date_to'] ?? ''),
    ];

    $paged = BP_BookingModel::admin_list_paged(array_merge($args, [
      'page' => absint($_GET['paged'] ?? 1),
      'per_page' => absint($_GET['per_page'] ?? 50),
    ]));
    $items = $paged['items'] ?? [];
    $services = BP_ServiceModel::all();
    $agents = BP_AgentModel::all(500, true);

    $this->render('admin/bookings_index', [
      'items' => $items,
      'pagination' => $paged,
      'filters' => $args,
      'services' => $services,
      'agents' => $agents,
    ]);
  }

  public function confirm() : void {
    $this->require_cap('bp_manage_bookings');
    check_admin_referer('bp_admin');

    $id = absint($_GET['id'] ?? 0);
    if ($id <= 0) {
      wp_safe_redirect(admin_url('admin.php?page=bp_bookings'));
      exit;
    }

    $booking = BP_BookingModel::find($id);
    if (!$booking) {
      wp_safe_redirect(admin_url('admin.php?page=bp_bookings'));
      exit;
    }

    $old = (string)($booking['status'] ?? 'pending');
    BP_BookingModel::update_status($id, 'confirmed');

    $this->email_status_change($id, $old, 'confirmed');

    BP_AuditHelper::log('booking_status_changed', [
      'actor_type' => 'admin',
      'booking_id' => $id,
      'meta' => ['old' => $old, 'new' => 'confirmed'],
    ]);

    wp_safe_redirect(admin_url('admin.php?page=bp_bookings&updated=1'));
    exit;
  }

  public function cancel() : void {
    $this->require_cap('bp_manage_bookings');
    check_admin_referer('bp_admin');

    $id = absint($_GET['id'] ?? 0);
    if ($id <= 0) {
      wp_safe_redirect(admin_url('admin.php?page=bp_bookings'));
      exit;
    }

    $booking = BP_BookingModel::find($id);
    if (!$booking) {
      wp_safe_redirect(admin_url('admin.php?page=bp_bookings'));
      exit;
    }

    $old = (string)($booking['status'] ?? 'pending');
    BP_BookingModel::update_status($id, 'cancelled');

    $this->email_status_change($id, $old, 'cancelled');

    BP_AuditHelper::log('booking_status_changed', [
      'actor_type' => 'admin',
      'booking_id' => $id,
      'meta' => ['old' => $old, 'new' => 'cancelled'],
    ]);

    wp_safe_redirect(admin_url('admin.php?page=bp_bookings&updated=1'));
    exit;
  }

  public function save_notes() : void {
    $this->require_cap('bp_manage_bookings');
    check_admin_referer('bp_admin');

    $id = absint($_POST['id'] ?? 0);
    $notes = wp_kses_post(wp_unslash($_POST['notes'] ?? ''));

    if ($id > 0) {
      BP_BookingModel::update_notes($id, $notes);
    }

    wp_safe_redirect(admin_url('admin.php?page=bp_bookings&updated=1'));
    exit;
  }

  public function export_csv() : void {
    $this->require_cap('bp_manage_bookings');
    check_admin_referer('bp_admin');

    $args = [
      'q' => sanitize_text_field($_GET['q'] ?? ''),
      'status' => sanitize_text_field($_GET['status'] ?? ''),
      'service_id' => absint($_GET['service_id'] ?? 0),
      'agent_id' => absint($_GET['agent_id'] ?? 0),
      'date_from' => sanitize_text_field($_GET['date_from'] ?? ''),
      'date_to' => sanitize_text_field($_GET['date_to'] ?? ''),
    ];

    $rows = BP_BookingModel::admin_list($args);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="bookings-' . date('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Booking ID','Status','Service','Agent','Customer','Email','Phone','Start','End','Notes']);

    foreach ($rows as $r) {
      fputcsv($out, [
        (int)$r['id'],
        (string)($r['status'] ?? ''),
        (string)($r['service_name'] ?? ''),
        (string)($r['agent_name'] ?? ''),
        (string)($r['customer_name'] ?? ''),
        (string)($r['customer_email'] ?? ''),
        (string)($r['customer_phone'] ?? ''),
        (string)($r['start_datetime'] ?? ''),
        (string)($r['end_datetime'] ?? ''),
        (string)($r['notes'] ?? ''),
      ]);
    }

    fclose($out);
    exit;
  }

  private function email_status_change(int $booking_id, string $old, string $new) : void {
    $b = BP_BookingModel::find($booking_id);
    if (!$b) return;

    $service = BP_ServiceModel::find((int)$b['service_id']);
    $customer = $b['customer_id'] ? BP_CustomerModel::find((int)$b['customer_id']) : null;

    if ($service && $customer) {
      BP_EmailHelper::booking_status_changed_customer($b, $service, $customer, $old, $new);
    }

    BP_WebhookHelper::fire('booking_status_changed', [
      'booking_id' => (int)$b['id'],
      'status' => (string)($b['status'] ?? ''),
      'old_status' => (string)$old,
      'service_id' => (int)($b['service_id'] ?? 0),
      'customer_id' => (int)($b['customer_id'] ?? 0),
      'agent_id' => (int)($b['agent_id'] ?? 0),
      'start_datetime' => (string)($b['start_datetime'] ?? ''),
      'end_datetime' => (string)($b['end_datetime'] ?? ''),
    ]);
  }

  public function change_status() : void {
    $this->require_cap('bp_manage_bookings');
    check_admin_referer('bp_admin');

    $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
    $status = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';

    if ($id > 0 && $status !== '') {
      BP_BookingModel::update_status($id, $status);
    }

    wp_safe_redirect(admin_url('admin.php?page=bp_bookings&updated=1'));
    exit;
  }
}
