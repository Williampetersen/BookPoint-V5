<?php
defined('ABSPATH') || exit;

class BP_FormFieldsSeedHelper {

  public static function ensure_defaults() : void {
    global $wpdb;

    $table = $wpdb->prefix . 'bp_form_fields';

    // If table doesn't exist, stop (installer must create it)
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    if ($exists !== $table) return;

    $now = current_time('mysql');

    self::normalize_legacy_fields($table, $now);

    // Default customer fields to restore if missing
    $defaults = [
      [
        'field_key' => 'first_name',
        'label' => 'First name',
        'type' => 'text',
        'scope' => 'customer',
        'step_key' => 'details',
        'placeholder' => '',
        'options' => null,
        'is_required' => 1,
        'is_enabled' => 1,
        'show_in_wizard' => 1,
        'sort_order' => 10,
      ],
      [
        'field_key' => 'last_name',
        'label' => 'Last name',
        'type' => 'text',
        'scope' => 'customer',
        'step_key' => 'details',
        'placeholder' => '',
        'options' => null,
        'is_required' => 0,
        'is_enabled' => 1,
        'show_in_wizard' => 1,
        'sort_order' => 20,
      ],
      [
        'field_key' => 'email',
        'label' => 'Email',
        'type' => 'email',
        'scope' => 'customer',
        'step_key' => 'details',
        'placeholder' => '',
        'options' => null,
        'is_required' => 1,
        'is_enabled' => 1,
        'show_in_wizard' => 1,
        'sort_order' => 30,
      ],
      [
        'field_key' => 'phone',
        'label' => 'Phone',
        'type' => 'tel',
        'scope' => 'customer',
        'step_key' => 'details',
        'placeholder' => '',
        'options' => null,
        'is_required' => 0,
        'is_enabled' => 1,
        'show_in_wizard' => 1,
        'sort_order' => 40,
      ],
    ];

    foreach ($defaults as $f) {
      // Check if field exists by (field_key + scope)
      $existing_id = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE field_key=%s AND scope=%s LIMIT 1",
        $f['field_key'],
        $f['scope']
      ));

      if ($existing_id > 0) {
        // If it exists but disabled, force enable it
        $wpdb->update($table, [
          'label' => $f['label'],
          'type' => $f['type'],
          'step_key' => $f['step_key'],
          'placeholder' => $f['placeholder'],
          'is_required' => (int)$f['is_required'],
          'is_enabled' => 1,
          'show_in_wizard' => 1,
          'sort_order' => (int)$f['sort_order'],
          'updated_at' => $now,
        ], ['id' => $existing_id]);

        continue;
      }

      $wpdb->insert($table, [
        'field_key' => $f['field_key'],
        'label' => $f['label'],
        'type' => $f['type'],
        'scope' => $f['scope'],
        'step_key' => $f['step_key'],
        'placeholder' => $f['placeholder'],
        'options' => $f['options'] ? wp_json_encode($f['options']) : null,
        'is_required' => (int)$f['is_required'],
        'is_enabled' => (int)$f['is_enabled'],
        'show_in_wizard' => (int)$f['show_in_wizard'],
        'sort_order' => (int)$f['sort_order'],
        'created_at' => $now,
        'updated_at' => $now,
      ]);
    }
  }

  private static function normalize_legacy_fields(string $table, string $now) : void {
    global $wpdb;

    // Legacy rows where field_key is missing
    $wpdb->query($wpdb->prepare(
      "UPDATE {$table}
       SET field_key = name_key,
           is_required = CASE WHEN (is_required IS NULL OR is_required = 0) AND required = 1 THEN 1 ELSE is_required END,
           is_enabled = CASE WHEN (is_enabled IS NULL OR is_enabled = 1) THEN is_active ELSE is_enabled END,
           show_in_wizard = CASE WHEN show_in_wizard IS NULL THEN 1 ELSE show_in_wizard END,
           step_key = CASE WHEN step_key IS NULL OR step_key = '' THEN 'details' ELSE step_key END,
           options = CASE WHEN (options IS NULL OR options = '') AND options_json IS NOT NULL AND options_json <> '' THEN options_json ELSE options END,
           updated_at = %s
       WHERE (field_key IS NULL OR field_key = '')
         AND name_key IS NOT NULL AND name_key <> ''",
      $now
    ));

    // Newer rows missing legacy columns
    $wpdb->query($wpdb->prepare(
      "UPDATE {$table}
       SET name_key = field_key,
           required = CASE WHEN required IS NULL THEN is_required ELSE required END,
           is_active = CASE WHEN is_active IS NULL THEN is_enabled ELSE is_active END,
           options_json = CASE WHEN (options_json IS NULL OR options_json = '') AND options IS NOT NULL AND options <> '' THEN options ELSE options_json END,
           updated_at = %s
       WHERE (name_key IS NULL OR name_key = '')
         AND field_key IS NOT NULL AND field_key <> ''",
      $now
    ));
  }
}
