# create-plugin-zip.ps1
# Creates a WordPress plugin ZIP with forward-slash paths for UNIX/Linux compatibility.

$ErrorActionPreference = "Stop"

$pluginDir = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $pluginDir

# Extract plugin name and version from main PHP file header.
$mainFile = Get-ChildItem -Path $pluginDir -Filter "*.php" -Depth 0 | Where-Object {
    ((Get-Content $_.FullName -TotalCount 5) -join "`n") -match "Plugin Name:"
} | Select-Object -First 1

if (-not $mainFile) {
    Write-Error "No main plugin file found."
    exit 1
}

$header = Get-Content $mainFile.FullName -Raw
$pluginName = if ($header -match "Plugin Name:\s*(.+)") { $Matches[1].Trim() } else { "plugin" }
$version = if ($header -match "Version:\s*([\d.]+)") { $Matches[1].Trim() } else { "0.0.0" }

# Derive slug from folder name.
$slug = (Get-Item $pluginDir).Name
$zipName = "$slug-$version.zip"
$outputDir = Join-Path $pluginDir "plugin"
$zipPath = Join-Path $outputDir $zipName

# Create plugin/ subfolder if needed.
if (-not (Test-Path $outputDir)) {
    New-Item -ItemType Directory -Path $outputDir -Force | Out-Null
}

# Remove old ZIP files.
Get-ChildItem -Path $outputDir -Filter "*.zip" -ErrorAction SilentlyContinue | Remove-Item -Force

Write-Host "Creating $zipName for $pluginName v$version..." -ForegroundColor Cyan

# Collect files, applying exclusion rules.
$files = Get-ChildItem -Path $pluginDir -Recurse -File -Force | Where-Object {
    $rel = $_.FullName.Substring($pluginDir.Length + 1)
    $parts = $rel -split '[/\\]'

    # Exclude hidden files/folders (starting with .)
    $hidden = $false
    foreach ($p in $parts) {
        if ($p.StartsWith('.')) { $hidden = $true; break }
    }
    if ($hidden) { return $false }

    # Exclude folders starting with src-
    foreach ($p in $parts[0..($parts.Length - 2)]) {
        if ($p -match '^src-') { return $false }
    }

    # Exclude folders starting with svelte- anywhere in path
    foreach ($p in $parts[0..($parts.Length - 2)]) {
        if ($p -match '^svelte-') { return $false }
    }

    # Exclude specific directories
    $excludeDirs = @('node_modules', 'plugin')
    foreach ($d in $excludeDirs) {
        if ($parts[0] -eq $d) { return $false }
    }

    # Exclude .md files
    if ($_.Extension -eq '.md') { return $false }

    # Exclude .log files
    if ($_.Extension -eq '.log') { return $false }

    # Exclude build configs
    if ($_.Name -match '^vite\.config\.') { return $false }
    if ($_.Name -match '^tsconfig\.') { return $false }
    if ($_.Name -match '^svelte\.config\.') { return $false }

    # Exclude package files
    if ($_.Name -eq 'package.json') { return $false }
    if ($_.Name -eq 'package-lock.json') { return $false }
    if ($_.Name -eq 'composer.lock') { return $false }

    # Exclude test configs
    if ($_.Name -eq 'phpcs.xml') { return $false }
    if ($_.Name -eq 'phpunit.xml') { return $false }

    # Exclude this script itself
    if ($_.Name -eq 'create-plugin-zip.ps1') { return $false }

    return $true
}

# Build ZIP using .NET with forward-slash paths.
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$stream = [System.IO.File]::Create($zipPath)
$archive = New-Object System.IO.Compression.ZipArchive($stream, [System.IO.Compression.ZipArchiveMode]::Create)

foreach ($file in $files) {
    $rel = $file.FullName.Substring($pluginDir.Length + 1)
    # Forward slashes + plugin slug prefix for proper extraction
    $entryPath = "$slug/" + ($rel -replace '\\', '/')
    $entry = $archive.CreateEntry($entryPath, [System.IO.Compression.CompressionLevel]::Optimal)
    $entryStream = $entry.Open()
    $fileStream = [System.IO.File]::OpenRead($file.FullName)
    $fileStream.CopyTo($entryStream)
    $fileStream.Close()
    $entryStream.Close()
}

$archive.Dispose()
$stream.Close()

$size = [math]::Round((Get-Item $zipPath).Length / 1KB, 1)
Write-Host "Done! plugin/$zipName ($size KB)" -ForegroundColor Green
