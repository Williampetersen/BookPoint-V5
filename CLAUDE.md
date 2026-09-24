# BookPoint (pointly-booking) — working notes

WordPress appointment booking plugin. Version 3.x is a rebuild: PHP services + REST API, React
admin and front ends. `FEATURES.md` is the feature contract (nothing in it may be removed),
`BUGS.md` the 2.x audit, `DECISIONS.md` the design/architecture decisions.

## Layout

| Path | What |
|------|------|
| `bookpoint-v5.php` | Main file (renamed to `pointly-booking.php` in the release ZIP). Constants, autoloader, boot. |
| `includes/` | PHP, namespace `PointlyBooking\` (PSR-4, own autoloader in `includes/Autoloader.php`). |
| `includes/Database/` | `Tables` (names), `Schema` (dbDelta), `Migrator` (versioned upgrades, `DB_VERSION`). |
| `includes/Repositories/` | Static data access per table. All SQL is `$wpdb->prepare()` with `%i` for identifiers. |
| `includes/Services/` | Business logic: `Availability/`, `Booking/`, `Pricing/`, `Payments/`, `Notifications/`, `Portal`, `Transfer`, `Webhooks`, `Audit`. |
| `includes/Rest/` | `Controller` base, `Presenter` (response shapes), `Routes` registry, `Admin/*` and `Front/*` controllers. |
| `includes/Admin/` | Menu (one React app, 2.x slug redirects), Assets, admin-post Actions, plugin links. |
| `includes/Frontend/` | Asset loading, shortcodes, block, manage page, legacy admin-ajax. |
| `includes/Compat/` | 2.x global functions and classes kept for add-ons/site code. |
| `templates/` | PHP templates (emails, print report, manage page shell). |
| `src/` | React sources: `admin/`, `front/` (wizard), `manage/` (manage page + portal), `blocks/booking-form/`, `shared/` (API client, formatting). |
| `build/` | Compiled bundles (**committed**). `build/<entry>/index.js|css|asset.php`. |
| `pro-addon/` | Separate Pro add-on (license/updates); never part of the WordPress.org ZIP. |
| `.dev/` | Docker dev stack, REST smoke test, PHPCS tools (vendor is git-ignored). |

## Commands

```bash
npm install                 # once
npm run build               # build all bundles into build/ (commit the result)
npm run start               # watch mode
npm run lint:js && npm run lint:css

# Dev site (http://localhost:8088, admin/admin)
docker compose -f .dev/docker-compose.yml up -d
docker compose -f .dev/docker-compose.yml exec -T cli wp <command>
docker compose -f .dev/docker-compose.yml exec -T cli wp eval-file wp-content/plugins/pointly-booking/.dev/smoke-rest.php

# PHP lint / coding standards (Git Bash needs MSYS_NO_PATHCONV=1)
MSYS_NO_PATHCONV=1 docker run --rm -v "$PWD":/app -w /app php:7.4-cli php -l includes/Plugin.php
MSYS_NO_PATHCONV=1 docker run --rm -v "$PWD/.dev/tools":/app -w /app composer:2 install   # once
MSYS_NO_PATHCONV=1 docker run --rm -v "$PWD":/app -w /app php:8.3-cli php .dev/tools/vendor/bin/phpcs --standard=phpcs.xml.dist
MSYS_NO_PATHCONV=1 docker run --rm -v "$PWD":/app -w /app php:8.3-cli php .dev/tools/vendor/bin/phpcbf --standard=phpcs.xml.dist

npm run package             # release ZIP in dist/ (PowerShell)
```

## Conventions

- PHP 7.4+ syntax only, WordPress 6.2+. WordPress-Extra + WordPress-Docs + PHPCompatibilityWP must pass (`phpcs.xml.dist`).
- Prefix everything: functions/options/hooks `pointlybooking_`, classes in `PointlyBooking\`, CSS classes `pbk-`, CSS custom properties `--pbk-`.
- Text domain `pointly-booking` everywhere (PHP `__()`, JS `@wordpress/i18n`). User-facing text is plain and friendly.
- REST: namespace `pointly-booking/v1`; every route has a `permission_callback` (`Controller::cap()` for admin, `Controller::public_access` + key/session checks for public). Success envelope `{ status: 'success', data }`; errors are `WP_Error` with `status` and optional `field`.
- Sanitise on input (`Support\Sanitize`), escape late in templates. Nonces for admin-post; REST uses the `wp_rest` nonce.
- Never drop tables/columns or rename options, hooks or shortcodes: add a migration step and keep a compat path. Record trade-offs in `DECISIONS.md`.
- Caching: `Support\Cache` with groups `catalog`, `availability`, `settings`, `dashboard`; call `Cache::bump( group )` after writes.
- JSX uses the classic runtime (see `babel.config.js`) so bundles work on WordPress 6.2+. Import React APIs from `@wordpress/element`.
- Front CSS is scoped under `.pbk-root`; use logical properties (margin-inline, inset-inline) for RTL.
- Times are site-local `Y-m-d H:i:s` strings; never convert them to the visitor's time zone in JS (`src/shared/format.js`).
- Git: commit per phase/feature; message ends with the `Co-Authored-By` line.
