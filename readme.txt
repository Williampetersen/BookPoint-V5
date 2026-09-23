=== BookPoint Booking & Appointments ===
Contributors: wpbookpoint
Donate link: https://wpbookpoint.com/
Tags: booking, appointment booking, scheduling, calendar, service booking
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 2.6.23
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight appointment booking plugin for WordPress with services, calendar, and booking management.

== Description ==

BookPoint Booking & Appointments is a lightweight and modern appointment booking plugin for WordPress.

It helps you manage services, schedules, availability, customers, bookings, and payments through a clean booking workflow.

This plugin is fully functional without any license key and includes:

* Services and categories management
* Locations management
* Service extras
* Promo codes
* Holidays and time-off management
* Booking widget with block and shortcode support
* Calendar, schedule, and availability configuration
* Customers and bookings management
* Online payments configuration for Cash, WooCommerce, Stripe, and PayPal
* Lightweight and fast performance
* Mobile responsive booking interface
* No locked or trial-only built-in features in this WordPress.org package

This WordPress.org package does not gate built-in functionality behind licenses, trials, quotas, or time limits. Any paid add-on functionality is distributed separately and is not included in this package.

== Source Code / Build ==

Generated asset files shipped in this plugin are built from human-readable source files included in the plugin package.

Source directories:

* `src/admin/` - admin React source
* `src/front/` - front-end React source
* `blocks/src/book-form/` - Gutenberg block source

Generated files:

* `build/admin.js`
* `build/index.jsx.css`
* `build/index.jsx-rtl.css`
* `public/build/front.js`
* `public/build/index.jsx.css`
* `public/build/index.jsx-rtl.css`
* `public/front.js`
* `public/index.jsx.css`
* `public/index.jsx-rtl.css`
* `blocks/build/book-form/index.js`

The files in `public/` listed above include legacy compatibility copies generated from the same `src/front/` sources.

Generated files should not be edited manually. Edit the source files in `src/` or `blocks/src/` and rebuild.

Build commands:

1. `npm install`
2. `npm run build:admin`
3. `npm run build:front`
4. `npm run build:book-form`

Build tooling is declared in `package.json` and uses `@wordpress/scripts`.

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory, or install it through the WordPress Plugins screen.
2. Activate the plugin through the Plugins menu in WordPress.
3. Configure services, schedules, locations, and availability in the BookPoint admin menu.
4. Add the booking widget using the included block or shortcode.

== Frequently Asked Questions ==

= Does the free version require a license? =

No. The plugin works fully without any license key.

= Is the plugin mobile friendly? =

Yes. The booking interface is fully responsive and works on desktop, tablet, and mobile devices.

= Does this plugin support payments? =

Yes. The plugin includes payment configuration for Cash, WooCommerce, Stripe, and PayPal.

== Screenshots ==

1. Booking wizard interface
2. Services and categories management screen
3. Calendar and time slot selection
4. Bookings management dashboard

== External services ==

Some optional features in this plugin connect to external services for payments, license-related operations, and optional webhook delivery.

Stripe

What the service is: Stripe is a payment processing platform.

What it is used for: The plugin uses Stripe to create payment sessions, create payment intents, and confirm payment-related transactions when Stripe payments are enabled.

What data is sent: This may include booking reference data, order amount, currency, return or cancel URLs, and payment metadata required to process the transaction.

When data is sent: Data is sent only when a customer starts a Stripe payment flow or when the site requests payment confirmation or related payment processing actions.

Terms of service URL: https://stripe.com/legal
Privacy policy URL: https://stripe.com/privacy

PayPal

What the service is: PayPal is a payment processing platform.

What it is used for: The plugin uses PayPal to obtain API access tokens, create checkout orders, and capture approved payments when PayPal payments are enabled.

What data is sent: This may include booking reference data, order amount, currency, return or cancel URLs, and PayPal order data required to process the payment.

When data is sent: Data is sent only when a customer starts a PayPal checkout flow and when an approved PayPal order is captured or verified.

Terms of service URL: https://www.paypal.com/webapps/mpp/ua/legalhub-full
Privacy policy URL: https://www.paypal.com/webapps/mpp/ua/privacy-full

BookPoint licensing and activation service

What the service is: This is a vendor-operated licensing service provided through wpbookpoint.com.

What it is used for: The plugin uses this service for license-related operations such as validating, activating, deactivating, and checking the status of a license when those features are used.

What data is sent: This may include the license key, site URL or domain, plugin identifier, plugin version, instance or activation identifier, and related data required to process the licensing request.

When data is sent: Data is sent only when an administrator validates, activates, deactivates, or checks the status of a license, or when a license status check is triggered by the plugin.

Terms of service URL: https://wpbookpoint.com/terms-and-conditions/
Privacy policy URL: https://wpbookpoint.com/privacy-policy/

Optional administrator-configured webhook destination

What the service is: This is an optional external webhook endpoint configured by the site administrator. The destination is not pre-defined by the plugin.

What it is used for: The plugin can send booking event notifications to an external automation, CRM, or integration endpoint chosen by the site administrator.

What data is sent: This may include the event name, site URL, timestamp, and event payload such as booking ID, status, service ID, customer ID, agent ID, and related booking fields.

When data is sent: Data is sent only if the webhook feature is enabled and the site administrator has configured an external webhook URL for the relevant event.

Terms of service URL: The terms of service of the external provider chosen by the site administrator.
Privacy policy URL: The privacy policy of the external provider chosen by the site administrator.

== Changelog ==

= 2.6.23 =
* Fix: every translatable string in the plugin (44 PHP files plus the booking-form block) used a text domain that didn't match the plugin's actual WordPress.org slug, so none of it could ever be loaded through WordPress.org's translation system. All strings and the plugin header now use the correct text domain.
* Fix: the WordPress.org release package now ships its main file under the same name the live plugin has always used, so this and future updates apply cleanly to existing installs instead of registering as a second, unrelated plugin.

= 2.6.22 =
* Fix: the Stripe payment step's Back/Pay buttons used CSS classes that only exist in the admin stylesheet, so they rendered as unstyled browser buttons for customers paying by card.
* Fix: removed a leftover, unused duplicate payment-method-selection component that was never wired into the wizard.
* Fix: the date picker's availability indicator used hardcoded colors instead of the shared design tokens, so it didn't adapt in dark mode.
* Improvement: added keyboard focus trapping to the booking wizard modal so Tab/Shift+Tab no longer escapes to the page behind it, plus an accessible name for screen readers and ARIA state on the date/time picker's calendar days and time slots.

= 2.6.21 =
* New design system: a shared set of design tokens (colors, spacing, radius, shadows) now drives the admin dashboard and the front-end booking wizard, replacing two independently-drifting color schemes with one consistent look.
* New: real dark mode for the admin dashboard (toggle in the top bar) and the booking wizard (follows the "Dark mode default" setting in Booking Form Designer, or the visitor's system preference).
* Improvement: unified the two duplicate primary-button styles, deduplicated repeated input/card CSS rules.
* Improvement: replaced the booking wizard's step-dot indicator (which broke past 8 steps) with a progress bar that scales to any number of steps.
* Improvement: Booking Form Designer's live preview now matches the real widget's dark-mode and help-box behavior.

= 2.6.20 =
* Security: the public "manage booking" reschedule slot-lookup endpoint (`/manage/slots`) accepted an `exclude_booking_id` for any booking with no ownership check, which could leak whether a specific booking exists/its schedule. It now only honors that exclusion when the caller supplies the booking's own manage key.

= 2.6.19 =
* Security: server now computes booking totals from the actual service/extras/promo-code prices instead of trusting the amount sent by the browser, and payment confirmation (Stripe, PayPal) now verifies the payment actually belongs to the booking being marked paid.
* Security: narrowed several REST API permission checks (agent schedule, promo codes, tools, calendar bookings, field values) to the specific capability each endpoint actually needs, instead of accepting any of several unrelated capabilities.
* Security: booking-form shortcode/block output is now built through an explicit allow-list instead of returning raw markup.
* Fix: bookings using PayPal, WooCommerce, or Cash could get stuck with no way to complete when online payments were enabled; the wizard now routes each payment method correctly.
* Fix: the Gutenberg block's Service, Default Date, Hide Notes, Require Phone, and Compact Layout settings now actually apply to the booking form.
* Fix: booking form now loads its stylesheet correctly on RTL sites.
* Fix: uninstall cleanup now removes all plugin database tables when that option is enabled.
* Improvement: added a database-level lock around slot booking to prevent double-booking under concurrent requests.
* Improvement: admin sidebar now links to Schedule, Holidays, Promo Codes, Form Fields, Notifications, Audit Log, and Tools; added the ability to delete an Agent from the admin UI; Booking Form Designer's Fields Layout panel can add fields back after removing them.
* Fix: corrected garbled/mis-encoded text across the admin UI, booking widget, and block editor.
* Tested up to WordPress 7.1.

== Upgrade Notice ==

= 2.6.19 =
Includes booking payment security fixes; upgrade recommended for all sites accepting online payments.
