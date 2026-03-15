<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_AdminPromoCodesController extends POINTLYBOOKING_Controller {
  private function has_valid_filter_nonce(): bool {
    $nonce = $this->query_text('pointlybooking_filter_nonce');
    if ($nonce === '') return false;
    return (bool) wp_verify_nonce($nonce, 'pointlybooking_admin_filter');
  }

  public function index(): void {
    $this->require_cap('pointlybooking_manage_settings');

    $has_filter_nonce = $this->has_valid_filter_nonce();
    $q = $has_filter_nonce ? $this->query_text('q') : '';
    $is_active = $has_filter_nonce ? $this->query_text('is_active') : '';
    if (!in_array($is_active, ['', '0', '1'], true)) {
      $is_active = '';
    }

    $items = POINTLYBOOKING_PromoCodeModel::all([
      'q' => $q,
      'is_active' => ($is_active === '' ? '' : (int)$is_active),
    ]);

    $this->render('admin/promo_codes_index', compact('items','q','is_active'));
  }

  public function edit(): void {
    $this->require_cap('pointlybooking_manage_settings');

    $id = $this->query_absint('id');
    if ($id > 0) {
      $nonce = $this->query_text('pointlybooking_edit_nonce');
      if (!wp_verify_nonce($nonce, 'pointlybooking_edit_promo_code_' . $id)) {
        wp_die(esc_html__('Security check failed.', 'pointly-booking'));
      }
    }
    $item = $id > 0 ? POINTLYBOOKING_PromoCodeModel::find($id) : null;

    $this->render('admin/promo_codes_edit', compact('item'));
  }

  public function save(): void {
    $this->require_cap('pointlybooking_manage_settings');
    check_admin_referer('pointlybooking_admin');

    $id = $this->post_absint('id');

    $new_id = POINTLYBOOKING_PromoCodeModel::save([
      'id' => $id,
      'code' => $this->post_text('code'),
      'type' => $this->post_key('type') ?: 'percent',
      'amount' => (float) $this->post_raw('amount'),
      'starts_at' => $this->post_text('starts_at'),
      'ends_at' => $this->post_text('ends_at'),
      'max_uses' => $this->post_text('max_uses'),
      'min_total' => $this->post_text('min_total'),
      'is_active' => $this->has_post_field('is_active') ? 1 : 0,
    ]);

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_promo_codes&updated=1&edit=' . $new_id));
    exit;
  }

  public function delete(): void {
    $this->require_cap('pointlybooking_manage_settings');
    check_admin_referer('pointlybooking_admin');

    $id = $this->query_absint('id');
    if ($id > 0) POINTLYBOOKING_PromoCodeModel::delete($id);

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_promo_codes&deleted=1'));
    exit;
  }
}
