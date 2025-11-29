#!/bin/bash
#
# Build plugin ZIP for distribution
#
# Usage: ./bin/build-zip.sh [version]
#

set -e

# Plugin slug
PLUGIN_SLUG="piip"

# Get the plugin root directory
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# Get version from piip.php if not provided
if [ -z "$1" ]; then
    VERSION=$(grep -oP "Version:\s*\K[0-9.]+" "$PLUGIN_DIR/piip.php")
else
    VERSION="$1"
fi

# Build directory
BUILD_DIR="$PLUGIN_DIR/build"
DIST_DIR="$BUILD_DIR/$PLUGIN_SLUG"
ZIP_FILE="$BUILD_DIR/${PLUGIN_SLUG}-${VERSION}.zip"

echo "Building ${PLUGIN_SLUG} v${VERSION}..."

# Clean up previous build
rm -rf "$BUILD_DIR"
mkdir -p "$DIST_DIR"

# Build exclude patterns for tar
EXCLUDE_ARGS=""
if [ -f "$PLUGIN_DIR/.distignore" ]; then
    while IFS= read -r line || [ -n "$line" ]; do
        # Skip empty lines and comments
        [[ -z "$line" || "$line" =~ ^# ]] && continue
        # Remove trailing slash for directories
        line="${line%/}"
        EXCLUDE_ARGS="$EXCLUDE_ARGS --exclude=$line"
    done < "$PLUGIN_DIR/.distignore"
fi

# Copy files using tar (more portable than rsync)
echo "Copying files..."
cd "$PLUGIN_DIR"
tar -cf - $EXCLUDE_ARGS . | tar -xf - -C "$DIST_DIR"

# Remove any empty directories
find "$DIST_DIR" -type d -empty -delete 2>/dev/null || true

# Create ZIP
echo "Creating ZIP archive..."
cd "$BUILD_DIR"
zip -rq "$ZIP_FILE" "$PLUGIN_SLUG" -x "*.DS_Store" -x "*__MACOSX*"

# Cleanup temporary directory
rm -rf "$DIST_DIR"

# Output result
echo ""
echo "========================================="
echo "Build complete!"
echo "ZIP file: $ZIP_FILE"
echo "Size: $(du -h "$ZIP_FILE" | cut -f1)"
echo "========================================="
