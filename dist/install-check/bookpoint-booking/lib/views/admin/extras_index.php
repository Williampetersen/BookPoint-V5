<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
defined('ABSPATH') || exit; ?>
<?php require_once __DIR__ . '/legacy_shell.php'; ?>
<?php
  $pointlybooking_actions_html = '<a class="bp-top-btn" href="' . esc_url(admin_url('admin.php?page=pointlybooking_extras&action=edit')) . '">' . esc_html__('Add New', 'bookpoint-booking') . '</a>';
  pointlybooking_render_legacy_shell_start(esc_html__('Service Extras', 'bookpoint-booking'), esc_html__('Manage add-ons attached to services.', 'bookpoint-booking'), $pointlybooking_actions_html, 'extras');
?>

  <form method="get" style="margin:12px 0;">
    <input type="hidden" name="page" value="pointlybooking_extras">
    <?php wp_nonce_field('pointlybooking_admin_filter', 'pointlybooking_filter_nonce'); ?>
    <input type="text" name="q" value="<?php echo esc_attr($q); ?>" placeholder="<?php echo esc_attr__('Search...', 'bookpoint-booking'); ?>" />

    <select name="service_id">
      <option value="0"><?php echo esc_html__('All Services', 'bookpoint-booking'); ?></option>
      <?php foreach ($services as $s): ?>
        <option value="<?php echo esc_attr((string) (int) ($s['id'] ?? 0)); ?>" <?php selected($service_id, (int)$s['id']); ?>>
          <?php echo esc_html($s['name']); ?>
        </option>
      <?php endforeach; ?>
    </select>

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
        <th><?php echo esc_html__('Extra', 'bookpoint-booking'); ?></th>
        <th><?php echo esc_html__('Service', 'bookpoint-booking'); ?></th>
        <th style="width:120px;"><?php echo esc_html__('Price', 'bookpoint-booking'); ?></th>
        <th style="width:110px;"><?php echo esc_html__('Duration', 'bookpoint-booking'); ?></th>
        <th style="width:110px;"><?php echo esc_html__('Status', 'bookpoint-booking'); ?></th>
        <th style="width:180px;"><?php echo esc_html__('Actions', 'bookpoint-booking'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($items)): ?>
        <tr><td colspan="7" style="text-align:center;"><?php echo esc_html__('No extras yet.', 'bookpoint-booking'); ?></td></tr>
      <?php else: foreach ($items as $row):
        $pointlybooking_img = '<span class="bp-muted">-</span>';
        $pointlybooking_id = (int) ($row['id'] ?? 0);
        $pointlybooking_edit_url = wp_nonce_url(
          admin_url('admin.php?page=pointlybooking_extras&action=edit&id=' . $pointlybooking_id),
          'pointlybooking_edit_extra_' . $pointlybooking_id,
          'pointlybooking_edit_nonce'
        );
        if (!empty($row['image_id'])) {
          $pointlybooking_img_url = wp_get_attachment_image_url((int)$row['image_id'], 'thumbnail');
          if ($pointlybooking_img_url) {
            $pointlybooking_img = '<img src="' . esc_url($pointlybooking_img_url) . '" style="width:44px;height:44px;object-fit:cover;border-radius:10px;" />';
          }
        }
        ?>
        <tr>
          <td><?php echo wp_kses_post($pointlybooking_img); ?></td>
          <td>
            <strong>
              <a href="<?php echo esc_url($pointlybooking_edit_url); ?>">
                <?php echo esc_html($row['name']); ?>
              </a>
            </strong>
            <div style="color:#666;"><?php echo esc_html(wp_trim_words((string)$row['description'], 14)); ?></div>
          </td>
          <td><?php echo esc_html($row['service_name'] ?? '-'); ?></td>
          <td><?php echo esc_html(number_format((float)$row['price'], 2)); ?></td>
          <td><?php echo !empty($row['duration_min']) ? esc_html((string)(int)$row['duration_min'].' min') : esc_html('-'); ?></td>
          <td><?php echo esc_html((int)$row['is_active'] === 1 ? __('Active', 'bookpoint-booking') : __('Inactive', 'bookpoint-booking')); ?></td>
          <td>
            <a class="button button-small" href="<?php echo esc_url($pointlybooking_edit_url); ?>">
              <?php echo esc_html__('Edit', 'bookpoint-booking'); ?>
            </a>
            <a class="button button-small button-link-delete" href="<?php
              echo esc_url(wp_nonce_url(
                admin_url('admin.php?page=pointlybooking_extras&action=delete&id='.(int)$row['id']),
                'pointlybooking_admin'
              ));
            ?>" onclick="return confirm('Delete this extra?');">
              <?php echo esc_html__('Delete', 'bookpoint-booking'); ?>
            </a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
<?php pointlybooking_render_legacy_shell_end(); ?>
