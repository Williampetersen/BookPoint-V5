<?php defined('ABSPATH') || exit; ?>

<div class="bp-wrap">
  <div class="bp-card">
    <h2 class="bp-h2"><?php esc_html_e('Verify Code', 'bookpoint'); ?></h2>
    <p class="bp-p"><?php echo esc_html(sprintf(__('We sent a code to %s', 'bookpoint'), $email)); ?></p>

    <form method="post" class="bp-row">
      <?php wp_nonce_field('bp_portal_verify'); ?>
      <input type="hidden" name="bp_portal_email" value="<?php echo esc_attr($email); ?>">

      <div class="bp-field">
        <label><?php esc_html_e('6-digit code', 'bookpoint'); ?></label>
        <input class="bp-input" type="text" name="bp_portal_otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
      </div>

      <div class="bp-field" style="justify-content:flex-end;">
        <button class="bp-btn" type="submit" name="bp_portal_action" value="verify_otp">
          <?php esc_html_e('Login', 'bookpoint'); ?>
        </button>
      </div>
    </form>
  </div>
</div>
