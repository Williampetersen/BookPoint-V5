# FTP Deployment (UnoEuro / generic)

This repo is a WordPress plugin. On a typical install, it lives here on the server:

`/public_html/wp-content/plugins/bookpoint-v5/`

## Recommended workflow

1. Build any assets you changed (examples):
   - `npm run build:admin`
   - `npm run build:front`
   - `npm run build:book-form`
2. Upload plugin files to the server (FTPES / explicit TLS):
   - Option A (script): `npm run deploy:plugin`
   - Option B (GUI): FileZilla / WinSCP â€œupload & overwriteâ€

## Script deploy (FTPES)

The deploy scripts read credentials from environment variables so you donâ€™t hardcode them in the repo:

- `POINTLYBOOKING_FTP_HOST` (example: `linux9.unoeuro.com`)
- `POINTLYBOOKING_FTP_USER` (example: `wpbookpoint.com`)
- `POINTLYBOOKING_FTP_PASS` (your FTP password)
- `POINTLYBOOKING_FTP_REMOTE_PLUGIN_DIR` (optional; default: `/public_html/wp-content/plugins/bookpoint-v5`)

PowerShell example:

```powershell
$env:POINTLYBOOKING_FTP_HOST="linux9.unoeuro.com"
$env:POINTLYBOOKING_FTP_USER="wpbookpoint.com"
$env:POINTLYBOOKING_FTP_PASS="(your password)"
# Optional:
# $env:POINTLYBOOKING_FTP_REMOTE_PLUGIN_DIR="/public_html/wp-content/plugins/bookpoint-v5"

npm run deploy:plugin
```

Dry run:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/deploy-plugin.ps1 -DryRun
```

## GUI deploy (FileZilla / WinSCP)

Connection:

- Host: `linux9.unoeuro.com`
- Port: `21`
- Encryption: â€œRequire explicit FTP over TLSâ€ (FTPES)
- Remote web root: `/public_html/`

Then upload your plugin folder into:

`/public_html/wp-content/plugins/`

## After upload

- WordPress Admin â†’ Plugins: ensure â€œBookPointâ€ is active
- Clear any cache plugin + hard refresh the browser (Ctrl+Shift+R)

