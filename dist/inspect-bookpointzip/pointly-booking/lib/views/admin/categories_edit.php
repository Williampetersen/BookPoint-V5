<?php
defined('ABSPATH') || exit;
require_once __DIR__ . '/legacy_shell.php';

$id = (int)($item['id'] ?? 0);
$name = (string)($item['name'] ?? '');
$description = (string)($item['description'] ?? '');
$image_id = (int)($item['image_id'] ?? 0);
$sort_order = (int)($item['sort_order'] ?? 0);
$is_active = isset($item['is_active']) ? (int)$item['is_active'] : 1;

$image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';

$pointlybooking_actions_html = '<a class="bp-top-btn" href="' . esc_url(admin_url('admin.php?page=pointlybooking_categories')) . '">' . esc_html__('Back to Categories', 'pointly-booking') . '</a>';
pointlybooking_render_legacy_shell_start(
  $id ? esc_html__('Edit Category', 'pointly-booking') : esc_html__('Add Category', 'pointly-booking'),
  esc_html__('Create and organize service categories.', 'pointly-booking'),
  $pointlybooking_actions_html,
  'categories'
);
?>

  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('pointlybooking_admin'); ?>
    <input type="hidden" name="action" value="pointlybooking_admin_categories_save">
    <input type="hidden" name="id" value="<?php echo esc_attr((string)$id); ?>">

    <table class="form-table">
      <tr>
        <th><label><?php echo esc_html__('Name', 'pointly-booking'); ?></label></th>
        <td><input type="text" name="name" value="<?php echo esc_attr($name); ?>" class="regular-text" required></td>
      </tr>

      <tr>
        <th><label><?php echo esc_html__('Description', 'pointly-booking'); ?></label></th>
        <td><textarea name="description" class="large-text" rows="4"><?php echo esc_textarea($description); ?></textarea></td>
      </tr>

      <tr>
        <th><label><?php echo esc_html__('Image', 'pointly-booking'); ?></label></th>
        <td>
          <input type="hidden" name="image_id" id="pointlybooking_image_id" value="<?php echo esc_attr((string)$image_id); ?>">
          <div id="pointlybooking_image_preview" style="margin-bottom:10px;">
            <?php if ($image_url): ?>
              <img src="<?php echo esc_url($image_url); ?>" style="width:140px;height:140px;object-fit:cover;border-radius:14px;border:1px solid #ddd;">
            <?php else: ?>
              <div style="width:140px;height:140px;border-radius:14px;border:1px dashed #ccc;display:flex;align-items:center;justify-content:center;color:#777;">
                <?php echo esc_html__('No image', 'pointly-booking'); ?>
              </div>
            <?php endif; ?>
          </div>

          <button type="button" class="button" id="pointlybooking_pick_image"><?php echo esc_html__('Choose Image', 'pointly-booking'); ?></button>
          <button type="button" class="button" id="pointlybooking_remove_image"><?php echo esc_html__('Remove', 'pointly-booking'); ?></button>
          <p class="description"><?php echo esc_html__('Uses Media Library. Stores attachment ID.', 'pointly-booking'); ?></p>
        </td>
      </tr>

      <tr>
        <th><label><?php echo esc_html__('Sort Order', 'pointly-booking'); ?></label></th>
        <td><input type="number" name="sort_order" value="<?php echo esc_attr((string)$sort_order); ?>" class="small-text"></td>
      </tr>

      <tr>
        <th><label><?php echo esc_html__('Active', 'pointly-booking'); ?></label></th>
        <td><label><input type="checkbox" name="is_active" value="1" <?php checked($is_active, 1); ?>> <?php echo esc_html__('Enabled', 'pointly-booking'); ?></label></td>
      </tr>
    </table>

    <p class="submit">
      <button type="submit" class="bp-btn bp-btn-primary">
        <?php echo esc_html($id ? __('Save Changes', 'pointly-booking') : __('Create Category', 'pointly-booking')); ?>
      </button>
      <a class="bp-btn" href="<?php echo esc_url(admin_url('admin.php?page=pointlybooking_categories')); ?>">
        <?php echo esc_html__('Back', 'pointly-booking'); ?>
      </a>
      <?php if ($id): ?>
        <a class="bp-btn bp-btn-danger" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=pointlybooking_categories_delete&id=' . absint($id)), 'pointlybooking_admin')); ?>" onclick="return confirm('<?php echo esc_js(__('Delete category?', 'pointly-booking')); ?>');">
          <?php echo esc_html__('Delete', 'pointly-booking'); ?>
        </a>
      <?php endif; ?>
    </p>
  </form>

<?php pointlybooking_render_legacy_shell_end(); ?>
