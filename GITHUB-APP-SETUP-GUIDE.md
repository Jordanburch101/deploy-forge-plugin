# GitHub App Setup Guide

This guide explains how to set up the GitHub App for WordPress GitHub Deploy plugin. The GitHub App provides a more user-friendly OAuth flow compared to Personal Access Tokens.

## Architecture Overview

The new authentication system consists of three components:

1. **Backend Service** (Vercel): Handles OAuth flow, stores installation tokens, proxies GitHub API requests
2. **GitHub App**: Registered on GitHub with required permissions
3. **WordPress Plugin**: Updated to use "Connect to GitHub" button

**Flow**: WordPress → Backend Service → GitHub App → User Installs → Callback → WordPress Connected

## Prerequisites

- A Vercel account (free tier works)
- A GitHub account
- Access to create GitHub Apps in your organization or personal account

## Step 1: Deploy Backend Service to Vercel

### 1.1 Install Vercel CLI

```bash
pnpm install -g vercel
```

### 1.2 Navigate to Backend Directory

```bash
cd github-wordpress-backend
```

### 1.3 Install Dependencies

```bash
pnpm install
```

### 1.4 Deploy to Vercel

```bash
vercel --prod
```

Follow the prompts:

- **Set up and deploy?** Yes
- **Which scope?** Select your account/organization
- **Link to existing project?** No (or Yes if you've already created one)
- **Project name?** github-wordpress-backend (or your preferred name)
- **Directory?** ./ (current directory)
- **Override settings?** No

After deployment, note the production URL (e.g., `https://deploy-forge.vercel.app`).

### 1.5 Add Vercel KV Storage

1. Go to [Vercel Dashboard](https://vercel.com/dashboard)
2. Select your project
3. Go to the **Storage** tab
4. Click **Create Database**
5. Select **KV** (Redis)
6. Name it (e.g., `github-wp-kv`)
7. Click **Create**
8. Go to **.env.local** tab and copy the KV connection variables
9. The KV variables will be automatically added to your project

## Step 2: Create GitHub App

### 2.1 Navigate to GitHub App Creation

- **For Personal Account**: https://github.com/settings/apps/new
- **For Organization**: https://github.com/organizations/YOUR_ORG/settings/apps/new

### 2.2 Fill in App Details

**General Information:**

- **GitHub App name**: `WordPress GitHub Deploy` (or your preferred name - must be unique)
- **Homepage URL**: Your backend URL (e.g., `https://deploy-forge.vercel.app`)
- **Description**: Automates WordPress theme deployments from GitHub repositories

**Callback URL:**

```
https://YOUR_BACKEND_URL.vercel.app/api/auth/callback
```

Replace `YOUR_BACKEND_URL` with your actual Vercel deployment URL.

**Setup URL:** Leave blank

**Webhook:**

- **Active**: ✅ Checked
- **Webhook URL**: `https://YOUR_BACKEND_URL.vercel.app/api/webhooks/github`
- **Webhook secret**: Generate a random secret (save this for later)
  ```bash
  openssl rand -hex 32
  ```

**Webhook events** - Subscribe to:

- ✅ Installation
- ✅ Installation repositories

**Permissions** - Set the following repository permissions:

- **Actions**: Read & Write
- **Contents**: Read-only
- **Metadata**: Read-only (automatically selected)
- **Webhooks**: Read & Write

**Where can this GitHub App be installed?**

- Select **Any account** (allows anyone to use it) OR
- Select **Only on this account** (restricts to your account/org)

### 2.3 Create the App

Click **Create GitHub App** button at the bottom.

### 2.4 After Creation - Gather Credentials

After creating the app, you'll be on the app's settings page. Gather the following:

1. **App ID**: Found at the top of the settings page
2. **Client ID**: Found in the "About" section
3. **Client Secret**: Click "Generate a new client secret" button, then copy it (you can only see it once!)
4. **Private Key**: Scroll down to "Private keys" section, click "Generate a private key" button, download the `.pem` file

## Step 3: Configure Backend Environment Variables

### 3.1 Add Environment Variables to Vercel

Go to your Vercel project dashboard, then **Settings** → **Environment Variables**.

Add the following variables for **Production**, **Preview**, and **Development**:

#### Required Variables:

```
GITHUB_APP_ID=123456
```

(Your App ID from step 2.4)

```
GITHUB_APP_CLIENT_ID=Iv1.abc123def456
```

(Your Client ID from step 2.4)

```
GITHUB_APP_CLIENT_SECRET=abc123def456ghi789
```

(Your Client Secret from step 2.4)

```
GITHUB_APP_PRIVATE_KEY
```

For the private key, open the `.pem` file you downloaded and copy the entire contents including the `-----BEGIN RSA PRIVATE KEY-----` and `-----END RSA PRIVATE KEY-----` lines. Paste it as-is (Vercel will handle the formatting).

**Important**: If you're setting this via CLI or manually, you need to escape newlines:

```
"-----BEGIN RSA PRIVATE KEY-----\nMIIE...\n-----END RSA PRIVATE KEY-----"
```

```
GITHUB_WEBHOOK_SECRET=your_webhook_secret_from_step_2.2
```

(The random secret you generated)

### 3.2 Redeploy Backend

After adding environment variables, redeploy:

```bash
vercel --prod
```

Or trigger a redeploy from the Vercel dashboard.

## Step 4: Update GitHub App URLs

Now that you have your final backend URL, update your GitHub App settings:

1. Go back to your GitHub App settings:

   - Personal: https://github.com/settings/apps/YOUR_APP_NAME
   - Org: https://github.com/organizations/YOUR_ORG/settings/apps/YOUR_APP_NAME

2. Update the following URLs with your actual Vercel URL:

   - **Homepage URL**: `https://YOUR_BACKEND_URL.vercel.app`
   - **Callback URL**: `https://YOUR_BACKEND_URL.vercel.app/api/auth/callback`
   - **Webhook URL**: `https://YOUR_BACKEND_URL.vercel.app/api/webhooks/github`

3. Click **Save changes**

## Step 5: Update Backend Code with GitHub App Slug

1. Open `/github-wordpress-backend/api/auth/connect.js`
2. Find line 56:
   ```javascript
   const githubInstallUrl = new URL(
     "https://github.com/apps/YOUR_APP_NAME/installations/new"
   );
   ```
3. Replace `YOUR_APP_NAME` with your GitHub App's slug (the name in lowercase with hyphens, visible in the URL when viewing your app)
4. Commit and redeploy:
   ```bash
   git add api/auth/connect.js
   git commit -m "Update GitHub App name"
   vercel --prod
   ```

## Step 6: Configure WordPress Plugin

### 6.1 Add Backend URL to WordPress

Add this line to your `wp-config.php`:

```php
define('GITHUB_DEPLOY_BACKEND_URL', 'https://YOUR_BACKEND_URL.vercel.app');
```

Replace `YOUR_BACKEND_URL` with your actual Vercel deployment URL.

### 6.2 Update/Reinstall Plugin

If you're updating from the PAT version:

1. The plugin will automatically work with the new GitHub App authentication
2. Existing PAT credentials will be ignored
3. Users need to click "Connect to GitHub" in the settings page

## Step 7: Test the Connection

1. Log in to your WordPress admin panel
2. Go to **GitHub Deploy** → **Settings**
3. Click **Connect to GitHub** button
4. You'll be redirected to GitHub
5. Click **Install** to install the app on your account/organization
6. Select which repository to connect (or select all repositories)
7. Click **Install**
8. You'll be redirected back to WordPress
9. The settings page should now show "Connected to GitHub" with your account and repository details

## Troubleshooting

### "Failed to connect to GitHub" Error

**Check:**

1. Backend environment variables are set correctly
2. GitHub App permissions are correct
3. Callback URL matches exactly (no trailing slash)
4. Backend is successfully deployed (visit /api/auth/connect in browser - should show error but not 404)

**Debug:**

```bash
# Check Vercel logs
vercel logs YOUR_PROJECT_NAME --prod
```

### "Invalid or expired state parameter"

**Causes:**

- OAuth state expired (>10 minutes between clicking Connect and completing GitHub install)
- Redis/KV storage not configured properly

**Fix:**

- Try connecting again (don't wait more than 10 minutes)
- Verify Vercel KV is attached to your project

### Webhook Not Receiving Events

**Check:**

1. Webhook URL is correct in GitHub App settings
2. Webhook secret matches environment variable
3. Webhook is marked as "Active"

**Test:**

1. Go to GitHub App settings → Advanced
2. Click "Recent Deliveries"
3. Check if requests are being sent and their responses

### Backend Responds with 500 Error

**Check Vercel logs:**

```bash
vercel logs --prod
```

**Common issues:**

- Private key not formatted correctly (check for escaped newlines)
- Missing environment variables
- KV storage not connected

## Local Development

### Setup Local Environment

1. Copy environment variables:

   ```bash
   cp .env.example .env
   ```

2. Add your GitHub App credentials to `.env`

3. Start Vercel dev server:

   ```bash
   vercel dev
   ```

4. Use ngrok for webhooks:

   ```bash
   ngrok http 3000
   ```

5. Update GitHub App webhook URL to ngrok URL temporarily

6. In WordPress `wp-config.php`, set:
   ```php
   define('GITHUB_DEPLOY_BACKEND_URL', 'https://YOUR_NGROK_URL.ngrok.io');
   ```

## Security Considerations

✅ **Good Practices:**

- Keep your GitHub App Client Secret and Private Key secure
- Never commit `.env` or `.pem` files to version control
- Use Vercel's environment variable encryption
- Webhook signatures are verified automatically
- Installation tokens expire after 1 hour (refreshed automatically)

⚠️ **Important:**

- The backend service has access to the repositories users install the app on
- Users can revoke access anytime from their GitHub settings
- Monitor Vercel logs for suspicious activity

## Next Steps

After successful setup:

1. **Test Deployments**: Try triggering a deployment from WordPress
2. **Configure Workflows**: Ensure your repository has the proper GitHub Actions workflow
3. **Set up Webhooks**: Configure automatic deployments on push
4. **Monitor**: Check Vercel logs and WordPress debug logs for any issues

## Getting Help

- **GitHub App Issues**: Check GitHub's documentation on Apps
- **Vercel Deployment Issues**: Check Vercel's documentation and support
- **WordPress Plugin Issues**: Check the plugin's debug logs (enable debug mode in settings)

## Maintenance

### Rotating Keys

If you need to rotate your GitHub App private key:

1. Generate new private key in GitHub App settings
2. Update `GITHUB_APP_PRIVATE_KEY` environment variable in Vercel
3. Redeploy backend
4. No action needed on WordPress side (uses API key that remains valid)

### Updating Backend

```bash
cd github-wordpress-backend
git pull origin main
vercel --prod
```

WordPress sites will automatically use the updated backend (no changes needed on their end).
