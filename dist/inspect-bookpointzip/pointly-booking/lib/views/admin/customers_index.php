<?php defined('ABSPATH') || exit; ?>
<?php require_once __DIR__ . '/legacy_shell.php'; ?>
<?php
  pointlybooking_render_legacy_shell_start(esc_html__('Customers', 'pointly-booking'), esc_html__('View and manage customer details.', 'pointly-booking'), '', 'customers');
?>

  <table class="widefat striped">
    <thead>
      <tr>
        <th>ID</th>
        <th><?php esc_html_e('Name', 'pointly-booking'); ?></th>
        <th><?php esc_html_e('Email', 'pointly-booking'); ?></th>
        <th><?php esc_html_e('Phone', 'pointly-booking'); ?></th>
        <th><?php esc_html_e('Actions', 'pointly-booking'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($items)) : ?>
        <tr><td colspan="5"><?php esc_html_e('No customers yet.', 'pointly-booking'); ?></td></tr>
      <?php else : foreach ($items as $c) : ?>
        <tr>
          <td><?php echo esc_html($c['id']); ?></td>
          <td><?php echo esc_html(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''))); ?></td>
          <td><?php echo esc_html($c['email'] ?? '-'); ?></td>
          <td><?php echo esc_html($c['phone'] ?? '-'); ?></td>
          <td>
            <a href="<?php echo esc_url(admin_url('admin.php?page=pointlybooking_customers_view&id=' . absint($c['id']))); ?>">
              <?php esc_html_e('View', 'pointly-booking'); ?>
            </a>
            <?php
              $gdpr_url = wp_nonce_url(
                admin_url('admin-post.php?action=pointlybooking_admin_customer_gdpr_delete&id=' . absint($c['id'])),
                'pointlybooking_admin'
              );
            ?>
            | <a href="<?php echo esc_url($gdpr_url); ?>" onclick="return confirm('<?php echo esc_js(__('Anonymize this customer?', 'pointly-booking')); ?>');">
              <?php esc_html_e('GDPR Delete', 'pointly-booking'); ?>
            </a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
<?php pointlybooking_render_legacy_shell_end(); ?>
