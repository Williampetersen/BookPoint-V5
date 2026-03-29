<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
defined('ABSPATH') || exit;
require_once __DIR__ . '/legacy_shell.php';

$id = (int)($item['id'] ?? 0);
$code = (string)($item['code'] ?? '');
$type = (string)($item['type'] ?? 'percent');
$amount = (string)($item['amount'] ?? '0');
$starts_at = (string)($item['starts_at'] ?? '');
$ends_at = (string)($item['ends_at'] ?? '');
$max_uses = isset($item['max_uses']) ? (string)$item['max_uses'] : '';
$min_total = isset($item['min_total']) ? (string)$item['min_total'] : '';
$is_active = isset($item['is_active']) ? (int)$item['is_active'] : 1;

$pointlybooking_actions_html = '<a class="bp-top-btn" href="' . esc_url(admin_url('admin.php?page=pointlybooking_promo_codes')) . '">' . esc_html__('Back to Promo Codes', 'bookpoint-booking') . '</a>';
pointlybooking_render_legacy_shell_start(
  $id ? esc_html__('Edit Promo Code', 'bookpoint-booking') : esc_html__('Add Promo Code', 'bookpoint-booking'),
  esc_html__('Create discounts and manage usage limits.', 'bookpoint-booking'),
  $pointlybooking_actions_html,
  'promo-codes'
);
?>

  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('pointlybooking_admin'); ?>
    <input type="hidden" name="action" value="pointlybooking_admin_promo_codes_save">
    <input type="hidden" name="id" value="<?php echo esc_attr((string)$id); ?>">

    <table class="form-table">
      <tr>
        <th><label><?php echo esc_html__('Code', 'bookpoint-booking'); ?></label></th>
        <td><input type="text" name="code" value="<?php echo esc_attr($code); ?>" class="regular-text" required placeholder="SAVE10"></td>
      </tr>

      <tr>
        <th><label><?php echo esc_html__('Type', 'bookpoint-booking'); ?></label></th>
        <td>
          <select name="type">
            <option value="percent" <?php selected($type, 'percent'); ?>><?php echo esc_html__('Percent', 'bookpoint-booking'); ?></option>
            <option value="fixed" <?php selected($type, 'fixed'); ?>><?php echo esc_html__('Fixed', 'bookpoint-booking'); ?></option>
          </select>
        </td>
      </tr>

      <tr>
        <th><label><?php echo esc_html__('Amount', 'bookpoint-booking'); ?></label></th>
        <td><input type="number" step="0.01" name="amount" value="<?php echo esc_attr($amount); ?>" class="small-text" required></td>
      </tr>

      <tr>
        <th><label><?php echo esc_html__('Starts At', 'bookpoint-booking'); ?></label></th>
        <td><input type="datetime-local" name="starts_at" value="<?php echo esc_attr($starts_at ? gmdate('Y-m-d\TH:i', strtotime($starts_at)) : ''); ?>"></td>
      </tr>

      <tr>
        <th><label><?php echo esc_html__('Ends At', 'bookpoint-booking'); ?></label></th>
        <td><input type="datetime-local" name="ends_at" value="<?php echo esc_attr($ends_at ? gmdate('Y-m-d\TH:i', strtotime($ends_at)) : ''); ?>"></td>
      </tr>

      <tr>
        <th><label><?php echo esc_html__('Max Uses', 'bookpoint-booking'); ?></label></th>
        <td><input type="number" name="max_uses" value="<?php echo esc_attr($max_uses); ?>" class="small-text" placeholder="(optional)"></td>
      </tr>

      <tr>
        <th><label><?php echo esc_html__('Min Total', 'bookpoint-booking'); ?></label></th>
        <td><input type="number" step="0.01" name="min_total" value="<?php echo esc_attr($min_total); ?>" class="small-text" placeholder="(optional)"></td>
      </tr>

      <tr>
        <th><label><?php echo esc_html__('Active', 'bookpoint-booking'); ?></label></th>
        <td><label><input type="checkbox" name="is_active" value="1" <?php checked($is_active, 1); ?>> <?php echo esc_html__('Enabled', 'bookpoint-booking'); ?></label></td>
      </tr>
    </table>

    <p class="submit">
      <button type="submit" class="bp-btn bp-btn-primary">
        <?php echo esc_html($id ? __('Save Changes', 'bookpoint-booking') : __('Create Promo Code', 'bookpoint-booking')); ?>
      </button>
      <a class="bp-btn" href="<?php echo esc_url(admin_url('admin.php?page=pointlybooking_promo_codes')); ?>">
        <?php echo esc_html__('Back', 'bookpoint-booking'); ?>
      </a>
      <?php if ($id): ?>
        <a class="bp-btn bp-btn-danger" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=pointlybooking_promo_codes_delete&id=' . absint($id)), 'pointlybooking_admin')); ?>" onclick="return confirm('<?php echo esc_js(__('Delete promo code?', 'bookpoint-booking')); ?>');">
          <?php echo esc_html__('Delete', 'bookpoint-booking'); ?>
        </a>
      <?php endif; ?>
    </p>
  </form>

<?php pointlybooking_render_legacy_shell_end(); ?>

