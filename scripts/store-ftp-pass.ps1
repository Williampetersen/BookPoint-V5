param(
  [string]$Path = (Join-Path $env:USERPROFILE ".bp_ftp_pass.enc"),
  [switch]$Force
)

$ErrorActionPreference = "Stop"

if (-not $Force -and (Test-Path $Path)) {
  throw "Password file already exists: $Path (use -Force to overwrite)"
}

$parent = Split-Path -Parent $Path
if ($parent -and -not (Test-Path $parent)) {
  New-Item -ItemType Directory -Path $parent -Force | Out-Null
}

$securePass = Read-Host -Prompt "FTP password (input hidden)" -AsSecureString
if (-not $securePass) { throw "No password provided." }

$enc = $securePass | ConvertFrom-SecureString
Set-Content -Path $Path -Value $enc -Encoding ASCII -NoNewline

Write-Host ("Saved encrypted password to: {0}" -f $Path)
Write-Host ("Verify: {0}" -f $(Test-Path $Path))
