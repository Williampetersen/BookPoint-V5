<?php defined('ABSPATH') || exit; ?>

<div class="bp-wrap">
  <div class="bp-card">
    <h2 class="bp-h2"><?php esc_html_e('My Bookings', 'bookpoint'); ?></h2>
    <p class="bp-p"><?php esc_html_e('Enter your email to receive a login code.', 'bookpoint'); ?></p>

    <form method="post" class="bp-row">
      <?php wp_nonce_field('bp_portal_email'); ?>
      <div class="bp-field">
        <label><?php esc_html_e('Email', 'bookpoint'); ?></label>
        <input class="bp-input" type="email" name="bp_portal_email" required>
      </div>
      <div class="bp-field" style="justify-content:flex-end;">
        <button class="bp-btn" type="submit" name="bp_portal_action" value="send_otp">
          <?php esc_html_e('Send Code', 'bookpoint'); ?>
        </button>
      </div>
    </form>
  </div>
</div>
