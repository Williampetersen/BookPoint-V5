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

    $this->render('admin/services_edit', [
      'service' => $service,
      'errors' => [],
    ]);
  }

  public function save() : void {
    $this->require_cap('bp_manage_services');

    check_admin_referer('bp_admin');

    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;

    $data = [
      'name' => sanitize_text_field($_POST['name'] ?? ''),
      'description' => wp_kses_post($_POST['description'] ?? ''),
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
      ]);
      return;
    }

    if ($id) {
      BP_ServiceModel::update($id, $data);
    } else {
      BP_ServiceModel::create($data);
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

