# Fix: "The link you followed has expired" Error

## Problem

When you click "Save Settings" in the GitHub Deploy settings page, you see:

```
The link you followed has expired.

This message was triggered by github-auto-deploy.

Call stack:
wp_die()
wp_nonce_ays()
check_admin_referer()
GitHub_Deploy_Admin_Pages->render_settings_page()
do_action('github-deploy-page_github-deploy-settings')
```

## Why This Happens

WordPress uses **nonces** (numbers used once) for security. These expire after:
- **12 hours** if you're logged in for less than 2 days
- **24 hours** if you're logged in longer

If you:
1. Open the settings page
2. Leave it open for several hours
3. Try to save

The nonce has expired and WordPress blocks the save for security.

## Quick Fix (Immediate)

### Option 1: Refresh the Page
1. **Refresh the page** (F5 or Cmd+R)
2. Re-enter your changes
3. Click "Save Settings" immediately

### Option 2: Open in New Tab
1. Right-click "Settings" in the menu
2. Choose "Open in New Tab"
3. The new page will have a fresh nonce
4. Make your changes and save

## Permanent Solutions

### Solution 1: Updated Plugin (Recommended)

I've just fixed this! The updated plugin now:

✅ **Better error handling** - Shows friendly message instead of wp_die()
✅ **Auto-warning** - Warns you after 1 hour if page is still open
✅ **Graceful failure** - Form still works, just shows error message

**To get the fix:**
```bash
# Rebuild the plugin
./build-simple.sh

# Re-upload to WordPress
# Upload dist/github-auto-deploy-1.0.0.zip
```

### Solution 2: Increase Nonce Lifetime (Advanced)

Add to your `wp-config.php` (before "That's all, stop editing!"):

```php
// Extend nonce lifetime to 24 hours
define('NONCE_LIFE', 86400); // 24 hours in seconds
```

**Default:** 86400 (24 hours)
**Options:**
- `43200` = 12 hours
- `86400` = 24 hours (default)
- `172800` = 48 hours

**Note:** Longer nonces = slightly less secure (but fine for admin areas)

### Solution 3: Save Frequently

- Don't leave settings page open for hours
- Save as soon as you make changes
- If interrupted, refresh before saving

## What Changed in the Fix

### Before (Old Code)
```php
if (isset($_POST['github_deploy_save_settings'])) {
    check_admin_referer('github_deploy_settings'); // Dies on failure
    // Save settings...
}
```

**Problem:** `check_admin_referer()` calls `wp_die()` on failure - no way to recover.

### After (New Code)
```php
if (isset($_POST['github_deploy_save_settings'])) {
    if (!wp_verify_nonce($_POST['_wpnonce'], 'github_deploy_settings')) {
        // Show error message instead of dying
        echo '<div class="notice notice-error"><p>Security verification failed. Please try again.</p></div>';
    } else {
        // Save settings...
        echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
    }
}
```

**Better:** Shows error message, user can refresh and try again.

### Plus: JavaScript Warning

After 1 hour, shows warning:
```
Notice: This page has been open for a while.
Please refresh the page before saving to avoid security errors.
[refresh link]
```

## Testing the Fix

1. **Upload updated plugin**
2. **Go to Settings page**
3. **Wait 1 hour** (or change `WARNING_TIME` in code to 60000 for 1 minute test)
4. **See warning appear**
5. **Click refresh link**
6. **Save works!**

## Prevention Tips

### For Users
✅ Save changes immediately
✅ Don't leave settings page open for hours
✅ Refresh page if you were away
✅ Use browser auto-fill for faster form completion

### For Developers
✅ Use `wp_verify_nonce()` instead of `check_admin_referer()`
✅ Add JavaScript to warn about stale pages
✅ Show user-friendly error messages
✅ Consider AJAX for settings (nonce refreshes on each request)

## Still Having Issues?

### Check WordPress Session
```bash
# Via WP-CLI
wp option get session_tokens

# Check if you're still logged in
wp user get $(wp user list --field=ID --role=administrator | head -1)
```

### Check Nonce Lifetime
```php
// Add to theme's functions.php temporarily
add_action('admin_footer', function() {
    echo '<!-- Nonce lifetime: ' . (NONCE_LIFE ?? 86400) . ' seconds -->';
});
```

### Clear Browser Cache
Sometimes cached forms have old nonces:
1. Clear browser cache
2. Hard refresh (Ctrl+Shift+R or Cmd+Shift+R)
3. Try again

### Check Server Time
If server time is wrong, nonces expire incorrectly:
```bash
# SSH to server
date

# Should match current time
```

## WordPress Nonce Documentation

- **Codex:** https://developer.wordpress.org/plugins/security/nonces/
- **Function:** `wp_create_nonce()`, `wp_verify_nonce()`
- **Expiration:** Based on `NONCE_LIFE` constant

## Summary

**Problem:** Nonce expired after leaving page open too long
**Quick Fix:** Refresh page before saving
**Permanent Fix:** Update plugin (includes better error handling + warning)
**Prevention:** Save immediately, don't leave page open

---

**Fixed in:** Plugin version 1.0.1 (updated code)
**Files changed:** `admin/class-admin-pages.php`, `admin/js/admin-scripts.js`
