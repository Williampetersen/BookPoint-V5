$ErrorActionPreference = 'Stop'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$distDir = Join-Path $repoRoot 'dist'
$outZip = Join-Path $distDir 'bookpoint-v5-pro.zip'
$stagingRoot = Join-Path $distDir ('.staging-pro-' + [DateTime]::UtcNow.ToString('yyyyMMddHHmmssfff'))
$pluginDirName = 'bookpoint-v5'
$stagingPluginDir = Join-Path $stagingRoot $pluginDirName

New-Item -ItemType Directory -Force $distDir | Out-Null
New-Item -ItemType Directory -Force $stagingPluginDir | Out-Null

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

if (Test-Path $outZip) { Remove-Item -Force $outZip }

$maxAttempts = 5
for ($attempt = 1; $attempt -le $maxAttempts; $attempt++) {
  try {
    Compress-Archive -Path (Join-Path $stagingRoot '*') -DestinationPath $outZip -CompressionLevel Optimal -Force
    break
  } catch {
    if ($attempt -ge $maxAttempts) { throw }
    Start-Sleep -Seconds 1
  }
}

try { Remove-Item -Recurse -Force $stagingRoot } catch { }

$zipInfo = Get-Item $outZip
Write-Host ("Created: {0} ({1} MB)" -f $zipInfo.FullName, ([math]::Round($zipInfo.Length / 1MB, 2)))

