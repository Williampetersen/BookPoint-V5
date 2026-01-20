<?php
defined('ABSPATH') || exit;

function bp_customer_name($row) {
  $name = trim(($row['customer_first_name'] ?? '') . ' ' . ($row['customer_last_name'] ?? ''));
  return $name !== '' ? $name : __('(No name)', 'bookpoint');
}
?>
<div class="wrap">
  <h1><?php echo esc_html__('Bookings', 'bookpoint'); ?></h1>

  <?php if (isset($_GET['updated'])) : ?>
    <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Booking updated.', 'bookpoint'); ?></p></div>
  <?php endif; ?>

  <table class="widefat striped">
    <thead>
      <tr>
        <th><?php echo esc_html__('ID', 'bookpoint'); ?></th>
        <th><?php echo esc_html__('Service', 'bookpoint'); ?></th>
        <th><?php echo esc_html__('Customer', 'bookpoint'); ?></th>
        <th><?php echo esc_html__('Email', 'bookpoint'); ?></th>
        <th><?php echo esc_html__('Start', 'bookpoint'); ?></th>
        <th><?php echo esc_html__('End', 'bookpoint'); ?></th>
        <th><?php echo esc_html__('Status', 'bookpoint'); ?></th>
        <th><?php echo esc_html__('Actions', 'bookpoint'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($items)) : ?>
        <tr><td colspan="8"><?php echo esc_html__('No bookings yet.', 'bookpoint'); ?></td></tr>
      <?php else : ?>
        <?php foreach ($items as $b) : ?>
          <tr>
            <td><?php echo esc_html($b['id']); ?></td>
            <td><?php echo esc_html($b['service_name'] ?? '-'); ?></td>
            <td><?php echo esc_html(bp_customer_name($b)); ?></td>
            <td><?php echo esc_html($b['customer_email'] ?? '-'); ?></td>
            <td><?php echo esc_html($b['start_datetime']); ?></td>
            <td><?php echo esc_html($b['end_datetime']); ?></td>
            <td><?php echo esc_html($b['status']); ?></td>
            <td>
              <?php
                $base = admin_url('admin.php?page=bp_bookings_change_status&id=' . absint($b['id']) . '&status=');
                $confirm_url = wp_nonce_url($base . 'confirmed', 'bp_admin');
                $cancel_url  = wp_nonce_url($base . 'cancelled', 'bp_admin');
              ?>
              <a href="<?php echo esc_url($confirm_url); ?>"><?php echo esc_html__('Confirm', 'bookpoint'); ?></a>
              |
              <a href="<?php echo esc_url($cancel_url); ?>"
                 onclick="return confirm('<?php echo esc_js(__('Cancel this booking?', 'bookpoint')); ?>');">
                <?php echo esc_html__('Cancel', 'bookpoint'); ?>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
