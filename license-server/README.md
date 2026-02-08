# BookPoint License Server (WooCommerce)

This plugin runs on **your WooCommerce store site** (your own domain). It generates license keys on purchase, emails customers, stores activation + expiry, and exposes REST endpoints used by the BookPoint plugin:

- `POST /wp-json/bookpoint/v1/validate`
- `POST /wp-json/bookpoint/v1/deactivate`
- `GET /wp-json/bookpoint/v1/updates`

## Install

1. Build the ZIP from this repo: run `scripts/package-license-server.ps1`
2. In WP Admin (your store site): **Plugins → Add New → Upload Plugin** → upload `dist/bookpoint-license-server.zip`
3. Activate **BookPoint License Server**

On activation, the plugin creates a DB table: `wp_bp_licenses` (prefix depends on your WP install).

## Sell a licensed product

Edit a WooCommerce product and enable:

- **Generate BookPoint license**
- (Optional) **BookPoint plan name**
- **License duration (days)** (0 = never expires)
- **Activation limit** (recommended: 1)

When an order reaches **Processing** or **Completed**, keys are generated and emailed to the billing email.
The keys are also included in standard WooCommerce customer emails for that order.

## Admin: manage licenses

WP Admin → **BookPoint Licenses**

- Search by license key, email, or activated domain
- Disable/Enable a key
- Reset Activation (clears bound domain + instance)
- Create a license manually (useful for marketplace sales)

## Customer: view licenses

WooCommerce My Account → **BookPoint Licenses**

Shows license key, status, expiry, activated domain, and activations.

WooCommerce My Account dashboard also shows the most recent keys.

## Updates (optional)

WP Admin → **BookPoint Licenses → Updates**

Configure:

- Latest version
- Package URL (direct ZIP download)

The BookPoint plugin will only receive the update package when the license is valid for the requesting site.
