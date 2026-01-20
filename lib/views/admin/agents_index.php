<?php defined('ABSPATH') || exit; ?>
<div class="wrap">
  <h1>
    <?php esc_html_e('Agents', 'bookpoint'); ?>
    <a class="page-title-action" href="<?php echo esc_url(admin_url('admin.php?page=bp_agents_edit')); ?>">
      <?php esc_html_e('Add New', 'bookpoint'); ?>
    </a>
  </h1>

  <table class="widefat striped">
    <thead>
      <tr>
        <th>ID</th>
        <th><?php esc_html_e('Name', 'bookpoint'); ?></th>
        <th><?php esc_html_e('Email', 'bookpoint'); ?></th>
        <th><?php esc_html_e('Active', 'bookpoint'); ?></th>
        <th><?php esc_html_e('Actions', 'bookpoint'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($items)) : ?>
        <tr><td colspan="5"><?php esc_html_e('No agents yet.', 'bookpoint'); ?></td></tr>
      <?php else : foreach ($items as $a) : ?>
        <tr>
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
</div>
