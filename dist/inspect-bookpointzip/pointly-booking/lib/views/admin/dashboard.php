<?php defined('ABSPATH') || exit;

function pointlybooking_fmt_money_legacy($n){ return number_format((float)$n, 2); }
?>

<div class="bp-admin-wrap">
  <div class="bp-admin-head">
    <div>
      <h1 class="bp-admin-title"><?php echo esc_html__('Dashboard', 'pointly-booking'); ?></h1>
      <div class="bp-admin-sub"><?php echo esc_html__('Overview of bookings and performance.', 'pointly-booking'); ?></div>
    </div>

    <div class="bp-admin-actions">
      <a class="bp-btn secondary" href="<?php echo esc_url(admin_url('admin.php?page=pointlybooking_bookings')); ?>">
        <?php echo esc_html__('Manage Bookings', 'pointly-booking'); ?>
      </a>
      <a class="bp-btn" href="<?php echo esc_url(admin_url('admin.php?page=pointlybooking_services')); ?>">
        <?php echo esc_html__('Add Service', 'pointly-booking'); ?>
      </a>
    </div>
  </div>

  <div class="bp-kpi-grid">
    <div class="bp-card">
      <div class="bp-kpi-label"><?php echo esc_html__('Bookings Today', 'pointly-booking'); ?></div>
      <div class="bp-kpi-value"><?php echo esc_html((string)$kpis['today_count']); ?></div>
      <div class="bp-kpi-foot"><?php echo esc_html($kpis['today']); ?></div>
    </div>

    <div class="bp-card">
      <div class="bp-kpi-label"><?php echo esc_html__('Pending', 'pointly-booking'); ?></div>
      <div class="bp-kpi-value"><?php echo esc_html((string)$kpis['pending_count']); ?></div>
      <div class="bp-kpi-foot"><?php echo esc_html__('Needs confirmation', 'pointly-booking'); ?></div>
    </div>

    <div class="bp-card">
      <div class="bp-kpi-label"><?php echo esc_html__('Revenue (7 days)', 'pointly-booking'); ?></div>
      <div class="bp-kpi-value"><?php echo esc_html(pointlybooking_fmt_money_legacy($kpis['revenue_7d'])); ?></div>
      <div class="bp-kpi-foot"><?php echo esc_html($kpis['since7'] . ' - ' . $kpis['today']); ?></div>
    </div>

    <div class="bp-card">
      <div class="bp-kpi-label"><?php echo esc_html__('Cancelled (30 days)', 'pointly-booking'); ?></div>
      <div class="bp-kpi-value"><?php echo esc_html((string)$kpis['cancel_30d']); ?></div>
      <div class="bp-kpi-foot"><?php echo esc_html($kpis['since30'] . ' - ' . $kpis['today']); ?></div>
    </div>
  </div>

  <div class="bp-dash-grid">
    <div class="bp-card">
      <div class="bp-card-title"><?php echo esc_html__('Bookings (Last 14 Days)', 'pointly-booking'); ?></div>
      <div class="bp-chart" data-labels="<?php echo esc_attr(wp_json_encode($series['labels'])); ?>"
           data-values="<?php echo esc_attr(wp_json_encode($series['values'])); ?>"></div>
      <div class="bp-chart-legend">
        <span><?php echo esc_html__('Each bar = bookings per day', 'pointly-booking'); ?></span>
      </div>
    </div>

    <div class="bp-card">
      <div class="bp-card-title"><?php echo esc_html__('Quick Actions', 'pointly-booking'); ?></div>
      <div class="bp-qa">
        <a class="bp-qa-item" href="<?php echo esc_url(admin_url('admin.php?page=pointlybooking_categories')); ?>">
          <strong><?php echo esc_html__('Categories', 'pointly-booking'); ?></strong>
          <span><?php echo esc_html__('Manage service categories', 'pointly-booking'); ?></span>
        </a>
        <a class="bp-qa-item" href="<?php echo esc_url(admin_url('admin.php?page=pointlybooking_services')); ?>">
          <strong><?php echo esc_html__('Services', 'pointly-booking'); ?></strong>
          <span><?php echo esc_html__('Create/edit services', 'pointly-booking'); ?></span>
        </a>
        <a class="bp-qa-item" href="<?php echo esc_url(admin_url('admin.php?page=pointlybooking_extras')); ?>">
          <strong><?php echo esc_html__('Service Extras', 'pointly-booking'); ?></strong>
          <span><?php echo esc_html__('Add upsells & add-ons', 'pointly-booking'); ?></span>
        </a>
        <a class="bp-qa-item" href="<?php echo esc_url(admin_url('admin.php?page=pointlybooking_agents')); ?>">
          <strong><?php echo esc_html__('Agents', 'pointly-booking'); ?></strong>
          <span><?php echo esc_html__('Staff and assignments', 'pointly-booking'); ?></span>
        </a>
        <a class="bp-qa-item" href="<?php echo esc_url(admin_url('admin.php?page=pointlybooking_settings')); ?>">
          <strong><?php echo esc_html__('Settings', 'pointly-booking'); ?></strong>
          <span><?php echo esc_html__('Email/webhooks/templates', 'pointly-booking'); ?></span>
        </a>
      </div>
    </div>
  </div>

  <div class="bp-card" style="margin-top:16px;">
    <div class="bp-card-title"><?php echo esc_html__('Recent Bookings', 'pointly-booking'); ?></div>

    <table class="widefat striped">
      <thead>
        <tr>
          <th><?php echo esc_html__('ID', 'pointly-booking'); ?></th>
          <th><?php echo esc_html__('Customer', 'pointly-booking'); ?></th>
          <th><?php echo esc_html__('Email', 'pointly-booking'); ?></th>
          <th><?php echo esc_html__('When', 'pointly-booking'); ?></th>
          <th><?php echo esc_html__('Status', 'pointly-booking'); ?></th>
          <th><?php echo esc_html__('Total', 'pointly-booking'); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($recent)): ?>
          <tr><td colspan="6" style="text-align:center;"><?php echo esc_html__('No bookings yet.', 'pointly-booking'); ?></td></tr>
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
            <td><?php echo esc_html(pointlybooking_fmt_money_legacy($b['total_price'] ?? 0)); ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
