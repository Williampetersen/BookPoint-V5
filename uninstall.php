<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

global $wpdb;

$remove = (int) get_option('pointlybooking_remove_data_on_uninstall', 0);
if ($remove !== 1) {
  delete_option('pointlybooking_remove_data_on_uninstall');
  return;
}

$tables = [
  'pointlybooking_service_agents',
  'pointlybooking_audit_log',
  'pointlybooking_bookings',
  'pointlybooking_customers',
  'pointlybooking_agents',
  'pointlybooking_services',
  'pointlybooking_settings',
];

foreach ($tables as $t) {
  $table = $wpdb->prefix . $t;
  if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
    continue;
  }
  // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- DROP TABLE cannot use placeholders; table name is validated by regex.
  $wpdb->query('DROP TABLE IF EXISTS `' . $table . '`');
}

// delete options

delete_option('pointlybooking_db_version');
delete_option('pointlybooking_remove_data_on_uninstall');
