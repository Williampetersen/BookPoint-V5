<?php
defined('ABSPATH') || exit;

function bp_err($errors, $k) {
  if (!empty($errors[$k])) {
    echo '<p style="color:#b32d2e;margin:6px 0 0;">' . esc_html($errors[$k]) . '</p>';
  }
}
?>
<div class="wrap">
  <h1><?php echo esc_html__('BookPoint Settings', 'bookpoint'); ?></h1>

  <?php if (isset($_GET['updated'])) : ?>
    <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Settings saved.', 'bookpoint'); ?></p></div>
  <?php endif; ?>

  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('bp_admin'); ?>
    <input type="hidden" name="action" value="bp_admin_settings_save">

    <table class="form-table" role="presentation">
      <tr>
        <th><label for="bp_open"><?php echo esc_html__('Open time', 'bookpoint'); ?></label></th>
        <td>
          <input id="bp_open" type="text" name="open_time" value="<?php echo esc_attr($open); ?>" placeholder="09:00">
          <?php bp_err($errors, 'open_time'); ?>
        </td>
      </tr>

      <tr>
        <th><label for="bp_close"><?php echo esc_html__('Close time', 'bookpoint'); ?></label></th>
        <td>
          <input id="bp_close" type="text" name="close_time" value="<?php echo esc_attr($close); ?>" placeholder="17:00">
          <?php bp_err($errors, 'close_time'); ?>
        </td>
      </tr>

      <tr>
        <th><label for="bp_interval"><?php echo esc_html__('Slot interval (minutes)', 'bookpoint'); ?></label></th>
        <td>
          <input id="bp_interval" type="number" min="5" max="120" name="slot_interval_minutes" value="<?php echo esc_attr((int)$interval); ?>">
          <?php bp_err($errors, 'slot_interval_minutes'); ?>
        </td>
      </tr>

      <tr>
        <th><label for="bp_currency"><?php echo esc_html__('Default currency', 'bookpoint'); ?></label></th>
        <td>
          <input id="bp_currency" type="text" name="default_currency" maxlength="3" value="<?php echo esc_attr($currency); ?>" placeholder="USD">
          <?php bp_err($errors, 'default_currency'); ?>
        </td>
      </tr>
    </table>

    <h2><?php esc_html_e('Email Notifications', 'bookpoint'); ?></h2>
    <table class="form-table" role="presentation">
      <tr>
        <th><?php esc_html_e('Enable Emails', 'bookpoint'); ?></th>
        <td>
          <label>
            <input type="checkbox" name="email_enabled" value="1" <?php checked((int)$email_enabled, 1); ?>>
            <?php esc_html_e('Send email notifications for bookings', 'bookpoint'); ?>
          </label>
        </td>
      </tr>

      <tr>
        <th><label for="bp_admin_email"><?php esc_html_e('Admin email', 'bookpoint'); ?></label></th>
        <td>
          <input id="bp_admin_email" type="email" name="admin_email" value="<?php echo esc_attr($admin_email); ?>" class="regular-text">
          <?php bp_err($errors,'admin_email'); ?>
        </td>
      </tr>

      <tr>
        <th><label for="bp_from_name"><?php esc_html_e('From name', 'bookpoint'); ?></label></th>
        <td>
          <input id="bp_from_name" type="text" name="from_name" value="<?php echo esc_attr($from_name); ?>" class="regular-text">
        </td>
      </tr>

      <tr>
        <th><label for="bp_from_email"><?php esc_html_e('From email', 'bookpoint'); ?></label></th>
        <td>
          <input id="bp_from_email" type="email" name="from_email" value="<?php echo esc_attr($from_email); ?>" class="regular-text">
          <?php bp_err($errors,'from_email'); ?>
        </td>
      </tr>
    </table>

    <h2><?php esc_html_e('Availability & Scheduling', 'bookpoint'); ?></h2>
    <table class="form-table" role="presentation">
      <tr>
        <th><label for="bp_future_days"><?php esc_html_e('Booking limit (days ahead)', 'bookpoint'); ?></label></th>
        <td>
          <input id="bp_future_days" type="number" min="1" max="365" name="future_days_limit" value="<?php echo esc_attr($future_days_limit); ?>">
          <p class="description"><?php esc_html_e('Customers can only book up to this many days in advance', 'bookpoint'); ?></p>
          <?php bp_err($errors,'future_days_limit'); ?>
        </td>
      </tr>

      <tr>
        <th><label for="bp_breaks"><?php esc_html_e('Daily breaks', 'bookpoint'); ?></label></th>
        <td>
          <input id="bp_breaks" type="text" name="breaks" value="<?php echo esc_attr($breaks); ?>" class="regular-text" placeholder="12:00-13:00,15:00-15:15">
          <p class="description"><?php esc_html_e('Comma-separated break times (e.g., 12:00-13:00,15:00-15:15)', 'bookpoint'); ?></p>
          <?php bp_err($errors,'breaks'); ?>
        </td>
      </tr>
    </table>

    <h2><?php esc_html_e('Weekly Schedule', 'bookpoint'); ?></h2>
    <table class="form-table" role="presentation">
      <tr>
        <td colspan="2">
          <table style="width:100%;border-collapse:collapse;">
            <tr style="background:#f1f1f1;">
              <th style="padding:8px;border:1px solid #ddd;text-align:left;"><?php esc_html_e('Day', 'bookpoint'); ?></th>
              <th style="padding:8px;border:1px solid #ddd;text-align:left;"><?php esc_html_e('Hours', 'bookpoint'); ?></th>
            </tr>
            <?php 
              $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
              for ($i = 0; $i < 7; $i++) :
            ?>
            <tr>
              <td style="padding:8px;border:1px solid #ddd;">
                <?php echo esc_html($days[$i]); ?>
              </td>
              <td style="padding:8px;border:1px solid #ddd;">
                <input type="text" name="schedule_<?php echo esc_attr($i); ?>" value="<?php echo esc_attr($schedule[$i]); ?>" placeholder="09:00-17:00" style="width:100%;box-sizing:border-box;">
                <?php bp_err($errors, 'schedule_' . $i); ?>
              </td>
            </tr>
            <?php endfor; ?>
          </table>
          <p class="description" style="margin-top:10px;"><?php esc_html_e('Leave empty for closed days. Format: HH:MM-HH:MM (e.g., 09:00-17:00)', 'bookpoint'); ?></p>
        </td>
      </tr>
    </table>


    <p class="submit">
      <button type="submit" class="button button-primary"><?php echo esc_html__('Save Settings', 'bookpoint'); ?></button>
    </p>
  </form>
</div>
