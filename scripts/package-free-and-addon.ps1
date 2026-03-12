$ErrorActionPreference = 'Stop'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$distDir = Join-Path $repoRoot 'dist'

New-Item -ItemType Directory -Force $distDir | Out-Null

# Keep only these two release artifacts.
$keep = @('wpbookpoint-booking-free.zip', 'bookpoint-pro-addon.zip')

# Remove any existing zip files first so dist ends with exactly 2 zips.
Get-ChildItem -Path $distDir -Filter *.zip -File -ErrorAction SilentlyContinue | ForEach-Object {
  if ($keep -notcontains $_.Name) {
    Remove-Item -Force $_.FullName
  }
}

& (Join-Path $PSScriptRoot 'package-plugin.ps1')
& (Join-Path $PSScriptRoot 'package-pro-addon.ps1')

# Remove any zip that isn't one of the two expected names (safety net).
Get-ChildItem -Path $distDir -Filter *.zip -File -ErrorAction SilentlyContinue | ForEach-Object {
  if ($keep -notcontains $_.Name) {
    Remove-Item -Force $_.FullName
  }
}

$zips = Get-ChildItem -Path $distDir -Filter *.zip -File | Sort-Object Name
Write-Host "Release ZIPs:"
$zips | ForEach-Object { Write-Host (" - {0} ({1} KB)" -f $_.Name, [math]::Round($_.Length / 1KB)) }
