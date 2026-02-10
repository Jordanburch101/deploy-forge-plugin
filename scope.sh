#!/bin/bash
#
# Scope Sentry SDK under DeployForge\Vendor namespace.
# Usage: composer scope
#
set -e

OUTPUT_DIR="deploy-forge/vendor-prefixed"

echo "→ Running PHP-Scoper..."
php-scoper add-prefix --output-dir="${OUTPUT_DIR}" --force

echo "→ Regenerating autoloader in scoped output..."
# Copy a minimal composer.json so dump-autoload can generate a classmap.
cat > "${OUTPUT_DIR}/composer.json" << 'COMPOSER_JSON'
{
    "autoload": {
        "classmap": ["sentry/", "guzzlehttp/", "psr/", "jean85/", "symfony/"],
        "files": ["sentry/sentry/src/functions.php"]
    }
}
COMPOSER_JSON

composer dump-autoload --working-dir="${OUTPUT_DIR}" --classmap-authoritative --quiet

# Copy Composer's InstalledVersions metadata (needed by jean85/pretty-package-versions).
cp vendor/composer/InstalledVersions.php "${OUTPUT_DIR}/vendor/composer/InstalledVersions.php"
cp vendor/composer/installed.php "${OUTPUT_DIR}/vendor/composer/installed.php"

echo "✓ Scoped vendor ready at ${OUTPUT_DIR}/"
