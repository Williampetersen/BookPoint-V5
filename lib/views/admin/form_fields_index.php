<?php defined('ABSPATH') || exit;

$tabs = [
  'form' => __('Form Fields', 'bookpoint-booking'),
  'customer' => __('Customer Fields', 'bookpoint-booking'),
  'booking' => __('Booking Fields', 'bookpoint-booking'),
];
?>

<div class="wrap">
  <h1 class="wp-heading-inline"><?php echo esc_html($tabs[$scope] ?? 'Fields'); ?></h1>
  <a class="page-title-action" href="<?php echo esc_url(admin_url('admin.php?page=pointlybooking_form_fields&scope='.$scope.'&action=edit')); ?>">
    <?php echo esc_html__('Add New', 'bookpoint-booking'); ?>
  </a>
  <hr class="wp-header-end">

  <h2 class="nav-tab-wrapper">
    <?php foreach ($tabs as $key => $label): ?>
      <a class="nav-tab <?php echo esc_attr($scope === $key ? 'nav-tab-active' : ''); ?>"
         href="<?php echo esc_url(admin_url('admin.php?page=pointlybooking_form_fields&scope='.$key)); ?>">
        <?php echo esc_html($label); ?>
      </a>
    <?php endforeach; ?>
  </h2>

  <form method="get" style="margin:12px 0;">
    <input type="hidden" name="page" value="pointlybooking_form_fields">
    <input type="hidden" name="scope" value="<?php echo esc_attr($scope); ?>">
    <?php wp_nonce_field('pointlybooking_admin_filter', 'pointlybooking_filter_nonce'); ?>
    <input type="text" name="q" value="<?php echo esc_attr($q); ?>" placeholder="<?php echo esc_attr__('Search...', 'bookpoint-booking'); ?>">
    <select name="is_active">
      <option value=""><?php echo esc_html__('All', 'bookpoint-booking'); ?></option>
      <option value="1" <?php selected($is_active, '1'); ?>><?php echo esc_html__('Active', 'bookpoint-booking'); ?></option>
      <option value="0" <?php selected($is_active, '0'); ?>><?php echo esc_html__('Inactive', 'bookpoint-booking'); ?></option>
    </select>
    <button class="button"><?php echo esc_html__('Filter', 'bookpoint-booking'); ?></button>
  </form>

  <table class="widefat striped">
    <thead>
      <tr>
        <th><?php echo esc_html__('Label', 'bookpoint-booking'); ?></th>
        <th><?php echo esc_html__('Key', 'bookpoint-booking'); ?></th>
        <th><?php echo esc_html__('Type', 'bookpoint-booking'); ?></th>
        <th><?php echo esc_html__('Required', 'bookpoint-booking'); ?></th>
        <th><?php echo esc_html__('Active', 'bookpoint-booking'); ?></th>
        <th style="width:180px;"><?php echo esc_html__('Actions', 'bookpoint-booking'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($items)): ?>
        <tr><td colspan="6" style="text-align:center;"><?php echo esc_html__('No fields yet.', 'bookpoint-booking'); ?></td></tr>
      <?php else: foreach ($items as $row): ?>
        <tr>
          <td><strong><?php echo esc_html($row['label']); ?></strong></td>
          <td><code><?php echo esc_html($row['name_key']); ?></code></td>
          <td><?php echo esc_html($row['type']); ?></td>
          <td><?php echo ((int)$row['required'] === 1) ? esc_html__('Yes', 'bookpoint-booking') : esc_html__('No', 'bookpoint-booking'); ?></td>
          <td><?php echo ((int)$row['is_active'] === 1) ? esc_html__('Yes', 'bookpoint-booking') : esc_html__('No', 'bookpoint-booking'); ?></td>
          <td>
            <a class="button button-small" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=pointlybooking_form_fields&scope='.$scope.'&action=edit&id='.(int)$row['id']), 'pointlybooking_edit_form_field_' . (int)$row['id'], 'pointlybooking_edit_nonce')); ?>">
              <?php echo esc_html__('Edit', 'bookpoint-booking'); ?>
            </a>
            <a class="button button-small button-link-delete" href="<?php
              echo esc_url(wp_nonce_url(
                admin_url('admin.php?page=pointlybooking_form_fields&scope='.$scope.'&action=delete&id='.(int)$row['id']),
                'pointlybooking_admin'
              ));
            ?>" onclick="return confirm('Delete this field?');">
              <?php echo esc_html__('Delete', 'bookpoint-booking'); ?>
            </a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

