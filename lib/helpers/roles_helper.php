<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_RolesHelper {

  public static function add_capabilities() : void {
    self::ensure_role_caps('administrator', self::all_caps());

    self::ensure_role('pointlybooking_manager', 'BookPoint Manager', self::manager_caps());
    self::ensure_role('pointlybooking_staff', 'BookPoint Staff', self::staff_caps());
  }

  public static function remove_roles() : void {
    remove_role('pointlybooking_manager');
    remove_role('pointlybooking_staff');
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
      'pointlybooking_manage_services',
      'pointlybooking_manage_agents',
      'pointlybooking_manage_bookings',
      'pointlybooking_manage_customers',
      'pointlybooking_manage_settings',
      'pointlybooking_manage_tools',
    ];
  }

  private static function manager_caps() : array {
    return [
      'read' => true,
      'pointlybooking_manage_services' => true,
      'pointlybooking_manage_agents' => true,
      'pointlybooking_manage_bookings' => true,
      'pointlybooking_manage_customers' => true,
      'pointlybooking_manage_settings' => true,
      'pointlybooking_manage_tools' => false,
    ];
  }

  private static function staff_caps() : array {
    return [
      'read' => true,
      'pointlybooking_manage_services' => false,
      'pointlybooking_manage_agents' => false,
      'pointlybooking_manage_bookings' => true,
      'pointlybooking_manage_customers' => true,
      'pointlybooking_manage_settings' => false,
      'pointlybooking_manage_tools' => false,
    ];
  }
}

