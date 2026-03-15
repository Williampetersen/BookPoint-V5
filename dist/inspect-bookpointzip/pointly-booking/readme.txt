=== BookPoint Booking & Appointments ===
Contributors: wpbookpoint
Donate link: https://wpbookpoint.com/
Tags: booking, appointment booking, scheduling, calendar, service booking
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.0.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight appointment booking plugin for WordPress with services, calendar, and booking management.

== Description ==

BookPoint Booking & Appointments is a lightweight and modern appointment booking plugin for WordPress.

This plugin is fully functional without any license key and includes:

* Services & Categories management
* Locations management
* Service extras
* Promo codes
* Holidays and time-off management
* Booking widget (block and shortcode support)
* Calendar, schedule, and availability configuration
* Customers and bookings management
* Online payments configuration (Cash, WooCommerce, Stripe, PayPal)
* Lightweight and fast performance
* Mobile responsive booking interface
* No locked or trial-only built-in features in this WordPress.org package

This WordPress.org package does not gate built-in functionality behind licenses, trials, quotas, or time limits. Any paid add-on functionality is distributed separately and is not included in this package.

== Source Code / Build ==

Generated asset files shipped in this plugin are built from human-readable source files included in the plugin package:

Source directories:

* `src/admin/` (admin React source)
* `src/front/` (front-end React source)
* `blocks/src/book-form/` (Gutenberg block source)

Generated files:

* `build/admin.js`, `build/index.jsx.css`, `build/index.jsx-rtl.css`
* `public/build/front.js`, `public/build/index.jsx.css`, `public/build/index.jsx-rtl.css`
* `public/front.js`, `public/index.jsx.css`, `public/index.jsx-rtl.css` (fallback copies used when `public/build/` is not present)
* `blocks/build/book-form/index.js`

Build commands:

1. `npm install`
2. `npm run build:admin`
3. `npm run build:front`
4. `npm run build:book-form`

Build tooling is declared in `package.json` and uses `@wordpress/scripts`.

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory, or install via the WordPress Plugins screen.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Configure services and schedules in the BookPoint admin menu.
4. Add the booking widget using the provided block or shortcode.

== Frequently Asked Questions ==

= Does the free version require a license? =
No. The plugin works fully without any license key.

= Is the plugin mobile friendly? =
Yes. The booking interface is fully responsive and works on all devices.

== Screenshots ==

1. Booking wizard interface
2. Services and categories management screen
3. Calendar and time slot selection
4. Bookings management dashboard

== External services ==

This plugin connects to third-party payment services when online payments are enabled by the site administrator.

Stripe
Used to create and confirm payment intents for card payments.
Data sent when a customer initiates payment: booking reference, order amount, currency, and required payment metadata.
Service provider: Stripe
Terms of service: https://stripe.com/legal
Privacy policy: https://stripe.com/privacy

PayPal
Used to create and capture PayPal payment orders.
Data sent when a customer initiates payment: booking reference, order amount, currency, and required payment metadata.
Service provider: PayPal
Terms of service: https://www.paypal.com/webapps/mpp/ua/legalhub-full
Privacy policy: https://www.paypal.com/webapps/mpp/ua/privacy-full

Webhooks (optional)
When enabled by the site administrator, the plugin can send booking event payloads to the webhook URLs configured in the plugin settings.
Data sent depends on the event (for example booking_id, status, service_id, customer_id, agent_id, and timestamps).

== Changelog ==

= 1.0.1 =
* Initial WordPress.org release.

== Upgrade Notice ==

= 1.0.1 =
Initial public release of BookPoint Booking & Appointments with core booking features.
