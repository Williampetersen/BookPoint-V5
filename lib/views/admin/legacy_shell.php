<?php
defined('ABSPATH') || exit;

function bp_render_legacy_shell_start(string $title, string $subtitle = '', string $actions_html = '', string $active = ''): void {
  $nav = function(string $key, string $label, string $url) use ($active) {
    $cls = 'bp-nav-item' . ($active === $key ? ' active' : '');
    echo '<a class="' . esc_attr($cls) . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
  };
  ?>
  <style>
    .bp-legacy-admin{
      --bp-bg:#f5f7ff;
      --bp-card:#ffffff;
      --bp-text:#0f172a;
      --bp-muted:#64748b;
      --bp-border:#e5e7eb;
      --bp-primary:#4318ff;
      background:var(--bp-bg);
      padding:18px;
      border-radius:16px;
    }
    .bp-legacy-admin .bp-page-head{display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap;margin-bottom:16px;}
    .bp-legacy-admin .bp-h1{font-size:22px;font-weight:1100;margin:0 0 6px;}
    .bp-legacy-admin .bp-muted{color:var(--bp-muted);font-weight:850;}
    .bp-legacy-admin .bp-head-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;}
    .bp-legacy-admin .bp-top-btn{padding:10px 12px;border-radius:14px;border:1px solid var(--bp-border);background:var(--bp-card);color:var(--bp-text);font-weight:900;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:8px;}
    .bp-legacy-admin .bp-top-btn:hover{border-color:rgba(67,24,255,.35);} 
    .bp-legacy-admin .bp-card{background:var(--bp-card);border:1px solid var(--bp-border);border-radius:18px;padding:14px;box-shadow:0 10px 30px rgba(2,6,23,.04);} 
    .bp-legacy-admin .form-table{width:100%;border-collapse:separate;border-spacing:0 12px;}
    .bp-legacy-admin .form-table th{width:240px;text-align:left;font-weight:900;color:var(--bp-muted);vertical-align:top;padding:10px 12px;}
    .bp-legacy-admin .form-table td{background:var(--bp-card);border:1px solid var(--bp-border);border-radius:14px;padding:12px;}
    .bp-legacy-admin input[type="text"],
    .bp-legacy-admin input[type="email"],
    .bp-legacy-admin input[type="url"],
    .bp-legacy-admin input[type="number"],
    .bp-legacy-admin input[type="date"],
    .bp-legacy-admin select,
    .bp-legacy-admin textarea{
      width:100%;
      padding:10px 12px;
      border-radius:12px;
      border:1px solid var(--bp-border);
      background:#fff;
      color:var(--bp-text);
      font-weight:900;
      box-sizing:border-box;
    }
    .bp-legacy-admin textarea{min-height:90px;}
    .bp-legacy-admin .description{color:var(--bp-muted);font-weight:850;}
    .bp-legacy-admin .bp-btn{padding:10px 14px;border-radius:14px;border:1px solid var(--bp-border);background:var(--bp-card);color:var(--bp-text);font-weight:900;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:8px;}
    .bp-legacy-admin .bp-btn-primary{background:var(--bp-primary);color:#fff;border-color:rgba(67,24,255,.25);} 
    .bp-legacy-admin .bp-btn-primary:hover{filter:brightness(1.03);} 
    .bp-legacy-admin .submit{margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;}
    .bp-legacy-admin .nav-tab-wrapper{border:0;margin:0 0 12px;display:flex;gap:8px;flex-wrap:wrap;}
    .bp-legacy-admin .nav-tab{border:1px solid var(--bp-border);background:var(--bp-card);color:var(--bp-muted);font-weight:900;border-radius:12px;padding:8px 12px;text-decoration:none;}
    .bp-legacy-admin .nav-tab-active{color:var(--bp-primary);border-color:rgba(67,24,255,.35);background:rgba(67,24,255,.08);} 
    .bp-legacy-admin .widefat{width:100%;border-collapse:separate;border-spacing:0 8px;border:0;background:transparent;}
    .bp-legacy-admin .widefat thead th{color:var(--bp-muted);font-weight:1100;font-size:12px;border:0;padding:8px 10px;text-align:left;}
    .bp-legacy-admin .widefat tbody tr td{background:var(--bp-card);border-top:1px solid var(--bp-border);border-bottom:1px solid var(--bp-border);padding:12px;}
    .bp-legacy-admin .widefat tbody tr td:first-child{border-left:1px solid var(--bp-border);border-radius:14px 0 0 14px;}
    .bp-legacy-admin .widefat tbody tr td:last-child{border-right:1px solid var(--bp-border);border-radius:0 14px 14px 0;}
    .bp-legacy-admin .button,
    .bp-legacy-admin .button-primary,
    .bp-legacy-admin .button-small{
      padding:8px 12px !important;
      border-radius:12px !important;
      border:1px solid var(--bp-border) !important;
      background:var(--bp-card) !important;
      color:var(--bp-text) !important;
      font-weight:900 !important;
      text-decoration:none;
    }
    .bp-legacy-admin .button-primary{background:var(--bp-primary) !important;color:#fff !important;border-color:rgba(67,24,255,.25) !important;}
    .bp-legacy-admin .button-link-delete{color:#b91c1c !important;border-color:#fecaca !important;background:#fee2e2 !important;}
  </style>

  <div class="bp-app">
    <div class="bp-shell">
      <aside class="bp-sidebar">
        <div class="bp-brand">
          <div class="bp-logo">BP</div>
          <div>
            <div class="bp-title">BookPoint</div>
            <div class="bp-sub">Admin</div>
          </div>
        </div>

        <nav class="bp-nav">
          <div class="bp-group-title">OVERVIEW</div>
          <?php $nav('dashboard', 'Dashboard', admin_url('admin.php?page=bp_dashboard')); ?>
          <?php $nav('bookings', 'Bookings', admin_url('admin.php?page=bp_bookings')); ?>
          <?php $nav('calendar', 'Calendar', admin_url('admin.php?page=bp_calendar')); ?>
          <?php $nav('schedule', 'Schedule', admin_url('admin.php?page=bp_schedule')); ?>
          <?php $nav('holidays', 'Holidays', admin_url('admin.php?page=bp_holidays')); ?>

          <div class="bp-group-title">RESOURCES</div>
          <?php $nav('services', 'Services', admin_url('admin.php?page=bp_services')); ?>
          <?php $nav('categories', 'Categories', admin_url('admin.php?page=bp_categories')); ?>
          <?php $nav('extras', 'Service Extras', admin_url('admin.php?page=bp_extras')); ?>
          <?php $nav('agents', 'Agents', admin_url('admin.php?page=bp_agents')); ?>
          <?php $nav('customers', 'Customers', admin_url('admin.php?page=bp_customers')); ?>
          <?php $nav('promo', 'Promo Codes', admin_url('admin.php?page=bp_promo_codes')); ?>
          <?php $nav('form-fields', 'Form Fields', admin_url('admin.php?page=bp_form_fields')); ?>

          <div class="bp-group-title">SYSTEM</div>
          <?php $nav('settings', 'Settings', admin_url('admin.php?page=bp_settings')); ?>
          <?php $nav('audit', 'Audit Log', admin_url('admin.php?page=bp_audit')); ?>
          <?php $nav('tools', 'Tools', admin_url('admin.php?page=bp_tools')); ?>
        </nav>

        <div class="bp-sidebar-footer">
          <a class="bp-top-btn" href="<?php echo esc_url(admin_url()); ?>">← Back to WordPress</a>
        </div>
      </aside>

      <main class="bp-main">
        <header class="bp-topbar">
          <div class="bp-search">
            <input placeholder="Search…">
          </div>
          <div class="bp-top-actions">
            <div class="bp-avatar">W</div>
          </div>
        </header>

        <div class="bp-content">
          <div class="wrap bp-legacy-admin">
            <div class="bp-page-head">
              <div>
                <div class="bp-h1"><?php echo esc_html($title); ?></div>
                <?php if ($subtitle) : ?><div class="bp-muted"><?php echo esc_html($subtitle); ?></div><?php endif; ?>
              </div>
              <?php if ($actions_html) : ?>
                <div class="bp-head-actions"><?php echo $actions_html; ?></div>
              <?php endif; ?>
            </div>
  <?php
}

function bp_render_legacy_shell_end(): void {
  ?>
          </div>
        </div>
      </main>
    </div>
  </div>
  <?php
}
