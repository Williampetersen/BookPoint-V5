param(
  [string]$FtpHost = $env:BP_FTP_HOST,
  [int]$FtpPort = 21,
  [string]$FtpUser = $env:BP_FTP_USER,
  [string]$FtpPassword = $env:BP_FTP_PASS,
  [string]$RemotePluginDir = $env:BP_FTP_REMOTE_PLUGIN_DIR,
  [switch]$DryRun,
  [switch]$Fast
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

$basePath = (Get-Location).Path.TrimEnd("\")

function Get-RelPath([string]$fullPath) {
  $rel = $fullPath.Substring($basePath.Length).TrimStart("\", "/")
  return $rel
}

$rootFiles = @(
  "bookpoint-v5.php",
  "bookpoint-pro.php",
  "readme.txt",
  "uninstall.php"
)

$rootDirs = @(
  "build",
  "lib",
  "public",
  "languages",
  "blocks/build"
)

$filesToUpload = New-Object System.Collections.Generic.List[string]

  if ($Fast) {
  $fastFiles = @(
    "bookpoint-v5.php",
    "bookpoint-pro.php",
    "readme.txt",
    "uninstall.php",
    "build/admin.js",
    "build/admin.asset.php",
    "build/index.jsx.css",
    "build/index.jsx-rtl.css",
    "public/build/front.js",
    "public/build/front.asset.php",
    "public/build/index.jsx.css",
    "public/build/index.jsx-rtl.css",
    "lib/rest/admin-booking-form-design-routes.php",
    "lib/rest/front-booking-form-design-routes.php",
    "public/admin-ui.css",
    "public/admin-app.css"
  )

  foreach ($f in $fastFiles) {
    $p = Join-Path $basePath $f
    if (Test-Path $p) { $filesToUpload.Add($p) }
  }

  # Always include icons in FAST mode (common hotfix/update).
  $iconsDir = Join-Path $basePath "public/icons"
  if (Test-Path $iconsDir) {
    Get-ChildItem -Path $iconsDir -Filter *.svg -File | ForEach-Object { $filesToUpload.Add($_.FullName) }
  }

  # Also include public images in FAST mode (logos/payment images, etc.).
  $imagesDir = Join-Path $basePath "public/images"
  if (Test-Path $imagesDir) {
    Get-ChildItem -Path $imagesDir -Recurse -File | ForEach-Object { $filesToUpload.Add($_.FullName) }
  }
} else {
  foreach ($f in $rootFiles) {
    $p = Join-Path $basePath $f
    if (Test-Path $p) { $filesToUpload.Add($p) }
  }

  foreach ($d in $rootDirs) {
    $p = Join-Path $basePath $d
    if (!(Test-Path $p)) { continue }
    Get-ChildItem -Path $p -Recurse -File | ForEach-Object { $filesToUpload.Add($_.FullName) }
  }
}

$filesToUpload = $filesToUpload | Sort-Object
if ($filesToUpload.Count -eq 0) { throw "No files found to upload." }

Write-Host ("Files to upload: {0}" -f $filesToUpload.Count)
Write-Host ("Remote base: {0}" -f $remoteBase)

foreach ($full in $filesToUpload) {
  $rel = Get-RelPath $full
  $url = ($remoteBase + "/" + ($rel -replace "\\", "/"))

  if ($DryRun) {
    Write-Host "DRYRUN  $rel -> $url"
    continue
  }

  Write-Host "Upload  $rel"
  curl.exe -sS --ssl-reqd --ftp-create-dirs --user "${FtpUser}:${FtpPassword}" -T "$full" "$url" | Out-Null
  if ($LASTEXITCODE -ne 0) { throw "Upload failed: $rel" }
}

Write-Host "Deploy complete."
