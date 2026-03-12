<?php defined('ABSPATH') || exit; ?>

<div class="bp-wrap">
  <div class="bp-card">
    <h2 class="bp-h2"><?php esc_html_e('Your Bookings', 'bookpoint-booking'); ?></h2>

  <?php
  $session = sanitize_text_field((string)($session ?? ''));
  if (!$session || !POINTLYBOOKING_PortalHelper::is_session_valid($email, $session)) {
    echo '<p>' . esc_html__('Session expired. Please request a new code.', 'bookpoint-booking') . '</p>';
    return;
  }

  $items = POINTLYBOOKING_BookingModel::find_by_customer_email($email);
  if (empty($items)) {
    echo '<p>' . esc_html__('No bookings found for this email.', 'bookpoint-booking') . '</p>';
    return;
  }
  ?>

  <table class="bp-table">
    <thead>
      <tr>
        <th><?php esc_html_e('Date', 'bookpoint-booking'); ?></th>
        <th><?php esc_html_e('Service', 'bookpoint-booking'); ?></th>
        <th><?php esc_html_e('Status', 'bookpoint-booking'); ?></th>
        <th><?php esc_html_e('Action', 'bookpoint-booking'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $b) : ?>
        <?php
          $service = POINTLYBOOKING_ServiceModel::find((int)$b['service_id']);
          $svc = (string)($service['name'] ?? 'Service');
          $dt  = (string)($b['start_datetime'] ?? '');
          $st  = (string)($b['status'] ?? 'pending');

          if (empty($b['manage_key'])) {
            $new = POINTLYBOOKING_BookingModel::rotate_manage_token((int)$b['id']);
            if ($new) { $b['manage_key'] = $new; }
          }

          $manage_url = add_query_arg([
            'pointlybooking_manage_booking' => 1,
            'key' => $b['manage_key'],
          ], site_url('/'));
        ?>
        <tr>
          <td><?php echo esc_html($dt); ?></td>
          <td><?php echo esc_html($svc); ?></td>
          <td><span class="bp-badge"><?php echo esc_html($st); ?></span></td>
          <td class="bp-actions"><a class="bp-btn secondary" href="<?php echo esc_url($manage_url); ?>"><?php esc_html_e('Manage', 'bookpoint-booking'); ?></a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

    <p style="margin-top:12px;">
      <a class="bp-btn secondary" href="<?php echo esc_url(remove_query_arg(['step','email','s','bpv','error'])); ?>">
        <?php esc_html_e('Logout', 'bookpoint-booking'); ?>
      </a>
    </p>
  </div>
</div>
