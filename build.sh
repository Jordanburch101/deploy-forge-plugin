#!/bin/bash

#####################################################################
# Deploy Forge - Unified Build Script
# Creates a production-ready ZIP file for WordPress installation
#####################################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PLUGIN_SLUG="deploy-forge"
MAIN_FILE="${PLUGIN_SLUG}/${PLUGIN_SLUG}.php"
VERSION=$(grep "Version:" ${MAIN_FILE} | grep -oE '[0-9]+\.[0-9]+\.[0-9]+')
BUILD_DIR="build"
DIST_DIR="dist"

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}  Deploy Forge - Build Script${NC}"
echo -e "${BLUE}  Version: ${VERSION}${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# Step 1: Clean previous builds
echo -e "${YELLOW}→${NC} Cleaning previous builds..."
rm -rf ${BUILD_DIR}
rm -rf ${DIST_DIR}
mkdir -p ${BUILD_DIR}
mkdir -p ${DIST_DIR}
echo -e "${GREEN}✓${NC} Build directories cleaned"
echo ""

# Step 2: Run PHP syntax check
echo -e "${YELLOW}→${NC} Running PHP syntax check..."
PHP_FILES=$(find ${PLUGIN_SLUG} -name "*.php")
PHP_ERRORS=0

for file in $PHP_FILES; do
    if ! php -l "$file" > /dev/null 2>&1; then
        echo -e "${RED}✗${NC} Syntax error in $file"
        PHP_ERRORS=$((PHP_ERRORS + 1))
    fi
done

if [ $PHP_ERRORS -gt 0 ]; then
    echo -e "${RED}✗${NC} Found $PHP_ERRORS PHP syntax errors"
    exit 1
fi
echo -e "${GREEN}✓${NC} All PHP files validated"
echo ""

# Step 3: Verify vendor-prefixed directory exists
echo -e "${YELLOW}→${NC} Checking vendor-prefixed directory..."
if [ ! -d "${PLUGIN_SLUG}/vendor-prefixed" ]; then
    echo -e "${YELLOW}!${NC} vendor-prefixed/ not found — running composer scope..."
    if command -v composer &> /dev/null; then
        composer scope
    else
        echo -e "${RED}✗${NC} Composer not available. Run 'composer scope' manually first."
        exit 1
    fi
fi

if [ ! -f "${PLUGIN_SLUG}/vendor-prefixed/vendor/autoload.php" ]; then
    echo -e "${RED}✗${NC} vendor-prefixed/vendor/autoload.php missing. Run 'composer scope' to generate it."
    exit 1
fi
echo -e "${GREEN}✓${NC} vendor-prefixed directory verified"
echo ""

# Step 4: Copy plugin folder to build directory
echo -e "${YELLOW}→${NC} Copying plugin files to build directory..."
cp -r ${PLUGIN_SLUG} ${BUILD_DIR}/
echo -e "${GREEN}✓${NC} Files copied"
echo ""

# Step 5: Remove development files
echo -e "${YELLOW}→${NC} Removing development files..."

cd ${BUILD_DIR}/${PLUGIN_SLUG}

# Remove development/documentation files
rm -f .DS_Store
rm -f .gitignore
rm -f .editorconfig
rm -f phpcs.xml
rm -f composer.json
rm -f composer.lock
rm -f package.json
rm -f package-lock.json
rm -rf node_modules
rm -rf .git
rm -rf .github
rm -rf .vscode
rm -f LINT-REPORT.md
rm -f CODE-QUALITY-SUMMARY.md
rm -f lint-check.sh
rm -f TESTING-GUIDE.md

# Remove any log files
find . -name "*.log" -delete
find . -name ".gitkeep" -delete

cd ../../

echo -e "${GREEN}✓${NC} Development files removed"
echo ""

# Step 6: Create ZIP file
echo -e "${YELLOW}→${NC} Creating ZIP archive..."

ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
cd ${BUILD_DIR}

# Create ZIP with no .DS_Store files
zip -rq "../${DIST_DIR}/${ZIP_NAME}" ${PLUGIN_SLUG} -x "*.DS_Store" -x "__MACOSX"

cd ..

ZIP_SIZE=$(du -h ${DIST_DIR}/${ZIP_NAME} | awk '{print $1}')
echo -e "${GREEN}✓${NC} ZIP created: ${ZIP_NAME} (${ZIP_SIZE})"
echo ""

# Step 7: Generate checksums
echo -e "${YELLOW}→${NC} Generating checksums..."
cd ${DIST_DIR}

# MD5 - handle both macOS and Linux
if command -v md5sum &> /dev/null; then
    md5sum "${ZIP_NAME}" > "${ZIP_NAME}.md5"
    MD5_HASH=$(md5sum "${ZIP_NAME}" | awk '{print $1}')
else
    MD5_HASH=$(md5 -q "${ZIP_NAME}")
    echo "${MD5_HASH}  ${ZIP_NAME}" > "${ZIP_NAME}.md5"
fi

# SHA256 - handle both macOS and Linux
if command -v sha256sum &> /dev/null; then
    sha256sum "${ZIP_NAME}" > "${ZIP_NAME}.sha256"
    SHA256_HASH=$(sha256sum "${ZIP_NAME}" | awk '{print $1}')
else
    SHA256_HASH=$(shasum -a 256 "${ZIP_NAME}" | awk '{print $1}')
    echo "${SHA256_HASH}  ${ZIP_NAME}" > "${ZIP_NAME}.sha256"
fi

cd ..

echo -e "${GREEN}✓${NC} Checksums generated"
echo "  MD5:    $MD5_HASH"
echo "  SHA256: $SHA256_HASH"
echo ""

# Step 8: Display file contents
echo -e "${YELLOW}→${NC} Archive contents:"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
unzip -l ${DIST_DIR}/${ZIP_NAME} | head -30
echo "  ..."
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# Step 9: File count
FILE_COUNT=$(unzip -l ${DIST_DIR}/${ZIP_NAME} | tail -1 | awk '{print $2}')
echo -e "${GREEN}✓${NC} Total files in archive: ${FILE_COUNT}"
echo ""

# Step 10: Clean up build directory
echo -e "${YELLOW}→${NC} Cleaning up temporary files..."
rm -rf ${BUILD_DIR}
echo -e "${GREEN}✓${NC} Build directory cleaned"
echo ""

# Success summary
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}  Build complete!${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "${BLUE}Package:${NC}     ${DIST_DIR}/${ZIP_NAME}"
echo -e "${BLUE}Size:${NC}        ${ZIP_SIZE}"
echo -e "${BLUE}MD5:${NC}         ${MD5_HASH}"
echo -e "${BLUE}SHA256:${NC}      ${SHA256_HASH}"
echo ""
echo -e "${YELLOW}Installation:${NC}"
echo "  1. Upload ${ZIP_NAME} to your WordPress site"
echo "  2. Go to Plugins → Add New → Upload Plugin"
echo "  3. Choose the ZIP file and click 'Install Now'"
echo "  4. Activate the plugin"
echo ""
