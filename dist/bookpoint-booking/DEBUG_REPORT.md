# BookPoint Debug Report

## Issues Fixed (January 20, 2026)

### 1. Database Table Naming Inconsistency (CRITICAL - FIXED)
**Problem**: Services, bookings, customers used uppercase `POINTLYBOOKING_` prefix, but agents used lowercase `pointlybooking_`.
**Impact**: Agent operations would fail because code expected different table names.

**Fixed Files**:
- âœ… `lib/models/agent_model.php` - Changed `pointlybooking_agents` â†’ `POINTLYBOOKING_agents`
- âœ… `lib/helpers/migrations_helper.php` - Changed `pointlybooking_agents` â†’ `POINTLYBOOKING_agents`, `pointlybooking_bookings` â†’ `POINTLYBOOKING_bookings`
- âœ… `lib/helpers/availability_helper.php` - Changed `pointlybooking_bookings` â†’ `POINTLYBOOKING_bookings`
- âœ… `lib/controllers/public_bookings_controller.php` - Changed `pointlybooking_bookings` â†’ `POINTLYBOOKING_bookings`
- âœ… `lib/models/booking_model.php` - Updated `all_with_relations()` to use uppercase

**Current Status**: All database table references now use consistent `POINTLYBOOKING_` uppercase prefix.

---

## Known Issues Requiring Further Investigation

### 2. Form Submission Not Working
If service/agent creation still fails:

**Checklist**:
- [ ] Check WordPress `wp_options` table for `POINTLYBOOKING_db_version` to confirm migrations ran
- [ ] Verify tables exist in database:
  - `wp_POINTLYBOOKING_services`
  - `wp_POINTLYBOOKING_agents` 
  - `wp_POINTLYBOOKING_bookings`
  - `wp_POINTLYBOOKING_customers`
- [ ] Check admin form `action` attributes match registered hooks
- [ ] Verify nonce field is present in all forms

### 3. JavaScript Agent Loading
If agent dropdown doesn't populate:

**Checklist**:
- [ ] Check browser console for AJAX errors
- [ ] Verify `/wp-json/bp/v1/agents` REST endpoint responds
- [ ] Check if agents exist in database
- [ ] Verify REST route registered in `bookpoint-v5.php` line ~215

### 4. Email Notifications
If notifications aren't sending:

**Checklist**:
- [ ] Verify email enabled: `POINTLYBOOKING_SettingsHelper::get_with_default('pointlybooking_email_enabled')`
- [ ] Check email template paths exist in `lib/views/emails/`
- [ ] Verify SMTP or default mail configured

---

## Database Structure

### Tables Created on Activation
```sql
wp_POINTLYBOOKING_services     -- Service definitions
wp_POINTLYBOOKING_customers    -- Customer records  
wp_POINTLYBOOKING_bookings     -- Booking records (with agent_id column)
wp_POINTLYBOOKING_agents       -- Agent records (NEW - Step 16)
wp_POINTLYBOOKING_settings     -- Plugin settings
```

### Key Fields for Step 16
- `wp_POINTLYBOOKING_agents` table:
  - `id` BIGINT UNSIGNED PRIMARY KEY
  - `first_name`, `last_name` VARCHAR(191)
  - `email` VARCHAR(191)
  - `phone` VARCHAR(50)
  - `is_active` TINYINT(1)
  - `schedule_json` LONGTEXT (optional override)
  - `created_at`, `updated_at` DATETIME

- `wp_POINTLYBOOKING_bookings` agent link:
  - `agent_id` BIGINT UNSIGNED NULL (added by migration)
  - Indexed for performance

---

## Code Architecture

### Admin Pages (Registered in `register_admin_menu()`)
- `bp` - Dashboard
- `pointlybooking_bookings` - Bookings list
- `pointlybooking_services` - Services list (with Edit/Delete hidden pages)
- `pointlybooking_customers` - Customers list (with View hidden page)
- `pointlybooking_agents` - Agents list (with Edit/Delete hidden pages) â† NEW Step 16

### Form Submission Flow
1. HTML form posts to `admin-post.php` with `action=pointlybooking_admin_[resource]_save`
2. WordPress routes to `admin_post_pointlybooking_admin_[resource]_save` hook
3. Main plugin class method `handle_[resource]_save()` called
4. Creates controller instance and calls `->save()` method
5. Controller validates data and calls Model::create() or Model::update()

### Public Booking Flow
1. Shortcode `[bookPoint service_id=X]` renders form
2. Form has agent dropdown (Step 16 new)
3. On agent change, JavaScript fetches slots filtered by agent_id
4. Form submits to AJAX endpoint with agent_id parameter
5. `public_bookings_controller.php` validates availability WITH agent_id
6. Booking created with agent_id stored in database

---

## Testing Steps

### 1. Plugin Activation
```
1. Go to Plugins page
2. Deactivate BookPoint
3. Reactivate BookPoint
4. Check WordPress error log for migration errors
```

### 2. Create Service
```
1. Go to BookPoint â†’ Services
2. Click "Add New"
3. Fill form: Name, Duration, Price
4. Save
Expected: New service appears in list
```

### 3. Create Agent
```
1. Go to BookPoint â†’ Agents
2. Click "Add New"
3. Fill form: First name, Last name, Email
4. Save
Expected: New agent appears in list, id assigned
```

### 4. Public Booking Form
```
1. Add [bookPoint service_id=1] to page/post
2. Load page in browser
3. Select date â†’ verify agent dropdown loads
4. Select agent â†’ verify slots update
5. Fill form and submit
Expected: Booking created with agent_id in database
```

### 5. Database Verification
```
Query: SELECT * FROM wp_POINTLYBOOKING_agents;
Query: SELECT id, service_id, agent_id FROM wp_POINTLYBOOKING_bookings ORDER BY id DESC LIMIT 5;
Expected: Records exist with correct IDs
```

---

## File Summary

### Models
- `service_model.php` - Table: `POINTLYBOOKING_services` âœ…
- `agent_model.php` - Table: `POINTLYBOOKING_agents` âœ… (FIXED)
- `booking_model.php` - Table: `POINTLYBOOKING_bookings` âœ… (FIXED)
- `customer_model.php` - Table: `POINTLYBOOKING_customers` âœ…

### Controllers
- `admin_services_controller.php` - CRUD + validation
- `admin_agents_controller.php` - CRUD (NEW Step 16)
- `public_bookings_controller.php` - Slots, submit (UPDATED Step 16)

### Helpers
- `availability_helper.php` - Overlap checking (UPDATED with agent_id) âœ… (FIXED)
- `migrations_helper.php` - DB setup (UPDATED with agent table) âœ… (FIXED)
- `settings_helper.php` - Plugin config
- `email_helper.php` - Notifications

### Views
- `admin/services_index.php`, `services_edit.php`
- `admin/agents_index.php`, `agents_edit.php` (NEW Step 16)
- `public/booking_form.php` (UPDATED with agent dropdown)

---

## Next Steps if Issues Persist

1. Check `wp-admin/admin.php?page=pointlybooking_agents` - Does admin interface load?
2. Check browser DevTools Network tab for AJAX errors
3. Check WordPress error log: `wp-content/debug.log`
4. Check database directly for table structure
5. Verify all PHP files have no syntax errors

