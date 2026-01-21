<?php
defined('ABSPATH') || exit;

final class BP_AdminCustomersController extends BP_Controller {

  public function index() : void {
    $this->require_cap('bp_manage_customers');

    $items = BP_CustomerModel::all(300);

    $this->render('admin/customers_index', [
      'items' => $items,
    ]);
  }

  public function view() : void {
    $this->require_cap('bp_manage_customers');

    $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
    if ($id <= 0) {
      wp_die(__('Invalid customer.', 'bookpoint'));
    }

    $customer = BP_CustomerModel::find($id);
    if (!$customer) {
      wp_die(__('Customer not found.', 'bookpoint'));
    }

    $bookings = BP_BookingModel::find_by_customer($id);

    $this->render('admin/customers_view', [
      'customer' => $customer,
      'bookings' => $bookings,
    ]);
  }

  public function gdpr_delete() : void {
    $this->require_cap('bp_manage_customers');
    check_admin_referer('bp_admin');

    $id = absint($_GET['id'] ?? 0);
    if ($id <= 0) {
      wp_safe_redirect(admin_url('admin.php?page=bp_customers'));
      exit;
    }

    BP_CustomerModel::anonymize($id);
    BP_BookingModel::detach_customer($id);

    BP_AuditHelper::log('gdpr_customer_anonymized', [
      'actor_type' => 'admin',
      'customer_id' => $id,
      'meta' => ['reason' => 'admin_action'],
    ]);

    wp_safe_redirect(admin_url('admin.php?page=bp_customers&gdpr_deleted=1'));
    exit;
  }
}
