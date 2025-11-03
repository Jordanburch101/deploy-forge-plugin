# Cancel Deployment Feature

## Overview
Added the ability to cancel pending or building deployments directly from the WordPress admin dashboard and history pages.

## Changes Made

### 1. GitHub API Integration (`includes/class-github-api.php`)
- **Added `cancel_workflow_run()` method**: Cancels a GitHub Actions workflow run via the GitHub API
  - Endpoint: `POST /repos/{owner}/{repo}/actions/runs/{run_id}/cancel`
  - Returns 202 Accepted on successful cancellation
  - Full error handling with WP_Error support

### 2. Deployment Manager (`includes/class-deployment-manager.php`)
- **Added `cancel_deployment()` method**: Orchestrates the cancellation process
  - Validates deployment status (only allows cancelling `pending` or `building` deployments)
  - Cancels the GitHub Actions workflow run if `workflow_run_id` exists
  - Updates deployment status to `cancelled` in database
  - Logs all cancellation steps for debugging
  - Triggers `github_deploy_cancelled` action hook
  - Sets error message: "Deployment cancelled by user."

### 3. Admin AJAX Handler (`admin/class-admin-pages.php`)
- **Added `ajax_cancel_deployment()` method**: Handles AJAX requests from frontend
  - Verifies nonce for security
  - Checks `manage_options` capability
  - Validates deployment ID
  - Returns success/error messages
- **Registered new AJAX action**: `wp_ajax_github_deploy_cancel`
- **Added localized strings**:
  - `confirmCancel`: "Are you sure you want to cancel this deployment? The GitHub Actions workflow will be stopped."
  - `cancelling`: "Cancelling..."

### 4. Dashboard Template (`templates/dashboard-page.php`)
- Added "Actions" column to Recent Deployments table
- Shows "Cancel" button for deployments with status `pending` or `building`
- Button includes dashicons-no icon and appropriate styling
- Shows "—" for non-cancellable deployments

### 5. History Template (`templates/history-page.php`)
- Added "Cancel" button in Actions column for `pending` or `building` deployments
- Consistent with dashboard implementation
- Includes dashicons-no icon

### 6. JavaScript Handler (`admin/js/admin-scripts.js`)
- **Added `cancelDeployment()` method**:
  - Shows confirmation dialog before cancelling
  - Disables button and shows loading state during cancellation
  - Makes AJAX request to `github_deploy_cancel` action
  - Reloads page on success to show updated status
  - Handles errors gracefully with user feedback
- **Bound event listener**: `.cancel-deployment-btn` click events

### 7. CSS Styling (`admin/css/admin-styles.css`)
- **Added `.deployment-status.status-cancelled`**:
  - Grey background (#f0f0f1)
  - Muted text color (#8c8f94)
  - Strike-through text decoration
- **Added `.cancel-deployment-btn` styles**:
  - Red color theme (#d63638)
  - Hover effect with white text on red background
  - Proper dashicons sizing and positioning
  - Disabled state styling

## Database Schema
No database schema changes required. The existing `status` field (varchar(20)) accommodates the new `cancelled` status value.

## Security Features
✅ **Nonce verification**: All AJAX requests verify nonces  
✅ **Capability checks**: Only users with `manage_options` can cancel deployments  
✅ **Input sanitization**: Deployment IDs are validated and sanitized  
✅ **Status validation**: Only `pending` or `building` deployments can be cancelled  

## WordPress Standards Compliance
✅ Uses `wp_remote_request()` for GitHub API calls  
✅ Uses `WP_Error` for error handling  
✅ Uses WordPress coding standards  
✅ Properly escaped outputs (`esc_attr`, `esc_html`)  
✅ Properly sanitized inputs  
✅ Follows WordPress AJAX patterns  
✅ Uses WordPress localization functions  
✅ Follows WordPress action hook patterns  

## User Experience

### Before Cancellation:
1. User sees a deployment stuck in "Building" status
2. Cancel button appears next to the deployment in both Dashboard and History pages

### During Cancellation:
1. User clicks "Cancel" button
2. Confirmation dialog: "Are you sure you want to cancel this deployment? The GitHub Actions workflow will be stopped."
3. If confirmed, button shows loading spinner and "Cancelling..." text
4. AJAX request sent to WordPress backend
5. WordPress calls GitHub API to cancel the workflow run
6. Database updated with cancelled status

### After Cancellation:
1. Page reloads to show updated status
2. Deployment status shows "Cancelled" with grey styling and strike-through
3. Cancel button no longer appears (deployment is final)
4. Deployment logs show cancellation details

## Action Hooks
- **`github_deploy_cancelled`**: Triggered after successful deployment cancellation
  - Parameters: `$deployment_id` (int)
  - Use for custom notifications, logging, or cleanup

## Debugging
All cancellation steps are logged via the Debug Logger:
- Initial cancellation request
- GitHub API cancellation request/response
- Database status update
- Final completion

## Error Handling
- Invalid deployment ID → Error message
- Deployment already completed/cancelled → Error message with explanation
- GitHub API failure → Continues to update local status, logs error
- Permission denied → Error message
- Network failures → User-friendly error message

## Testing Checklist
- ✅ Cancel button only appears for pending/building deployments
- ✅ Cancel button triggers confirmation dialog
- ✅ Successful cancellation updates status in database
- ✅ Successful cancellation calls GitHub API to stop workflow
- ✅ Status badge shows "Cancelled" with appropriate styling
- ✅ Non-admin users cannot cancel deployments
- ✅ Nonce validation prevents CSRF attacks
- ✅ Already completed deployments cannot be cancelled
- ✅ Error messages are user-friendly
- ✅ Page refreshes to show updated status

## GitHub API Rate Limits
Each cancellation consumes 1 GitHub API request. Standard GitHub API rate limits apply (5,000 requests/hour for authenticated requests).

## Future Enhancements
- Add bulk cancellation for multiple deployments
- Add "Resume" functionality for cancelled deployments
- Add email/Slack notifications for cancellations
- Add cancellation reason/notes field
- Add automatic cancellation after X minutes of pending status

