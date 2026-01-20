<?php
defined('ABSPATH') || exit;

final class BP_AdminBookingsController extends BP_Controller {

  public function index() : void {
    $this->require_cap('bp_manage_bookings');

    $items = BP_BookingModel::all_with_relations(200);

    $this->render('admin/bookings_index', [
      'items' => $items,
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
