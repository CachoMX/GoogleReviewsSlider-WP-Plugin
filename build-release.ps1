# Build script for Google Reviews Slider WordPress Plugin
# Creates a proper ZIP file with consistent folder name for auto-updates
#
# Usage: .\build-release.ps1 [version]
# Example: .\build-release.ps1 2.2.4

param(
    [string]$Version = ""
)

# Get version from parameter or extract from plugin file
if ([string]::IsNullOrEmpty($Version)) {
    $content = Get-Content "google-reviews-slider.php"
    $versionLine = $content | Select-String "Version:\s*([0-9.]+)"
    $Version = $versionLine.Matches.Groups[1].Value
}

Write-Host "=========================================" -ForegroundColor Cyan
Write-Host "Building Google Reviews Slider v$Version" -ForegroundColor Cyan
Write-Host "=========================================" -ForegroundColor Cyan

# Define paths
$BUILD_DIR = "build"
$PLUGIN_FOLDER = "GoogleReviewsSlider-WP-Plugin"
# ZIP name MUST match the folder name WordPress should create
$ZIP_NAME = "$PLUGIN_FOLDER.zip"
$RELEASE_DIR = "releases"

# Clean previous builds
Write-Host "Cleaning previous builds..." -ForegroundColor Yellow
if (Test-Path $BUILD_DIR) {
    Remove-Item -Path $BUILD_DIR -Recurse -Force
}
if (!(Test-Path $RELEASE_DIR)) {
    New-Item -ItemType Directory -Path $RELEASE_DIR | Out-Null
}
New-Item -ItemType Directory -Path $BUILD_DIR | Out-Null

# Create build directory - files will go directly in build/ folder
Write-Host "Preparing build directory..." -ForegroundColor Yellow
$pluginPath = $BUILD_DIR

# Files and folders to exclude
$excludePatterns = @(
    "*.git*",
    "node_modules",
    "build",
    "releases",
    "*.sh",
    "*.bat",
    "*.ps1",
    "README.md",
    "RELEASE-*.md",
    "test-*.php",
    "cleanup-old-folders.php",
    "check-updates.php",
    "force-update-check.php",
    ".vscode",
    ".idea",
    "*.log",
    "build-exclude.txt"
)

# Copy plugin files
Write-Host "Copying plugin files..." -ForegroundColor Yellow
$filesToCopy = Get-ChildItem -Path "." -Recurse | Where-Object {
    $file = $_
    $shouldExclude = $false

    foreach ($pattern in $excludePatterns) {
        if ($file.FullName -like "*$pattern*") {
            $shouldExclude = $true
            break
        }
    }

    !$shouldExclude
}

foreach ($file in $filesToCopy) {
    $relativePath = $file.FullName.Substring((Get-Location).Path.Length + 1)
    $targetPath = Join-Path $pluginPath $relativePath

    if ($file.PSIsContainer) {
        if (!(Test-Path $targetPath)) {
            New-Item -ItemType Directory -Path $targetPath -Force | Out-Null
        }
    } else {
        $targetDir = Split-Path $targetPath -Parent
        if (!(Test-Path $targetDir)) {
            New-Item -ItemType Directory -Path $targetDir -Force | Out-Null
        }
        Copy-Item -Path $file.FullName -Destination $targetPath -Force
        Write-Host "  + $relativePath" -ForegroundColor DarkGray
    }
}

# Create ZIP with files at root (WordPress will create the folder from the ZIP name)
Write-Host "`nCreating ZIP: $ZIP_NAME" -ForegroundColor Yellow
$zipPath = Join-Path (Get-Location).Path (Join-Path $RELEASE_DIR $ZIP_NAME)
$buildFullPath = Join-Path (Get-Location).Path $BUILD_DIR

if (Test-Path $zipPath) {
    Remove-Item -Path $zipPath -Force
}

Add-Type -AssemblyName System.IO.Compression.FileSystem

# Create ZIP with files at root level (no parent folder inside ZIP)
# WordPress will extract this into a folder named after the ZIP file
# IMPORTANT: Use forward slashes for Linux compatibility
$zip = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')
Get-ChildItem -Path $buildFullPath -Recurse -File | ForEach-Object {
    $relativePath = $_.FullName.Substring($buildFullPath.Length + 1)
    # Replace backslashes with forward slashes for Linux compatibility
    $entryName = $relativePath -replace '\\', '/'
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $entryName) | Out-Null
}
$zip.Dispose()

# Verify ZIP structure
Write-Host "`nVerifying ZIP structure..." -ForegroundColor Yellow
$zip = [System.IO.Compression.ZipFile]::OpenRead($zipPath)
$zip.Entries | Select-Object -First 15 | ForEach-Object {
    Write-Host "  $($_.FullName)" -ForegroundColor DarkGray
}
$zip.Dispose()

# Get file size
$fileSize = (Get-Item $zipPath).Length / 1MB
$fileSizeStr = "{0:N2} MB" -f $fileSize

Write-Host "`n=========================================" -ForegroundColor Green
Write-Host "✓ Build Complete!" -ForegroundColor Green
Write-Host "=========================================" -ForegroundColor Green
Write-Host "ZIP file: $zipPath" -ForegroundColor White
Write-Host "File size: $fileSizeStr" -ForegroundColor White
Write-Host "WordPress will extract to: $PLUGIN_FOLDER/" -ForegroundColor White
Write-Host "`nNext steps:" -ForegroundColor Cyan
Write-Host "1. Test the ZIP file locally" -ForegroundColor White
Write-Host "2. Update version in google-reviews-slider.php to $Version" -ForegroundColor White
Write-Host "3. Commit changes" -ForegroundColor White
Write-Host "4. Create GitHub release v$Version" -ForegroundColor White
Write-Host "5. Upload $ZIP_NAME as release asset" -ForegroundColor White
Write-Host "6. Auto-updates will use this ZIP ✓" -ForegroundColor White
Write-Host "`nIMPORTANT: ZIP is named $ZIP_NAME so WordPress extracts to correct folder!" -ForegroundColor Yellow
Write-Host "=========================================" -ForegroundColor Green
