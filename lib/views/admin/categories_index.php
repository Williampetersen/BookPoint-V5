<?php defined('ABSPATH') || exit; ?>

<div class="wrap">
  <h1 class="wp-heading-inline"><?php echo esc_html__('Categories', 'bookpoint'); ?></h1>
  <a class="page-title-action" href="<?php echo esc_url(admin_url('admin.php?page=bp_categories&action=edit')); ?>">
    <?php echo esc_html__('Add New', 'bookpoint'); ?>
  </a>
  <hr class="wp-header-end">

  <form method="get" style="margin:12px 0;">
    <input type="hidden" name="page" value="bp_categories">
    <input type="text" name="q" value="<?php echo esc_attr($q); ?>" placeholder="<?php echo esc_attr__('Search...', 'bookpoint'); ?>" />
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
        <th style="width:60px;"><?php echo esc_html__('Image', 'bookpoint'); ?></th>
        <th><?php echo esc_html__('Name', 'bookpoint'); ?></th>
        <th><?php echo esc_html__('Status', 'bookpoint'); ?></th>
        <th style="width:120px;"><?php echo esc_html__('Sort', 'bookpoint'); ?></th>
        <th style="width:180px;"><?php echo esc_html__('Actions', 'bookpoint'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($items)): ?>
        <tr><td colspan="5" style="text-align:center;"><?php echo esc_html__('No categories yet.', 'bookpoint'); ?></td></tr>
      <?php else: foreach ($items as $row):
        $img = '';
        if (!empty($row['image_id'])) {
          $url = wp_get_attachment_image_url((int)$row['image_id'], 'thumbnail');
          if ($url) $img = '<img src="'.esc_url($url).'" style="width:44px;height:44px;object-fit:cover;border-radius:10px;" />';
        }
        ?>
        <tr>
          <td><?php echo $img ?: '—'; ?></td>
          <td>
            <strong>
              <a href="<?php echo esc_url(admin_url('admin.php?page=bp_categories&action=edit&id='.(int)$row['id'])); ?>">
                <?php echo esc_html($row['name']); ?>
              </a>
            </strong>
            <div style="color:#666;"><?php echo esc_html(wp_trim_words((string)$row['description'], 14)); ?></div>
          </td>
          <td>
            <?php echo ((int)$row['is_active'] === 1) ? '✅ ' . esc_html__('Active', 'bookpoint') : '— ' . esc_html__('Inactive', 'bookpoint'); ?>
          </td>
          <td><?php echo (int)$row['sort_order']; ?></td>
          <td>
            <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=bp_categories&action=edit&id='.(int)$row['id'])); ?>">
              <?php echo esc_html__('Edit', 'bookpoint'); ?>
            </a>
            <a class="button button-small button-link-delete" href="<?php
              echo esc_url(wp_nonce_url(
                admin_url('admin.php?page=bp_categories&action=delete&id='.(int)$row['id']),
                'bp_admin'
              ));
            ?>" onclick="return confirm('Delete this category?');">
              <?php echo esc_html__('Delete', 'bookpoint'); ?>
            </a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
