#!/bin/bash
# WordPress Plugin Build Script for Newera
# This script creates a production-ready zip package of the plugin

set -e

PLUGIN_NAME="newera"
VERSION=$(grep "Version:" newera.php | sed 's/.*Version: //' | tr -d ' \r')
BUILD_DIR="/tmp/newera-build"
OUTPUT_FILE="${PLUGIN_NAME}-${VERSION}.zip"

echo "Building Newera Plugin v${VERSION}..."

# Clean previous build
rm -rf "$BUILD_DIR"
rm -f "$OUTPUT_FILE"

# Create build directory
mkdir -p "$BUILD_DIR/$PLUGIN_NAME"

# Copy plugin files (excluding dev files)
echo "Copying plugin files..."
cp -r includes "$BUILD_DIR/$PLUGIN_NAME/"
cp -r modules "$BUILD_DIR/$PLUGIN_NAME/"
cp -r templates "$BUILD_DIR/$PLUGIN_NAME/"
cp -r database "$BUILD_DIR/$PLUGIN_NAME/"
cp -r vendor "$BUILD_DIR/$PLUGIN_NAME/"
cp newera.php "$BUILD_DIR/$PLUGIN_NAME/"
cp README.md "$BUILD_DIR/$PLUGIN_NAME/"
cp LICENSE "$BUILD_DIR/$PLUGIN_NAME/"

# Copy built assets to proper location
mkdir -p "$BUILD_DIR/$PLUGIN_NAME/assets/css"
mkdir -p "$BUILD_DIR/$PLUGIN_NAME/assets/js"

# Copy built assets
if [ -d "dist/css" ]; then
    cp dist/css/*.css "$BUILD_DIR/$PLUGIN_NAME/assets/css/" 2>/dev/null || true
fi

if [ -d "dist/js" ]; then
    cp dist/js/*.js "$BUILD_DIR/$PLUGIN_NAME/assets/js/" 2>/dev/null || true
fi

# Also copy source assets as fallback
cp includes/Assets/css/*.css "$BUILD_DIR/$PLUGIN_NAME/assets/css/" 2>/dev/null || true
cp includes/Assets/js/*.js "$BUILD_DIR/$PLUGIN_NAME/assets/js/" 2>/dev/null || true

# Create the zip file
echo "Creating zip package..."
cd "$BUILD_DIR"
zip -r "$OUTPUT_FILE" "$PLUGIN_NAME" -x "*.DS_Store" -x "*.git*" -x "*node_modules*" -x "*.log"

# Move to project root
mv "$OUTPUT_FILE" "/home/runner/work/NewEra/NewEra/"

echo ""
echo "Build complete!"
echo "Package: /home/runner/work/NewEra/NewEra/$OUTPUT_FILE"
echo ""

# Cleanup
rm -rf "$BUILD_DIR"

# Show package info
echo "Package contents:"
unzip -l "/home/runner/work/NewEra/NewEra/$OUTPUT_FILE" | head -30
echo "..."
