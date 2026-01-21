<?php
defined('ABSPATH') || exit;

final class BP_AdminDashboardController extends BP_Controller {

  public function index(): void {
    $this->require_cap('bp_manage_bookings');

    $range = BP_DashboardHelper::range();
    $kpis = BP_DashboardHelper::kpis_for_range($range['from'], $range['to']);
    $series = BP_DashboardHelper::bookings_series_for_range($range['from'], $range['to']);

    $top_services = BP_DashboardHelper::top_services($range['from'], $range['to']);
    $top_categories = BP_DashboardHelper::top_categories($range['from'], $range['to']);
    $top_agents = BP_DashboardHelper::top_agents($range['from'], $range['to']);

    $pending = BP_DashboardHelper::pending_bookings(8);
    $recent = BP_DashboardHelper::recent_bookings(10);

    $this->render('admin/dashboard_v2', compact(
      'range', 'kpis', 'series', 'top_services', 'top_categories', 'top_agents', 'pending', 'recent'
    ));
  }
}

