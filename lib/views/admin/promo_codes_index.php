<?php defined('ABSPATH') || exit; ?>
<div class="wrap">
  <h1 class="wp-heading-inline"><?php echo esc_html__('Promo Codes', 'bookpoint'); ?></h1>
  <a class="page-title-action" href="<?php echo esc_url(admin_url('admin.php?page=bp_promo_codes&action=edit')); ?>">
    <?php echo esc_html__('Add New', 'bookpoint'); ?>
  </a>
  <hr class="wp-header-end">

  <form method="get" style="margin:12px 0;">
    <input type="hidden" name="page" value="bp_promo_codes">
    <input type="text" name="q" value="<?php echo esc_attr($q); ?>" placeholder="<?php echo esc_attr__('Search code...', 'bookpoint'); ?>">
    <select name="is_active">
      <option value=""><?php echo esc_html__('All', 'bookpoint'); ?></option>
      <option value="1" <?php selected($is_active, '1'); ?>><?php echo esc_html__('Active', 'bookpoint'); ?></option>
      <option value="0" <?php selected($is_active, '0'); ?>><?php echo esc_html__('Inactive', 'bookpoint'); ?></option>
    </select>
    <button class="button"><?php echo esc_html__('Filter', 'bookpoint'); ?></button>
  </form>

  <table class="widefat striped">
    <thead>
      <tr>
        <th><?php echo esc_html__('Code', 'bookpoint'); ?></th>
        <th><?php echo esc_html__('Type', 'bookpoint'); ?></th>
        <th><?php echo esc_html__('Amount', 'bookpoint'); ?></th>
        <th><?php echo esc_html__('Uses', 'bookpoint'); ?></th>
        <th><?php echo esc_html__('Active', 'bookpoint'); ?></th>
        <th style="width:180px;"><?php echo esc_html__('Actions', 'bookpoint'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($items)): ?>
        <tr><td colspan="6" style="text-align:center;"><?php echo esc_html__('No promo codes yet.', 'bookpoint'); ?></td></tr>
      <?php else: foreach ($items as $row): ?>
        <tr>
          <td><strong><?php echo esc_html($row['code']); ?></strong></td>
          <td><?php echo esc_html($row['type']); ?></td>
          <td><?php echo esc_html(number_format((float)$row['amount'], 2)); ?></td>
          <td><?php echo esc_html((string)((int)$row['uses_count'])); ?><?php echo isset($row['max_uses']) && $row['max_uses'] !== null ? ' / ' . esc_html((string)(int)$row['max_uses']) : ''; ?></td>
          <td><?php echo ((int)$row['is_active'] === 1) ? '✅' : '—'; ?></td>
          <td>
            <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=bp_promo_codes&action=edit&id='.(int)$row['id'])); ?>">
              <?php echo esc_html__('Edit', 'bookpoint'); ?>
            </a>
            <a class="button button-small button-link-delete" href="<?php
              echo esc_url(wp_nonce_url(
                admin_url('admin.php?page=bp_promo_codes&action=delete&id='.(int)$row['id']),
                'bp_admin'
              ));
            ?>" onclick="return confirm('Delete this promo code?');">
              <?php echo esc_html__('Delete', 'bookpoint'); ?>
            </a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
