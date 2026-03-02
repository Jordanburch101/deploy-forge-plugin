#!/bin/bash

#####################################################################
# Deploy Forge - Local R2 Release Test
# Simulates the CI release workflow locally using .env credentials.
#
# Usage:
#   ./test-r2-release.sh          # Build, upload ZIP + manifest
#   ./test-r2-release.sh --dry    # Build + generate manifest only (no upload)
#   ./test-r2-release.sh --verify # Just check if manifest is accessible
#####################################################################

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

DRY_RUN=false
VERIFY_ONLY=false

for arg in "$@"; do
    case $arg in
        --dry) DRY_RUN=true ;;
        --verify) VERIFY_ONLY=true ;;
    esac
done

# ── Load .env ────────────────────────────────────────────────────

if [ ! -f .env ]; then
    echo -e "${RED}✗ .env file not found${NC}"
    echo "  Copy .env.example to .env and fill in your R2 credentials"
    exit 1
fi

set -a
source .env
set +a

# Validate required vars
REQUIRED_VARS=(CLOUDFLARE_R2_ACCESS_KEY_ID CLOUDFLARE_R2_SECRET_ACCESS_KEY CLOUDFLARE_R2_ENDPOINT CLOUDFLARE_R2_BUCKET CLOUDFLARE_R2_PUBLIC_URL)
for var in "${REQUIRED_VARS[@]}"; do
    if [ -z "${!var}" ]; then
        echo -e "${RED}✗ Missing required env var: ${var}${NC}"
        exit 1
    fi
done

# Set AWS env vars for the aws CLI
export AWS_ACCESS_KEY_ID="${CLOUDFLARE_R2_ACCESS_KEY_ID}"
export AWS_SECRET_ACCESS_KEY="${CLOUDFLARE_R2_SECRET_ACCESS_KEY}"

# ── Extract version ─────────────────────────────────────────────

VERSION=$(grep "Version:" deploy-forge/deploy-forge.php | grep -oE '[0-9]+\.[0-9]+\.[0-9]+')
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}  R2 Release Test — v${VERSION}${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# ── Verify only mode ─────────────────────────────────────────────

if $VERIFY_ONLY; then
    echo -e "${YELLOW}→${NC} Checking manifest at ${CLOUDFLARE_R2_PUBLIC_URL}/manifest.json..."
    echo ""

    RESPONSE=$(curl -s -w "\n%{http_code}" "${CLOUDFLARE_R2_PUBLIC_URL}/manifest.json")
    HTTP_CODE=$(echo "$RESPONSE" | tail -1)
    BODY=$(echo "$RESPONSE" | sed '$d')

    if [ "$HTTP_CODE" = "200" ]; then
        echo -e "${GREEN}✓${NC} Manifest accessible (HTTP ${HTTP_CODE})"
        echo ""
        echo "$BODY" | jq .
        echo ""

        MANIFEST_VERSION=$(echo "$BODY" | jq -r '.version')
        MANIFEST_URL=$(echo "$BODY" | jq -r '.download_url')

        echo -e "${BLUE}Version:${NC}      ${MANIFEST_VERSION}"
        echo -e "${BLUE}Download URL:${NC} ${MANIFEST_URL}"
        echo ""

        # Test download URL
        echo -e "${YELLOW}→${NC} Checking ZIP is accessible..."
        ZIP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "${MANIFEST_URL}")
        if [ "$ZIP_CODE" = "200" ]; then
            echo -e "${GREEN}✓${NC} ZIP accessible (HTTP ${ZIP_CODE})"
        else
            echo -e "${RED}✗${NC} ZIP returned HTTP ${ZIP_CODE}"
        fi
    else
        echo -e "${RED}✗${NC} Manifest returned HTTP ${HTTP_CODE}"
        echo "$BODY"
    fi

    exit 0
fi

# ── Step 1: Build ZIP ───────────────────────────────────────────

echo -e "${YELLOW}→${NC} Building plugin ZIP..."
bash build.sh
echo ""

ZIP_FILE="dist/deploy-forge-${VERSION}.zip"
SHA256_FILE="dist/deploy-forge-${VERSION}.zip.sha256"

if [ ! -f "$ZIP_FILE" ]; then
    echo -e "${RED}✗ ZIP not found: ${ZIP_FILE}${NC}"
    exit 1
fi

if [ ! -f "$SHA256_FILE" ]; then
    echo -e "${RED}✗ SHA256 file not found: ${SHA256_FILE}${NC}"
    exit 1
fi

SHA256_HASH=$(awk '{print $1}' "$SHA256_FILE")

# ── Step 2: Generate manifest.json ──────────────────────────────

echo -e "${YELLOW}→${NC} Generating manifest.json..."

PUBLISHED_AT=$(date -u +"%Y-%m-%dT%H:%M:%SZ")

# Extract changelog from CHANGELOG.md
CHANGELOG=""
if [ -f "CHANGELOG.md" ]; then
    CHANGELOG=$(sed -n "/## \[${VERSION}\]/,/## \[/p" CHANGELOG.md | sed '$ d' 2>/dev/null || echo "")
fi
if [ -z "$CHANGELOG" ]; then
    CHANGELOG="Release version ${VERSION}"
fi

cat > /tmp/deploy-forge-manifest.json << EOF
{
  "name": "Deploy Forge",
  "slug": "deploy-forge",
  "version": "${VERSION}",
  "download_url": "${CLOUDFLARE_R2_PUBLIC_URL}/deploy-forge-${VERSION}.zip",
  "requires": "5.8",
  "requires_php": "8.0",
  "tested": "6.9",
  "changelog": $(echo "${CHANGELOG}" | jq -Rs .),
  "published_at": "${PUBLISHED_AT}",
  "checksum_sha256": "${SHA256_HASH}"
}
EOF

echo -e "${GREEN}✓${NC} manifest.json generated:"
echo ""
cat /tmp/deploy-forge-manifest.json | jq .
echo ""

# ── Step 3: Upload to R2 ────────────────────────────────────────

if $DRY_RUN; then
    echo -e "${YELLOW}⚠ DRY RUN — skipping upload${NC}"
    echo ""
    echo "Would upload:"
    echo "  ${ZIP_FILE} → s3://${CLOUDFLARE_R2_BUCKET}/deploy-forge-${VERSION}.zip"
    echo "  manifest.json → s3://${CLOUDFLARE_R2_BUCKET}/manifest.json"
    echo ""
    echo "Run without --dry to actually upload."
    exit 0
fi

echo -e "${YELLOW}→${NC} Uploading ZIP to R2..."
aws s3 cp "${ZIP_FILE}" "s3://${CLOUDFLARE_R2_BUCKET}/deploy-forge-${VERSION}.zip" \
    --endpoint-url "${CLOUDFLARE_R2_ENDPOINT}" \
    --content-type "application/zip"
echo -e "${GREEN}✓${NC} ZIP uploaded"
echo ""

echo -e "${YELLOW}→${NC} Uploading manifest.json to R2..."
aws s3 cp /tmp/deploy-forge-manifest.json "s3://${CLOUDFLARE_R2_BUCKET}/manifest.json" \
    --endpoint-url "${CLOUDFLARE_R2_ENDPOINT}" \
    --content-type "application/json" \
    --cache-control "public, max-age=300"
echo -e "${GREEN}✓${NC} manifest.json uploaded"
echo ""

# ── Step 4: Verify ──────────────────────────────────────────────

echo -e "${YELLOW}→${NC} Verifying manifest is accessible..."
sleep 2

RESPONSE=$(curl -s -w "\n%{http_code}" "${CLOUDFLARE_R2_PUBLIC_URL}/manifest.json")
HTTP_CODE=$(echo "$RESPONSE" | tail -1)
BODY=$(echo "$RESPONSE" | sed '$d')

if [ "$HTTP_CODE" = "200" ]; then
    REMOTE_VERSION=$(echo "$BODY" | jq -r '.version')
    if [ "$REMOTE_VERSION" = "$VERSION" ]; then
        echo -e "${GREEN}✓${NC} Manifest verified — version ${REMOTE_VERSION}"
    else
        echo -e "${RED}✗${NC} Version mismatch: expected ${VERSION}, got ${REMOTE_VERSION}"
        exit 1
    fi
else
    echo -e "${RED}✗${NC} Manifest returned HTTP ${HTTP_CODE}"
    echo "$BODY"
    exit 1
fi

echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}  R2 Release Test Complete!${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "${BLUE}Manifest:${NC}  ${CLOUDFLARE_R2_PUBLIC_URL}/manifest.json"
echo -e "${BLUE}ZIP:${NC}       ${CLOUDFLARE_R2_PUBLIC_URL}/deploy-forge-${VERSION}.zip"
echo -e "${BLUE}Version:${NC}   ${VERSION}"
echo -e "${BLUE}SHA256:${NC}    ${SHA256_HASH}"
echo ""
