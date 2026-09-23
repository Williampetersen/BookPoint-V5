<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_LicenseGateHelper {

  public static function is_pro() : bool {
    return defined('POINTLYBOOKING_IS_PRO') && POINTLYBOOKING_IS_PRO;
  }

  public static function is_locked() : bool {
    if (!self::is_pro()) return false;
    return !POINTLYBOOKING_LicenseHelper::is_valid();
  }

  public static function maybe_block_rest($result, $server, $request) {
    if (!self::is_locked()) return $result;
    if (!$request instanceof WP_REST_Request) return $result;

    $route = (string) $request->get_route();
    if (strpos($route, '/pointly-booking/v1') !== 0) return $result;

    // Allow license management endpoints so the admin can activate the plugin.
    if (strpos($route, '/pointly-booking/v1/admin/license') === 0) return $result;

    return new WP_Error(
      'pointlybooking_license_required',
      __('BookPoint Pro requires an active license. Go to BookPoint -> Settings -> License.', 'pointly-booking'),
      ['status' => 403]
    );
  }

  public static function admin_notice(): void {
    if (!self::is_locked()) return;
    if (!current_user_can('administrator') && !current_user_can('pointlybooking_manage_settings')) return;
    $url = admin_url('admin.php?page=pointlybooking_settings&tab=license');
    echo '<div class="notice notice-error"><p>';
    echo esc_html__('BookPoint Pro is disabled because the license is not active on this site.', 'pointly-booking') . ' ';
    echo '<a href="' . esc_url($url) . '">' . esc_html__('Open License settings', 'pointly-booking') . '</a>';
    echo '</p></div>';
  }
}
