<?php
// phpcs:disable WordPress.Security.NonceVerification.Recommended
defined('ABSPATH') || exit;

final class POINTLYBOOKING_AdminCategoriesController extends POINTLYBOOKING_Controller {

  public function index(): void {
    $this->require_cap('pointlybooking_manage_services');
    $has_filter_nonce = $this->has_valid_admin_filter_nonce();

    $q = $has_filter_nonce ? sanitize_text_field(wp_unslash($_GET['q'] ?? '')) : '';
    $is_active = $has_filter_nonce && isset($_GET['is_active']) ? sanitize_text_field(wp_unslash($_GET['is_active'])) : '';

    $items = POINTLYBOOKING_CategoryModel::all([
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
    $this->require_cap('pointlybooking_manage_services');

    $id = absint(wp_unslash($_GET['id'] ?? 0));
    if ($id > 0) {
      $nonce = sanitize_text_field(wp_unslash($_GET['pointlybooking_edit_nonce'] ?? ''));
      if (!wp_verify_nonce($nonce, 'pointlybooking_edit_category_' . $id)) {
        wp_die(esc_html__('Security check failed.', 'bookpoint-booking'));
      }
    }
    $item = $id > 0 ? POINTLYBOOKING_CategoryModel::find($id) : null;

    $this->render('admin/categories_edit', [
      'item' => $item,
    ]);
  }

  public function save(): void {
    $this->require_cap('pointlybooking_manage_services');
    check_admin_referer('pointlybooking_admin');

    $id = absint(wp_unslash($_POST['id'] ?? 0));

    $new_id = POINTLYBOOKING_CategoryModel::save([
      'id' => $id,
      'name' => sanitize_text_field(wp_unslash($_POST['name'] ?? '')),
      'description' => wp_kses_post(wp_unslash($_POST['description'] ?? '')),
      'image_id' => absint(wp_unslash($_POST['image_id'] ?? 0)),
      'sort_order' => absint(wp_unslash($_POST['sort_order'] ?? 0)),
      'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ]);

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_categories&updated=1&edit=' . $new_id));
    exit;
  }

  public function delete(): void {
    $this->require_cap('pointlybooking_manage_services');
    check_admin_referer('pointlybooking_admin');

    $id = absint(wp_unslash($_GET['id'] ?? 0));
    if ($id > 0) {
      POINTLYBOOKING_CategoryModel::delete($id);
    }
    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_categories&deleted=1'));
    exit;
  }
}
