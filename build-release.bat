@echo off
REM Build script for Google Reviews Slider WordPress Plugin
REM Creates a proper ZIP file with consistent folder name for auto-updates
REM
REM Usage: build-release.bat [version]
REM Example: build-release.bat 2.2.4

setlocal enabledelayedexpansion

REM Get version from argument or extract from plugin file
if "%~1"=="" (
    for /f "tokens=2" %%a in ('findstr /C:"Version:" google-reviews-slider.php') do set VERSION=%%a
) else (
    set VERSION=%~1
)

echo =========================================
echo Building Google Reviews Slider v%VERSION%
echo =========================================

REM Define paths
set BUILD_DIR=build
set PLUGIN_FOLDER=GoogleReviewsSlider-WP-Plugin
set ZIP_NAME=google-reviews-slider-%VERSION%.zip
set RELEASE_DIR=releases

REM Clean previous builds
echo Cleaning previous builds...
if exist "%BUILD_DIR%" rmdir /s /q "%BUILD_DIR%"
if not exist "%RELEASE_DIR%" mkdir "%RELEASE_DIR%"
mkdir "%BUILD_DIR%"

REM Create plugin folder with CORRECT name (no version suffix!)
echo Creating plugin folder: %PLUGIN_FOLDER%
mkdir "%BUILD_DIR%\%PLUGIN_FOLDER%"

REM Copy plugin files (excluding development files)
echo Copying plugin files...
xcopy /E /I /Q /EXCLUDE:build-exclude.txt . "%BUILD_DIR%\%PLUGIN_FOLDER%"

REM Create ZIP using PowerShell
echo Creating ZIP: %ZIP_NAME%
powershell -command "Compress-Archive -Path '%BUILD_DIR%\%PLUGIN_FOLDER%' -DestinationPath '%RELEASE_DIR%\%ZIP_NAME%' -Force"

REM Verify ZIP
echo.
echo Verifying ZIP structure...
powershell -command "Add-Type -AssemblyName System.IO.Compression.FileSystem; $zip = [System.IO.Compression.ZipFile]::OpenRead('%RELEASE_DIR%\%ZIP_NAME%'); $zip.Entries | Select-Object -First 10 -Property FullName | Format-Table; $zip.Dispose()"

REM Get file size
for %%A in ("%RELEASE_DIR%\%ZIP_NAME%") do set FILE_SIZE=%%~zA

echo.
echo =========================================
echo Build Complete!
echo =========================================
echo ZIP file: %RELEASE_DIR%\%ZIP_NAME%
echo File size: %FILE_SIZE% bytes
echo Folder name in ZIP: %PLUGIN_FOLDER%
echo.
echo Next steps:
echo 1. Test the ZIP file locally
echo 2. Create GitHub release v%VERSION%
echo 3. Upload %ZIP_NAME% as release asset
echo 4. Auto-updates will use this ZIP
echo =========================================

pause
