<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

global $wpdb;

$remove = (int) get_option('pointlybooking_remove_data_on_uninstall', 0);
if ($remove !== 1) {
  delete_option('pointlybooking_remove_data_on_uninstall');
  return;
}

$plugin_tables = [
  $wpdb->prefix . 'pointlybooking_service_agents',
  $wpdb->prefix . 'pointlybooking_audit_log',
  $wpdb->prefix . 'pointlybooking_bookings',
  $wpdb->prefix . 'pointlybooking_customers',
  $wpdb->prefix . 'pointlybooking_agents',
  $wpdb->prefix . 'pointlybooking_services',
  $wpdb->prefix . 'pointlybooking_settings',
];

foreach ($plugin_tables as $plugin_table) {
  if (preg_match('/^[A-Za-z0-9_]+$/', $plugin_table) !== 1) {
    continue;
  }

  // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- DROP TABLE is uninstall-only DDL and this validated plugin table name cannot be parameterized.
  $wpdb->query("DROP TABLE IF EXISTS {$plugin_table}");
}

// delete options

delete_option('pointlybooking_db_version');
delete_option('pointlybooking_remove_data_on_uninstall');
