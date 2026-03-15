$ErrorActionPreference = 'Stop'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$distDir = Join-Path $repoRoot 'dist'
$outZip = Join-Path $distDir 'bookpoint-v5-pro.zip'
$stagingRoot = Join-Path $distDir ('.staging-pro-' + [DateTime]::UtcNow.ToString('yyyyMMddHHmmssfff'))
$pluginDirName = 'bookpoint-v5'
$stagingPluginDir = Join-Path $stagingRoot $pluginDirName

New-Item -ItemType Directory -Force $distDir | Out-Null
New-Item -ItemType Directory -Force $stagingPluginDir | Out-Null

Add-Type -AssemblyName System.IO.Compression.FileSystem
Add-Type -AssemblyName System.IO.Compression

function New-ZipFromDirectory([string]$SourceDir, [string]$ZipPath) {
  if (Test-Path $ZipPath) { Remove-Item -Force $ZipPath }

  $source = (Resolve-Path $SourceDir).Path.TrimEnd('\', '/')
  $fs = [System.IO.File]::Open($ZipPath, [System.IO.FileMode]::CreateNew)
  $zip = New-Object System.IO.Compression.ZipArchive($fs, [System.IO.Compression.ZipArchiveMode]::Create, $false)

  try {
    Get-ChildItem -Path $source -Recurse -File | ForEach-Object {
      $full = $_.FullName
      $rel = $full.Substring($source.Length).TrimStart('\', '/')
      $rel = $rel -replace '\\', '/'

      $entry = $zip.CreateEntry($rel, [System.IO.Compression.CompressionLevel]::Optimal)
      $entry.LastWriteTime = $_.LastWriteTime

      $in = [System.IO.File]::OpenRead($full)
      $out = $entry.Open()
      try { $in.CopyTo($out) } finally { $out.Dispose(); $in.Dispose() }
    }
  } finally {
    $zip.Dispose()
    $fs.Dispose()
  }
}

$includeDirs = @('lib', 'public', 'build', 'blocks')
$includeFiles = @(
  'bookpoint-v5.php',
  'bookpoint-pro.php',
  'uninstall.php',
  'readme.txt'
)

foreach ($dir in $includeDirs) {
  $src = Join-Path $repoRoot $dir
  if (Test-Path $src) {
    Copy-Item -Recurse -Force $src (Join-Path $stagingPluginDir $dir)
  }
}

foreach ($file in $includeFiles) {
  $src = Join-Path $repoRoot $file
  if (Test-Path $src) {
    Copy-Item -Force $src (Join-Path $stagingPluginDir $file)
  }
}

# Pro package: adjust plugin header so it is clearly distinguishable in WP Admin.
$mainPluginFile = Join-Path $stagingPluginDir 'bookpoint-v5.php'
if (Test-Path $mainPluginFile) {
  $content = Get-Content -Raw -Encoding UTF8 $mainPluginFile
  $content = $content -replace '(?m)^\s*\*\s*Plugin Name:\s*BookPoint\s*$', ' * Plugin Name: BookPoint Pro'
  $content = $content -replace '(?m)^\s*\*\s*Description:\s*Appointment booking system \(with optional Pro add-on\)\.\s*$', ' * Description: Professional appointment booking system (Pro version).'
  # Write without BOM to avoid "headers already sent" during activation.
  [System.IO.File]::WriteAllText($mainPluginFile, $content, (New-Object System.Text.UTF8Encoding($false)))
}

# Strip UTF-8 BOM from PHP files to prevent "headers already sent" during activation.
Get-ChildItem -Path $stagingPluginDir -Recurse -File -Filter *.php | ForEach-Object {
  try {
    $bytes = [System.IO.File]::ReadAllBytes($_.FullName)
    if ($bytes.Length -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF) {
      $newBytes = New-Object byte[] ($bytes.Length - 3)
      [System.Array]::Copy($bytes, 3, $newBytes, 0, $bytes.Length - 3)
      [System.IO.File]::WriteAllBytes($_.FullName, $newBytes)
    }
  } catch {
    throw "Packaging failed while stripping BOM from: $($_.FullName) - $($_.Exception.Message)"
  }
}

# Sanity checks: ensure required static assets are included in releases.
$requiredPaths = @(
  (Join-Path $stagingPluginDir 'build/admin.js'),
  (Join-Path $stagingPluginDir 'build/admin.asset.php'),
  (Join-Path $stagingPluginDir 'public/icons/menu.svg'),
  (Join-Path $stagingPluginDir 'public/images/service-image.png'),
  (Join-Path $stagingPluginDir 'public/images/intl-tel-input/flags.png')
)

foreach ($p in $requiredPaths) {
  if (!(Test-Path $p)) {
    throw "Packaging failed: missing required file in staging: $p"
  }
}

if (Test-Path $outZip) { Remove-Item -Force $outZip }

New-ZipFromDirectory -SourceDir $stagingRoot -ZipPath $outZip

try { Remove-Item -Recurse -Force $stagingRoot } catch { }

$zipInfo = Get-Item $outZip
Write-Host ("Created: {0} ({1} MB)" -f $zipInfo.FullName, ([math]::Round($zipInfo.Length / 1MB, 2)))
