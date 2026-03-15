<?php
defined('ABSPATH') || exit;

if (!function_exists('pointlybooking_table')) {
  /**
   * Builds a safe plugin table name from a hardcoded suffix.
   */
  function pointlybooking_table(string $suffix): string {
    global $wpdb;

    $suffix = strtolower(preg_replace('/[^a-z0-9_]/', '', $suffix));
    $table = $wpdb->prefix . 'pointlybooking_' . $suffix;
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
      return esc_sql($wpdb->prefix . 'pointlybooking_invalid');
    }

    return esc_sql($table);
  }
}

if (!function_exists('pointlybooking_user_can_manage')) {
  function pointlybooking_user_can_manage(): bool {
    return current_user_can('manage_options')
      || current_user_can('pointlybooking_manage_settings')
      || current_user_can('pointlybooking_manage_tools');
  }
}

if (!function_exists('pointlybooking_verify_admin_nonce')) {
  function pointlybooking_verify_admin_nonce(string $action, string $field = '_wpnonce'): bool {
    $nonce = filter_input(INPUT_POST, $field, FILTER_UNSAFE_RAW);
    if (!is_string($nonce) || $nonce === '') {
      $nonce = filter_input(INPUT_GET, $field, FILTER_UNSAFE_RAW);
    }
    $nonce = sanitize_text_field(is_string($nonce) ? $nonce : '');
    if ($nonce === '') {
      return false;
    }
    return (bool) wp_verify_nonce($nonce, $action);
  }
}

if (!function_exists('pointlybooking_get_uploaded_file_contents')) {
  /**
   * Reads uploaded file content through WP_Filesystem after strict validation.
   */
  function pointlybooking_get_uploaded_file_contents(string $field, array $allowed_ext = ['csv'], int $max_size = 5242880): ?string {
    // Calling code must verify nonce + capability before invoking this helper (e.g. admin-post handlers).
    // phpcs:disable WordPress.Security.NonceVerification.Missing
    if (empty($_FILES[$field]) || !is_array($_FILES[$field])) {
      return null;
    }

    $tmp_name = isset($_FILES[$field]['tmp_name']) ? sanitize_text_field((string) wp_unslash($_FILES[$field]['tmp_name'])) : '';
    $name = isset($_FILES[$field]['name']) ? sanitize_file_name((string) wp_unslash($_FILES[$field]['name'])) : '';
    $size = isset($_FILES[$field]['size']) ? (int) wp_unslash($_FILES[$field]['size']) : 0;
    $error = isset($_FILES[$field]['error']) ? (int) wp_unslash($_FILES[$field]['error']) : UPLOAD_ERR_NO_FILE;

    if ($error !== UPLOAD_ERR_OK || $tmp_name === '' || $name === '') {
      return null;
    }
    if ($size <= 0 || $size > $max_size) {
      return null;
    }
    if (!is_uploaded_file($tmp_name)) {
      return null;
    }

    $mimes = [];
    foreach ($allowed_ext as $ext) {
      $clean_ext = strtolower(preg_replace('/[^a-z0-9]/', '', (string) $ext));
      if ($clean_ext !== '') {
        $mimes[$clean_ext] = $clean_ext;
      }
    }
    if (empty($mimes)) {
      return null;
    }

    $check = wp_check_filetype_and_ext($tmp_name, $name, $mimes);
    $file_ext = strtolower((string) ($check['ext'] ?? ''));
    if ($file_ext === '' || !in_array($file_ext, array_keys($mimes), true)) {
      return null;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    global $wp_filesystem;
    if (!WP_Filesystem()) {
      return null;
    }
    if (!is_object($wp_filesystem) || !method_exists($wp_filesystem, 'get_contents')) {
      return null;
    }

    $contents = $wp_filesystem->get_contents($tmp_name);
    if (!is_string($contents) || $contents === '') {
      return null;
    }

    // phpcs:enable WordPress.Security.NonceVerification.Missing
    return $contents;
  }
}

if (!function_exists('pointlybooking_build_csv')) {
  /**
   * Builds CSV text without raw fopen/fclose calls.
   */
  function pointlybooking_build_csv(array $header, array $rows): string {
    $csv = new SplTempFileObject();
    $csv->fputcsv($header);
    foreach ($rows as $row) {
      $csv->fputcsv($row);
    }

    $csv->rewind();
    $output = '';
    while (!$csv->eof()) {
      $line = $csv->fgets();
      if ($line === false) {
        break;
      }
      $output .= $line;
    }

    return $output;
  }
}

if (!function_exists('pointlybooking_db_table_exists')) {
  function pointlybooking_db_table_exists(string $table): bool {
    global $wpdb;

    if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
      return false;
    }

    return (int) $wpdb->get_var(
      $wpdb->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
        $table
      )
    ) > 0;
  }
}

if (!function_exists('pointlybooking_db_table_columns')) {
  function pointlybooking_db_table_columns(string $table): array {
    global $wpdb;

    if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
      return [];
    }

    $columns = $wpdb->get_col(
      $wpdb->prepare(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s ORDER BY ORDINAL_POSITION ASC',
        $table
      )
    );

    return is_array($columns) ? $columns : [];
  }
}

if (!function_exists('pointlybooking_db_column_exists')) {
  function pointlybooking_db_column_exists(string $table, string $column): bool {
    global $wpdb;

    if (
      preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1
      || preg_match('/^[A-Za-z0-9_]+$/', $column) !== 1
    ) {
      return false;
    }

    return (int) $wpdb->get_var(
      $wpdb->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
        $table,
        $column
      )
    ) > 0;
  }
}

if (!function_exists('pointlybooking_db_index_exists')) {
  function pointlybooking_db_index_exists(string $table, string $index_name): bool {
    global $wpdb;

    if (
      preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1
      || preg_match('/^[A-Za-z0-9_]+$/', $index_name) !== 1
    ) {
      return false;
    }

    return (int) $wpdb->get_var(
      $wpdb->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = %s',
        $table,
        $index_name
      )
    ) > 0;
  }
}

if (!function_exists('pointlybooking_db_column_index_exists')) {
  function pointlybooking_db_column_index_exists(string $table, string $column): bool {
    global $wpdb;

    if (
      preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1
      || preg_match('/^[A-Za-z0-9_]+$/', $column) !== 1
    ) {
      return false;
    }

    return (int) $wpdb->get_var(
      $wpdb->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
        $table,
        $column
      )
    ) > 0;
  }
}

final class POINTLYBOOKING_DatabaseHelper {

  const DB_VERSION_OPTION = 'pointlybooking_db_version';

  public static function install_or_update(string $target_version) : void {
    $installed_version = get_option(self::DB_VERSION_OPTION, '');

    $needs_migration = ($installed_version !== $target_version) || self::tables_missing();

    if ($needs_migration) {
      POINTLYBOOKING_MigrationsHelper::create_tables();
      update_option(self::DB_VERSION_OPTION, $target_version, false);
    }
  }

  private static function tables_missing() : bool {
    global $wpdb;

    $tables = [
      $wpdb->prefix . 'pointlybooking_services',
      $wpdb->prefix . 'pointlybooking_agents',
      $wpdb->prefix . 'pointlybooking_bookings',
      $wpdb->prefix . 'pointlybooking_customers',
      $wpdb->prefix . 'pointlybooking_settings',
      $wpdb->prefix . 'pointlybooking_form_fields',
      $wpdb->prefix . 'pointlybooking_field_values',
    ];

    foreach ($tables as $table) {
      if (!pointlybooking_db_table_exists($table)) {
        return true;
      }
    }

    return false;
  }
}
