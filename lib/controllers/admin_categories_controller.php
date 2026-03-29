<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_AdminCategoriesController extends POINTLYBOOKING_Controller {

  public function index(): void {
    $this->require_cap('pointlybooking_manage_services');
    $has_filter_nonce = $this->has_valid_admin_filter_nonce();

    $q = $has_filter_nonce ? $this->query_text('q') : '';
    $is_active = $has_filter_nonce ? $this->query_text('is_active') : '';

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

    $id = $this->query_absint('id');
    if ($id > 0) {
      $nonce = $this->query_text('pointlybooking_edit_nonce');
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

    $id = $this->post_absint('id');

    $new_id = POINTLYBOOKING_CategoryModel::save([
      'id' => $id,
      'name' => $this->post_text('name'),
      'description' => wp_kses_post($this->post_raw('description')),
      'image_id' => $this->post_absint('image_id'),
      'sort_order' => $this->post_absint('sort_order'),
      'is_active' => $this->has_post_field('is_active') ? 1 : 0,
    ]);

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_categories&updated=1&edit=' . $new_id));
    exit;
  }

  public function delete(): void {
    $this->require_cap('pointlybooking_manage_services');
    check_admin_referer('pointlybooking_admin');

    $id = $this->query_absint('id');
    if ($id > 0) {
      POINTLYBOOKING_CategoryModel::delete($id);
    }
    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_categories&deleted=1'));
    exit;
  }
}
