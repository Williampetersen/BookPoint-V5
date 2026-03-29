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

  protected function request_raw(int $type, string $key): string {
    $source = [];
    if ($type === INPUT_GET) {
      $source = $_GET;
    } elseif ($type === INPUT_POST) {
      $source = $_POST;
    } elseif ($type === INPUT_SERVER) {
      $source = $_SERVER;
    }

    if (!is_array($source) || !array_key_exists($key, $source)) {
      return '';
    }

    $value = $source[$key];
    if (is_array($value) || is_object($value)) {
      return '';
    }

    if (is_string($value)) {
      return wp_unslash($value);
    }

    if (!is_scalar($value)) {
      return '';
    }

    return (string) $value;
  }

  protected function query_text(string $key): string {
    return sanitize_text_field($this->request_raw(INPUT_GET, $key));
  }

  protected function query_key(string $key): string {
    return sanitize_key($this->request_raw(INPUT_GET, $key));
  }

  protected function query_absint(string $key): int {
    return absint($this->request_raw(INPUT_GET, $key));
  }

  protected function post_raw(string $key): string {
    return $this->request_raw(INPUT_POST, $key);
  }

  protected function post_text(string $key): string {
    return sanitize_text_field($this->post_raw($key));
  }

  protected function post_key(string $key): string {
    return sanitize_key($this->post_raw($key));
  }

  protected function post_absint(string $key): int {
    return absint($this->post_raw($key));
  }

  protected function has_post_field(string $key): bool {
    return is_array($_POST) && array_key_exists($key, $_POST);
  }

  protected function post_array(string $key): array {
    if (!is_array($_POST) || !array_key_exists($key, $_POST)) {
      return [];
    }

    $nonce_raw = $_POST['_wpnonce'] ?? '';
    if (is_array($nonce_raw) || is_object($nonce_raw)) {
      return [];
    }
    $nonce = sanitize_text_field(wp_unslash((string) $nonce_raw));
    if ($nonce === '' || !wp_verify_nonce($nonce, 'pointlybooking_admin')) {
      return [];
    }

    $value = wp_unslash($_POST[$key]);
    if (!is_array($value)) {
      return [];
    }

    return $value;
  }

  protected function post_id_list(string $key): array {
    return wp_parse_id_list($this->post_array($key));
  }

  protected function server_text(string $key): string {
    return sanitize_text_field($this->request_raw(INPUT_SERVER, $key));
  }

  protected function has_valid_admin_filter_nonce(string $field = 'pointlybooking_filter_nonce'): bool {
    $nonce = $this->query_text($field);
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
