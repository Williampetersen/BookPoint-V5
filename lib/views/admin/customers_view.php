<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
defined('ABSPATH') || exit; ?>

<div class="wrap">
  <h1><?php esc_html_e('Customer Details', 'pointly-booking'); ?></h1>

  <p><strong><?php esc_html_e('Name:', 'pointly-booking'); ?></strong>
    <?php echo esc_html(trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''))); ?>
  </p>

  <p><strong><?php esc_html_e('Email:', 'pointly-booking'); ?></strong>
    <?php echo esc_html($customer['email'] ?? '-'); ?>
  </p>

  <p><strong><?php esc_html_e('Phone:', 'pointly-booking'); ?></strong>
    <?php echo esc_html($customer['phone'] ?? '-'); ?>
  </p>

  <h2><?php esc_html_e('Bookings', 'pointly-booking'); ?></h2>

  <?php if (empty($bookings)) : ?>
    <p><?php esc_html_e('No bookings for this customer.', 'pointly-booking'); ?></p>
  <?php else : ?>
    <ul>
      <?php foreach ($bookings as $b) : ?>
        <li>
          #<?php echo esc_html($b['id']); ?> -
          <?php echo esc_html($b['start_datetime']); ?> to
          <?php echo esc_html($b['end_datetime']); ?> -
          <?php echo esc_html($b['status']); ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <p>
    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=pointlybooking_customers')); ?>">
      <?php esc_html_e('Back to Customers', 'pointly-booking'); ?>
    </a>
  </p>
</div>
