<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_AdminServicesController extends POINTLYBOOKING_Controller {
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
    $this->require_cap('pointlybooking_manage_services');

    $services = POINTLYBOOKING_ServiceModel::all();
    $this->render('admin/services_index', [
      'services' => $services,
    ]);
  }

  public function edit() : void {
    $this->require_cap('pointlybooking_manage_services');

    $id = isset($_GET['id']) ? absint(wp_unslash($_GET['id'])) : 0;
    if ($id > 0) {
      $nonce = sanitize_text_field(wp_unslash($_GET['pointlybooking_edit_nonce'] ?? ''));
      if (!wp_verify_nonce($nonce, 'pointlybooking_edit_service_' . $id)) {
        wp_die(esc_html__('Security check failed.', 'bookpoint-booking'));
      }
    }
    $service = $id ? POINTLYBOOKING_ServiceModel::find($id) : null;

    $categories = POINTLYBOOKING_CategoryModel::all(['is_active' => 1]);

    $all_agents = POINTLYBOOKING_AgentModel::all(500, false);
    $selected_agent_ids = $id ? POINTLYBOOKING_ServiceAgentModel::get_agent_ids_for_service($id) : [];

    $this->render('admin/services_edit', [
      'service' => $service,
      'errors' => [],
      'categories' => $categories,
      'all_agents' => $all_agents,
      'selected_agent_ids' => $selected_agent_ids,
    ]);
  }

  public function save() : void {
    $this->require_cap('pointlybooking_manage_services');

    check_admin_referer('pointlybooking_admin');

    $id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;

    $schedule_errors = [];
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized/validated in sanitize_schedule_json().
    $schedule_json_input = wp_unslash($_POST['schedule_json'] ?? '');
    $schedule_json_raw = '';
    if (is_scalar($schedule_json_input)) {
      $schedule_json_raw = (string) $schedule_json_input;
    } elseif ($schedule_json_input !== null) {
      $schedule_errors['schedule_json'] = __('Invalid schedule data.', 'bookpoint-booking');
    }
    $data = [
      'name' => sanitize_text_field(wp_unslash($_POST['name'] ?? '')),
      'description' => wp_kses_post(wp_unslash($_POST['description'] ?? '')),
      'category_id' => 0,
      'image_id' => absint(wp_unslash($_POST['image_id'] ?? 0)),
      'duration_minutes' => absint(wp_unslash($_POST['duration_minutes'] ?? 60)),
      'price_cents' => absint(wp_unslash($_POST['price_cents'] ?? 0)),
      'currency' => sanitize_key(wp_unslash($_POST['currency'] ?? 'usd')),
      'is_active' => isset($_POST['is_active']) ? 1 : 0,
      // Step 15: Service-based availability
      'capacity' => absint(wp_unslash($_POST['capacity'] ?? 1)),
      'buffer_before_minutes' => absint(wp_unslash($_POST['buffer_before_minutes'] ?? 0)),
      'buffer_after_minutes' => absint(wp_unslash($_POST['buffer_after_minutes'] ?? 0)),
      'use_global_schedule' => isset($_POST['use_global_schedule']) ? 1 : 0,
      'schedule_json' => $this->sanitize_schedule_json($schedule_json_raw, $schedule_errors),
    ];
    $data['currency'] = strtoupper($data['currency']);

    $errors = POINTLYBOOKING_ServiceModel::validate($data);
    if (!empty($schedule_errors)) {
      $errors = array_merge($errors, $schedule_errors);
    }
    if (!empty($errors)) {
      $service = $data;
      $service['id'] = $id;

      $this->render('admin/services_edit', [
        'service' => $service,
        'errors' => $errors,
        'categories' => POINTLYBOOKING_CategoryModel::all(['is_active' => 1]),
        'all_agents' => POINTLYBOOKING_AgentModel::all(500, false),
        'selected_agent_ids' => isset($_POST['agent_ids']) ? array_map('absint', (array) wp_unslash($_POST['agent_ids'])) : [],
      ]);
      return;
    }

    $category_ids = isset($_POST['category_ids'])
      ? wp_parse_id_list(wp_unslash($_POST['category_ids']))
      : [];
    $first_cat = !empty($category_ids) ? (int)$category_ids[0] : 0;
    $data['category_id'] = $first_cat;

    if ($id) {
      POINTLYBOOKING_ServiceModel::update($id, $data);
      $service_id = $id;
    } else {
      $service_id = POINTLYBOOKING_ServiceModel::create($data);
    }

    POINTLYBOOKING_ServiceModel::set_categories((int)$service_id, $category_ids);

    global $wpdb;
    $wpdb->update($wpdb->prefix . 'pointlybooking_services', ['category_id' => $first_cat], ['id' => $service_id], ['%d'], ['%d']);

    $agent_ids = isset($_POST['agent_ids']) ? array_map('absint', (array) wp_unslash($_POST['agent_ids'])) : [];
    POINTLYBOOKING_ServiceAgentModel::set_agents_for_service((int)$service_id, $agent_ids);

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_services&updated=1'));
    exit;
  }

  public function delete() : void {
    $this->require_cap('pointlybooking_manage_services');

    check_admin_referer('pointlybooking_admin');

    $id = isset($_GET['id']) ? absint(wp_unslash($_GET['id'])) : 0;
    if ($id) {
      POINTLYBOOKING_ServiceModel::delete($id);
    }

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_services&deleted=1'));
    exit;
  }
}

