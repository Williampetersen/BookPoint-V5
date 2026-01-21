<?php
defined('ABSPATH') || exit;

function bp_install_form_fields_table() : void {
  global $wpdb;
  $charset_collate = $wpdb->get_charset_collate();
  $t = $wpdb->prefix . 'bp_form_fields';

  $sql = "CREATE TABLE {$t} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    field_key VARCHAR(80) NOT NULL,
    label VARCHAR(190) NOT NULL,
    type VARCHAR(30) NOT NULL DEFAULT 'text',
    scope VARCHAR(20) NOT NULL DEFAULT 'booking',
    step_key VARCHAR(30) NOT NULL DEFAULT 'details',
    placeholder VARCHAR(190) DEFAULT NULL,
    options LONGTEXT DEFAULT NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    show_in_wizard TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    name_key VARCHAR(120) NULL,
    options_json LONGTEXT NULL,
    required TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_field_key_scope (field_key, scope),
    UNIQUE KEY scope_name_key (scope, name_key),
    KEY idx_scope_enabled (scope, is_enabled, sort_order),
    KEY idx_step (step_key),
    KEY scope (scope),
    KEY is_active (is_active),
    KEY sort_order (sort_order)
  ) {$charset_collate};";

  require_once ABSPATH . 'wp-admin/includes/upgrade.php';
  dbDelta($sql);
}

function bp_seed_default_form_fields() : void {
  global $wpdb;
  $t = $wpdb->prefix . 'bp_form_fields';

  $count = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$t}");
  if ($count > 0) return;

  $now = current_time('mysql');

  $defaults = [
    ['field_key'=>'first_name','label'=>'First name','type'=>'text','scope'=>'customer','step_key'=>'details','placeholder'=>'','is_required'=>1,'is_enabled'=>1,'show_in_wizard'=>1,'sort_order'=>10],
    ['field_key'=>'last_name','label'=>'Last name','type'=>'text','scope'=>'customer','step_key'=>'details','placeholder'=>'','is_required'=>0,'is_enabled'=>1,'show_in_wizard'=>1,'sort_order'=>20],
    ['field_key'=>'email','label'=>'Email','type'=>'email','scope'=>'customer','step_key'=>'details','placeholder'=>'','is_required'=>1,'is_enabled'=>1,'show_in_wizard'=>1,'sort_order'=>30],
    ['field_key'=>'phone','label'=>'Phone','type'=>'tel','scope'=>'customer','step_key'=>'details','placeholder'=>'','is_required'=>0,'is_enabled'=>1,'show_in_wizard'=>1,'sort_order'=>40],
    ['field_key'=>'notes','label'=>'Notes','type'=>'textarea','scope'=>'booking','step_key'=>'details','placeholder'=>'','is_required'=>0,'is_enabled'=>1,'show_in_wizard'=>1,'sort_order'=>10],
  ];

  foreach ($defaults as $f) {
    $wpdb->insert($t, [
      'field_key'=>$f['field_key'],
      'label'=>$f['label'],
      'type'=>$f['type'],
      'scope'=>$f['scope'],
      'step_key'=>$f['step_key'],
      'placeholder'=>$f['placeholder'],
      'options'=>null,
      'is_required'=>$f['is_required'],
      'is_enabled'=>$f['is_enabled'],
      'show_in_wizard'=>$f['show_in_wizard'],
      'sort_order'=>$f['sort_order'],
      'created_at'=>$now,
      'updated_at'=>$now,
      'name_key'=>$f['field_key'],
      'options_json'=>null,
      'required'=>$f['is_required'],
      'is_active'=>$f['is_enabled'],
    ], ['%s','%s','%s','%s','%s','%s','%s','%d','%d','%d','%d','%s','%s','%s','%s','%d','%d']);
  }
}
