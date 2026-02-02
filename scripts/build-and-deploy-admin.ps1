Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$localEnv = Join-Path $PSScriptRoot ".deploy.local.ps1"
if (Test-Path $localEnv) {
  . $localEnv
}

& npm run build:admin
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

& npm run deploy:admin
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

