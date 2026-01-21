<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

global $wpdb;

$remove = (int) get_option('bp_remove_data_on_uninstall', 0);
if ($remove !== 1) {
  delete_option('bp_remove_data_on_uninstall');
  return;
}

$tables = [
  'bp_service_agents',
  'bp_audit_log',
  'bp_bookings',
  'bp_customers',
  'bp_agents',
  'bp_services',
  'bp_settings',
];

foreach ($tables as $t) {
  $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$t}");
}

// delete options

delete_option('BP_db_version');
delete_option('bp_remove_data_on_uninstall');
