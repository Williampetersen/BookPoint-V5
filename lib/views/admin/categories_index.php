<?php defined('ABSPATH') || exit; ?>
<?php require_once __DIR__ . '/legacy_shell.php'; ?>
<?php
  $actions_html = '<a class="bp-top-btn" href="' . esc_url(admin_url('admin.php?page=bp_categories_edit')) . '">' . esc_html__('Add New', 'bookpoint') . '</a>';
  bp_render_legacy_shell_start(esc_html__('Categories', 'bookpoint'), esc_html__('Organize your services into categories.', 'bookpoint'), $actions_html, 'categories');
?>

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
        if (!$img) {
          $initial = mb_substr((string)($row['name'] ?? '—'), 0, 1);
          $img = '<div style="width:44px;height:44px;border-radius:10px;background:var(--bp-bg);display:flex;align-items:center;justify-content:center;font-weight:900;color:var(--bp-muted);">'.esc_html($initial).'</div>';
        }
        ?>
        <tr>
          <td><?php echo $img; ?></td>
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
            <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=bp_categories_edit&id='.(int)$row['id'])); ?>">
              <?php echo esc_html__('Edit', 'bookpoint'); ?>
            </a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
<?php bp_render_legacy_shell_end(); ?>
