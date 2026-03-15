<?php defined('ABSPATH') || exit; ?>

<div class="bp-wrap">
  <div class="bp-card">
    <h2 class="bp-h2"><?php esc_html_e('Verify Code', 'pointly-booking'); ?></h2>
    <p class="bp-p"><?php
      /* translators: %s: Customer email address. */
      /* translators: %s: Customer email address. */
      echo esc_html(sprintf(__('We sent a code to %s', 'pointly-booking'), $email));
    ?></p>

    <form method="post" class="bp-row">
      <?php wp_nonce_field('pointlybooking_portal_verify'); ?>
      <input type="hidden" name="pointlybooking_portal_email" value="<?php echo esc_attr($email); ?>">

      <div class="bp-field">
        <label><?php esc_html_e('6-digit code', 'pointly-booking'); ?></label>
        <input class="bp-input" type="text" name="pointlybooking_portal_otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
      </div>

      <div class="bp-field" style="justify-content:flex-end;">
        <button class="bp-btn" type="submit" name="pointlybooking_portal_action" value="verify_otp">
          <?php esc_html_e('Login', 'pointly-booking'); ?>
        </button>
      </div>
    </form>
  </div>
</div>
