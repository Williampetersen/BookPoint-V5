<?php defined('ABSPATH') || exit;

$tabs = [
  'form' => __('Form Fields', 'bookpoint'),
  'customer' => __('Customer Fields', 'bookpoint'),
  'booking' => __('Booking Fields', 'bookpoint'),
];
?>

<div class="wrap">
  <h1 class="wp-heading-inline"><?php echo esc_html($tabs[$scope] ?? 'Fields'); ?></h1>
  <a class="page-title-action" href="<?php echo esc_url(admin_url('admin.php?page=bp_form_fields&scope='.$scope.'&action=edit')); ?>">
    <?php echo esc_html__('Add New', 'bookpoint'); ?>
  </a>
  <hr class="wp-header-end">

  <h2 class="nav-tab-wrapper">
    <?php foreach ($tabs as $key => $label): ?>
      <a class="nav-tab <?php echo $scope === $key ? 'nav-tab-active' : ''; ?>"
         href="<?php echo esc_url(admin_url('admin.php?page=bp_form_fields&scope='.$key)); ?>">
        <?php echo esc_html($label); ?>
      </a>
    <?php endforeach; ?>
  </h2>

  <form method="get" style="margin:12px 0;">
    <input type="hidden" name="page" value="bp_form_fields">
    <input type="hidden" name="scope" value="<?php echo esc_attr($scope); ?>">
    <input type="text" name="q" value="<?php echo esc_attr($q); ?>" placeholder="<?php echo esc_attr__('Search...', 'bookpoint'); ?>">
    <select name="is_active">
      <option value=""><?php echo esc_html__('All', 'bookpoint'); ?></option>
      <option value="1" <?php selected($is_active, '1'); ?>><?php echo esc_html__('Active', 'bookpoint'); ?></option>
      <option value="0" <?php selected($is_active, '0'); ?>><?php echo esc_html__('Inactive', 'bookpoint'); ?></option>
    </select>
    <button class="button"><?php echo esc_html__('Filter', 'bookpoint'); ?></button>
  </form>

  <table class="widefat striped">
    <thead>
      <tr>
        <th><?php echo esc_html__('Label', 'bookpoint'); ?></th>
        <th><?php echo esc_html__('Key', 'bookpoint'); ?></th>
        <th><?php echo esc_html__('Type', 'bookpoint'); ?></th>
        <th><?php echo esc_html__('Required', 'bookpoint'); ?></th>
        <th><?php echo esc_html__('Active', 'bookpoint'); ?></th>
        <th style="width:180px;"><?php echo esc_html__('Actions', 'bookpoint'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($items)): ?>
        <tr><td colspan="6" style="text-align:center;"><?php echo esc_html__('No fields yet.', 'bookpoint'); ?></td></tr>
      <?php else: foreach ($items as $row): ?>
        <tr>
          <td><strong><?php echo esc_html($row['label']); ?></strong></td>
          <td><code><?php echo esc_html($row['name_key']); ?></code></td>
          <td><?php echo esc_html($row['type']); ?></td>
          <td><?php echo ((int)$row['required'] === 1) ? '✅' : '—'; ?></td>
          <td><?php echo ((int)$row['is_active'] === 1) ? '✅' : '—'; ?></td>
          <td>
            <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=bp_form_fields&scope='.$scope.'&action=edit&id='.(int)$row['id'])); ?>">
              <?php echo esc_html__('Edit', 'bookpoint'); ?>
            </a>
            <a class="button button-small button-link-delete" href="<?php
              echo esc_url(wp_nonce_url(
                admin_url('admin.php?page=bp_form_fields&scope='.$scope.'&action=delete&id='.(int)$row['id']),
                'bp_admin'
              ));
            ?>" onclick="return confirm('Delete this field?');">
              <?php echo esc_html__('Delete', 'bookpoint'); ?>
            </a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
