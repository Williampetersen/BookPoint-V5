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
  $wpdb->prefix . 'pointlybooking_categories',
  $wpdb->prefix . 'pointlybooking_service_extras',
  $wpdb->prefix . 'pointlybooking_bundles',
  $wpdb->prefix . 'pointlybooking_bundle_items',
  $wpdb->prefix . 'pointlybooking_promo_codes',
  $wpdb->prefix . 'pointlybooking_workflows',
  $wpdb->prefix . 'pointlybooking_workflow_actions',
  $wpdb->prefix . 'pointlybooking_workflow_logs',
  $wpdb->prefix . 'pointlybooking_holidays',
  $wpdb->prefix . 'pointlybooking_schedules',
  $wpdb->prefix . 'pointlybooking_schedule_settings',
  $wpdb->prefix . 'pointlybooking_service_categories',
  $wpdb->prefix . 'pointlybooking_extra_services',
  $wpdb->prefix . 'pointlybooking_form_fields',
  $wpdb->prefix . 'pointlybooking_field_values',
  $wpdb->prefix . 'pointlybooking_locations',
  $wpdb->prefix . 'pointlybooking_location_categories',
  $wpdb->prefix . 'pointlybooking_location_agents',
  $wpdb->prefix . 'pointlybooking_agent_working_hours',
  $wpdb->prefix . 'pointlybooking_agent_breaks',
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
