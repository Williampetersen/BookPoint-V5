$ErrorActionPreference = 'Stop'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$distDir = Join-Path $repoRoot 'dist'
$outZip = Join-Path $distDir 'bookpoint-pro-addon.zip'
$stagingRoot = Join-Path $distDir ('.staging-pro-addon-' + [DateTime]::UtcNow.ToString('yyyyMMddHHmmssfff'))
$pluginDirName = 'bookpoint-pro-addon'
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

# Main plugin file
$srcMain = Join-Path $repoRoot 'pro-addon/bookpoint-pro-addon.php'
Copy-Item -Force $srcMain (Join-Path $stagingPluginDir 'bookpoint-pro-addon.php')

# Include Pro-only helper code (kept in the main repo under lib/helpers).
$incDir = Join-Path $stagingPluginDir 'includes'
New-Item -ItemType Directory -Force $incDir | Out-Null

$includeHelpers = @(
  'lib/helpers/license_helper.php',
  'lib/helpers/license_gate_helper.php',
  'lib/helpers/updates_helper.php'
)

foreach ($rel in $includeHelpers) {
  $src = Join-Path $repoRoot $rel
  if (!(Test-Path $src)) {
    throw "Packaging failed: missing required helper: $src"
  }
  Copy-Item -Force $src (Join-Path $incDir ([IO.Path]::GetFileName($src)))
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

if (Test-Path $outZip) { Remove-Item -Force $outZip }

New-ZipFromDirectory -SourceDir $stagingRoot -ZipPath $outZip

try { Remove-Item -Recurse -Force $stagingRoot } catch { }

$zipInfo = Get-Item $outZip
Write-Host ("Created: {0} ({1} MB)" -f $zipInfo.FullName, ([math]::Round($zipInfo.Length / 1MB, 2)))
