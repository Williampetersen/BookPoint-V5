<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
defined('ABSPATH') || exit;
require_once __DIR__ . '/legacy_shell.php';

$id = isset($agent['id']) ? (int)$agent['id'] : 0;
$errors = is_array($errors ?? null) ? $errors : [];
$services = $services ?? [];
$selected_service_ids = $selected_service_ids ?? [];

$image_id  = (int)($agent['image_id'] ?? 0);
$image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';

$pointlybooking_actions_html = '<a class="bp-top-btn" href="' . esc_url(admin_url('admin.php?page=pointlybooking_agents')) . '">' . esc_html__('Back to Agents', 'pointly-booking') . '</a>';
pointlybooking_render_legacy_shell_start(
  $id ? esc_html__('Edit Agent', 'pointly-booking') : esc_html__('Add Agent', 'pointly-booking'),
  esc_html__('Manage agent profile, photo, and service assignments.', 'pointly-booking'),
  $pointlybooking_actions_html,
  'agents'
);
?>

  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('pointlybooking_admin'); ?>
    <input type="hidden" name="action" value="pointlybooking_admin_agents_save">
    <input type="hidden" name="id" value="<?php echo esc_attr((string)$id); ?>">

    <table class="form-table" role="presentation">
      <tr>
        <th><label><?php esc_html_e('First name', 'pointly-booking'); ?></label></th>
        <td><input type="text" name="first_name" value="<?php echo esc_attr($agent['first_name'] ?? ''); ?>" class="regular-text"></td>
      </tr>
      <tr>
        <th><label><?php esc_html_e('Last name', 'pointly-booking'); ?></label></th>
        <td><input type="text" name="last_name" value="<?php echo esc_attr($agent['last_name'] ?? ''); ?>" class="regular-text"></td>
      </tr>
      <tr>
        <th><label><?php esc_html_e('Email', 'pointly-booking'); ?></label></th>
        <td><input type="email" name="email" value="<?php echo esc_attr($agent['email'] ?? ''); ?>" class="regular-text"></td>
      </tr>
      <tr>
        <th><label><?php esc_html_e('Phone', 'pointly-booking'); ?></label></th>
        <td><input type="text" name="phone" value="<?php echo esc_attr($agent['phone'] ?? ''); ?>" class="regular-text"></td>
      </tr>
      <tr>
        <th><label><?php echo esc_html__('Agent Image', 'pointly-booking'); ?></label></th>
        <td>
          <input type="hidden" name="image_id" id="pointlybooking_agent_image_id" value="<?php echo esc_attr((string)$image_id); ?>">

          <div id="pointlybooking_agent_image_preview" style="margin-bottom:10px;">
            <?php if ($image_url): ?>
              <img src="<?php echo esc_url($image_url); ?>" style="width:140px;height:140px;object-fit:cover;border-radius:14px;border:1px solid #ddd;">
            <?php else: ?>
              <div style="width:140px;height:140px;border-radius:14px;border:1px dashed #ccc;display:flex;align-items:center;justify-content:center;color:#777;">
                <?php echo esc_html__('No image', 'pointly-booking'); ?>
              </div>
            <?php endif; ?>
          </div>

          <button type="button" class="button" id="pointlybooking_agent_pick_image"><?php echo esc_html__('Choose Image', 'pointly-booking'); ?></button>
          <button type="button" class="button" id="pointlybooking_agent_remove_image"><?php echo esc_html__('Remove', 'pointly-booking'); ?></button>
          <p class="description"><?php echo esc_html__('Uses Media Library. Stores attachment ID.', 'pointly-booking'); ?></p>
        </td>
      </tr>
      <tr>
        <th><?php esc_html_e('Active', 'pointly-booking'); ?></th>
        <td><label><input type="checkbox" name="is_active" value="1" <?php checked((int)($agent['is_active'] ?? 1), 1); ?>> <?php esc_html_e('Enabled', 'pointly-booking'); ?></label></td>
      </tr>
      <tr>
        <th><?php esc_html_e('Schedule JSON', 'pointly-booking'); ?></th>
        <td>
          <textarea name="schedule_json" rows="4" class="large-text" placeholder='{"1":"09:00-17:00","2":"09:00-17:00","0":""}'><?php echo esc_textarea($agent['schedule_json'] ?? ''); ?></textarea>
          <p class="description"><?php esc_html_e('Optional override schedule for this agent.', 'pointly-booking'); ?></p>
          <?php if (!empty($errors['schedule_json'])) : ?>
            <p style="color:#b32d2e;margin:6px 0 0;"><?php echo esc_html($errors['schedule_json']); ?></p>
          <?php endif; ?>
        </td>
      </tr>
      <tr>
        <th><label><?php echo esc_html__('Services', 'pointly-booking'); ?></label></th>
        <td>
          <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;max-width:720px;">
            <?php if (empty($services)): ?>
              <div style="color:#666;"><?php echo esc_html__('No services found. Create services first.', 'pointly-booking'); ?></div>
            <?php else: foreach ($services as $s):
              $sid = (int)$s['id'];
              $checked = in_array($sid, $selected_service_ids, true);
            ?>
              <label style="border:1px solid #e5e5e5;border-radius:12px;padding:10px;display:flex;gap:10px;align-items:center;">
                <input type="checkbox" name="service_ids[]" value="<?php echo esc_attr((string)$sid); ?>" <?php checked($checked); ?>>
                <span><?php echo esc_html($s['name']); ?></span>
              </label>
            <?php endforeach; endif; ?>
          </div>
          <p class="description"><?php echo esc_html__('Only these services will be available when selecting this agent in the booking form.', 'pointly-booking'); ?></p>
        </td>
      </tr>
    </table>

    <p class="submit">
      <button type="submit" class="bp-btn bp-btn-primary">
        <?php echo esc_html($id ? __('Save Changes', 'pointly-booking') : __('Create Agent', 'pointly-booking')); ?>
      </button>
      <a class="bp-btn" href="<?php echo esc_url(admin_url('admin.php?page=pointlybooking_agents')); ?>">
        <?php echo esc_html__('Back', 'pointly-booking'); ?>
      </a>
    </p>
  </form>

<?php pointlybooking_render_legacy_shell_end(); ?>
