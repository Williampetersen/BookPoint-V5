<?php
defined('ABSPATH') || exit;
?>
<div class="wrap">
  <h1><?php echo esc_html($title ?? 'BookPoint'); ?></h1>

  <p><?php echo esc_html__('✅ BookPoint is installed and working.', 'bookpoint'); ?></p>

  <h2><?php echo esc_html__('Next steps', 'bookpoint'); ?></h2>
  <ol>
    <li><?php echo esc_html__('Add Services CRUD', 'bookpoint'); ?></li>
    <li><?php echo esc_html__('Add Booking form shortcode + slots', 'bookpoint'); ?></li>
    <li><?php echo esc_html__('Add Gutenberg block', 'bookpoint'); ?></li>
  </ol>
</div>

