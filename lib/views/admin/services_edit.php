<?php
defined('ABSPATH') || exit;

$service = $service ?? ($item ?? null);
$categories = $categories ?? [];

$id = isset($service['id']) ? (int)$service['id'] : 0;
$name = $service['name'] ?? '';
$description = $service['description'] ?? '';
$duration = isset($service['duration_minutes']) ? (int)$service['duration_minutes'] : 60;
$price_cents = isset($service['price_cents']) ? (int)$service['price_cents'] : 0;
$currency = $service['currency'] ?? 'USD';
$is_active = isset($service['is_active']) ? (int)$service['is_active'] : 1;

$category_id = (int)($service['category_id'] ?? 0);
$image_id = (int)($service['image_id'] ?? 0);
$image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';

$selected_category_ids = !empty($service['id'])
  ? BP_ServiceModel::get_category_ids((int)$service['id'])
  : [];
$categories = $categories ?? BP_CategoryModel::all(['is_active' => 1]);

// Step 15: Service-based availability (with null-safe defaults)
$use_global_schedule = (is_array($service) && isset($service['use_global_schedule'])) ? (int)$service['use_global_schedule'] : 1;
$schedule_json = (is_array($service) && isset($service['schedule_json'])) ? $service['schedule_json'] : '';
$buffer_before = (is_array($service) && isset($service['buffer_before_minutes'])) ? (int)$service['buffer_before_minutes'] : 0;
$buffer_after  = (is_array($service) && isset($service['buffer_after_minutes'])) ? (int)$service['buffer_after_minutes'] : 0;
$capacity      = (is_array($service) && isset($service['capacity'])) ? (int)$service['capacity'] : 1;

function BP_field_error($errors, $key) {
  if (!empty($errors[$key])) {
    echo '<p style="color:#b32d2e;margin:6px 0 0;">' . esc_html($errors[$key]) . '</p>';
  }
}
?>
<div class="wrap">
  <h1><?php echo $id ? esc_html__('Edit Service', 'bookpoint') : esc_html__('Add Service', 'bookpoint'); ?></h1>

  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('bp_admin'); ?>
    <input type="hidden" name="action" value="bp_admin_services_save">
    <input type="hidden" name="id" value="<?php echo esc_attr($id); ?>">

    <table class="form-table" role="presentation">
      <tr>
        <th><label for="bp_name"><?php echo esc_html__('Name', 'bookpoint'); ?></label></th>
        <td>
          <input type="text" id="bp_name" name="name" class="regular-text" value="<?php echo esc_attr($name); ?>" required>
          <?php BP_field_error($errors, 'name'); ?>
        </td>
      </tr>

      <tr>
        <th><label for="bp_duration"><?php echo esc_html__('Duration (minutes)', 'bookpoint'); ?></label></th>
        <td>
          <input type="number" id="bp_duration" name="duration_minutes" min="5" max="1440" value="<?php echo esc_attr($duration); ?>">
          <?php BP_field_error($errors, 'duration_minutes'); ?>
        </td>
      </tr>

      <tr>
        <th><label for="bp_price"><?php echo esc_html__('Price (cents)', 'bookpoint'); ?></label></th>
        <td>
          <input type="number" id="bp_price" name="price_cents" min="0" value="<?php echo esc_attr($price_cents); ?>">
          <p class="description"><?php echo esc_html__('Example: 2500 = 25.00', 'bookpoint'); ?></p>
          <?php BP_field_error($errors, 'price_cents'); ?>
        </td>
      </tr>

      <tr>
        <th><label for="bp_currency"><?php echo esc_html__('Currency', 'bookpoint'); ?></label></th>
        <td>
          <input type="text" id="bp_currency" name="currency" maxlength="3" value="<?php echo esc_attr($currency); ?>">
          <?php BP_field_error($errors, 'currency'); ?>
        </td>
      </tr>

      <tr>
        <th><?php echo esc_html__('Description', 'bookpoint'); ?></th>
        <td>
          <textarea name="description" rows="5" class="large-text"><?php echo esc_textarea($description); ?></textarea>
        </td>
      </tr>

      <tr>
        <th><label><?php echo esc_html__('Categories', 'bookpoint'); ?></label></th>
        <td>
          <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;max-width:760px;">
            <?php foreach ($categories as $cat):
              $cid = (int)$cat['id'];
              $img = !empty($cat['image_id']) ? wp_get_attachment_image_url((int)$cat['image_id'], 'thumbnail') : '';
            ?>
              <label style="border:1px solid #e5e5e5;border-radius:14px;padding:10px;display:flex;gap:10px;align-items:center;">
                <input type="checkbox" name="category_ids[]" value="<?php echo esc_attr((string)$cid); ?>"
                  <?php checked(in_array($cid, $selected_category_ids, true)); ?>>
                <?php if ($img): ?>
                  <img src="<?php echo esc_url($img); ?>" style="width:36px;height:36px;border-radius:10px;object-fit:cover;">
                <?php endif; ?>
                <span><?php echo esc_html($cat['name']); ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <p class="description"><?php echo esc_html__('A service can belong to multiple categories.', 'bookpoint'); ?></p>
        </td>
      </tr>

      <tr>
        <th><label><?php echo esc_html__('Service Image', 'bookpoint'); ?></label></th>
        <td>
          <input type="hidden" name="image_id" id="bp_service_image_id" value="<?php echo esc_attr((string)$image_id); ?>">

          <div id="bp_service_image_preview" style="margin-bottom:10px;">
            <?php if ($image_url): ?>
              <img src="<?php echo esc_url($image_url); ?>" style="width:140px;height:140px;object-fit:cover;border-radius:14px;border:1px solid #ddd;">
            <?php else: ?>
              <div style="width:140px;height:140px;border-radius:14px;border:1px dashed #ccc;display:flex;align-items:center;justify-content:center;color:#777;">
                <?php echo esc_html__('No image', 'bookpoint'); ?>
              </div>
            <?php endif; ?>
          </div>

          <button type="button" class="button" id="bp_service_pick_image"><?php echo esc_html__('Choose Image', 'bookpoint'); ?></button>
          <button type="button" class="button" id="bp_service_remove_image"><?php echo esc_html__('Remove', 'bookpoint'); ?></button>

          <p class="description"><?php echo esc_html__('Stored as Media Library attachment ID.', 'bookpoint'); ?></p>
        </td>
      </tr>

      <tr>
        <th><?php echo esc_html__('Active', 'bookpoint'); ?></th>
        <td>
          <label>
            <input type="checkbox" name="is_active" value="1" <?php checked($is_active, 1); ?>>
            <?php echo esc_html__('Service is active', 'bookpoint'); ?>
          </label>
        </td>
      </tr>

      <tr>
        <th><label for="bp_capacity"><?php echo esc_html__('Capacity', 'bookpoint'); ?></label></th>
        <td>
          <input id="bp_capacity" type="number" min="1" max="50" name="capacity" value="<?php echo esc_attr($capacity); ?>">
          <?php BP_field_error($errors, 'capacity'); ?>
          <p class="description"><?php echo esc_html__('How many bookings can be made for the same time slot.', 'bookpoint'); ?></p>
        </td>
      </tr>

      <tr>
        <th><label for="bp_buf_before"><?php echo esc_html__('Buffer before (minutes)', 'bookpoint'); ?></label></th>
        <td>
          <input id="bp_buf_before" type="number" min="0" max="240" name="buffer_before_minutes" value="<?php echo esc_attr($buffer_before); ?>">
          <?php BP_field_error($errors, 'buffer_before_minutes'); ?>
        </td>
      </tr>

      <tr>
        <th><label for="bp_buf_after"><?php echo esc_html__('Buffer after (minutes)', 'bookpoint'); ?></label></th>
        <td>
          <input id="bp_buf_after" type="number" min="0" max="240" name="buffer_after_minutes" value="<?php echo esc_attr($buffer_after); ?>">
          <?php BP_field_error($errors, 'buffer_after_minutes'); ?>
        </td>
      </tr>

      <tr>
        <th><?php echo esc_html__('Use Global Schedule', 'bookpoint'); ?></th>
        <td>
          <label>
            <input type="checkbox" name="use_global_schedule" value="1" <?php checked($use_global_schedule, 1); ?>>
            <?php echo esc_html__('Use global weekly schedule from Settings', 'bookpoint'); ?>
          </label>
          <p class="description"><?php echo esc_html__('If disabled, you can provide a service-specific schedule JSON below.', 'bookpoint'); ?></p>
        </td>
      </tr>

      <tr>
        <th><?php echo esc_html__('Service Schedule JSON', 'bookpoint'); ?></th>
        <td>
          <textarea name="schedule_json" rows="4" class="large-text" placeholder='{"1":"09:00-17:00","2":"09:00-17:00","0":""}'><?php echo esc_textarea($schedule_json); ?></textarea>
          <p class="description"><?php echo esc_html__('Optional. Keys are weekday numbers 0-6. Values are "HH:MM-HH:MM" or empty for closed.', 'bookpoint'); ?></p>
        </td>
      </tr>
    </table>

    <h2><?php esc_html_e('Agents for this service', 'bookpoint'); ?></h2>

    <?php if (!empty($all_agents)) : ?>
      <?php foreach ($all_agents as $a) :
        $aid = (int)$a['id'];
        $checked = in_array($aid, $selected_agent_ids ?? [], true);
      ?>
        <label style="display:block; margin:6px 0;">
          <input type="checkbox" name="agent_ids[]" value="<?php echo esc_attr($aid); ?>" <?php checked($checked); ?>>
          <?php echo esc_html(BP_AgentModel::display_name($a)); ?>
        </label>
      <?php endforeach; ?>
    <?php else : ?>
      <p><?php esc_html_e('No agents yet. Add agents first.', 'bookpoint'); ?></p>
    <?php endif; ?>

    <p class="submit">
      <button type="submit" class="button button-primary"><?php echo esc_html__('Save Service', 'bookpoint'); ?></button>
      <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=bp_services')); ?>"><?php echo esc_html__('Back', 'bookpoint'); ?></a>
    </p>
  </form>
</div>

