<?php
defined('ABSPATH') || exit;

final class POINTLYBOOKING_AdminBookingsController extends POINTLYBOOKING_Controller {

  public function index() : void {
    $this->require_cap('pointlybooking_manage_bookings');
    $has_filter_nonce = $this->has_valid_admin_filter_nonce();

    $args = [
      'q' => $has_filter_nonce ? $this->query_text('q') : '',
      'status' => $has_filter_nonce ? $this->query_text('status') : '',
      'service_id' => $has_filter_nonce ? $this->query_absint('service_id') : 0,
      'agent_id' => $has_filter_nonce ? $this->query_absint('agent_id') : 0,
      'date_from' => $has_filter_nonce ? $this->query_text('date_from') : '',
      'date_to' => $has_filter_nonce ? $this->query_text('date_to') : '',
    ];

    $paged = POINTLYBOOKING_BookingModel::admin_list_paged(array_merge($args, [
      'page' => $has_filter_nonce ? max(1, $this->query_absint('paged')) : 1,
      'per_page' => $has_filter_nonce ? max(1, $this->query_absint('per_page')) : 50,
    ]));
    $items = $paged['items'] ?? [];
    $services = POINTLYBOOKING_ServiceModel::all();
    $agents = POINTLYBOOKING_AgentModel::all(500, true);
    $export_args = array_merge($args, [
      'action' => 'pointlybooking_admin_bookings_export_csv',
      'page' => 'pointlybooking_bookings',
      'paged' => (string) ($paged['page'] ?? 1),
      'per_page' => (string) ($paged['per_page'] ?? 50),
    ]);
    if ($has_filter_nonce) {
      $export_args['pointlybooking_filter_nonce'] = $this->query_text('pointlybooking_filter_nonce');
    }
    $export_url = wp_nonce_url(
      add_query_arg($export_args, admin_url('admin-post.php')),
      'pointlybooking_admin'
    );

    $this->render('admin/bookings_index', [
      'items' => $items,
      'pagination' => $paged,
      'filters' => $args,
      'services' => $services,
      'agents' => $agents,
      'updated_notice' => $this->query_text('updated') !== '',
      'export_url' => $export_url,
    ]);
  }

  public function confirm() : void {
    $this->require_cap('pointlybooking_manage_bookings');
    check_admin_referer('pointlybooking_admin');

    $id = $this->query_absint('id');
    if ($id <= 0) {
      wp_safe_redirect(admin_url('admin.php?page=pointlybooking_bookings'));
      exit;
    }

    $booking = POINTLYBOOKING_BookingModel::find($id);
    if (!$booking) {
      wp_safe_redirect(admin_url('admin.php?page=pointlybooking_bookings'));
      exit;
    }

    $old = (string)($booking['status'] ?? 'pending');
    POINTLYBOOKING_BookingModel::update_status($id, 'confirmed');

    $this->email_status_change($id, $old, 'confirmed');

    POINTLYBOOKING_AuditHelper::log('booking_status_changed', [
      'actor_type' => 'admin',
      'booking_id' => $id,
      'meta' => ['old' => $old, 'new' => 'confirmed'],
    ]);

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_bookings&updated=1'));
    exit;
  }

  public function cancel() : void {
    $this->require_cap('pointlybooking_manage_bookings');
    check_admin_referer('pointlybooking_admin');

    $id = $this->query_absint('id');
    if ($id <= 0) {
      wp_safe_redirect(admin_url('admin.php?page=pointlybooking_bookings'));
      exit;
    }

    $booking = POINTLYBOOKING_BookingModel::find($id);
    if (!$booking) {
      wp_safe_redirect(admin_url('admin.php?page=pointlybooking_bookings'));
      exit;
    }

    $old = (string)($booking['status'] ?? 'pending');
    POINTLYBOOKING_BookingModel::update_status($id, 'cancelled');

    $this->email_status_change($id, $old, 'cancelled');

    POINTLYBOOKING_AuditHelper::log('booking_status_changed', [
      'actor_type' => 'admin',
      'booking_id' => $id,
      'meta' => ['old' => $old, 'new' => 'cancelled'],
    ]);

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_bookings&updated=1'));
    exit;
  }

  public function save_notes() : void {
    $this->require_cap('pointlybooking_manage_bookings');
    check_admin_referer('pointlybooking_admin');

    $id = $this->post_absint('id');
    $notes = wp_kses_post($this->post_raw('notes'));

    if ($id > 0) {
      POINTLYBOOKING_BookingModel::update_notes($id, $notes);
    }

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_bookings&updated=1'));
    exit;
  }

  public function export_csv() : void {
    $this->require_cap('pointlybooking_manage_bookings');
    check_admin_referer('pointlybooking_admin');
    $has_filter_nonce = $this->has_valid_admin_filter_nonce();

    $args = [
      'q' => $has_filter_nonce ? $this->query_text('q') : '',
      'status' => $has_filter_nonce ? $this->query_text('status') : '',
      'service_id' => $has_filter_nonce ? $this->query_absint('service_id') : 0,
      'agent_id' => $has_filter_nonce ? $this->query_absint('agent_id') : 0,
      'date_from' => $has_filter_nonce ? $this->query_text('date_from') : '',
      'date_to' => $has_filter_nonce ? $this->query_text('date_to') : '',
    ];

    $rows = POINTLYBOOKING_BookingModel::admin_list($args);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="bookings-' . gmdate('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF";

    $csv_rows = [];
    foreach ($rows as $r) {
      $csv_rows[] = [
        (int)$r['id'],
        (string)($r['status'] ?? ''),
        (string)($r['service_name'] ?? ''),
        (string)($r['agent_name'] ?? ''),
        (string)($r['customer_name'] ?? ''),
        (string)($r['customer_email'] ?? ''),
        (string)($r['customer_phone'] ?? ''),
        (string)($r['start_datetime'] ?? ''),
        (string)($r['end_datetime'] ?? ''),
        (string)($r['notes'] ?? ''),
      ];
    }
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV output, not HTML.
    echo pointlybooking_build_csv(['Booking ID','Status','Service','Agent','Customer','Email','Phone','Start','End','Notes'], $csv_rows);
    exit;
  }

  public function export_pdf() : void {
    $this->require_cap('pointlybooking_manage_bookings');
    check_admin_referer('pointlybooking_admin');
    $has_filter_nonce = $this->has_valid_admin_filter_nonce();

    $args = [
      'q' => $has_filter_nonce ? $this->query_text('q') : '',
      'status' => $has_filter_nonce ? $this->query_text('status') : '',
      'service_id' => $has_filter_nonce ? $this->query_absint('service_id') : 0,
      'agent_id' => $has_filter_nonce ? $this->query_absint('agent_id') : 0,
      'date_from' => $has_filter_nonce ? $this->query_text('date_from') : '',
      'date_to' => $has_filter_nonce ? $this->query_text('date_to') : '',
    ];

    $rows = POINTLYBOOKING_BookingModel::admin_list($args);

    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: inline; filename="bookings-' . gmdate('Y-m-d') . '.html"');

    $th_style = 'border:1px solid #e5e7eb;padding:6px 8px;text-align:left;vertical-align:top;background:#f9fafb;';
    $td_style = 'border:1px solid #e5e7eb;padding:6px 8px;text-align:left;vertical-align:top;';

    echo '<!doctype html><html><head><meta charset="utf-8">';
    echo '<title>Bookings Export</title>';
    echo '</head><body style="font-family:Arial,sans-serif;color:#111;padding:24px;">';
    echo '<h1 style="font-size:20px;margin:0 0 12px;">Bookings Export</h1>';
    echo '<div style="color:#64748b;font-size:12px;margin-bottom:12px;">Generated ' . esc_html(gmdate('Y-m-d H:i')) . '</div>';
    echo '<table style="width:100%;border-collapse:collapse;font-size:12px;"><thead><tr>';
    echo '<th style="' . esc_attr($th_style) . '">ID</th><th style="' . esc_attr($th_style) . '">Status</th><th style="' . esc_attr($th_style) . '">Service</th><th style="' . esc_attr($th_style) . '">Agent</th><th style="' . esc_attr($th_style) . '">Customer</th><th style="' . esc_attr($th_style) . '">Email</th><th style="' . esc_attr($th_style) . '">Phone</th><th style="' . esc_attr($th_style) . '">Start</th><th style="' . esc_attr($th_style) . '">End</th><th style="' . esc_attr($th_style) . '">Notes</th>';
    echo '</tr></thead><tbody>';

    foreach ($rows as $r) {
      echo '<tr>';
      echo '<td style="' . esc_attr($td_style) . '">' . esc_html((string)($r['id'] ?? '')) . '</td>';
      echo '<td style="' . esc_attr($td_style) . '">' . esc_html((string)($r['status'] ?? '')) . '</td>';
      echo '<td style="' . esc_attr($td_style) . '">' . esc_html((string)($r['service_name'] ?? '')) . '</td>';
      echo '<td style="' . esc_attr($td_style) . '">' . esc_html((string)($r['agent_name'] ?? '')) . '</td>';
      echo '<td style="' . esc_attr($td_style) . '">' . esc_html((string)($r['customer_name'] ?? '')) . '</td>';
      echo '<td style="' . esc_attr($td_style) . '">' . esc_html((string)($r['customer_email'] ?? '')) . '</td>';
      echo '<td style="' . esc_attr($td_style) . '">' . esc_html((string)($r['customer_phone'] ?? '')) . '</td>';
      echo '<td style="' . esc_attr($td_style) . '">' . esc_html((string)($r['start_datetime'] ?? '')) . '</td>';
      echo '<td style="' . esc_attr($td_style) . '">' . esc_html((string)($r['end_datetime'] ?? '')) . '</td>';
      echo '<td style="' . esc_attr($td_style) . '">' . esc_html((string)($r['notes'] ?? '')) . '</td>';
      echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</body></html>';
    exit;
  }

  private function email_status_change(int $booking_id, string $old, string $new) : void {
    $b = POINTLYBOOKING_BookingModel::find($booking_id);
    if (!$b) return;

    $service = POINTLYBOOKING_ServiceModel::find((int)$b['service_id']);
    $customer = $b['customer_id'] ? POINTLYBOOKING_CustomerModel::find((int)$b['customer_id']) : null;

    if ($service && $customer) {
      POINTLYBOOKING_EmailHelper::booking_status_changed_customer($b, $service, $customer, $old, $new);
    }

    POINTLYBOOKING_WebhookHelper::fire('booking_status_changed', [
      'booking_id' => (int)$b['id'],
      'status' => (string)($b['status'] ?? ''),
      'old_status' => (string)$old,
      'service_id' => (int)($b['service_id'] ?? 0),
      'customer_id' => (int)($b['customer_id'] ?? 0),
      'agent_id' => (int)($b['agent_id'] ?? 0),
      'start_datetime' => (string)($b['start_datetime'] ?? ''),
      'end_datetime' => (string)($b['end_datetime'] ?? ''),
    ]);
  }

  public function change_status() : void {
    $this->require_cap('pointlybooking_manage_bookings');
    check_admin_referer('pointlybooking_admin');

    $id = $this->query_absint('id');
    $status = $this->query_key('status');

    if ($id > 0 && $status !== '') {
      POINTLYBOOKING_BookingModel::update_status($id, $status);
    }

    wp_safe_redirect(admin_url('admin.php?page=pointlybooking_bookings&updated=1'));
    exit;
  }
}
