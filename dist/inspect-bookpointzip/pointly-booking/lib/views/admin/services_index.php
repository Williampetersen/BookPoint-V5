<?php
defined('ABSPATH') || exit;
?>
<div class="wrap">
  <h1>
    <?php echo esc_html__('Services', 'pointly-booking'); ?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=pointlybooking_services_edit')); ?>" class="page-title-action">
      <?php echo esc_html__('Add New', 'pointly-booking'); ?>
    </a>
  </h1>

  <?php $pointlybooking_updated = sanitize_text_field(wp_unslash($_GET['updated'] ?? '')); ?>
  <?php if ($pointlybooking_updated !== '') : ?>
    <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Service saved.', 'pointly-booking'); ?></p></div>
  <?php endif; ?>
  <?php $pointlybooking_deleted = sanitize_text_field(wp_unslash($_GET['deleted'] ?? '')); ?>
  <?php if ($pointlybooking_deleted !== '') : ?>
    <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Service deleted.', 'pointly-booking'); ?></p></div>
  <?php endif; ?>

  <table class="widefat striped">
    <thead>
      <tr>
        <th style="width:60px;"><?php echo esc_html__('Image', 'pointly-booking'); ?></th>
        <th><?php echo esc_html__('ID', 'pointly-booking'); ?></th>
        <th><?php echo esc_html__('Name', 'pointly-booking'); ?></th>
        <th><?php echo esc_html__('Duration', 'pointly-booking'); ?></th>
        <th><?php echo esc_html__('Price', 'pointly-booking'); ?></th>
        <th><?php echo esc_html__('Active', 'pointly-booking'); ?></th>
        <th><?php echo esc_html__('Actions', 'pointly-booking'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($services)) : ?>
        <tr><td colspan="7"><?php echo esc_html__('No services yet.', 'pointly-booking'); ?></td></tr>
      <?php else : ?>
        <?php foreach ($services as $s) : ?>
          <?php
            $url = !empty($s['image_id']) ? wp_get_attachment_image_url((int)$s['image_id'], 'thumbnail') : '';
            $img = $url
              ? '<img src="' . esc_url($url) . '" style="width:44px;height:44px;border-radius:10px;object-fit:cover;">'
              : '<span class="bp-muted">-</span>';
          ?>
          <tr>
            <td><?php echo wp_kses_post($img); ?></td>
            <td><?php echo esc_html($s['id']); ?></td>
            <td><?php echo esc_html($s['name']); ?></td>
            <td><?php echo esc_html((int)$s['duration_minutes']); ?> <?php echo esc_html__('min', 'pointly-booking'); ?></td>
            <td><?php echo esc_html($s['currency']); ?> <?php echo esc_html(number_format_i18n(((int)$s['price_cents']) / 100, 2)); ?></td>
            <td><?php echo esc_html((int)$s['is_active'] === 1 ? __('Yes', 'pointly-booking') : __('No', 'pointly-booking')); ?></td>
            <td>
              <a href="<?php echo esc_url(admin_url('admin.php?page=pointlybooking_services_edit&id=' . absint($s['id']))); ?>">
                <?php echo esc_html__('Edit', 'pointly-booking'); ?>
              </a>
              |
              <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=pointlybooking_services_delete&id=' . absint($s['id'])), 'pointlybooking_admin')); ?>"
                 onclick="return confirm('<?php echo esc_js(__('Delete this service?', 'pointly-booking')); ?>');">
                 <?php echo esc_html__('Delete', 'pointly-booking'); ?>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
