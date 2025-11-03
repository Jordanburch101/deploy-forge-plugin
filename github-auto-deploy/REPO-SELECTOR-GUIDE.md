# Repository Selector Feature Guide

## Overview

The GitHub Repository Selector makes it easy to connect your WordPress site to a GitHub repository without manually typing owner/repo names. Simply enter your Personal Access Token, click a button, and select from a dropdown!

## ✨ Features

- **Visual Repository Selection** - See all your accessible repos in a dropdown
- **Automatic Workflow Detection** - Discovers GitHub Actions workflows automatically
- **Auto-Fill Form Fields** - Selecting a repo fills in all the details
- **Smart Icons** - 🔒 for private repos, 📖 for public, ⚙️ for repos with workflows
- **Manual Override** - Can still enter details manually if needed

## 🚀 How to Use

### Step 1: Enter Your GitHub Token

1. Go to **GitHub Deploy → Settings**
2. Scroll to "Personal Access Token"
3. Enter your GitHub PAT (with `repo` and `workflow` scopes)
4. Click **Save Settings**

### Step 2: Load Your Repositories

1. After saving, the **"Select Repository"** section appears
2. Click the **"Load Repositories"** button
3. Wait a few seconds while it fetches your repos
4. The dropdown populates with all your accessible repositories

### Step 3: Select a Repository

1. Click the **"Select Repository"** dropdown
2. Choose your theme repository from the list
3. Icons help you identify:
   - 🔒 = Private repository
   - 📖 = Public repository
   - ⚙️ = Has GitHub Actions workflows

4. **Auto-magic happens!** The following fields auto-fill:
   - Repository Owner
   - Repository Name
   - Branch (uses default branch)

### Step 4: Select a Workflow

1. After selecting a repo, the **"Select Workflow"** dropdown appears
2. It shows all GitHub Actions workflows in that repo
3. Choose the workflow you want to use for deployments
4. The **"Workflow File Name"** field auto-fills

### Step 5: Complete Setup

1. Enter **Target Theme Directory** (your theme folder name)
2. Configure **Webhook Settings** (optional)
3. Set **Deployment Options**
4. Click **"Save Settings"**

Done! 🎉

## 💡 Pro Tips

###Refresh Repository List

- Click "Load Repositories" again to refresh the list
- Cached for 5 minutes to avoid rate limits

### Manual Entry Still Available

- Don't want to use the selector? No problem!
- Click **"Or enter repository details manually"**
- Enter owner/repo/branch/workflow manually

### Workflow Icons

- ✓ = Active workflow (ready to use)
- ⚠️ = Inactive workflow (may need attention)

### Private Repos

- Ensure your PAT has `repo` scope for private repositories
- Public repos only need `public_repo` scope

## 🔧 Troubleshooting

### "Error loading repositories"

**Causes:**
- Invalid or expired GitHub token
- Token doesn't have correct scopes
- Network connection issue
- GitHub API rate limit reached

**Solutions:**
1. Verify your token is correct
2. Check token scopes: `repo` + `workflow`
3. Wait a few minutes and try again
4. Check browser console for errors

### "No workflows found"

**Causes:**
- Repository doesn't have `.github/workflows/` directory
- Workflows exist but aren't properly formatted
- Token doesn't have `actions` scope

**Solutions:**
1. Add a GitHub Actions workflow to your repo
2. Use manual entry and type workflow filename
3. Check your token permissions

### Dropdown is empty

**Causes:**
- No repositories accessible with current token
- Account has no repos
- Token has wrong permissions

**Solutions:**
1. Create a test repository in GitHub
2. Ensure token has `repo` scope
3. Try loading repos again

## 📊 Technical Details

### API Endpoints Used

- `GET /user/repos` - Fetches user's repositories
- `GET /repos/{owner}/{repo}/actions/workflows` - Fetches workflows

### Caching

- **Repositories:** Cached for 5 minutes
- **Workflows:** Cached for 10 minutes
- Click "Load Repositories" to bypass cache

### Rate Limits

- GitHub API: 5,000 requests/hour (authenticated)
- Loading repos uses 1 request
- Loading workflows uses 1 request per repo selection

### Data Stored

Nothing additional is stored. When you select a repo/workflow, it just fills the existing form fields. Saving works the same as manual entry.

## 🎯 Comparison: Selector vs. Manual

| Feature | Repo Selector | Manual Entry |
|---------|--------------|--------------|
| **Speed** | 30 seconds | 5 minutes |
| **Typos** | Impossible | Common |
| **Workflow Discovery** | Automatic | Must know filename |
| **Branch Detection** | Uses default | Must know/guess |
| **UX** | Modern | Traditional |
| **Setup** | Need PAT first | Works anytime |

## 🔐 Security

- Token never leaves your server
- Requests use WordPress AJAX (nonces required)
- Only admins can access (`manage_options` capability)
- Cached data is site-specific
- Token encryption unchanged (sodium)

## 🆕 What's New

**v1.1 Features:**
- Visual repository selector
- Automatic workflow detection
- Smart auto-fill functionality
- Collapsible manual entry section

**Still Supported:**
- Manual entry (as before)
- All existing functionality
- No breaking changes

## 📝 Example Workflow

```
1. User enters PAT → Saves
2. Clicks "Load Repositories"
3. Sees dropdown with:
   📖 username/public-theme ⚙️
   🔒 company/private-theme ⚙️
   📖 test/demo-site
4. Selects "company/private-theme"
5. Fields auto-fill:
   - Owner: company
   - Name: private-theme
   - Branch: main
6. Workflow dropdown shows:
   ✓ Build and Deploy (build-theme.yml)
   ✓ Run Tests (test.yml)
7. Selects "Build and Deploy"
8. Workflow field fills: build-theme.yml
9. Adds theme directory: my-theme
10. Saves → Done!
```

## 🚦 Status Indicators

**Repository List:**
- Loading... = Fetching from GitHub
- Error loading = Check token/connection
- X repositories loaded = Success!

**Workflow List:**
- Loading workflows... = Fetching
- No workflows found = Repo has none
- ✓ Active = Workflow ready
- ⚠️ Inactive = Workflow exists but disabled

## Need Help?

- Check [README.md](README.md) for general setup
- See [TESTING-GUIDE.md](TESTING-GUIDE.md) for testing
- Report issues on GitHub

---

**Happy Deploying!** 🚀
