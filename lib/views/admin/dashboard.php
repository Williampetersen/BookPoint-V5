<?php defined('ABSPATH') || exit;

function bp_fmt_money($n){ return number_format((float)$n, 2); }
?>

<div class="bp-admin-wrap">
  <div class="bp-admin-head">
    <div>
      <h1 class="bp-admin-title"><?php echo esc_html__('Dashboard', 'bookpoint'); ?></h1>
      <div class="bp-admin-sub"><?php echo esc_html__('Overview of bookings and performance.', 'bookpoint'); ?></div>
    </div>

    <div class="bp-admin-actions">
      <a class="bp-btn secondary" href="<?php echo esc_url(admin_url('admin.php?page=bp_bookings')); ?>">
        <?php echo esc_html__('Manage Bookings', 'bookpoint'); ?>
      </a>
      <a class="bp-btn" href="<?php echo esc_url(admin_url('admin.php?page=bp_services')); ?>">
        <?php echo esc_html__('Add Service', 'bookpoint'); ?>
      </a>
    </div>
  </div>

  <div class="bp-kpi-grid">
    <div class="bp-card">
      <div class="bp-kpi-label"><?php echo esc_html__('Bookings Today', 'bookpoint'); ?></div>
      <div class="bp-kpi-value"><?php echo esc_html((string)$kpis['today_count']); ?></div>
      <div class="bp-kpi-foot"><?php echo esc_html($kpis['today']); ?></div>
    </div>

    <div class="bp-card">
      <div class="bp-kpi-label"><?php echo esc_html__('Pending', 'bookpoint'); ?></div>
      <div class="bp-kpi-value"><?php echo esc_html((string)$kpis['pending_count']); ?></div>
      <div class="bp-kpi-foot"><?php echo esc_html__('Needs confirmation', 'bookpoint'); ?></div>
    </div>

    <div class="bp-card">
      <div class="bp-kpi-label"><?php echo esc_html__('Revenue (7 days)', 'bookpoint'); ?></div>
      <div class="bp-kpi-value">€ <?php echo esc_html(bp_fmt_money($kpis['revenue_7d'])); ?></div>
      <div class="bp-kpi-foot"><?php echo esc_html($kpis['since7'] . ' → ' . $kpis['today']); ?></div>
    </div>

    <div class="bp-card">
      <div class="bp-kpi-label"><?php echo esc_html__('Cancelled (30 days)', 'bookpoint'); ?></div>
      <div class="bp-kpi-value"><?php echo esc_html((string)$kpis['cancel_30d']); ?></div>
      <div class="bp-kpi-foot"><?php echo esc_html($kpis['since30'] . ' → ' . $kpis['today']); ?></div>
    </div>
  </div>

  <div class="bp-dash-grid">
    <div class="bp-card">
      <div class="bp-card-title"><?php echo esc_html__('Bookings (Last 14 Days)', 'bookpoint'); ?></div>
      <div class="bp-chart" data-labels="<?php echo esc_attr(wp_json_encode($series['labels'])); ?>"
           data-values="<?php echo esc_attr(wp_json_encode($series['values'])); ?>"></div>
      <div class="bp-chart-legend">
        <span><?php echo esc_html__('Each bar = bookings per day', 'bookpoint'); ?></span>
      </div>
    </div>

    <div class="bp-card">
      <div class="bp-card-title"><?php echo esc_html__('Quick Actions', 'bookpoint'); ?></div>
      <div class="bp-qa">
        <a class="bp-qa-item" href="<?php echo esc_url(admin_url('admin.php?page=bp_categories')); ?>">
          <strong><?php echo esc_html__('Categories', 'bookpoint'); ?></strong>
          <span><?php echo esc_html__('Manage service categories', 'bookpoint'); ?></span>
        </a>
        <a class="bp-qa-item" href="<?php echo esc_url(admin_url('admin.php?page=bp_services')); ?>">
          <strong><?php echo esc_html__('Services', 'bookpoint'); ?></strong>
          <span><?php echo esc_html__('Create/edit services', 'bookpoint'); ?></span>
        </a>
        <a class="bp-qa-item" href="<?php echo esc_url(admin_url('admin.php?page=bp_extras')); ?>">
          <strong><?php echo esc_html__('Service Extras', 'bookpoint'); ?></strong>
          <span><?php echo esc_html__('Add upsells & add-ons', 'bookpoint'); ?></span>
        </a>
        <a class="bp-qa-item" href="<?php echo esc_url(admin_url('admin.php?page=bp_agents')); ?>">
          <strong><?php echo esc_html__('Agents', 'bookpoint'); ?></strong>
          <span><?php echo esc_html__('Staff and assignments', 'bookpoint'); ?></span>
        </a>
        <a class="bp-qa-item" href="<?php echo esc_url(admin_url('admin.php?page=bp_settings')); ?>">
          <strong><?php echo esc_html__('Settings', 'bookpoint'); ?></strong>
          <span><?php echo esc_html__('Email/webhooks/templates', 'bookpoint'); ?></span>
        </a>
      </div>
    </div>
  </div>

  <div class="bp-card" style="margin-top:16px;">
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
            <td>
              <?php
                $when = trim((string)($b['start_date'] ?? '')) . ' ' . trim((string)($b['start_time'] ?? ''));
                echo esc_html(trim($when) ?: (string)$b['created_at']);
              ?>
            </td>
            <td><span class="bp-badge bp-<?php echo esc_attr((string)$b['status']); ?>"><?php echo esc_html((string)$b['status']); ?></span></td>
            <td>€ <?php echo esc_html(bp_fmt_money($b['total_price'] ?? 0)); ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

