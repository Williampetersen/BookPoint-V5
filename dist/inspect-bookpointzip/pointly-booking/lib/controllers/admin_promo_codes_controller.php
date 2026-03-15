<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_AdminPromoCodesController extends POINTLYBOOKING_Controller {
  private function has_valid_filter_nonce(): bool {
    $nonce = sanitize_text_field(wp_unslash($_GET['pointlybooking_filter_nonce'] ?? ''));
    if ($nonce === '') return false;
    return (bool) wp_verify_nonce($nonce, 'pointlybooking_admin_filter');
  }

  public function index(): void {
    $this->require_cap('pointlybooking_manage_settings');

    $has_filter_nonce = $this->has_valid_filter_nonce();
    $q = $has_filter_nonce ? sanitize_text_field(wp_unslash($_GET['q'] ?? '')) : '';
    $is_active = $has_filter_nonce && isset($_GET['is_active']) ? sanitize_text_field(wp_unslash($_GET['is_active'])) : '';

    $items = POINTLYBOOKING_PromoCodeModel::all([
      'q' => $q,
      'is_active' => ($is_active === '' ? '' : (int)$is_active),
    ]);

    $this->render('admin/promo_codes_index', compact('items','q','is_active'));
  }

  public function edit(): void {
    $this->require_cap('pointlybooking_manage_settings');

    $id = absint(wp_unslash($_GET['id'] ?? 0));
    $item = $id > 0 ? POINTLYBOOKING_PromoCodeModel::find($id) : null;

    $this->render('admin/promo_codes_edit', compact('item'));
  }

  public function save(): void {
    $this->require_cap('pointlybooking_manage_settings');
    check_admin_referer('pointlybooking_admin');

    $id = absint(wp_unslash($_POST['id'] ?? 0));

    $new_id = POINTLYBOOKING_PromoCodeModel::save([
      'id' => $id,
      'code' => sanitize_text_field(wp_unslash($_POST['code'] ?? '')),
      'type' => sanitize_key(wp_unslash($_POST['type'] ?? 'percent')),
      'amount' => floatval(wp_unslash($_POST['amount'] ?? 0)),
      'starts_at' => sanitize_text_field(wp_unslash($_POST['starts_at'] ?? '')),
      'ends_at' => sanitize_text_field(wp_unslash($_POST['ends_at'] ?? '')),
      'max_uses' => sanitize_text_field(wp_unslash($_POST['max_uses'] ?? '')),
      'min_total' => sanitize_text_field(wp_unslash($_POST['min_total'] ?? '')),
      'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ]);

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_promo_codes&updated=1&edit=' . $new_id));
    exit;
  }

  public function delete(): void {
    $this->require_cap('pointlybooking_manage_settings');
    check_admin_referer('pointlybooking_admin');

    $id = absint(wp_unslash($_GET['id'] ?? 0));
    if ($id > 0) POINTLYBOOKING_PromoCodeModel::delete($id);

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_promo_codes&deleted=1'));
    exit;
  }
}
