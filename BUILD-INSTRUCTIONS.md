# Build Instructions

## Quick Build (Recommended)

### Option 1: Simple Script

```bash
./build-simple.sh
```

This creates `dist/github-auto-deploy-1.0.0.zip` ready for upload.

### Option 2: Full Build Script

```bash
./build.sh
```

This includes PHP validation, checksums, and detailed output.

### Option 3: Manual ZIP

```bash
zip -r github-auto-deploy.zip github-auto-deploy \
    -x "*.git*" \
    -x "*node_modules*" \
    -x "*.DS_Store"
```

---

## What Gets Included

### ✅ Production Files
- `github-auto-deploy.php` (main plugin file)
- `includes/` (all PHP classes)
- `admin/` (admin interface files)
- `templates/` (page templates)
- `README.md` (user documentation)
- `REPO-SELECTOR-GUIDE.md` (feature guide)

### ❌ Excluded (Development Only)
- `.git/` directory
- `.github/` workflows
- `node_modules/`
- Development markdown files (TESTING-GUIDE, LINT-REPORT, etc.)
- Build scripts
- Composer/npm files

---

## Upload to WordPress

### Method 1: WordPress Admin (Easiest)

1. **Build the plugin:**
   ```bash
   ./build-simple.sh
   ```

2. **Go to WordPress Admin:**
   - Navigate to `Plugins → Add New`
   - Click "Upload Plugin"
   - Choose `dist/github-auto-deploy-1.0.0.zip`
   - Click "Install Now"
   - Click "Activate Plugin"

3. **Done!** Go to `GitHub Deploy → Settings` to configure.

### Method 2: FTP/SFTP

1. **Build the plugin:**
   ```bash
   ./build-simple.sh
   ```

2. **Unzip locally:**
   ```bash
   unzip dist/github-auto-deploy-1.0.0.zip
   ```

3. **Upload via FTP:**
   - Connect to your server
   - Navigate to `wp-content/plugins/`
   - Upload the `github-auto-deploy` folder

4. **Activate in WordPress Admin**

### Method 3: SSH/Command Line

1. **Build and upload:**
   ```bash
   ./build-simple.sh
   scp dist/github-auto-deploy-1.0.0.zip user@yourserver.com:/tmp/
   ```

2. **SSH to server and install:**
   ```bash
   ssh user@yourserver.com
   cd /path/to/wordpress/wp-content/plugins/
   unzip /tmp/github-auto-deploy-1.0.0.zip
   rm /tmp/github-auto-deploy-1.0.0.zip
   ```

3. **Activate via WP-CLI:**
   ```bash
   wp plugin activate github-auto-deploy
   ```

### Method 4: Direct Copy (Development)

If you're developing locally and deploying to a local WordPress:

```bash
# Copy plugin directly to WordPress
cp -r github-auto-deploy /path/to/wordpress/wp-content/plugins/

# Or create a symlink
ln -s $(pwd)/github-auto-deploy /path/to/wordpress/wp-content/plugins/github-auto-deploy
```

---

## Build Output

After running `./build-simple.sh`, you'll see:

```
🔨 Building WordPress Plugin...
Plugin: github-auto-deploy
Version: 1.0.0

✅ Build complete!

📦 Package: dist/github-auto-deploy-1.0.0.zip
📏 Size: 45K

🚀 Ready to upload to WordPress!
```

The ZIP file will be in the `dist/` directory.

---

## Version Management

### Update Version Number

Edit `github-auto-deploy/github-auto-deploy.php`:

```php
/**
 * Plugin Name: GitHub Auto-Deploy for WordPress
 * Version: 1.0.0  ← Change this
 */
```

The build script automatically reads this version number.

### Create Release

1. Update version in main plugin file
2. Run build script:
   ```bash
   ./build.sh
   ```
3. ZIP file will be: `dist/github-auto-deploy-X.X.X.zip`
4. Upload to your server or GitHub releases

---

## Troubleshooting

### "Permission denied" when running script

```bash
chmod +x build-simple.sh
./build-simple.sh
```

### "Command not found: zip"

**macOS/Linux:** Install zip utility
```bash
# macOS
brew install zip

# Ubuntu/Debian
sudo apt-get install zip

# CentOS/RHEL
sudo yum install zip
```

**Windows:** Use WSL or Git Bash, or install 7-Zip

### PHP Syntax Errors

Run the full build script which includes validation:
```bash
./build.sh
```

Or manually check:
```bash
find github-auto-deploy -name "*.php" -exec php -l {} \;
```

### ZIP file too large

Check what's included:
```bash
unzip -l dist/github-auto-deploy-1.0.0.zip
```

Make sure `node_modules/` and `.git/` are excluded.

---

## File Size Guidelines

**Expected size:** 40-60 KB

**If larger than 100 KB, check for:**
- `node_modules/` directory (should be excluded)
- `.git/` directory (should be excluded)
- Large documentation files
- Vendor dependencies

---

## Deployment Checklist

Before deploying to production:

- [ ] Update version number
- [ ] Run full build: `./build.sh`
- [ ] Check PHP syntax (included in build)
- [ ] Test ZIP on staging site first
- [ ] Verify all files included
- [ ] Check file size is reasonable
- [ ] Backup current plugin before updating
- [ ] Upload and activate
- [ ] Test basic functionality
- [ ] Configure GitHub settings

---

## Quick Reference

```bash
# Build plugin
./build-simple.sh

# Upload to server
scp dist/github-auto-deploy-1.0.0.zip user@server:/tmp/

# Install via SSH
ssh user@server
cd /path/to/wordpress/wp-content/plugins/
unzip /tmp/github-auto-deploy-1.0.0.zip
wp plugin activate github-auto-deploy

# Done!
```

---

## Need Help?

- Check main [README.md](github-auto-deploy/README.md) for plugin usage
- See [TESTING-GUIDE.md](github-auto-deploy/TESTING-GUIDE.md) for testing
- Review [REPO-SELECTOR-GUIDE.md](github-auto-deploy/REPO-SELECTOR-GUIDE.md) for repository selector

---

**Happy Deploying!** 🚀
