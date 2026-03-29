<?php
defined('ABSPATH') || exit;
$pointlybooking_tab_raw = is_array($_GET) && array_key_exists('tab', $_GET) ? wp_unslash($_GET['tab']) : 'general';
$pointlybooking_tab = sanitize_key(is_scalar($pointlybooking_tab_raw) ? (string) $pointlybooking_tab_raw : 'general');
if (!in_array($pointlybooking_tab, ['general', 'emails', 'webhooks', 'import_export'], true)) {
  $pointlybooking_tab = 'general';
}

$pointlybooking_updated = false;
$pointlybooking_updated_raw = is_array($_GET) && array_key_exists('updated', $_GET) ? wp_unslash($_GET['updated']) : '';
$pointlybooking_updated_value = sanitize_key(is_scalar($pointlybooking_updated_raw) ? (string) $pointlybooking_updated_raw : '');
$pointlybooking_updated_nonce_raw = is_array($_GET) && array_key_exists('pointlybooking_updated_nonce', $_GET) ? wp_unslash($_GET['pointlybooking_updated_nonce']) : '';
$pointlybooking_updated_nonce = sanitize_text_field(is_scalar($pointlybooking_updated_nonce_raw) ? (string) $pointlybooking_updated_nonce_raw : '');
if (
  $pointlybooking_updated_value === '1'
  && $pointlybooking_updated_nonce !== ''
  && wp_verify_nonce($pointlybooking_updated_nonce, 'pointlybooking_settings_updated_notice')
) {
  $pointlybooking_updated = true;
}

$pointlybooking_import = '';
$pointlybooking_import_nonce_raw = is_array($_GET) && array_key_exists('pointlybooking_import_nonce', $_GET) ? wp_unslash($_GET['pointlybooking_import_nonce']) : '';
$pointlybooking_import_nonce = sanitize_text_field(is_scalar($pointlybooking_import_nonce_raw) ? (string) $pointlybooking_import_nonce_raw : '');
if ($pointlybooking_import_nonce !== '' && wp_verify_nonce($pointlybooking_import_nonce, 'pointlybooking_import_result')) {
  $pointlybooking_import_raw = is_array($_GET) && array_key_exists('import', $_GET) ? wp_unslash($_GET['import']) : '';
  $pointlybooking_import_state = sanitize_key(is_scalar($pointlybooking_import_raw) ? (string) $pointlybooking_import_raw : '');
  if (in_array($pointlybooking_import_state, ['ok', 'missing', 'badfile', 'badsettings'], true)) {
    $pointlybooking_import = $pointlybooking_import_state;
  }
}

function pointlybooking_err($errors, $k) {
  if (!empty($errors[$k])) {
    echo '<p style="color:#b32d2e;margin:6px 0 0;">' . esc_html($errors[$k]) . '</p>';
  }
}
require_once __DIR__ . '/legacy_shell.php';
pointlybooking_render_legacy_shell_start(esc_html__('BookPoint Settings', 'bookpoint-booking'), esc_html__('Configure global settings and integrations.', 'bookpoint-booking'), '', 'settings');
?>

  <h2 class="nav-tab-wrapper">
    <a class="nav-tab <?php echo esc_attr($pointlybooking_tab === 'general' ? 'nav-tab-active' : ''); ?>" href="<?php echo esc_url(admin_url('admin.php?page=pointlybooking_settings&tab=general')); ?>"><?php esc_html_e('General', 'bookpoint-booking'); ?></a>
    <a class="nav-tab <?php echo esc_attr($pointlybooking_tab === 'emails' ? 'nav-tab-active' : ''); ?>" href="<?php echo esc_url(admin_url('admin.php?page=pointlybooking_settings&tab=emails')); ?>"><?php esc_html_e('Emails', 'bookpoint-booking'); ?></a>
    <a class="nav-tab <?php echo esc_attr($pointlybooking_tab === 'webhooks' ? 'nav-tab-active' : ''); ?>" href="<?php echo esc_url(admin_url('admin.php?page=pointlybooking_settings&tab=webhooks')); ?>"><?php esc_html_e('Webhooks', 'bookpoint-booking'); ?></a>
    <a class="nav-tab <?php echo esc_attr($pointlybooking_tab === 'import_export' ? 'nav-tab-active' : ''); ?>" href="<?php echo esc_url(admin_url('admin.php?page=pointlybooking_settings&tab=import_export')); ?>"><?php esc_html_e('Import / Export', 'bookpoint-booking'); ?></a>
  </h2>

  <?php if ($pointlybooking_updated) : ?>
    <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Settings saved.', 'bookpoint-booking'); ?></p></div>
  <?php endif; ?>

  <?php if (in_array($pointlybooking_tab, ['general','emails','webhooks'], true)) : ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <?php wp_nonce_field('pointlybooking_admin'); ?>
      <input type="hidden" name="action" value="pointlybooking_admin_settings_save">
      <input type="hidden" name="tab" value="<?php echo esc_attr($pointlybooking_tab); ?>">

              <?php if ($pointlybooking_tab === 'general') : ?>
              <table class="form-table" role="presentation">
                <tr>
                  <th><label for="pointlybooking_open"><?php echo esc_html__('Open time', 'bookpoint-booking'); ?></label></th>
                  <td>
                    <input id="pointlybooking_open" type="text" name="open_time" value="<?php echo esc_attr($open); ?>" placeholder="09:00">
                    <?php pointlybooking_err($errors, 'open_time'); ?>
                  </td>
                </tr>

                <tr>
                  <th><label for="pointlybooking_close"><?php echo esc_html__('Close time', 'bookpoint-booking'); ?></label></th>
                  <td>
                    <input id="pointlybooking_close" type="text" name="close_time" value="<?php echo esc_attr($close); ?>" placeholder="17:00">
                    <?php pointlybooking_err($errors, 'close_time'); ?>
                  </td>
                </tr>

                <tr>
                  <th><label for="pointlybooking_interval"><?php echo esc_html__('Slot interval (minutes)', 'bookpoint-booking'); ?></label></th>
                  <td>
                    <input id="pointlybooking_interval" type="number" min="5" max="120" name="slot_interval_minutes" value="<?php echo esc_attr((int)$interval); ?>">
                    <?php pointlybooking_err($errors, 'slot_interval_minutes'); ?>
                  </td>
                </tr>

                <tr>
                  <th><label for="pointlybooking_currency"><?php echo esc_html__('Default currency', 'bookpoint-booking'); ?></label></th>
                  <td>
                    <input id="pointlybooking_currency" type="text" name="default_currency" maxlength="3" value="<?php echo esc_attr($currency); ?>" placeholder="USD">
                    <?php pointlybooking_err($errors, 'default_currency'); ?>
                  </td>
                </tr>

                <tr>
                  <th><label for="pointlybooking_currency_position"><?php echo esc_html__('Currency position', 'bookpoint-booking'); ?></label></th>
                  <td>
                    <select id="pointlybooking_currency_position" name="currency_position">
                      <option value="before" <?php selected($currency_position ?? 'before', 'before'); ?>><?php echo esc_html__('Before amount', 'bookpoint-booking'); ?></option>
                      <option value="after" <?php selected($currency_position ?? 'before', 'after'); ?>><?php echo esc_html__('After amount', 'bookpoint-booking'); ?></option>
                    </select>
                    <?php pointlybooking_err($errors, 'currency_position'); ?>
                  </td>
                </tr>
              </table>

              <h2><?php esc_html_e('Availability & Scheduling', 'bookpoint-booking'); ?></h2>
              <table class="form-table" role="presentation">
                <tr>
                  <th><label for="pointlybooking_future_days"><?php esc_html_e('Booking limit (days ahead)', 'bookpoint-booking'); ?></label></th>
                  <td>
                    <input id="pointlybooking_future_days" type="number" min="1" max="365" name="future_days_limit" value="<?php echo esc_attr($future_days_limit); ?>">
                    <p class="description"><?php esc_html_e('Customers can only book up to this many days in advance', 'bookpoint-booking'); ?></p>
                    <?php pointlybooking_err($errors,'future_days_limit'); ?>
                  </td>
                </tr>

                <tr>
                  <th><label for="pointlybooking_breaks"><?php esc_html_e('Daily breaks', 'bookpoint-booking'); ?></label></th>
                  <td>
                    <input id="pointlybooking_breaks" type="text" name="breaks" value="<?php echo esc_attr($breaks); ?>" class="regular-text" placeholder="12:00-13:00,15:00-15:15">
                    <p class="description"><?php esc_html_e('Comma-separated break times (e.g., 12:00-13:00,15:00-15:15)', 'bookpoint-booking'); ?></p>
                    <?php pointlybooking_err($errors,'breaks'); ?>
                  </td>
                </tr>
              </table>

              <h2><?php esc_html_e('Weekly Schedule', 'bookpoint-booking'); ?></h2>
              <table class="form-table" role="presentation">
                <tr>
                  <td colspan="2">
                    <table style="width:100%;border-collapse:collapse;">
                      <tr style="background:#f1f1f1;">
                        <th style="padding:8px;border:1px solid #ddd;text-align:left;"><?php esc_html_e('Day', 'bookpoint-booking'); ?></th>
                        <th style="padding:8px;border:1px solid #ddd;text-align:left;"><?php esc_html_e('Hours', 'bookpoint-booking'); ?></th>
                      </tr>
                      <?php 
                        $pointlybooking_days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                        for ($pointlybooking_i = 0; $pointlybooking_i < 7; $pointlybooking_i++) :
                      ?>
                      <tr>
                        <td style="padding:8px;border:1px solid #ddd;">
                          <?php echo esc_html($pointlybooking_days[$pointlybooking_i]); ?>
                        </td>
                        <td style="padding:8px;border:1px solid #ddd;">
                          <input type="text" name="schedule_<?php echo esc_attr($pointlybooking_i); ?>" value="<?php echo esc_attr($schedule[$pointlybooking_i]); ?>" placeholder="09:00-17:00" style="width:100%;box-sizing:border-box;">
                          <?php pointlybooking_err($errors, 'schedule_' . $pointlybooking_i); ?>
                        </td>
                      </tr>
                      <?php endfor; ?>
                    </table>
                    <p class="description" style="margin-top:10px;"><?php esc_html_e('Leave empty for closed days. Format: HH:MM-HH:MM (e.g., 09:00-17:00)', 'bookpoint-booking'); ?></p>
                  </td>
                </tr>
              </table>

              <h2><?php esc_html_e('Uninstall', 'bookpoint-booking'); ?></h2>
              <table class="form-table" role="presentation">
                <tr>
                  <th><?php esc_html_e('Remove data on uninstall', 'bookpoint-booking'); ?></th>
                  <td>
                    <label>
                      <input type="checkbox" name="pointlybooking_remove_data_on_uninstall" value="1" <?php checked((int)($remove_data_on_uninstall ?? 0), 1); ?>>
                      <?php esc_html_e('Delete all BookPoint data when the plugin is uninstalled.', 'bookpoint-booking'); ?>
                    </label>
                  </td>
                </tr>
              </table>
              <?php endif; ?>

              <?php if ($pointlybooking_tab === 'emails') : ?>
              <h2><?php esc_html_e('Email Notifications', 'bookpoint-booking'); ?></h2>
              <table class="form-table" role="presentation">
                <tr>
                  <th><?php esc_html_e('Enable Emails', 'bookpoint-booking'); ?></th>
                  <td>
                    <label>
                      <input type="checkbox" name="email_enabled" value="1" <?php checked((int)$email_enabled, 1); ?>>
                      <?php esc_html_e('Send email notifications for bookings', 'bookpoint-booking'); ?>
                    </label>
                  </td>
                </tr>

                <tr>
                  <th><label for="pointlybooking_admin_email"><?php esc_html_e('Admin email', 'bookpoint-booking'); ?></label></th>
                  <td>
                    <input id="pointlybooking_admin_email" type="email" name="admin_email" value="<?php echo esc_attr($admin_email); ?>" class="regular-text">
                    <?php pointlybooking_err($errors,'admin_email'); ?>
                  </td>
                </tr>

                <tr>
                  <th><label for="pointlybooking_from_name"><?php esc_html_e('From name', 'bookpoint-booking'); ?></label></th>
                  <td>
                    <input id="pointlybooking_from_name" type="text" name="from_name" value="<?php echo esc_attr($from_name); ?>" class="regular-text">
                  </td>
                </tr>

                <tr>
                  <th><label for="pointlybooking_from_email"><?php esc_html_e('From email', 'bookpoint-booking'); ?></label></th>
                  <td>
                    <input id="pointlybooking_from_email" type="email" name="from_email" value="<?php echo esc_attr($from_email); ?>" class="regular-text">
                    <?php pointlybooking_err($errors,'from_email'); ?>
                  </td>
                </tr>
              </table>
              <?php endif; ?>

              <?php if ($pointlybooking_tab === 'webhooks') : ?>
              <h2><?php esc_html_e('Webhooks', 'bookpoint-booking'); ?></h2>
              <table class="form-table" role="presentation">
                <tr>
                  <th><?php esc_html_e('Enable Webhooks', 'bookpoint-booking'); ?></th>
                  <td>
                    <label>
                      <input type="checkbox" name="webhooks_enabled" value="1" <?php checked((int)$webhooks_enabled, 1); ?>>
                      <?php esc_html_e('Send webhook events for bookings', 'bookpoint-booking'); ?>
                    </label>
                  </td>
                </tr>
                <tr>
                  <th><label for="pointlybooking_webhook_secret"><?php esc_html_e('Webhook secret', 'bookpoint-booking'); ?></label></th>
                  <td>
                    <input id="pointlybooking_webhook_secret" type="text" name="webhooks_secret" value="<?php echo esc_attr($webhooks_secret); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e('Used to sign webhook payloads (optional).', 'bookpoint-booking'); ?></p>
                  </td>
                </tr>
                <tr>
                  <th><label for="pointlybooking_webhook_booking_created"><?php esc_html_e('Booking created URL', 'bookpoint-booking'); ?></label></th>
                  <td>
                    <input id="pointlybooking_webhook_booking_created" type="url" name="webhooks_url_booking_created" value="<?php echo esc_attr($webhooks_url_booking_created); ?>" class="regular-text">
                  </td>
                </tr>
                <tr>
                  <th><label for="pointlybooking_webhook_booking_status_changed"><?php esc_html_e('Booking status changed URL', 'bookpoint-booking'); ?></label></th>
                  <td>
                    <input id="pointlybooking_webhook_booking_status_changed" type="url" name="webhooks_url_booking_status_changed" value="<?php echo esc_attr($webhooks_url_booking_status_changed); ?>" class="regular-text">
                  </td>
                </tr>
                <tr>
                  <th><label for="pointlybooking_webhook_booking_updated"><?php esc_html_e('Booking updated URL', 'bookpoint-booking'); ?></label></th>
                  <td>
                    <input id="pointlybooking_webhook_booking_updated" type="url" name="webhooks_url_booking_updated" value="<?php echo esc_attr($webhooks_url_booking_updated); ?>" class="regular-text">
                  </td>
                </tr>
                <tr>
                  <th><label for="pointlybooking_webhook_booking_cancelled"><?php esc_html_e('Booking cancelled URL', 'bookpoint-booking'); ?></label></th>
                  <td>
                    <input id="pointlybooking_webhook_booking_cancelled" type="url" name="webhooks_url_booking_cancelled" value="<?php echo esc_attr($webhooks_url_booking_cancelled); ?>" class="regular-text">
                  </td>
                </tr>
              </table>
              <?php endif; ?>

              <p class="submit">
                <button type="submit" class="button button-primary"><?php echo esc_html__('Save Settings', 'bookpoint-booking'); ?></button>
              </p>
            </form>
          <?php endif; ?>

          <?php if ($pointlybooking_tab === 'import_export') : ?>
            <div style="max-width:900px;">
              <h2><?php esc_html_e('Import / Export', 'bookpoint-booking'); ?></h2>

              <h3><?php esc_html_e('Export settings', 'bookpoint-booking'); ?></h3>
              <p class="description"><?php esc_html_e('Downloads a JSON file with BookPoint settings.', 'bookpoint-booking'); ?></p>
              <?php
                $pointlybooking_export_url = wp_nonce_url(
                  admin_url('admin-post.php?action=pointlybooking_admin_settings_export_json'),
                  'pointlybooking_admin'
                );
              ?>
              <a class="button button-primary" href="<?php echo esc_url($pointlybooking_export_url); ?>"><?php esc_html_e('Export JSON', 'bookpoint-booking'); ?></a>

              <hr>

              <h3><?php esc_html_e('Import settings', 'bookpoint-booking'); ?></h3>
              <p class="description"><?php esc_html_e('Upload a previously exported JSON file. Only whitelisted settings keys are imported.', 'bookpoint-booking'); ?></p>

              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('pointlybooking_admin'); ?>
                <input type="hidden" name="action" value="pointlybooking_admin_settings_import_json">

                <input type="file" name="pointlybooking_settings_file" accept="application/json" required>
                <button class="button" type="submit" onclick="return confirm('Import settings JSON?');">
                  <?php esc_html_e('Import JSON', 'bookpoint-booking'); ?>
                </button>
              </form>

              <?php if ($pointlybooking_import !== '') : ?>
                <p style="margin-top:12px;">
                  <strong><?php echo esc_html__('Import result:', 'bookpoint-booking'); ?></strong>
                  <?php echo esc_html($pointlybooking_import); ?>
                </p>
              <?php endif; ?>
            </div>
          <?php endif; ?>
<?php pointlybooking_render_legacy_shell_end(); ?>
