<?php defined('ABSPATH') || exit; ?>
<?php require_once __DIR__ . '/legacy_shell.php'; ?>
<?php bp_render_legacy_shell_start(esc_html__('Tools', 'bookpoint'), esc_html__('Maintenance, tests, and data utilities.', 'bookpoint'), '', 'tools'); ?>

  <h2><?php esc_html_e('System Status', 'bookpoint'); ?></h2>
  <p><strong><?php esc_html_e('Plugin version:', 'bookpoint'); ?></strong> <?php echo esc_html($plugin_version ?: '-'); ?></p>
  <p><strong><?php esc_html_e('DB version:', 'bookpoint'); ?></strong> <?php echo esc_html($db_version ?: '-'); ?></p>

  <table class="widefat striped" style="max-width:780px;">
    <thead><tr><th><?php esc_html_e('Table', 'bookpoint'); ?></th><th><?php esc_html_e('Status', 'bookpoint'); ?></th></tr></thead>
    <tbody>
      <?php foreach (($exists ?? []) as $t => $ok) : ?>
        <tr>
          <td><?php echo esc_html($t); ?></td>
          <td><?php echo $ok ? '✅ OK' : '❌ Missing'; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <hr>

  <h2><?php echo esc_html__('Sync Relations', 'bookpoint'); ?></h2>
  <p><?php echo esc_html__('This will rebuild service-category and extra-service mappings and sync legacy columns.', 'bookpoint'); ?></p>

  <a class="button button-primary" href="<?php
    echo esc_url(wp_nonce_url(
      admin_url('admin.php?page=bp_tools&run=sync_relations'),
      'bp_tools_sync_relations'
    ));
  ?>">
    <?php echo esc_html__('Run Sync Relations', 'bookpoint'); ?>
  </a>

  <?php if (!empty($result)): ?>
    <hr>
    <h3><?php echo esc_html__('Result', 'bookpoint'); ?></h3>
    <pre><?php echo esc_html(print_r($result, true)); ?></pre>
  <?php endif; ?>

  <hr>

  <h2><?php esc_html_e('Email Test', 'bookpoint'); ?></h2>
  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('bp_admin'); ?>
    <input type="hidden" name="action" value="bp_admin_tools_email_test">
    <input type="email" name="to" value="<?php echo esc_attr(get_option('admin_email')); ?>" class="regular-text">
    <button class="button button-primary"><?php esc_html_e('Send Test Email', 'bookpoint'); ?></button>
  </form>

  <hr>

  <h2><?php esc_html_e('Webhook Test', 'bookpoint'); ?></h2>
  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('bp_admin'); ?>
    <input type="hidden" name="action" value="bp_admin_tools_webhook_test">
    <select name="event">
      <option value="booking_created">booking_created</option>
      <option value="booking_status_changed">booking_status_changed</option>
      <option value="booking_updated">booking_updated</option>
      <option value="booking_cancelled">booking_cancelled</option>
    </select>
    <button class="button"><?php esc_html_e('Send Webhook', 'bookpoint'); ?></button>
    <p class="description"><?php esc_html_e('Make sure webhooks are enabled and URL is set in Settings → Webhooks.', 'bookpoint'); ?></p>
  </form>

  <hr>

  <h2><?php esc_html_e('Generate Demo Data', 'bookpoint'); ?></h2>
  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('bp_admin'); ?>
    <input type="hidden" name="action" value="bp_admin_tools_generate_demo">

    <label><?php esc_html_e('Services', 'bookpoint'); ?> <input type="number" name="services" value="3" min="1" max="50"></label>
    <label style="margin-left:12px;"><?php esc_html_e('Agents', 'bookpoint'); ?> <input type="number" name="agents" value="3" min="1" max="50"></label>
    <label style="margin-left:12px;"><?php esc_html_e('Customers', 'bookpoint'); ?> <input type="number" name="customers" value="5" min="1" max="200"></label>
    <label style="margin-left:12px;"><?php esc_html_e('Bookings', 'bookpoint'); ?> <input type="number" name="bookings" value="10" min="1" max="500"></label>

    <div style="margin-top:10px;">
      <button class="button button-primary" onclick="return confirm('Generate demo data?');">
        <?php esc_html_e('Generate Demo Data', 'bookpoint'); ?>
      </button>
    </div>
  </form>

  <hr>

  <h2><?php esc_html_e('Export Settings', 'bookpoint'); ?></h2>
  <?php
    $export_url = wp_nonce_url(
      add_query_arg(['action' => 'bp_admin_tools_export_settings'], admin_url('admin-post.php')),
      'bp_admin'
    );
  ?>
  <a class="button" href="<?php echo esc_url($export_url); ?>"><?php esc_html_e('Download JSON', 'bookpoint'); ?></a>

  <hr>

  <h2><?php esc_html_e('Import Settings', 'bookpoint'); ?></h2>
  <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('bp_admin'); ?>
    <input type="hidden" name="action" value="bp_admin_tools_import_settings">
    <input type="file" name="bp_settings_file" accept="application/json">
    <button class="button button-primary"><?php esc_html_e('Import', 'bookpoint'); ?></button>
  </form>
<?php bp_render_legacy_shell_end(); ?>
