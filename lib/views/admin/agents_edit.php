<?php
defined('ABSPATH') || exit;
require_once __DIR__ . '/legacy_shell.php';

$id = isset($agent['id']) ? (int)$agent['id'] : 0;
$services = $services ?? [];
$selected_service_ids = $selected_service_ids ?? [];

$image_id  = (int)($agent['image_id'] ?? 0);
$image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';

$actions_html = '<a class="bp-top-btn" href="' . esc_url(admin_url('admin.php?page=bp_agents')) . '">' . esc_html__('Back to Agents', 'bookpoint') . '</a>';
bp_render_legacy_shell_start(
  $id ? esc_html__('Edit Agent', 'bookpoint') : esc_html__('Add Agent', 'bookpoint'),
  esc_html__('Manage agent profile, photo, and service assignments.', 'bookpoint'),
  $actions_html,
  'agents'
);
?>

  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('bp_admin'); ?>
    <input type="hidden" name="action" value="bp_admin_agents_save">
    <input type="hidden" name="id" value="<?php echo esc_attr((string)$id); ?>">

    <table class="form-table" role="presentation">
      <tr>
        <th><label><?php esc_html_e('First name', 'bookpoint'); ?></label></th>
        <td><input type="text" name="first_name" value="<?php echo esc_attr($agent['first_name'] ?? ''); ?>" class="regular-text"></td>
      </tr>
      <tr>
        <th><label><?php esc_html_e('Last name', 'bookpoint'); ?></label></th>
        <td><input type="text" name="last_name" value="<?php echo esc_attr($agent['last_name'] ?? ''); ?>" class="regular-text"></td>
      </tr>
      <tr>
        <th><label><?php esc_html_e('Email', 'bookpoint'); ?></label></th>
        <td><input type="email" name="email" value="<?php echo esc_attr($agent['email'] ?? ''); ?>" class="regular-text"></td>
      </tr>
      <tr>
        <th><label><?php esc_html_e('Phone', 'bookpoint'); ?></label></th>
        <td><input type="text" name="phone" value="<?php echo esc_attr($agent['phone'] ?? ''); ?>" class="regular-text"></td>
      </tr>
      <tr>
        <th><label><?php echo esc_html__('Agent Image', 'bookpoint'); ?></label></th>
        <td>
          <input type="hidden" name="image_id" id="bp_agent_image_id" value="<?php echo esc_attr((string)$image_id); ?>">

          <div id="bp_agent_image_preview" style="margin-bottom:10px;">
            <?php if ($image_url): ?>
              <img src="<?php echo esc_url($image_url); ?>" style="width:140px;height:140px;object-fit:cover;border-radius:14px;border:1px solid #ddd;">
            <?php else: ?>
              <div style="width:140px;height:140px;border-radius:14px;border:1px dashed #ccc;display:flex;align-items:center;justify-content:center;color:#777;">
                <?php echo esc_html__('No image', 'bookpoint'); ?>
              </div>
            <?php endif; ?>
          </div>

          <button type="button" class="button" id="bp_agent_pick_image"><?php echo esc_html__('Choose Image', 'bookpoint'); ?></button>
          <button type="button" class="button" id="bp_agent_remove_image"><?php echo esc_html__('Remove', 'bookpoint'); ?></button>
          <p class="description"><?php echo esc_html__('Uses Media Library. Stores attachment ID.', 'bookpoint'); ?></p>
        </td>
      </tr>
      <tr>
        <th><?php esc_html_e('Active', 'bookpoint'); ?></th>
        <td><label><input type="checkbox" name="is_active" value="1" <?php checked((int)($agent['is_active'] ?? 1), 1); ?>> <?php esc_html_e('Enabled', 'bookpoint'); ?></label></td>
      </tr>
      <tr>
        <th><?php esc_html_e('Schedule JSON', 'bookpoint'); ?></th>
        <td>
          <textarea name="schedule_json" rows="4" class="large-text" placeholder='{"1":"09:00-17:00","2":"09:00-17:00","0":""}'><?php echo esc_textarea($agent['schedule_json'] ?? ''); ?></textarea>
          <p class="description"><?php esc_html_e('Optional override schedule for this agent.', 'bookpoint'); ?></p>
        </td>
      </tr>
      <tr>
        <th><label><?php echo esc_html__('Services', 'bookpoint'); ?></label></th>
        <td>
          <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;max-width:720px;">
            <?php if (empty($services)): ?>
              <div style="color:#666;"><?php echo esc_html__('No services found. Create services first.', 'bookpoint'); ?></div>
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
          <p class="description"><?php echo esc_html__('Only these services will be available when selecting this agent in the booking form.', 'bookpoint'); ?></p>
        </td>
      </tr>
    </table>

    <p class="submit">
      <button type="submit" class="bp-btn bp-btn-primary">
        <?php echo $id ? esc_html__('Save Changes', 'bookpoint') : esc_html__('Create Agent', 'bookpoint'); ?>
      </button>
      <a class="bp-btn" href="<?php echo esc_url(admin_url('admin.php?page=bp_agents')); ?>">
        <?php echo esc_html__('Back', 'bookpoint'); ?>
      </a>
    </p>
  </form>

<?php bp_render_legacy_shell_end(); ?>
