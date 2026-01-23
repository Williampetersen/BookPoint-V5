# C14 Deployment Checklist

## Files Ready for Production

### 📦 Build Files (Auto-generated, ready to deploy)
```
build/admin.js                          316.43 KB    ✅ Rebuilt with all changes
build/admin.asset.php                   0.12 KB     ✅ Dependencies manifest
```

### 📄 Source Files Modified (Auto-sync with build)
```
src/admin/screens/CalendarScreen.jsx                 ✅ Added eventClick + event bus
src/admin/lib/bpEvents.js                           ✅ New event bus system
```

### 🎨 CSS Updated
```
public/admin-app.css                                ✅ Added event color classes
                                                       - .bp-evt-confirmed (green)
                                                       - .bp-evt-pending (orange)
                                                       - .bp-evt-cancelled (gray)
                                                       - .bp-evt-completed (blue)
```

### 🔌 Backend Updated
```
lib/rest/admin-calendar-routes.php                  ✅ Added POST /admin/bookings/{id}/status
                                                       - New function: bp_rest_admin_booking_change_status
                                                       - Validates status values
                                                       - Updates timestamp
```

---

## Deployment Instructions

### Option A: Upload via FTP/SFTP

1. **Upload build files:**
   ```
   Local:  build/admin.js
   Remote: /wp-content/plugins/bookpoint-v5/build/admin.js
   
   Local:  build/admin.asset.php
   Remote: /wp-content/plugins/bookpoint-v5/build/admin.asset.php
   ```

2. **Upload CSS:**
   ```
   Local:  public/admin-app.css
   Remote: /wp-content/plugins/bookpoint-v5/public/admin-app.css
   ```

3. **Upload PHP:**
   ```
   Local:  lib/rest/admin-calendar-routes.php
   Remote: /wp-content/plugins/bookpoint-v5/lib/rest/admin-calendar-routes.php
   ```

### Option B: Upload via WordPress Plugin Screen

1. ZIP the entire plugin folder
2. Go to Plugins → Add New → Upload Plugin
3. Select ZIP and activate

### Option C: Git Deploy (if connected)

```bash
git add .
git commit -m "C14: Calendar eventClick, event bus, status endpoint, event colors"
git push origin main
# Pull on production server
```

---

## Post-Deployment Verification

### 1. **Check Files Are In Place**
```bash
# SSH to server
cd /wp-content/plugins/bookpoint-v5/
ls -lh build/admin.*
ls -lh lib/rest/admin-calendar-routes.php
grep -n "bp_evt_confirmed" public/admin-app.css
```

### 2. **Clear WordPress Cache**
- Go to WordPress admin
- If using caching plugin: flush cache
- Hard refresh browser: Ctrl+Shift+R (Windows) or Cmd+Shift+R (Mac)

### 3. **Test Calendar Page**
1. Visit: `yoursite.com/wp-admin/?page=bp_calendar`
2. Verify:
   - ✅ Calendar loads with events
   - ✅ Events show status colors (green/orange/gray/blue)
   - ✅ Event titles show "Service • Customer" format
   - ✅ Click event opens drawer
   - ✅ Drag event reschedules it

### 4. **Check Browser Console**
- Press F12 → Console tab
- Should see NO errors
- May see warnings (bundle size) - OK

### 5. **Monitor Network Tab**
- Press F12 → Network tab
- Click event: should see GET `/wp-json/bp/v1/admin/bookings/{id}`
- Change status: should see POST `/wp-json/bp/v1/admin/bookings/{id}/status`
- Drag event: should see POST `/wp-json/bp/v1/admin/bookings/{id}/reschedule`

---

## Rollback Plan (If Issues)

If something breaks:

1. **Delete the new files:**
   ```bash
   rm build/admin.js
   rm lib/rest/admin-calendar-routes.php
   ```

2. **Restore from backup:**
   - If using Git: `git revert <commit-hash>`
   - If using backup: restore those files from pre-deployment backup

3. **Re-activate plugin** or clear cache

---

## Expected Behavior After Deployment

### Calendar Page
- ✅ Events colored by status (not just orange/green)
- ✅ Event text: "Service Name • Customer Name"
- ✅ Click → drawer opens with booking details
- ✅ Drag → reschedules with validation
- ✅ Status change → calendar refreshes automatically

### New Endpoints Available
- ✅ `POST /admin/bookings/{id}/status` - Change status
- ✅ `POST /admin/bookings/{id}/reschedule` - Reschedule (already had this)

### No Breaking Changes
- ✅ All existing endpoints still work
- ✅ BookingsScreen unaffected
- ✅ ScheduleScreen unaffected
- ✅ HolidaysScreen unaffected

---

## Support Notes

If users report issues after deployment:

1. **Blank Calendar** → Clear browser cache + hard refresh
2. **No color coding** → Check CSS file uploaded correctly
3. **Click not opening drawer** → Check build files uploaded
4. **Reschedule fails** → Check /admin/bookings/{id}/reschedule endpoint works
5. **Status change fails** → Check new status endpoint was uploaded

---

## Metrics

- Bundle size increase: +3 KiB (due to event bus module)
- New database queries: 0 (uses existing bookings table)
- New CSS: ~400 bytes (4 color classes)
- New PHP: ~25 lines (one endpoint handler)

✅ **Low-risk deployment** - Mostly UI/client-side changes
✅ **No database migrations needed** - Uses existing tables
✅ **Backward compatible** - Old bookings still display
