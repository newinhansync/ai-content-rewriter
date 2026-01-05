#!/bin/bash
#
# AI Content Rewriter - Build Script
# Creates a distributable ZIP package for WordPress plugin installation
#
# Usage: ./build.sh [version]
# Example: ./build.sh 1.0.6
#

set -e

# Configuration
PLUGIN_SLUG="ai-content-rewriter"
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BUILD_DIR="${PLUGIN_DIR}/build"
DIST_DIR="/Users/hansync/Dropbox/Project2025-dev/wordpress/dist"

# Get version from argument or main plugin file
if [ -n "$1" ]; then
    VERSION="$1"
else
    VERSION=$(grep -m 1 "Version:" "${PLUGIN_DIR}/${PLUGIN_SLUG}.php" | sed 's/.*Version: *//' | tr -d '[:space:]')
fi

ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"

echo "=========================================="
echo "Building ${PLUGIN_SLUG} v${VERSION}"
echo "=========================================="

# Clean up previous builds
echo "Cleaning up previous builds..."
rm -rf "${BUILD_DIR}"
rm -f "${DIST_DIR}/${PLUGIN_SLUG}-"*.zip

# Create directories
mkdir -p "${BUILD_DIR}/${PLUGIN_SLUG}"

# Files and directories to include
echo "Copying plugin files..."
INCLUDE_FILES=(
    "${PLUGIN_SLUG}.php"
    "readme.txt"
    "uninstall.php"
    "src"
    "assets"
    "languages"
    "templates"
    "vendor"
)

for item in "${INCLUDE_FILES[@]}"; do
    if [ -e "${PLUGIN_DIR}/${item}" ]; then
        cp -r "${PLUGIN_DIR}/${item}" "${BUILD_DIR}/${PLUGIN_SLUG}/"
        echo "  + ${item}"
    fi
done

# Remove development files from copied directories
echo "Removing development files..."
find "${BUILD_DIR}" -name "*.git*" -exec rm -rf {} + 2>/dev/null || true
find "${BUILD_DIR}" -name ".DS_Store" -exec rm -rf {} + 2>/dev/null || true
find "${BUILD_DIR}" -name "*.log" -exec rm -rf {} + 2>/dev/null || true
find "${BUILD_DIR}" -name "Thumbs.db" -exec rm -rf {} + 2>/dev/null || true
find "${BUILD_DIR}" -name "*.map" -exec rm -rf {} + 2>/dev/null || true
find "${BUILD_DIR}" -name "node_modules" -exec rm -rf {} + 2>/dev/null || true
find "${BUILD_DIR}" -name "tests" -type d -exec rm -rf {} + 2>/dev/null || true
find "${BUILD_DIR}" -name "test" -type d -exec rm -rf {} + 2>/dev/null || true
find "${BUILD_DIR}" -name ".phpcs.xml" -exec rm -rf {} + 2>/dev/null || true
find "${BUILD_DIR}" -name "phpunit.xml" -exec rm -rf {} + 2>/dev/null || true
find "${BUILD_DIR}" -name "composer.json" -exec rm -rf {} + 2>/dev/null || true
find "${BUILD_DIR}" -name "composer.lock" -exec rm -rf {} + 2>/dev/null || true
find "${BUILD_DIR}" -name "package.json" -exec rm -rf {} + 2>/dev/null || true
find "${BUILD_DIR}" -name "package-lock.json" -exec rm -rf {} + 2>/dev/null || true

# Create ZIP file
echo "Creating ZIP archive..."
cd "${BUILD_DIR}"
zip -r "${DIST_DIR}/${ZIP_NAME}" "${PLUGIN_SLUG}" -x "*.DS_Store" -x "*__MACOSX*"

# Also create a latest version link
cp "${DIST_DIR}/${ZIP_NAME}" "${DIST_DIR}/${PLUGIN_SLUG}-latest.zip"

# Clean up build directory
rm -rf "${BUILD_DIR}"

# Output results
echo ""
echo "=========================================="
echo "Build complete!"
echo "=========================================="
echo ""
echo "Output files:"
echo "  ${DIST_DIR}/${ZIP_NAME}"
echo "  ${DIST_DIR}/${PLUGIN_SLUG}-latest.zip"
echo ""
echo "File size: $(du -h "${DIST_DIR}/${ZIP_NAME}" | cut -f1)"
echo ""
echo "To install:"
echo "  1. Go to WordPress Admin > Plugins > Add New > Upload Plugin"
echo "  2. Choose the ZIP file and click 'Install Now'"
echo "  3. Activate the plugin"
echo ""
