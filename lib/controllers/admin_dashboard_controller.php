<?php
defined('ABSPATH') || exit;

final class BP_AdminDashboardController extends BP_Controller {

  public function index() : void {
    // Any admin can see dashboard if they have at least one BPV5 cap
    $allowed = current_user_can('BP_manage_bookings')
      || current_user_can('BP_manage_services')
      || current_user_can('BP_manage_customers')
      || current_user_can('BP_manage_settings')
      || current_user_can('manage_options');

    if (!$allowed) {
      wp_die(esc_html__('You do not have permission to access BookPoint V5.', 'bookpoint'));
    }

    $this->render('admin/dashboard', [
      'title' => 'BookPoint V5 Dashboard',
    ]);
  }
}

