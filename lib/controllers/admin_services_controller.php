<?php
defined('ABSPATH') || exit;

final class BP_AdminServicesController extends BP_Controller {

  public function index() : void {
    $this->require_cap('bp_manage_services');

    $services = BP_ServiceModel::all();
    $this->render('admin/services_index', [
      'services' => $services,
    ]);
  }

  public function edit() : void {
    $this->require_cap('bp_manage_services');

    $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
    $service = $id ? BP_ServiceModel::find($id) : null;

    $categories = BP_CategoryModel::all(['is_active' => 1]);

    $all_agents = BP_AgentModel::all(500, false);
    $selected_agent_ids = $id ? BP_ServiceAgentModel::get_agent_ids_for_service($id) : [];

    $this->render('admin/services_edit', [
      'service' => $service,
      'errors' => [],
      'categories' => $categories,
      'all_agents' => $all_agents,
      'selected_agent_ids' => $selected_agent_ids,
    ]);
  }

  public function save() : void {
    $this->require_cap('bp_manage_services');

    check_admin_referer('bp_admin');

    error_log('BookPoint: services save() reached');

    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;

    $data = [
      'name' => sanitize_text_field($_POST['name'] ?? ''),
      'description' => wp_kses_post($_POST['description'] ?? ''),
      'category_id' => 0,
      'image_id' => (int)($_POST['image_id'] ?? 0),
      'duration_minutes' => absint($_POST['duration_minutes'] ?? 60),
      'price_cents' => absint($_POST['price_cents'] ?? 0),
      'currency' => sanitize_key($_POST['currency'] ?? 'usd'),
      'is_active' => isset($_POST['is_active']) ? 1 : 0,
      // Step 15: Service-based availability
      'capacity' => absint($_POST['capacity'] ?? 1),
      'buffer_before_minutes' => absint($_POST['buffer_before_minutes'] ?? 0),
      'buffer_after_minutes' => absint($_POST['buffer_after_minutes'] ?? 0),
      'use_global_schedule' => isset($_POST['use_global_schedule']) ? 1 : 0,
      'schedule_json' => wp_unslash($_POST['schedule_json'] ?? ''),
    ];
    $data['currency'] = strtoupper($data['currency']);

    // Step 15: Sanitize schedule_json
    $data['schedule_json'] = trim($data['schedule_json']);
    if ($data['schedule_json'] === '') $data['schedule_json'] = null;

    $errors = BP_ServiceModel::validate($data);
    if (!empty($errors)) {
      $service = $data;
      $service['id'] = $id;

      $this->render('admin/services_edit', [
        'service' => $service,
        'errors' => $errors,
        'categories' => BP_CategoryModel::all(['is_active' => 1]),
        'all_agents' => BP_AgentModel::all(500, false),
        'selected_agent_ids' => isset($_POST['agent_ids']) ? array_map('absint', (array)$_POST['agent_ids']) : [],
      ]);
      return;
    }

    $category_ids = $_POST['category_ids'] ?? [];
    if (!is_array($category_ids)) $category_ids = [];
    $first_cat = !empty($category_ids) ? (int)$category_ids[0] : 0;
    $data['category_id'] = $first_cat;

    if ($id) {
      BP_ServiceModel::update($id, $data);
      $service_id = $id;
    } else {
      $service_id = BP_ServiceModel::create($data);
    }

    BP_ServiceModel::set_categories((int)$service_id, $category_ids);

    global $wpdb;
    $wpdb->update($wpdb->prefix . 'bp_services', ['category_id' => $first_cat], ['id' => $service_id], ['%d'], ['%d']);

    $agent_ids = isset($_POST['agent_ids']) ? (array)$_POST['agent_ids'] : [];
    BP_ServiceAgentModel::set_agents_for_service((int)$service_id, $agent_ids);

    if (!empty($wpdb->last_error)) {
      error_log('BookPoint: services save DB error: ' . $wpdb->last_error);
      error_log('BookPoint: services save last query: ' . $wpdb->last_query);
    }

    wp_safe_redirect(admin_url('admin.php?page=bp_services&updated=1'));
    exit;
  }

  public function delete() : void {
    $this->require_cap('bp_manage_services');

    check_admin_referer('bp_admin');

    $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
    if ($id) {
      BP_ServiceModel::delete($id);
    }

    wp_safe_redirect(admin_url('admin.php?page=bp_services&deleted=1'));
    exit;
  }
}

