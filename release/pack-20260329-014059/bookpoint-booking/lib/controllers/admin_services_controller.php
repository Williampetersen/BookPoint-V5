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
      'updated_notice' => $this->query_text('updated') !== '',
      'deleted_notice' => $this->query_text('deleted') !== '',
    ]);
  }

  public function edit() : void {
    $this->require_cap('pointlybooking_manage_services');

    $id = $this->query_absint('id');
    if ($id > 0) {
      $nonce = $this->query_text('pointlybooking_edit_nonce');
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

    $id = $this->post_absint('id');

    $schedule_errors = [];
    $schedule_json_raw = '';
    if ($this->has_post_field('schedule_json')) {
      if (pointlybooking_request_value_is_array('post', 'schedule_json')) {
        $schedule_errors['schedule_json'] = __('Invalid schedule data.', 'bookpoint-booking');
      } else {
        $schedule_json_raw = sanitize_textarea_field($this->post_raw('schedule_json'));
      }
    }
    $data = [
      'name' => $this->post_text('name'),
      'description' => wp_kses_post($this->post_raw('description')),
      'category_id' => 0,
      'image_id' => $this->post_absint('image_id'),
      'duration_minutes' => $this->post_absint('duration_minutes') ?: 60,
      'price_cents' => $this->post_absint('price_cents'),
      'currency' => $this->post_key('currency') ?: 'usd',
      'is_active' => $this->has_post_field('is_active') ? 1 : 0,
      // Step 15: Service-based availability
      'capacity' => $this->post_absint('capacity') ?: 1,
      'buffer_before_minutes' => $this->post_absint('buffer_before_minutes'),
      'buffer_after_minutes' => $this->post_absint('buffer_after_minutes'),
      'use_global_schedule' => $this->has_post_field('use_global_schedule') ? 1 : 0,
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
        'selected_agent_ids' => array_map('absint', $this->post_array('agent_ids')),
      ]);
      return;
    }

    $category_ids = $this->post_id_list('category_ids');
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
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $wpdb->update($wpdb->prefix . 'pointlybooking_services', ['category_id' => $first_cat], ['id' => $service_id], ['%d'], ['%d']);

    $agent_ids = array_map('absint', $this->post_array('agent_ids'));
    POINTLYBOOKING_ServiceAgentModel::set_agents_for_service((int)$service_id, $agent_ids);

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_services&updated=1'));
    exit;
  }

  public function delete() : void {
    $this->require_cap('pointlybooking_manage_services');

    check_admin_referer('pointlybooking_admin');

    $id = $this->query_absint('id');
    if ($id) {
      POINTLYBOOKING_ServiceModel::delete($id);
    }

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_services&deleted=1'));
    exit;
  }
}
