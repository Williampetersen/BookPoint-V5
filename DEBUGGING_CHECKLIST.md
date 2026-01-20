# BookPoint Save Not Working - Debug Checklist

Your code IS correctly structured. Follow these steps to identify the exact issue.

---

## STEP 1: Enable Debug Logging (Required First)

### In wp-config.php, add:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

**Location**: `/wp-config.php` (root of your WordPress install, NOT the plugin)

### Then reproduce the problem:

1. Go to **BookPoint → Services**
2. Click **Add New**
3. Fill in: Name = "Test Service", Duration = 60, Price = 0
4. Click **Save**
5. Nothing happens

### Check the error log:

```
/wp-content/debug.log
```

**What to look for** (common errors):
- `Fatal error: Call to undefined function`
- `table doesn't exist`
- `nonce verification failed`
- `Call to undefined method`
- `Uncaught Exception`

**Copy/paste the error here** and I can identify the exact cause.

---

## STEP 2: Verify Database Tables Exist

### Access your database via:
- **phpMyAdmin** (cPanel control panel)
- **Adminer** (if available)
- **Database client** (DBeaver, MySQL Workbench)

### Run these queries:

```sql
SHOW TABLES LIKE '%bp_%';
```

**Expected result** - You should see:
```
wp_bp_services
wp_bp_agents
wp_bp_bookings
wp_bp_customers
wp_bp_settings
```

**If ANY table is missing:**
- Go to WordPress admin
- Deactivate BookPoint plugin
- Reactivate BookPoint plugin
- This triggers the migration/activation hook
- Check the database again

---

## STEP 3: Verify Code Configuration

### ✅ Check 1: Activation Hook (should run migrations)

**File**: `bookpoint-v5/bookpoint-v5.php` around line 101

Should contain:
```php
register_activation_hook(BP_PLUGIN_FILE, [__CLASS__, 'on_activate']);
```

**Status**: ✅ CORRECT in your code

---

### ✅ Check 2: Migrations Run on Activation

**File**: `bookpoint-v5/bookpoint-v5.php` around line 140

Should contain:
```php
public static function on_activate() : void {
    self::includes();
    BP_RolesHelper::add_capabilities();
    BP_DatabaseHelper::install_or_update(self::DB_VERSION);
}
```

**Status**: ✅ CORRECT in your code

---

### ✅ Check 3: Admin-Post Hook for Services

**File**: `bookpoint-v5/bookpoint-v5.php` around line 108

Should contain:
```php
add_action('admin_post_bp_admin_services_save', [__CLASS__, 'handle_services_save']);
```

**Status**: ✅ CORRECT in your code

---

### ✅ Check 4: Admin-Post Hook for Agents

**File**: `bookpoint-v5/bookpoint-v5.php` around line 114

Should contain:
```php
add_action('admin_post_bp_admin_agents_save', [__CLASS__, 'handle_agents_save']);
```

**Status**: ✅ CORRECT in your code

---

### ✅ Check 5: Services Form Action Value

**File**: `bookpoint-v5/lib/views/admin/services_edit.php` line 30

Should contain:
```html
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
  <?php wp_nonce_field('bp_admin'); ?>
  <input type="hidden" name="action" value="bp_admin_services_save">
```

**Status**: ✅ CORRECT in your code

---

### ✅ Check 6: Agents Form Action Value

**File**: `bookpoint-v5/lib/views/admin/agents_edit.php` line 8-9

Should contain:
```html
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
  <?php wp_nonce_field('bp_admin'); ?>
  <input type="hidden" name="action" value="bp_admin_agents_save">
```

**Status**: ✅ CORRECT in your code

---

### ✅ Check 7: Services Controller Nonce Check

**File**: `bookpoint-v5/lib/controllers/admin_services_controller.php` line 25-27

Should contain:
```php
public function save() : void {
    $this->require_cap('bp_manage_services');
    check_admin_referer('bp_admin');
```

**Status**: ✅ CORRECT in your code

---

### ✅ Check 8: Agents Controller Nonce Check

**File**: `bookpoint-v5/lib/controllers/admin_agents_controller.php` line 22-24

Should contain:
```php
public function save() : void {
    $this->require_cap('bp_manage_settings');
    check_admin_referer('bp_admin');
```

**Status**: ✅ CORRECT in your code

---

### ✅ Check 9: Services Controller Capability

**File**: `bookpoint-v5/lib/controllers/admin_services_controller.php`

Each method should start with:
```php
public function save() : void {
    $this->require_cap('bp_manage_services');
```

**Status**: ✅ CORRECT in your code

---

### ✅ Check 10: Agents Controller Capability

**File**: `bookpoint-v5/lib/controllers/admin_agents_controller.php`

Each method should start with:
```php
public function save() : void {
    $this->require_cap('bp_manage_settings');
```

**Status**: ✅ CORRECT in your code

---

## STEP 4: Most Likely Problem - Database Not Created

If you don't see the tables when you run `SHOW TABLES LIKE '%bp_%'`:

### Quick Fix:

1. Go to WordPress **Plugins** page
2. Find **BookPoint** 
3. Click **Deactivate**
4. Click **Activate**
5. Check database again

**Why this works**: Plugin activation triggers `register_activation_hook()` which calls `BP_DatabaseHelper::install_or_update()` which runs `BP_MigrationsHelper::create_tables()`

---

## STEP 5: If Still Not Working - Capability Issue

If you can open the form but saving silently fails (no error):

### Test capability:

In `bookpoint-v5/lib/controllers/admin_services_controller.php`, in the `save()` method, temporarily change:

```php
public function save() : void {
    $this->require_cap('bp_manage_services');
```

to:

```php
public function save() : void {
    if (!current_user_can('manage_options')) {
        wp_die('DEBUG: You do not have manage_options capability');
    }
```

If you now see the error message → capabilities are broken.

**Fix**: Verify `BP_RolesHelper::add_capabilities()` is being called in `on_activate()` 

**Or**: Check if you're logged in as admin (super admin can bypass some cap checks)

---

## STEP 6: Check Handler Methods Exist

In `bookpoint-v5/bookpoint-v5.php`, around line 420+, verify these methods exist:

```php
public static function handle_services_save() : void {
    (new BP_AdminServicesController())->save();
}

public static function handle_agents_save() : void {
    (new BP_AdminAgentsController())->save();
}
```

---

## Summary of Your Code Status

| Item | Expected | Your Code | Status |
|------|----------|-----------|--------|
| Activation hook | `register_activation_hook()` | ✅ Present line 101 | ✅ OK |
| DB install/update | `BP_DatabaseHelper::install_or_update()` | ✅ Present line 140 | ✅ OK |
| Services admin-post hook | `admin_post_bp_admin_services_save` | ✅ Present line 108 | ✅ OK |
| Agents admin-post hook | `admin_post_bp_admin_agents_save` | ✅ Present line 114 | ✅ OK |
| Services form action | `bp_admin_services_save` | ✅ Present | ✅ OK |
| Agents form action | `bp_admin_agents_save` | ✅ Present | ✅ OK |
| Services nonce | `bp_admin` | ✅ Present | ✅ OK |
| Agents nonce | `bp_admin` | ✅ Present | ✅ OK |
| Services capability | `bp_manage_services` | ✅ Present | ✅ OK |
| Agents capability | `bp_manage_settings` | ✅ Present | ✅ OK |

**Conclusion**: Your code structure is 100% correct. The problem is most likely:

1. **Database tables don't exist** → Reactivate plugin to run migrations
2. **Permissions/capability issue** → Run Step 5 test above
3. **PHP error in the code** → Check `/wp-content/debug.log` after enabling WP_DEBUG

---

## What To Do Now

1. **Enable WP_DEBUG** in wp-config.php (Step 1)
2. **Try to save** a service/agent
3. **Check /wp-content/debug.log**
4. Share the error message and I'll fix it immediately

