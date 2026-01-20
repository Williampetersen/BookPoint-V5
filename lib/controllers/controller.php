<?php
defined('ABSPATH') || exit;

abstract class BP_Controller {

  protected array $params = [];

  public function __construct(array $params = []) {
    $this->params = $params;
  }

  protected function require_cap(string $cap) : void {
    if (!current_user_can($cap)) {
      wp_die(esc_html__('You do not have permission to access this page.', 'bookpoint'));
    }
  }

  public function render(string $view, array $data = []) : void {
    $view_file = BP_VIEWS_PATH . $view . '.php';
    if (!file_exists($view_file)) {
      wp_die('View not found: ' . esc_html($view_file));
    }

    extract($data, EXTR_SKIP);
    include $view_file;
  }
}

