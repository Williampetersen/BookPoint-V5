<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_AdminExtrasController extends POINTLYBOOKING_Controller {

  public function index(): void {
    $this->require_cap('pointlybooking_manage_services');

    $q = sanitize_text_field(wp_unslash($_GET['q'] ?? ''));
    $service_id = absint(wp_unslash($_GET['service_id'] ?? 0));
    $is_active = isset($_GET['is_active']) ? sanitize_text_field(wp_unslash($_GET['is_active'])) : '';

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

    $id = absint(wp_unslash($_GET['id'] ?? 0));
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

    $id = absint(wp_unslash($_POST['id'] ?? 0));

    $new_id = POINTLYBOOKING_ServiceExtraModel::save([
      'id' => $id,
      'service_id' => 0,
      'name' => sanitize_text_field(wp_unslash($_POST['name'] ?? '')),
      'description' => wp_kses_post(wp_unslash($_POST['description'] ?? '')),
      'price' => floatval(wp_unslash($_POST['price'] ?? 0)),
      'duration_min' => absint(wp_unslash($_POST['duration_min'] ?? 0)),
      'image_id' => absint(wp_unslash($_POST['image_id'] ?? 0)),
      'sort_order' => absint(wp_unslash($_POST['sort_order'] ?? 0)),
      'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ]);

    $service_ids = isset($_POST['service_ids']) ? wp_unslash($_POST['service_ids']) : [];
    if (!is_array($service_ids)) $service_ids = [];
    $service_ids = array_map('absint', $service_ids);
    $first_service = !empty($service_ids) ? (int)$service_ids[0] : 0;

    POINTLYBOOKING_ServiceExtraModel::set_services($new_id, $service_ids);

    global $wpdb;
    $wpdb->update($wpdb->prefix . 'pointlybooking_service_extras', ['service_id' => $first_service], ['id' => $new_id], ['%d'], ['%d']);

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_extras&updated=1&edit=' . $new_id));
    exit;
  }

  public function delete(): void {
    $this->require_cap('pointlybooking_manage_services');
    check_admin_referer('pointlybooking_admin');

    $id = absint(wp_unslash($_GET['id'] ?? 0));
    if ($id > 0) POINTLYBOOKING_ServiceExtraModel::delete($id);

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_extras&deleted=1'));
    exit;
  }
}
