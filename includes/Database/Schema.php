<?php
/**
 * Complete table definitions for dbDelta().
 *
 * The definitions mirror the schema produced by every 2.x release so that
 * dbDelta() only ever adds what is missing (new columns/indexes) and never
 * rewrites existing data. Changes are introduced through Migrator steps.
 *
 * @package PointlyBooking
 */

namespace PointlyBooking\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Builds CREATE TABLE statements.
 */
final class Schema {

	/**
	 * Returns every CREATE TABLE statement keyed by logical name.
	 *
	 * @return array<string,string>
	 */
	public static function statements() {
		global $wpdb;
		$c = $wpdb->get_charset_collate();
		$t = static function ( $name ) {
			return Tables::name( $name );
		};

		$sql = array();

		$sql['services'] = "CREATE TABLE {$t('services')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(190) NOT NULL,
  description longtext NULL,
  duration_minutes int(10) unsigned NOT NULL DEFAULT 60,
  price_cents int(10) unsigned NOT NULL DEFAULT 0,
  currency char(3) NOT NULL DEFAULT 'USD',
  is_active tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  use_global_schedule tinyint(1) NOT NULL DEFAULT 1,
  schedule_json longtext NULL,
  buffer_before_minutes int(11) NOT NULL DEFAULT 0,
  buffer_after_minutes int(11) NOT NULL DEFAULT 0,
  capacity int(11) NOT NULL DEFAULT 1,
  category_id bigint(20) unsigned NULL,
  image_id bigint(20) unsigned NULL,
  sort_order int(11) NOT NULL DEFAULT 0,
  buffer_before int(11) NOT NULL DEFAULT 0,
  buffer_after int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY is_active (is_active),
  KEY sort_order (sort_order)
) $c;";

		$sql['categories'] = "CREATE TABLE {$t('categories')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(190) NOT NULL,
  description text NULL,
  image_id bigint(20) unsigned NULL,
  sort_order int(11) NOT NULL DEFAULT 0,
  is_active tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime NULL,
  PRIMARY KEY  (id),
  KEY is_active (is_active),
  KEY sort_order (sort_order)
) $c;";

		$sql['service_categories'] = "CREATE TABLE {$t('service_categories')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  service_id bigint(20) unsigned NOT NULL,
  category_id bigint(20) unsigned NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uniq (service_id,category_id),
  KEY service_id (service_id),
  KEY category_id (category_id)
) $c;";

		$sql['service_extras'] = "CREATE TABLE {$t('service_extras')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  service_id bigint(20) unsigned NOT NULL DEFAULT 0,
  name varchar(190) NOT NULL,
  description text NULL,
  price decimal(10,2) NOT NULL DEFAULT 0.00,
  duration_min int(11) NULL,
  image_id bigint(20) unsigned NULL,
  sort_order int(11) NOT NULL DEFAULT 0,
  is_active tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime NULL,
  PRIMARY KEY  (id),
  KEY service_id (service_id),
  KEY is_active (is_active),
  KEY sort_order (sort_order)
) $c;";

		$sql['extra_services'] = "CREATE TABLE {$t('extra_services')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  extra_id bigint(20) unsigned NOT NULL,
  service_id bigint(20) unsigned NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uniq (extra_id,service_id),
  KEY extra_id (extra_id),
  KEY service_id (service_id)
) $c;";

		$sql['agents'] = "CREATE TABLE {$t('agents')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  first_name varchar(191) NULL,
  last_name varchar(191) NULL,
  email varchar(191) NULL,
  phone varchar(50) NULL,
  is_active tinyint(1) NOT NULL DEFAULT 1,
  schedule_json longtext NULL,
  created_at datetime NULL,
  updated_at datetime NULL,
  image_id bigint(20) unsigned NULL,
  PRIMARY KEY  (id),
  KEY is_active (is_active)
) $c;";

		$sql['agent_services'] = "CREATE TABLE {$t('agent_services')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  agent_id bigint(20) unsigned NOT NULL,
  service_id bigint(20) unsigned NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY agent_service (agent_id,service_id),
  KEY agent_id (agent_id),
  KEY service_id (service_id)
) $c;";

		$sql['service_agents'] = "CREATE TABLE {$t('service_agents')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  service_id bigint(20) unsigned NOT NULL,
  agent_id bigint(20) unsigned NOT NULL,
  created_at datetime NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY service_agent (service_id,agent_id),
  KEY service_id (service_id),
  KEY agent_id (agent_id)
) $c;";

		$sql['agent_working_hours'] = "CREATE TABLE {$t('agent_working_hours')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  agent_id bigint(20) unsigned NOT NULL,
  weekday tinyint(4) NOT NULL,
  start_time time NOT NULL,
  end_time time NOT NULL,
  is_enabled tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY  (id),
  KEY agent_weekday (agent_id,weekday)
) $c;";

		$sql['agent_breaks'] = "CREATE TABLE {$t('agent_breaks')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  agent_id bigint(20) unsigned NOT NULL,
  break_date date NOT NULL,
  start_time time NOT NULL,
  end_time time NOT NULL,
  note varchar(255) NULL,
  PRIMARY KEY  (id),
  KEY agent_date (agent_id,break_date)
) $c;";

		$sql['customers'] = "CREATE TABLE {$t('customers')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  first_name varchar(190) NULL,
  last_name varchar(190) NULL,
  email varchar(190) NULL,
  phone varchar(50) NULL,
  wp_user_id bigint(20) unsigned NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  custom_fields_json longtext NULL,
  PRIMARY KEY  (id),
  KEY wp_user_id (wp_user_id),
  KEY email (email),
  KEY created_at (created_at)
) $c;";

		$sql['bookings'] = "CREATE TABLE {$t('bookings')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  service_id bigint(20) unsigned NOT NULL,
  customer_id bigint(20) unsigned NOT NULL,
  agent_id bigint(20) unsigned NULL,
  start_datetime datetime NOT NULL,
  end_datetime datetime NOT NULL,
  status varchar(30) NOT NULL DEFAULT 'pending',
  notes longtext NULL,
  manage_key char(64) NOT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  manage_token_last_used_at datetime NULL,
  category_id bigint(20) unsigned NULL,
  extras_json longtext NULL,
  promo_code varchar(60) NULL,
  discount_total decimal(10,2) NOT NULL DEFAULT 0.00,
  total_price decimal(10,2) NOT NULL DEFAULT 0.00,
  currency char(3) NOT NULL DEFAULT 'USD',
  payment_method varchar(30) NOT NULL DEFAULT 'cash',
  payment_status varchar(30) NOT NULL DEFAULT 'unpaid',
  payment_provider_ref varchar(190) NULL,
  payment_amount decimal(10,2) NULL,
  payment_currency char(3) NULL,
  customer_fields_json longtext NULL,
  booking_fields_json longtext NULL,
  custom_fields_json longtext NULL,
  location_id bigint(20) unsigned NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY manage_key (manage_key),
  KEY service_id (service_id),
  KEY customer_id (customer_id),
  KEY start_datetime (start_datetime),
  KEY status (status),
  KEY agent_id (agent_id),
  KEY category_id (category_id),
  KEY location_id (location_id),
  KEY agent_start (agent_id,start_datetime),
  KEY created_at (created_at)
) $c;";

		$sql['settings'] = "CREATE TABLE {$t('settings')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  setting_key varchar(190) NOT NULL,
  setting_value longtext NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY setting_key (setting_key)
) $c;";

		$sql['form_fields'] = "CREATE TABLE {$t('form_fields')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  field_key varchar(80) NOT NULL,
  label varchar(190) NOT NULL,
  type varchar(30) NOT NULL DEFAULT 'text',
  scope varchar(20) NOT NULL DEFAULT 'booking',
  step_key varchar(30) NOT NULL DEFAULT 'details',
  placeholder varchar(190) NULL,
  options longtext NULL,
  is_required tinyint(1) NOT NULL DEFAULT 0,
  is_enabled tinyint(1) NOT NULL DEFAULT 1,
  show_in_wizard tinyint(1) NOT NULL DEFAULT 1,
  sort_order int(11) NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  name_key varchar(120) NULL,
  options_json longtext NULL,
  required tinyint(1) NOT NULL DEFAULT 0,
  is_active tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY  (id),
  UNIQUE KEY uniq_field_key_scope (field_key,scope),
  UNIQUE KEY scope_name_key (scope,name_key),
  KEY idx_scope_enabled (scope,is_enabled,sort_order),
  KEY idx_step (step_key),
  KEY scope (scope),
  KEY is_active (is_active),
  KEY sort_order (sort_order)
) $c;";

		$sql['field_values'] = "CREATE TABLE {$t('field_values')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  entity_type varchar(20) NOT NULL,
  entity_id bigint(20) unsigned NOT NULL,
  field_id bigint(20) unsigned NOT NULL,
  field_key varchar(80) NOT NULL,
  scope varchar(20) NOT NULL,
  value_long longtext NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY idx_entity (entity_type,entity_id),
  KEY idx_field (field_id),
  KEY idx_scope (scope),
  KEY idx_field_key (field_key)
) $c;";

		$sql['promo_codes'] = "CREATE TABLE {$t('promo_codes')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  code varchar(60) NOT NULL,
  type varchar(10) NOT NULL,
  amount decimal(10,2) NOT NULL DEFAULT 0.00,
  starts_at datetime NULL,
  ends_at datetime NULL,
  max_uses int(11) NULL,
  uses_count int(11) NOT NULL DEFAULT 0,
  min_total decimal(10,2) NULL,
  is_active tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY code (code),
  KEY is_active (is_active)
) $c;";

		$sql['holidays'] = "CREATE TABLE {$t('holidays')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  agent_id bigint(20) unsigned NULL,
  title varchar(190) NOT NULL,
  start_date date NOT NULL,
  end_date date NOT NULL,
  is_recurring tinyint(1) NOT NULL DEFAULT 0,
  is_recurring_yearly tinyint(1) NOT NULL DEFAULT 0,
  is_enabled tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime NULL,
  PRIMARY KEY  (id),
  KEY date_range (start_date,end_date),
  KEY agent_id (agent_id),
  KEY enabled (is_enabled)
) $c;";

		$sql['schedules'] = "CREATE TABLE {$t('schedules')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  agent_id bigint(20) unsigned NULL,
  day_of_week tinyint(4) NOT NULL,
  start_time time NOT NULL,
  end_time time NOT NULL,
  breaks_json longtext NULL,
  is_enabled tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime NULL,
  PRIMARY KEY  (id),
  KEY agent_day (agent_id,day_of_week)
) $c;";

		$sql['schedule_settings'] = "CREATE TABLE {$t('schedule_settings')} (
  id bigint(20) unsigned NOT NULL,
  slot_interval_minutes int(11) NOT NULL DEFAULT 30,
  timezone varchar(64) NOT NULL DEFAULT 'UTC',
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime NULL,
  PRIMARY KEY  (id)
) $c;";

		$sql['workflows'] = "CREATE TABLE {$t('workflows')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(255) NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'active',
  event_key varchar(80) NOT NULL,
  is_conditional tinyint(1) NOT NULL DEFAULT 0,
  conditions_json longtext NULL,
  has_time_offset tinyint(1) NOT NULL DEFAULT 0,
  time_offset_minutes int(11) NOT NULL DEFAULT 0,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime NULL,
  PRIMARY KEY  (id),
  KEY event_key (event_key),
  KEY status (status)
) $c;";

		$sql['workflow_actions'] = "CREATE TABLE {$t('workflow_actions')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  workflow_id bigint(20) unsigned NOT NULL,
  type varchar(40) NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'active',
  config_json longtext NULL,
  sort_order int(11) NOT NULL DEFAULT 0,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime NULL,
  PRIMARY KEY  (id),
  KEY workflow_id (workflow_id),
  KEY sort_order (sort_order)
) $c;";

		$sql['workflow_logs'] = "CREATE TABLE {$t('workflow_logs')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  workflow_id bigint(20) unsigned NOT NULL,
  event_key varchar(80) NOT NULL,
  entity_type varchar(40) NOT NULL,
  entity_id bigint(20) unsigned NULL,
  status varchar(20) NOT NULL,
  message longtext NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY workflow_id (workflow_id),
  KEY event_key (event_key),
  KEY entity_type (entity_type),
  KEY entity_id (entity_id)
) $c;";

		$sql['audit_log'] = "CREATE TABLE {$t('audit_log')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  event varchar(60) NOT NULL,
  actor_type varchar(20) NOT NULL,
  actor_wp_user_id bigint(20) unsigned NULL,
  actor_ip varchar(60) NULL,
  booking_id bigint(20) unsigned NULL,
  customer_id bigint(20) unsigned NULL,
  meta longtext NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY event (event),
  KEY booking_id (booking_id),
  KEY customer_id (customer_id),
  KEY created_at (created_at)
) $c;";

		$sql['locations'] = "CREATE TABLE {$t('locations')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  status varchar(20) NOT NULL DEFAULT 'active',
  name varchar(190) NOT NULL,
  address varchar(255) NULL,
  category_id bigint(20) unsigned NULL,
  image_id bigint(20) unsigned NULL,
  use_custom_schedule tinyint(1) NOT NULL DEFAULT 0,
  schedule_json longtext NULL,
  created_at datetime NULL,
  updated_at datetime NULL,
  PRIMARY KEY  (id),
  KEY status (status),
  KEY category_id (category_id)
) $c;";

		$sql['location_categories'] = "CREATE TABLE {$t('location_categories')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  status varchar(20) NOT NULL DEFAULT 'active',
  name varchar(190) NOT NULL,
  image_id bigint(20) unsigned NULL,
  created_at datetime NULL,
  updated_at datetime NULL,
  PRIMARY KEY  (id),
  KEY status (status)
) $c;";

		$sql['location_agents'] = "CREATE TABLE {$t('location_agents')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  location_id bigint(20) unsigned NOT NULL,
  agent_id bigint(20) unsigned NOT NULL,
  services_json longtext NULL,
  created_at datetime NULL,
  updated_at datetime NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY loc_agent (location_id,agent_id),
  KEY location_id (location_id),
  KEY agent_id (agent_id)
) $c;";

		$sql['bundles'] = "CREATE TABLE {$t('bundles')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(190) NOT NULL,
  description text NULL,
  price decimal(10,2) NOT NULL DEFAULT 0.00,
  image_id bigint(20) unsigned NULL,
  is_active tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime NULL,
  PRIMARY KEY  (id),
  KEY is_active (is_active)
) $c;";

		$sql['bundle_items'] = "CREATE TABLE {$t('bundle_items')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  bundle_id bigint(20) unsigned NOT NULL,
  item_type varchar(20) NOT NULL,
  item_id bigint(20) unsigned NOT NULL,
  qty int(11) NOT NULL DEFAULT 1,
  sort_order int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY bundle_id (bundle_id),
  KEY item_type (item_type),
  KEY item_id (item_id)
) $c;";

		return $sql;
	}

	/**
	 * Creates or upgrades every table with dbDelta().
	 *
	 * @return void
	 */
	public static function install() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( self::statements() as $statement ) {
			dbDelta( $statement );
		}
	}
}
