<?php defined('ABSPATH') || exit; ?>
<?php require_once __DIR__ . '/legacy_shell.php'; ?>
<?php
  $pointlybooking_actions_html = '<a class="bp-top-btn" href="' . esc_url(admin_url('admin.php?page=pointlybooking_agents_edit')) . '">' . esc_html__('Add New', 'pointly-booking') . '</a>';
  pointlybooking_render_legacy_shell_start(esc_html__('Agents', 'pointly-booking'), esc_html__('Manage your team members and assignments.', 'pointly-booking'), $pointlybooking_actions_html, 'agents');
?>

  <table class="widefat striped">
    <thead>
      <tr>
        <th style="width:60px;"><?php esc_html_e('Image', 'pointly-booking'); ?></th>
        <th>ID</th>
        <th><?php esc_html_e('Name', 'pointly-booking'); ?></th>
        <th><?php esc_html_e('Email', 'pointly-booking'); ?></th>
        <th><?php esc_html_e('Active', 'pointly-booking'); ?></th>
        <th><?php esc_html_e('Actions', 'pointly-booking'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($items)) : ?>
        <tr><td colspan="6"><?php esc_html_e('No agents yet.', 'pointly-booking'); ?></td></tr>
      <?php else : foreach ($items as $pointlybooking_agent) : ?>
        <?php
          $pointlybooking_url = !empty($pointlybooking_agent['image_id']) ? wp_get_attachment_image_url((int) $pointlybooking_agent['image_id'], 'thumbnail') : '';
          $pointlybooking_img = $pointlybooking_url
            ? '<img src="' . esc_url($pointlybooking_url) . '" style="width:44px;height:44px;border-radius:10px;object-fit:cover;">'
            : '<span class="bp-muted">-</span>';
        ?>
        <tr>
          <td><?php echo wp_kses_post($pointlybooking_img); ?></td>
          <td><?php echo esc_html($pointlybooking_agent['id']); ?></td>
          <td><?php echo esc_html(POINTLYBOOKING_AgentModel::display_name($pointlybooking_agent)); ?></td>
          <td><?php echo esc_html($pointlybooking_agent['email'] ?? '-'); ?></td>
          <td><?php echo esc_html((int) ($pointlybooking_agent['is_active'] ?? 0) === 1 ? 'Yes' : 'No'); ?></td>
          <td>
            <a href="<?php echo esc_url(admin_url('admin.php?page=pointlybooking_agents_edit&id=' . absint($pointlybooking_agent['id']))); ?>"><?php esc_html_e('Edit', 'pointly-booking'); ?></a>
            |
            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=pointlybooking_agents_delete&id=' . absint($pointlybooking_agent['id'])), 'pointlybooking_admin')); ?>"
               onclick="return confirm('<?php echo esc_js(__('Delete agent?', 'pointly-booking')); ?>');">
              <?php esc_html_e('Delete', 'pointly-booking'); ?>
            </a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
<?php pointlybooking_render_legacy_shell_end(); ?>
