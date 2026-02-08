<?php
defined('ABSPATH') || exit;

final class BP_LicenseGateHelper {

  public static function is_pro() : bool {
    return defined('BP_IS_PRO') && BP_IS_PRO;
  }

  public static function is_locked() : bool {
    if (!self::is_pro()) return false;
    if (class_exists('BP_TrialHelper') && BP_TrialHelper::is_trial_active()) return false;
    return !BP_LicenseHelper::is_valid();
  }

  public static function maybe_block_rest($result, $server, $request) {
    if (!self::is_locked()) return $result;
    if (!$request instanceof WP_REST_Request) return $result;

    $route = (string) $request->get_route();
    if (strpos($route, '/bp/v1') !== 0) return $result;

    // Allow license management endpoints so the admin can activate the plugin.
    if (strpos($route, '/bp/v1/admin/license') === 0) return $result;

    return new WP_Error(
      'bp_license_required',
      __('BookPoint Pro requires an active license. Go to BookPoint -> Settings -> License.', 'bookpoint'),
      ['status' => 403]
    );
  }

  public static function admin_notice(): void {
    if (!self::is_locked()) return;
    if (!current_user_can('administrator') && !current_user_can('bp_manage_settings')) return;
    $url = admin_url('admin.php?page=bp_settings&tab=license');
    echo '<div class="notice notice-error"><p>';
    echo esc_html__('BookPoint Pro is disabled because the license is not active on this site.', 'bookpoint') . ' ';
    echo '<a href="' . esc_url($url) . '">' . esc_html__('Open License settings', 'bookpoint') . '</a>';
    echo '</p></div>';
  }
}
