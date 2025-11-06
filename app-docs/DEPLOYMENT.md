# Quick Deployment Guide

## Build and Deploy in 3 Steps

### Step 1: Build the Plugin

```bash
./build-simple.sh
```

Output:
```
✅ Build complete!
📦 Package: dist/github-auto-deploy-1.0.0.zip
📏 Size: 32K
```

### Step 2: Upload to WordPress

**Option A: WordPress Admin (Easiest)**

1. Go to your WordPress site
2. Navigate to `Plugins → Add New → Upload Plugin`
3. Click "Choose File" and select `dist/github-auto-deploy-1.0.0.zip`
4. Click "Install Now"
5. Click "Activate"

**Option B: SSH/Command Line**

```bash
# Upload
scp dist/github-auto-deploy-1.0.0.zip user@yourserver.com:/tmp/

# SSH and install
ssh user@yourserver.com
cd /path/to/wordpress/wp-content/plugins/
unzip /tmp/github-auto-deploy-1.0.0.zip

# Activate
wp plugin activate github-auto-deploy
```

### Step 3: Configure

1. Go to `GitHub Deploy → Settings`
2. Enter your GitHub Personal Access Token
3. Click "Load Repositories"
4. Select your repository
5. Select your workflow
6. Set target theme directory
7. Click "Save Settings"

Done! 🎉

---

## Package Contents

The ZIP file includes:

```
github-auto-deploy/
├── github-auto-deploy.php       # Main plugin file
├── includes/                     # Core classes
│   ├── class-database.php
│   ├── class-github-api.php
│   ├── class-deployment-manager.php
│   ├── class-webhook-handler.php
│   └── class-settings.php
├── admin/                        # Admin interface
│   ├── class-admin-pages.php
│   ├── css/admin-styles.css
│   └── js/admin-scripts.js
├── templates/                    # Page templates
│   ├── dashboard-page.php
│   ├── settings-page.php
│   └── history-page.php
└── .github-workflow-example.yml  # Example workflow
```

**Total:** 19 files, ~32 KB

---

## Requirements

- WordPress 5.8+
- PHP 7.4+
- HTTPS (for webhooks)
- GitHub account with repository

---

## After Installation

1. **Configure Settings**
   - GitHub Deploy → Settings
   - Enter GitHub PAT
   - Select repository
   - Configure deployment options

2. **Setup GitHub Webhook** (Optional)
   - Copy webhook URL from settings
   - Add to GitHub repository
   - Enable auto-deploy

3. **Test Manual Deployment**
   - Go to Dashboard
   - Click "Deploy Now"
   - Verify it works

See [README.md](github-auto-deploy/README.md) for complete documentation.

---

## Build Scripts

- `build-simple.sh` - Quick build (recommended)
- `build.sh` - Full build with validation and checksums
- `BUILD-INSTRUCTIONS.md` - Detailed build documentation

---

**Ready to Deploy!** 🚀
