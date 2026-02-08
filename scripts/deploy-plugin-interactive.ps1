param(
  [string]$FtpHost = "linux9.unoeuro.com",
  [int]$FtpPort = 21,
  [string]$FtpUser = "wpbookpoint.com",
  [string]$RemotePluginDir = "/public_html/wp-content/plugins/bookpoint-v5",
  [switch]$DryRun,
  [switch]$Fast
)

$ErrorActionPreference = "Stop"

function Require-Val([string]$name, [string]$val) {
  if ([string]::IsNullOrWhiteSpace($val)) { throw "Missing required value: $name" }
}

Require-Val "FtpHost" $FtpHost
Require-Val "FtpUser" $FtpUser
Require-Val "RemotePluginDir" $RemotePluginDir

$RemotePluginDir = $RemotePluginDir.TrimEnd("/")

Write-Host "BookPoint deploy (interactive)"
Write-Host ("Host: {0}:{1}" -f $FtpHost, $FtpPort)
Write-Host ("Remote: {0}" -f $RemotePluginDir)
Write-Host ("Mode: {0}" -f ($(if ($Fast) { "FAST (changed files only)" } else { "FULL (all plugin files)" })))

try {
  if (-not $DryRun) {
    $securePass = Read-Host -Prompt "FTP password (input hidden)" -AsSecureString
    $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePass)
    try {
      $plainPass = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr)
    } finally {
      [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)
    }

    $netrcPath = Join-Path $env:TEMP ("bp-netrc-{0}.txt" -f ([Guid]::NewGuid().ToString("N")))
    @"
machine $FtpHost
login $FtpUser
password $plainPass
"@ | Set-Content -Path $netrcPath -Encoding ASCII -NoNewline
  }

  $hostPart = $FtpHost
  if ($FtpPort -and $FtpPort -ne 21) { $hostPart = "$FtpHost`:$FtpPort" }
  $remoteBase = "ftp://$hostPart$RemotePluginDir"

  $basePath = (Get-Location).Path.TrimEnd("\")

  function Get-RelPath([string]$fullPath) {
    $rel = $fullPath.Substring($basePath.Length).TrimStart("\", "/")
    return $rel
  }

  $filesToUpload = New-Object System.Collections.Generic.List[string]

  if ($Fast) {
    $fastFiles = @(
      "bookpoint-v5.php",
      "uninstall.php",
      "build/admin.js",
      "build/admin.asset.php",
      "build/index.jsx.css",
      "build/index.jsx-rtl.css",
      "public/front.js",
      "public/front.asset.php",
      "public/index.jsx.css",
      "public/index.jsx-rtl.css",
      "lib/rest/admin-booking-form-design-routes.php",
      "public/admin-ui.css",
      "public/admin-app.css"
    )

    foreach ($f in $fastFiles) {
      $p = Join-Path $basePath $f
      if (Test-Path $p) { $filesToUpload.Add($p) }
    }
  } else {
    $rootFiles = @(
      "bookpoint-v5.php",
      "uninstall.php"
    )

    $rootDirs = @(
      "build",
      "lib",
      "public",
      "languages",
      "blocks/build"
    )

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

  $idx = 0
  foreach ($full in $filesToUpload) {
    $idx++
    $rel = Get-RelPath $full
    $url = ($remoteBase + "/" + ($rel -replace "\\", "/"))

    if ($DryRun) {
      Write-Host ("DRYRUN {0}/{1}  {2} -> {3}" -f $idx, $filesToUpload.Count, $rel, $url)
      continue
    }

    Write-Host ("Upload {0}/{1}  {2}" -f $idx, $filesToUpload.Count, $rel)

    $ok = $false
    for ($attempt = 1; $attempt -le 3; $attempt++) {
      curl.exe -sS --ssl-reqd --ftp-create-dirs --netrc-file "$netrcPath" -T "$full" "$url" | Out-Null
      if ($LASTEXITCODE -eq 0) { $ok = $true; break }
      Start-Sleep -Seconds ([Math]::Min(5, $attempt * 2))
    }

    if (-not $ok) { throw "Upload failed after retries: $rel" }
  }

  Write-Host "Deploy complete."
} finally {
  if ($netrcPath -and (Test-Path $netrcPath)) {
    Remove-Item -Force $netrcPath | Out-Null
  }

  $plainPass = $null
  $securePass = $null
}
