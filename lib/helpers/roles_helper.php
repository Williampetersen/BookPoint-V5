<?php
defined('ABSPATH') || exit;

final class BP_RolesHelper {

  public static function add_capabilities() : void {
    $role = get_role('administrator');
    if (!$role) return;

    $caps = [
      'bp_manage_bookings',
      'bp_manage_services',
      'bp_manage_customers',
      'bp_manage_settings',
    ];

    foreach ($caps as $cap) {
      $role->add_cap($cap);
    }
  }

  public static function remove_capabilities() : void {
    $role = get_role('administrator');
    if (!$role) return;

    $caps = [
      'bp_manage_bookings',
      'bp_manage_services',
      'bp_manage_customers',
      'bp_manage_settings',
    ];

    foreach ($caps as $cap) {
      $role->remove_cap($cap);
    }
  }
}

