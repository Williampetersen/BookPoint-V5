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
  $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$t}");
}

// delete options

delete_option('pointlybooking_db_version');
delete_option('pointlybooking_remove_data_on_uninstall');
