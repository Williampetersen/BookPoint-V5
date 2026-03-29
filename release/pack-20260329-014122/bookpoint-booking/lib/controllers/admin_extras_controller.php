<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_AdminExtrasController extends POINTLYBOOKING_Controller {

  public function index(): void {
    $this->require_cap('pointlybooking_manage_services');
    $has_filter_nonce = $this->has_valid_admin_filter_nonce();

    $q = $has_filter_nonce ? $this->query_text('q') : '';
    $service_id = $has_filter_nonce ? $this->query_absint('service_id') : 0;
    $is_active = $has_filter_nonce ? $this->query_text('is_active') : '';

    $services = POINTLYBOOKING_ServiceModel::all(['is_active' => 1]);

    $items = POINTLYBOOKING_ServiceExtraModel::all([
      'q' => $q,
      'service_id' => $service_id,
      'is_active' => ($is_active === '' ? '' : (int)$is_active),
    ]);

    $this->render('admin/extras_index', [
      'items' => $items,
      'services' => $services,
      'q' => $q,
      'service_id' => $service_id,
      'is_active' => $is_active,
    ]);
  }

  public function edit(): void {
    $this->require_cap('pointlybooking_manage_services');

    $id = $this->query_absint('id');
    if ($id > 0) {
      $nonce = $this->query_text('pointlybooking_edit_nonce');
      if (!wp_verify_nonce($nonce, 'pointlybooking_edit_extra_' . $id)) {
        wp_die(esc_html__('Security check failed.', 'bookpoint-booking'));
      }
    }
    $item = $id > 0 ? POINTLYBOOKING_ServiceExtraModel::find($id) : null;

    $services = POINTLYBOOKING_ServiceModel::all(['is_active' => 1]);

    $this->render('admin/extras_edit', [
      'item' => $item,
      'services' => $services,
    ]);
  }

  public function save(): void {
    $this->require_cap('pointlybooking_manage_services');
    check_admin_referer('pointlybooking_admin');

    $id = $this->post_absint('id');

    $new_id = POINTLYBOOKING_ServiceExtraModel::save([
      'id' => $id,
      'service_id' => 0,
      'name' => $this->post_text('name'),
      'description' => wp_kses_post($this->post_raw('description')),
      'price' => (float) $this->post_raw('price'),
      'duration_min' => $this->post_absint('duration_min'),
      'image_id' => $this->post_absint('image_id'),
      'sort_order' => $this->post_absint('sort_order'),
      'is_active' => $this->has_post_field('is_active') ? 1 : 0,
    ]);

    $service_ids = $this->post_id_list('service_ids');
    $first_service = !empty($service_ids) ? (int)$service_ids[0] : 0;

    POINTLYBOOKING_ServiceExtraModel::set_services($new_id, $service_ids);

    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $wpdb->update($wpdb->prefix . 'pointlybooking_service_extras', ['service_id' => $first_service], ['id' => $new_id], ['%d'], ['%d']);

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_extras&updated=1&edit=' . $new_id));
    exit;
  }

  public function delete(): void {
    $this->require_cap('pointlybooking_manage_services');
    check_admin_referer('pointlybooking_admin');

    $id = $this->query_absint('id');
    if ($id > 0) POINTLYBOOKING_ServiceExtraModel::delete($id);

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_extras&deleted=1'));
    exit;
  }
}
