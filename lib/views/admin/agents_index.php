<?php defined('ABSPATH') || exit; ?>
<?php require_once __DIR__ . '/legacy_shell.php'; ?>
<?php
  $actions_html = '<a class="bp-top-btn" href="' . esc_url(admin_url('admin.php?page=bp_agents_edit')) . '">' . esc_html__('Add New', 'bookpoint') . '</a>';
  bp_render_legacy_shell_start(esc_html__('Agents', 'bookpoint'), esc_html__('Manage your team members and assignments.', 'bookpoint'), $actions_html, 'agents');
?>

  <table class="widefat striped">
    <thead>
      <tr>
        <th style="width:60px;"><?php esc_html_e('Image', 'bookpoint'); ?></th>
        <th>ID</th>
        <th><?php esc_html_e('Name', 'bookpoint'); ?></th>
        <th><?php esc_html_e('Email', 'bookpoint'); ?></th>
        <th><?php esc_html_e('Active', 'bookpoint'); ?></th>
        <th><?php esc_html_e('Actions', 'bookpoint'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($items)) : ?>
        <tr><td colspan="6"><?php esc_html_e('No agents yet.', 'bookpoint'); ?></td></tr>
      <?php else : foreach ($items as $a) : ?>
        <?php
          $url = !empty($a['image_id']) ? wp_get_attachment_image_url((int)$a['image_id'], 'thumbnail') : '';
          $img = $url ? '<img src="' . esc_url($url) . '" style="width:44px;height:44px;border-radius:10px;object-fit:cover;">' : '—';
        ?>
        <tr>
          <td><?php echo $img; ?></td>
          <td><?php echo esc_html($a['id']); ?></td>
          <td><?php echo esc_html(BP_AgentModel::display_name($a)); ?></td>
          <td><?php echo esc_html($a['email'] ?? '-'); ?></td>
          <td><?php echo (int)($a['is_active'] ?? 0) === 1 ? 'Yes' : 'No'; ?></td>
          <td>
            <a href="<?php echo esc_url(admin_url('admin.php?page=bp_agents_edit&id=' . absint($a['id']))); ?>"><?php esc_html_e('Edit', 'bookpoint'); ?></a>
            |
            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=bp_agents_delete&id=' . absint($a['id'])), 'bp_admin')); ?>"
               onclick="return confirm('<?php echo esc_js(__('Delete agent?', 'bookpoint')); ?>');">
              <?php esc_html_e('Delete', 'bookpoint'); ?>
            </a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
<?php bp_render_legacy_shell_end(); ?>
