<?php defined('ABSPATH') || exit; ?>

<div class="bp-wrap">
  <div class="bp-card">
    <h2 class="bp-h2"><?php esc_html_e('Your Bookings', 'bookpoint'); ?></h2>

  <?php
  $session = sanitize_text_field($_GET['s'] ?? '');
  if (!$session || !BP_PortalHelper::is_session_valid($email, $session)) {
    echo '<p>' . esc_html__('Session expired. Please request a new code.', 'bookpoint') . '</p>';
    return;
  }

  $items = BP_BookingModel::find_by_customer_email($email);
  if (empty($items)) {
    echo '<p>' . esc_html__('No bookings found for this email.', 'bookpoint') . '</p>';
    return;
  }
  ?>

  <table class="bp-table">
    <thead>
      <tr>
        <th><?php esc_html_e('Date', 'bookpoint'); ?></th>
        <th><?php esc_html_e('Service', 'bookpoint'); ?></th>
        <th><?php esc_html_e('Status', 'bookpoint'); ?></th>
        <th><?php esc_html_e('Action', 'bookpoint'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $b) : ?>
        <?php
          $service = BP_ServiceModel::find((int)$b['service_id']);
          $svc = esc_html($service['name'] ?? 'Service');
          $dt  = esc_html($b['start_datetime'] ?? '');
          $st  = esc_html($b['status'] ?? 'pending');

          if (empty($b['manage_key'])) {
            $new = BP_BookingModel::rotate_manage_token((int)$b['id']);
            if ($new) { $b['manage_key'] = $new; }
          }

          $manage_url = add_query_arg([
            'bp_manage_booking' => 1,
            'key' => $b['manage_key'],
          ], site_url('/'));
        ?>
        <tr>
          <td><?php echo $dt; ?></td>
          <td><?php echo $svc; ?></td>
          <td><span class="bp-badge"><?php echo $st; ?></span></td>
          <td class="bp-actions"><a class="bp-btn secondary" href="<?php echo esc_url($manage_url); ?>"><?php esc_html_e('Manage', 'bookpoint'); ?></a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

    <p style="margin-top:12px;">
      <a class="bp-btn secondary" href="<?php echo esc_url(remove_query_arg(['step','email','s'], site_url('/my-bookings/'))); ?>">
        <?php esc_html_e('Logout', 'bookpoint'); ?>
      </a>
    </p>
  </div>
</div>
