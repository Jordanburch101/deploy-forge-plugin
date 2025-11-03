# GitHub PAT Permissions Fix

## Problem
The plugin gets a **401 Unauthorized** error when trying to download artifacts from GitHub's Azure storage.

## Root Cause
Your GitHub Personal Access Token (PAT) needs specific permissions to download artifacts from private repositories.

## Solution: Update PAT Permissions

### 1. Go to GitHub Token Settings
https://github.com/settings/tokens

### 2. Find your existing token OR create a new one

### 3. Ensure these permissions are selected:

**Required Permissions:**
- ✅ `repo` (Full control of private repositories)
  - This includes repo:status, repo_deployment, public_repo, repo:invite, security_events
- ✅ `workflow` (Update GitHub Action workflows)
- ✅ `actions:read` (Read GitHub Actions artifacts and workflow runs)

**Important:** The key permission for downloading artifacts is implicit in the `repo` scope, but GitHub's artifact download API requires OAuth, not just a PAT.

## Alternative: Use GitHub's Download Artifact Action Pattern

Since GitHub artifacts use Azure Blob Storage with OAuth tokens, we need to use GitHub's API differently.

### The Real Fix: Use the Artifact Download URL with proper authentication

GitHub's artifact download endpoint requires:
1. A redirect to Azure Blob Storage
2. Following that redirect WITHOUT the Authorization header
3. Or using GitHub's artifact download token mechanism

Let me update the plugin to handle this properly.
