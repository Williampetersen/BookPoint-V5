<?php defined('ABSPATH') || exit;
$f = $filters ?? [];
?>

<div class="wrap">
  <h1><?php esc_html_e('Audit Log', 'bookpoint'); ?></h1>

  <?php $p = $pagination ?? ['page' => 1, 'per_page' => 50, 'total' => 0]; ?>

  <form method="get" style="margin:12px 0;">
    <input type="hidden" name="page" value="bp_audit">
    <input type="hidden" name="paged" value="<?php echo esc_attr((string)($p['page'] ?? 1)); ?>">
    <input type="hidden" name="per_page" value="<?php echo esc_attr((string)($p['per_page'] ?? 50)); ?>">

    <select name="event">
      <option value=""><?php esc_html_e('All events', 'bookpoint'); ?></option>
      <?php foreach (($events ?? []) as $ev) : ?>
        <option value="<?php echo esc_attr($ev); ?>" <?php selected(($f['event'] ?? ''), $ev); ?>>
          <?php echo esc_html($ev); ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="actor_type">
      <option value=""><?php esc_html_e('All actors', 'bookpoint'); ?></option>
      <?php foreach (['admin','customer','system'] as $at) : ?>
        <option value="<?php echo esc_attr($at); ?>" <?php selected(($f['actor_type'] ?? ''), $at); ?>>
          <?php echo esc_html(ucfirst($at)); ?>
        </option>
      <?php endforeach; ?>
    </select>

    <input type="number" name="booking_id" placeholder="Booking ID" value="<?php echo esc_attr((string)($f['booking_id'] ?? '')); ?>" style="width:120px;">
    <input type="number" name="customer_id" placeholder="Customer ID" value="<?php echo esc_attr((string)($f['customer_id'] ?? '')); ?>" style="width:120px;">

    <input type="date" name="date_from" value="<?php echo esc_attr($f['date_from'] ?? ''); ?>">
    <input type="date" name="date_to" value="<?php echo esc_attr($f['date_to'] ?? ''); ?>">

    <button class="button"><?php esc_html_e('Filter', 'bookpoint'); ?></button>
    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=bp_audit')); ?>"><?php esc_html_e('Reset', 'bookpoint'); ?></a>
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
        <th><?php esc_html_e('ID', 'bookpoint'); ?></th>
        <th><?php esc_html_e('Time', 'bookpoint'); ?></th>
        <th><?php esc_html_e('Event', 'bookpoint'); ?></th>
        <th><?php esc_html_e('Actor', 'bookpoint'); ?></th>
        <th><?php esc_html_e('Booking', 'bookpoint'); ?></th>
        <th><?php esc_html_e('Customer', 'bookpoint'); ?></th>
        <th><?php esc_html_e('Meta', 'bookpoint'); ?></th>
        <th><?php esc_html_e('IP', 'bookpoint'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($items)) : ?>
        <tr><td colspan="8"><?php esc_html_e('No logs found.', 'bookpoint'); ?></td></tr>
      <?php else : foreach ($items as $r) : ?>
        <tr>
          <td><?php echo (int)$r['id']; ?></td>
          <td><?php echo esc_html($r['created_at'] ?? ''); ?></td>
          <td><?php echo esc_html($r['event'] ?? ''); ?></td>
          <td>
            <?php
              $actor = esc_html($r['actor_type'] ?? '');
              $uid = (int)($r['actor_wp_user_id'] ?? 0);
              echo $actor . ($uid ? ' (#' . $uid . ')' : '');
            ?>
          </td>
          <td><?php echo (int)($r['booking_id'] ?? 0); ?></td>
          <td><?php echo (int)($r['customer_id'] ?? 0); ?></td>
          <td><code style="white-space:pre-wrap;display:block;max-width:520px;"><?php echo esc_html($r['meta'] ?? ''); ?></code></td>
          <td><?php echo esc_html($r['actor_ip'] ?? ''); ?></td>
        </tr>
      <?php endforeach; endif; ?>
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
