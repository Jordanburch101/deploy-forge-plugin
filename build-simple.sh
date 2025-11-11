#!/bin/bash

# Simple WordPress Plugin Build Script
# Creates a clean ZIP for upload to WordPress

PLUGIN_SLUG="deploy-forge"
VERSION=$(grep "Version:" ${PLUGIN_SLUG}/${PLUGIN_SLUG}.php | awk '{print $3}')
ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"

echo "🔨 Building WordPress Plugin..."
echo "Plugin: ${PLUGIN_SLUG}"
echo "Version: ${VERSION}"
echo ""

# Clean previous build
rm -rf dist
mkdir -p dist

# Create ZIP (exclude development files)
zip -r dist/${ZIP_NAME} ${PLUGIN_SLUG} \
    -x "${PLUGIN_SLUG}/.git/*" \
    -x "${PLUGIN_SLUG}/.github/*" \
    -x "${PLUGIN_SLUG}/node_modules/*" \
    -x "${PLUGIN_SLUG}/.DS_Store" \
    -x "${PLUGIN_SLUG}/*.md" \
    -x "${PLUGIN_SLUG}/lint-check.sh" \
    -x "${PLUGIN_SLUG}/composer.*" \
    -x "${PLUGIN_SLUG}/package*.json" \
    -x "*TESTING-GUIDE.md" \
    -x "*OAUTH-*.md" \
    -x "*MULTI-SITE-*.md" \
    -x "*LINT-*.md" \
    -x "*CODE-QUALITY-*.md" \
    -x "*REPO-SELECTOR-SUMMARY.md"

# Keep README.md and REPO-SELECTOR-GUIDE.md for users

SIZE=$(du -h dist/${ZIP_NAME} | awk '{print $1}')

echo "✅ Build complete!"
echo ""
echo "📦 Package: dist/${ZIP_NAME}"
echo "📏 Size: ${SIZE}"
echo ""
echo "🚀 Ready to upload to WordPress!"
