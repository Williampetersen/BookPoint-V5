<?php defined('ABSPATH') || exit; ?>
<?php require_once __DIR__ . '/legacy_shell.php'; ?>
<?php
  $pointlybooking_actions_html = '<a class="bp-top-btn" href="' . esc_url(admin_url('admin.php?page=pointlybooking_promo_codes&action=edit')) . '">' . esc_html__('Add New', 'pointly-booking') . '</a>';
  pointlybooking_render_legacy_shell_start(esc_html__('Promo Codes', 'pointly-booking'), esc_html__('Create and manage discount codes.', 'pointly-booking'), $pointlybooking_actions_html, 'promo');
?>

  <form method="get" style="margin:12px 0;">
    <input type="hidden" name="page" value="pointlybooking_promo_codes">
    <?php wp_nonce_field('pointlybooking_admin_filter', 'pointlybooking_filter_nonce'); ?>
    <input type="text" name="q" value="<?php echo esc_attr($q); ?>" placeholder="<?php echo esc_attr__('Search code...', 'pointly-booking'); ?>">
    <select name="is_active">
      <option value=""><?php echo esc_html__('All', 'pointly-booking'); ?></option>
      <option value="1" <?php selected($is_active, '1'); ?>><?php echo esc_html__('Active', 'pointly-booking'); ?></option>
      <option value="0" <?php selected($is_active, '0'); ?>><?php echo esc_html__('Inactive', 'pointly-booking'); ?></option>
    </select>
    <button class="button"><?php echo esc_html__('Filter', 'pointly-booking'); ?></button>
  </form>

  <table class="widefat striped">
    <thead>
      <tr>
        <th><?php echo esc_html__('Code', 'pointly-booking'); ?></th>
        <th><?php echo esc_html__('Type', 'pointly-booking'); ?></th>
        <th><?php echo esc_html__('Amount', 'pointly-booking'); ?></th>
        <th><?php echo esc_html__('Uses', 'pointly-booking'); ?></th>
        <th><?php echo esc_html__('Active', 'pointly-booking'); ?></th>
        <th style="width:180px;"><?php echo esc_html__('Actions', 'pointly-booking'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($items)): ?>
        <tr><td colspan="6" style="text-align:center;"><?php echo esc_html__('No promo codes yet.', 'pointly-booking'); ?></td></tr>
      <?php else: foreach ($items as $row): ?>
        <tr>
          <td><strong><?php echo esc_html($row['code']); ?></strong></td>
          <td><?php echo esc_html($row['type']); ?></td>
          <td><?php echo esc_html(number_format((float)$row['amount'], 2)); ?></td>
          <td><?php echo esc_html((string)((int)$row['uses_count'])); ?><?php echo isset($row['max_uses']) && $row['max_uses'] !== null ? ' / ' . esc_html((string)(int)$row['max_uses']) : ''; ?></td>
          <td><?php echo ((int)$row['is_active'] === 1) ? esc_html__('Yes', 'pointly-booking') : esc_html__('No', 'pointly-booking'); ?></td>
          <td>
            <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=pointlybooking_promo_codes&action=edit&id='.(int)$row['id'])); ?>">
              <?php echo esc_html__('Edit', 'pointly-booking'); ?>
            </a>
            <a class="button button-small button-link-delete" href="<?php
              echo esc_url(wp_nonce_url(
                admin_url('admin.php?page=pointlybooking_promo_codes&action=delete&id='.(int)$row['id']),
                'pointlybooking_admin'
              ));
            ?>" onclick="return confirm('Delete this promo code?');">
              <?php echo esc_html__('Delete', 'pointly-booking'); ?>
            </a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
<?php pointlybooking_render_legacy_shell_end(); ?>
