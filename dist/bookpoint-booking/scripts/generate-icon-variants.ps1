param(
  [string]$IconsDir = (Join-Path (Get-Location).Path "public/icons"),
  [string]$ActiveColor = "#2563eb",
  [string]$DarkColor = "#cbd5e1",
  [string]$ActiveDarkColor = "#60a5fa"
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

function Apply-Color([string]$svg, [string]$color) {
  $out = $svg

  # Replace fill attributes (except 'none').
  $out = [Regex]::Replace($out, 'fill=\"(?!none\b)[^\"]+\"', ('fill="{0}"' -f $color))
  $out = [Regex]::Replace($out, 'fill\:(?!none\b)[^;]+;', ('fill:{0};' -f $color))

  # Replace stroke attributes (except 'none').
  $out = [Regex]::Replace($out, 'stroke=\"(?!none\b)[^\"]+\"', ('stroke="{0}"' -f $color))
  $out = [Regex]::Replace($out, 'stroke\:(?!none\b)[^;]+;', ('stroke:{0};' -f $color))

  return $out
}

$written = New-Object System.Collections.Generic.List[string]

foreach ($name in $iconNames) {
  $basePath = Join-Path $IconsDir ("{0}.svg" -f $name)
  if (-not (Test-Path $basePath)) { continue }

  $baseSvg = Get-Content -Path $basePath -Raw
  if ([string]::IsNullOrWhiteSpace($baseSvg)) { continue }

  $targets = @(
    @{ Suffix = "-active"; Color = $ActiveColor },
    @{ Suffix = "-dark"; Color = $DarkColor },
    @{ Suffix = "-active-dark"; Color = $ActiveDarkColor }
  )

  foreach ($t in $targets) {
    $targetPath = Join-Path $IconsDir ("{0}{1}.svg" -f $name, $t.Suffix)
    $variantSvg = Apply-Color $baseSvg $t.Color
    Set-Content -Path $targetPath -Value $variantSvg -Encoding utf8
    $written.Add((Split-Path $targetPath -Leaf))
  }
}

if ($written.Count -gt 0) {
  Write-Host ("Generated {0} icon variants:" -f $written.Count)
  $written | Sort-Object | ForEach-Object { Write-Host ("  - {0}" -f $_) }
} else {
  Write-Host "No icons generated (no base icons found)."
}
