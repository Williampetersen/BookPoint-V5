<?php defined('ABSPATH') || exit; ?>
<?php require_once __DIR__ . '/legacy_shell.php'; ?>
<?php pointlybooking_render_legacy_shell_start(esc_html__('Tools', 'pointly-booking'), esc_html__('Maintenance, tests, and data utilities.', 'pointly-booking'), '', 'tools'); ?>

  <h2><?php esc_html_e('System Status', 'pointly-booking'); ?></h2>
  <p><strong><?php esc_html_e('Plugin version:', 'pointly-booking'); ?></strong> <?php echo esc_html($plugin_version ?: '-'); ?></p>
  <p><strong><?php esc_html_e('DB version:', 'pointly-booking'); ?></strong> <?php echo esc_html($db_version ?: '-'); ?></p>

  <table class="widefat striped" style="max-width:780px;">
    <thead><tr><th><?php esc_html_e('Table', 'pointly-booking'); ?></th><th><?php esc_html_e('Status', 'pointly-booking'); ?></th></tr></thead>
    <tbody>
      <?php foreach (($exists ?? []) as $t => $ok) : ?>
        <tr>
          <td><?php echo esc_html($t); ?></td>
          <td><?php echo esc_html($ok ? __('OK', 'pointly-booking') : __('Missing', 'pointly-booking')); ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <hr>

  <h2><?php echo esc_html__('Sync Relations', 'pointly-booking'); ?></h2>
  <p><?php echo esc_html__('This will rebuild service-category and extra-service mappings and sync legacy columns.', 'pointly-booking'); ?></p>

  <a class="button button-primary" href="<?php
    echo esc_url(wp_nonce_url(
      admin_url('admin.php?page=pointlybooking_tools&run=sync_relations'),
      'pointlybooking_tools_sync_relations'
    ));
  ?>">
    <?php echo esc_html__('Run Sync Relations', 'pointly-booking'); ?>
  </a>

  <?php if (!empty($result)): ?>
    <hr>
    <h3><?php echo esc_html__('Result', 'pointly-booking'); ?></h3>
    <pre><?php echo esc_html(wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
  <?php endif; ?>

  <hr>

  <h2><?php esc_html_e('Email Test', 'pointly-booking'); ?></h2>
  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('pointlybooking_admin'); ?>
    <input type="hidden" name="action" value="pointlybooking_admin_tools_email_test">
    <input type="email" name="to" value="<?php echo esc_attr(get_option('admin_email')); ?>" class="regular-text">
    <button class="button button-primary"><?php esc_html_e('Send Test Email', 'pointly-booking'); ?></button>
  </form>

  <hr>

  <h2><?php esc_html_e('Webhook Test', 'pointly-booking'); ?></h2>
  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('pointlybooking_admin'); ?>
    <input type="hidden" name="action" value="pointlybooking_admin_tools_webhook_test">
    <select name="event">
      <option value="booking_created">booking_created</option>
      <option value="booking_status_changed">booking_status_changed</option>
      <option value="booking_updated">booking_updated</option>
      <option value="booking_cancelled">booking_cancelled</option>
    </select>
    <button class="button"><?php esc_html_e('Send Webhook', 'pointly-booking'); ?></button>
    <p class="description"><?php esc_html_e('Make sure webhooks are enabled and URL is set in Settings -> Webhooks.', 'pointly-booking'); ?></p>
  </form>

  <hr>

  <h2><?php esc_html_e('Generate Demo Data', 'pointly-booking'); ?></h2>
  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('pointlybooking_admin'); ?>
    <input type="hidden" name="action" value="pointlybooking_admin_tools_generate_demo">

    <label><?php esc_html_e('Services', 'pointly-booking'); ?> <input type="number" name="services" value="3" min="1" max="50"></label>
    <label style="margin-left:12px;"><?php esc_html_e('Agents', 'pointly-booking'); ?> <input type="number" name="agents" value="3" min="1" max="50"></label>
    <label style="margin-left:12px;"><?php esc_html_e('Customers', 'pointly-booking'); ?> <input type="number" name="customers" value="5" min="1" max="200"></label>
    <label style="margin-left:12px;"><?php esc_html_e('Bookings', 'pointly-booking'); ?> <input type="number" name="bookings" value="10" min="1" max="500"></label>

    <div style="margin-top:10px;">
      <button class="button button-primary" onclick="return confirm('Generate demo data?');">
        <?php esc_html_e('Generate Demo Data', 'pointly-booking'); ?>
      </button>
    </div>
  </form>

  <hr>

  <h2><?php esc_html_e('Export Settings', 'pointly-booking'); ?></h2>
  <?php
    $pointlybooking_export_url = wp_nonce_url(
      add_query_arg(['action' => 'pointlybooking_admin_tools_export_settings'], admin_url('admin-post.php')),
      'pointlybooking_admin'
    );
  ?>
  <a class="button" href="<?php echo esc_url($pointlybooking_export_url); ?>"><?php esc_html_e('Download JSON', 'pointly-booking'); ?></a>

  <hr>

  <h2><?php esc_html_e('Import Settings', 'pointly-booking'); ?></h2>
  <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('pointlybooking_admin'); ?>
    <input type="hidden" name="action" value="pointlybooking_admin_tools_import_settings">
    <input type="file" name="pointlybooking_settings_file" accept="application/json">
    <button class="button button-primary"><?php esc_html_e('Import', 'pointly-booking'); ?></button>
  </form>
<?php pointlybooking_render_legacy_shell_end(); ?>
