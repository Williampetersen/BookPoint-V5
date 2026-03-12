<?php defined('ABSPATH') || exit; ?>
<?php require_once __DIR__ . '/legacy_shell.php'; ?>
<?php
  $pointlybooking_actions_html = '<a class="bp-top-btn" href="' . esc_url(admin_url('admin.php?page=pointlybooking_categories_edit')) . '">' . esc_html__('Add New', 'bookpoint-booking') . '</a>';
  pointlybooking_render_legacy_shell_start(esc_html__('Categories', 'bookpoint-booking'), esc_html__('Organize your services into categories.', 'bookpoint-booking'), $pointlybooking_actions_html, 'categories');
?>

  <form method="get" style="margin:12px 0;">
    <input type="hidden" name="page" value="pointlybooking_categories">
    <?php wp_nonce_field('pointlybooking_admin_filter', 'pointlybooking_filter_nonce'); ?>
    <input type="text" name="q" value="<?php echo esc_attr($q); ?>" placeholder="<?php echo esc_attr__('Search...', 'bookpoint-booking'); ?>" />
    <select name="is_active">
      <option value=""><?php echo esc_html__('All', 'bookpoint-booking'); ?></option>
      <option value="1" <?php selected($is_active, '1'); ?>><?php echo esc_html__('Active', 'bookpoint-booking'); ?></option>
      <option value="0" <?php selected($is_active, '0'); ?>><?php echo esc_html__('Inactive', 'bookpoint-booking'); ?></option>
    </select>
    <button class="button"><?php echo esc_html__('Filter', 'bookpoint-booking'); ?></button>
  </form>

  <table class="widefat striped">
    <thead>
      <tr>
        <th style="width:60px;"><?php echo esc_html__('Image', 'bookpoint-booking'); ?></th>
        <th><?php echo esc_html__('Name', 'bookpoint-booking'); ?></th>
        <th><?php echo esc_html__('Status', 'bookpoint-booking'); ?></th>
        <th style="width:120px;"><?php echo esc_html__('Sort', 'bookpoint-booking'); ?></th>
        <th style="width:180px;"><?php echo esc_html__('Actions', 'bookpoint-booking'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($items)): ?>
        <tr><td colspan="5" style="text-align:center;"><?php echo esc_html__('No categories yet.', 'bookpoint-booking'); ?></td></tr>
      <?php else: foreach ($items as $row):
        $pointlybooking_img = '';
        $pointlybooking_id = (int) ($row['id'] ?? 0);
        $pointlybooking_edit_action_url = wp_nonce_url(
          admin_url('admin.php?page=pointlybooking_categories&action=edit&id=' . $pointlybooking_id),
          'pointlybooking_edit_category_' . $pointlybooking_id,
          'pointlybooking_edit_nonce'
        );
        $pointlybooking_edit_page_url = wp_nonce_url(
          admin_url('admin.php?page=pointlybooking_categories_edit&id=' . $pointlybooking_id),
          'pointlybooking_edit_category_' . $pointlybooking_id,
          'pointlybooking_edit_nonce'
        );
        if (!empty($row['image_id'])) {
          $pointlybooking_img_url = wp_get_attachment_image_url((int)$row['image_id'], 'thumbnail');
          if ($pointlybooking_img_url) {
            $pointlybooking_img = '<img src="' . esc_url($pointlybooking_img_url) . '" style="width:44px;height:44px;object-fit:cover;border-radius:10px;" />';
          }
        }
        if (!$pointlybooking_img) {
          $pointlybooking_initial = mb_substr((string)($row['name'] ?? '-'), 0, 1);
          $pointlybooking_img = '<div style="width:44px;height:44px;border-radius:10px;background:var(--bp-bg);display:flex;align-items:center;justify-content:center;font-weight:900;color:var(--bp-muted);">' . esc_html($pointlybooking_initial) . '</div>';
        }
        ?>
        <tr>
          <td><?php echo wp_kses_post($pointlybooking_img); ?></td>
          <td>
            <strong>
              <a href="<?php echo esc_url($pointlybooking_edit_action_url); ?>">
                <?php echo esc_html($row['name']); ?>
              </a>
            </strong>
            <div style="color:#666;"><?php echo esc_html(wp_trim_words((string)$row['description'], 14)); ?></div>
          </td>
          <td>
            <?php echo esc_html((int)$row['is_active'] === 1 ? __('Active', 'bookpoint-booking') : __('Inactive', 'bookpoint-booking')); ?>
          </td>
          <td><?php echo esc_html((string) (int) ($row['sort_order'] ?? 0)); ?></td>
          <td>
            <a class="button button-small" href="<?php echo esc_url($pointlybooking_edit_page_url); ?>">
              <?php echo esc_html__('Edit', 'bookpoint-booking'); ?>
            </a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
<?php pointlybooking_render_legacy_shell_end(); ?>
