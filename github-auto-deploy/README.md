# GitHub Auto-Deploy for WordPress

Automates WordPress theme deployment from GitHub repositories via GitHub Actions webhooks. Push code to GitHub, and it automatically deploys to your WordPress site.

## Features

- ✅ **Automatic Deployments** - Deploy on commit via GitHub webhooks
- ✅ **GitHub Actions Integration** - Build themes with your existing workflow
- ✅ **Deployment History** - Track all deployments with logs
- ✅ **Automatic Backups** - Rollback to previous versions
- ✅ **Manual Approval Mode** - Control when deployments happen
- ✅ **Secure** - HMAC webhook validation and encrypted token storage
- ✅ **WordPress Admin UI** - Configure and monitor everything from your dashboard

## Requirements

- WordPress 5.8+
- PHP 7.4+
- HTTPS enabled (for webhooks)
- GitHub repository with Actions enabled
- GitHub Personal Access Token

## Installation

1. **Download the plugin**
   ```bash
   cd wp-content/plugins/
   git clone <repository-url> github-auto-deploy
   ```

2. **Activate the plugin**
   - Go to WordPress admin → Plugins
   - Activate "GitHub Auto-Deploy for WordPress"

3. **Configure settings**
   - Navigate to GitHub Deploy → Settings
   - Enter your repository details
   - Create and enter GitHub Personal Access Token

## GitHub Setup

### 1. Create Personal Access Token

1. Go to GitHub → Settings → Developer settings → Personal access tokens
2. Click "Generate new token (classic)"
3. Select scopes:
   - `repo` (Full control of private repositories)
   - `actions` (workflow access)
4. Copy the token

### 2. Create GitHub Actions Workflow

Create `.github/workflows/build-theme.yml` in your repository:

```yaml
name: Build Theme

on:
  workflow_dispatch:
  push:
    branches: [ main ]

jobs:
  build:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v3

      - name: Setup Node.js
        uses: actions/setup-node@v3
        with:
          node-version: '18'

      - name: Install dependencies
        run: npm install

      - name: Build theme
        run: npm run build

      - name: Upload artifact
        uses: actions/upload-artifact@v3
        with:
          name: theme-build
          path: |
            **/*
            !node_modules/**
            !.git/**
```

### 3. Configure Webhook (Optional)

For automatic deployments on push:

1. Go to your GitHub repository → Settings → Webhooks
2. Click "Add webhook"
3. **Payload URL**: Copy from WordPress Admin → GitHub Deploy → Settings
4. **Content type**: `application/json`
5. **Secret**: Copy from WordPress Admin → GitHub Deploy → Settings
6. **Events**: Select "Just the push event"
7. Click "Add webhook"

## Plugin Configuration

### Settings Page

Navigate to **GitHub Deploy → Settings**:

1. **Repository Settings**
   - Repository Owner: Your GitHub username/organization
   - Repository Name: Repository name
   - Branch: Branch to monitor (e.g., `main`)
   - Workflow File Name: Your workflow file (e.g., `build-theme.yml`)

2. **Authentication**
   - Personal Access Token: Paste your GitHub token

3. **Theme Settings**
   - Target Theme Directory: Theme folder name (e.g., `my-theme`)

4. **Deployment Options**
   - Auto Deploy: Enable automatic deployments on commit
   - Manual Approval: Require approval before deploying
   - Create Backups: Backup theme before each deployment

### Test Connection

After entering settings, click "Test Connection" to verify:
- GitHub token is valid
- Repository is accessible
- Permissions are correct

## Usage

### Manual Deployment

1. Go to **GitHub Deploy → Dashboard**
2. Click "Deploy Now"
3. Confirm deployment
4. Monitor progress in real-time

### Automatic Deployment

1. Enable "Auto Deploy" in settings
2. Configure webhook in GitHub
3. Push commit to configured branch
4. Plugin automatically:
   - Receives webhook
   - Triggers GitHub Actions
   - Downloads build artifact
   - Creates backup
   - Deploys to theme directory

### Rollback

1. Go to **GitHub Deploy → History**
2. Find successful deployment
3. Click "Rollback"
4. Confirm to restore previous version

## Workflow

```
Developer commits → GitHub webhook → WordPress Plugin
                                           ↓
                                    Trigger GitHub Actions
                                           ↓
                                    Wait for build completion
                                           ↓
                                    Download artifact
                                           ↓
                                    Create backup
                                           ↓
                                    Deploy to theme directory
                                           ↓
                                    Log deployment
```

## Troubleshooting

### Deployment Fails

1. Check deployment logs in History page
2. Verify GitHub Actions workflow completed successfully
3. Check file permissions on theme directory
4. Ensure artifact is created in workflow

### Webhook Not Working

1. Verify webhook URL is correct
2. Check webhook secret matches
3. Ensure site is accessible via HTTPS
4. Review webhook deliveries in GitHub

### Build Not Starting

1. Test connection in Settings
2. Verify workflow file exists in `.github/workflows/`
3. Check GitHub Actions permissions
4. Ensure workflow has `workflow_dispatch` trigger

### Permission Errors

1. Check theme directory permissions (755 for directories, 644 for files)
2. Verify WordPress can write to theme directory
3. Check PHP user/group permissions

## Security

- GitHub tokens encrypted using sodium cryptography
- Webhook signatures validated with HMAC SHA-256
- All inputs sanitized and validated
- CSRF protection on all forms
- WordPress nonces on AJAX requests
- Admin-only access (`manage_options` capability)

## Development

### File Structure

```
github-auto-deploy/
├── github-auto-deploy.php       # Main plugin file
├── includes/
│   ├── class-database.php        # Database operations
│   ├── class-github-api.php      # GitHub API wrapper
│   ├── class-deployment-manager.php  # Deployment logic
│   ├── class-webhook-handler.php # Webhook processing
│   └── class-settings.php        # Settings management
├── admin/
│   ├── class-admin-pages.php     # Admin interface
│   ├── css/admin-styles.css      # Admin styles
│   └── js/admin-scripts.js       # Admin JavaScript
└── templates/
    ├── dashboard-page.php        # Dashboard template
    ├── settings-page.php         # Settings template
    └── history-page.php          # History template
```

### Hooks & Filters

**Actions:**
- `github_deploy_completed` - Fires after successful deployment
- `github_deploy_rolled_back` - Fires after rollback
- `github_deploy_webhook_received` - Fires when webhook received
- `github_deploy_rate_limit_warning` - Fires when rate limit is low

## Changelog

### 1.0.0
- Initial release
- GitHub Actions integration
- Automatic and manual deployments
- Webhook support
- Backup and rollback functionality
- Admin interface

## Support

For issues and feature requests, please use the GitHub repository issue tracker.

## License

GPL v2 or later
