<?php defined('ABSPATH') || exit;

$id = (int)($item['id'] ?? 0);
$service_id = (int)($item['service_id'] ?? 0);
$name = (string)($item['name'] ?? '');
$description = (string)($item['description'] ?? '');
$price = (string)($item['price'] ?? '0');
$duration_min = isset($item['duration_min']) ? (string)$item['duration_min'] : '';
$image_id = (int)($item['image_id'] ?? 0);
$sort_order = (int)($item['sort_order'] ?? 0);
$is_active = isset($item['is_active']) ? (int)$item['is_active'] : 1;

$image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';

$selected_service_ids = $id ? BP_ServiceExtraModel::get_service_ids($id) : [];
$services = $services ?? BP_ServiceModel::all(['is_active' => 1]);
?>

<div class="wrap">
  <h1><?php echo $id ? esc_html__('Edit Extra', 'bookpoint') : esc_html__('Add Extra', 'bookpoint'); ?></h1>

  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('bp_admin'); ?>
    <input type="hidden" name="action" value="bp_admin_extras_save">
    <input type="hidden" name="id" value="<?php echo esc_attr((string)$id); ?>">

    <table class="form-table">
      <tr>
        <th><label><?php echo esc_html__('Services', 'bookpoint'); ?></label></th>
        <td>
          <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;max-width:760px;">
            <?php foreach ($services as $s):
              $sid = (int)$s['id'];
              $img = !empty($s['image_id']) ? wp_get_attachment_image_url((int)$s['image_id'], 'thumbnail') : '';
            ?>
              <label style="border:1px solid #e5e5e5;border-radius:14px;padding:10px;display:flex;gap:10px;align-items:center;">
                <input type="checkbox" name="service_ids[]" value="<?php echo esc_attr((string)$sid); ?>"
                  <?php checked(in_array($sid, $selected_service_ids, true)); ?>>
                <?php if ($img): ?>
                  <img src="<?php echo esc_url($img); ?>" style="width:36px;height:36px;border-radius:10px;object-fit:cover;">
                <?php endif; ?>
                <span><?php echo esc_html($s['name']); ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <p class="description"><?php echo esc_html__('This extra will appear for selected services.', 'bookpoint'); ?></p>
        </td>
      </tr>

      <tr>
        <th><label><?php echo esc_html__('Name', 'bookpoint'); ?></label></th>
        <td><input type="text" name="name" value="<?php echo esc_attr($name); ?>" class="regular-text" required></td>
      </tr>

      <tr>
        <th><label><?php echo esc_html__('Description', 'bookpoint'); ?></label></th>
        <td><textarea name="description" class="large-text" rows="4"><?php echo esc_textarea($description); ?></textarea></td>
      </tr>

      <tr>
        <th><label><?php echo esc_html__('Price', 'bookpoint'); ?></label></th>
        <td><input type="number" step="0.01" name="price" value="<?php echo esc_attr($price); ?>" class="small-text"></td>
      </tr>

      <tr>
        <th><label><?php echo esc_html__('Duration (minutes)', 'bookpoint'); ?></label></th>
        <td><input type="number" name="duration_min" value="<?php echo esc_attr($duration_min); ?>" class="small-text"></td>
      </tr>

      <tr>
        <th><label><?php echo esc_html__('Image', 'bookpoint'); ?></label></th>
        <td>
          <input type="hidden" name="image_id" id="bp_extra_image_id" value="<?php echo esc_attr((string)$image_id); ?>">
          <div id="bp_extra_image_preview" style="margin-bottom:10px;">
            <?php if ($image_url): ?>
              <img src="<?php echo esc_url($image_url); ?>" style="width:140px;height:140px;object-fit:cover;border-radius:14px;border:1px solid #ddd;">
            <?php else: ?>
              <div style="width:140px;height:140px;border-radius:14px;border:1px dashed #ccc;display:flex;align-items:center;justify-content:center;color:#777;">
                <?php echo esc_html__('No image', 'bookpoint'); ?>
              </div>
            <?php endif; ?>
          </div>

          <button type="button" class="button" id="bp_extra_pick_image"><?php echo esc_html__('Choose Image', 'bookpoint'); ?></button>
          <button type="button" class="button" id="bp_extra_remove_image"><?php echo esc_html__('Remove', 'bookpoint'); ?></button>
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

    <?php submit_button($id ? __('Save Changes', 'bookpoint') : __('Create Extra', 'bookpoint')); ?>
  </form>
</div>
