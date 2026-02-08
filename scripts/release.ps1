param(
  [string]$Slug = "bookpoint-pro",
  [string]$Version = "",
  [switch]$BuildAdmin,
  [switch]$BuildBlocks
)

$ErrorActionPreference = "Stop"

function Get-PluginVersion {
  param([string]$MainFile)
  if (!(Test-Path $MainFile)) { throw "Main plugin file not found: $MainFile" }
  $content = Get-Content -Raw $MainFile
  $m = [regex]::Match($content, 'const\s+VERSION\s*=\s*''([^'']+)''')
  if ($m.Success) { return $m.Groups[1].Value }
  $m2 = [regex]::Match($content, 'Version:\s*([0-9]+\.[0-9]+\.[0-9]+)')
  if ($m2.Success) { return $m2.Groups[1].Value }
  throw "Could not detect plugin version from $MainFile"
}

$root = (Resolve-Path ".").Path
$main = Join-Path $root "bookpoint-v5.php"

if ($Version -eq "") {
  $Version = Get-PluginVersion -MainFile $main
}

if ($BuildAdmin) {
  if (!(Test-Path (Join-Path $root "package.json"))) { throw "package.json not found; can't build admin." }
  npm run build:admin | Out-Host
}

if ($BuildBlocks) {
  if (!(Test-Path (Join-Path $root "package.json"))) { throw "package.json not found; can't build blocks." }
  npm run build:blocks | Out-Host
}

$dist = Join-Path $root "dist"
New-Item -ItemType Directory -Force -Path $dist | Out-Null

$stage = Join-Path $dist "_stage"
if (Test-Path $stage) { Remove-Item -Recurse -Force $stage }
New-Item -ItemType Directory -Force -Path $stage | Out-Null

$pluginDir = Join-Path $stage $Slug
New-Item -ItemType Directory -Force -Path $pluginDir | Out-Null

# Runtime files/folders to ship (dev-only folders are intentionally excluded).
$include = @(
  "bookpoint-v5.php",
  "uninstall.php",
  "lib",
  "public",
  "build",
  (Join-Path "blocks" "build")
)

foreach ($rel in $include) {
  $src = Join-Path $root $rel
  if (!(Test-Path $src)) {
    throw "Missing required path for release: $rel"
  }

  if ((Get-Item $src).PSIsContainer) {
    Copy-Item -Recurse -Force $src $pluginDir
  } else {
    Copy-Item -Force $src $pluginDir
  }
}

$zip = Join-Path $dist ("{0}-{1}.zip" -f $Slug, $Version)
if (Test-Path $zip) { Remove-Item -Force $zip }

for ($i = 1; $i -le 8; $i++) {
  try {
    Compress-Archive -Path $pluginDir -DestinationPath $zip -Force
    break
  } catch {
    if ($i -eq 8) { throw }
    Start-Sleep -Milliseconds (250 * $i)
  }
}

Write-Host "Created: $zip"
