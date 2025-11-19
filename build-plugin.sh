#!/bin/bash
#
# Deploy Forge - Plugin Build Script
# Creates a production-ready ZIP file for WordPress installation
#

set -e

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}  Deploy Forge - Plugin Build Script${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# Configuration
PLUGIN_SLUG="deploy-forge"
VERSION="1.0.0"
BUILD_DIR="build"
DIST_DIR="dist"

# Get script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR"

echo -e "${YELLOW}→${NC} Cleaning previous builds..."
rm -rf "$BUILD_DIR"
rm -rf "$DIST_DIR"
mkdir -p "$BUILD_DIR/$PLUGIN_SLUG"
mkdir -p "$DIST_DIR"

echo -e "${YELLOW}→${NC} Copying plugin files..."

# Copy main plugin file
cp deploy-forge.php "$BUILD_DIR/$PLUGIN_SLUG/"

# Copy subdirectories (NOT the deploy-forge folder itself)
cp -r deploy-forge/includes "$BUILD_DIR/$PLUGIN_SLUG/"
cp -r deploy-forge/admin "$BUILD_DIR/$PLUGIN_SLUG/"
cp -r deploy-forge/templates "$BUILD_DIR/$PLUGIN_SLUG/" 2>/dev/null || true

# Copy documentation
if [ -f "README.md" ]; then
    cp README.md "$BUILD_DIR/$PLUGIN_SLUG/"
fi

if [ -d "spec" ]; then
    cp -r spec "$BUILD_DIR/$PLUGIN_SLUG/"
fi

echo -e "${YELLOW}→${NC} Removing development files..."

# Remove development/testing files from build
find "$BUILD_DIR" -type f -name ".DS_Store" -delete
find "$BUILD_DIR" -type f -name "*.log" -delete
find "$BUILD_DIR" -type f -name ".gitkeep" -delete
find "$BUILD_DIR" -type d -name ".git" -exec rm -rf {} + 2>/dev/null || true
find "$BUILD_DIR" -type d -name "node_modules" -exec rm -rf {} + 2>/dev/null || true
find "$BUILD_DIR" -type d -name ".vscode" -exec rm -rf {} + 2>/dev/null || true

# Remove spec directory from production build (optional)
# Uncomment the next line if you don't want specs in the production ZIP
# rm -rf "$BUILD_DIR/$PLUGIN_SLUG/spec"

echo -e "${YELLOW}→${NC} Creating ZIP archive..."

cd "$BUILD_DIR"
zip -r "../$DIST_DIR/${PLUGIN_SLUG}-${VERSION}.zip" "$PLUGIN_SLUG" -q

cd ..

# Get file size
FILE_SIZE=$(du -h "$DIST_DIR/${PLUGIN_SLUG}-${VERSION}.zip" | cut -f1)

echo ""
echo -e "${GREEN}✓${NC} Build completed successfully!"
echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}  Plugin ZIP:${NC} $DIST_DIR/${PLUGIN_SLUG}-${VERSION}.zip"
echo -e "${GREEN}  File Size:${NC} $FILE_SIZE"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "${YELLOW}Installation Instructions:${NC}"
echo "1. Upload the ZIP file to WordPress (Plugins → Add New → Upload Plugin)"
echo "2. Activate the plugin"
echo "3. Navigate to Deploy Forge → Settings"
echo "4. Connect to GitHub and configure your deployment"
echo ""
echo -e "${YELLOW}Testing the Async Deployment Feature:${NC}"
echo "1. Set up a repository with GitHub Actions workflow"
echo "2. Configure the webhook in your GitHub repository"
echo "3. Push a commit to trigger the workflow"
echo "4. Watch for the 'queued' status (webhook responds in <500ms)"
echo "5. Monitor the status transition: queued → deploying → success"
echo ""
