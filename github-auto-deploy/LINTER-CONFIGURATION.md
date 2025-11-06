# Linter Configuration Guide

## PHP Built-in Classes False Positives

The plugin uses several PHP built-in classes that some linters don't recognize by default:

- `ZipArchive` - from PHP's `zip` extension
- `RecursiveIteratorIterator` - from PHP's SPL (Standard PHP Library)
- `RecursiveDirectoryIterator` - from PHP's SPL
- `FilesystemIterator` - from PHP's SPL

These classes are available in any standard PHP installation and the code includes proper runtime checks (e.g., `class_exists('ZipArchive')`).

## Solution Options

### Option 1: Configure Intelephense (Recommended for VS Code/Cursor)

If you're using Intelephense (the default PHP linter in VS Code/Cursor), add this to your workspace settings:

**`.vscode/settings.json` or Cursor settings:**

```json
{
  "intelephense.stubs": [
    "Core",
    "SPL",
    "standard",
    "zip",
    "wordpress"
  ]
}
```

Or use the provided `.intelephense.json` file in the project root.

### Option 2: Configure PHPStan

If you're using PHPStan, create or update `phpstan.neon`:

```neon
parameters:
    level: 5
    paths:
        - github-auto-deploy
    bootstrapFiles:
        - github-auto-deploy/.stubs/php-builtin.php
    ignoreErrors:
        - '#Undefined type (ZipArchive|RecursiveIteratorIterator|RecursiveDirectoryIterator)#'
```

### Option 3: Use the Stub File

A stub file has been created at `github-auto-deploy/.stubs/php-builtin.php` which defines these classes for static analysis. Configure your linter to include this file.

### Option 4: Ignore These Specific Errors

If you cannot configure your linter, you can safely ignore these specific "Undefined type" errors. The code:

1. ✅ Has runtime checks with `class_exists()`
2. ✅ Has proper error handling
3. ✅ Includes `@var` type hints
4. ✅ Includes `@noinspection` comments
5. ✅ Will work correctly on any standard PHP installation

## Verification

These classes are standard PHP and can be verified:

```bash
php -r "echo class_exists('ZipArchive') ? 'ZipArchive: OK' : 'ZipArchive: Missing'; echo PHP_EOL;"
php -r "echo class_exists('RecursiveIteratorIterator') ? 'RecursiveIteratorIterator: OK' : 'Missing'; echo PHP_EOL;"
```

## Files Modified

The following files include type hints and suppression comments to help linters:

- `includes/class-deployment-manager.php` - All instances include `@var` hints and `@noinspection` comments
- `includes/class-github-api.php` - Fixed undefined constant issue
- `includes/class-github-app-connector.php` - Fixed undefined constant issue  
- `includes/class-settings.php` - Fixed undefined constant issue

## Status

✅ **The code is correct and production-ready**  
⚠️ **Linter warnings are false positives** - configuration issue, not code issue


