<?php
/**
 * Plugin Name: BookPoint Pro Add-on
 * Description: Enables Pro features for the BookPoint plugin.
 * Version: 6.1.8
 * Author: William
 * Text Domain: bookpoint
 */

defined('ABSPATH') || exit;

if (!defined('BP_IS_PRO')) {
  define('BP_IS_PRO', true);
}

// Bootstrap Pro helpers after the free plugin has initialized.
add_action('plugins_loaded', function () {
  // If the free plugin is not active, show a notice and stop.
  if (!defined('BP_PLUGIN_FILE')) {
    add_action('admin_notices', function () {
      if (!current_user_can('activate_plugins')) return;
      echo '<div class="notice notice-error"><p>';
      echo esc_html__('BookPoint Pro Add-on requires the free BookPoint plugin to be installed and active.', 'bookpoint');
      echo '</p></div>';
    });
    return;
  }

  $inc = __DIR__ . '/includes/';
  $req = [
    $inc . 'license_helper.php',
    $inc . 'license_gate_helper.php',
    $inc . 'updates_helper.php',
  ];

  foreach ($req as $file) {
    if (file_exists($file)) {
      require_once $file;
    }
  }

  if (class_exists('BP_UpdatesHelper')) {
    BP_UpdatesHelper::init();
  }
}, 30);

