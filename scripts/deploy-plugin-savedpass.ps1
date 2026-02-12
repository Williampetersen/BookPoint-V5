param(
  [string]$FtpHost = "linux9.unoeuro.com",
  [int]$FtpPort = 21,
  [string]$FtpUser = "wpbookpoint.com",
  [string]$RemotePluginDir = "/public_html/wp-content/plugins/bookpoint-v5",
  [string]$PassFile = (Join-Path $env:USERPROFILE ".bp_ftp_pass.enc"),
  [switch]$CreatePassFileIfMissing,
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

if (-not (Test-Path $PassFile)) {
  if ($CreatePassFileIfMissing) {
    Write-Host ("Saved password file not found: {0}" -f $PassFile)
    $securePass = Read-Host -Prompt "FTP password (input hidden)" -AsSecureString
    if (-not $securePass) { throw "No password provided." }
    $securePass | ConvertFrom-SecureString | Set-Content -Path $PassFile -Encoding ASCII -NoNewline
    Write-Host ("Saved encrypted password to: {0}" -f $PassFile)
  } else {
    throw "Missing saved password file: $PassFile (create it with: powershell -ExecutionPolicy Bypass -File scripts/store-ftp-pass.ps1)"
  }
}

$enc = Get-Content -Path $PassFile -Raw
if ([string]::IsNullOrWhiteSpace($enc)) { throw "Saved password file is empty: $PassFile" }

$securePass = $enc | ConvertTo-SecureString
$bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePass)
try {
  $plainPass = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr)

  $env:BP_FTP_HOST = $FtpHost
  $env:BP_FTP_USER = $FtpUser
  $env:BP_FTP_PASS = $plainPass
  $env:BP_FTP_REMOTE_PLUGIN_DIR = $RemotePluginDir

  $deployScript = Join-Path $PSScriptRoot "deploy-plugin.ps1"
  if (-not (Test-Path $deployScript)) { throw "Missing deploy script: $deployScript" }

  & $deployScript -FtpPort $FtpPort -DryRun:$DryRun -Fast:$Fast
  if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
} finally {
  if ($bstr -and $bstr -ne [IntPtr]::Zero) {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)
  }
  $plainPass = $null
  $securePass = $null
  $enc = $null
  $env:BP_FTP_PASS = ""
}
