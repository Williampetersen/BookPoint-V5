<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_AdminDashboardController extends POINTLYBOOKING_Controller {

  public function index(): void {
    $this->require_cap('pointlybooking_manage_bookings');

    $range = POINTLYBOOKING_DashboardHelper::range();
    $kpis = POINTLYBOOKING_DashboardHelper::kpis_for_range($range['from'], $range['to']);
    $series = POINTLYBOOKING_DashboardHelper::bookings_series_for_range($range['from'], $range['to']);

    $top_services = POINTLYBOOKING_DashboardHelper::top_services($range['from'], $range['to']);
    $top_categories = POINTLYBOOKING_DashboardHelper::top_categories($range['from'], $range['to']);
    $top_agents = POINTLYBOOKING_DashboardHelper::top_agents($range['from'], $range['to']);

    $pending = POINTLYBOOKING_DashboardHelper::pending_bookings(8);
    $recent = POINTLYBOOKING_DashboardHelper::recent_bookings(10);

    $this->render('admin/dashboard_v2', compact(
      'range', 'kpis', 'series', 'top_services', 'top_categories', 'top_agents', 'pending', 'recent'
    ));
  }
}

