# BookPoint V5 (Pro) + License Server + WordPress.org free plugin

This repo builds **three things** from one shared codebase:

- **BookPoint Pro** (installed on the customer's WordPress site)
- **BookPoint License Server** (installed on *your* WooCommerce store site)
- **BookPoint Booking & Appointments** — the free version listed on WordPress.org
  under slug `pointly-booking` (see section 3 below)

The License Server generates license keys when a WooCommerce product is purchased, emails the customer, stores license status/expiry/activation, and exposes REST endpoints that BookPoint Pro calls to **activate/validate** and to **protect updates**.

## Getting started on a new machine

```
git clone https://github.com/Williampetersen/BookPoint-V5.git
cd BookPoint-V5
npm ci
npm run build:admin
npm run build:front
npm run build:book-form
```

`node_modules/`, `dist/`, and `release/` are gitignored and regenerated locally —
they are not part of the clone. To publish to WordPress.org from this machine, you
still need your own local SVN checkout (see "Publishing an update to WordPress.org"
below) — that's separate from this git repo and isn't something `git clone` provides.

## Components

### 1) License Server (WooCommerce store site)

- Code: `license-server/bookpoint-license-server.php`
- REST endpoints:
  - `POST /wp-json/bookpoint/v1/validate`
  - `POST /wp-json/bookpoint/v1/deactivate`
  - `GET /wp-json/bookpoint/v1/updates`
  - `GET /wp-json/bookpoint/v1/download` (redirects to ZIP if license is valid)
- Data: creates a table named `wp_pointlybooking_licenses` (your WP table prefix may differ)
- Admin UI: WP Admin → **BookPoint Licenses**
- Customer UI: WooCommerce **My Account → BookPoint Licenses**

### 2) BookPoint Pro (customer site)

- Entry: `bookpoint-v5.php`
- License client: `lib/helpers/license_helper.php`
- Updates client: `lib/helpers/updates_helper.php`
- Pro gating (blocks Pro-only features when unlicensed): `lib/helpers/license_gate_helper.php`

Important: PHP plugins are never 100% "uncrackable", but this system reliably blocks Pro features + updates when the license is not valid.

## How activation works

- The **license key is generated on purchase** on your WooCommerce site.
- The license becomes **bound to the customer's website domain** when they validate it the first time from inside BookPoint Pro.
- Each license is intended to be active on **one website** (activation limit = 1).

"Auto-activating" a license on the customer's separate site without them entering the key generally requires a secure connect flow (OAuth/token exchange). This repo uses the standard approach: customer pastes the key into the plugin on their site.

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

### B) On the customer's WordPress site

1. Install **BookPoint Pro**.
2. Go to BookPoint → Settings → License:
   - Set **License server URL** to your store domain (the site running the license server plugin).
   - Paste the license key and **Activate/Validate**.

## Packaging

- License server ZIP: `scripts/package-license-server.ps1` → `dist/bookpoint-license-server.zip`
- Pro ZIP: `scripts/package-plugin-pro.ps1` → `dist/bookpoint-v5-pro.zip`
- Free WordPress.org ZIP: `scripts/package-plugin.ps1` → `dist/wpbookpoint-booking-free.zip` (see below)

## 3) Free plugin on WordPress.org (pointly-booking)

There is a third distribution: the free version of this plugin listed on WordPress.org
under the slug **pointly-booking** ("BookPoint Booking & Appointments"). It's built
from the same `bookpoint-v5.php` source as the Pro plugin, minus the Pro-only files.

- Built by `scripts/package-plugin.ps1`, which copies `lib/`, `public/`, `build/`,
  `blocks/`, `languages/`, `src/`, plus a few root files into a `pointly-booking/`
  folder, **renames the main file to `pointly-booking.php`**, and strips
  `lib/helpers/license_helper.php`, `lib/helpers/license_gate_helper.php`,
  `lib/helpers/updates_helper.php`, and `assets/`.
- The main-file rename matters: WordPress tracks plugin activation by the exact
  `slug/file.php` path. The live plugin has always shipped as
  `pointly-booking/pointly-booking.php`. If a release ever ships with the file still
  named `bookpoint-v5.php`, every existing install would see it as a second, unrelated
  plugin instead of an update, and the real one would go stale. Don't remove that
  rename step from the packaging script.
- Text domain must be `pointly-booking` everywhere (the plugin header and every
  `__()`/`_e()` call) — it has to match the WordPress.org slug or none of the strings
  can ever be delivered as a translation. This was broken (`bookpoint-v5` domain) and
  fixed in v2.6.23; don't reintroduce the old domain string.
- The WordPress.org listing's screenshots (`screenshot-1.png`..`screenshot-4.png`, at
  the repo root) are included in the free ZIP. They currently show the pre-redesign
  UI (before the 2.6.21 dark-mode/design-system update) — worth refreshing next time
  you're in WP Admin, not urgent.

### Publishing an update to WordPress.org

WordPress.org plugins are distributed over **SVN**, not GitHub — pushing to GitHub
does nothing for existing installs. You need a local SVN working copy checked out
from `https://plugins.svn.wordpress.org/pointly-booking/` (via TortoiseSVN on
Windows, using your WordPress.org account credentials).

On a machine that doesn't have that checkout yet:

1. Right-click a folder in Explorer → **SVN Checkout** → URL
   `https://plugins.svn.wordpress.org/pointly-booking/` → choose a local folder
   (e.g. `C:\pointly-booking-svn`). This pulls `trunk/`, `tags/`, and `assets/`.
2. In this repo, build the release: `npm run build:admin && npm run build:front &&
   npm run build:book-form`, then `powershell -File scripts/package-plugin.ps1`.
   This produces `dist/wpbookpoint-booking-free.zip`.
3. Extract that zip's `pointly-booking/` folder contents **over** the SVN checkout's
   `trunk/` folder (replacing its contents, keeping the `.svn` metadata folder at the
   working-copy root untouched — modern SVN keeps that at the checkout root only, not
   per-subfolder, so it's safe to freely replace files inside `trunk/`).
4. Right-click `trunk/` → **TortoiseSVN → Commit**. Review the change list — new/
   modified/deleted files should make sense (mostly modified + newly-dead files
   showing as "! missing", which you check to include as deletions). Commit.
5. Tag the release: right-click → **TortoiseSVN → Branch/Tag** → "To path"
   `^/tags/<version>` (matching the `Stable tag` in `readme.txt`), from HEAD revision.
   WordPress.org's directory page and auto-updates read from the tag, not trunk.
6. Bump `Stable tag` in `readme.txt` and `Version:`/`const VERSION` in
   `bookpoint-v5.php` **before** step 2, so the packaged zip and the tag agree.

## Troubleshooting

If keys are not generating:

- Confirm the product has **Generate BookPoint license** enabled.
- Confirm the order status reaches **Processing** or **Completed**.
- WP Admin → **BookPoint Licenses** → check the **Debug log** (shows: table missing, product not enabled, DB insert error).

If keys do not show in My Account:

- Update the License Server plugin to the latest version and refresh the account page.
- Make sure `/my-account/*` is **not cached** by any cache plugin, host cache, or CDN.

