<?php
defined('ABSPATH') || exit;

function bp_customer_name($row) {
  $name = trim(($row['customer_first_name'] ?? '') . ' ' . ($row['customer_last_name'] ?? ''));
  return $name !== '' ? $name : __('(No name)', 'bookpoint');
}
?>
<div class="wrap">
  <h1><?php echo esc_html__('Bookings', 'bookpoint'); ?></h1>

  <?php if (isset($_GET['updated'])) : ?>
    <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Booking updated.', 'bookpoint'); ?></p></div>
  <?php endif; ?>

  <?php $p = $pagination ?? ['page' => 1, 'per_page' => 50, 'total' => 0]; ?>

  <?php $f = $filters ?? []; ?>
  <form method="get" style="margin:12px 0;">
    <input type="hidden" name="page" value="bp_bookings">
    <input type="hidden" name="paged" value="<?php echo esc_attr((string)($p['page'] ?? 1)); ?>">
    <input type="hidden" name="per_page" value="<?php echo esc_attr((string)($p['per_page'] ?? 50)); ?>">

    <input type="text" name="q" value="<?php echo esc_attr($f['q'] ?? ''); ?>" placeholder="<?php esc_attr_e('Search customer/service/agent...', 'bookpoint'); ?>" style="width:260px;">

    <select name="status">
      <option value=""><?php esc_html_e('All statuses', 'bookpoint'); ?></option>
      <?php foreach (['pending','confirmed','cancelled'] as $st) : ?>
        <option value="<?php echo esc_attr($st); ?>" <?php selected(($f['status'] ?? ''), $st); ?>><?php echo esc_html(ucfirst($st)); ?></option>
      <?php endforeach; ?>
    </select>

    <select name="service_id">
      <option value="0"><?php esc_html_e('All services', 'bookpoint'); ?></option>
      <?php foreach (($services ?? []) as $s) : ?>
        <option value="<?php echo (int)$s['id']; ?>" <?php selected((int)($f['service_id'] ?? 0), (int)$s['id']); ?>>
          <?php echo esc_html($s['name']); ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="agent_id">
      <option value="0"><?php esc_html_e('All agents', 'bookpoint'); ?></option>
      <?php foreach (($agents ?? []) as $a) : ?>
        <option value="<?php echo (int)$a['id']; ?>" <?php selected((int)($f['agent_id'] ?? 0), (int)$a['id']); ?>>
          <?php echo esc_html(BP_AgentModel::display_name($a)); ?>
        </option>
      <?php endforeach; ?>
    </select>

    <input type="date" name="date_from" value="<?php echo esc_attr($f['date_from'] ?? ''); ?>">
    <input type="date" name="date_to" value="<?php echo esc_attr($f['date_to'] ?? ''); ?>">

    <button class="button"><?php esc_html_e('Filter', 'bookpoint'); ?></button>
    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=bp_bookings')); ?>"><?php esc_html_e('Reset', 'bookpoint'); ?></a>
    <?php
      $export_url = wp_nonce_url(
        add_query_arg(array_merge($_GET, ['action' => 'bp_admin_bookings_export_csv']), admin_url('admin-post.php')),
        'bp_admin'
      );
    ?>
    <a class="button" href="<?php echo esc_url($export_url); ?>"><?php esc_html_e('Export CSV', 'bookpoint'); ?></a>
  </form>

  <?php
    $total_pages = (int)ceil(($p['total'] ?? 0) / max(1, (int)($p['per_page'] ?? 50)));
    if ($total_pages > 1) {
      echo paginate_links([
        'base' => add_query_arg('paged', '%#%'),
        'format' => '',
        'current' => max(1, (int)($p['page'] ?? 1)),
        'total' => $total_pages,
      ]);
    }
  ?>

  <table class="widefat striped">
    <thead>
      <tr>
        <th><?php echo esc_html__('ID', 'bookpoint'); ?></th>
        <th><?php echo esc_html__('Service', 'bookpoint'); ?></th>
        <th><?php echo esc_html__('Customer', 'bookpoint'); ?></th>
        <th><?php echo esc_html__('Email', 'bookpoint'); ?></th>
        <th><?php echo esc_html__('Agent', 'bookpoint'); ?></th>
        <th><?php echo esc_html__('Start', 'bookpoint'); ?></th>
        <th><?php echo esc_html__('End', 'bookpoint'); ?></th>
        <th><?php echo esc_html__('Status', 'bookpoint'); ?></th>
        <th><?php echo esc_html__('Actions', 'bookpoint'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($items)) : ?>
        <tr><td colspan="9"><?php echo esc_html__('No bookings yet.', 'bookpoint'); ?></td></tr>
      <?php else : ?>
        <?php foreach ($items as $b) : ?>
          <tr>
            <td><?php echo esc_html($b['id']); ?></td>
            <td><?php echo esc_html($b['service_name'] ?? '-'); ?></td>
            <td><?php echo esc_html($b['customer_name'] ?? bp_customer_name($b)); ?></td>
            <td><?php echo esc_html($b['customer_email'] ?? '-'); ?></td>
            <td><?php echo esc_html($b['agent_name'] ?? '-'); ?></td>
            <td><?php echo esc_html($b['start_datetime']); ?></td>
            <td><?php echo esc_html($b['end_datetime']); ?></td>
            <td><?php echo esc_html($b['status']); ?></td>
            <td>
              <?php
                $confirm_url = wp_nonce_url(admin_url('admin.php?page=bp_booking_confirm&id=' . absint($b['id'])), 'bp_admin');
                $cancel_url  = wp_nonce_url(admin_url('admin.php?page=bp_booking_cancel&id=' . absint($b['id'])), 'bp_admin');
              ?>
              <a class="button button-small" href="<?php echo esc_url($confirm_url); ?>"><?php echo esc_html__('Confirm', 'bookpoint'); ?></a>
              <a class="button button-small" href="<?php echo esc_url($cancel_url); ?>"
                 onclick="return confirm('<?php echo esc_js(__('Cancel this booking?', 'bookpoint')); ?>');">
                <?php echo esc_html__('Cancel', 'bookpoint'); ?>
              </a>

              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:8px;">
                <?php wp_nonce_field('bp_admin'); ?>
                <input type="hidden" name="action" value="bp_admin_booking_notes_save">
                <input type="hidden" name="id" value="<?php echo (int)$b['id']; ?>">
                <textarea name="notes" rows="2" style="width:100%; max-width:420px;"><?php echo esc_textarea($b['notes'] ?? ''); ?></textarea>
                <button class="button button-small"><?php esc_html_e('Save Notes', 'bookpoint'); ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <?php
    if ($total_pages > 1) {
      echo paginate_links([
        'base' => add_query_arg('paged', '%#%'),
        'format' => '',
        'current' => max(1, (int)($p['page'] ?? 1)),
        'total' => $total_pages,
      ]);
    }
  ?>
</div>
