![Deploy Forge](https://media.getdeployforge.com/df-banner.png)

# Deploy Forge - WordPress Plugin

**Push to GitHub. Deploy to WordPress.**

Automated WordPress theme deployment via Git. Push code to GitHub, deploy instantly to any WordPress site. No FTP, zero downtime, instant rollbacks.

[Get Started](https://getdeployforge.com/signup) | [Documentation](https://getdeployforge.com/docs) | [Website](https://getdeployforge.com)

---

## How It Works

```
Commit code → GitHub Actions builds → Plugin deploys to WordPress
```

1. **Push** your theme code to GitHub
2. **Build** — GitHub Actions compiles your assets (npm install, build, etc.)
3. **Deploy** — The plugin downloads the build artifact and deploys it to your theme directory

Commits trigger builds. Builds trigger deployments. Zero downtime. Instant rollbacks.

## Features

- **Automatic Git Deployments** — Push to a branch, your site updates automatically
- **Zero-Downtime Atomic Deployments** — No partial updates, no broken state
- **Instant Rollbacks** — Revert to any previous version with one click
- **Asset Compilation** — GitHub Actions handles `npm install` and build steps
- **Automatic Backups** — Every deployment creates a ZIP backup of the previous version
- **Secure Webhooks** — HMAC SHA-256 signature validation on all payloads
- **Multi-Environment** — Deploy `develop` to staging, `main` to production

## Requirements

- WordPress 5.8+
- PHP 8.0+
- HTTPS enabled (for webhooks)
- A [Deploy Forge](https://getdeployforge.com) account

## Installation

1. Download the latest release from [GitHub Releases](https://github.com/Jordanburch101/deploy-forge-plugin/releases/latest)
2. In your WordPress admin, go to **Plugins > Add New > Upload Plugin**
3. Upload the ZIP file and activate
4. Follow the setup wizard to connect your Deploy Forge account

The plugin checks for updates automatically — you'll get update notifications directly in your WordPress dashboard.

## Pricing

All plans include a **30-day free trial** with no credit card required.

| Plan | Sites | Price |
|------|-------|-------|
| **Hobby** | 5 production sites | $5/month |
| **Pro** | 50 production sites | $25/month |
| **Agency** | 100 production sites | $50/month |

Every plan includes unlimited deployments, instant rollbacks, automatic backups, and staging environments.

[View pricing](https://getdeployforge.com/#pricing)

## Support

- [Documentation](https://getdeployforge.com/docs)
- [Contact](https://getdeployforge.com/contact)
- [System Status](https://status.getdeployforge.com)

## License

GPL v2 or later. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
