# GitHub Personal Access Token Setup

## Minimum Required Permissions

When creating your GitHub Personal Access Token, you need **exactly 2 scopes**:

### ✅ Required Scopes

#### 1. `repo` (Full control of private repositories)
**Why:** Allows the plugin to:
- Read repository information
- Trigger GitHub Actions workflows
- Download build artifacts
- Access private repositories (if applicable)

**Includes sub-scopes:**
- `repo:status` - Access commit status
- `repo_deployment` - Access deployment status
- `public_repo` - Access public repositories
- `repo:invite` - Access repository invitations

#### 2. `workflow` (Update GitHub Action workflows)
**Why:** Allows the plugin to:
- Trigger workflow runs via `workflow_dispatch`
- Check workflow status
- Access workflow run information

---

## Step-by-Step Token Creation

### 1. Go to GitHub Settings

Visit: https://github.com/settings/tokens/new

Or navigate manually:
1. Click your profile picture (top-right)
2. Settings
3. Developer settings (bottom of left sidebar)
4. Personal access tokens → Tokens (classic)
5. Generate new token → Generate new token (classic)

### 2. Configure Token

**Note/Description:**
```
WordPress GitHub Deploy - [Your Site Name]
```

**Expiration:**
- Choose: `No expiration` (recommended for automation)
- Or: `90 days` (more secure, but requires renewal)

**Select scopes:**

```
✅ repo
   ✅ repo:status
   ✅ repo_deployment
   ✅ public_repo
   ✅ repo:invite
   ✅ security_events

✅ workflow
```

**DO NOT SELECT:**
- ❌ `admin:repo_hook` (not needed)
- ❌ `admin:org` (not needed)
- ❌ `delete_repo` (dangerous!)
- ❌ `user` scopes (not needed)
- ❌ `gist` (not needed)
- ❌ `notifications` (not needed)

### 3. Generate and Copy

1. Scroll to bottom
2. Click **"Generate token"**
3. **COPY THE TOKEN IMMEDIATELY** (you won't see it again!)
4. Store it safely (password manager recommended)

---

## Token Permissions Breakdown

### What the plugin CAN do with these permissions:

✅ **Read repository info** (name, owner, branches)
✅ **Trigger GitHub Actions workflows** (via workflow_dispatch)
✅ **Check workflow run status** (is build complete?)
✅ **Download build artifacts** (the compiled theme ZIP)
✅ **List workflows** (for the repo selector feature)
✅ **Access private repositories** (if you own/collaborate on them)

### What the plugin CANNOT do:

❌ Modify your code
❌ Delete repositories
❌ Change repository settings
❌ Add/remove collaborators
❌ Access other GitHub accounts
❌ Modify webhook settings (done manually)

---

## Public Repositories Only?

If you're **only deploying from public repositories**, you can use less permissions:

### Minimum for Public Repos Only:

```
✅ public_repo (Access public repositories)
✅ workflow (Update GitHub Action workflows)
```

However, using full `repo` scope is recommended because:
- Future-proof (if you switch to private repos)
- Repo selector works better
- No functional difference for public repos

---

## Token Security

### ✅ Best Practices

**DO:**
- ✅ Use a descriptive note ("WordPress Deploy - Production Site")
- ✅ Store token in a password manager
- ✅ Use HTTPS for your WordPress site
- ✅ Set expiration if possible (renew periodically)
- ✅ Revoke old tokens you're not using

**DON'T:**
- ❌ Share your token with anyone
- ❌ Commit token to Git
- ❌ Send via email/Slack
- ❌ Use same token for multiple unrelated sites
- ❌ Give more permissions than needed

### Token Storage in WordPress

The plugin stores your token:
- ✅ **Encrypted** using PHP's `sodium_crypto_secretbox()`
- ✅ **Database only** (not in files)
- ✅ **Admin-only access** (`manage_options` capability)
- ✅ **Never exposed** to frontend or logs

---

## Using the Token

### In WordPress Admin

1. Go to **GitHub Deploy → Settings**
2. Scroll to **"Personal Access Token"**
3. Paste your token
4. Click **"Save Settings"**
5. Click **"Test Connection"** to verify

The token is now saved (encrypted) and ready to use!

---

## Multiple Sites / Environments

### Option 1: One Token for All Sites (Recommended)

Use the **same token** on:
- Development site
- Staging site
- Production site

**Advantages:**
- ✅ Easy to manage
- ✅ One token to revoke if compromised
- ✅ Consistent permissions

### Option 2: Different Tokens per Environment

Create separate tokens:
- `WordPress Deploy - Dev`
- `WordPress Deploy - Staging`
- `WordPress Deploy - Production`

**Advantages:**
- ✅ Can revoke one without affecting others
- ✅ Better audit trail
- ✅ Environment isolation

**Disadvantage:**
- ⚠️ More tokens to manage

---

## Token Expiration

### If You Set Expiration

GitHub will email you **7 days before expiration**.

**To renew:**
1. Go to https://github.com/settings/tokens
2. Click **"Regenerate token"** on the expired token
3. Keep same scopes
4. Copy new token
5. Update in WordPress settings
6. Save

**Note:** Old token stops working immediately when you regenerate.

---

## Troubleshooting

### "Bad credentials" or "Unauthorized"

**Causes:**
- Token not entered correctly (copy/paste issue)
- Token expired
- Token revoked
- Wrong permissions

**Solution:**
1. Regenerate token with correct scopes
2. Copy carefully (no extra spaces)
3. Paste into WordPress
4. Test connection

### "Not Found" or "Repository not accessible"

**Causes:**
- Token doesn't have `repo` scope
- You're not a collaborator on the repository
- Repository is private but token only has `public_repo`

**Solution:**
- Ensure full `repo` scope is selected
- Check you have access to the repository in GitHub

### Rate Limit Errors

**Cause:**
- Making too many GitHub API requests

**Solution:**
- Plugin caches responses (5-10 minutes)
- Wait a few minutes before retrying
- Check rate limit: https://github.com/settings/tokens

**Limits:**
- **Authenticated:** 5,000 requests/hour
- **Unauthenticated:** 60 requests/hour

The plugin uses ~1-2 requests per deployment, so you're safe!

---

## FAQ

### Q: Can I use the same token on multiple sites?
**A:** Yes! Perfectly safe. The token gives access to YOUR repositories.

### Q: What if I lose the token?
**A:** Regenerate it in GitHub settings and update WordPress.

### Q: Can someone steal my token?
**A:** Only if they have admin access to WordPress or database. The token is encrypted in the database.

### Q: Do I need to create a new token for each repo?
**A:** No! One token works for all your repositories.

### Q: What happens if I revoke the token?
**A:** Deployments will fail. You'll need to create a new token and update WordPress.

### Q: Can I use a Fine-Grained token instead?
**A:** Classic tokens are recommended. Fine-grained tokens work but have more complex setup.

---

## Quick Reference

### Token Creation URL
```
https://github.com/settings/tokens/new
```

### Required Scopes (Minimum)
```
✅ repo
✅ workflow
```

### Optional (for public repos only)
```
✅ public_repo (instead of full repo)
✅ workflow
```

---

## Example Token Creation (Screenshots Flow)

1. **Name:** `WordPress Deploy - My Site`
2. **Expiration:** `No expiration`
3. **Scopes:**
   ```
   [✓] repo
       [✓] repo:status
       [✓] repo_deployment
       [✓] public_repo
       [✓] repo:invite
       [✓] security_events
   [✓] workflow
   ```
4. **Click:** Generate token
5. **Copy:** `ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`
6. **Store:** In password manager
7. **Use:** Paste into WordPress → GitHub Deploy → Settings

---

## Security Checklist

Before creating your token:

- [ ] Using HTTPS on WordPress site
- [ ] WordPress admin is secure (strong password)
- [ ] Will store token in password manager
- [ ] Understand what permissions do
- [ ] Know how to revoke if needed
- [ ] Site has recent backups
- [ ] Only giving minimum required permissions

---

## Need Help?

- **GitHub Token Docs:** https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/creating-a-personal-access-token
- **Plugin README:** See [README.md](github-auto-deploy/README.md)
- **Testing:** See [TESTING-GUIDE.md](github-auto-deploy/TESTING-GUIDE.md)

---

**Summary:** Just select `repo` + `workflow` and you're good to go! ✅
