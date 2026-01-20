<?php
defined('ABSPATH') || exit;

final class BP_AdminAgentsController extends BP_Controller {

  public function index() : void {
    $this->require_cap('bp_manage_settings');
    $items = BP_AgentModel::all(500, false);
    $this->render('admin/agents_index', ['items' => $items]);
  }

  public function edit() : void {
    $this->require_cap('bp_manage_settings');

    $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
    $agent = $id ? BP_AgentModel::find($id) : null;

    $this->render('admin/agents_edit', [
      'agent' => $agent,
      'errors' => [],
    ]);
  }

  public function save() : void {
    $this->require_cap('bp_manage_settings');
    check_admin_referer('bp_admin');

    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;

    $data = [
      'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
      'last_name'  => sanitize_text_field($_POST['last_name'] ?? ''),
      'email'      => sanitize_email($_POST['email'] ?? ''),
      'phone'      => sanitize_text_field($_POST['phone'] ?? ''),
      'is_active'  => isset($_POST['is_active']) ? 1 : 0,
      'schedule_json' => trim(wp_unslash($_POST['schedule_json'] ?? '')),
    ];

    if ($data['schedule_json'] === '') $data['schedule_json'] = null;

    if ($id > 0) {
      BP_AgentModel::update($id, $data);
    } else {
      BP_AgentModel::create($data);
    }

    wp_safe_redirect(admin_url('admin.php?page=bp_agents'));
    exit;
  }

  public function delete() : void {
    $this->require_cap('bp_manage_settings');
    check_admin_referer('bp_admin');

    $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
    if ($id > 0) BP_AgentModel::delete($id);

    wp_safe_redirect(admin_url('admin.php?page=bp_agents'));
    exit;
  }
}
