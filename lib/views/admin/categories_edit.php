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

$actions_html = '<a class="bp-top-btn" href="' . esc_url(admin_url('admin.php?page=bp_categories')) . '">' . esc_html__('Back to Categories', 'bookpoint') . '</a>';
bp_render_legacy_shell_start(
  $id ? esc_html__('Edit Category', 'bookpoint') : esc_html__('Add Category', 'bookpoint'),
  esc_html__('Create and organize service categories.', 'bookpoint'),
  $actions_html,
  'categories'
);
?>

  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('bp_admin'); ?>
    <input type="hidden" name="action" value="bp_admin_categories_save">
    <input type="hidden" name="id" value="<?php echo esc_attr((string)$id); ?>">

    <table class="form-table">
      <tr>
        <th><label><?php echo esc_html__('Name', 'bookpoint'); ?></label></th>
        <td><input type="text" name="name" value="<?php echo esc_attr($name); ?>" class="regular-text" required></td>
      </tr>

      <tr>
        <th><label><?php echo esc_html__('Description', 'bookpoint'); ?></label></th>
        <td><textarea name="description" class="large-text" rows="4"><?php echo esc_textarea($description); ?></textarea></td>
      </tr>

      <tr>
        <th><label><?php echo esc_html__('Image', 'bookpoint'); ?></label></th>
        <td>
          <input type="hidden" name="image_id" id="bp_image_id" value="<?php echo esc_attr((string)$image_id); ?>">
          <div id="bp_image_preview" style="margin-bottom:10px;">
            <?php if ($image_url): ?>
              <img src="<?php echo esc_url($image_url); ?>" style="width:140px;height:140px;object-fit:cover;border-radius:14px;border:1px solid #ddd;">
            <?php else: ?>
              <div style="width:140px;height:140px;border-radius:14px;border:1px dashed #ccc;display:flex;align-items:center;justify-content:center;color:#777;">
                <?php echo esc_html__('No image', 'bookpoint'); ?>
              </div>
            <?php endif; ?>
          </div>

          <button type="button" class="button" id="bp_pick_image"><?php echo esc_html__('Choose Image', 'bookpoint'); ?></button>
          <button type="button" class="button" id="bp_remove_image"><?php echo esc_html__('Remove', 'bookpoint'); ?></button>
          <p class="description"><?php echo esc_html__('Uses Media Library. Stores attachment ID.', 'bookpoint'); ?></p>
        </td>
      </tr>

      <tr>
        <th><label><?php echo esc_html__('Sort Order', 'bookpoint'); ?></label></th>
        <td><input type="number" name="sort_order" value="<?php echo esc_attr((string)$sort_order); ?>" class="small-text"></td>
      </tr>

      <tr>
        <th><label><?php echo esc_html__('Active', 'bookpoint'); ?></label></th>
        <td><label><input type="checkbox" name="is_active" value="1" <?php checked($is_active, 1); ?>> <?php echo esc_html__('Enabled', 'bookpoint'); ?></label></td>
      </tr>
    </table>

    <p class="submit">
      <button type="submit" class="bp-btn bp-btn-primary">
        <?php echo $id ? esc_html__('Save Changes', 'bookpoint') : esc_html__('Create Category', 'bookpoint'); ?>
      </button>
      <a class="bp-btn" href="<?php echo esc_url(admin_url('admin.php?page=bp_categories')); ?>">
        <?php echo esc_html__('Back', 'bookpoint'); ?>
      </a>
    </p>
  </form>

<?php bp_render_legacy_shell_end(); ?>
