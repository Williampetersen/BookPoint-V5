<?php
defined('ABSPATH') || exit;

// expects: $service_id, $nonce, $options (optional)
$options = $options ?? [];
$default_date = $options['default_date'] ?? '';
$hide_notes = !empty($options['hide_notes']);
$require_phone = !empty($options['require_phone']);
$compact = !empty($options['compact']);
$allow_service_select = !empty($options['allow_service_select']);
?>
<div class="bp-book-form <?php echo esc_attr($compact ? 'bp-compact' : ''); ?>" data-service-id="<?php echo esc_attr($service_id); ?>" data-require-phone="<?php echo esc_attr($require_phone ? '1' : '0'); ?>">
  <div class="bp-message" style="margin:10px 0;"></div>

  <input type="hidden" class="bp-nonce" value="<?php echo esc_attr($nonce); ?>">
  <input type="text" name="pointlybooking_hp" class="bp-hp" value="" style="display:none" autocomplete="off">

  <?php if ($allow_service_select) : ?>
  <p>
    <label><?php echo esc_html__('Service', 'pointly-booking'); ?></label><br>
    <select class="bp-service">
      <option value="0"><?php echo esc_html__('Select a service', 'pointly-booking'); ?></option>
    </select>
  </p>
  <?php endif; ?>

  <p>
    <label><?php echo esc_html__('Date', 'pointly-booking'); ?></label><br>
    <input type="date" class="bp-date" value="<?php echo esc_attr($default_date); ?>">
  </p>

  <p>
    <label><?php echo esc_html__('Agent', 'pointly-booking'); ?></label><br>
    <select class="bp-agent">
      <option value="0"><?php echo esc_html__('Any agent', 'pointly-booking'); ?></option>
    </select>
  </p>

  <p>
    <label><?php echo esc_html__('Time', 'pointly-booking'); ?></label><br>
    <select class="bp-time">
      <option value=""><?php echo esc_html__('Select a date first', 'pointly-booking'); ?></option>
    </select>
  </p>

  <p>
    <label><?php echo esc_html__('First name', 'pointly-booking'); ?></label><br>
    <input type="text" class="bp-first-name">
  </p>

  <p>
    <label><?php echo esc_html__('Last name', 'pointly-booking'); ?></label><br>
    <input type="text" class="bp-last-name">
  </p>

  <p>
    <label><?php echo esc_html__('Email', 'pointly-booking'); ?></label><br>
    <input type="email" class="bp-email">
  </p>

  <p>
    <label><?php echo esc_html__('Phone', 'pointly-booking'); ?></label><br>
    <input type="text" class="bp-phone" <?php echo esc_attr($require_phone ? 'required' : ''); ?>>
  </p>

  <?php if (!$hide_notes) : ?>
  <p>
    <label><?php echo esc_html__('Notes', 'pointly-booking'); ?></label><br>
    <textarea class="bp-notes" rows="3"></textarea>
  </p>
  <?php endif; ?>

  <p>
    <button type="button" class="bp-submit button">
      <?php echo esc_html__('Book now', 'pointly-booking'); ?>
    </button>
  </p>
</div>
