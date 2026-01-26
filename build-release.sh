#!/bin/bash
# Build script for Google Reviews Slider WordPress Plugin
# Creates a proper ZIP file with consistent folder name for auto-updates
#
# Usage: ./build-release.sh [version]
# Example: ./build-release.sh 2.2.4
#
# This ensures the ZIP contains:
# GoogleReviewsSlider-WP-Plugin/
#   ├── google-reviews-slider.php
#   ├── css/
#   ├── js/
#   └── ...
#
# NOT:
# GoogleReviewsSlider-WP-Plugin-2.2.4/  ← Version in folder name (BAD!)

set -e  # Exit on error

# Get version from argument or extract from main plugin file
if [ -n "$1" ]; then
    VERSION="$1"
else
    VERSION=$(grep -oP "Version:\s*\K[0-9.]+" google-reviews-slider.php | head -1)
fi

echo "========================================="
echo "Building Google Reviews Slider v${VERSION}"
echo "========================================="

# Define paths
BUILD_DIR="build"
PLUGIN_FOLDER="GoogleReviewsSlider-WP-Plugin"
ZIP_NAME="google-reviews-slider-${VERSION}.zip"
RELEASE_DIR="releases"

# Clean previous builds
echo "Cleaning previous builds..."
rm -rf "${BUILD_DIR}"
mkdir -p "${BUILD_DIR}"
mkdir -p "${RELEASE_DIR}"

# Create plugin folder with CORRECT name (no version suffix!)
echo "Creating plugin folder: ${PLUGIN_FOLDER}"
mkdir -p "${BUILD_DIR}/${PLUGIN_FOLDER}"

# Copy plugin files
echo "Copying plugin files..."
rsync -av --progress \
    --exclude='.git' \
    --exclude='.gitignore' \
    --exclude='node_modules' \
    --exclude='build' \
    --exclude='releases' \
    --exclude='*.sh' \
    --exclude='*.md' \
    --exclude='test-*.php' \
    --exclude='cleanup-old-folders.php' \
    --exclude='check-updates.php' \
    --exclude='force-update-check.php' \
    --exclude='.vscode' \
    --exclude='.idea' \
    --exclude='*.log' \
    ./ "${BUILD_DIR}/${PLUGIN_FOLDER}/"

# Create ZIP
echo "Creating ZIP: ${ZIP_NAME}"
cd "${BUILD_DIR}"
zip -r "../${RELEASE_DIR}/${ZIP_NAME}" "${PLUGIN_FOLDER}" -q

# Go back to root
cd ..

# Verify ZIP structure
echo ""
echo "Verifying ZIP structure..."
unzip -l "${RELEASE_DIR}/${ZIP_NAME}" | head -20

# Calculate file size
FILE_SIZE=$(du -h "${RELEASE_DIR}/${ZIP_NAME}" | cut -f1)

echo ""
echo "========================================="
echo "✓ Build Complete!"
echo "========================================="
echo "ZIP file: ${RELEASE_DIR}/${ZIP_NAME}"
echo "File size: ${FILE_SIZE}"
echo "Folder name in ZIP: ${PLUGIN_FOLDER}"
echo ""
echo "Next steps:"
echo "1. Test the ZIP file locally"
echo "2. Create GitHub release v${VERSION}"
echo "3. Upload ${ZIP_NAME} as release asset"
echo "4. Auto-updates will use this ZIP"
echo "========================================="
