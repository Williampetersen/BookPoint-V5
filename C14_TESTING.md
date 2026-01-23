# C14 Testing Checklist

## ✅ C14.1 — Click Event Opens Drawer

**Step 1:** Navigate to Calendar page (`wp-admin/?page=bp_calendar`)
**Step 2:** Click on any colored event block
**Expected Result:**
- Drawer slides in from right
- Shows "Booking #123" title
- Displays start_datetime → end_datetime
- Shows customer, service, agent, email in drawer

---

## ✅ C14.2 — Status Change Endpoint Works

**Step 1:** Open drawer (from C14.1)
**Step 2:** Look for status selector in BookingDrawer
**Step 3:** Change status (e.g., pending → confirmed)
**Expected Result:**
- Status updates immediately
- `POST /admin/bookings/{id}/status` called with new status
- Drawer shows updated status

---

## ✅ C14.3 — Event Bus Triggers Refresh

**Step 1:** Drag an event to a new time
**Step 2:** Watch calendar
**Expected Result:**
- Event snaps to new slot
- Request to `/admin/bookings/{id}/reschedule` sent
- Calendar events reload automatically
- Event stays in new position

**Step 2b:** Change status in drawer
**Expected Result:**
- Calendar refreshes without manual action
- Event color updates if status changed

---

## ✅ C14.4 — Colors By Status

**Visual Check:**

| Status | Color | Background |
|--------|-------|------------|
| confirmed | Dark green text | Light green bg |
| pending | Dark orange text | Light orange bg |
| cancelled | Gray text | Light gray bg |
| completed | Dark blue text | Light blue bg |

**How to verify:**
1. Add test bookings with each status
2. View in month/week/day views
3. Colors should be consistent and visible

---

## Common Issues & Fixes

**Issue:** Drawer not appearing
- Clear browser cache: Ctrl+Shift+Delete
- Hard refresh: Ctrl+Shift+R
- Check console for errors: F12 → Console tab

**Issue:** Event title shows "Booking" instead of "Service • Customer"
- Rebuild admin bundle: `npm run build:admin`
- Check CalendarScreen event mapping has service_name + customer_name

**Issue:** Colors not showing
- Check CSS is loaded: F12 → Elements → find `.bp-evt-confirmed`
- Verify public/admin-app.css has color classes
- Clear cache and rebuild

**Issue:** Status change doesn't refresh
- Check event bus is imported in CalendarScreen
- Verify BookingDrawer calls `onUpdated` prop
- Check network tab for `/admin/bookings/{id}/status` call

---

## Files to Deploy

```
build/admin.js                                  ← rebuilt with all changes
build/admin.asset.php                          ← dependency manifest
lib/rest/admin-calendar-routes.php             ← new status endpoint
src/admin/screens/CalendarScreen.jsx           ← new event bus integration
public/admin-app.css                           ← new event colors
```

No PHP file changes needed on production (endpoints auto-register via hook).
