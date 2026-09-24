# BookPoint 2.6.23 — Bug, Security, Performance and Compliance Audit

Found during the Phase 0 read-through of every PHP, JS, CSS and template file.
Severity: **Critical** (data loss, money, security), **High** (feature broken), **Medium**
(wrong behaviour or significant UX problem), **Low** (polish, code quality).
Status column: `open` before the rebuild; set to `fixed in 3.0` (with a short note) in Phase 7.

## A. Booking wizard (front end)

| ID | Sev | Where | Problem | Status |
|----|-----|-------|---------|--------|
| B-001 | Critical | `src/front/wizard/steps/StepPayment.jsx` → `/front/payment/stripe/start` | The Stripe Elements (inline card) flow never sends the booking `key`, but the endpoint requires it → every inline card payment fails with "Missing booking key". Confirm has the same problem. | open |
| B-002 | High | `WizardModal.jsx` | There is no UI for choosing a payment method. The method is silently set to the default from settings, so customers can never pick Stripe, PayPal, or WooCommerce themselves. | open |
| B-003 | High | `StepConfirmation.jsx` | Cash/pay-later bookings (status confirmed, payment unpaid) show "Payment is still processing… Retry payment", which is confusing and wrong. Free bookings show "Payment successful". | open |
| B-004 | High | `StepLocation.jsx` + default design | The location step is enabled by default. With no locations defined the list is empty and "Next" stays disabled, so the wizard is unusable on a fresh install until the admin disables the step. The same applies to the category step when no categories exist. | open |
| B-005 | High | `StepCustomer.jsx` / designer `fieldsLayout` | Once the designer has saved a fields layout, any custom field added later in Form Fields is **never shown** in the wizard (the layout list wins). Required fields that are missing from the layout make server validation fail with no visible field. | open |
| B-006 | Medium | `StepAgent.jsx` | Agent email addresses are shown publicly. There is no "Any available" option: customers must pick a specific person. | open |
| B-007 | Medium | `StepDateTime.jsx` | "Today" is computed in UTC in the browser, not in the site timezone. Days without slots are still clickable (only a thin bar hints availability). No auto-selection of the first available date. The calendar ignores `default_date` for the visible month. No timezone is shown. | open |
| B-008 | Medium | `/public/availability-slots` | Past time slots for today are offered (only whole past days are excluded, computed with UTC `gmdate`). | open |
| B-009 | Medium | `WizardModal.jsx` | No promo-code input, although promo codes, validation, and server-side discounts exist. The total shown never reflects a discount. | open |
| B-010 | Medium | `WizardModal.jsx` | Esc / close discards all entered data immediately with no confirmation. | open |
| B-011 | Medium | wizard | Only a progress bar with "Step X of Y"; no clickable stepper, and going back from later steps is only possible one step at a time. | open |
| B-012 | Medium | wizard | Confirmation shows no booking details and no "Add to calendar" option. | open |
| B-013 | Medium | wizard (all strings) | Every customer-facing string is hard-coded English, so the wizard cannot be translated. | open |
| B-014 | Low | `DynamicFields.jsx` | The `radio` and `number` field types are rendered as plain text inputs, email format is not validated on the client, and there are no `autocomplete` attributes. | open |
| B-015 | Low | `StepDateTime.jsx` | Weekday headers are hard-coded Monday-first English letters ("M T W…"). | open |
| B-016 | Low | wizard | Month availability is fetched by a request that runs the full slot generator for every day of the month (see B-121); visibly slow on shared hosting. | open |
| B-017 | Medium | wizard | Slot label end time includes buffers (`occupied_min`), so a 30-min service with a 10-min buffer shows "10:00 – 10:40". | open |

## B. Booking creation, availability and scheduling (server)

| ID | Sev | Where | Problem | Status |
|----|-----|-------|---------|--------|
| B-020 | Critical | `lib/rest/admin-schedule-routes.php` `pointlybooking_rest_admin_schedule_save()` | Inserts into undefined `$t` **after deleting** the existing schedule rows. Saving the global or agent schedule wipes it, and availability then falls back to "08:00–20:00 every day" (see B-024). **Data loss.** | open |
| B-021 | High | `lib/rest/admin-catalog-routes.php` `/admin/availability/slots` | Queries the non-existent columns `bookings.start_date` / `start_time` → SQL error → every slot is shown free in admin (create/reschedule), allowing admin double bookings. The "any agent" branch selects the non-existent `agents.name` → no slots at all. Fixed 15-min step ignores the slot-interval setting. | open |
| B-022 | High | `lib/rest/public-booking-routes.php` `pointlybooking_insert_booking_from_payload()` | The availability check (`overlapping_count`) is scoped to the **same service**: an agent can be double-booked by two different services at the same time. | open |
| B-023 | High | `POST /public/bookings` | Cash/free bookings are always created as `confirmed`, ignoring the "Default status" setting (`pointlybooking_default_booking_status`). | open |
| B-024 | High | `ScheduleHelper::get_working_hours()` / public slot generator | When no schedule rows exist, "working hours" default to **08:00–20:00 every day including weekends**, contradicting the settings default (Mon–Fri 09–17, 12–13 break). | open |
| B-025 | High | availability | There are three unrelated sources of truth for agent hours: `schedules` (agent rows), `agent_working_hours` + `agent_breaks` (agent editor), and `agents.schedule_json` ("override schedule" toggle, **never read**). "Copy global schedule to agent" writes to `schedules`, which then silently overrides the agent editor. | open |
| B-026 | High | locations | The chosen location is not stored on the booking, agents are not filtered by location (`location_id` is accepted but ignored), and the location custom schedule is never applied. The location step has no functional effect. | open |
| B-027 | High | `ServiceModel` / pricing | REST-created extras never set the legacy `service_extras.service_id` column (NOT NULL, no default → insert fails under MySQL strict mode). Pricing reads extras via that legacy column, while the wizard lists extras via the `extra_services` map, so selected extras are **not charged** (price 0) unless "Sync relations" is run. | open |
| B-028 | High | `insert_booking_from_payload()` | Requires `agent_id > 0`, so services with no agents (or an "any agent" choice) can never be booked. | open |
| B-029 | Medium | holidays | Recurring holidays that cross the year end (e.g. 24 Dec – 2 Jan) never match (`DATE_FORMAT(m-d) BETWEEN`). | open |
| B-030 | Medium | `insert_booking_from_payload()` | Stores the raw client `extras` array in `extras_json` (arbitrary client JSON, including fake names/prices shown in admin). | open |
| B-031 | Medium | `insert_booking_from_payload()` | An existing customer's first/last name and phone are overwritten by any later booking that uses the same email (by anyone). | open |
| B-032 | Medium | admin PATCH booking | `end_datetime` is set to start + duration **+ buffers** on reschedule, but start + duration on create, so the data is inconsistent. | open |
| B-033 | Medium | `pointlybooking_public_max_booking_days` (120) vs setting `pointlybooking_future_days_limit` (60) | Two booking-window limits disagree; the wizard ignores the admin setting. | open |
| B-034 | Medium | `/front/slots`, `/front/availability*` | Availability is cached for 60–300 s in transients, and the caches are **not invalidated** when a booking is created, so the UI keeps offering taken slots. | open |
| B-035 | Medium | `ServiceModel` | Service create/update via REST does not flush the `pointlybooking_services_all_*` transients (stale for 5 min). | open |
| B-036 | Low | `/public/availability-slots?debug=1` | Leaks internal schedule data to anonymous visitors. | open |
| B-037 | Medium | `rotate_manage_token()` | Generates 40-hex tokens, but `find_by_manage_key()` only accepts 64-hex. After a customer cancels or reschedules, the redirect lands on "Booking not found", and **every emailed manage link stops working**. Demo bookings get unusable tokens too. | open |
| B-038 | Medium | manage page reschedule `/manage/slots` | Uses the legacy global-schedule slot generator (ignores agent schedules), unlike the wizard. | open |
| B-039 | Low | `BookingModel::detach_customer()` | Writes NULL into NOT NULL `customer_id` (GDPR delete can fail under strict SQL mode). | open |
| B-040 | Low | `BookingModel::create()` | Writes NULL into NOT NULL `payment_method` / `payment_status` for non-payment bookings (legacy path). | open |

## C. Notifications, emails, webhooks, audit

| ID | Sev | Where | Problem | Status |
|----|-----|-------|---------|--------|
| B-050 | Critical | booking REST path | Bookings from the current wizard send **no email at all** on a fresh install. Only workflows fire on booking_created, and none exist by default. The built-in customer/admin emails (`EmailHelper::booking_created_*`) run only on the dead legacy AJAX path. No webhook and no audit entry are recorded either. | open |
| B-051 | High | admin status change (`PATCH /admin/bookings/{id}`, `POST …/status`, calendar) | Status changes and reschedules made in admin fire **no** workflows, emails, webhooks, or audit entries, because they bypass `BookingModel::update_status()`. | open |
| B-052 | High | `Notifications_Helper::build_ics_attachment()` | The .ics text uses literal `\r\n` (double-escaped in double quotes), so attached calendar files are malformed. | open |
| B-053 | Medium | `EmailHelper::send()` | The kses allow-list strips `h2`, `div`, `table`, and inline styles, so the built-in templates and admin-authored HTML bodies lose their structure. | open |
| B-054 | Medium | `EmailHelper` templates | "Booking Received", "Hi", "Status: Pending", etc. are not translatable. | open |
| B-055 | Medium | email settings | `pointlybooking_email_enabled`, admin email, and from name/email exist but were dropped from the React settings UI (only reachable in dead PHP views). The same applies to all webhook settings and the delete-data-on-uninstall flag. | open |
| B-056 | Low | workflow cron | The whole booking/customer payload is stored in cron args (can be large). The payload is not refreshed when the delayed email runs, so it may send stale data. | open |

## D. Admin UI and data integrity

| ID | Sev | Where | Problem | Status |
|----|-----|-------|---------|--------|
| B-070 | High | `lib/rest/dashboard-routes.php` | "Recent bookings" selects the non-existent columns `customer_name`, `service_name`, `agent_name` → SQL error → the list is always empty. Legacy `DashboardHelper` has the same issue (plus `agents.name`). `/admin/calendar-legacy` too. | open |
| B-071 | High | `FormFieldsSeedHelper::ensure_defaults()` on every `admin_init` | Force-resets label, type, required, enabled, and sort order of first_name, last_name, email, and phone on **every admin page load**, so admin edits to these fields are silently reverted. | open |
| B-072 | High | admin booking GET | Uses `array_is_list()` (PHP ≥ 8.1, WP polyfill only since 6.5) → fatal error on PHP 7.4–8.0 with WP 6.0–6.4 (supported versions). | open |
| B-073 | Medium | `/admin/settings` POST | Accepts and stores **any** key (sanitize_text_field on everything). Users with `pointlybooking_manage_settings` (Manager role) can read and overwrite Stripe/PayPal secret keys that the payments endpoint restricts to `manage_options`. | open |
| B-074 | Medium | customer delete | Deleting a customer leaves orphan bookings pointing to a missing customer. | open |
| B-075 | Medium | public `/public/categories`, `/public/extras` | Inactive categories and extras are returned and shown to customers. | open |
| B-076 | Medium | service-agent relations | Two pivot tables (`agent_services`, `service_agents`) are used by different endpoints (`/service-agents` reads the other one). | open |
| B-077 | Low | menu | "Form Fields" appears three times in the submenu (`bp-form-fields`, `pointlybooking_form_fields`, `pointlybooking_form_fields_edit`). Schedule/Holidays/Promo/Notifications/Audit/Tools menu items immediately redirect. The menu is also duplicated under Settings and Plugins (clutter, and possibly seen as a nag by reviewers). | open |
| B-078 | Low | Tools "Reset cache" | Calls `wp_cache_flush()`, flushing the **entire site** object cache (other plugins' data). | open |
| B-079 | Low | audit export via REST | Returns CSV as a JSON-encoded string. | open |
| B-080 | Low | admin | The React settings screen expects `window.pointlybooking_ADMIN.restUrl`. Many screens swallow errors (console only) with no user feedback. Most screens have no empty/error states. | open |
| B-081 | Low | admin | Admin strings are hard-coded English (not translatable). | open |

## E. Payments

| ID | Sev | Where | Problem | Status |
|----|-----|-------|---------|--------|
| B-090 | Critical | `lib/rest/front-payments-stripe.php` `pointlybooking_stripe_start_checkout()` | The Stripe Checkout amount and currency come from the **client payload** (`total_price`), not from the server-computed booking total. A customer can pay €0.01 for any booking, and the webhook then marks it paid and confirmed. | open |
| B-091 | High | same file | The secret key is chosen by `payments_stripe_mode` (never written by the admin UI, default `checkout`), so Stripe Checkout **always uses the test key**, even in live mode. The webhook secret is read only from `stripe_webhook_secret`. | open |
| B-092 | High | `front-payments-woocommerce.php` | The cart line is the configured WC product at its **own fixed price**. The booking total is never applied (no `woocommerce_before_calculate_totals`), so customers pay the wrong amount. The client total is stored in cart meta. | open |
| B-093 | Medium | WooCommerce hooks | Only `woocommerce_payment_complete` confirms, so offline gateways (bank transfer, COD) leave bookings `pending_payment` forever even when the order is completed by admin. | open |
| B-094 | Medium | `payments_require_payment_to_confirm` | Stored and shown in admin, but never enforced by any code path. | open |
| B-095 | Medium | `/front/booking/create` | Accepts any `payment_method` string (not checked against the enabled methods). | open |
| B-096 | Low | Stripe PaymentIntent start | Hard-coded fallback currency `dkk`. | open |

## F. Security

| ID | Sev | Where | Problem | Status |
|----|-----|-------|---------|--------|
| B-100 | High | `/public/agents`, `/front/agents` | `SELECT a.*` exposes agent email, phone, and schedule JSON to anonymous visitors (privacy leak). | open |
| B-101 | Medium | `POST /booking/create` (legacy, main file) | A public endpoint with no availability check, no nonce, and no rate limit; it increments promo usage and stores raw fields. | open |
| B-102 | Medium | public booking endpoints | No rate limiting or honeypot on the REST booking endpoints (the legacy AJAX path had a honeypot). | open |
| B-103 | Medium | `rate_limit_or_block()` inside the `[pointlybooking_customer_portal]` shortcode | `wp_die()` while rendering page content breaks the whole page (and admin previews). | open |
| B-104 | Medium | query var `key` | Registers the generic public query var `key`, which can collide with other plugins/themes. | open |
| B-105 | Medium | manage page | Replaces **every** `the_content` call on the request (widgets, excerpts, other blocks). Inline `onclick="return confirm('…')"` handlers with an untranslated string. | open |
| B-106 | Low | `debug_admin_menu_notice()` | Reads other plugins' PHP source files from disk to find "menu removers". Surprising, and the kind of thing reviewers flag. | open |
| B-107 | Low | admin booking GET | Returns the full raw DB row, including `manage_key`, to the admin UI (more than needed). | open |
| B-108 | Low | `/admin/booking-form-design` POST | Stores an arbitrary, unsanitised array (admin-only, size-limited). | open |
| B-109 | Low | portal OTP | Bound to the client IP, so it breaks when mobile IPs change. `md5` keys. | open |

## G. Performance

| ID | Sev | Where | Problem | Status |
|----|-----|-------|---------|--------|
| B-120 | High | `admin_init` | Every admin page load runs `MigrationsHelper::run()` → `needs_run()` (≈40 `information_schema` queries), `Locations_Migrations_Helper::ensure_tables()` (3× `dbDelta`), and `FormFieldsSeedHelper::ensure_defaults()` (writes). The DB version option is shared by two helpers with different version numbers. | open |
| B-121 | High | slot generation | For every candidate slot it calls `is_within_schedule()` → `is_date_closed()` and `get_day_windows()` (several queries each) plus `get_service_rules()` with an `information_schema` columns query per existing booking. A day costs hundreds of queries and a month tens of thousands. | open |
| B-122 | Medium | asset enqueue (front + admin) | Recursively scans `public/images` and globs the icons directory for mtimes on **every page view** that loads the wizard or admin. | open |
| B-123 | Medium | `SettingsHelper::get_with_default()` | Rebuilds the whole defaults array (including `get_option`, `get_bloginfo`, and a `file_exists()` + read of `public/defaults.json`) on every call. | open |
| B-124 | Medium | admin bundle | 633 KB `admin.js` loaded for every admin screen (FullCalendar bundled even when not on the calendar). No code splitting. | open |
| B-125 | Medium | front assets | Loaded only for the shortcode (`has_shortcode`), not the block, so block pages rely on late enqueue in the footer. | open |
| B-126 | Low | customers list | Correlated subquery per row for the bookings count. | open |
| B-127 | Low | `pointlybooking_db_table_columns()` | Called on almost every request path (information_schema). | open |

## H. WordPress.org guideline / Plugin Check / originality issues

| ID | Sev | Where | Problem | Status |
|----|-----|-------|---------|--------|
| B-140 | High | `public/images/*` | Ships third-party brand assets and images whose names and look match another commercial booking plugin (`processor-braintree.png`, `processor-stripe-connect.png`, `payment_now_w_paypal.png`, `white-curve.png`, `blue-dot.png`, `office-365-logo-compact.jpg`, `apple-/google-/facebook-/outlook-logo-compact.png`, `intl-tel-input/`). Originality/licensing risk; must be replaced with original assets. | open |
| B-141 | Medium | many files | Hundreds of `phpcs:ignore`/`phpcs:disable` suppressions instead of compliant code. `phpcs:disable` without matching `enable` in several files. | open |
| B-142 | Medium | `ensure_react_scripts()` | Registers `react`/`react-dom` from `public/vendor/*` (does not exist) and injects a hand-written JSX runtime shim via inline script. Build output depends on the `react-jsx-runtime` handle. | open |
| B-143 | Medium | readme.txt | Lists "BookPoint licensing and activation service" under External services although the free package strips that code. Changelog/FAQ need an update for 3.0. `Tested up to: 7.1` must match reality. | open |
| B-144 | Medium | i18n | No `.pot` file, no `wp_set_script_translations()`; JS strings are not translatable (see B-013, B-081). | open |
| B-145 | Low | legacy duplicate builds | `public/front.js`, `public/index.jsx*.css` (stale copies), `lib/testsyn.css` (junk), `public/icon.php` referenced but missing, and `iconsProxyUrl`. | open |
| B-146 | Low | uninstall.php | Does not remove `pointlybooking_settings`, `pointlybooking_booking_form_design`, `pointlybooking_version`, `pointlybooking_caps_seeded`, the `agent_services` table, custom roles/caps, transients, or scheduled cron events. | open |
| B-147 | Low | `add_submenu_page(null, …)` | Passing `null` as the parent triggers PHP 8.1+ deprecation notices in WP (`strip_tags(): Passing null`). | open |
| B-148 | Low | legacy PHP admin views and controllers (`lib/views/admin/*`, `lib/controllers/*`) | Dead code duplicating the React admin; several reference non-existent columns. | open |

---
Totals: 91 issues (4 Critical, 23 High, 41 Medium, 23 Low).
