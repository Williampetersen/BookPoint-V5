<?php
defined('ABSPATH') || exit;

final class BP_AdminFormFieldsController extends BP_Controller {

  private function scope(): string {
    $scope = sanitize_text_field($_GET['scope'] ?? 'form');
    if (!in_array($scope, ['form','customer','booking'], true)) $scope = 'form';
    return $scope;
  }

  public function index(): void {
    $this->require_cap('bp_manage_settings');

    $scope = $this->scope();
    $q = sanitize_text_field($_GET['q'] ?? '');
    $is_active = isset($_GET['is_active']) ? sanitize_text_field($_GET['is_active']) : '';

    $items = BP_FormFieldModel::all($scope, [
      'q' => $q,
      'is_active' => ($is_active === '' ? '' : (int)$is_active),
    ]);

    $this->render('admin/form_fields_index', compact('items','scope','q','is_active'));
  }

  public function edit(): void {
    $this->require_cap('bp_manage_settings');

    $scope = $this->scope();
    $id = (int)($_GET['id'] ?? 0);
    $item = $id > 0 ? BP_FormFieldModel::find($id) : null;

    $this->render('admin/form_fields_edit', compact('item','scope'));
  }

  public function save(): void {
    $this->require_cap('bp_manage_settings');
    check_admin_referer('bp_admin');

    $scope = sanitize_text_field($_POST['scope'] ?? 'form');

    $id = (int)($_POST['id'] ?? 0);
    $new_id = BP_FormFieldModel::save([
      'id' => $id,
      'scope' => $scope,
      'label' => $_POST['label'] ?? '',
      'name_key' => $_POST['name_key'] ?? '',
      'type' => $_POST['type'] ?? 'text',
      'options_raw' => $_POST['options_raw'] ?? '',
      'required' => isset($_POST['required']) ? 1 : 0,
      'sort_order' => (int)($_POST['sort_order'] ?? 0),
      'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ]);

    wp_safe_redirect(admin_url('admin.php?page=bp_form_fields&scope='.$scope.'&updated=1&edit='.$new_id));
    exit;
  }

  public function delete(): void {
    $this->require_cap('bp_manage_settings');
    check_admin_referer('bp_admin');

    $scope = $this->scope();
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) BP_FormFieldModel::delete($id);

    wp_safe_redirect(admin_url('admin.php?page=bp_form_fields&scope='.$scope.'&deleted=1'));
    exit;
  }
}
