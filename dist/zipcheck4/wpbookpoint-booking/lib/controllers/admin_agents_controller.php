<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_AdminAgentsController extends POINTLYBOOKING_Controller {

  public function index() : void {
    $this->require_cap('pointlybooking_manage_agents');
    $items = POINTLYBOOKING_AgentModel::all(500, false);
    $this->render('admin/agents_index', ['items' => $items]);
  }

  public function edit() : void {
    $this->require_cap('pointlybooking_manage_agents');

    $id = isset($_GET['id']) ? absint(wp_unslash($_GET['id'])) : 0;
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

    error_log('BookPoint: agents save() reached');

    $id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;

    $data = [
      'first_name' => sanitize_text_field(wp_unslash($_POST['first_name'] ?? '')),
      'last_name'  => sanitize_text_field(wp_unslash($_POST['last_name'] ?? '')),
      'email'      => sanitize_email(wp_unslash($_POST['email'] ?? '')),
      'phone'      => sanitize_text_field(wp_unslash($_POST['phone'] ?? '')),
      'image_id'   => absint(wp_unslash($_POST['image_id'] ?? 0)),
      'is_active'  => isset($_POST['is_active']) ? 1 : 0,
      'schedule_json' => trim(wp_unslash($_POST['schedule_json'] ?? '')),
    ];

    if ($data['schedule_json'] === '') $data['schedule_json'] = null;

    if ($id > 0) {
      POINTLYBOOKING_AgentModel::update($id, $data);
      $agent_id = $id;
    } else {
      $agent_id = POINTLYBOOKING_AgentModel::create($data);
    }

    $service_ids = isset($_POST['service_ids']) ? wp_unslash($_POST['service_ids']) : [];
    if (!is_array($service_ids)) $service_ids = [];
    $service_ids = array_map('absint', $service_ids);
    POINTLYBOOKING_AgentModel::set_services_for_agent($agent_id, $service_ids);

    global $wpdb;
    if (!empty($wpdb->last_error)) {
      error_log('BookPoint: agents save DB error: ' . $wpdb->last_error);
      error_log('BookPoint: agents save last query: ' . $wpdb->last_query);
    }

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
