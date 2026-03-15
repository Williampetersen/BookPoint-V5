<?php
// phpcs:disable WordPress.Security.NonceVerification.Recommended
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
defined('ABSPATH') || exit;
$pointlybooking_filters = $filters ?? [];
require_once __DIR__ . '/legacy_shell.php';
pointlybooking_render_legacy_shell_start(esc_html__('Audit Log', 'bookpoint-booking'), esc_html__('Review system activity and changes.', 'bookpoint-booking'), '', 'audit');
?>

  <?php $pointlybooking_pagination_state = $pagination ?? ['page' => 1, 'per_page' => 50, 'total' => 0]; ?>

  <form method="get" style="margin:12px 0;">
    <input type="hidden" name="page" value="pointlybooking_audit">
    <?php wp_nonce_field('pointlybooking_admin_filter', 'pointlybooking_filter_nonce'); ?>
    <input type="hidden" name="paged" value="<?php echo esc_attr((string)($pointlybooking_pagination_state['page'] ?? 1)); ?>">
    <input type="hidden" name="per_page" value="<?php echo esc_attr((string)($pointlybooking_pagination_state['per_page'] ?? 50)); ?>">

    <select name="event">
      <option value=""><?php esc_html_e('All events', 'bookpoint-booking'); ?></option>
      <?php foreach (($events ?? []) as $ev) : ?>
        <option value="<?php echo esc_attr($ev); ?>" <?php selected(($pointlybooking_filters['event'] ?? ''), $ev); ?>>
          <?php echo esc_html($ev); ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="actor_type">
      <option value=""><?php esc_html_e('All actors', 'bookpoint-booking'); ?></option>
      <?php foreach (['admin','customer','system'] as $at) : ?>
        <option value="<?php echo esc_attr($at); ?>" <?php selected(($pointlybooking_filters['actor_type'] ?? ''), $at); ?>>
          <?php echo esc_html(ucfirst($at)); ?>
        </option>
      <?php endforeach; ?>
    </select>

    <input type="number" name="booking_id" placeholder="Booking ID" value="<?php echo esc_attr((string)($pointlybooking_filters['booking_id'] ?? '')); ?>" style="width:120px;">
    <input type="number" name="customer_id" placeholder="Customer ID" value="<?php echo esc_attr((string)($pointlybooking_filters['customer_id'] ?? '')); ?>" style="width:120px;">

    <input type="date" name="date_from" value="<?php echo esc_attr($pointlybooking_filters['date_from'] ?? ''); ?>">
    <input type="date" name="date_to" value="<?php echo esc_attr($pointlybooking_filters['date_to'] ?? ''); ?>">

    <button class="button"><?php esc_html_e('Filter', 'bookpoint-booking'); ?></button>
    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=pointlybooking_audit')); ?>"><?php esc_html_e('Reset', 'bookpoint-booking'); ?></a>
  </form>

  <?php
    $total_pages = (int)ceil(($pointlybooking_pagination_state['total'] ?? 0) / max(1, (int)($pointlybooking_pagination_state['per_page'] ?? 50)));
    $pointlybooking_paged_base = sanitize_text_field((string) wp_unslash($_SERVER['REQUEST_URI'] ?? ''));
    if ($pointlybooking_paged_base === '') {
      $pointlybooking_paged_base = admin_url('admin.php?page=pointlybooking_audit');
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
        <th><?php esc_html_e('ID', 'bookpoint-booking'); ?></th>
        <th><?php esc_html_e('Time', 'bookpoint-booking'); ?></th>
        <th><?php esc_html_e('Event', 'bookpoint-booking'); ?></th>
        <th><?php esc_html_e('Actor', 'bookpoint-booking'); ?></th>
        <th><?php esc_html_e('Booking', 'bookpoint-booking'); ?></th>
        <th><?php esc_html_e('Customer', 'bookpoint-booking'); ?></th>
        <th><?php esc_html_e('Meta', 'bookpoint-booking'); ?></th>
        <th><?php esc_html_e('IP', 'bookpoint-booking'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($items)) : ?>
        <tr><td colspan="8"><?php esc_html_e('No logs found.', 'bookpoint-booking'); ?></td></tr>
      <?php else : foreach ($items as $r) : ?>
        <tr>
          <td><?php echo esc_html((string) (int) ($r['id'] ?? 0)); ?></td>
          <td><?php echo esc_html($r['created_at'] ?? ''); ?></td>
          <td><?php echo esc_html($r['event'] ?? ''); ?></td>
          <td>
            <?php
              $pointlybooking_actor = (string) ($r['actor_type'] ?? '');
              $pointlybooking_uid = (int) ($r['actor_wp_user_id'] ?? 0);
              $pointlybooking_actor_label = $pointlybooking_uid > 0
                ? sprintf('%1$s (#%2$d)', $pointlybooking_actor, $pointlybooking_uid)
                : $pointlybooking_actor;
              echo esc_html($pointlybooking_actor_label);
            ?>
          </td>
          <td><?php echo esc_html((string) (int) ($r['booking_id'] ?? 0)); ?></td>
          <td><?php echo esc_html((string) (int) ($r['customer_id'] ?? 0)); ?></td>
          <td><code style="white-space:pre-wrap;display:block;max-width:520px;"><?php echo esc_html($r['meta'] ?? ''); ?></code></td>
          <td><?php echo esc_html($r['actor_ip'] ?? ''); ?></td>
        </tr>
      <?php endforeach; endif; ?>
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
<?php pointlybooking_render_legacy_shell_end(); ?>

