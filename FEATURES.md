# BookPoint — Feature Inventory (the contract)

This file lists every feature, setting, data structure, and integration point that existed in
BookPoint **2.6.23** before the 3.0 rebuild. It is the contract for the rebuild: every item here
must still work afterwards. Each item has an ID so it can be ticked off in Phase 7.

Legend: `[ ]` = not yet verified in 3.0, `✅` = verified working in 3.0.

---

## 1. Plugin identity and bootstrap

| ID | Item |
|----|------|
| F-001 | Plugin name "BookPoint Booking & Appointments", slug `pointly-booking`, text domain `pointly-booking`, Domain Path `/languages`, GPLv2+, Requires WP 6.0, PHP 7.4. |
| F-002 | Dev main file `bookpoint-v5.php` is shipped as `pointly-booking/pointly-booking.php` by `scripts/package-plugin.ps1`; the free zip strips `license_helper.php`, `license_gate_helper.php`, and `updates_helper.php`. |
| F-003 | Constants `POINTLYBOOKING_PLUGIN_FILE`, `_DIR`, `_URL`, `_PATH`, `_LIB_PATH`, `_PUBLIC_PATH`, `_VIEWS_PATH`, `_BLOCKS_PATH`. The Pro add-on checks `POINTLYBOOKING_PLUGIN_FILE` to detect the free plugin. |
| F-004 | Class `POINTLYBOOKING_Core_Plugin` (with `VERSION` and `DB_VERSION` constants) plus `class_alias` `pointlybooking_Plugin`, for backwards compatibility. |
| F-005 | If another copy of BookPoint is active: the duplicate-class guard shows an admin notice, and activation deactivates the new copy and stops with `wp_die`. The Pro add-on (`bookpoint-pro-addon`) is exempt. |
| F-006 | Boot runs on `plugins_loaded` (priority 20), or immediately if that hook has already fired. |
| F-007 | "Open BookPoint" and "Settings" quick links on the Plugins screen. |
| F-008 | Pro distribution: `bookpoint-pro.php` defines `POINTLYBOOKING_IS_PRO`, and the Pro add-on loads license, gate, and updates helpers. The REST gate blocks `/pointly-booking/v1/*` except `/admin/license*` when the Pro license is invalid. |
| F-009 | Admin body class `bp-app-mode` on every `pointlybooking_*` admin page. |

## 2. Roles and capabilities

| ID | Item |
|----|------|
| F-010 | Capabilities `pointlybooking_manage_bookings`, `pointlybooking_manage_services`, `pointlybooking_manage_customers`, `pointlybooking_manage_agents`, `pointlybooking_manage_settings`, and `pointlybooking_manage_tools`. All six are granted to `administrator`. |
| F-011 | Role `pointlybooking_manager` ("BookPoint Manager"): read, services, agents, bookings, customers, and settings (no tools). |
| F-012 | Role `pointlybooking_staff` ("BookPoint Staff"): read, bookings, and customers. |
| F-013 | Capabilities are seeded once per site even without activation (option `pointlybooking_caps_seeded`). |
| F-014 | Menu visibility falls back to `manage_options` or `activate_plugins`, so admins always see the menu. |

## 3. Admin menu and pages (slug → screen)

All pages render the React admin app (`#bp-admin-app`). The top-level menu is "BookPoint" (`dashicons-calendar-alt`, position 56).

| ID | Page slug | Screen / purpose |
|----|-----------|------------------|
| F-020 | `pointlybooking_dashboard` | Dashboard |
| F-021 | `pointlybooking_how_to_use` | How to Use guide |
| F-022 | `pointlybooking_bookings` | Bookings list |
| F-023 | `pointlybooking_bookings_edit` (hidden; `&id=` or `&new=1`) | Create/edit booking |
| F-024 | `pointlybooking_calendar` | Calendar |
| F-025 | `pointlybooking_schedule` → redirects to `settings&tab=schedule` | Schedule |
| F-026 | `pointlybooking_holidays` → `settings&tab=holidays` | Holidays |
| F-027 | `pointlybooking_services` / `pointlybooking_services_edit` | Services list / edit |
| F-028 | `pointlybooking_categories` / `pointlybooking_categories_edit` | Categories list / edit |
| F-029 | `pointlybooking_extras` / `pointlybooking_extras_edit` | Service extras list / edit |
| F-030 | `pointlybooking_locations` / `pointlybooking_locations_edit` / `pointlybooking_location_categories_edit` | Locations, location edit, location category edit |
| F-031 | `pointlybooking_promo_codes` → `settings&tab=promo_codes` | Promo codes |
| F-032 | `bp-form-fields`, `pointlybooking_form_fields` → `settings&tab=form_fields` | Form fields |
| F-033 | `pointlybooking_design_form` | Booking Form Designer |
| F-034 | `pointlybooking_customers` / `pointlybooking_customers_edit` | Customers list / edit |
| F-035 | `pointlybooking_settings` (tabs: general, payments, schedule, holidays, form_fields, promo_codes, notifications, audit_log, tools) | Settings |
| F-036 | `pointlybooking_notifications` → `settings&tab=notifications` | Notification workflows |
| F-037 | `pointlybooking_audit`, `pointlybooking_audit_log` → `settings&tab=audit_log` | Audit log |
| F-038 | `pointlybooking_tools` (also under Tools → BookPoint Tools) → `settings&tab=tools` | Tools |
| F-039 | `pointlybooking_agents` / `pointlybooking_agents_edit` | Agents (staff) list / edit |
| F-040 | Legacy hidden action pages (`*_delete`, `pointlybooking_booking_confirm`, `pointlybooking_booking_cancel`, `pointlybooking_customers_view`, `pointlybooking_promo_codes_edit`, `pointlybooking_form_fields_edit`): old deep links must still land somewhere sensible. |
| F-041 | Fallback menu entries under Settings/Plugins, plus `ensure_admin_menu_visible` for sites where another plugin hides the menu, and `?pointlybooking_menu_debug=1` diagnostics. |
| F-042 | Admin light/dark theme toggle (stored per browser in `localStorage` key `pointlybooking_theme`). Collapsible sidebar. "+ Booking" quick action in the top bar. |

## 4. Dashboard

| ID | Item |
|----|------|
| F-050 | KPI cards: bookings today, upcoming 7 days, pending, services count, agents count. |
| F-051 | Recent bookings list (customer, service, agent, status, when). |
| F-052 | Performance chart of bookings per day, with range presets (7/30/90 days, YTD, custom from/to). |
| F-053 | Quick actions: add service, add agent, manage bookings, open calendar, edit form fields, settings. |

## 5. Bookings

| ID | Item |
|----|------|
| F-060 | Bookings list: search (customer name, email, service, agent), status filter (all/pending/confirmed/cancelled), date from/to, sort (newest/oldest), pagination, per-page status counts, and filtered total. |
| F-061 | Booking detail drawer: ID, status, service, agent, customer (name/email/phone), start/end, created, extras (name/price/qty), promo code, discount, total, form responses (customer + booking custom fields), and admin notes. |
| F-062 | Change booking status: pending, confirmed, cancelled, completed (and system statuses pending_payment, failed_payment). |
| F-063 | Edit admin notes. |
| F-064 | Reschedule a booking (date + available slot for its agent), and change its agent. Conflicts, schedule, and holidays are validated on the server. |
| F-065 | Delete a booking (removes its field values too). |
| F-066 | Create a booking from admin: service, agent, date, time (from available slots), customer first/last name, email, phone, notes, and status. |
| F-067 | Export bookings to CSV (ID, status, service, agent, customer, email, phone, start, end, notes), respecting filters. |
| F-068 | Printable HTML "PDF" export of bookings, respecting filters. |
| F-069 | Admin status changes notify the customer (legacy email_status_change) and fire the `booking_status_changed` webhook and audit log entry. |

## 6. Calendar

| ID | Item |
|----|------|
| F-070 | Month, week, day, and list views with today/prev/next navigation. |
| F-071 | Filters: agent, status, and search text. Status colour legend. |
| F-072 | Click an event to open the booking drawer. |
| F-073 | Drag-and-drop reschedule, validated against the agent's schedule, breaks, conflicts, and capacity. |
| F-074 | Add a holiday from the calendar (title, start, end, global or per-agent scope, repeat yearly, enabled). |
| F-075 | Unavailable-time background blocks for a selected agent (`/admin/schedule/unavailable`). |
| F-076 | "+ Booking" shortcut. |

## 7. Services, categories, extras

| ID | Item |
|----|------|
| F-080 | Services list: search, status filter, sort by name/duration/price, and totals. |
| F-081 | Service fields: name*, description, duration (minutes, 5–1440)*, price, currency, image (media library), sort order, active status, buffer before/after (0–240), capacity (1–50), and categories (many-to-many). Legacy fields `use_global_schedule` and `schedule_json` (per-weekday override, `{"0":"HH:MM-HH:MM",...}`) are kept. |
| F-082 | Delete a service (removes its category, agent, and extra relations). |
| F-083 | Categories list and edit: name*, description, image, sort order, active status, and service count. Deleting a category removes its relations. |
| F-084 | Extras list and edit: name*, description, price, image, sort order, active status, and assigned services (many-to-many via `extra_services`). |
| F-085 | Extras are priced server-side and added to the booking total. |

## 8. Agents (staff)

| ID | Item |
|----|------|
| F-090 | Agents list: search, status filter, sort, services count, and avatar. |
| F-091 | Agent fields: first name, last name, email, phone, image, and active status (inactive agents are hidden in the wizard). |
| F-092 | Assign services to an agent (`agent_services`). If no agent is assigned to a service, the wizard offers every active agent. |
| F-093 | Agent weekly working hours (per weekday intervals, enabled flag) and date-specific breaks (date, start, end, note), stored in `agent_working_hours` and `agent_breaks`. |
| F-094 | Agent "override schedule" JSON (`agents.schedule_json`). |
| F-095 | Copy the global schedule to an agent. |
| F-096 | Delete an agent (removes its service relations). |

## 9. Locations

| ID | Item |
|----|------|
| F-100 | Locations list: search, status, category, sort, and image. |
| F-101 | Location fields: name*, address, category, image, status (active/inactive), "use custom schedule", and schedule JSON. |
| F-102 | Assign agents to a location, with an optional per-agent service subset (`location_agents.services_json`). |
| F-103 | Location categories: name, image, create/edit/delete. |
| F-104 | Location step in the wizard (when enabled and locations exist). |

## 10. Customers

| ID | Item |
|----|------|
| F-110 | Customers list: search (name/email/phone), sort (latest/earliest), pagination, and bookings count. |
| F-111 | Customer create/edit: first name, last name, email, phone, and customer-scope custom fields (`custom_fields_json`). Create deduplicates by email. |
| F-112 | Customer detail: booking history (service, agent, status, when). |
| F-113 | Delete a customer. |
| F-114 | GDPR "delete" (anonymise the customer and detach them from bookings). |
| F-115 | Export customers to CSV (id, first_name, last_name, email, phone, wp_user_id, created_at, updated_at). |
| F-116 | Import customers from CSV (≤5 MB, headers `first_name`, `last_name`, `email`, `phone`, or `name`), upserting by email. |
| F-117 | Logged-in WP users are linked on booking (`wp_user_id`). |

## 11. Schedule and availability

| ID | Item |
|----|------|
| F-120 | Global weekly schedule: per weekday (1=Mon…7=Sun), multiple intervals, each with breaks and an enabled flag (`schedules` table, `agent_id` NULL). Presets: Mon–Fri 09–17, Mon–Fri 08–16, every day 09–17, clear. |
| F-121 | Per-agent schedule (`schedules` table with `agent_id`), falling back to global. |
| F-122 | Schedule settings: slot interval (5–120 min) and timezone (`schedule_settings` table row id=1). |
| F-123 | Legacy settings schedule: `pointlybooking_schedule_0..6` ("HH:MM-HH:MM", 0=Sunday), `pointlybooking_breaks` (comma list), and open/close time. Used as a fallback. |
| F-124 | Holidays: title, start/end date, global or per-agent, repeat yearly, enabled. Year filter and agent filter. Quick templates (New Year, Christmas, Boxing Day, Christmas 2-day, Independence Day). |
| F-125 | Booking window: `pointlybooking_future_days_limit` (1–365, default 60), plus the filter `pointlybooking_public_max_booking_days` (default 120). |
| F-126 | Slot generation respects agent windows, breaks, holidays, service duration, buffers, capacity, and existing non-cancelled bookings. |
| F-127 | Server-side double-booking protection on create (MySQL `GET_LOCK` per agent/day + availability re-check). |

## 12. Promo codes

| ID | Item |
|----|------|
| F-130 | Promo codes CRUD: code (unique, uppercased, "Generate" button), type (percent/fixed), amount, starts_at, ends_at, max uses, minimum total, and active. |
| F-131 | Duplicate a promo code (`CODE-COPY`, `CODE-COPY2`…). |
| F-132 | Test calculator (order total → discount/result) and status badges (active/scheduled/expired/disabled). Usage counter. |
| F-133 | Public validation `GET /promo/validate?code=&subtotal=`. The discount is applied server-side on booking and the use count is incremented. |

## 13. Form fields

| ID | Item |
|----|------|
| F-140 | Fields per scope: `customer`, `booking`, `form`. Properties: key, label, type (text, email, tel, textarea, number, date, select, checkbox, radio), placeholder/help, options (select/radio), required, enabled, show in wizard, step (details/payment/summary), and sort order. |
| F-141 | Default fields: customer first_name*, last_name, email*, phone; booking notes. Reseed defaults. |
| F-142 | Reorder with drag/up/down. Search, status filter, and a preview panel. |
| F-143 | Values are stored per booking in the `field_values` table and as JSON on the booking (`customer_fields_json`, `booking_fields_json`, `custom_fields_json`) and on the customer (`custom_fields_json`). |
| F-144 | Required fields are validated on the server. |

## 14. Booking Form Designer (option `pointlybooking_booking_form_design`)

| ID | Item |
|----|------|
| F-150 | Appearance: primary colour, border style (rounded/flat/square), and dark mode default. |
| F-151 | Steps (location, category, service, extras, agents, datetime, customer, payment, review, confirm): enable/disable, title, subtitle, image (built-in or media library: `imageUrl`/`imageId`), per-step back/next labels, accent override, show left panel, and show help box. Service, agent, datetime, customer, review, and confirm are always enabled. |
| F-152 | Reorder steps (modal). |
| F-153 | Texts: help title, help phone, global next/back labels. |
| F-154 | Fields layout: customer and booking fields order, width (half/full), required override, and add/remove. |
| F-155 | Live preview, local draft autosave, publish, and reset to defaults. |
| F-156 | Legacy step-image upgrade (maps legacy image names to the new defaults). |

## 15. Customer booking wizard (front end)

| ID | Item |
|----|------|
| F-160 | Shortcode `[pointlybooking_booking_form]` with atts `label` (default "Book Now"), `service_id`, `default_date`, `hide_notes`, `require_phone`, and `compact`. Renders a "Book" button plus a mount point; clicking opens a modal wizard. Works in widgets and builders (late enqueue + print). |
| F-161 | Gutenberg block `bookpoint/booking-form` with attributes `serviceId`, `defaultDate`, `hideNotes`, `requirePhone`, and `compact`, rendered server-side through the shortcode. Editor sidebar with a service select. |
| F-162 | Legacy triggers: `#bp-front-root` + `.bp-open-wizard`, and `[data-bp-open="wizard"]`. |
| F-163 | Steps in canonical order: location → category → service → extras → agent → date/time → customer details → review → payment → confirmation, each enabled or disabled from the designer. |
| F-164 | Location step: cards with image, name, and address. |
| F-165 | Category step: cards with image; picking one filters services. |
| F-166 | Service step: cards with image, name, description, duration chip, price. Preselect via `service_id`. |
| F-167 | Extras step: multi-select cards with image, name, description, price. Optional. |
| F-168 | Agent step: cards with avatar and name. Auto-selected when the step is disabled and there is only one agent. |
| F-169 | Date & time step: month calendar with availability indicators, previous/next month, slot list for the chosen day, and preset `default_date`. |
| F-170 | Customer step: dynamic customer and booking fields (layout from the designer, half/full width), with required validation. `hide_notes` hides notes and `require_phone` makes phone required. |
| F-171 | Review step: every selection, custom field answers, payment method, and total. |
| F-172 | Payment methods (when payments are enabled): free, cash (pay at location), WooCommerce (redirect to checkout), Stripe Checkout (redirect), Stripe Elements (inline PaymentIntent), and PayPal (redirect + capture). The default method comes from settings. |
| F-173 | Payment return handling: `?pointlybooking_payment=stripe_success|stripe_cancel|paypal_return|paypal_cancel&booking_id&key[&token]`. Cancel marks the booking cancelled; success polls status until confirmed and paid. |
| F-174 | Confirmation step: booking ID, status, payment status, method, retry payment, and close. |
| F-175 | Live summary sidebar: location, category, service, service price, agent, date, time, extras, extras price, and total. Currency formatting (code + before/after position). |
| F-176 | Left panel: step image, title, subtitle, and help box (title + phone). |
| F-177 | Accessibility: focus trap, Esc closes, ARIA dialog, body scroll lock (reference counted), and dark mode (design setting or OS preference). Multiple widgets per page. |
| F-178 | Anti-spam honeypot `pointlybooking_hp` (legacy AJAX path). |

## 16. Manage booking page and customer portal

| ID | Item |
|----|------|
| F-180 | Manage page at `/?pointlybooking_manage_booking=1&key={64-hex manage_key}`: status, service, start, end. Rendered inside the theme content. |
| F-181 | Customer cancel from the manage page (nonce-protected). |
| F-182 | Customer reschedule from the manage page: pick a date → available times (`/manage/slots`, excluding the booking itself when the key matches), availability re-checked on the server. |
| F-183 | The manage token rotates after a customer action, and `manage_token_last_used_at` is recorded. |
| F-184 | Shortcode `[pointlybooking_customer_portal]`: email → 6-digit one-time code (10 min) by email → booking list with "Manage" links. Session lasts 20 min. Logout. |
| F-185 | Rate limiting (per IP): portal view 120/10 min, portal actions 20/10 min, manage view 60/10 min, manage actions 30/10 min. |

## 17. Notifications and email

| ID | Item |
|----|------|
| F-190 | Workflows: name, status (active/disabled), event (`booking_created`, `booking_updated`, `booking_confirmed`, `booking_cancelled`, `customer_created`), conditional flag and conditions JSON, and time offset (delay minutes → WP-Cron single event `pointlybooking_run_workflow_event`). |
| F-191 | Actions: `send_email` with to, subject, body (HTML), from name, from email, attach .ics; status and sort order. |
| F-192 | Smart variables `{{var}}`: site_name, site_url, admin_email, booking_id, booking_status, booking_notes, start/end date/time, booking_duration, customer_name/email/phone, agent_name/email/phone, service_name/duration, subtotal, discount, tax, total, promo_code, manage_booking_url_customer, manage_booking_url_agent, and `field_{key}` for custom fields. Smart-variables drawer in the editor. |
| F-193 | Workflow test / action test against a chosen or the latest booking. Workflow run logs (last run time and status) in `workflow_logs`. |
| F-194 | Quick templates: "Booking Created → Email Customer", "Booking Confirmed → Email Customer", and "Booking Cancelled → Email Admin". |
| F-195 | Email settings: `pointlybooking_email_enabled`, `pointlybooking_admin_email`, `pointlybooking_email_from_name`, and `pointlybooking_email_from_email`. Built-in customer "booking received" and admin "new booking" emails, plus the customer status-change email. |
| F-196 | Portal one-time-code email. |
| F-197 | Tools test email. |

## 18. Webhooks

| ID | Item |
|----|------|
| F-200 | Settings: `webhooks_enabled`, `webhooks_secret`, and `webhooks_url_booking_created`, `_booking_status_changed`, `_booking_updated`, `_booking_cancelled`. |
| F-201 | POST JSON `{event, site, timestamp, data}` with headers `X-BP-Event` and `X-BP-Signature` (HMAC-SHA256 of the body with the secret). |
| F-202 | Tools "Fire test webhook" for a chosen event. |

## 19. Payments

| ID | Item |
|----|------|
| F-210 | Payments settings (manage_options): payments enabled, enabled methods (free, cash, woocommerce, stripe, paypal), default method, and require payment to confirm. |
| F-211 | WooCommerce: product ID; the booking is added to the cart with meta `pointlybooking_booking_id`; payment complete → booking confirmed + paid. |
| F-212 | Stripe: mode test/live, test/live secret and publishable keys, webhook secret, success/cancel URLs, and the displayed webhook URL `/wp-json/pointly-booking/v1/webhooks/stripe`. Checkout Session flow, PaymentIntent (Elements) flow, and a signature-verified webhook (`checkout.session.completed`). |
| F-213 | PayPal: mode sandbox/live, client ID, secret, and return/cancel URLs. Order create → approve → capture, verified (status, custom_id, amount). |
| F-214 | Server-authoritative pricing: service price + valid extras + validated promo code. Stored as `total_price`, `discount_total`, `promo_code`, `currency`, `payment_amount`, and `payment_currency`. |
| F-215 | Booking payment fields: `payment_method`, `payment_status` (unpaid/pending/paid/cancelled/…), and `payment_provider_ref`. Payment cancel endpoint (key-protected). Status polling endpoint (key-protected). |

## 20. Settings (General tab and misc)

| ID | Item |
|----|------|
| F-220 | Open/close time, slot interval, booking limit days, currency (≈160 ISO codes, with preview), currency position (before/after), daily breaks, weekly schedule strings, and default booking status (confirmed/pending/cancelled/completed). |
| F-221 | Delete data on uninstall (`pointlybooking_remove_data_on_uninstall`). |
| F-222 | Settings JSON export/import (settings table + `pointlybooking_settings` option + design + uninstall flag). Import accepts the plugin ids `bookpoint-booking`, `pointly-booking`, and `bookpoint`, and only keys with the prefixes `pointlybooking_`, `payments_`, `stripe_`, `webhooks_`, `emails_`, `tpl_`, `booking_`, `portal_`. |
| F-223 | Settings storage: option `pointlybooking_settings` (array) + legacy key/value table `pointlybooking_settings`, with a legacy key map (slot_interval_minutes ↔ pointlybooking_slot_interval_minutes, currency ↔ pointlybooking_default_currency, currency_position ↔ pointlybooking_currency_position). Optional `public/defaults.json`, `default-settings.json`, and `default-design.json` override the defaults. |

## 21. Audit log

| ID | Item |
|----|------|
| F-230 | Audit events are recorded with event, actor type (admin/customer/system), WP user, IP, booking ID, customer ID, meta JSON, and time. Events used: booking_created, booking_status_changed, customer_rescheduled, customer_cancelled, settings_imported, tools_* (email_test, webhook_test, demo_generated, sync_relations, cache_reset, migrations_run, settings_imported), gdpr delete. |
| F-231 | Audit screen: search, event filter, actor type filter, date range, pagination and rows per page, detail panel with links to the booking/customer, export CSV, and clear all. |

## 22. Tools

| ID | Item |
|----|------|
| F-240 | System status: table existence, DB version, plugin version, WP/PHP versions, and counts. |
| F-241 | Actions: sync relations (legacy columns ↔ pivot tables), generate demo data (services, agents, customers, bookings), reset cache, run migrations, send test email, and fire test webhook. |
| F-242 | Download a system report (JSON). Export and import settings. |
| F-243 | Run log of recent tool actions (per browser). |

## 23. How to Use

| ID | Item |
|----|------|
| F-250 | A guide page covering first-time setup, the shortcode, button customisation, skipping steps, custom fields, the portal page, and troubleshooting. |

## 24. REST API (namespace `pointly-booking/v1`)

Public, customer-facing endpoints (must keep working; external links and payment providers depend on some):

| ID | Endpoint |
|----|----------|
| F-260 | `GET /categories`, `GET /services?category_id`, `GET /extras?service_id`, `GET /agents?service_id`, `GET /service-agents?service_id`, `GET /form-fields?scope`, `GET /promo/validate`, `POST /booking/create` (legacy) |
| F-261 | `GET /manage/slots?service_id&agent_id&date&exclude_booking_id&key` |
| F-262 | `GET /public/categories`, `/public/services`, `/public/extras`, `/public/agents`, `/public/form-fields`, `/public/settings`, `/public/availability-slots`, `GET /availability/timeslots`, `POST /public/bookings` |
| F-263 | `GET /front/locations`, `/front/categories`, `POST /front/services`, `POST /front/extras`, `POST /front/agents`, `POST /front/slots`, `GET /front/form-fields`, `GET /front/form-fields/active`, `POST /front/bookings`, `GET /front/availability?month`, `POST /front/availability/month`, `POST /front/availability/day`, `POST /front/availability/month-slots`, `GET /front/booking-form-design`, `GET /front/settings`, `POST /front/booking/create` |
| F-264 | `GET /front/bookings/{id}/status?key`, `POST /front/bookings/{id}/payment-cancel` |
| F-265 | `POST /front/payments/stripe/start`, `POST /webhooks/stripe` (**URL configured in Stripe dashboards — must not change**), `POST /front/payment/stripe/start`, `POST /front/payment/stripe/confirm`, `POST /front/payments/paypal/start`, `POST /front/payments/paypal/capture`, `POST /front/payments/woocommerce/start` |

Admin endpoints (used by the admin UI; kept for backwards compatibility with any custom integrations):

| ID | Endpoint group |
|----|----------------|
| F-266 | Bookings: `GET/POST /admin/bookings`, `GET/PATCH/DELETE /admin/bookings/{id}`, `POST /admin/bookings/{id}/status`, `PATCH|POST /admin/bookings/{id}/reschedule`, `GET /admin/availability/slots`, `GET /admin/calendar`, `GET /admin/calendar/bookings`, `GET /admin/calendar-legacy` |
| F-267 | Catalog: `/admin/categories[/{id}]`, `/admin/services[/{id}]`, `/admin/services/{id}/categories`, `/admin/extras[/{id}]`, `/admin/extras/{id}/services`, `/admin/agents[/{id}]`, `/admin/agents-full`, `/admin/agents/{id}/services`, `/admin/agents/{id}/schedule[/copy]` |
| F-268 | Customers `/admin/customers[/{id}]`, `/admin/customers/form-fields`. Field values `/admin/field-values`. Promo codes `/admin/promo-codes[/{id}[/duplicate]]`. |
| F-269 | Schedule `/admin/schedule`, `/admin/schedule/unavailable`. Holidays `/admin/holidays[/{id}]`. Locations `/admin/locations[/{id}[/agents]]`, `/admin/location-categories[/{id}]`. |
| F-270 | Settings `/admin/settings`, `/admin/settings/payments`. Design `/admin/booking-form-design`, `/admin/booking-form-design-reset`. Form fields `/admin/form-fields[/all|/{id}|/reorder|/reseed]`. |
| F-271 | Notifications `/admin/notifications/meta`, `/workflows[/{id}[/test|/actions]]`, `/actions/{id}[/test]`, `/smart-variables`. Audit `/admin/audit-logs[/meta|/clear|/export]`. Tools `/admin/tools/status|run/{action}|report|export-settings|import-settings`. Dashboard `/admin/dashboard`. |

## 25. admin-post and AJAX actions (legacy entry points)

| ID | Action |
|----|--------|
| F-280 | `admin_post_pointlybooking_admin_customers_export_csv`, `…_customers_import_csv` (used by the current UI). |
| F-281 | `admin_post_pointlybooking_admin_bookings_export_csv`, `…_bookings_export_pdf`, `…_booking_notes_save`, `…_booking_quick_update`, `…_customer_gdpr_delete`, `…_settings_save`, `…_settings_export_json`, `…_settings_import_json`, `…_tools_email_test`, `…_tools_webhook_test`, `…_tools_generate_demo`, `…_tools_export_settings`, `…_tools_import_settings`, `…_services_save`, `…_categories_save`, `…_extras_save`, `…_promo_codes_save`, `…_form_fields_save`, `…_agents_save`. |
| F-282 | `wp_ajax(_nopriv)_pointlybooking_slots`, `wp_ajax(_nopriv)_pointlybooking_submit_booking` (legacy form; nonce `pointlybooking_public`). |

## 26. Hooks, cron, query vars

| ID | Item |
|----|------|
| F-290 | Filter `pointlybooking_public_max_booking_days` (int, default 120). |
| F-291 | Filter `pointlybooking_license_http_args` (Pro). |
| F-292 | Cron action `pointlybooking_run_workflow_event( $workflow_id, $payload_json )`. |
| F-293 | Query vars `pointlybooking_manage_booking`, `key`, and `pointlybooking_action`. |
| F-294 | WooCommerce hooks: `woocommerce_checkout_create_order_line_item` and `woocommerce_payment_complete`. Function `pointlybooking_mark_booking_paid_and_confirmed()`. |
| F-295 | Global functions used by the Pro add-on or custom code (kept): `pointlybooking_request_*`, `pointlybooking_table()`, `pointlybooking_db_*`, `pointlybooking_render_template()`, `pointlybooking_run_workflows()`, `pointlybooking_shortcode_booking_form()`, `pointlybooking_insert_booking_from_payload()`, `pointlybooking_compute_authoritative_pricing()`, `pointlybooking_confirm_booking_paid()`. |

## 27. Database tables (prefix `{$wpdb->prefix}pointlybooking_`)

| ID | Table | Key columns |
|----|-------|-------------|
| F-300 | `services` | name, description, duration_minutes, price_cents, currency, is_active, category_id, image_id, sort_order, use_global_schedule, schedule_json, buffer_before_minutes, buffer_after_minutes, buffer_before, buffer_after, capacity, created_at, updated_at |
| F-301 | `categories` | name, description, image_id, sort_order, is_active |
| F-302 | `service_extras` | service_id (legacy), name, description, price, duration_min, image_id, sort_order, is_active |
| F-303 | `service_categories`, `extra_services`, `agent_services`, `service_agents` | pivot tables |
| F-304 | `agents` | first_name, last_name, email, phone, is_active, schedule_json, image_id |
| F-305 | `customers` | first_name, last_name, email, phone, wp_user_id, custom_fields_json |
| F-306 | `bookings` | service_id, customer_id, agent_id, category_id, start_datetime, end_datetime (site-local time), status, notes, manage_key (64 hex, unique), manage_token_last_used_at, extras_json, promo_code, discount_total, total_price, currency, payment_method, payment_status, payment_provider_ref, payment_amount, payment_currency, customer_fields_json, booking_fields_json, custom_fields_json |
| F-307 | `settings` | setting_key (unique), setting_value |
| F-308 | `form_fields` | field_key, label, type, scope, step_key, placeholder, options, is_required, is_enabled, show_in_wizard, sort_order, name_key, options_json, required, is_active |
| F-309 | `field_values` | entity_type, entity_id, field_id, field_key, scope, value_long |
| F-310 | `promo_codes` | code (unique), type, amount, starts_at, ends_at, max_uses, uses_count, min_total, is_active |
| F-311 | `holidays` | title, start_date, end_date, agent_id, is_recurring, is_recurring_yearly, is_enabled |
| F-312 | `schedules` | agent_id NULL=global, day_of_week 1–7, start_time, end_time, breaks_json, is_enabled |
| F-313 | `schedule_settings` | id=1, slot_interval_minutes, timezone |
| F-314 | `agent_working_hours`, `agent_breaks` | legacy per-agent hours and dated breaks |
| F-315 | `workflows`, `workflow_actions`, `workflow_logs` | notifications |
| F-316 | `audit_log` | event, actor_type, actor_wp_user_id, actor_ip, booking_id, customer_id, meta, created_at |
| F-317 | `locations`, `location_categories`, `location_agents` | locations |
| F-318 | `bundles`, `bundle_items` | created by migrations, no UI (kept) |

## 28. Options and transients

| ID | Item |
|----|------|
| F-320 | Options: `pointlybooking_settings`, `pointlybooking_booking_form_design`, `pointlybooking_db_version`, `pointlybooking_version`, `pointlybooking_caps_seeded`, `pointlybooking_remove_data_on_uninstall`, `pointlybooking_relations_migrated_1_4`, `pointlybooking_currency` (legacy fallback), `pointlybooking_stripe_publishable_key` / `pointlybooking_stripe_secret_key` (legacy overrides), and Pro license options `pointlybooking_license_*`. |
| F-321 | Transients: `pointlybooking_services_all_*`, `pointlybooking_agents_all_*`, `pointlybooking_service_agents_{id}`, `pointlybooking_front_slots_*`, `pointlybooking_front_avail_*`, `pointlybooking_av_*`, `pointlybooking_rl_*`, `pointlybooking_portal_*`. |
| F-322 | Uninstall: when the delete-data option is set, drop all plugin tables and delete the options. Otherwise only the flag is removed. |

## 29. Assets and localisation objects

| ID | Item |
|----|------|
| F-330 | `window.pointlybooking_FRONT` = {rest, restUrl, ajaxUrl, siteUrl, nonce, images, icons, imagesBuild, iconsBuild, tz, stripe_pk, currency, settings{…}}. |
| F-331 | `window.pointlybooking_ADMIN` = {restUrl, nonce, adminNonce, adminPostUrl, pluginUrl, publicImagesUrl, publicIconsUrl, route, page, build, timezone, currency, currency_position}. |
| F-332 | `window.pointlybooking_MANAGE` (manage page) = {restUrl, i18n}. |
| F-333 | Front CSS/JS load only on singular posts containing the shortcode (or when the shortcode/block renders). Optional `public/front-overrides.css` is loaded if present (site-level override hook). RTL stylesheets. |
