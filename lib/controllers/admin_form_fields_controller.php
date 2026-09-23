<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_AdminFormFieldsController extends POINTLYBOOKING_Controller {
  private function has_valid_filter_nonce(): bool {
    $nonce = $this->query_text('pointlybooking_filter_nonce');
    if ($nonce === '') return false;
    return (bool) wp_verify_nonce($nonce, 'pointlybooking_admin_filter');
  }

  private function scope(): string {
    $scope = $this->query_text('scope');
    if ($scope === '') {
      $scope = 'form';
    }
    if (!in_array($scope, ['form','customer','booking'], true)) $scope = 'form';
    return $scope;
  }

  private function has_valid_edit_nonce(int $id): bool {
    if ($id <= 0) return false;
    $nonce = $this->query_text('pointlybooking_edit_nonce');
    if ($nonce === '') return false;
    return (bool) wp_verify_nonce($nonce, 'pointlybooking_edit_form_field_' . $id);
  }

  public function index(): void {
    $this->require_cap('pointlybooking_manage_settings');

    $scope = $this->scope();
    $has_filter_nonce = $this->has_valid_filter_nonce();
    $q = $has_filter_nonce ? $this->query_text('q') : '';
    $is_active = $has_filter_nonce ? $this->query_text('is_active') : '';
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
    $id = $this->query_absint('id');
    if ($id > 0 && !$this->has_valid_edit_nonce($id)) {
      wp_die(esc_html__('Invalid request.', 'pointly-booking'));
    }
    $item = $id > 0 ? POINTLYBOOKING_FormFieldModel::find($id) : null;

    $this->render('admin/form_fields_edit', compact('item','scope'));
  }

  public function save(): void {
    $this->require_cap('pointlybooking_manage_settings');
    check_admin_referer('pointlybooking_admin');

    $scope = $this->post_text('scope');
    if ($scope === '') {
      $scope = 'form';
    }

    $id = $this->post_absint('id');
    $new_id = POINTLYBOOKING_FormFieldModel::save([
      'id' => $id,
      'scope' => $scope,
      'label' => $this->post_text('label'),
      'name_key' => $this->post_key('name_key'),
      'type' => $this->post_key('type') ?: 'text',
      'options_raw' => sanitize_textarea_field($this->post_raw('options_raw')),
      'required' => $this->has_post_field('required') ? 1 : 0,
      'sort_order' => $this->post_absint('sort_order'),
      'is_active' => $this->has_post_field('is_active') ? 1 : 0,
    ]);

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_form_fields&scope='.$scope.'&updated=1&edit='.$new_id));
    exit;
  }

  public function delete(): void {
    $this->require_cap('pointlybooking_manage_settings');
    check_admin_referer('pointlybooking_admin');

    $scope = $this->scope();
    $id = $this->query_absint('id');
    if ($id > 0) POINTLYBOOKING_FormFieldModel::delete($id);

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_form_fields&scope='.$scope.'&deleted=1'));
    exit;
  }
}

