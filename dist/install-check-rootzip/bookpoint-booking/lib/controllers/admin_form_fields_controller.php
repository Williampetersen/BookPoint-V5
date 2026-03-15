<?php
// phpcs:disable WordPress.Security.NonceVerification.Recommended
defined('ABSPATH') || exit;

final class POINTLYBOOKING_AdminFormFieldsController extends POINTLYBOOKING_Controller {
  private function has_valid_filter_nonce(): bool {
    $nonce = sanitize_text_field(wp_unslash($_GET['pointlybooking_filter_nonce'] ?? ''));
    if ($nonce === '') return false;
    return (bool) wp_verify_nonce($nonce, 'pointlybooking_admin_filter');
  }

  private function scope(): string {
    $scope = sanitize_text_field(wp_unslash($_GET['scope'] ?? 'form'));
    if (!in_array($scope, ['form','customer','booking'], true)) $scope = 'form';
    return $scope;
  }

  private function has_valid_edit_nonce(int $id): bool {
    if ($id <= 0) return false;
    $nonce = sanitize_text_field(wp_unslash($_GET['pointlybooking_edit_nonce'] ?? ''));
    if ($nonce === '') return false;
    return (bool) wp_verify_nonce($nonce, 'pointlybooking_edit_form_field_' . $id);
  }

  public function index(): void {
    $this->require_cap('pointlybooking_manage_settings');

    $scope = $this->scope();
    $has_filter_nonce = $this->has_valid_filter_nonce();
    $q = $has_filter_nonce ? sanitize_text_field(wp_unslash($_GET['q'] ?? '')) : '';
    $is_active = $has_filter_nonce && isset($_GET['is_active']) ? sanitize_text_field(wp_unslash($_GET['is_active'])) : '';
    if (!in_array($is_active, ['', '0', '1'], true)) {
      $is_active = '';
    }

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
    if ($id > 0 && !$this->has_valid_edit_nonce($id)) {
      wp_die(esc_html__('Invalid request.', 'bookpoint-booking'));
    }
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

