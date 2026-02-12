param(
  [string]$IconsDir = (Join-Path (Get-Location).Path "public/icons")
)

$ErrorActionPreference = "Stop"

if (-not (Test-Path $IconsDir)) {
  throw "Icons directory not found: $IconsDir"
}

$iconNames = @(
  "agents",
  "bookings",
  "calendar",
  "categories",
  "customers",
  "dashboard",
  "designer",
  "locations",
  "service-extras",
  "services",
  "settings"
)

$suffixes = @(
  "-active",
  "-dark",
  "-active-dark"
)

$created = New-Object System.Collections.Generic.List[string]

foreach ($name in $iconNames) {
  $base = Join-Path $IconsDir ("{0}.svg" -f $name)
  if (-not (Test-Path $base)) { continue }

  foreach ($s in $suffixes) {
    $target = Join-Path $IconsDir ("{0}{1}.svg" -f $name, $s)
    if (Test-Path $target) { continue }
    Copy-Item -Path $base -Destination $target
    $created.Add((Split-Path $target -Leaf))
  }
}

if ($created.Count -gt 0) {
  Write-Host ("Created {0} missing icon variants:" -f $created.Count)
  $created | Sort-Object | ForEach-Object { Write-Host ("  - {0}" -f $_) }
} else {
  Write-Host "All icon variants already exist."
}
