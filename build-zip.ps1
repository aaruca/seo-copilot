param(
    [string]$Version = "1.0.0",
    [string]$Slug = "seo-copilot"
)

$ErrorActionPreference = "Stop"
$root  = Split-Path -Parent $MyInvocation.MyCommand.Path
$stage = Join-Path $root ".build"
$out   = Join-Path (Split-Path $root -Parent) "$Slug-$Version.zip"

if (Test-Path $stage) { Remove-Item $stage -Recurse -Force }
New-Item -ItemType Directory -Path $stage | Out-Null
$dest = Join-Path $stage $Slug
New-Item -ItemType Directory -Path $dest | Out-Null

# Files / folders to ship.
$include = @("seo-copilot.php","uninstall.php","readme.txt","src","views","assets")
foreach ($i in $include) {
    $src = Join-Path $root $i
    if (Test-Path $src) {
        Copy-Item $src $dest -Recurse -Force
    }
}

# Strip dev artefacts that may have slipped in.
Get-ChildItem -Recurse -Path $dest -Force -Include ".DS_Store","Thumbs.db",".gitkeep" -ErrorAction SilentlyContinue | Remove-Item -Force

if (Test-Path $out) { Remove-Item $out -Force }

# Build zip with forward slashes so WordPress/Linux can read it.
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::Open($out, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    $stagedRoot = $stage
    Get-ChildItem -Recurse -Path $stagedRoot | ForEach-Object {
        if ($_.PSIsContainer) { return }
        $rel = $_.FullName.Substring($stagedRoot.Length + 1) -replace "\\","/"
        $entry = $zip.CreateEntry($rel, [System.IO.Compression.CompressionLevel]::Optimal)
        $stream = $entry.Open()
        $bytes = [System.IO.File]::ReadAllBytes($_.FullName)
        $stream.Write($bytes, 0, $bytes.Length)
        $stream.Close()
    }
} finally {
    $zip.Dispose()
}

Remove-Item $stage -Recurse -Force
Write-Host "Built $out"
