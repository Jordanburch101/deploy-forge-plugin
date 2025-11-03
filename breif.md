# GitHub Auto-Deploy for WordPress - Project Brief

## Project Overview

**Plugin Name:** GitHub Auto-Deploy for WordPress  
**Version:** 1.0.0  
**Type:** WordPress Plugin  
**Target Users:** WordPress developers and site administrators using GitHub for version control

## Problem Statement

WordPress theme development typically involves a cumbersome deployment process:
- Developers commit code to GitHub
- Someone manually downloads/pulls the repository
- Files must be manually uploaded via FTP/SFTP or copied to the server
- Build processes (Sass compilation, JS bundling, etc.) must be run manually
- No easy way to track deployment history or rollback changes
- Risk of human error in manual deployments

This plugin automates this entire workflow, enabling continuous deployment from GitHub directly to WordPress.

## Solution

A WordPress plugin that connects to a GitHub repository, automatically detects new commits, triggers GitHub Actions to build the theme, and deploys the compiled files directly to the WordPress theme directory. It provides a user-friendly admin interface for configuration, monitoring, and managing deployments.

## Key Features

### Core Functionality
1. **GitHub Integration**
   - Connect to any GitHub repository via Personal Access Token
   - Monitor specific branches for changes
   - Webhook support for real-time deployment triggers

2. **Automated Build & Deploy**
   - Trigger GitHub Actions workflows on commit
   - Poll for build completion
   - Download compiled artifacts
   - Deploy to WordPress theme directory

3. **Admin Dashboard**
   - Visual interface for repository configuration
   - Real-time deployment status monitoring
   - One-click manual deployments
   - Connection testing and validation

4. **Deployment History**
   - Complete log of all deployments
   - Commit information and metadata
   - Build logs from GitHub Actions
   - Deployment success/failure tracking

5. **Safety Features**
   - Automatic backups before deployment
   - One-click rollback to previous versions
   - Manual approval mode (optional)
   - Secure token storage (encrypted)

## Technical Architecture

### Technology Stack
- **Backend:** PHP 7.4+ (WordPress standard)
- **Frontend:** JavaScript (vanilla or jQuery), CSS
- **API Integration:** GitHub REST API v3
- **Database:** WordPress wpdb (MySQL/MariaDB)
- **Build System:** GitHub Actions (external)

### Key Components

```
┌─────────────┐         ┌──────────────┐         ┌─────────────┐
│   GitHub    │────────▶│  WordPress   │────────▶│   Theme     │
│ Repository  │ webhook │    Plugin    │ deploy  │  Directory  │
└─────────────┘         └──────────────┘         └─────────────┘
       │                        │
       │                        │
       ▼                        ▼
┌─────────────┐         ┌──────────────┐
│   GitHub    │◀────────│   Database   │
│   Actions   │ trigger │  Deployments │
└─────────────┘         └──────────────┘
```

### Workflow
1. Developer commits to GitHub repository
2. GitHub webhook notifies WordPress plugin
3. Plugin validates webhook and triggers GitHub Actions workflow
4. GitHub Actions builds theme (npm install, compile assets, etc.)
5. Plugin polls GitHub API for build completion
6. Plugin downloads artifact ZIP from GitHub
7. Plugin creates backup of current theme
8. Plugin extracts and deploys new theme files
9. Deployment logged to database with full details

## User Stories

**As a WordPress developer, I want to:**
- Push code to GitHub and have it automatically deploy to my WordPress site
- View a history of all deployments with commit information
- Quickly rollback to a previous version if something breaks
- Manually deploy specific commits when needed
- Receive notifications when deployments fail

**As a site administrator, I want to:**
- Easily configure the GitHub connection without touching code
- Monitor deployment status in real-time
- Ensure deployments are safe with automatic backups
- Control whether deployments happen automatically or require approval

## Technical Requirements

### WordPress Requirements
- WordPress 5.8 or higher
- PHP 7.4 or higher
- MySQL 5.7+ or MariaDB 10.2+
- Write permissions to wp-content/themes directory

### Server Requirements
- HTTPS (required for GitHub webhooks)
- Ability to receive external HTTP requests
- Sufficient disk space for theme backups
- wp-cron enabled (or system cron configured)

### GitHub Requirements
- GitHub account with repository access
- Personal Access Token with scopes:
  - `repo` (full repository access)
  - `actions` (workflow access)
- Repository with GitHub Actions enabled
- GitHub Actions workflow file configured

## Security Considerations

- **Authentication:** GitHub Personal Access Tokens encrypted in database
- **Authorization:** WordPress capability checks (admin only)
- **Webhook Validation:** HMAC signature verification for all webhooks
- **Input Sanitization:** All user inputs sanitized and validated
- **Output Escaping:** All outputs properly escaped
- **Nonce Protection:** CSRF protection on all forms and AJAX requests
- **File Security:** Path validation to prevent directory traversal
- **Rate Limiting:** Prevent abuse of webhook endpoints

## Success Metrics

- Plugin successfully deploys theme on commit
- Zero manual intervention required for standard deployments
- Deployment history accurately logged
- Rollback functionality restores previous versions correctly
- Admin interface is intuitive and requires minimal documentation
- No security vulnerabilities in code review
- Compatible with major WordPress hosting providers

## Project Scope

### In Scope (v1.0)
- Single repository connection
- Single branch monitoring
- WordPress theme deployment only
- GitHub Actions integration
- Basic deployment history
- Rollback functionality
- Manual and automatic deployment modes
- Webhook support
- Admin interface

### Out of Scope (Future Versions)
- Plugin deployments (themes only for v1.0)
- Multiple repository support
- Multiple environment management (staging/production)
- Advanced permissions (team roles)
- Slack/email notifications integration
- Deployment scheduling
- A/B testing capabilities
- Blue-green deployments

## Timeline Estimate

- **Phase 1-3** (Foundation): 4-6 hours
- **Phase 4-6** (Core Logic): 8-10 hours
- **Phase 7-10** (Admin Interface): 8-10 hours
- **Phase 11-13** (Polish & Security): 4-6 hours
- **Phase 14-15** (Documentation & Testing): 4-6 hours

**Total:** ~30-40 hours for complete v1.0

## Risks & Mitigations

| Risk | Impact | Mitigation |
|------|--------|-----------|
| GitHub API rate limits | Medium | Cache responses, implement exponential backoff |
| Build failures break site | High | Require manual approval mode, automatic backups |
| Server lacks write permissions | High | Check permissions on activation, clear error messages |
| Large themes timeout | Medium | Increase PHP execution limits, use chunked processing |
| Webhook endpoint exposed | Medium | Signature validation, rate limiting |

## Deliverables

1. Complete WordPress plugin codebase
2. README with installation and configuration instructions
3. Example GitHub Actions workflow file
4. User documentation
5. Developer documentation (inline comments)
6. Security audit checklist

## Future Enhancements (v2.0+)

- Support for WordPress plugin deployments
- Multi-environment support (staging, production)
- Rollback to any historical version
- Email/Slack notifications
- Deployment scheduling
- GitLab and Bitbucket support
- WP-CLI commands for deployments
- Deployment approval workflow for teams
- Advanced logging with search/filter
- Integration with WordPress VIP or other enterprise platforms

---

**This plugin aims to bring modern CI/CD practices to WordPress theme development, making deployments safer, faster, and more reliable.**