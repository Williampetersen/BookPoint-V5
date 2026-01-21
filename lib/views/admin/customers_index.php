<?php defined('ABSPATH') || exit; ?>

<div class="wrap">
  <h1><?php esc_html_e('Customers', 'bookpoint'); ?></h1>

  <table class="widefat striped">
    <thead>
      <tr>
        <th>ID</th>
        <th><?php esc_html_e('Name', 'bookpoint'); ?></th>
        <th><?php esc_html_e('Email', 'bookpoint'); ?></th>
        <th><?php esc_html_e('Phone', 'bookpoint'); ?></th>
        <th><?php esc_html_e('Actions', 'bookpoint'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($items)) : ?>
        <tr><td colspan="5"><?php esc_html_e('No customers yet.', 'bookpoint'); ?></td></tr>
      <?php else : foreach ($items as $c) : ?>
        <tr>
          <td><?php echo esc_html($c['id']); ?></td>
          <td><?php echo esc_html(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''))); ?></td>
          <td><?php echo esc_html($c['email'] ?? '-'); ?></td>
          <td><?php echo esc_html($c['phone'] ?? '-'); ?></td>
          <td>
            <a href="<?php echo esc_url(admin_url('admin.php?page=bp_customers_view&id=' . absint($c['id']))); ?>">
              <?php esc_html_e('View', 'bookpoint'); ?>
            </a>
            <?php
              $gdpr_url = wp_nonce_url(
                admin_url('admin-post.php?action=bp_admin_customer_gdpr_delete&id=' . absint($c['id'])),
                'bp_admin'
              );
            ?>
            | <a href="<?php echo esc_url($gdpr_url); ?>" onclick="return confirm('<?php echo esc_js(__('Anonymize this customer?', 'bookpoint')); ?>');">
              <?php esc_html_e('GDPR Delete', 'bookpoint'); ?>
            </a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
