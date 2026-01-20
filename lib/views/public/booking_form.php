<?php
defined('ABSPATH') || exit;

// expects: $service_id, $nonce, $options (optional)
$options = $options ?? [];
$default_date = $options['default_date'] ?? '';
$hide_notes = !empty($options['hide_notes']);
$require_phone = !empty($options['require_phone']);
$compact = !empty($options['compact']);
?>
<div class="bp-book-form <?php echo $compact ? 'bp-compact' : ''; ?>" data-service-id="<?php echo esc_attr($service_id); ?>" data-require-phone="<?php echo $require_phone ? '1' : '0'; ?>">
  <div class="bp-message" style="margin:10px 0;"></div>

  <input type="hidden" class="bp-nonce" value="<?php echo esc_attr($nonce); ?>">
  <input type="text" name="bp_hp" class="bp-hp" value="" style="display:none" autocomplete="off">

  <p>
    <label><?php echo esc_html__('Date', 'bookpoint'); ?></label><br>
    <input type="date" class="bp-date" value="<?php echo esc_attr($default_date); ?>">
  </p>

  <p>
    <label><?php echo esc_html__('Agent', 'bookpoint'); ?></label><br>
    <select class="bp-agent">
      <option value="0"><?php echo esc_html__('Any agent', 'bookpoint'); ?></option>
    </select>
  </p>

  <p>
    <label><?php echo esc_html__('Time', 'bookpoint'); ?></label><br>
    <select class="bp-time">
      <option value=""><?php echo esc_html__('Select a date first', 'bookpoint'); ?></option>
    </select>
  </p>

  <p>
    <label><?php echo esc_html__('First name', 'bookpoint'); ?></label><br>
    <input type="text" class="bp-first-name">
  </p>

  <p>
    <label><?php echo esc_html__('Last name', 'bookpoint'); ?></label><br>
    <input type="text" class="bp-last-name">
  </p>

  <p>
    <label><?php echo esc_html__('Email', 'bookpoint'); ?></label><br>
    <input type="email" class="bp-email">
  </p>

  <p>
    <label><?php echo esc_html__('Phone', 'bookpoint'); ?></label><br>
    <input type="text" class="bp-phone" <?php echo $require_phone ? 'required' : ''; ?>>
  </p>

  <?php if (!$hide_notes) : ?>
  <p>
    <label><?php echo esc_html__('Notes', 'bookpoint'); ?></label><br>
    <textarea class="bp-notes" rows="3"></textarea>
  </p>
  <?php endif; ?>

  <p>
    <button type="button" class="bp-submit button">
      <?php echo esc_html__('Book now', 'bookpoint'); ?>
    </button>
  </p>
</div>

