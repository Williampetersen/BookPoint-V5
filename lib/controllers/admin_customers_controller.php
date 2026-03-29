<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_AdminCustomersController extends POINTLYBOOKING_Controller {

  public function index() : void {
    $this->require_cap('pointlybooking_manage_customers');

    $items = POINTLYBOOKING_CustomerModel::all(300);

    $this->render('admin/customers_index', [
      'items' => $items,
    ]);
  }

  public function view() : void {
    $this->require_cap('pointlybooking_manage_customers');

    $id = $this->query_absint('id');
    if ($id <= 0) {
      wp_die(esc_html__('Invalid customer.', 'bookpoint-booking'));
    }

    $customer = POINTLYBOOKING_CustomerModel::find($id);
    if (!$customer) {
      wp_die(esc_html__('Customer not found.', 'bookpoint-booking'));
    }

    $bookings = POINTLYBOOKING_BookingModel::find_by_customer($id);

    $this->render('admin/customers_view', [
      'customer' => $customer,
      'bookings' => $bookings,
    ]);
  }

  public function gdpr_delete() : void {
    $this->require_cap('pointlybooking_manage_customers');
    check_admin_referer('pointlybooking_admin');

    $id = $this->query_absint('id');
    if ($id <= 0) {
      wp_safe_redirect(admin_url('admin.php?page=pointlybooking_customers'));
      exit;
    }

    POINTLYBOOKING_CustomerModel::anonymize($id);
    POINTLYBOOKING_BookingModel::detach_customer($id);

    POINTLYBOOKING_AuditHelper::log('gdpr_customer_anonymized', [
      'actor_type' => 'admin',
      'customer_id' => $id,
      'meta' => ['reason' => 'admin_action'],
    ]);

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_customers&gdpr_deleted=1'));
    exit;
  }
}
