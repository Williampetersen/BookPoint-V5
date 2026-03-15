=== WPBookPoint Booking & Appointments ===
Contributors: wpbookpoint
Donate link: https://wpbookpoint.com/
Tags: booking, appointment booking, scheduling, calendar, service booking, booking system
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.0.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight appointment booking plugin for WordPress with services, calendar, and booking management.

== Description ==

WPBookPoint Booking & Appointments is a lightweight and modern appointment booking plugin for WordPress.

The free version provides core booking functionality suitable for service-based businesses that need a simple and reliable scheduling system.

Core features included in the free version:

* Services & Categories management
* Booking widget (block and shortcode support)
* Basic calendar & schedule configuration
* Customers and bookings management
* Lightweight and fast performance
* Mobile responsive booking interface

An optional Pro add-on (distributed separately) provides advanced features such as:

* Locations management
* Service extras
* Promo codes
* Holidays & time-off management
* Online payments integration

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory, or install via the WordPress Plugins screen.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Configure services and schedules in the WPBookPoint admin menu.
4. Add the booking widget using the provided block or shortcode.

== Frequently Asked Questions ==

= Does the free version require a license? =
No. The free version works fully without any license. Only advanced features are available in the optional Pro add-on.

= Can I upgrade to Pro later? =
Yes. You can install the Pro add-on plugin to unlock additional features without affecting existing bookings.

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

== Changelog ==

= 1.0.1 =
* Initial WordPress.org release.

== Upgrade Notice ==

= 1.0.1 =
Initial public release of WPBookPoint Booking & Appointments with core booking features.
