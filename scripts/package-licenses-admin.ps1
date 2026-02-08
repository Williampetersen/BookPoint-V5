$ErrorActionPreference = 'Stop'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$distDir = Join-Path $repoRoot 'dist'
$outZip = Join-Path $distDir 'bookpoint-licenses-admin.zip'
$tmpZip = Join-Path $distDir 'bookpoint-licenses-admin._tmp.zip'
$stagingRoot = Join-Path $distDir ('.staging-licenses-admin-' + [DateTime]::UtcNow.ToString('yyyyMMddHHmmssfff'))
$pluginDirName = 'bookpoint-licenses-admin'
$stagingPluginDir = Join-Path $stagingRoot $pluginDirName

New-Item -ItemType Directory -Force $distDir | Out-Null
New-Item -ItemType Directory -Force $stagingPluginDir | Out-Null

$srcMain = Join-Path (Join-Path $repoRoot 'licenses-admin') 'bookpoint-licenses-admin.php'
if (!(Test-Path $srcMain)) { throw "Missing: $srcMain" }
Copy-Item -Force $srcMain (Join-Path $stagingPluginDir 'bookpoint-licenses-admin.php')

if (Test-Path $tmpZip) { Remove-Item -Force $tmpZip }

$maxAttempts = 5
for ($attempt = 1; $attempt -le $maxAttempts; $attempt++) {
  try {
    Compress-Archive -Path (Join-Path $stagingRoot '*') -DestinationPath $tmpZip -CompressionLevel Optimal -Force
    break
  } catch {
    if ($attempt -ge $maxAttempts) { throw }
    Start-Sleep -Seconds 1
  }
}

try { Remove-Item -Recurse -Force $stagingRoot } catch { }

for ($i = 1; $i -le 20; $i++) {
  try {
    if (Test-Path $outZip) {
      Move-Item -Force $outZip ($outZip + '.bak') -ErrorAction SilentlyContinue
    }
    Move-Item -Force $tmpZip $outZip
    break
  } catch {
    if ($i -eq 20) {
      $fallback = Join-Path $distDir ('bookpoint-licenses-admin-' + [DateTime]::UtcNow.ToString('yyyyMMddHHmmss') + '.zip')
      Move-Item -Force $tmpZip $fallback
      $zipInfo = Get-Item $fallback
      Write-Host ("Created: {0} ({1} MB)" -f $zipInfo.FullName, ([math]::Round($zipInfo.Length / 1MB, 2)))
      return
    }
    Start-Sleep -Milliseconds (250 * $i)
  }
}

$zipInfo = Get-Item $outZip
Write-Host ("Created: {0} ({1} MB)" -f $zipInfo.FullName, ([math]::Round($zipInfo.Length / 1MB, 2)))

