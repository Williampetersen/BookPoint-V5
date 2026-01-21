<?php
defined('ABSPATH') || exit;

final class BP_AdminPromoCodesController extends BP_Controller {

  public function index(): void {
    $this->require_cap('bp_manage_settings');

    $q = sanitize_text_field($_GET['q'] ?? '');
    $is_active = isset($_GET['is_active']) ? sanitize_text_field($_GET['is_active']) : '';

    $items = BP_PromoCodeModel::all([
      'q' => $q,
      'is_active' => ($is_active === '' ? '' : (int)$is_active),
    ]);

    $this->render('admin/promo_codes_index', compact('items','q','is_active'));
  }

  public function edit(): void {
    $this->require_cap('bp_manage_settings');

    $id = (int)($_GET['id'] ?? 0);
    $item = $id > 0 ? BP_PromoCodeModel::find($id) : null;

    $this->render('admin/promo_codes_edit', compact('item'));
  }

  public function save(): void {
    $this->require_cap('bp_manage_settings');
    check_admin_referer('bp_admin');

    $id = (int)($_POST['id'] ?? 0);

    $new_id = BP_PromoCodeModel::save([
      'id' => $id,
      'code' => $_POST['code'] ?? '',
      'type' => $_POST['type'] ?? 'percent',
      'amount' => $_POST['amount'] ?? 0,
      'starts_at' => $_POST['starts_at'] ?? '',
      'ends_at' => $_POST['ends_at'] ?? '',
      'max_uses' => $_POST['max_uses'] ?? '',
      'min_total' => $_POST['min_total'] ?? '',
      'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ]);

    wp_safe_redirect(admin_url('admin.php?page=bp_promo_codes&updated=1&edit=' . $new_id));
    exit;
  }

  public function delete(): void {
    $this->require_cap('bp_manage_settings');
    check_admin_referer('bp_admin');

    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) BP_PromoCodeModel::delete($id);

    wp_safe_redirect(admin_url('admin.php?page=bp_promo_codes&deleted=1'));
    exit;
  }
}
