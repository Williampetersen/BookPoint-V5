<?php
defined('ABSPATH') || exit;
?>
<div class="wrap">
  <h1>
    <?php echo esc_html__('Services', 'bookpoint'); ?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=bp_services_edit')); ?>" class="page-title-action">
      <?php echo esc_html__('Add New', 'bookpoint'); ?>
    </a>
  </h1>

  <?php if (isset($_GET['updated'])) : ?>
    <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Service saved.', 'bookpoint'); ?></p></div>
  <?php endif; ?>
  <?php if (isset($_GET['deleted'])) : ?>
    <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Service deleted.', 'bookpoint'); ?></p></div>
  <?php endif; ?>

  <table class="widefat striped">
    <thead>
      <tr>
        <th><?php echo esc_html__('ID', 'bookpoint'); ?></th>
        <th><?php echo esc_html__('Name', 'bookpoint'); ?></th>
        <th><?php echo esc_html__('Duration', 'bookpoint'); ?></th>
        <th><?php echo esc_html__('Price', 'bookpoint'); ?></th>
        <th><?php echo esc_html__('Active', 'bookpoint'); ?></th>
        <th><?php echo esc_html__('Actions', 'bookpoint'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($services)) : ?>
        <tr><td colspan="6"><?php echo esc_html__('No services yet.', 'bookpoint'); ?></td></tr>
      <?php else : ?>
        <?php foreach ($services as $s) : ?>
          <tr>
            <td><?php echo esc_html($s['id']); ?></td>
            <td><?php echo esc_html($s['name']); ?></td>
            <td><?php echo esc_html((int)$s['duration_minutes']); ?> <?php echo esc_html__('min', 'bookpoint'); ?></td>
            <td><?php echo esc_html($s['currency']); ?> <?php echo esc_html(number_format_i18n(((int)$s['price_cents']) / 100, 2)); ?></td>
            <td><?php echo ((int)$s['is_active'] === 1) ? '✅' : '—'; ?></td>
            <td>
              <a href="<?php echo esc_url(admin_url('admin.php?page=bp_services_edit&id=' . absint($s['id']))); ?>">
                <?php echo esc_html__('Edit', 'bookpoint'); ?>
              </a>
              |
              <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=bp_services_delete&id=' . absint($s['id'])), 'bp_admin')); ?>"
                 onclick="return confirm('<?php echo esc_js(__('Delete this service?', 'bookpoint')); ?>');">
                 <?php echo esc_html__('Delete', 'bookpoint'); ?>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

