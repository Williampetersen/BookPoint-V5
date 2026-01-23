<?php
defined('ABSPATH') || exit;

$service = $service ?? ($item ?? null);
$categories = $categories ?? [];

$id = isset($service['id']) ? (int)$service['id'] : 0;
$name = $service['name'] ?? '';
$description = $service['description'] ?? '';
$duration = isset($service['duration_minutes']) ? (int)$service['duration_minutes'] : 60;
$price_cents = isset($service['price_cents']) ? (int)$service['price_cents'] : 0;
$currency = $service['currency'] ?? 'USD';
$is_active = isset($service['is_active']) ? (int)$service['is_active'] : 1;

$category_id = (int)($service['category_id'] ?? 0);
$image_id = (int)($service['image_id'] ?? 0);
$image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';

$selected_category_ids = !empty($service['id'])
  ? BP_ServiceModel::get_category_ids((int)$service['id'])
  : [];
$categories = $categories ?? BP_CategoryModel::all(['is_active' => 1]);

// Step 15: Service-based availability (with null-safe defaults)
$use_global_schedule = (is_array($service) && isset($service['use_global_schedule'])) ? (int)$service['use_global_schedule'] : 1;
$schedule_json = (is_array($service) && isset($service['schedule_json'])) ? $service['schedule_json'] : '';
$buffer_before = (is_array($service) && isset($service['buffer_before_minutes'])) ? (int)$service['buffer_before_minutes'] : 0;
$buffer_after  = (is_array($service) && isset($service['buffer_after_minutes'])) ? (int)$service['buffer_after_minutes'] : 0;
$capacity      = (is_array($service) && isset($service['capacity'])) ? (int)$service['capacity'] : 1;

function BP_field_error($errors, $key) {
  if (!empty($errors[$key])) {
    echo '<p style="color:#b32d2e;margin:6px 0 0;">' . esc_html($errors[$key]) . '</p>';
  }
}
?>
<?php $is_legacy = isset($_GET['legacy']); ?>
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
  .bp-legacy-admin input[type="number"],
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
        <a class="bp-nav-item" href="<?php echo esc_url(admin_url('admin.php?page=bp_dashboard')); ?>">Dashboard</a>
        <a class="bp-nav-item" href="<?php echo esc_url(admin_url('admin.php?page=bp_bookings')); ?>">Bookings</a>
        <a class="bp-nav-item" href="<?php echo esc_url(admin_url('admin.php?page=bp_calendar')); ?>">Calendar</a>
        <a class="bp-nav-item" href="<?php echo esc_url(admin_url('admin.php?page=bp_schedule')); ?>">Schedule</a>
        <a class="bp-nav-item" href="<?php echo esc_url(admin_url('admin.php?page=bp_holidays')); ?>">Holidays</a>

        <div class="bp-group-title">RESOURCES</div>
        <a class="bp-nav-item active" href="<?php echo esc_url(admin_url('admin.php?page=bp_services')); ?>">Services</a>
        <a class="bp-nav-item" href="<?php echo esc_url(admin_url('admin.php?page=bp_categories')); ?>">Categories</a>
        <a class="bp-nav-item" href="<?php echo esc_url(admin_url('admin.php?page=bp_extras')); ?>">Service Extras</a>
        <a class="bp-nav-item" href="<?php echo esc_url(admin_url('admin.php?page=bp_agents')); ?>">Agents</a>
        <a class="bp-nav-item" href="<?php echo esc_url(admin_url('admin.php?page=bp_customers')); ?>">Customers</a>
        <a class="bp-nav-item" href="<?php echo esc_url(admin_url('admin.php?page=bp_promo_codes')); ?>">Promo Codes</a>
        <a class="bp-nav-item" href="<?php echo esc_url(admin_url('admin.php?page=bp_form_fields')); ?>">Form Fields</a>

        <div class="bp-group-title">SYSTEM</div>
        <a class="bp-nav-item" href="<?php echo esc_url(admin_url('admin.php?page=bp_settings')); ?>">Settings</a>
        <a class="bp-nav-item" href="<?php echo esc_url(admin_url('admin.php?page=bp_audit')); ?>">Audit Log</a>
        <a class="bp-nav-item" href="<?php echo esc_url(admin_url('admin.php?page=bp_tools')); ?>">Tools</a>
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
              <div class="bp-h1"><?php echo $id ? esc_html__('Edit Service', 'bookpoint') : esc_html__('Add Service', 'bookpoint'); ?></div>
              <div class="bp-muted"><?php echo esc_html__('Manage service details, pricing, and availability.', 'bookpoint'); ?></div>
            </div>
            <div class="bp-head-actions">
              <a class="bp-top-btn" href="<?php echo esc_url(admin_url('admin.php?page=bp_services')); ?>"<?php echo $is_legacy ? ' target="_top"' : ''; ?>>
                <?php echo esc_html__('Back to Services', 'bookpoint'); ?>
              </a>
            </div>
          </div>

  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"<?php echo $is_legacy ? ' target="_top"' : ''; ?>>
    <?php wp_nonce_field('bp_admin'); ?>
    <input type="hidden" name="action" value="bp_admin_services_save">
    <input type="hidden" name="id" value="<?php echo esc_attr($id); ?>">

    <div class="bp-card">
      <table class="form-table" role="presentation">
      <tr>
        <th><label for="bp_name"><?php echo esc_html__('Name', 'bookpoint'); ?></label></th>
        <td>
          <input type="text" id="bp_name" name="name" class="regular-text" value="<?php echo esc_attr($name); ?>" required>
          <?php BP_field_error($errors, 'name'); ?>
        </td>
      </tr>

      <tr>
        <th><label for="bp_duration"><?php echo esc_html__('Duration (minutes)', 'bookpoint'); ?></label></th>
        <td>
          <input type="number" id="bp_duration" name="duration_minutes" min="5" max="1440" value="<?php echo esc_attr($duration); ?>">
          <?php BP_field_error($errors, 'duration_minutes'); ?>
        </td>
      </tr>

      <tr>
        <th><label for="bp_price"><?php echo esc_html__('Price (cents)', 'bookpoint'); ?></label></th>
        <td>
          <input type="number" id="bp_price" name="price_cents" min="0" value="<?php echo esc_attr($price_cents); ?>">
          <p class="description"><?php echo esc_html__('Example: 2500 = 25.00', 'bookpoint'); ?></p>
          <?php BP_field_error($errors, 'price_cents'); ?>
        </td>
      </tr>

      <tr>
        <th><label for="bp_currency"><?php echo esc_html__('Currency', 'bookpoint'); ?></label></th>
        <td>
          <input type="text" id="bp_currency" name="currency" maxlength="3" value="<?php echo esc_attr($currency); ?>">
          <?php BP_field_error($errors, 'currency'); ?>
        </td>
      </tr>

      <tr>
        <th><?php echo esc_html__('Description', 'bookpoint'); ?></th>
        <td>
          <textarea name="description" rows="5" class="large-text"><?php echo esc_textarea($description); ?></textarea>
        </td>
      </tr>

      <tr>
        <th><label><?php echo esc_html__('Categories', 'bookpoint'); ?></label></th>
        <td>
          <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;max-width:760px;">
            <?php foreach ($categories as $cat):
              $cid = (int)$cat['id'];
              $img = !empty($cat['image_id']) ? wp_get_attachment_image_url((int)$cat['image_id'], 'thumbnail') : '';
            ?>
              <label style="border:1px solid #e5e5e5;border-radius:14px;padding:10px;display:flex;gap:10px;align-items:center;">
                <input type="checkbox" name="category_ids[]" value="<?php echo esc_attr((string)$cid); ?>"
                  <?php checked(in_array($cid, $selected_category_ids, true)); ?>>
                <?php if ($img): ?>
                  <img src="<?php echo esc_url($img); ?>" style="width:36px;height:36px;border-radius:10px;object-fit:cover;">
                <?php endif; ?>
                <span><?php echo esc_html($cat['name']); ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <p class="description"><?php echo esc_html__('A service can belong to multiple categories.', 'bookpoint'); ?></p>
        </td>
      </tr>

      <tr>
        <th><label><?php echo esc_html__('Service Image', 'bookpoint'); ?></label></th>
        <td>
          <input type="hidden" name="image_id" id="bp_service_image_id" value="<?php echo esc_attr((string)$image_id); ?>">

          <div id="bp_service_image_preview" style="margin-bottom:10px;">
            <?php if ($image_url): ?>
              <img src="<?php echo esc_url($image_url); ?>" style="width:140px;height:140px;object-fit:cover;border-radius:14px;border:1px solid #ddd;">
            <?php else: ?>
              <div style="width:140px;height:140px;border-radius:14px;border:1px dashed #ccc;display:flex;align-items:center;justify-content:center;color:#777;">
                <?php echo esc_html__('No image', 'bookpoint'); ?>
              </div>
            <?php endif; ?>
          </div>

          <button type="button" class="button" id="bp_service_pick_image"><?php echo esc_html__('Choose Image', 'bookpoint'); ?></button>
          <button type="button" class="button" id="bp_service_remove_image"><?php echo esc_html__('Remove', 'bookpoint'); ?></button>

          <p class="description"><?php echo esc_html__('Stored as Media Library attachment ID.', 'bookpoint'); ?></p>
        </td>
      </tr>

      <tr>
        <th><?php echo esc_html__('Active', 'bookpoint'); ?></th>
        <td>
          <label>
            <input type="checkbox" name="is_active" value="1" <?php checked($is_active, 1); ?>>
            <?php echo esc_html__('Service is active', 'bookpoint'); ?>
          </label>
        </td>
      </tr>

      <tr>
        <th><label for="bp_capacity"><?php echo esc_html__('Capacity', 'bookpoint'); ?></label></th>
        <td>
          <input id="bp_capacity" type="number" min="1" max="50" name="capacity" value="<?php echo esc_attr($capacity); ?>">
          <?php BP_field_error($errors, 'capacity'); ?>
          <p class="description"><?php echo esc_html__('How many bookings can be made for the same time slot.', 'bookpoint'); ?></p>
        </td>
      </tr>

      <tr>
        <th><label for="bp_buf_before"><?php echo esc_html__('Buffer before (minutes)', 'bookpoint'); ?></label></th>
        <td>
          <input id="bp_buf_before" type="number" min="0" max="240" name="buffer_before_minutes" value="<?php echo esc_attr($buffer_before); ?>">
          <?php BP_field_error($errors, 'buffer_before_minutes'); ?>
        </td>
      </tr>

      <tr>
        <th><label for="bp_buf_after"><?php echo esc_html__('Buffer after (minutes)', 'bookpoint'); ?></label></th>
        <td>
          <input id="bp_buf_after" type="number" min="0" max="240" name="buffer_after_minutes" value="<?php echo esc_attr($buffer_after); ?>">
          <?php BP_field_error($errors, 'buffer_after_minutes'); ?>
        </td>
      </tr>

      <tr>
        <th><?php echo esc_html__('Use Global Schedule', 'bookpoint'); ?></th>
        <td>
          <label>
            <input type="checkbox" name="use_global_schedule" value="1" <?php checked($use_global_schedule, 1); ?>>
            <?php echo esc_html__('Use global weekly schedule from Settings', 'bookpoint'); ?>
          </label>
          <p class="description"><?php echo esc_html__('If disabled, you can provide a service-specific schedule JSON below.', 'bookpoint'); ?></p>
        </td>
      </tr>

      <tr>
        <th><?php echo esc_html__('Service Schedule JSON', 'bookpoint'); ?></th>
        <td>
          <textarea name="schedule_json" rows="4" class="large-text" placeholder='{"1":"09:00-17:00","2":"09:00-17:00","0":""}'><?php echo esc_textarea($schedule_json); ?></textarea>
          <p class="description"><?php echo esc_html__('Optional. Keys are weekday numbers 0-6. Values are "HH:MM-HH:MM" or empty for closed.', 'bookpoint'); ?></p>
        </td>
      </tr>
      </table>
    </div>

    <div class="bp-card" style="margin-top:14px;">
      <h2 style="margin:0 0 10px;">
        <?php esc_html_e('Agents for this service', 'bookpoint'); ?>
      </h2>

    <?php if (!empty($all_agents)) : ?>
      <?php foreach ($all_agents as $a) :
        $aid = (int)$a['id'];
        $checked = in_array($aid, $selected_agent_ids ?? [], true);
      ?>
        <label style="display:block; margin:6px 0;">
          <input type="checkbox" name="agent_ids[]" value="<?php echo esc_attr($aid); ?>" <?php checked($checked); ?>>
          <?php echo esc_html(BP_AgentModel::display_name($a)); ?>
        </label>
      <?php endforeach; ?>
    <?php else : ?>
      <p class="bp-muted"><?php esc_html_e('No agents yet. Add agents first.', 'bookpoint'); ?></p>
    <?php endif; ?>

    <p class="submit">
      <button type="submit" class="bp-btn bp-btn-primary"><?php echo esc_html__('Save Service', 'bookpoint'); ?></button>
      <a class="bp-btn" href="<?php echo esc_url(admin_url('admin.php?page=bp_services')); ?>"<?php echo $is_legacy ? ' target="_top"' : ''; ?>><?php echo esc_html__('Back', 'bookpoint'); ?></a>
    </p>
    </div>
          </form>
        </div>
      </div>
    </main>
  </div>
</div>

