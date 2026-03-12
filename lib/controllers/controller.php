<?php
defined('ABSPATH') || exit;

abstract class POINTLYBOOKING_Controller {

  protected array $params = [];

  public function __construct(array $params = []) {
    $this->params = $params;
  }

  protected function require_cap(string $cap) : void {
    if (!current_user_can($cap)) {
      wp_die(esc_html__('You do not have permission to access this page.', 'bookpoint-booking'));
    }
  }

  protected function has_valid_admin_filter_nonce(string $field = 'pointlybooking_filter_nonce'): bool {
    $nonce = sanitize_text_field(wp_unslash($_GET[$field] ?? ''));
    if ($nonce === '') {
      return false;
    }

    return (bool) wp_verify_nonce($nonce, 'pointlybooking_admin_filter');
  }

  public function render(string $view, array $data = []) : void {
    $view_file = POINTLYBOOKING_VIEWS_PATH . $view . '.php';
    if (!file_exists($view_file)) {
      wp_die('View not found: ' . esc_html($view_file));
    }

    extract($data, EXTR_SKIP);
    include $view_file;
  }
}

