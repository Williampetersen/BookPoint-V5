# BookPoint V5 (Pro) + License Server

This repo contains **two WordPress plugins**:

- **BookPoint Pro** (installed on the customer’s WordPress site)
- **BookPoint License Server** (installed on *your* WooCommerce store site)

The License Server generates license keys when a WooCommerce product is purchased, emails the customer, stores license status/expiry/activation, and exposes REST endpoints that BookPoint Pro calls to **activate/validate** and to **protect updates**.

## Components

### 1) License Server (WooCommerce store site)

- Code: `license-server/bookpoint-license-server.php`
- REST endpoints:
  - `POST /wp-json/bookpoint/v1/validate`
  - `POST /wp-json/bookpoint/v1/deactivate`
  - `GET /wp-json/bookpoint/v1/updates`
  - `GET /wp-json/bookpoint/v1/download` (redirects to ZIP if license is valid)
- Data: creates a table named `wp_bp_licenses` (your WP table prefix may differ)
- Admin UI: WP Admin → **BookPoint Licenses**
- Customer UI: WooCommerce **My Account → BookPoint Licenses**

### 2) BookPoint Pro (customer site)

- Entry: `bookpoint-v5.php`
- License client: `lib/helpers/license_helper.php`
- Updates client: `lib/helpers/updates_helper.php`
- Pro gating (blocks Pro-only features when unlicensed): `lib/helpers/license_gate_helper.php`

Important: PHP plugins are never 100% “uncrackable”, but this system reliably blocks Pro features + updates when the license is not valid.

## How activation works

- The **license key is generated on purchase** on your WooCommerce site.
- The license becomes **bound to the customer’s website domain** when they validate it the first time from inside BookPoint Pro.
- Each license is intended to be active on **one website** (activation limit = 1).

“Auto-activating” a license on the customer’s separate site without them entering the key generally requires a secure connect flow (OAuth/token exchange). This repo uses the standard approach: customer pastes the key into the plugin on their site.

## Setup

### A) On your WooCommerce store site

1. Build + install the license server plugin:
   - Run `scripts/package-license-server.ps1`
   - Upload `dist/bookpoint-license-server.zip` in WP Admin → Plugins → Add New → Upload Plugin
2. Edit your WooCommerce product and enable:
   - **Generate BookPoint license**
   - (Optional) **BookPoint plan name**
   - **License duration (days)** (`0` = never expires)
   - **Activation limit** (recommended: `1`)
3. Place a test order:
   - When an order reaches **Processing** or **Completed**, the key is generated.
   - The customer receives the key by email and can view it in **My Account → BookPoint Licenses**.

### B) On the customer’s WordPress site

1. Install **BookPoint Pro**.
2. Go to BookPoint → Settings → License:
   - Set **License server URL** to your store domain (the site running the license server plugin).
   - Paste the license key and **Activate/Validate**.

## Packaging

- License server ZIP: `scripts/package-license-server.ps1` → `dist/bookpoint-license-server.zip`
- Pro ZIP: `scripts/package-plugin-pro.ps1` → `dist/bookpoint-v5-pro.zip`

## Troubleshooting

If keys are not generating:

- Confirm the product has **Generate BookPoint license** enabled.
- Confirm the order status reaches **Processing** or **Completed**.
- WP Admin → **BookPoint Licenses** → check the **Debug log** (shows: table missing, product not enabled, DB insert error).

If keys do not show in My Account:

- Update the License Server plugin to the latest version and refresh the account page.
- Make sure `/my-account/*` is **not cached** by any cache plugin, host cache, or CDN.

