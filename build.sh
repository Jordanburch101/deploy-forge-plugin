#!/bin/bash

#####################################################################
# WordPress Plugin Build Script
# Creates a production-ready ZIP file for deployment
#####################################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PLUGIN_SLUG="github-auto-deploy"
VERSION=$(grep "Version:" ${PLUGIN_SLUG}/${PLUGIN_SLUG}.php | awk '{print $3}')
BUILD_DIR="build"
DIST_DIR="dist"
PLUGIN_DIR="${PLUGIN_SLUG}"

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}  WordPress Plugin Build Script${NC}"
echo -e "${BLUE}  Plugin: ${PLUGIN_SLUG}${NC}"
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
PHP_FILES=$(find ${PLUGIN_DIR} -name "*.php")
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

# Step 3: Copy plugin files
echo -e "${YELLOW}→${NC} Copying plugin files to build directory..."
cp -r ${PLUGIN_DIR} ${BUILD_DIR}/
echo -e "${GREEN}✓${NC} Files copied"
echo ""

# Step 4: Remove development files
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
rm -f LINT-REPORT.md
rm -f CODE-QUALITY-SUMMARY.md
rm -f lint-check.sh
rm -f TESTING-GUIDE.md
rm -f OAUTH-IMPLEMENTATION-PLAN.md
rm -f MULTI-SITE-ARCHITECTURE.md
rm -f REPO-SELECTOR-SUMMARY.md

# Optional: Keep or remove these
# rm -f REPO-SELECTOR-GUIDE.md  # User guide - keep for now
# rm -f README.md                # Keep for documentation

cd ../../

echo -e "${GREEN}✓${NC} Development files removed"
echo ""

# Step 5: Create ZIP file
echo -e "${YELLOW}→${NC} Creating ZIP archive..."

ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
cd ${BUILD_DIR}

# Create ZIP with no .DS_Store files
zip -r "../${DIST_DIR}/${ZIP_NAME}" ${PLUGIN_SLUG} -x "*.DS_Store" -x "__MACOSX" > /dev/null

cd ..

ZIP_SIZE=$(du -h ${DIST_DIR}/${ZIP_NAME} | awk '{print $1}')
echo -e "${GREEN}✓${NC} ZIP created: ${ZIP_NAME} (${ZIP_SIZE})"
echo ""

# Step 6: Generate checksums
echo -e "${YELLOW}→${NC} Generating checksums..."
cd ${DIST_DIR}

# MD5
MD5_HASH=$(md5 -q ${ZIP_NAME} 2>/dev/null || md5sum ${ZIP_NAME} 2>/dev/null | awk '{print $1}')
echo "$MD5_HASH  ${ZIP_NAME}" > ${ZIP_NAME}.md5

# SHA256
SHA256_HASH=$(shasum -a 256 ${ZIP_NAME} | awk '{print $1}')
echo "$SHA256_HASH  ${ZIP_NAME}" > ${ZIP_NAME}.sha256

cd ..

echo -e "${GREEN}✓${NC} Checksums generated"
echo "  MD5:    $MD5_HASH"
echo "  SHA256: $SHA256_HASH"
echo ""

# Step 7: Display file contents
echo -e "${YELLOW}→${NC} Archive contents:"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
unzip -l ${DIST_DIR}/${ZIP_NAME} | head -30
echo "  ..."
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# Step 8: File count
FILE_COUNT=$(unzip -l ${DIST_DIR}/${ZIP_NAME} | tail -1 | awk '{print $2}')
echo -e "${GREEN}✓${NC} Total files in archive: ${FILE_COUNT}"
echo ""

# Step 9: Clean up build directory (optional)
echo -e "${YELLOW}→${NC} Cleaning up temporary files..."
rm -rf ${BUILD_DIR}
echo -e "${GREEN}✓${NC} Build directory cleaned"
echo ""

# Success summary
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}  ✓ Build complete!${NC}"
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
echo -e "${YELLOW}Alternative (via SSH):${NC}"
echo "  scp ${DIST_DIR}/${ZIP_NAME} user@server:/tmp/"
echo "  ssh user@server"
echo "  cd /path/to/wordpress/wp-content/plugins/"
echo "  unzip /tmp/${ZIP_NAME}"
echo ""
echo -e "${GREEN}Happy Deploying! 🚀${NC}"
echo ""
