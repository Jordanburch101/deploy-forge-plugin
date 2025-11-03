# PHP Stubs

This directory contains PHP stub files for IDE intellisense and code completion.

## What are PHP Stubs?

PHP stubs are skeleton files that contain function and class signatures without actual implementation code. They help IDEs like VSCode (using Intelephense) understand functions that exist at runtime but aren't in your codebase.

## Files in this Directory

- **acf-pro-stubs.php** - Advanced Custom Fields Pro function definitions
  - Provides autocomplete for ACF functions like `get_field()`, `the_field()`, `update_field()`, etc.
  - Eliminates "undefined function" warnings for ACF functions

## How It Works

The `.vscode/settings.json` file is configured to tell Intelephense where to find these stubs:

```json
"intelephense.environment.includePaths": [
  "/path/to/wp-content/plugins"
]
```

When you use ACF functions in your theme files, Intelephense references these stub files to:
1. Provide autocomplete suggestions
2. Show function parameter hints
3. Eliminate false "undefined function" errors
4. Improve type checking and IntelliSense

## Usage

No action required - the stubs work automatically once VSCode is configured. Simply use ACF functions normally in your code:

```php
$value = get_field('field_name');
the_field('another_field');
```

## Adding More Stubs

To add stubs for other WordPress plugins or libraries:
1. Download or generate stub files for the plugin
2. Place them in this directory
3. Reload VSCode window (`Cmd+Shift+P` ’ "Reload Window")
