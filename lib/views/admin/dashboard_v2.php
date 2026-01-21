<?php defined('ABSPATH') || exit;
function bp_fmt_money($n){ return number_format((float)$n, 2); }

$rangeLabel = $range['range'] === 'custom'
  ? ($range['from'].' → '.$range['to'])
  : ($range['days'].' days');
?>

<div class="bp-admin-wrap">
  <div class="bp-admin-head">
    <div>
      <h1 class="bp-admin-title"><?php echo esc_html__('Dashboard', 'bookpoint'); ?></h1>
      <div class="bp-admin-sub"><?php echo esc_html__('Range:', 'bookpoint'); ?> <?php echo esc_html($rangeLabel); ?></div>
    </div>
  </div>

  <div class="bp-card" style="margin-bottom:12px;">
    <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
      <input type="hidden" name="page" value="bp_dashboard">
      <div>
        <div class="bp-kpi-label"><?php echo esc_html__('Range', 'bookpoint'); ?></div>
        <select name="range" class="bp-range-select">
          <option value="7" <?php selected($range['range'], '7'); ?>>7</option>
          <option value="14" <?php selected($range['range'], '14'); ?>>14</option>
          <option value="30" <?php selected($range['range'], '30'); ?>>30</option>
          <option value="custom" <?php selected($range['range'], 'custom'); ?>>Custom</option>
        </select>
      </div>

      <div>
        <div class="bp-kpi-label"><?php echo esc_html__('From', 'bookpoint'); ?></div>
        <input type="date" name="from" value="<?php echo esc_attr($range['from']); ?>">
      </div>

      <div>
        <div class="bp-kpi-label"><?php echo esc_html__('To', 'bookpoint'); ?></div>
        <input type="date" name="to" value="<?php echo esc_attr($range['to']); ?>">
      </div>

      <button class="bp-btn secondary" type="submit"><?php echo esc_html__('Apply', 'bookpoint'); ?></button>
    </form>
  </div>

  <div class="bp-kpi-grid">
    <div class="bp-card">
      <div class="bp-kpi-label"><?php echo esc_html__('Bookings', 'bookpoint'); ?></div>
      <div class="bp-kpi-value"><?php echo esc_html((string)$kpis['total']); ?></div>
      <div class="bp-kpi-foot"><?php echo esc_html($rangeLabel); ?></div>
    </div>

    <div class="bp-card">
      <div class="bp-kpi-label"><?php echo esc_html__('Pending', 'bookpoint'); ?></div>
      <div class="bp-kpi-value"><?php echo esc_html((string)$kpis['pending']); ?></div>
      <div class="bp-kpi-foot"><?php echo esc_html__('Waiting action', 'bookpoint'); ?></div>
    </div>

    <div class="bp-card">
      <div class="bp-kpi-label"><?php echo esc_html__('Revenue', 'bookpoint'); ?></div>
      <div class="bp-kpi-value">€ <?php echo esc_html(bp_fmt_money($kpis['revenue'])); ?></div>
      <div class="bp-kpi-foot"><?php echo esc_html__('Confirmed only', 'bookpoint'); ?></div>
    </div>

    <div class="bp-card">
      <div class="bp-kpi-label"><?php echo esc_html__('Cancelled', 'bookpoint'); ?></div>
      <div class="bp-kpi-value"><?php echo esc_html((string)$kpis['cancelled']); ?></div>
      <div class="bp-kpi-foot"><?php echo esc_html($rangeLabel); ?></div>
    </div>
  </div>

  <div class="bp-dash-grid" style="margin-top:12px;">
    <div class="bp-card">
      <div class="bp-card-title"><?php echo esc_html__('Bookings per day', 'bookpoint'); ?></div>
      <div class="bp-chart"
        data-labels="<?php echo esc_attr(wp_json_encode($series['labels'])); ?>"
        data-values="<?php echo esc_attr(wp_json_encode($series['values'])); ?>"></div>
      <div class="bp-chart-legend"><?php echo esc_html__('Hover shows date & count', 'bookpoint'); ?></div>
    </div>

    <div class="bp-card">
      <div class="bp-card-title"><?php echo esc_html__('Pending Bookings', 'bookpoint'); ?></div>

      <?php if (empty($pending)): ?>
        <div class="bp-muted"><?php echo esc_html__('No pending bookings.', 'bookpoint'); ?></div>
      <?php else: ?>
        <div style="display:grid;gap:10px;">
          <?php foreach ($pending as $p): ?>
            <div style="border:1px solid #e5e7eb;border-radius:14px;padding:12px;">
              <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;">
                <div>
                  <strong>#<?php echo esc_html((string)(int)$p['id']); ?> — <?php echo esc_html($p['customer_name']); ?></strong>
                  <div class="bp-muted"><?php echo esc_html($p['customer_email']); ?></div>
                  <div class="bp-muted"><?php echo esc_html(trim(($p['start_date'] ?? '').' '.($p['start_time'] ?? ''))); ?></div>
                </div>
                <div><strong>€ <?php echo esc_html(bp_fmt_money($p['total_price'] ?? 0)); ?></strong></div>
              </div>

              <div style="display:flex;gap:10px;margin-top:10px;">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                  <?php wp_nonce_field('bp_admin'); ?>
                  <input type="hidden" name="action" value="bp_admin_booking_quick_update">
                  <input type="hidden" name="id" value="<?php echo esc_attr((string)(int)$p['id']); ?>">
                  <input type="hidden" name="status" value="confirmed">
                  <button class="bp-btn" type="submit"><?php echo esc_html__('Confirm', 'bookpoint'); ?></button>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                  <?php wp_nonce_field('bp_admin'); ?>
                  <input type="hidden" name="action" value="bp_admin_booking_quick_update">
                  <input type="hidden" name="id" value="<?php echo esc_attr((string)(int)$p['id']); ?>">
                  <input type="hidden" name="status" value="cancelled">
                  <button class="bp-btn secondary" type="submit"><?php echo esc_html__('Cancel', 'bookpoint'); ?></button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="bp-dash-grid" style="margin-top:12px;">
    <div class="bp-card">
      <div class="bp-card-title"><?php echo esc_html__('Top Services', 'bookpoint'); ?></div>
      <?php if (empty($top_services)): ?>
        <div class="bp-muted"><?php echo esc_html__('No data yet.', 'bookpoint'); ?></div>
      <?php else: ?>
        <table class="widefat striped">
          <thead><tr><th>Name</th><th>Bookings</th><th>Revenue</th></tr></thead>
          <tbody>
            <?php foreach ($top_services as $r): ?>
              <tr>
                <td><?php echo esc_html($r['name']); ?></td>
                <td><?php echo esc_html((string)(int)$r['bookings']); ?></td>
                <td>€ <?php echo esc_html(bp_fmt_money($r['revenue'])); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="bp-card">
      <div class="bp-card-title"><?php echo esc_html__('Top Categories', 'bookpoint'); ?></div>
      <?php if (empty($top_categories)): ?>
        <div class="bp-muted"><?php echo esc_html__('No data yet.', 'bookpoint'); ?></div>
      <?php else: ?>
        <table class="widefat striped">
          <thead><tr><th>Name</th><th>Bookings</th><th>Revenue</th></tr></thead>
          <tbody>
            <?php foreach ($top_categories as $r): ?>
              <tr>
                <td><?php echo esc_html($r['name']); ?></td>
                <td><?php echo esc_html((string)(int)$r['bookings']); ?></td>
                <td>€ <?php echo esc_html(bp_fmt_money($r['revenue'])); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="bp-card">
      <div class="bp-card-title"><?php echo esc_html__('Top Agents', 'bookpoint'); ?></div>
      <?php if (empty($top_agents)): ?>
        <div class="bp-muted"><?php echo esc_html__('No data yet.', 'bookpoint'); ?></div>
      <?php else: ?>
        <table class="widefat striped">
          <thead><tr><th>Name</th><th>Bookings</th><th>Revenue</th></tr></thead>
          <tbody>
            <?php foreach ($top_agents as $r): ?>
              <tr>
                <td><?php echo esc_html($r['name']); ?></td>
                <td><?php echo esc_html((string)(int)$r['bookings']); ?></td>
                <td>€ <?php echo esc_html(bp_fmt_money($r['revenue'])); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <div class="bp-card" style="margin-top:12px;">
    <div class="bp-card-title"><?php echo esc_html__('Recent Bookings', 'bookpoint'); ?></div>

    <table class="widefat striped">
      <thead>
        <tr>
          <th><?php echo esc_html__('ID', 'bookpoint'); ?></th>
          <th><?php echo esc_html__('Customer', 'bookpoint'); ?></th>
          <th><?php echo esc_html__('Email', 'bookpoint'); ?></th>
          <th><?php echo esc_html__('When', 'bookpoint'); ?></th>
          <th><?php echo esc_html__('Status', 'bookpoint'); ?></th>
          <th><?php echo esc_html__('Total', 'bookpoint'); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($recent)): ?>
          <tr><td colspan="6" style="text-align:center;"><?php echo esc_html__('No bookings yet.', 'bookpoint'); ?></td></tr>
        <?php else: foreach ($recent as $b): ?>
          <tr>
            <td><strong>#<?php echo esc_html((string)(int)$b['id']); ?></strong></td>
            <td><?php echo esc_html((string)$b['customer_name']); ?></td>
            <td><?php echo esc_html((string)$b['customer_email']); ?></td>
            <td><?php echo esc_html(trim(($b['start_date'] ?? '').' '.($b['start_time'] ?? '')) ?: (string)$b['created_at']); ?></td>
            <td><span class="bp-badge bp-<?php echo esc_attr((string)$b['status']); ?>"><?php echo esc_html((string)$b['status']); ?></span></td>
            <td>€ <?php echo esc_html(bp_fmt_money($b['total_price'] ?? 0)); ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
