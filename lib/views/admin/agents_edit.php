<?php defined('ABSPATH') || exit;
$id = isset($agent['id']) ? (int)$agent['id'] : 0;
?>
<div class="wrap">
  <h1><?php echo $id ? esc_html__('Edit Agent', 'bookpoint') : esc_html__('Add Agent', 'bookpoint'); ?></h1>

  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('bp_admin'); ?>
    <input type="hidden" name="action" value="bp_admin_agents_save">
    <input type="hidden" name="id" value="<?php echo esc_attr($id); ?>">

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
    </table>

    <p class="submit">
      <button class="button button-primary" type="submit"><?php esc_html_e('Save', 'bookpoint'); ?></button>
      <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=bp_agents')); ?>"><?php esc_html_e('Cancel', 'bookpoint'); ?></a>
    </p>
  </form>
</div>
