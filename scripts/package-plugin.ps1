$ErrorActionPreference = 'Stop'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$distDir = Join-Path $repoRoot 'dist'
$outZip = Join-Path $distDir 'wpbookpoint-booking-free.zip'
$stagingRoot = Join-Path $distDir ('.staging-' + [DateTime]::UtcNow.ToString('yyyyMMddHHmmssfff'))
$pluginDirName = 'pointly-booking'
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

# build/ holds the compiled apps; src/ + build configs are shipped so the minified
# code can be reviewed and rebuilt (WordPress.org guideline 4).
$includeDirs = @('includes', 'templates', 'build', 'languages', 'src')
$includeFiles = @(
  'LICENSE.txt',
  'package.json',
  'package-lock.json',
  'webpack.config.js',
  'babel.config.js',
  'uninstall.php',
  'readme.txt',
  'screenshot-1.png',
  'screenshot-2.png',
  'screenshot-3.png',
  'screenshot-4.png'
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

# The live WordPress.org plugin's main file is named pointly-booking.php (that
# exact slug/filename pair is what WordPress uses to track activation on every
# install). Our dev repo keeps the historical name bookpoint-v5.php, so the
# released package must rename it on the way out or every existing install
# would be silently deactivated by an update that changes its main file path.
$mainFileSrc = Join-Path $repoRoot 'bookpoint-v5.php'
$mainFileDst = Join-Path $stagingPluginDir 'pointly-booking.php'
if (Test-Path $mainFileSrc) {
  Copy-Item -Force $mainFileSrc $mainFileDst
} else {
  throw "Packaging failed: main plugin file not found at $mainFileSrc"
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

# Normalize line endings in text files to LF to avoid mixed-EOL warnings in Plugin Check.
$lfExtensions = @('*.php', '*.txt', '*.md', '*.json', '*.js', '*.css', '*.jsx', '*.ts', '*.tsx')
Get-ChildItem -Path $stagingPluginDir -Recurse -File -Include $lfExtensions | ForEach-Object {
  try {
    $text = [System.IO.File]::ReadAllText($_.FullName)
    $normalized = $text -replace "`r`n", "`n"
    if ($normalized -ne $text) {
      [System.IO.File]::WriteAllText($_.FullName, $normalized, (New-Object System.Text.UTF8Encoding($false)))
    }
  } catch {
    throw "Packaging failed while normalizing LF in: $($_.FullName) - $($_.Exception.Message)"
  }
}

# Free (WP.org) package: Pro licensing code lives in pro-addon/ and is never copied.
$excludePaths = @(
  (Join-Path $stagingPluginDir 'assets')
)
foreach ($p in $excludePaths) {
  if (Test-Path $p) {
    Remove-Item -Recurse -Force $p -ErrorAction SilentlyContinue
  }
}

if (Test-Path $outZip) { Remove-Item -Force $outZip }

New-ZipFromDirectory -SourceDir $stagingRoot -ZipPath $outZip

try { Remove-Item -Recurse -Force $stagingRoot } catch { }

$zipInfo = Get-Item $outZip
Write-Host ("Created: {0} ({1} MB)" -f $zipInfo.FullName, ([math]::Round($zipInfo.Length / 1MB, 2)))
