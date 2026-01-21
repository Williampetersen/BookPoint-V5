<?php
defined('ABSPATH') || exit;

final class BP_RolesHelper {

  public static function add_capabilities() : void {
    self::ensure_role_caps('administrator', self::all_caps());

    self::ensure_role('bp_manager', 'BookPoint Manager', self::manager_caps());
    self::ensure_role('bp_staff', 'BookPoint Staff', self::staff_caps());
  }

  public static function remove_roles() : void {
    remove_role('bp_manager');
    remove_role('bp_staff');
  }

  public static function remove_capabilities() : void {
    self::remove_roles();
  }

  private static function ensure_role(string $key, string $label, array $caps) : void {
    $role = get_role($key);
    if (!$role) {
      add_role($key, $label, $caps);
      return;
    }

    foreach ($caps as $cap => $grant) {
      if ($grant) {
        $role->add_cap($cap);
      } else {
        $role->remove_cap($cap);
      }
    }
  }

  private static function ensure_role_caps(string $role_key, array $caps) : void {
    $role = get_role($role_key);
    if (!$role) return;

    foreach ($caps as $cap) {
      $role->add_cap($cap);
    }
  }

  private static function all_caps() : array {
    return [
      'bp_manage_services',
      'bp_manage_agents',
      'bp_manage_bookings',
      'bp_manage_customers',
      'bp_manage_settings',
      'bp_manage_tools',
    ];
  }

  private static function manager_caps() : array {
    return [
      'read' => true,
      'bp_manage_services' => true,
      'bp_manage_agents' => true,
      'bp_manage_bookings' => true,
      'bp_manage_customers' => true,
      'bp_manage_settings' => true,
      'bp_manage_tools' => false,
    ];
  }

  private static function staff_caps() : array {
    return [
      'read' => true,
      'bp_manage_services' => false,
      'bp_manage_agents' => false,
      'bp_manage_bookings' => true,
      'bp_manage_customers' => true,
      'bp_manage_settings' => false,
      'bp_manage_tools' => false,
    ];
  }
}

