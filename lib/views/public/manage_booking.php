<?php
defined('ABSPATH') || exit;

/** expects:
 * $booking (array|null)
 * $service (array|null)
 * $cancel_url (string)
 * $message (string)
 */
?>
<div class="bpv5-manage-booking">
  <h2><?php echo esc_html__('Manage Booking', 'bookpoint'); ?></h2>

  <?php if (!empty($message)) : ?>
    <div style="padding:10px;border:1px solid #ccc;margin:10px 0;">
      <?php echo wp_kses_post($message); ?>
    </div>
  <?php endif; ?>

  <?php if (!$booking) : ?>
    <p><?php echo esc_html__('Booking not found.', 'bookpoint'); ?></p>
    <?php return; ?>
  <?php endif; ?>

  <ul>
    <li><strong><?php echo esc_html__('Status:', 'bookpoint'); ?></strong> <?php echo esc_html($booking['status']); ?></li>
    <li><strong><?php echo esc_html__('Service:', 'bookpoint'); ?></strong> <?php echo esc_html($service['name'] ?? '-'); ?></li>
    <li><strong><?php echo esc_html__('Start:', 'bookpoint'); ?></strong> <?php echo esc_html($booking['start_datetime']); ?></li>
    <li><strong><?php echo esc_html__('End:', 'bookpoint'); ?></strong> <?php echo esc_html($booking['end_datetime']); ?></li>
  </ul>

  <?php if ($booking['status'] !== 'cancelled') : ?>
    <p>
      <a class="button" href="<?php echo esc_url($cancel_url); ?>"
         onclick="return confirm('<?php echo esc_js(__('Cancel this booking?', 'bookpoint')); ?>');">
        <?php echo esc_html__('Cancel Booking', 'bookpoint'); ?>
      </a>
    </p>
  <?php else : ?>
    <p><?php echo esc_html__('This booking is already cancelled.', 'bookpoint'); ?></p>
  <?php endif; ?>
</div>

