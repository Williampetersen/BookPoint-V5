<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_AdminFormFieldsController extends POINTLYBOOKING_Controller {

  private function scope(): string {
    $scope = sanitize_text_field(wp_unslash($_GET['scope'] ?? 'form'));
    if (!in_array($scope, ['form','customer','booking'], true)) $scope = 'form';
    return $scope;
  }

  public function index(): void {
    $this->require_cap('pointlybooking_manage_settings');

    $scope = $this->scope();
    $q = sanitize_text_field(wp_unslash($_GET['q'] ?? ''));
    $is_active = isset($_GET['is_active']) ? sanitize_text_field(wp_unslash($_GET['is_active'])) : '';

    $items = POINTLYBOOKING_FormFieldModel::all($scope, [
      'q' => $q,
      'is_active' => ($is_active === '' ? '' : (int)$is_active),
    ]);

    $this->render('admin/form_fields_index', compact('items','scope','q','is_active'));
  }

  public function edit(): void {
    $this->require_cap('pointlybooking_manage_settings');

    $scope = $this->scope();
    $id = absint(wp_unslash($_GET['id'] ?? 0));
    $item = $id > 0 ? POINTLYBOOKING_FormFieldModel::find($id) : null;

    $this->render('admin/form_fields_edit', compact('item','scope'));
  }

  public function save(): void {
    $this->require_cap('pointlybooking_manage_settings');
    check_admin_referer('pointlybooking_admin');

    $scope = sanitize_text_field(wp_unslash($_POST['scope'] ?? 'form'));

    $id = absint(wp_unslash($_POST['id'] ?? 0));
    $new_id = POINTLYBOOKING_FormFieldModel::save([
      'id' => $id,
      'scope' => $scope,
      'label' => sanitize_text_field(wp_unslash($_POST['label'] ?? '')),
      'name_key' => sanitize_key(wp_unslash($_POST['name_key'] ?? '')),
      'type' => sanitize_key(wp_unslash($_POST['type'] ?? 'text')),
      'options_raw' => sanitize_textarea_field(wp_unslash($_POST['options_raw'] ?? '')),
      'required' => isset($_POST['required']) ? 1 : 0,
      'sort_order' => absint(wp_unslash($_POST['sort_order'] ?? 0)),
      'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ]);

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_form_fields&scope='.$scope.'&updated=1&edit='.$new_id));
    exit;
  }

  public function delete(): void {
    $this->require_cap('pointlybooking_manage_settings');
    check_admin_referer('pointlybooking_admin');

    $scope = $this->scope();
    $id = absint(wp_unslash($_GET['id'] ?? 0));
    if ($id > 0) POINTLYBOOKING_FormFieldModel::delete($id);

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_form_fields&scope='.$scope.'&deleted=1'));
    exit;
  }
}
