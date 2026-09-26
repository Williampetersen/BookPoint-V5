# Changelog

Repo-level technical changelog for the 3.0 rebuild. See `readme.txt` for the WordPress.org-facing
version, `FEATURES.md` for the full feature contract, `DECISIONS.md` for the reasoning behind each
architectural call, `BUGS.md` for the full 2.6.23 audit this release fixes, and `DESIGN-QA.md` for
the final verification pass.

## 3.0.0

Full rebuild from procedural PHP/jQuery to namespaced OOP PHP services behind a REST API, with
original React front ends for the admin app, booking wizard, manage page and customer portal.
Every 2.6.23 feature, table, option, shortcode, REST route and admin-post/AJAX action from
`FEATURES.md` is kept (`D-001`, `D-020`); nothing was removed.

### Architecture

- `PointlyBooking\` namespace, PSR-4 autoloading (`includes/Autoloader.php`), no Composer
  dependency at runtime.
- REST API under `pointly-booking/v1`, one controller per resource, capability-gated
  `permission_callback`s, `{status:'success', data}` success envelope.
- React 18 admin app (`src/admin/`), booking wizard (`src/front/wizard/`), and a manage-page +
  customer-portal bundle (`src/manage/`), sharing an original component library (`src/ui/`).
- Classic JSX runtime (`@wordpress/element`) for WordPress 6.2+ compatibility (`D-004`).
- Minimum WordPress raised to 6.2 (from 6.0) for `$wpdb->prepare()`'s `%i` identifier
  placeholder and React 18 `createRoot` (`D-003`).

### Fixed (see `BUGS.md` for full IDs and severity)

**Money and data-loss (Critical):**
- Stripe Checkout and PaymentIntent amounts are now computed server-side from the authoritative
  booking total, never trusted from the client (B-090).
- Saving the global or per-agent schedule no longer wipes existing rows before the new ones are
  inserted (B-020).
- The booking wizard sends real confirmation/received emails on a fresh install; admin status
  changes and reschedules now fire workflows, emails, webhooks and audit entries the same way
  wizard bookings do (B-050, B-051).

**Booking and availability (High):**
- Double-booking protection now covers every entry point (wizard, admin, manage-page reschedule,
  legacy routes) via a MySQL `GET_LOCK` plus a re-check inside the lock, and checks conflicts
  across all of an agent's services, not just the same one (B-011, B-022).
- Admin availability slots no longer reference non-existent columns; admin double-booking via a
  broken slot query is fixed (B-021).
- Extras created through the REST API are now priced and charged correctly without needing a
  manual "sync relations" pass (B-027).
- Services and "any available" agent selections can be booked even when no agent record exists
  yet (B-028).
- The manage-link token length mismatch that broke every emailed cancel/reschedule link is fixed;
  tokens rotate correctly after a customer action (B-037).
- Recurring holidays that cross the year boundary (e.g. 24 Dec – 2 Jan) now match correctly
  (B-029).
- Availability caches are invalidated when a booking is created, so the calendar stops offering
  already-taken slots (B-034).

**Payments (High):**
- Stripe live/test key selection now follows the configured mode instead of always using the test
  key (B-091). WooCommerce cart totals reflect the real booking price instead of the product's own
  fixed price (B-092).

**Security and privacy (Medium):**
- Agent email, phone and schedule data are no longer exposed to anonymous visitors on public
  endpoints (B-100). Rate limiting and a honeypot now cover the public REST booking endpoints, not
  just the legacy AJAX path (B-102). The Manager role can no longer read or overwrite Stripe/PayPal
  secret keys — those stay `manage_options`-only (B-073).
- The customer portal's rate limiter no longer calls `wp_die()` mid-page-render (B-103).

**Admin UI and correctness (High/Medium):**
- The dashboard's "recent bookings" query and both calendar endpoints no longer reference
  non-existent columns (B-070). Admin booking reads no longer use a PHP 8.1-only function that
  crashed the plugin on PHP 7.4–8.0 (B-072). Customer records are no longer silently overwritten
  by unrelated bookings that reuse the same email (B-031). Deleting a customer no longer leaves
  orphaned bookings (B-074). Inactive categories and extras are no longer shown to customers
  (B-075).
- Notification `.ics` attachments are no longer malformed by double-escaped line breaks (B-052).

**Performance:**
- Migrations and table checks no longer run on every admin page load — only when the stored DB
  version actually differs (B-120). Slot generation no longer runs hundreds of queries per day
  (B-121). The admin bundle is code-split per screen instead of one 633 KB file (B-124). The one
  N+1 query found in this rebuild's own Phase 5 audit (`StaffController::agents_index()`) was
  fixed before shipping (`D-055`).

**WordPress.org compliance:**
- Third-party brand assets that resembled another commercial plugin's UI are gone — every asset in
  this build is original (B-140, brief requirement). `uninstall.php` now actually removes plugin
  options, the design setting, transients, custom roles/capabilities and every plugin table when
  the delete-data option is set (B-146). A `.pot` file now ships for translations (B-144, B-013,
  B-081, B-054). `readme.txt` accurately describes the 3.0 architecture and external services.

### Changed / improved beyond the 2.x baseline

- Global and per-agent schedules now support multiple intervals and breaks per day, not one
  range (Settings → Schedule); the legacy single-range settings keys are kept for storage/import
  compatibility but are no longer the primary editing UI (see `DESIGN-QA.md` F-220).
- The customer portal is a small React app with a session token, replacing 2.x's server-rendered,
  URL-token-based flow (`D-016`).
- Workflow-delayed sends now run under a new cron hook (`pointlybooking_run_workflow`); the old
  `pointlybooking_run_workflow_event` name is kept registered only to drain jobs a 2.x site had
  already queued before upgrading (see `DESIGN-QA.md` F-190/F-292).
- The admin body class gained `pbk-admin-page` alongside the original `bp-app-mode` (kept for any
  site CSS/JS still targeting the old name).

### Known non-blocking gaps (see `DESIGN-QA.md`)

- The General settings currency picker lists ~36 ISO codes rather than the historical ~160, and
  has no live formatted-amount preview.
- The Plugins-screen quick link now reads "Dashboard" instead of the 2.x "Open BookPoint" wording.

---

## Pre-3.0 (2.6.23 and earlier)

Procedural PHP with jQuery admin views and a jQuery-driven front-end wizard. See `BUGS.md` for the
full audit of that codebase's issues, all addressed above.
