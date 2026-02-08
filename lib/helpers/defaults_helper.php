<?php
defined('ABSPATH') || exit;

function bp_defaults_read_json_file(): array {
  $paths = [
    BP_PLUGIN_PATH . 'public/defaults.json',
    BP_PLUGIN_PATH . 'public/default-settings.json',
  ];

  foreach ($paths as $p) {
    if (!file_exists($p)) continue;
    $raw = file_get_contents($p);
    if ($raw === false || trim($raw) === '') continue;
    $data = json_decode($raw, true);
    if (is_array($data)) return $data;
  }

  return [];
}

function bp_default_settings_from_file(): ?array {
  $data = bp_defaults_read_json_file();
  if (isset($data['bp_settings']) && is_array($data['bp_settings'])) {
    return $data['bp_settings'];
  }
  if (!empty($data)) {
    return $data;
  }
  return null;
}

function bp_default_design_from_file(): ?array {
  $data = bp_defaults_read_json_file();
  if (isset($data['bp_booking_form_design']) && is_array($data['bp_booking_form_design'])) {
    return $data['bp_booking_form_design'];
  }

  $design_path = BP_PLUGIN_PATH . 'public/default-design.json';
  if (file_exists($design_path)) {
    $raw = file_get_contents($design_path);
    if ($raw !== false && trim($raw) !== '') {
      $design = json_decode($raw, true);
      if (is_array($design)) return $design;
    }
  }

  return null;
}
