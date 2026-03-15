<?php
defined('ABSPATH') || exit;

function pointlybooking_customer_name($row) {
  $name = trim(($row['customer_first_name'] ?? '') . ' ' . ($row['customer_last_name'] ?? ''));
  return $name !== '' ? $name : __('(No name)', 'bookpoint-booking');
}
?>
<div class="wrap">
  <h1><?php echo esc_html__('Bookings', 'bookpoint-booking'); ?></h1>

  <?php $pointlybooking_updated = sanitize_text_field(wp_unslash($_GET['updated'] ?? '')); ?>
  <?php if ($pointlybooking_updated !== '') : ?>
    <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Booking updated.', 'bookpoint-booking'); ?></p></div>
  <?php endif; ?>

  <?php $pointlybooking_pagination_state = $pagination ?? ['page' => 1, 'per_page' => 50, 'total' => 0]; ?>

  <?php $pointlybooking_filters = $filters ?? []; ?>
  <form method="get" style="margin:12px 0;">
    <input type="hidden" name="page" value="pointlybooking_bookings">
    <?php wp_nonce_field('pointlybooking_admin_filter', 'pointlybooking_filter_nonce'); ?>
    <input type="hidden" name="paged" value="<?php echo esc_attr((string)($pointlybooking_pagination_state['page'] ?? 1)); ?>">
    <input type="hidden" name="per_page" value="<?php echo esc_attr((string)($pointlybooking_pagination_state['per_page'] ?? 50)); ?>">

    <input type="text" name="q" value="<?php echo esc_attr($pointlybooking_filters['q'] ?? ''); ?>" placeholder="<?php esc_attr_e('Search customer/service/agent...', 'bookpoint-booking'); ?>" style="width:260px;">

    <select name="status">
      <option value=""><?php esc_html_e('All statuses', 'bookpoint-booking'); ?></option>
      <?php foreach (['pending','confirmed','cancelled'] as $st) : ?>
        <option value="<?php echo esc_attr($st); ?>" <?php selected(($pointlybooking_filters['status'] ?? ''), $st); ?>><?php echo esc_html(ucfirst($st)); ?></option>
      <?php endforeach; ?>
    </select>

    <select name="service_id">
      <option value="0"><?php esc_html_e('All services', 'bookpoint-booking'); ?></option>
      <?php foreach (($services ?? []) as $s) : ?>
        <option value="<?php echo esc_attr((string) (int) ($s['id'] ?? 0)); ?>" <?php selected((int)($pointlybooking_filters['service_id'] ?? 0), (int)$s['id']); ?>>
          <?php echo esc_html($s['name']); ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="agent_id">
      <option value="0"><?php esc_html_e('All agents', 'bookpoint-booking'); ?></option>
      <?php foreach (($agents ?? []) as $a) : ?>
        <option value="<?php echo esc_attr((string) (int) ($a['id'] ?? 0)); ?>" <?php selected((int)($pointlybooking_filters['agent_id'] ?? 0), (int)$a['id']); ?>>
          <?php echo esc_html(POINTLYBOOKING_AgentModel::display_name($a)); ?>
        </option>
      <?php endforeach; ?>
    </select>

    <input type="date" name="date_from" value="<?php echo esc_attr($pointlybooking_filters['date_from'] ?? ''); ?>">
    <input type="date" name="date_to" value="<?php echo esc_attr($pointlybooking_filters['date_to'] ?? ''); ?>">

    <button class="button"><?php esc_html_e('Filter', 'bookpoint-booking'); ?></button>
    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=pointlybooking_bookings')); ?>"><?php esc_html_e('Reset', 'bookpoint-booking'); ?></a>
    <?php
      $pointlybooking_safe_query = [];
      $pointlybooking_allowed_query_keys = ['page', 'paged', 'per_page', 'q', 'status', 'service_id', 'agent_id', 'date_from', 'date_to', 'pointlybooking_filter_nonce'];
      foreach ($pointlybooking_allowed_query_keys as $pointlybooking_allowed_key) {
        if (!isset($_GET[$pointlybooking_allowed_key])) continue;
        if (in_array($pointlybooking_allowed_key, ['service_id', 'agent_id', 'paged', 'per_page'], true)) {
          $pointlybooking_safe_query[$pointlybooking_allowed_key] = (string) absint(wp_unslash($_GET[$pointlybooking_allowed_key]));
          continue;
        }
        $pointlybooking_safe_query[$pointlybooking_allowed_key] = sanitize_text_field(wp_unslash($_GET[$pointlybooking_allowed_key]));
      }
      $pointlybooking_export_url = wp_nonce_url(
        add_query_arg(array_merge($pointlybooking_safe_query, ['action' => 'pointlybooking_admin_bookings_export_csv']), admin_url('admin-post.php')),
        'pointlybooking_admin'
      );
    ?>
    <a class="button" href="<?php echo esc_url($pointlybooking_export_url); ?>"><?php esc_html_e('Export CSV', 'bookpoint-booking'); ?></a>
  </form>

  <?php
    $total_pages = (int)ceil(($pointlybooking_pagination_state['total'] ?? 0) / max(1, (int)($pointlybooking_pagination_state['per_page'] ?? 50)));
    $pointlybooking_paged_base = (string) wp_unslash($_SERVER['REQUEST_URI'] ?? '');
    if ($pointlybooking_paged_base === '') {
      $pointlybooking_paged_base = admin_url('admin.php?page=pointlybooking_bookings');
    }
    if ($total_pages > 1) {
      echo wp_kses_post(paginate_links([
        'base' => add_query_arg('paged', '%#%', $pointlybooking_paged_base),
        'format' => '',
        'current' => max(1, (int)($pointlybooking_pagination_state['page'] ?? 1)),
        'total' => $total_pages,
      ]));
    }
  ?>

  <table class="widefat striped">
    <thead>
      <tr>
        <th><?php echo esc_html__('ID', 'bookpoint-booking'); ?></th>
        <th><?php echo esc_html__('Service', 'bookpoint-booking'); ?></th>
        <th><?php echo esc_html__('Customer', 'bookpoint-booking'); ?></th>
        <th><?php echo esc_html__('Email', 'bookpoint-booking'); ?></th>
        <th><?php echo esc_html__('Agent', 'bookpoint-booking'); ?></th>
        <th><?php echo esc_html__('Start', 'bookpoint-booking'); ?></th>
        <th><?php echo esc_html__('End', 'bookpoint-booking'); ?></th>
        <th><?php echo esc_html__('Status', 'bookpoint-booking'); ?></th>
        <th><?php echo esc_html__('Actions', 'bookpoint-booking'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($items)) : ?>
        <tr><td colspan="9"><?php echo esc_html__('No bookings yet.', 'bookpoint-booking'); ?></td></tr>
      <?php else : ?>
        <?php foreach ($items as $b) : ?>
          <tr>
            <td><?php echo esc_html($b['id']); ?></td>
            <td><?php echo esc_html($b['service_name'] ?? '-'); ?></td>
            <td><?php echo esc_html($b['customer_name'] ?? pointlybooking_customer_name($b)); ?></td>
            <td><?php echo esc_html($b['customer_email'] ?? '-'); ?></td>
            <td><?php echo esc_html($b['agent_name'] ?? '-'); ?></td>
            <td><?php echo esc_html($b['start_datetime']); ?></td>
            <td><?php echo esc_html($b['end_datetime']); ?></td>
            <td><?php echo esc_html($b['status']); ?></td>
            <td>
              <?php
                $confirm_url = wp_nonce_url(admin_url('admin.php?page=pointlybooking_booking_confirm&id=' . absint($b['id'])), 'pointlybooking_admin');
                $cancel_url  = wp_nonce_url(admin_url('admin.php?page=pointlybooking_booking_cancel&id=' . absint($b['id'])), 'pointlybooking_admin');
              ?>
              <a class="button button-small" href="<?php echo esc_url($confirm_url); ?>"><?php echo esc_html__('Confirm', 'bookpoint-booking'); ?></a>
              <a class="button button-small" href="<?php echo esc_url($cancel_url); ?>"
                 onclick="return confirm('<?php echo esc_js(__('Cancel this booking?', 'bookpoint-booking')); ?>');">
                <?php echo esc_html__('Cancel', 'bookpoint-booking'); ?>
              </a>

              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:8px;">
                <?php wp_nonce_field('pointlybooking_admin'); ?>
                <input type="hidden" name="action" value="pointlybooking_admin_booking_notes_save">
                <input type="hidden" name="id" value="<?php echo esc_attr((string) (int) ($b['id'] ?? 0)); ?>">
                <textarea name="notes" rows="2" style="width:100%; max-width:420px;"><?php echo esc_textarea($b['notes'] ?? ''); ?></textarea>
                <button class="button button-small"><?php esc_html_e('Save Notes', 'bookpoint-booking'); ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <?php
    if ($total_pages > 1) {
      echo wp_kses_post(paginate_links([
        'base' => add_query_arg('paged', '%#%', $pointlybooking_paged_base),
        'format' => '',
        'current' => max(1, (int)($pointlybooking_pagination_state['page'] ?? 1)),
        'total' => $total_pages,
      ]));
    }
  ?>
</div>
