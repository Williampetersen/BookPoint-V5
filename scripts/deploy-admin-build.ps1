param(
  [string]$FtpHost = $env:BP_FTP_HOST,
  [int]$FtpPort = 21,
  [string]$FtpUser = $env:BP_FTP_USER,
  [string]$FtpPassword = $env:BP_FTP_PASS,
  [string]$RemotePluginDir = $env:BP_FTP_REMOTE_PLUGIN_DIR
)

$ErrorActionPreference = "Stop"

function Require-Val([string]$name, [string]$val) {
  if ([string]::IsNullOrWhiteSpace($val)) {
    throw "Missing required value: $name"
  }
}

Require-Val "BP_FTP_HOST / -FtpHost" $FtpHost
Require-Val "BP_FTP_USER / -FtpUser" $FtpUser
Require-Val "BP_FTP_PASS / -FtpPassword" $FtpPassword

$defaultRemote = "/public_html/wp-content/plugins/bookpoint-v5"
if ([string]::IsNullOrWhiteSpace($RemotePluginDir)) { $RemotePluginDir = $defaultRemote }

$RemotePluginDir = $RemotePluginDir.TrimEnd("/")
$hostPart = $FtpHost
if ($FtpPort -and $FtpPort -ne 21) { $hostPart = "$FtpHost`:$FtpPort" }
$remoteBase = "ftp://$hostPart$RemotePluginDir"

$files = @(
  "build/admin.js",
  "build/admin.asset.php",
  "build/index.jsx.css",
  "build/index.jsx-rtl.css",
  "lib/rest/admin-misc-routes.php",
  "lib/models/audit_model.php",
  "lib/rest/settings-routes.php",
  "lib/helpers/license_helper.php",
  "lib/rest/admin-notifications-routes.php",
  "lib/helpers/notifications_helper.php"
)

foreach ($f in $files) {
  $local = Join-Path (Get-Location) $f
  if (!(Test-Path $local)) { throw "Missing local file: $f" }
  $url = ($remoteBase + "/" + ($f -replace "\\\\", "/"))

  Write-Host "Uploading $f -> $url"
  # Use explicit TLS (AUTH SSL) on port 21 via ftp:// + --ssl-reqd.
  curl.exe -sS --ssl-reqd --user "${FtpUser}:${FtpPassword}" -T "$local" "$url" | Out-Null
  if ($LASTEXITCODE -ne 0) { throw "Upload failed: $f" }
}

Write-Host "Deploy complete."
