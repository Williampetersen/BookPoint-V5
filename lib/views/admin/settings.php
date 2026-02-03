<?php
defined('ABSPATH') || exit;
$tab = sanitize_text_field($_GET['tab'] ?? 'general');

function bp_err($errors, $k) {
  if (!empty($errors[$k])) {
    echo '<p style="color:#b32d2e;margin:6px 0 0;">' . esc_html($errors[$k]) . '</p>';
  }
}
require_once __DIR__ . '/legacy_shell.php';
bp_render_legacy_shell_start(esc_html__('BookPoint Settings', 'bookpoint'), esc_html__('Configure global settings and integrations.', 'bookpoint'), '', 'settings');
?>

  <h2 class="nav-tab-wrapper">
    <a class="nav-tab <?php echo $tab === 'general' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=bp_settings&tab=general')); ?>"><?php esc_html_e('General', 'bookpoint'); ?></a>
    <a class="nav-tab <?php echo $tab === 'emails' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=bp_settings&tab=emails')); ?>"><?php esc_html_e('Emails', 'bookpoint'); ?></a>
    <a class="nav-tab <?php echo $tab === 'webhooks' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=bp_settings&tab=webhooks')); ?>"><?php esc_html_e('Webhooks', 'bookpoint'); ?></a>
    <a class="nav-tab <?php echo $tab === 'license' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=bp_settings&tab=license')); ?>"><?php esc_html_e('License', 'bookpoint'); ?></a>
    <a class="nav-tab <?php echo $tab === 'import_export' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=bp_settings&tab=import_export')); ?>"><?php esc_html_e('Import / Export', 'bookpoint'); ?></a>
  </h2>

  <?php if (isset($_GET['updated'])) : ?>
    <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Settings saved.', 'bookpoint'); ?></p></div>
  <?php endif; ?>

  <?php if (in_array($tab, ['general','emails','webhooks'], true)) : ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <?php wp_nonce_field('bp_admin'); ?>
      <input type="hidden" name="action" value="bp_admin_settings_save">
      <input type="hidden" name="tab" value="<?php echo esc_attr($tab); ?>">

              <?php if ($tab === 'general') : ?>
              <table class="form-table" role="presentation">
                <tr>
                  <th><label for="bp_open"><?php echo esc_html__('Open time', 'bookpoint'); ?></label></th>
                  <td>
                    <input id="bp_open" type="text" name="open_time" value="<?php echo esc_attr($open); ?>" placeholder="09:00">
                    <?php bp_err($errors, 'open_time'); ?>
                  </td>
                </tr>

                <tr>
                  <th><label for="bp_close"><?php echo esc_html__('Close time', 'bookpoint'); ?></label></th>
                  <td>
                    <input id="bp_close" type="text" name="close_time" value="<?php echo esc_attr($close); ?>" placeholder="17:00">
                    <?php bp_err($errors, 'close_time'); ?>
                  </td>
                </tr>

                <tr>
                  <th><label for="bp_interval"><?php echo esc_html__('Slot interval (minutes)', 'bookpoint'); ?></label></th>
                  <td>
                    <input id="bp_interval" type="number" min="5" max="120" name="slot_interval_minutes" value="<?php echo esc_attr((int)$interval); ?>">
                    <?php bp_err($errors, 'slot_interval_minutes'); ?>
                  </td>
                </tr>

                <tr>
                  <th><label for="bp_currency"><?php echo esc_html__('Default currency', 'bookpoint'); ?></label></th>
                  <td>
                    <input id="bp_currency" type="text" name="default_currency" maxlength="3" value="<?php echo esc_attr($currency); ?>" placeholder="USD">
                    <?php bp_err($errors, 'default_currency'); ?>
                  </td>
                </tr>

                <tr>
                  <th><label for="bp_currency_position"><?php echo esc_html__('Currency position', 'bookpoint'); ?></label></th>
                  <td>
                    <select id="bp_currency_position" name="currency_position">
                      <option value="before" <?php selected($currency_position ?? 'before', 'before'); ?>><?php echo esc_html__('Before amount', 'bookpoint'); ?></option>
                      <option value="after" <?php selected($currency_position ?? 'before', 'after'); ?>><?php echo esc_html__('After amount', 'bookpoint'); ?></option>
                    </select>
                    <?php bp_err($errors, 'currency_position'); ?>
                  </td>
                </tr>
              </table>

              <h2><?php esc_html_e('Availability & Scheduling', 'bookpoint'); ?></h2>
              <table class="form-table" role="presentation">
                <tr>
                  <th><label for="bp_future_days"><?php esc_html_e('Booking limit (days ahead)', 'bookpoint'); ?></label></th>
                  <td>
                    <input id="bp_future_days" type="number" min="1" max="365" name="future_days_limit" value="<?php echo esc_attr($future_days_limit); ?>">
                    <p class="description"><?php esc_html_e('Customers can only book up to this many days in advance', 'bookpoint'); ?></p>
                    <?php bp_err($errors,'future_days_limit'); ?>
                  </td>
                </tr>

                <tr>
                  <th><label for="bp_breaks"><?php esc_html_e('Daily breaks', 'bookpoint'); ?></label></th>
                  <td>
                    <input id="bp_breaks" type="text" name="breaks" value="<?php echo esc_attr($breaks); ?>" class="regular-text" placeholder="12:00-13:00,15:00-15:15">
                    <p class="description"><?php esc_html_e('Comma-separated break times (e.g., 12:00-13:00,15:00-15:15)', 'bookpoint'); ?></p>
                    <?php bp_err($errors,'breaks'); ?>
                  </td>
                </tr>
              </table>

              <h2><?php esc_html_e('Weekly Schedule', 'bookpoint'); ?></h2>
              <table class="form-table" role="presentation">
                <tr>
                  <td colspan="2">
                    <table style="width:100%;border-collapse:collapse;">
                      <tr style="background:#f1f1f1;">
                        <th style="padding:8px;border:1px solid #ddd;text-align:left;"><?php esc_html_e('Day', 'bookpoint'); ?></th>
                        <th style="padding:8px;border:1px solid #ddd;text-align:left;"><?php esc_html_e('Hours', 'bookpoint'); ?></th>
                      </tr>
                      <?php 
                        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                        for ($i = 0; $i < 7; $i++) :
                      ?>
                      <tr>
                        <td style="padding:8px;border:1px solid #ddd;">
                          <?php echo esc_html($days[$i]); ?>
                        </td>
                        <td style="padding:8px;border:1px solid #ddd;">
                          <input type="text" name="schedule_<?php echo esc_attr($i); ?>" value="<?php echo esc_attr($schedule[$i]); ?>" placeholder="09:00-17:00" style="width:100%;box-sizing:border-box;">
                          <?php bp_err($errors, 'schedule_' . $i); ?>
                        </td>
                      </tr>
                      <?php endfor; ?>
                    </table>
                    <p class="description" style="margin-top:10px;"><?php esc_html_e('Leave empty for closed days. Format: HH:MM-HH:MM (e.g., 09:00-17:00)', 'bookpoint'); ?></p>
                  </td>
                </tr>
              </table>

              <h2><?php esc_html_e('Uninstall', 'bookpoint'); ?></h2>
              <table class="form-table" role="presentation">
                <tr>
                  <th><?php esc_html_e('Remove data on uninstall', 'bookpoint'); ?></th>
                  <td>
                    <label>
                      <input type="checkbox" name="bp_remove_data_on_uninstall" value="1" <?php checked((int)($remove_data_on_uninstall ?? 0), 1); ?>>
                      <?php esc_html_e('Delete all BookPoint data when the plugin is uninstalled.', 'bookpoint'); ?>
                    </label>
                  </td>
                </tr>
              </table>
              <?php endif; ?>

              <?php if ($tab === 'emails') : ?>
              <h2><?php esc_html_e('Email Notifications', 'bookpoint'); ?></h2>
              <table class="form-table" role="presentation">
                <tr>
                  <th><?php esc_html_e('Enable Emails', 'bookpoint'); ?></th>
                  <td>
                    <label>
                      <input type="checkbox" name="email_enabled" value="1" <?php checked((int)$email_enabled, 1); ?>>
                      <?php esc_html_e('Send email notifications for bookings', 'bookpoint'); ?>
                    </label>
                  </td>
                </tr>

                <tr>
                  <th><label for="bp_admin_email"><?php esc_html_e('Admin email', 'bookpoint'); ?></label></th>
                  <td>
                    <input id="bp_admin_email" type="email" name="admin_email" value="<?php echo esc_attr($admin_email); ?>" class="regular-text">
                    <?php bp_err($errors,'admin_email'); ?>
                  </td>
                </tr>

                <tr>
                  <th><label for="bp_from_name"><?php esc_html_e('From name', 'bookpoint'); ?></label></th>
                  <td>
                    <input id="bp_from_name" type="text" name="from_name" value="<?php echo esc_attr($from_name); ?>" class="regular-text">
                  </td>
                </tr>

                <tr>
                  <th><label for="bp_from_email"><?php esc_html_e('From email', 'bookpoint'); ?></label></th>
                  <td>
                    <input id="bp_from_email" type="email" name="from_email" value="<?php echo esc_attr($from_email); ?>" class="regular-text">
                    <?php bp_err($errors,'from_email'); ?>
                  </td>
                </tr>
              </table>
              <?php endif; ?>

              <?php if ($tab === 'webhooks') : ?>
              <h2><?php esc_html_e('Webhooks', 'bookpoint'); ?></h2>
              <table class="form-table" role="presentation">
                <tr>
                  <th><?php esc_html_e('Enable Webhooks', 'bookpoint'); ?></th>
                  <td>
                    <label>
                      <input type="checkbox" name="webhooks_enabled" value="1" <?php checked((int)$webhooks_enabled, 1); ?>>
                      <?php esc_html_e('Send webhook events for bookings', 'bookpoint'); ?>
                    </label>
                  </td>
                </tr>
                <tr>
                  <th><label for="bp_webhook_secret"><?php esc_html_e('Webhook secret', 'bookpoint'); ?></label></th>
                  <td>
                    <input id="bp_webhook_secret" type="text" name="webhooks_secret" value="<?php echo esc_attr($webhooks_secret); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e('Used to sign webhook payloads (optional).', 'bookpoint'); ?></p>
                  </td>
                </tr>
                <tr>
                  <th><label for="bp_webhook_booking_created"><?php esc_html_e('Booking created URL', 'bookpoint'); ?></label></th>
                  <td>
                    <input id="bp_webhook_booking_created" type="url" name="webhooks_url_booking_created" value="<?php echo esc_attr($webhooks_url_booking_created); ?>" class="regular-text">
                  </td>
                </tr>
                <tr>
                  <th><label for="bp_webhook_booking_status_changed"><?php esc_html_e('Booking status changed URL', 'bookpoint'); ?></label></th>
                  <td>
                    <input id="bp_webhook_booking_status_changed" type="url" name="webhooks_url_booking_status_changed" value="<?php echo esc_attr($webhooks_url_booking_status_changed); ?>" class="regular-text">
                  </td>
                </tr>
                <tr>
                  <th><label for="bp_webhook_booking_updated"><?php esc_html_e('Booking updated URL', 'bookpoint'); ?></label></th>
                  <td>
                    <input id="bp_webhook_booking_updated" type="url" name="webhooks_url_booking_updated" value="<?php echo esc_attr($webhooks_url_booking_updated); ?>" class="regular-text">
                  </td>
                </tr>
                <tr>
                  <th><label for="bp_webhook_booking_cancelled"><?php esc_html_e('Booking cancelled URL', 'bookpoint'); ?></label></th>
                  <td>
                    <input id="bp_webhook_booking_cancelled" type="url" name="webhooks_url_booking_cancelled" value="<?php echo esc_attr($webhooks_url_booking_cancelled); ?>" class="regular-text">
                  </td>
                </tr>
              </table>
              <?php endif; ?>

              <p class="submit">
                <button type="submit" class="button button-primary"><?php echo esc_html__('Save Settings', 'bookpoint'); ?></button>
              </p>
            </form>
          <?php endif; ?>

          <?php if ($tab === 'license') : ?>
            <?php
              $badge = $license_status ?? 'unset';
              if (($license_status ?? '') === 'valid') $badge = 'valid';
              elseif (($license_status ?? '') === 'expired') $badge = 'expired';
              elseif (($license_status ?? '') === 'invalid') $badge = 'invalid';
              else $badge = 'unset';
            ?>
            <div style="max-width:900px;">
              <h2><?php esc_html_e('License', 'bookpoint'); ?></h2>
              <p><strong><?php esc_html_e('Status:', 'bookpoint'); ?></strong> <?php echo esc_html($badge); ?></p>
              <?php if (!empty($license_licensed_domain)) : ?>
                <p><strong><?php esc_html_e('Activated domain:', 'bookpoint'); ?></strong> <?php echo esc_html($license_licensed_domain); ?></p>
              <?php endif; ?>
              <?php if (!empty($license_expires_at)) : ?>
                <p><strong><?php esc_html_e('Expires:', 'bookpoint'); ?></strong> <?php echo esc_html($license_expires_at); ?></p>
              <?php endif; ?>
              <?php if (!empty($license_instance_id)) : ?>
                <p><strong><?php esc_html_e('Instance ID:', 'bookpoint'); ?></strong> <?php echo esc_html($license_instance_id); ?></p>
              <?php endif; ?>
              <?php if (!empty($license_checked_at)) : ?>
                <p class="description"><?php echo esc_html__('Last checked:', 'bookpoint') . ' ' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), (int)$license_checked_at)); ?></p>
              <?php endif; ?>
              <?php if (!empty($license_last_error)) : ?>
                <p class="description"><?php echo esc_html__('Message:', 'bookpoint') . ' ' . esc_html($license_last_error); ?></p>
              <?php endif; ?>

              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:14px;">
                <?php wp_nonce_field('bp_admin'); ?>
                <input type="hidden" name="action" value="bp_admin_settings_save_license">
                <table class="form-table">
                  <tr>
                    <th scope="row"><?php esc_html_e('License key', 'bookpoint'); ?></th>
                    <td>
                      <input type="text" class="regular-text" name="bp_license_key" value="<?php echo esc_attr($license_key ?? ''); ?>">
                      <p class="description"><?php esc_html_e('Paste your license key here and save.', 'bookpoint'); ?></p>
                    </td>
                  </tr>
                </table>
                <p class="submit">
                  <button class="button button-primary" type="submit"><?php esc_html_e('Save License', 'bookpoint'); ?></button>
                </p>
              </form>

              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('bp_admin'); ?>
                <input type="hidden" name="action" value="bp_admin_settings_validate_license">
                <button class="button" type="submit"><?php esc_html_e('Validate Now', 'bookpoint'); ?></button>
                <p class="description"><?php esc_html_e('Forces a license check immediately.', 'bookpoint'); ?></p>
              </form>

              <div style="display:flex;gap:10px;align-items:center;margin:12px 0 0;">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;">
                  <?php wp_nonce_field('bp_admin'); ?>
                  <input type="hidden" name="action" value="bp_admin_settings_activate_license">
                  <button class="button button-primary" type="submit"><?php esc_html_e('Activate License (this site)', 'bookpoint'); ?></button>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;">
                  <?php wp_nonce_field('bp_admin'); ?>
                  <input type="hidden" name="action" value="bp_admin_settings_deactivate_license">
                  <button class="button" type="submit" onclick="return confirm('<?php echo esc_js(__('Deactivate license for this site?', 'bookpoint')); ?>');"><?php esc_html_e('Deactivate', 'bookpoint'); ?></button>
                </form>
              </div>

              <hr>
              <h3><?php esc_html_e('Updates', 'bookpoint'); ?></h3>
              <p class="description"><?php esc_html_e('If your update server is configured, WordPress will show updates on the Plugins page.', 'bookpoint'); ?></p>
              <p><a class="button" href="<?php echo esc_url(admin_url('update-core.php')); ?>"><?php esc_html_e('Go to Updates', 'bookpoint'); ?></a></p>
            </div>
          <?php endif; ?>

          <?php if ($tab === 'import_export') : ?>
            <div style="max-width:900px;">
              <h2><?php esc_html_e('Import / Export', 'bookpoint'); ?></h2>

              <h3><?php esc_html_e('Export settings', 'bookpoint'); ?></h3>
              <p class="description"><?php esc_html_e('Downloads a JSON file with BookPoint settings.', 'bookpoint'); ?></p>
              <?php
                $export_url = wp_nonce_url(
                  admin_url('admin-post.php?action=bp_admin_settings_export_json'),
                  'bp_admin'
                );
              ?>
              <a class="button button-primary" href="<?php echo esc_url($export_url); ?>"><?php esc_html_e('Export JSON', 'bookpoint'); ?></a>

              <hr>

              <h3><?php esc_html_e('Import settings', 'bookpoint'); ?></h3>
              <p class="description"><?php esc_html_e('Upload a previously exported JSON file. Only whitelisted settings keys are imported.', 'bookpoint'); ?></p>

              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('bp_admin'); ?>
                <input type="hidden" name="action" value="bp_admin_settings_import_json">

                <input type="file" name="bp_settings_file" accept="application/json" required>
                <button class="button" type="submit" onclick="return confirm('Import settings JSON?');">
                  <?php esc_html_e('Import JSON', 'bookpoint'); ?>
                </button>
              </form>

              <?php if (!empty($_GET['import'])) : ?>
                <p style="margin-top:12px;">
                  <strong><?php echo esc_html__('Import result:', 'bookpoint'); ?></strong>
                  <?php echo esc_html(sanitize_text_field($_GET['import'])); ?>
                </p>
              <?php endif; ?>
            </div>
          <?php endif; ?>
<?php bp_render_legacy_shell_end(); ?>
