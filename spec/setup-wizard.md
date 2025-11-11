# Setup Wizard Specification

**Last Updated:** 2025-11-09
**Status:** Planned
**Priority:** High

## Overview

A modern, multi-step setup wizard that guides users through initial plugin configuration. The wizard provides a streamlined onboarding experience with a sleek UI, using Select2 for enhanced dropdowns and AJAX for seamless step transitions.

## Purpose

### Problem Statement

Currently, users must navigate through complex settings pages to configure the plugin. First-time users may:

- Feel overwhelmed by all options at once
- Miss critical configuration steps
- Not understand the optimal setup sequence
- Abandon setup before completing configuration

### Solution

A guided setup wizard that:

- Breaks configuration into logical, sequential steps
- Provides contextual help and explanations
- Validates each step before proceeding
- Creates a sense of progress and accomplishment
- Ensures all critical settings are configured

## When to Show Wizard

### Initial Setup Triggers

1. **First Activation:** Plugin activated for first time (no API key exists)
2. **Post-Reset:** User clicks "Reset All Plugin Data" in Danger Zone
3. **Manual Launch:** Admin clicks "Run Setup Wizard" button (added to settings page)

### Skip Conditions

- User has completed wizard before (stored in wp_options)
- User clicks "Skip Setup" during wizard
- User manually configures all required settings

## Wizard Steps

### Step 1: Welcome & Introduction

**Goal:** Orient user and explain the plugin

**UI Elements:**

- Large plugin logo/icon
- Welcome message
- Brief description of what the plugin does
- List of what will be configured:
  - ✓ Connect to GitHub
  - ✓ Select repository
  - ✓ Choose deployment method
  - ✓ Configure deployment options
- Estimated time: "5 minutes"

**Actions:**

- Primary button: "Let's Get Started" → Step 2
- Secondary link: "Skip Setup (Manual Configuration)"

**Validation:** None

---

### Step 2: Connect to GitHub

**Goal:** Establish GitHub App connection

**UI Elements:**

- Section title: "Connect Your GitHub Account"
- Explanation: "GitHub Deploy uses a secure GitHub App to access your repositories. Click below to install the app and authorize access."
- Connection status indicator
- Large "Connect to GitHub" button (primary, with GitHub icon)
- Help text: "You'll be redirected to GitHub to install the app, then automatically returned here."

**Flow:**

1. User clicks "Connect to GitHub"
2. Redirect to GitHub OAuth flow (existing functionality)
3. User installs app and selects repositories
4. Redirect back to wizard at Step 2
5. Show success message: "✓ Successfully connected to GitHub as @username"
6. Auto-advance to Step 3 after 1 second

**Actions:**

- Primary button: "Next" (enabled after connection)
- Secondary button: "Back" → Step 1

**Validation:**

- GitHub connection must be established (API key exists)
- Show error if connection fails with retry button

**AJAX:**

- Check connection status on page load
- Poll for connection after redirect

---

### Step 3: Select Repository

**Goal:** Choose which repository to deploy

**UI Elements:**

- Section title: "Select Your Theme Repository"
- Explanation: "Choose the GitHub repository that contains your WordPress theme."
- **Select2 dropdown** showing available repositories:
  - Format: "owner/repository-name" with repo icon
  - Search enabled
  - Show repository visibility (public/private badge)
  - Display repository description as subtitle
- Branch selector (shown after repo selected):
  - **Select2 dropdown** with branches for selected repo
  - Default: repository's default branch
  - Search enabled for repos with many branches
- Preview: Shows target deployment path
  - "Theme will be deployed to: `/wp-content/themes/repository-name/`"

**Flow:**

1. On page load, AJAX fetch user's repositories
2. Show loading spinner while fetching
3. Populate Select2 with repositories
4. On repository selection:
   - AJAX fetch branches for that repo
   - Populate branch Select2
   - Auto-select default branch
   - Show deployment path preview
5. Enable "Next" button when both selected

**Actions:**

- Primary button: "Next" (enabled after selections)
- Secondary button: "Back" → Step 2

**Validation:**

- Repository must be selected
- Branch must be selected
- Repository must be accessible (check via AJAX)

**AJAX Endpoints:**

- `github_deploy_wizard_get_repos` - Fetch repositories
- `github_deploy_wizard_get_branches` - Fetch branches for repo
- `github_deploy_wizard_validate_repo` - Verify access

**Select2 Configuration:**

```javascript
$("#repository-select").select2({
  placeholder: "Search repositories...",
  width: "100%",
  templateResult: formatRepo, // Custom template with icon
  templateSelection: formatRepoSelection,
  minimumInputLength: 0,
});
```

---

### Step 4: Choose Deployment Method

**Goal:** Select GitHub Actions or Direct Clone

**UI Elements:**

- Section title: "How Should Deployments Work?"
- Two large, visual option cards (radio button style):

**Option 1: GitHub Actions (Build + Deploy)**

- Icon: ⚙️ (gear/cog)
- Title: "GitHub Actions"
- Subtitle: "Build assets then deploy"
- Description: "Uses GitHub workflows to compile/build your theme (webpack, npm, SCSS) before deploying. Best for themes with build processes."
- Visual indicators:
  - "Recommended for most themes"
  - Time estimate: "2-5 minutes per deployment"
- Expanded details (when selected):
  - Workflow file selector (**Select2 dropdown**)
  - AJAX loads workflows from repo
  - Shows workflow name and last run status
  - Link to create workflow if none exist

**Option 2: Direct Clone (No Build)**

- Icon: ⚡ (lightning bolt)
- Title: "Direct Clone"
- Subtitle: "Deploy immediately"
- Description: "Downloads repository directly without building. Perfect for simple themes using plain CSS/JS with no build tools."
- Visual indicators:
  - "Fastest deployment"
  - Time estimate: "10-30 seconds per deployment"
- Expanded details (when selected):
  - Note: "No GitHub Actions workflow needed"
  - Checklist of what gets deployed

**Flow:**

1. User selects deployment method (card clicks are radio buttons)
2. Show expanded details for selected method
3. If GitHub Actions selected:
   - AJAX fetch available workflows
   - Populate Select2 with workflows
   - Show "Create Workflow" button if none exist
4. Enable "Next" when valid selection made

**Actions:**

- Primary button: "Next" (enabled after selection + validation)
- Secondary button: "Back" → Step 3

**Validation:**

- Deployment method must be selected
- If GitHub Actions: workflow must be selected OR acknowledged missing
- AJAX validate workflow exists (if selected)

**AJAX Endpoints:**

- `github_deploy_wizard_get_workflows` - Fetch workflows
- `github_deploy_wizard_validate_workflow` - Check workflow is valid

**Select2 Configuration:**

```javascript
$("#workflow-select").select2({
  placeholder: "Select a workflow...",
  width: "100%",
  templateResult: formatWorkflow, // Show workflow name + status
  minimumInputLength: 0,
});
```

---

### Step 5: Deployment Options

**Goal:** Configure deployment behavior

**UI Elements:**

- Section title: "Fine-Tune Your Deployment Settings"
- Toggle switches (modern iOS-style) for each option:

**Option 1: Automatic Deployments**

- Label: "Auto-deploy on commit"
- Description: "Automatically deploy when you push to GitHub"
- Default: ON
- Substep (if enabled):
  - Manual Approval toggle:
    - Label: "Require manual approval"
    - Description: "Create pending deployment, require dashboard approval before deploying"
    - Default: OFF

**Option 2: Create Backups**

- Label: "Backup before deploying"
- Description: "Automatically backup current theme before each deployment (recommended)"
- Default: ON

**Option 3: Webhook Setup**

- Label: "Enable GitHub webhooks"
- Description: "Receive instant notifications when you push to GitHub"
- Default: ON
- Expanded details (if enabled):
  - Webhook URL display (read-only)
  - Copy button with feedback
  - Auto-generated webhook secret (read-only)
  - Copy button with feedback
  - Link to "How to configure GitHub webhook" documentation

**Flow:**

1. Display all options with defaults
2. Toggle switches have smooth animation
3. Webhook section expands/collapses smoothly
4. All changes saved in real-time (no need to wait for "Next")

**Actions:**

- Primary button: "Next" → Step 6
- Secondary button: "Back" → Step 4

**Validation:**

- None required (all have defaults)

**AJAX:**

- `github_deploy_wizard_generate_secret` - Generate webhook secret if enabled

---

### Step 6: Review & Complete

**Goal:** Review configuration and finish setup

**UI Elements:**

- Section title: "Review Your Configuration"
- Summary cards showing all configured settings:

**GitHub Connection**

- ✓ Connected as @username
- Account avatar
- Edit link → Step 2

**Repository**

- owner/repository-name
- Branch: main
- Edit link → Step 3

**Deployment Method**

- GitHub Actions (Build + Deploy) OR Direct Clone
- Workflow: deploy-theme.yml (if applicable)
- Edit link → Step 4

**Settings**

- ✓ Auto-deploy enabled
- ✓ Manual approval required (if applicable)
- ✓ Backups enabled
- ✓ Webhooks configured (if applicable)
- Edit link → Step 5

**Next Steps**

- Checklist of what user should do next:
  - [ ] Configure GitHub webhook (if enabled, with link)
  - [ ] Create GitHub Actions workflow (if needed, with link)
  - [ ] Test deployment from dashboard
  - [ ] Review deployment history

**Actions:**

- Primary button: "Complete Setup" → Dashboard with success message
- Secondary button: "Back" → Step 5

**Validation:**

- All settings must be valid
- Show warning if webhook not configured but enabled

**AJAX:**

- `github_deploy_wizard_complete` - Save settings and mark wizard as completed

---

## UI/UX Design Specifications

### Visual Design

**Layout:**

- Full-screen wizard overlay (modal-like)
- Centered content container: max-width 900px
- White background with subtle shadow
- Responsive: adapts to tablet/mobile

**Progress Indicator:**

- Horizontal stepper at top
- Shows all 6 steps with icons
- Current step highlighted (blue)
- Completed steps show checkmark (green)
- Future steps grayed out
- Step numbers: 1/6, 2/6, etc.

**Color Scheme:**

- Primary: #0073aa (WordPress blue)
- Success: #46b450 (green)
- Warning: #ffb900 (yellow)
- Error: #dc3232 (red)
- Neutral: #f0f0f1 (gray background)
- Text: #1e1e1e (dark gray)

**Typography:**

- Headings: System font stack (same as WordPress)
- Body: 16px line-height 1.6
- Step titles: 28px bold
- Descriptions: 14px regular

**Spacing:**

- Consistent 24px grid system
- Generous white space between sections
- 16px padding on all cards

### Animations

**Transitions:**

- Step transitions: Slide animation (300ms ease-in-out)
- Expand/collapse: Height animation (200ms ease)
- Button states: Opacity/transform (150ms)
- Loading states: Fade in (200ms)

**Loading Indicators:**

- Spinner for AJAX calls
- Skeleton screens for Select2 loading
- Progress bar for file operations

**Micro-interactions:**

- Toggle switches slide smoothly
- Buttons scale slightly on hover (1.02x)
- Success checkmarks animate in
- Copy buttons show "✓ Copied" feedback

### Accessibility

**Keyboard Navigation:**

- Tab order follows visual flow
- All interactive elements keyboard accessible
- Escape key closes wizard (with confirmation)
- Enter key submits current step

**Screen Readers:**

- Proper ARIA labels on all elements
- Step progress announced
- Error messages associated with fields
- Status updates announced live

**Focus Management:**

- Focus moves to first input on step change
- Focus trapped within wizard
- Clear focus indicators (blue outline)

## Technical Implementation

### File Structure

```
deploy-forge/
├── admin/
│   ├── class-setup-wizard.php        # Main wizard class
│   ├── css/
│   │   └── setup-wizard.css          # Wizard-specific styles
│   └── js/
│       └── setup-wizard.js           # Wizard JavaScript logic
├── templates/
│   └── setup-wizard/
│       ├── wizard-container.php      # Main wrapper
│       ├── step-welcome.php          # Step 1
│       ├── step-connect.php          # Step 2
│       ├── step-repository.php       # Step 3
│       ├── step-method.php           # Step 4
│       ├── step-options.php          # Step 5
│       └── step-review.php           # Step 6
```

### PHP Class Structure

```php
class GitHub_Deploy_Setup_Wizard {

    private GitHub_Deploy_Settings $settings;
    private GitHub_API $github_api;
    private GitHub_Deploy_Database $database;

    public function __construct() {
        // Register admin menu
        add_action('admin_menu', [$this, 'add_wizard_page']);

        // Enqueue assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        // AJAX handlers
        add_action('wp_ajax_github_deploy_wizard_get_repos', [$this, 'ajax_get_repos']);
        add_action('wp_ajax_github_deploy_wizard_get_branches', [$this, 'ajax_get_branches']);
        add_action('wp_ajax_github_deploy_wizard_get_workflows', [$this, 'ajax_get_workflows']);
        add_action('wp_ajax_github_deploy_wizard_save_step', [$this, 'ajax_save_step']);
        add_action('wp_ajax_github_deploy_wizard_complete', [$this, 'ajax_complete']);

        // Redirect to wizard on activation if needed
        add_action('admin_init', [$this, 'maybe_redirect_to_wizard']);
    }

    public function should_show_wizard(): bool {
        // Check if wizard needed
    }

    public function add_wizard_page(): void {
        // Register hidden menu page
    }

    public function render_wizard(): void {
        // Render main wizard template
    }

    public function enqueue_assets(): void {
        // Enqueue CSS, JS, Select2
    }

    // AJAX methods...
}
```

### JavaScript Structure

```javascript
const GitHubDeployWizard = {
  currentStep: 1,
  totalSteps: 6,
  wizardData: {},

  init: function () {
    this.bindEvents();
    this.initSelect2();
    this.loadStep(this.currentStep);
  },

  bindEvents: function () {
    // Button clicks
    $(document).on("click", ".wizard-next", this.nextStep.bind(this));
    $(document).on("click", ".wizard-back", this.prevStep.bind(this));
    $(document).on("click", ".wizard-skip", this.skipWizard.bind(this));

    // Step-specific events
    $(document).on(
      "change",
      "#repository-select",
      this.onRepoChange.bind(this)
    );
    $(document).on(
      "change",
      "#deployment-method",
      this.onMethodChange.bind(this)
    );
  },

  initSelect2: function () {
    // Initialize all Select2 instances
  },

  loadStep: function (stepNumber) {
    // AJAX load step content or show/hide steps
    this.updateProgress(stepNumber);
    this.validateStep(stepNumber);
  },

  nextStep: function () {
    if (!this.validateStep(this.currentStep)) {
      return;
    }
    this.saveStep(this.currentStep);
    this.currentStep++;
    this.loadStep(this.currentStep);
  },

  prevStep: function () {
    this.currentStep--;
    this.loadStep(this.currentStep);
  },

  validateStep: function (stepNumber) {
    // Step-specific validation
  },

  saveStep: function (stepNumber) {
    // AJAX save step data
  },

  completeWizard: function () {
    // Final save and redirect
  },
};
```

### Select2 Implementation

**CDN or Local:**

- Use CDN for simplicity: https://cdn.jsdelivr.net/npm/select2@4.1.0/
- Version 4.1.0 (latest stable)

**Enqueue:**

```php
wp_enqueue_style('select2',
    'https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css'
);

wp_enqueue_script('select2',
    'https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/js/select2.min.js',
    ['jquery'],
    null,
    true
);

wp_enqueue_style('select2-custom',
    GITHUB_DEPLOY_PLUGIN_URL . 'admin/css/select2-custom.css'
);
```

**Custom Templates:**

```javascript
// Repository template with icon and description
function formatRepo(repo) {
  if (!repo.id) return repo.text;

  return $(
    '<div class="repo-option">' +
      '<div class="repo-icon"><span class="dashicons dashicons-admin-home"></span></div>' +
      '<div class="repo-info">' +
      '<div class="repo-name">' +
      repo.full_name +
      "</div>" +
      '<div class="repo-desc">' +
      (repo.description || "No description") +
      "</div>" +
      "</div>" +
      '<div class="repo-badge">' +
      (repo.private ? "Private" : "Public") +
      "</div>" +
      "</div>"
  );
}

// Workflow template with status
function formatWorkflow(workflow) {
  if (!workflow.id) return workflow.text;

  let statusIcon = workflow.status === "success" ? "✓" : "○";
  let statusClass = workflow.status === "success" ? "success" : "pending";

  return $(
    '<div class="workflow-option">' +
      '<span class="workflow-status ' +
      statusClass +
      '">' +
      statusIcon +
      "</span>" +
      '<span class="workflow-name">' +
      workflow.name +
      "</span>" +
      "</div>"
  );
}
```

### AJAX Endpoints

**Get Repositories:**

```php
public function ajax_get_repos(): void {
    check_ajax_referer('github_deploy_wizard', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $repos = $this->github_api->get_installation_repositories();

    if (!$repos['success']) {
        wp_send_json_error(['message' => $repos['message']]);
    }

    // Format for Select2
    $formatted = array_map(function($repo) {
        return [
            'id' => $repo['id'],
            'text' => $repo['full_name'],
            'full_name' => $repo['full_name'],
            'description' => $repo['description'],
            'private' => $repo['private'],
            'default_branch' => $repo['default_branch']
        ];
    }, $repos['data']);

    wp_send_json_success(['repositories' => $formatted]);
}
```

**Get Branches:**

```php
public function ajax_get_branches(): void {
    check_ajax_referer('github_deploy_wizard', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $repo_id = intval($_POST['repo_id'] ?? 0);

    // Fetch branches via GitHub API
    $branches = $this->github_api->get_branches($repo_id);

    if (!$branches['success']) {
        wp_send_json_error(['message' => $branches['message']]);
    }

    wp_send_json_success(['branches' => $branches['data']]);
}
```

**Save Step:**

```php
public function ajax_save_step(): void {
    check_ajax_referer('github_deploy_wizard', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $step = intval($_POST['step'] ?? 0);
    $data = $_POST['data'] ?? [];

    // Sanitize and validate step data
    $sanitized = $this->sanitize_step_data($step, $data);

    // Save to transient (temporary storage during wizard)
    set_transient('github_deploy_wizard_step_' . $step, $sanitized, HOUR_IN_SECONDS);

    wp_send_json_success();
}
```

**Complete Wizard:**

```php
public function ajax_complete(): void {
    check_ajax_referer('github_deploy_wizard', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    // Gather all step data from transients
    $wizard_data = [];
    for ($i = 1; $i <= 6; $i++) {
        $step_data = get_transient('github_deploy_wizard_step_' . $i);
        if ($step_data) {
            $wizard_data = array_merge($wizard_data, $step_data);
        }
    }

    // Save final settings
    $this->settings->save($wizard_data);

    // Mark wizard as completed
    update_option('github_deploy_wizard_completed', true);

    // Clean up transients
    for ($i = 1; $i <= 6; $i++) {
        delete_transient('github_deploy_wizard_step_' . $i);
    }

    wp_send_json_success(['redirect' => admin_url('admin.php?page=deploy-forge')]);
}
```

## Data Storage

### During Wizard (Transients)

- `github_deploy_wizard_step_1` - Welcome (no data)
- `github_deploy_wizard_step_2` - Connection data
- `github_deploy_wizard_step_3` - Repository selection
- `github_deploy_wizard_step_4` - Deployment method
- `github_deploy_wizard_step_5` - Options
- `github_deploy_wizard_step_6` - Review (no data)

**Expiration:** 1 hour

### After Completion (Options)

- All settings saved to `github_deploy_settings`
- `github_deploy_wizard_completed` = true
- `github_deploy_wizard_completed_at` = timestamp

## Skip/Exit Behavior

### Skip Setup Button

- Shows confirmation: "Are you sure? You can run the wizard again from settings."
- If confirmed:
  - Set `github_deploy_wizard_skipped` = true
  - Redirect to settings page
  - Show notice: "Setup wizard skipped. You can configure settings manually below."

### Exit (X) Button

- Shows confirmation: "Exit setup? Your progress will be saved."
- If confirmed:
  - Keep transients (progress saved)
  - Redirect to dashboard
  - Show notice: "Setup wizard paused. Click 'Resume Setup' to continue."

### Resume Setup

- If transients exist, show "Resume Setup" button on dashboard
- Clicking resumes at last completed step

## Error Handling

### Network Errors

- Show friendly error message
- Provide "Retry" button
- Don't advance to next step
- Log error to debug log

### Validation Errors

- Highlight invalid fields in red
- Show specific error message under field
- Disable "Next" button until fixed
- Shake animation on submit attempt

### GitHub API Errors

- 401 Unauthorized: "GitHub connection lost. Please reconnect."
- 404 Not Found: "Repository not found. Please check access."
- 403 Forbidden: "No access to repository. Check installation permissions."
- 500 Server Error: "GitHub is temporarily unavailable. Please try again."

## Testing Scenarios

### Happy Path

1. New user activates plugin → wizard shows
2. Clicks through all 6 steps
3. Connects GitHub successfully
4. Selects repository and branch
5. Chooses deployment method
6. Configures options
7. Reviews and completes
8. Redirected to dashboard with success message

### Error Scenarios

1. GitHub connection fails → show error and retry
2. No repositories available → show helpful message
3. Repository access denied → explain permissions
4. Network timeout → show retry button
5. Invalid workflow selected → validation error

### Edge Cases

1. User closes browser mid-wizard → progress saved in transients
2. User skips wizard → can manually configure or restart wizard
3. User completes wizard then resets data → wizard shows again
4. Multiple browser tabs open → last save wins (transients)

## Success Metrics

### Completion Rate

- Track: % of users who complete wizard vs. skip/abandon
- Goal: >80% completion rate

### Time to Complete

- Track: Average time from start to finish
- Goal: <5 minutes

### Error Rate

- Track: % of users who encounter errors
- Goal: <10% error rate

### Support Reduction

- Track: Support tickets about initial setup
- Goal: 50% reduction after wizard launch

## Future Enhancements (v2)

### V2.0 Features

- Video tutorials embedded in steps
- Live preview of deployment path
- Workflow file generator/wizard
- Multi-repository support
- Team member invitation
- Automated webhook configuration (via GitHub API)

### V2.1 Features

- Import/export wizard configuration
- Presets for common setups (WordPress.org themes, custom themes, etc.)
- A/B test different wizard flows
- Onboarding checklist after wizard

## Dependencies

### Required

- WordPress 5.8+
- jQuery (WordPress core)
- Select2 4.1.0+
- Existing GitHub API integration
- Existing settings infrastructure

### Optional

- WP_Filesystem for workflow file creation
- Transients API for progress storage

## Related Specs

- [requirements.md](requirements.md) - FR012 (Settings Management)
- [api-integration.md](api-integration.md) - GitHub API endpoints
- [security.md](security.md) - AJAX security requirements

## Change Log

- 2025-11-09: Initial specification created
