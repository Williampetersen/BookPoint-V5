param(
  [switch]$Fast = $true,
  [switch]$BuildAdmin,
  [switch]$AutoBumpVersion = $true,
  [ValidateRange(250, 10000)]
  [int]$DebounceMs = 1500
)

$ErrorActionPreference = "Stop"

$repoRoot = (Resolve-Path ".").Path
$deployScript = Join-Path $repoRoot "scripts\\deploy-plugin.ps1"
if (!(Test-Path $deployScript)) {
  throw "deploy script not found: $deployScript"
}

function Should-Ignore([string]$fullPath) {
  $p = $fullPath.ToLowerInvariant()
  return (
    $p.Contains("\\.git\\") -or
    $p.Contains("\\node_modules\\") -or
    $p.Contains("\\vendor\\") -or
    $p.Contains("\\.vscode\\") -or
    $p.Contains("\\.idea\\") -or
    $p.Contains("\\.cache\\") -or
    $p.Contains("\\scripts\\")
  )
}

function Needs-Admin-Build([string]$fullPath) {
  $p = $fullPath.ToLowerInvariant()
  return $p.Contains("\\src\\admin\\")
}

function Bump-Version {
  $file = Join-Path $repoRoot "bookpoint-v5.php"
  if (!(Test-Path $file)) { return }
  $content = Get-Content -Path $file -Raw
  $m = [regex]::Match($content, "Version:\\s*(\\d+)\\.(\\d+)\\.(\\d+)")
  if (!$m.Success) { return }
  $major = [int]$m.Groups[1].Value
  $minor = [int]$m.Groups[2].Value
  $patch = [int]$m.Groups[3].Value + 1
  $newVer = "$major.$minor.$patch"
  $content = [regex]::Replace($content, "Version:\\s*\\d+\\.\\d+\\.\\d+", "Version: $newVer", 1)
  $content = [regex]::Replace($content, "const VERSION\\s*=\\s*'\\d+\\.\\d+\\.\\d+';", "const VERSION    = '$newVer';", 1)
  Set-Content -Path $file -Value $content -NoNewline
  Write-Host "Version bumped to $newVer"
}

$script:pending = $false
$script:busy = $false
$script:needsAdminBuild = $false

$timer = New-Object System.Timers.Timer($DebounceMs)
$timer.AutoReset = $false
$timer.add_Elapsed({
  if ($script:busy) { $script:pending = $true; return }
  $script:busy = $true
  try {
    if ($AutoBumpVersion) { Bump-Version }
    $doBuild = $BuildAdmin -or $script:needsAdminBuild
    $script:needsAdminBuild = $false
    if ($doBuild) {
      Write-Host "Running admin build..."
      npm run build:admin | Out-Host
    }
    $args = @()
    if ($Fast) { $args += "-Fast" }
    & $deployScript @args
  } finally {
    $script:busy = $false
    if ($script:pending) { $script:pending = $false; $timer.Start() }
  }
})

$watchDirs = @(
  $repoRoot,
  (Join-Path $repoRoot "src"),
  (Join-Path $repoRoot "public"),
  (Join-Path $repoRoot "build"),
  (Join-Path $repoRoot "lib"),
  (Join-Path $repoRoot "blocks"),
  (Join-Path $repoRoot "languages")
)

$watchers = @()
foreach ($dir in $watchDirs) {
  if (!(Test-Path $dir)) { continue }
  $fsw = New-Object System.IO.FileSystemWatcher
  $fsw.Path = $dir
  $fsw.IncludeSubdirectories = $true
  $fsw.EnableRaisingEvents = $true
  $fsw.NotifyFilter = [IO.NotifyFilters]'FileName, LastWrite, Size, DirectoryName'
  Register-ObjectEvent -InputObject $fsw -EventName Changed -Action {
    $full = $Event.SourceEventArgs.FullPath
    if (Should-Ignore $full) { return }
    if (Needs-Admin-Build $full) { $script:needsAdminBuild = $true }
    if ($script:busy) { $script:pending = $true; return }
    $timer.Stop()
    $timer.Start()
  } | Out-Null
  Register-ObjectEvent -InputObject $fsw -EventName Created -Action {
    $full = $Event.SourceEventArgs.FullPath
    if (Should-Ignore $full) { return }
    if (Needs-Admin-Build $full) { $script:needsAdminBuild = $true }
    if ($script:busy) { $script:pending = $true; return }
    $timer.Stop()
    $timer.Start()
  } | Out-Null
  Register-ObjectEvent -InputObject $fsw -EventName Renamed -Action {
    $full = $Event.SourceEventArgs.FullPath
    if (Should-Ignore $full) { return }
    if (Needs-Admin-Build $full) { $script:needsAdminBuild = $true }
    if ($script:busy) { $script:pending = $true; return }
    $timer.Stop()
    $timer.Start()
  } | Out-Null
  $watchers += $fsw
}

Write-Host "Auto-deploy is running. Debounce: $DebounceMs ms. Press Ctrl+C to stop."
while ($true) { Start-Sleep -Seconds 2 }
