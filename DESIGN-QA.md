# BookPoint 3.0 — Design QA (Phase 7)

Final verification pass against `FEATURES.md` (the feature contract). Legend: `✅` verified,
`⚠` verified with a real, noted discrepancy, `[ ]` not independently re-checked this phase.

## Methodology (read this before the tables)

Three different levels of evidence back the checkmarks below, and this file is honest about
which one applies to each section — a blanket "everything verified" would not be true:

1. **Live-tested this rebuild, with a browser or real REST calls, against seeded data**
   (Playwright suites in `.dev/e2e/`, or `wp eval-file` scripts dispatching real REST requests).
   This happened during Phase 3 (wizard, manage page, portal — `wizard.js`, `double-booking.js`,
   `manage-portal.js`) and Phase 4 (every admin screen — `bookings.js`, `calendar.js`,
   `services.js`, `staff.js`, `locations.js`, `customers.js`, `notifications.js`, `design.js`,
   `settings.js`). These suites still exist and can be re-run as regression tests.
2. **Structurally re-verified in Phase 7** by reading the actual shipped code (routes, DB
   schema, option names, constants, hook names) against each `FEATURES.md` line, specifically
   to catch drift between what was built and what the contract says — this is where the ⚠ items
   below were found.
3. **Carried over from an earlier phase's own audit** without a fresh Phase-7 recheck (e.g. the
   N+1 audit in Phase 5, the Plugin Check pass in Phase 6) — noted inline where relevant.

Sections 17–22 (Notifications, Webhooks, Payments, Settings, Audit, Tools) got a full line-by-line
re-check in Phase 7. Sections 1–3, 24–29 (identity, roles, menu, REST routes, admin-post/AJAX,
hooks, DB tables, options, assets) got a targeted structural re-check of every literal name the
contract calls out (slugs, table names, option names, route paths, hook names). Sections 4–16
(Dashboard through Manage/Portal) rely on the Phase 3/4 live testing plus the Phase 5 N+1 audit;
they were not re-clicked through a browser again in Phase 7, since nothing in Phase 5 or 6 touched
their code paths.

---

## 1. Plugin identity and bootstrap

| ID | Result |
|----|--------|
| F-001 | ⚠ Name/slug/text-domain/Domain-Path/GPLv2+/PHP 7.4 all match. `Requires at least` is **6.2**, not 6.0 — an intentional, documented change (D-003: needed for `%i` in `$wpdb->prepare()` and React 18 `createRoot`), not a regression. |
| F-002 | ✅ `scripts/package-plugin.ps1` renames `bookpoint-v5.php` → `pointly-booking.php` and excludes the three Pro helper files from the free ZIP. |
| F-003 | ✅ All eight constants defined in `bookpoint-v5.php` (`PLUGIN_FILE`, `PLUGIN_DIR`, `PLUGIN_URL`, `PLUGIN_PATH`, `LIB_PATH`, `PUBLIC_PATH`, `VIEWS_PATH`, `BLOCKS_PATH`). |
| F-004 | ✅ `includes/Compat/classes.php` defines `POINTLYBOOKING_Core_Plugin` (VERSION, DB_VERSION) and `class_alias`s it to `pointlybooking_Plugin`. |
| F-005 | ✅ `bookpoint-v5.php` shows the admin notice and returns; `Installer::activate()` calls `deactivate_plugins()` + `wp_die()` for a genuine duplicate, exempting `bookpoint-pro-addon` and license files. |
| F-006 | ✅ Boots on `plugins_loaded` priority 20, or immediately via `did_action()` check. |
| F-007 | ⚠ The Plugins-screen link text is **"Dashboard"**, not the contract's "Open BookPoint" — a cosmetic label change (still links to the dashboard); "Settings" link is unchanged. |
| F-008 | ✅ `pro-addon/includes/license_gate_helper.php` gates every `/pointly-booking/v1/*` route except `/admin/license*` on `strpos($route, ...)`. |
| F-009 | ⚠ The admin body class is **`pbk-admin-page`**, not `bp-app-mode` — renamed to match the `pbk-` CSS prefix convention (CLAUDE.md). Functionally equivalent, but any external CSS/JS targeting the literal `bp-app-mode` class would stop matching. |

## 2. Roles and capabilities

| ID | Result |
|----|--------|
| F-010 | ✅ All six capabilities defined in `Installer::CAPS`; administrator gets all via the `user_has_cap` filter (D-009). |
| F-011 | ✅ `pointlybooking_manager` role seeded with read + services/agents/bookings/customers/settings (no tools). |
| F-012 | ✅ `pointlybooking_staff` role seeded with read + bookings/customers. |
| F-013 | ✅ `pointlybooking_caps_seeded` option gates one-time seeding outside activation. |
| F-014 | ✅ Menu capability falls back through `manage_options`/`activate_plugins`. |

## 3. Admin menu and pages

| ID | Result |
|----|--------|
| F-020–F-039 | ✅ Every page slug in the contract (including the `bp-form-fields` alias) is registered in `includes/Admin/Menu.php`, mapped to a lazy-loaded React screen in `src/admin/App.js`. |
| F-040 | ✅ Legacy hidden action-page slugs (`*_delete`, `booking_confirm/cancel`, `customers_view`, `promo_codes_edit`, `form_fields_edit`) are all present in the menu registry. |
| F-041 | ✅ `?pointlybooking_menu_debug=1` diagnostics gated on `manage_options`; Tools shortcut present. |
| F-042 | ✅ Theme toggle uses `localStorage['pointlybooking_theme']` (Phase 4 build, `Shell.js`); collapsible sidebar and "+ Booking" quick action live-tested in Phase 4. |

## 4. Dashboard — 5. Bookings — 6. Calendar — 7. Services/categories/extras — 8. Agents

`F-050`–`F-096`: ✅ built and Playwright-verified against live seeded data in Phase 4 (KPI cards,
recent bookings, chart with range presets, full bookings CRUD + CSV/PDF export, month/week/day/list
calendar with drag-drop reschedule and holiday quick-add, services/categories/extras with drag
reorder and media picker, agent schedule + breaks + "copy global schedule"). Not re-clicked this
phase; nothing in Phases 5–6 touched this code. The one Phase 5 change in this area
(`ScheduleRepository::agents_with_schedule()` batching, F-093/F-096-adjacent) was verified by
comparing the endpoint's JSON output before/after against the live dev site (D-055).

## 9. Locations — 10. Customers

`F-100`–`F-117`: ✅ Built and Playwright-verified in Phase 4 (locations CRUD, location categories,
per-location agent assignment with service subsets; customers search/sort/pagination, GDPR
anonymise, CSV export via nonce link and import via real `admin-post.php` form submission per
D-051). `wp_user_id` linkage (F-117) is structurally present in `BookingsController`; not
independently re-tested this phase.

## 11. Schedule and availability

`F-120`–`F-127`: ✅ Global/per-agent multi-interval schedule with breaks, presets, holidays with
quick templates, booking-window filters, and the `GET_LOCK`-based double-booking protection were
all built and exercised by the Phase 3 `double-booking.js` concurrent-booking test (confirms
F-127 specifically: two simultaneous booking attempts for the same slot, one succeeds).

## 12. Promo codes

`F-130`–`F-133`: ✅ CRUD, duplicate-with-suffix, test calculator, and public
`GET /promo/validate` were built in Phase 4; the public validation endpoint's route is confirmed
present in `Front\WizardController` this phase.

## 13. Form fields

`F-140`–`F-144`: ✅ Confirmed structurally this phase (scopes, types, `field_values` table, JSON
mirrors on booking/customer) plus Phase 4 live testing of the admin editor (drag reorder,
required-field server validation exercised by the wizard e2e suite).

## 14. Booking Form Designer

`F-150`–`F-156`: ✅ Built and Playwright-verified in Phase 4 (`design.js`): appearance, per-step
editor with reorder modal, texts, fields-layout editor, live preview, `localStorage` draft
autosave with restore/discard banner, reset to defaults.

## 15. Customer booking wizard

`F-160`–`F-178`: ✅ Live-tested end to end in Phase 3 (`wizard.js`, desktop + mobile, modal +
inline shortcode mount, all steps in canonical order including payment methods and the
confirmation screen). Gutenberg block (F-161) and legacy trigger selectors (F-162) are structurally
present; not separately clicked through this phase.

## 16. Manage page and customer portal

`F-180`–`F-185`: ✅ Live-tested in Phase 3 (`manage-portal.js`): cancel, reschedule with live
slot re-check, token rotation, portal one-time-code flow verified against real emails via Mailpit.
Rate-limit thresholds (F-185) are structurally present in `Support\RateLimit` call sites; the
exact per-IP counts were not re-driven to their limits this phase.

## 17. Notifications and email

| ID | Result |
|----|--------|
| F-190 | ⚠ Workflow shape (name/status/event/conditions/offset) matches exactly, but the WP-Cron hook for **new** delayed sends is `pointlybooking_run_workflow` (`WorkflowEngine::CRON_HOOK`), not `pointlybooking_run_workflow_event` as the contract names it. The old name is still registered, but only to finish cron jobs a 2.x site already had queued at upgrade time — it never receives new schedules. Any external code hooking the old event name to observe or extend sends will silently stop firing. `includes/Services/Notifications/WorkflowEngine.php:20-21,128`. |
| F-191–F-197 | ✅ Actions (send_email + ics attach), smart variables (full list incl. `field_{key}`), workflow/action test + run logs, quick templates (created→customer / confirmed→customer / cancelled→admin all present, plus 3 extras), email settings, portal OTP email, tools test email. |

## 18. Webhooks — 19. Payments

`F-200`–`F-215`: ✅ Settings, HMAC-signed POST with `X-BP-Event`/`X-BP-Signature`, test-fire tool
action, and the full payments matrix (WooCommerce cart-meta flow, Stripe Checkout + Elements +
signature-verified webhook, PayPal order/approve/capture) all confirmed present and matching the
contract exactly. Route paths spot-checked directly against `Front\*` controllers this phase.

## 20. Settings

| ID | Result |
|----|--------|
| F-220 | ⚠ Slot interval, booking-limit days, default booking status all present. The currency list is **~36 ISO codes**, not ~160 (`src/admin/settings/GeneralTab.js`), and there's no live formatted-amount preview tied to the selected currency (only a static "Before ($10)/After (10€)" example). The legacy `pointlybooking_open_time`/`close_time`/`breaks`/`schedule_0..6` keys the contract describes as user-editable here have been **superseded** by the richer per-day multi-interval + breaks editor on Settings → Schedule (`/admin/schedule`) — functionally a strict improvement, but not what F-220 literally describes; the legacy keys remain in `Settings::schema()` only for storage/import compatibility, not as an editable UI. |
| F-221–F-223 | ✅ Uninstall flag, settings JSON export/import (exact plugin-id and key-prefix match confirmed by direct code read), and the option+legacy-table+`LEGACY_MAP` storage model all confirmed. |

## 21. Audit log

| ID | Result |
|----|--------|
| F-230 | ✅ All named audit events present, including `customer_gdpr_deleted`. |
| F-231 | ⚠ Search, event filter, actor-type filter, date range, detail panel with booking/customer links, CSV export, and clear-all are all present and correct. **Rows-per-page is not user-configurable** — `per_page` is hardcoded to 20 in the request and the shared `Pagination` component only takes a fixed prop, with no page-size control in the UI. |

## 22. Tools

`F-240`–`F-243`: ✅ System status, all six tool actions (sync relations, demo data, cache reset,
migrations, test email, test webhook), JSON report + settings export/import, and the per-browser
run log all confirmed present and live-tested in Phase 4 (this is also where the `result.message`
null-crash bug — D-052 — was caught and fixed).

## 23. How to Use

F-250 | ✅ `src/admin/screens/HelpScreen.js` exists and is wired to the `help` route.

## 24. REST API

`F-260`–`F-271`: ✅ Every controller class in `Rest\Routes::CONTROLLERS` (11 admin + 5 front)
accounted for; spot-checked route paths for the highest-risk groups this phase — promo validation,
manage slots, all six payment-provider routes (Stripe start/confirm, PayPal start/capture,
WooCommerce start, the signature-verified `/webhooks/stripe`) — all present with the exact paths
the contract names. The full admin CRUD surface (bookings/catalog/customers/schedule/locations/
settings/notifications/audit/tools) was exercised live during Phase 4's per-screen testing.

## 25. admin-post and AJAX actions

`F-280`–`F-282`: ✅ All `admin_post_pointlybooking_admin_*` actions registered in
`Admin\Actions`; the legacy `wp_ajax(_nopriv)_pointlybooking_{action}` hooks (built from a loop
over `slots`/`submit_booking` in `Frontend\LegacyAjax`) confirmed present, including the
`pointlybooking_hp` honeypot check.

## 26. Hooks, cron, query vars

| ID | Result |
|----|--------|
| F-290, F-291, F-293–F-295 | ✅ `pointlybooking_public_max_booking_days` filter, Pro license filter, query vars (`pointlybooking_manage_booking`, `key`, `pointlybooking_action`), WooCommerce hooks, and every named legacy global function confirmed present in `includes/Compat/functions.php`. |
| F-292 | ⚠ Same finding as F-190: the cron action fired for new workflow sends is `pointlybooking_run_workflow`, not `pointlybooking_run_workflow_event`. The old name is kept registered only so `Installer`/`uninstall.php` can clear any hook still scheduled under it from before the upgrade — it is not the live signal for new sends. This is a real naming drift from the contract, not a functional loss (workflows still run, delayed emails still send), but external code integrating against the literal old hook name would need updating. |

## 27. Database tables

F-300–F-318 | ✅ All 27 tables (including `bundles`/`bundle_items`, kept per the contract with no
UI) confirmed present by name in `includes/Database/Tables.php`. Column-level detail for every
table was not re-diffed line-by-line against `Schema.php` this phase (this was done in Phase 1
when the schema was written and has not changed since — no migration touched table shape in
Phases 4–6).

## 28. Options and transients

F-320–F-322 | ✅ All named options confirmed via direct `get_option`/`update_option` grep.
`uninstall.php` now correctly removes settings, design, version, caps-seeded flag, roles/caps,
transients and all plugin tables when the delete-data flag is set — this is a genuine fix over
2.x, which left most of that behind (BUGS.md B-146).

## 29. Assets and localisation objects

F-330–F-333 | ✅ `window.pointlybooking_FRONT`, `_ADMIN`, and `_MANAGE` all confirmed emitted via
`wp_add_inline_script()` in `Frontend\Assets` / `Admin\Assets`. Conditional front-asset loading
(shortcode/block detection) and RTL stylesheets structurally present; not re-tested on an RTL
locale this phase.

---

## Summary

Of 333 feature-contract items, **5 real discrepancies** were found in this phase's re-check,
none of them regressions in customer-facing behavior:

1. **F-007** — Plugins-screen link reads "Dashboard" instead of "Open BookPoint" (cosmetic).
2. **F-009** — Admin body class renamed `bp-app-mode` → `pbk-admin-page` (consistent with the
   project's own `pbk-` CSS convention, but a literal break for anyone targeting the old class).
3. **F-190 / F-292** — The workflow cron hook for new sends is `pointlybooking_run_workflow`,
   not `pointlybooking_run_workflow_event`; the old name only drains pre-upgrade scheduled jobs.
   External code hooking the literal old event name to observe/extend sends needs updating.
4. **F-220** — Currency list is ~36 codes (not ~160) and has no live amount-format preview; the
   legacy open/close/breaks/schedule-string settings keys are superseded by a richer schedule
   editor (functionally better, but not what the line literally describes).
5. **F-231** — Audit log page size is fixed at 20 rows; no per-page selector in the UI.

None of these are launch-blocking. #3 and #4 are worth a line in `DECISIONS.md` since they're
intentional architectural improvements that happen to diverge from the literal 2.x-era wording of
the contract; #1, #2, and #5 are small enough to either fix directly or accept and move on.

**Recommendation:** fix #2 (`bp-app-mode` class) and #5 (audit page-size selector) now, since both
are small and #2 in particular is a silent compatibility break for anyone who copied 2.x CSS. Leave
#1 as-is (already fine) and log #3/#4 as intentional decisions rather than "fixing" them back to
worse behavior.
