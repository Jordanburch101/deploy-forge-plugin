# Workflow Dropdown Feature - Implementation Summary

**Date:** 2025-11-07
**Feature:** Dynamic workflow selection during repository onboarding

## Overview

Added ability to fetch and select GitHub Actions workflows from a dropdown during the repository setup process, instead of manually typing the workflow filename.

## Changes Made

### 1. Backend - GitHub API Method (`class-github-api.php`)

**New Method:** `get_workflows(string $repo_owner, string $repo_name): array`

**Security Features:**
- Input validation with regex patterns (`/^[a-zA-Z0-9_-]+$/` for owner, `/^[a-zA-Z0-9_.-]+$/` for repo)
- Sanitizes all output using `sanitize_text_field()` and `sanitize_file_name()`
- Filters to only show **active** workflows
- Only includes `.yml` and `.yaml` files
- Returns sanitized workflow data: name, filename, path, state

**Location:** Lines 90-154

### 2. Backend - AJAX Handler (`class-admin-pages.php`)

**Updated Method:** `ajax_get_workflows()`

**Security Features:**
- Nonce verification (`check_ajax_referer`)
- Capability check (`current_user_can('manage_options')`)
- Input sanitization (`sanitize_text_field()`)
- Regex validation matching GitHub API method
- Returns errors for invalid input formats

**Location:** Lines 419-456

### 3. Frontend - Settings Page UI (`templates/settings-page.php`)

**Added Elements:**
- Workflow dropdown (`<select id="github_workflow_dropdown">`)
- Manual text input (existing `github_workflow_name` field)
- "Load Workflows" button
- Loading spinner
- Workflow count display
- Error message display

**Behavior:**
- Dropdown hidden by default (manual entry shown)
- Button appears when repository is selected
- Dropdown shows after workflows loaded
- Option to switch back to manual entry via "✏️ Or enter manually..." option

**Location:** Lines 185-210

### 4. Frontend - JavaScript (`admin-scripts.js`)

**New Methods:**

1. **`loadWorkflows(e)`** (Lines 667-786)
   - Fetches workflows via AJAX
   - Validates repository data client-side
   - Populates dropdown with workflow options
   - Handles name attribute toggling (dropdown vs input)
   - Shows helpful error messages

2. **`onWorkflowSelect()`** (Lines 792-806)
   - Handles dropdown selection changes
   - Switches to manual entry when requested
   - Syncs values between dropdown and text input

**Event Bindings:**
- Line 24: `$("#load-workflows-btn").on("click", this.loadWorkflows.bind(this))`
- Line 25: `$("#github_workflow_dropdown").on("change", this.onWorkflowSelect.bind(this))`

**Updated:**
- `onRepoSelectChange()` - Shows/hides workflow button when repo selected (Lines 597-610)

## User Flow

### During Initial Setup:

1. User connects to GitHub App
2. User selects a repository from dropdown
3. **"Load Workflows" button appears**
4. User clicks button → AJAX fetches workflows
5. Dropdown populates with available workflows
6. User selects workflow from list OR clicks "Or enter manually..."
7. User saves settings

### After Repository Bound:

- Manual entry remains available
- User can still type workflow filename directly
- No breaking changes to existing functionality

## Security Considerations

**✅ Input Validation:**
- Regex validation on both client and server
- Prevents injection attacks via malformed repo names

**✅ Output Sanitization:**
- All workflow data sanitized before display
- Uses WordPress sanitization functions

**✅ Access Control:**
- Nonce verification on AJAX requests
- Admin capability checks (`manage_options`)

**✅ No Additional Permissions:**
- Uses existing GitHub App installation token
- No new scopes or permissions required
- Backend proxy already validates repo access

**✅ Rate Limiting:**
- Existing backend rate limiting applies (60 req/min)
- Client-side prevents duplicate requests

## Backend Compatibility

### Vercel Proxy (`/api/github/proxy`)

**Issue Fixed:** Removed `/repos` from restricted installation endpoints list

**Before:** Line 199 included `/repos` which incorrectly flagged ALL repository endpoints
**After:** Only truly installation-level endpoints flagged:
- `/installation/repositories`
- `/user/repos`
- `/user/installations`

**Impact:** Workflow dispatch endpoints no longer show misleading "SECURITY" warnings

## Default Workflow Name

**Changed:** Default workflow filename from `build-theme.yml` → `deploy-theme.yml`

**Files Updated:**
- `class-settings.php` - Line 30 (defaults)
- `class-settings.php` - Line 65 (sanitization fallback)
- `class-admin-pages.php` - Line 174 (form processing fallback)

## Testing Checklist

- [ ] Load workflows button appears after selecting repo
- [ ] Workflows populate correctly when button clicked
- [ ] Dropdown shows workflow name + filename
- [ ] Selecting workflow updates form value
- [ ] "Or enter manually" option switches to text input
- [ ] Form submission uses correct workflow value
- [ ] Errors shown for invalid repo formats
- [ ] Errors shown when no workflows found
- [ ] Works with both during setup AND after repo bound
- [ ] Manual entry still works without using dropdown

## Browser Compatibility

- Modern browsers (ES6+ JavaScript)
- jQuery required (bundled with WordPress admin)
- No external dependencies

## Performance

- Workflows fetched on-demand only
- No automatic fetching on page load
- Single AJAX request per workflow load
- Minimal DOM manipulation

## Future Enhancements (Out of Scope)

- ❌ Auto-load workflows when repo selected
- ❌ Cache workflow list client-side
- ❌ Filter workflows by `workflow_dispatch` trigger (GitHub API doesn't expose triggers)
- ❌ Show workflow descriptions or last run status
- ❌ Multiple workflow selection

## Notes

- Workflow filename validation happens server-side during deployment
- Plugin will return 404 if selected workflow doesn't have `workflow_dispatch` trigger
- User can still manually enter any filename - dropdown is convenience, not enforcement
