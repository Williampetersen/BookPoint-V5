<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

global $wpdb;

$remove = (int) get_option('pointlybooking_remove_data_on_uninstall', 0);
if ($remove !== 1) {
  delete_option('pointlybooking_remove_data_on_uninstall');
  return;
}

$service_agents_table = $wpdb->prefix . 'pointlybooking_service_agents';
$audit_log_table = $wpdb->prefix . 'pointlybooking_audit_log';
$bookings_table = $wpdb->prefix . 'pointlybooking_bookings';
$customers_table = $wpdb->prefix . 'pointlybooking_customers';
$agents_table = $wpdb->prefix . 'pointlybooking_agents';
$services_table = $wpdb->prefix . 'pointlybooking_services';
$settings_table = $wpdb->prefix . 'pointlybooking_settings';

if (preg_match('/^[A-Za-z0-9_]+$/', $service_agents_table) === 1) {
  // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- DROP TABLE cannot use placeholders; this table name is a hardcoded plugin table plus the sanitized WordPress prefix.
  $wpdb->query("DROP TABLE IF EXISTS {$service_agents_table}");
}
if (preg_match('/^[A-Za-z0-9_]+$/', $audit_log_table) === 1) {
  // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- DROP TABLE cannot use placeholders; this table name is a hardcoded plugin table plus the sanitized WordPress prefix.
  $wpdb->query("DROP TABLE IF EXISTS {$audit_log_table}");
}
if (preg_match('/^[A-Za-z0-9_]+$/', $bookings_table) === 1) {
  // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- DROP TABLE cannot use placeholders; this table name is a hardcoded plugin table plus the sanitized WordPress prefix.
  $wpdb->query("DROP TABLE IF EXISTS {$bookings_table}");
}
if (preg_match('/^[A-Za-z0-9_]+$/', $customers_table) === 1) {
  // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- DROP TABLE cannot use placeholders; this table name is a hardcoded plugin table plus the sanitized WordPress prefix.
  $wpdb->query("DROP TABLE IF EXISTS {$customers_table}");
}
if (preg_match('/^[A-Za-z0-9_]+$/', $agents_table) === 1) {
  // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- DROP TABLE cannot use placeholders; this table name is a hardcoded plugin table plus the sanitized WordPress prefix.
  $wpdb->query("DROP TABLE IF EXISTS {$agents_table}");
}
if (preg_match('/^[A-Za-z0-9_]+$/', $services_table) === 1) {
  // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- DROP TABLE cannot use placeholders; this table name is a hardcoded plugin table plus the sanitized WordPress prefix.
  $wpdb->query("DROP TABLE IF EXISTS {$services_table}");
}
if (preg_match('/^[A-Za-z0-9_]+$/', $settings_table) === 1) {
  // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- DROP TABLE cannot use placeholders; this table name is a hardcoded plugin table plus the sanitized WordPress prefix.
  $wpdb->query("DROP TABLE IF EXISTS {$settings_table}");
}

// delete options

delete_option('pointlybooking_db_version');
delete_option('pointlybooking_remove_data_on_uninstall');
