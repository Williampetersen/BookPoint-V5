<?php
defined('ABSPATH') || exit;

final class BP_AdminCategoriesController extends BP_Controller {

  public function index(): void {
    $this->require_cap('bp_manage_services');

    $q = sanitize_text_field($_GET['q'] ?? '');
    $is_active = isset($_GET['is_active']) ? sanitize_text_field($_GET['is_active']) : '';

    $items = BP_CategoryModel::all([
      'q' => $q,
      'is_active' => ($is_active === '' ? '' : (int)$is_active),
    ]);

    $this->render('admin/categories_index', [
      'items' => $items,
      'q' => $q,
      'is_active' => $is_active,
    ]);
  }

  public function edit(): void {
    $this->require_cap('bp_manage_services');

    $id = (int)($_GET['id'] ?? 0);
    $item = $id > 0 ? BP_CategoryModel::find($id) : null;

    $this->render('admin/categories_edit', [
      'item' => $item,
    ]);
  }

  public function save(): void {
    $this->require_cap('bp_manage_services');
    check_admin_referer('bp_admin');

    $id = (int)($_POST['id'] ?? 0);

    $new_id = BP_CategoryModel::save([
      'id' => $id,
      'name' => $_POST['name'] ?? '',
      'description' => $_POST['description'] ?? '',
      'image_id' => (int)($_POST['image_id'] ?? 0),
      'sort_order' => (int)($_POST['sort_order'] ?? 0),
      'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ]);

    wp_safe_redirect(admin_url('admin.php?page=bp_categories&updated=1&edit=' . $new_id));
    exit;
  }

  public function delete(): void {
    $this->require_cap('bp_manage_services');
    check_admin_referer('bp_admin');

    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
      BP_CategoryModel::delete($id);
    }
    wp_safe_redirect(admin_url('admin.php?page=bp_categories&deleted=1'));
    exit;
  }
}
