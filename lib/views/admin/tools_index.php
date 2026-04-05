<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
defined('ABSPATH') || exit; ?>
<?php require_once __DIR__ . '/legacy_shell.php'; ?>
<?php pointlybooking_render_legacy_shell_start(esc_html__('Tools', 'bookpoint-v5'), esc_html__('Maintenance, tests, and data utilities.', 'bookpoint-v5'), '', 'tools'); ?>

  <h2><?php esc_html_e('System Status', 'bookpoint-v5'); ?></h2>
  <p><strong><?php esc_html_e('Plugin version:', 'bookpoint-v5'); ?></strong> <?php echo esc_html($plugin_version ?: '-'); ?></p>
  <p><strong><?php esc_html_e('DB version:', 'bookpoint-v5'); ?></strong> <?php echo esc_html($db_version ?: '-'); ?></p>

  <table class="widefat striped" style="max-width:780px;">
    <thead><tr><th><?php esc_html_e('Table', 'bookpoint-v5'); ?></th><th><?php esc_html_e('Status', 'bookpoint-v5'); ?></th></tr></thead>
    <tbody>
      <?php foreach (($exists ?? []) as $t => $ok) : ?>
        <tr>
          <td><?php echo esc_html($t); ?></td>
          <td><?php echo esc_html($ok ? __('OK', 'bookpoint-v5') : __('Missing', 'bookpoint-v5')); ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <hr>

  <h2><?php echo esc_html__('Sync Relations', 'bookpoint-v5'); ?></h2>
  <p><?php echo esc_html__('This will rebuild service-category and extra-service mappings and sync legacy columns.', 'bookpoint-v5'); ?></p>

  <a class="button button-primary" href="<?php
    echo esc_url(wp_nonce_url(
      admin_url('admin.php?page=pointlybooking_tools&run=sync_relations'),
      'pointlybooking_tools_sync_relations'
    ));
  ?>">
    <?php echo esc_html__('Run Sync Relations', 'bookpoint-v5'); ?>
  </a>

  <?php if (!empty($result)): ?>
    <hr>
    <h3><?php echo esc_html__('Result', 'bookpoint-v5'); ?></h3>
    <pre><?php echo esc_html(wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
  <?php endif; ?>

  <hr>

  <h2><?php esc_html_e('Email Test', 'bookpoint-v5'); ?></h2>
  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('pointlybooking_admin'); ?>
    <input type="hidden" name="action" value="pointlybooking_admin_tools_email_test">
    <input type="email" name="to" value="<?php echo esc_attr(get_option('admin_email')); ?>" class="regular-text">
    <button class="button button-primary"><?php esc_html_e('Send Test Email', 'bookpoint-v5'); ?></button>
  </form>

  <hr>

  <h2><?php esc_html_e('Webhook Test', 'bookpoint-v5'); ?></h2>
  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('pointlybooking_admin'); ?>
    <input type="hidden" name="action" value="pointlybooking_admin_tools_webhook_test">
    <select name="event">
      <option value="booking_created">booking_created</option>
      <option value="booking_status_changed">booking_status_changed</option>
      <option value="booking_updated">booking_updated</option>
      <option value="booking_cancelled">booking_cancelled</option>
    </select>
    <button class="button"><?php esc_html_e('Send Webhook', 'bookpoint-v5'); ?></button>
    <p class="description"><?php esc_html_e('Make sure webhooks are enabled and URL is set in Settings -> Webhooks.', 'bookpoint-v5'); ?></p>
  </form>

  <hr>

  <h2><?php esc_html_e('Generate Demo Data', 'bookpoint-v5'); ?></h2>
  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('pointlybooking_admin'); ?>
    <input type="hidden" name="action" value="pointlybooking_admin_tools_generate_demo">

    <label><?php esc_html_e('Services', 'bookpoint-v5'); ?> <input type="number" name="services" value="3" min="1" max="50"></label>
    <label style="margin-left:12px;"><?php esc_html_e('Agents', 'bookpoint-v5'); ?> <input type="number" name="agents" value="3" min="1" max="50"></label>
    <label style="margin-left:12px;"><?php esc_html_e('Customers', 'bookpoint-v5'); ?> <input type="number" name="customers" value="5" min="1" max="200"></label>
    <label style="margin-left:12px;"><?php esc_html_e('Bookings', 'bookpoint-v5'); ?> <input type="number" name="bookings" value="10" min="1" max="500"></label>

    <div style="margin-top:10px;">
      <button class="button button-primary" onclick="return confirm('Generate demo data?');">
        <?php esc_html_e('Generate Demo Data', 'bookpoint-v5'); ?>
      </button>
    </div>
  </form>

  <hr>

  <h2><?php esc_html_e('Export Settings', 'bookpoint-v5'); ?></h2>
  <?php
    $pointlybooking_export_url = wp_nonce_url(
      add_query_arg(['action' => 'pointlybooking_admin_tools_export_settings'], admin_url('admin-post.php')),
      'pointlybooking_admin'
    );
  ?>
  <a class="button" href="<?php echo esc_url($pointlybooking_export_url); ?>"><?php esc_html_e('Download JSON', 'bookpoint-v5'); ?></a>

  <hr>

  <h2><?php esc_html_e('Import Settings', 'bookpoint-v5'); ?></h2>
  <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('pointlybooking_admin'); ?>
    <input type="hidden" name="action" value="pointlybooking_admin_tools_import_settings">
    <input type="file" name="pointlybooking_settings_file" accept="application/json">
    <button class="button button-primary"><?php esc_html_e('Import', 'bookpoint-v5'); ?></button>
  </form>
<?php pointlybooking_render_legacy_shell_end(); ?>
