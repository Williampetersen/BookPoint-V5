<?php
defined('ABSPATH') || exit;

final class BP_AdminExtrasController extends BP_Controller {

  public function index(): void {
    $this->require_cap('bp_manage_services');

    $q = sanitize_text_field($_GET['q'] ?? '');
    $service_id = (int)($_GET['service_id'] ?? 0);
    $is_active = isset($_GET['is_active']) ? sanitize_text_field($_GET['is_active']) : '';

    $services = BP_ServiceModel::all(['is_active' => 1]);

    $items = BP_ServiceExtraModel::all([
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
    $this->require_cap('bp_manage_services');

    $id = (int)($_GET['id'] ?? 0);
    $item = $id > 0 ? BP_ServiceExtraModel::find($id) : null;

    $services = BP_ServiceModel::all(['is_active' => 1]);

    $this->render('admin/extras_edit', [
      'item' => $item,
      'services' => $services,
    ]);
  }

  public function save(): void {
    $this->require_cap('bp_manage_services');
    check_admin_referer('bp_admin');

    $id = (int)($_POST['id'] ?? 0);

    $new_id = BP_ServiceExtraModel::save([
      'id' => $id,
      'service_id' => 0,
      'name' => $_POST['name'] ?? '',
      'description' => $_POST['description'] ?? '',
      'price' => $_POST['price'] ?? 0,
      'duration_min' => $_POST['duration_min'] ?? '',
      'image_id' => (int)($_POST['image_id'] ?? 0),
      'sort_order' => (int)($_POST['sort_order'] ?? 0),
      'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ]);

    $service_ids = $_POST['service_ids'] ?? [];
    if (!is_array($service_ids)) $service_ids = [];
    $first_service = !empty($service_ids) ? (int)$service_ids[0] : 0;

    BP_ServiceExtraModel::set_services($new_id, $service_ids);

    global $wpdb;
    $wpdb->update($wpdb->prefix . 'bp_service_extras', ['service_id' => $first_service], ['id' => $new_id], ['%d'], ['%d']);

    wp_safe_redirect(admin_url('admin.php?page=bp_extras&updated=1&edit=' . $new_id));
    exit;
  }

  public function delete(): void {
    $this->require_cap('bp_manage_services');
    check_admin_referer('bp_admin');

    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) BP_ServiceExtraModel::delete($id);

    wp_safe_redirect(admin_url('admin.php?page=bp_extras&deleted=1'));
    exit;
  }
}
