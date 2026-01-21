<?php
defined('ABSPATH') || exit;

/** expects:
 * $booking (array|null)
 * $service (array|null)
 * $cancel_url (string)
 * $message (string)
 */
?>
<div class="bp-wrap">
  <div class="bp-card">
    <h2 class="bp-h2"><?php echo esc_html__('Manage Booking', 'bookpoint'); ?></h2>

  <?php if (!empty($message)) : ?>
    <div class="bp-msg ok">
      <?php echo wp_kses_post($message); ?>
    </div>
  <?php endif; ?>

  <?php if (!$booking) : ?>
    <p><?php echo esc_html__('Booking not found.', 'bookpoint'); ?></p>
    <?php return; ?>
  <?php endif; ?>

    <table class="bp-table">
      <tbody>
        <tr><td><strong><?php echo esc_html__('Status:', 'bookpoint'); ?></strong></td><td><span class="bp-badge"><?php echo esc_html($booking['status']); ?></span></td></tr>
        <tr><td><strong><?php echo esc_html__('Service:', 'bookpoint'); ?></strong></td><td><?php echo esc_html($service['name'] ?? '-'); ?></td></tr>
        <tr><td><strong><?php echo esc_html__('Start:', 'bookpoint'); ?></strong></td><td><?php echo esc_html($booking['start_datetime']); ?></td></tr>
        <tr><td><strong><?php echo esc_html__('End:', 'bookpoint'); ?></strong></td><td><?php echo esc_html($booking['end_datetime']); ?></td></tr>
      </tbody>
    </table>

  <?php if (($booking['status'] ?? '') !== 'cancelled') : ?>
    <hr>

    <h3 class="bp-h2" style="font-size:18px; margin-top:16px;"><?php esc_html_e('Reschedule', 'bookpoint'); ?></h3>

    <div class="bp-reschedule"
         data-service-id="<?php echo (int)$booking['service_id']; ?>"
         data-agent-id="<?php echo (int)($booking['agent_id'] ?? 0); ?>"
         data-exclude-booking-id="<?php echo (int)$booking['id']; ?>">
      <div class="bp-row">
        <div class="bp-field">
          <label><?php esc_html_e('New date', 'bookpoint'); ?></label>
          <input type="date" class="bp-input bp-r-date" value="">
        </div>

        <div class="bp-field">
          <label><?php esc_html_e('New time', 'bookpoint'); ?></label>
          <select class="bp-select bp-r-time">
            <option value=""><?php esc_html_e('Select a time', 'bookpoint'); ?></option>
          </select>
        </div>
      </div>

      <form method="post" style="margin-top:12px;">
        <?php wp_nonce_field('BP_manage_booking'); ?>
        <input type="hidden" name="bp_manage_action" value="reschedule">
        <input type="hidden" name="key" value="<?php echo esc_attr($_GET['key'] ?? ''); ?>">
        <input type="hidden" name="bp_new_start" class="bp-new-start" value="">
        <input type="hidden" name="bp_new_end" class="bp-new-end" value="">

        <button type="submit" class="bp-btn" onclick="return confirm('Reschedule this booking?');">
          <?php esc_html_e('Confirm Reschedule', 'bookpoint'); ?>
        </button>
      </form>

      <p class="bp-r-msg bp-p" style="margin-top:10px;"></p>
    </div>
  <?php endif; ?>

  <?php if ($booking['status'] !== 'cancelled') : ?>
    <p class="bp-actions">
      <a class="bp-btn secondary" href="<?php echo esc_url($cancel_url); ?>"
         onclick="return confirm('<?php echo esc_js(__('Cancel this booking?', 'bookpoint')); ?>');">
        <?php echo esc_html__('Cancel Booking', 'bookpoint'); ?>
      </a>
    </p>
  <?php else : ?>
    <p><?php echo esc_html__('This booking is already cancelled.', 'bookpoint'); ?></p>
  <?php endif; ?>
  </div>
</div>

