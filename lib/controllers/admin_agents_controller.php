<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_AdminAgentsController extends POINTLYBOOKING_Controller {
  private function parse_hhmm_range(string $range): ?array {
    if (!preg_match('/^((?:[01]\d|2[0-3]):[0-5]\d)\-((?:[01]\d|2[0-3]):[0-5]\d)$/', $range, $m)) {
      return null;
    }

    return [$m[1], $m[2]];
  }

  private function hhmm_to_minutes(string $value): int {
    [$hours, $minutes] = array_map('intval', explode(':', $value));
    return ($hours * 60) + $minutes;
  }

  private function sanitize_schedule_json($raw, array &$errors): ?string {
    $raw = trim((string) $raw);
    if ($raw === '') return null;
    if (strlen($raw) > 5000) {
      $errors['schedule_json'] = __('Schedule JSON is too large.', 'bookpoint-booking');
      return null;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
      $errors['schedule_json'] = __('Schedule JSON must be a valid JSON object.', 'bookpoint-booking');
      return null;
    }
    if (count($decoded) > 7) {
      $errors['schedule_json'] = __('Schedule JSON can contain at most 7 weekday entries.', 'bookpoint-booking');
      return null;
    }

    $normalized = [];
    foreach ($decoded as $day => $range) {
      $day_key = (string) $day;
      if (!preg_match('/^[0-6]$/', $day_key)) {
        $errors['schedule_json'] = __('Schedule JSON keys must be weekday numbers 0-6.', 'bookpoint-booking');
        return null;
      }
      if (is_array($range) || is_object($range)) {
        $errors['schedule_json'] = __('Schedule values must be strings like HH:MM-HH:MM or empty.', 'bookpoint-booking');
        return null;
      }
      $range_str = trim((string) $range);
      if ($range_str === '') {
        $normalized[$day_key] = '';
        continue;
      }
      $parsed = $this->parse_hhmm_range($range_str);
      if ($parsed === null) {
        $errors['schedule_json'] = __('Schedule values must use HH:MM-HH:MM format.', 'bookpoint-booking');
        return null;
      }
      [$open, $close] = $parsed;
      if ($this->hhmm_to_minutes($close) <= $this->hhmm_to_minutes($open)) {
        $errors['schedule_json'] = __('Schedule range end must be after start.', 'bookpoint-booking');
        return null;
      }
      $normalized[$day_key] = $open . '-' . $close;
    }

    ksort($normalized, SORT_NUMERIC);

    $normalized_json = wp_json_encode($normalized);
    if (!is_string($normalized_json) || $normalized_json === '') {
      $errors['schedule_json'] = __('Schedule JSON could not be normalized.', 'bookpoint-booking');
      return null;
    }

    return $normalized_json;
  }

  public function index() : void {
    $this->require_cap('pointlybooking_manage_agents');
    $items = POINTLYBOOKING_AgentModel::all(500, false);
    $this->render('admin/agents_index', ['items' => $items]);
  }

  public function edit() : void {
    $this->require_cap('pointlybooking_manage_agents');

    $id = isset($_GET['id']) ? absint(wp_unslash($_GET['id'])) : 0;
    if ($id > 0) {
      $nonce = sanitize_text_field(wp_unslash($_GET['pointlybooking_edit_nonce'] ?? ''));
      if (!wp_verify_nonce($nonce, 'pointlybooking_edit_agent_' . $id)) {
        wp_die(esc_html__('Security check failed.', 'bookpoint-booking'));
      }
    }
    $agent = $id ? POINTLYBOOKING_AgentModel::find($id) : null;

    $services = POINTLYBOOKING_ServiceModel::all(['is_active' => 1]);
    $selected_service_ids = $id > 0 ? POINTLYBOOKING_AgentModel::get_service_ids_for_agent($id) : [];

    $this->render('admin/agents_edit', [
      'agent' => $agent,
      'errors' => [],
      'services' => $services,
      'selected_service_ids' => $selected_service_ids,
    ]);
  }

  public function save() : void {
    $this->require_cap('pointlybooking_manage_agents');
    check_admin_referer('pointlybooking_admin');

    $id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;
    $errors = [];
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized/validated in sanitize_schedule_json().
    $schedule_json_input = wp_unslash($_POST['schedule_json'] ?? '');
    $schedule_json_raw = '';
    if (is_scalar($schedule_json_input)) {
      $schedule_json_raw = (string) $schedule_json_input;
    } elseif ($schedule_json_input !== null) {
      $errors['schedule_json'] = __('Invalid schedule data.', 'bookpoint-booking');
    }

    $data = [
      'first_name' => sanitize_text_field(wp_unslash($_POST['first_name'] ?? '')),
      'last_name'  => sanitize_text_field(wp_unslash($_POST['last_name'] ?? '')),
      'email'      => sanitize_email(wp_unslash($_POST['email'] ?? '')),
      'phone'      => sanitize_text_field(wp_unslash($_POST['phone'] ?? '')),
      'image_id'   => absint(wp_unslash($_POST['image_id'] ?? 0)),
      'is_active'  => isset($_POST['is_active']) ? 1 : 0,
      'schedule_json' => $this->sanitize_schedule_json($schedule_json_raw, $errors),
    ];

    if (!empty($errors)) {
      $services = POINTLYBOOKING_ServiceModel::all(['is_active' => 1]);
      $selected_service_ids = isset($_POST['service_ids'])
        ? wp_parse_id_list(wp_unslash($_POST['service_ids']))
        : [];

      $agent = $data;
      $agent['id'] = $id;
      $agent['schedule_json'] = sanitize_textarea_field((string) $schedule_json_raw);

      $this->render('admin/agents_edit', [
        'agent' => $agent,
        'errors' => $errors,
        'services' => $services,
        'selected_service_ids' => $selected_service_ids,
      ]);
      return;
    }

    if ($id > 0) {
      POINTLYBOOKING_AgentModel::update($id, $data);
      $agent_id = $id;
    } else {
      $agent_id = POINTLYBOOKING_AgentModel::create($data);
    }

    $service_ids = isset($_POST['service_ids'])
      ? wp_parse_id_list(wp_unslash($_POST['service_ids']))
      : [];
    POINTLYBOOKING_AgentModel::set_services_for_agent($agent_id, $service_ids);

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_agents'));
    exit;
  }

  public function delete() : void {
    $this->require_cap('pointlybooking_manage_agents');
    check_admin_referer('pointlybooking_admin');

    $id = isset($_GET['id']) ? absint(wp_unslash($_GET['id'])) : 0;
    if ($id > 0) POINTLYBOOKING_AgentModel::delete($id);

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_agents'));
    exit;
  }
}

