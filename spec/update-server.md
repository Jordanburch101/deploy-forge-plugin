# Update Server - Express/Vercel Proxy

**Last Updated:** 2025-11-18

## Overview

A lightweight Express.js application deployed on Vercel that acts as a proxy between WordPress sites running Deploy Forge and private GitHub releases. The server authenticates with GitHub using a Personal Access Token to fetch release information and stream private release assets to WordPress sites.

## Purpose

- Provide WordPress plugin update API compatibility
- Proxy access to private GitHub releases
- Transform GitHub release data to WordPress update format
- Stream release ZIP files without storing locally
- Rate limit and monitor update requests

## Architecture

```
┌─────────────────┐
│   WordPress     │
│   Sites (n)     │
└────────┬────────┘
         │ HTTPS
         ▼
┌─────────────────┐
│     Vercel      │
│   Edge Network  │
└────────┬────────┘
         │
         ▼
┌─────────────────┐         ┌─────────────────┐
│   Express App   │────────▶│   GitHub API    │
│   (Serverless)  │  Auth   │  (Private Repo) │
└─────────────────┘  Token  └─────────────────┘
```

## Technology Stack

- **Runtime:** Node.js 18.x (Vercel default)
- **Framework:** Express.js 4.x
- **Deployment:** Vercel (Serverless Functions)
- **HTTP Client:** Axios (GitHub API requests)
- **Caching:** Vercel Edge Cache + In-memory
- **Environment:** Environment variables via Vercel

## Project Structure

```
update-server/
├── api/
│   ├── update-check.js      # Update check endpoint
│   ├── download.js          # Download proxy endpoint
│   └── health.js            # Health check endpoint
├── lib/
│   ├── github.js            # GitHub API client
│   ├── transform.js         # GitHub → WordPress data transformer
│   ├── cache.js             # Caching layer
│   ├── validator.js         # Request validation
│   └── logger.js            # Logging utility
├── config/
│   ├── plugins.js           # Plugin configuration (whitelist)
│   └── constants.js         # App constants
├── middleware/
│   ├── error-handler.js     # Global error handling
│   ├── rate-limiter.js      # Rate limiting
│   └── cors.js              # CORS configuration
├── .env.example             # Example environment variables
├── .vercelignore           # Vercel ignore file
├── package.json            # Dependencies
├── vercel.json             # Vercel configuration
└── README.md               # Documentation
```

## API Endpoints

### 1. Health Check

**Endpoint:** `GET /api/health`

**Purpose:** Verify server is running and GitHub API is accessible

**Response (200):**
```json
{
  "status": "healthy",
  "timestamp": "2025-11-18T12:00:00Z",
  "github_api": "reachable",
  "rate_limit": {
    "remaining": 4850,
    "limit": 5000,
    "reset": 1700323200
  }
}
```

**Response (503):**
```json
{
  "status": "unhealthy",
  "timestamp": "2025-11-18T12:00:00Z",
  "github_api": "unreachable",
  "error": "API rate limit exceeded"
}
```

### 2. Update Check

**Endpoint:** `POST /api/update-check`

**Purpose:** Check if a newer version of the plugin is available

**Headers:**
```
Content-Type: application/json
User-Agent: WordPress/6.4; https://example.com
```

**Request Body:**
```json
{
  "plugin": "deploy-forge",
  "version": "1.0.0",
  "php_version": "8.1.0",
  "wp_version": "6.4.0",
  "site_url": "https://example.com"
}
```

**Response (Update Available - 200):**
```json
{
  "version": "1.1.0",
  "download_url": "https://deploy-forge-updates.vercel.app/api/download/deploy-forge/1.1.0",
  "requires": "5.8",
  "requires_php": "7.4",
  "tested": "6.4",
  "last_updated": "2025-11-18 12:00:00",
  "upgrade_notice": "This update includes security fixes.",
  "changelog": {
    "1.1.0": [
      "Added: Plugin update checker",
      "Fixed: Security vulnerability",
      "Improved: GitHub API error handling"
    ]
  },
  "sections": {
    "description": "Automates theme deployment from GitHub repositories",
    "installation": "Upload and activate the plugin",
    "changelog": "See changelog above"
  },
  "package_hash": "sha256:abc123...",
  "banners": {
    "low": "https://cdn.example.com/banner-772x250.png",
    "high": "https://cdn.example.com/banner-1544x500.png"
  },
  "icons": {
    "1x": "https://cdn.example.com/icon-128x128.png",
    "2x": "https://cdn.example.com/icon-256x256.png"
  }
}
```

**Response (No Update - 200):**
```json
{
  "version": "1.0.0",
  "message": "Plugin is up to date"
}
```

**Response (Invalid Request - 400):**
```json
{
  "error": true,
  "message": "Invalid plugin slug",
  "code": "INVALID_PLUGIN"
}
```

**Response (Server Error - 500):**
```json
{
  "error": true,
  "message": "Failed to fetch release information",
  "code": "GITHUB_API_ERROR"
}
```

### 3. Download Plugin

**Endpoint:** `GET /api/download/:plugin/:version`

**Purpose:** Stream plugin ZIP file from GitHub release

**Parameters:**
- `plugin` - Plugin slug (e.g., "deploy-forge")
- `version` - Version to download (e.g., "1.1.0")

**Example:** `GET /api/download/deploy-forge/1.1.0`

**Response (Success - 200):**
```
Content-Type: application/zip
Content-Disposition: attachment; filename="deploy-forge-1.1.0.zip"
Content-Length: 524288
X-Download-Source: github-release
X-Release-Tag: v1.1.0
Cache-Control: public, max-age=86400

[Binary ZIP data stream]
```

**Response (Not Found - 404):**
```json
{
  "error": true,
  "message": "Release not found",
  "code": "RELEASE_NOT_FOUND"
}
```

**Response (Rate Limited - 429):**
```json
{
  "error": true,
  "message": "Too many requests. Try again later.",
  "code": "RATE_LIMITED",
  "retry_after": 60
}
```

## GitHub API Integration

### Authentication

**Method:** Personal Access Token (PAT)

**Token Permissions Required:**
- `repo` (full control of private repositories)
- `read:org` (if repo is in organization)

**Storage:** Vercel environment variable `GITHUB_TOKEN`

**Usage:**
```javascript
const headers = {
  'Authorization': `Bearer ${process.env.GITHUB_TOKEN}`,
  'Accept': 'application/vnd.github.v3+json',
  'User-Agent': 'Deploy-Forge-Update-Server/1.0'
};
```

### API Endpoints Used

**1. List Releases**

```
GET /repos/{owner}/{repo}/releases
```

Fetch all releases to find latest version

**2. Get Latest Release**

```
GET /repos/{owner}/{repo}/releases/latest
```

Get the most recent non-prerelease, non-draft release

**3. Get Specific Release**

```
GET /repos/{owner}/{repo}/releases/tags/{tag}
```

Fetch specific version information

**4. Download Release Asset**

```
GET /repos/{owner}/{repo}/releases/assets/{asset_id}
Accept: application/octet-stream
```

Stream ZIP file for plugin download

### Rate Limiting

**GitHub API Limits:**
- Authenticated: 5,000 requests/hour
- Unauthenticated: 60 requests/hour

**Strategy:**
- Use authenticated requests (higher limit)
- Implement aggressive caching
- Monitor rate limit headers
- Return 503 if rate limit exhausted

**Rate Limit Headers:**
```
X-RateLimit-Limit: 5000
X-RateLimit-Remaining: 4850
X-RateLimit-Reset: 1700323200
```

## Data Transformation

### GitHub Release → WordPress Update Format

**GitHub Release Object:**
```json
{
  "tag_name": "v1.1.0",
  "name": "Version 1.1.0 - Security Update",
  "body": "## Changes\n\n- Added update checker\n- Fixed vulnerability\n- Improved logging",
  "draft": false,
  "prerelease": false,
  "created_at": "2025-11-18T12:00:00Z",
  "published_at": "2025-11-18T12:30:00Z",
  "assets": [
    {
      "id": 12345,
      "name": "deploy-forge-1.1.0.zip",
      "size": 524288,
      "browser_download_url": "https://github.com/..."
    }
  ]
}
```

**Transformation Logic:**

```javascript
function transformReleaseToUpdate(release, plugin) {
  // Parse version (remove 'v' prefix)
  const version = release.tag_name.replace(/^v/, '');

  // Parse changelog from release body
  const changelog = parseChangelogMarkdown(release.body);

  // Find ZIP asset
  const asset = release.assets.find(a => a.name.endsWith('.zip'));

  return {
    version: version,
    download_url: `${BASE_URL}/api/download/${plugin}/${version}`,
    requires: extractRequirement(release.body, 'WordPress'),
    requires_php: extractRequirement(release.body, 'PHP'),
    tested: extractTestedVersion(release.body),
    last_updated: release.published_at,
    upgrade_notice: extractUpgradeNotice(release.body),
    changelog: changelog,
    sections: {
      description: extractDescription(release.body),
      installation: getPluginInstallationInstructions(plugin),
      changelog: formatChangelogHTML(changelog)
    },
    package_hash: asset ? await calculateHash(asset.url) : null,
    banners: getPluginBanners(plugin),
    icons: getPluginIcons(plugin)
  };
}
```

### Changelog Parsing

**Markdown Format (GitHub Release Body):**

```markdown
## Changes in 1.1.0

- Added: Plugin update checker
- Fixed: Security vulnerability in webhook handler
- Improved: GitHub API error handling

## Requirements

- WordPress 5.8+
- PHP 7.4+
- Tested up to: WordPress 6.4

## Upgrade Notice

This update includes critical security fixes. Update immediately.
```

**Parsed Output:**

```javascript
{
  changelog: {
    "1.1.0": [
      "Added: Plugin update checker",
      "Fixed: Security vulnerability in webhook handler",
      "Improved: GitHub API error handling"
    ]
  },
  requires: "5.8",
  requires_php: "7.4",
  tested: "6.4",
  upgrade_notice: "This update includes critical security fixes. Update immediately."
}
```

### Version Comparison

```javascript
function compareVersions(v1, v2) {
  const parse = (v) => {
    const clean = v.replace(/^v/, '');
    const parts = clean.split('.');
    return {
      major: parseInt(parts[0] || 0),
      minor: parseInt(parts[1] || 0),
      patch: parseInt(parts[2] || 0)
    };
  };

  const a = parse(v1);
  const b = parse(v2);

  if (a.major !== b.major) return a.major - b.major;
  if (a.minor !== b.minor) return a.minor - b.minor;
  return a.patch - b.patch;
}
```

## Caching Strategy

### Edge Caching (Vercel)

**Update Check Responses:**
- Cache-Control: `public, s-maxage=3600, stale-while-revalidate=86400`
- Duration: 1 hour fresh, 24 hours stale
- Invalidation: On new release

**Download Responses:**
- Cache-Control: `public, max-age=86400, immutable`
- Duration: 24 hours (immutable - specific version never changes)
- Invalidation: Never (version is immutable)

### In-Memory Cache

**Release Data:**
```javascript
const cache = new Map();

function getCachedReleases(plugin) {
  const key = `releases:${plugin}`;
  const cached = cache.get(key);

  if (cached && Date.now() - cached.timestamp < 3600000) {
    return cached.data;
  }

  return null;
}

function setCachedReleases(plugin, data) {
  cache.set(`releases:${plugin}`, {
    data: data,
    timestamp: Date.now()
  });
}
```

**Cache Invalidation:**

```javascript
// Webhook from GitHub on new release
app.post('/api/webhook/release', (req, res) => {
  const { repository, release } = req.body;
  const plugin = getPluginSlug(repository.name);

  // Clear cache
  cache.delete(`releases:${plugin}`);

  // Purge Vercel edge cache
  await purgeEdgeCache(`/api/update-check`);

  res.json({ success: true });
});
```

## Security

### Environment Variables

**Required:**
```bash
GITHUB_TOKEN=ghp_xxxxxxxxxxxxxxxxxxxx
GITHUB_OWNER=jordanburch101
GITHUB_REPO=deploy-forge
NODE_ENV=production
```

**Optional:**
```bash
RATE_LIMIT_WINDOW=900000        # 15 minutes
RATE_LIMIT_MAX_REQUESTS=100     # Max requests per window
ALLOWED_ORIGINS=https://example.com,https://example2.com
LOG_LEVEL=info
```

### Request Validation

**Plugin Slug Whitelist:**

```javascript
// config/plugins.js
const ALLOWED_PLUGINS = [
  {
    slug: 'deploy-forge',
    repo: 'jordanburch101/deploy-forge',
    branch: 'main'
  }
  // Add more plugins as needed
];

function validatePlugin(slug) {
  return ALLOWED_PLUGINS.find(p => p.slug === slug);
}
```

**Version Format Validation:**

```javascript
function validateVersion(version) {
  // Must match semver: 1.0.0, 1.2.3-beta, etc.
  const semverRegex = /^v?\d+\.\d+\.\d+(-[\w.]+)?$/;
  return semverRegex.test(version);
}
```

### Rate Limiting

**Implementation:**

```javascript
// middleware/rate-limiter.js
const rateLimit = require('express-rate-limit');

const updateCheckLimiter = rateLimit({
  windowMs: 15 * 60 * 1000, // 15 minutes
  max: 100, // 100 requests per window per IP
  message: {
    error: true,
    message: 'Too many update checks. Try again later.',
    code: 'RATE_LIMITED'
  },
  standardHeaders: true,
  legacyHeaders: false,
  handler: (req, res) => {
    res.status(429).json({
      error: true,
      message: 'Too many requests',
      retry_after: Math.ceil(req.rateLimit.resetTime / 1000)
    });
  }
});

// Apply to endpoints
app.post('/api/update-check', updateCheckLimiter, handleUpdateCheck);
```

**Per-Site Tracking:**

```javascript
// Track by site URL instead of IP
const keyGenerator = (req) => {
  return req.body.site_url || req.ip;
};
```

### CORS Configuration

```javascript
// middleware/cors.js
const cors = require('cors');

const corsOptions = {
  origin: function (origin, callback) {
    // Allow requests with no origin (mobile apps, curl, etc.)
    if (!origin) return callback(null, true);

    // Allow all WordPress sites (they identify via User-Agent)
    if (origin && isWordPressSite(origin)) {
      return callback(null, true);
    }

    // Optionally whitelist specific domains
    const allowedOrigins = process.env.ALLOWED_ORIGINS?.split(',') || [];
    if (allowedOrigins.includes(origin)) {
      return callback(null, true);
    }

    callback(new Error('Not allowed by CORS'));
  },
  methods: ['GET', 'POST'],
  allowedHeaders: ['Content-Type', 'User-Agent']
};

app.use(cors(corsOptions));
```

### HTTPS Only

Vercel enforces HTTPS automatically. For local development:

```javascript
// Redirect HTTP to HTTPS
app.use((req, res, next) => {
  if (req.headers['x-forwarded-proto'] !== 'https' && process.env.NODE_ENV === 'production') {
    return res.redirect(301, `https://${req.headers.host}${req.url}`);
  }
  next();
});
```

## Error Handling

### Global Error Handler

```javascript
// middleware/error-handler.js
function errorHandler(err, req, res, next) {
  // Log error
  logger.error('Request error', {
    error: err.message,
    stack: err.stack,
    url: req.url,
    method: req.method,
    ip: req.ip
  });

  // GitHub API errors
  if (err.response && err.response.status === 403) {
    return res.status(503).json({
      error: true,
      message: 'GitHub API rate limit exceeded',
      code: 'RATE_LIMIT_EXCEEDED',
      retry_after: err.response.headers['x-ratelimit-reset']
    });
  }

  // GitHub API not found
  if (err.response && err.response.status === 404) {
    return res.status(404).json({
      error: true,
      message: 'Release not found',
      code: 'RELEASE_NOT_FOUND'
    });
  }

  // Generic server error
  res.status(500).json({
    error: true,
    message: 'Internal server error',
    code: 'INTERNAL_ERROR'
  });
}

app.use(errorHandler);
```

### Specific Error Types

**Invalid Plugin:**
```javascript
if (!validatePlugin(plugin)) {
  return res.status(400).json({
    error: true,
    message: 'Invalid plugin slug',
    code: 'INVALID_PLUGIN'
  });
}
```

**Invalid Version:**
```javascript
if (!validateVersion(version)) {
  return res.status(400).json({
    error: true,
    message: 'Invalid version format',
    code: 'INVALID_VERSION'
  });
}
```

**GitHub API Down:**
```javascript
try {
  const releases = await github.getLatestRelease(owner, repo);
} catch (error) {
  logger.error('GitHub API error', error);
  return res.status(503).json({
    error: true,
    message: 'Could not reach GitHub API',
    code: 'GITHUB_UNREACHABLE'
  });
}
```

## Logging

### Log Levels

- **error** - Critical errors (GitHub API failures, server errors)
- **warn** - Warnings (rate limit approaching, cache misses)
- **info** - Important events (update checks, downloads)
- **debug** - Detailed debugging (request/response data)

### Log Format

```javascript
// lib/logger.js
const winston = require('winston');

const logger = winston.createLogger({
  level: process.env.LOG_LEVEL || 'info',
  format: winston.format.json(),
  defaultMeta: {
    service: 'update-server',
    environment: process.env.NODE_ENV
  },
  transports: [
    new winston.transports.Console({
      format: winston.format.combine(
        winston.format.colorize(),
        winston.format.simple()
      )
    })
  ]
});

// Vercel logs automatically capture console output
module.exports = logger;
```

### Log Examples

**Update Check:**
```javascript
logger.info('Update check', {
  plugin: 'deploy-forge',
  current_version: '1.0.0',
  latest_version: '1.1.0',
  update_available: true,
  site_url: 'https://example.com',
  user_agent: req.headers['user-agent']
});
```

**Download:**
```javascript
logger.info('Download request', {
  plugin: 'deploy-forge',
  version: '1.1.0',
  size_bytes: 524288,
  ip: req.ip,
  duration_ms: 1250
});
```

**Error:**
```javascript
logger.error('GitHub API error', {
  error: error.message,
  status_code: error.response?.status,
  rate_limit_remaining: error.response?.headers['x-ratelimit-remaining'],
  endpoint: '/repos/owner/repo/releases'
});
```

## Deployment

### Vercel Configuration

**vercel.json:**
```json
{
  "version": 2,
  "builds": [
    {
      "src": "api/**/*.js",
      "use": "@vercel/node"
    }
  ],
  "routes": [
    {
      "src": "/api/(.*)",
      "dest": "/api/$1"
    }
  ],
  "env": {
    "NODE_ENV": "production"
  },
  "headers": [
    {
      "source": "/api/download/(.*)",
      "headers": [
        {
          "key": "Cache-Control",
          "value": "public, max-age=86400, immutable"
        }
      ]
    },
    {
      "source": "/api/update-check",
      "headers": [
        {
          "key": "Cache-Control",
          "value": "public, s-maxage=3600, stale-while-revalidate=86400"
        }
      ]
    }
  ],
  "redirects": [
    {
      "source": "/",
      "destination": "/api/health",
      "permanent": true
    }
  ]
}
```

### Environment Variables Setup

**Vercel Dashboard:**
1. Go to Project Settings → Environment Variables
2. Add variables:
   - `GITHUB_TOKEN` (secret)
   - `GITHUB_OWNER` (plaintext)
   - `GITHUB_REPO` (plaintext)
3. Select environment: Production, Preview, Development

**CLI:**
```bash
vercel env add GITHUB_TOKEN
vercel env add GITHUB_OWNER
vercel env add GITHUB_REPO
```

### Deployment Commands

**Deploy to Production:**
```bash
vercel --prod
```

**Deploy Preview:**
```bash
vercel
```

**Check Logs:**
```bash
vercel logs
```

## Monitoring

### Health Monitoring

**Endpoint:** `GET /api/health`

Monitor every 5 minutes with uptime service (UptimeRobot, Pingdom, etc.)

**Alert on:**
- Status code !== 200
- Response time > 2000ms
- Response body: `status !== "healthy"`

### GitHub API Rate Limit

**Monitor:**
```javascript
app.get('/api/metrics', async (req, res) => {
  const rateLimit = await github.getRateLimit();

  res.json({
    github_api: {
      limit: rateLimit.limit,
      remaining: rateLimit.remaining,
      reset: new Date(rateLimit.reset * 1000),
      percentage_used: ((rateLimit.limit - rateLimit.remaining) / rateLimit.limit * 100).toFixed(2)
    }
  });
});
```

**Alert when:**
- Remaining < 500 (90% used)
- Remaining < 100 (98% used)

### Error Rate

Track errors in Vercel dashboard or send to monitoring service:

```javascript
// Send errors to Sentry, LogRocket, etc.
if (process.env.SENTRY_DSN) {
  Sentry.captureException(error);
}
```

## Performance

### Response Times

**Target Response Times:**
- Update Check: < 500ms (cached), < 2000ms (uncached)
- Download: Depends on file size, stream immediately
- Health Check: < 100ms

### Optimization Strategies

**1. Aggressive Caching:**
- Edge cache for update checks (1 hour)
- In-memory cache for release data (1 hour)
- Immutable download URLs (24 hour cache)

**2. Streaming Downloads:**
```javascript
// Don't buffer entire file
const response = await axios.get(asset.url, {
  responseType: 'stream',
  headers: {
    'Authorization': `Bearer ${GITHUB_TOKEN}`,
    'Accept': 'application/octet-stream'
  }
});

// Stream directly to response
response.data.pipe(res);
```

**3. Parallel Requests:**
```javascript
// Fetch release info and asset metadata in parallel
const [release, assets] = await Promise.all([
  github.getRelease(tag),
  github.getAssets(releaseId)
]);
```

**4. Minimize Data Transfer:**
```javascript
// Only fetch required fields from GitHub API
const params = {
  per_page: 10, // Only need latest releases
  page: 1
};
```

## Testing

### Unit Tests

```javascript
// tests/transform.test.js
describe('transformReleaseToUpdate', () => {
  it('should parse version correctly', () => {
    const release = { tag_name: 'v1.2.3' };
    const result = transformReleaseToUpdate(release, 'deploy-forge');
    expect(result.version).toBe('1.2.3');
  });

  it('should extract changelog', () => {
    const release = {
      body: '## Changes\n\n- Added: Feature\n- Fixed: Bug'
    };
    const result = transformReleaseToUpdate(release, 'deploy-forge');
    expect(result.changelog['1.0.0']).toContain('Added: Feature');
  });
});
```

### Integration Tests

```javascript
// tests/api.test.js
describe('POST /api/update-check', () => {
  it('should return update info when newer version available', async () => {
    const response = await request(app)
      .post('/api/update-check')
      .send({
        plugin: 'deploy-forge',
        version: '1.0.0'
      });

    expect(response.status).toBe(200);
    expect(response.body.version).toBe('1.1.0');
    expect(response.body.download_url).toBeDefined();
  });

  it('should return 400 for invalid plugin', async () => {
    const response = await request(app)
      .post('/api/update-check')
      .send({
        plugin: 'invalid-plugin',
        version: '1.0.0'
      });

    expect(response.status).toBe(400);
    expect(response.body.error).toBe(true);
  });
});
```

### E2E Tests

```javascript
// tests/e2e.test.js
describe('Full update flow', () => {
  it('should check for update and download plugin', async () => {
    // 1. Check for update
    const checkResponse = await request(app)
      .post('/api/update-check')
      .send({ plugin: 'deploy-forge', version: '1.0.0' });

    expect(checkResponse.body.version).toBe('1.1.0');

    // 2. Download plugin
    const downloadUrl = checkResponse.body.download_url;
    const downloadResponse = await axios.get(downloadUrl, {
      responseType: 'arraybuffer'
    });

    expect(downloadResponse.status).toBe(200);
    expect(downloadResponse.headers['content-type']).toBe('application/zip');
    expect(downloadResponse.data.length).toBeGreaterThan(0);
  });
});
```

## Future Enhancements

### Multi-Plugin Support

Support multiple plugins from whitelist:

```javascript
const PLUGINS = [
  {
    slug: 'deploy-forge',
    repo: 'jordanburch101/deploy-forge'
  },
  {
    slug: 'another-plugin',
    repo: 'jordanburch101/another-plugin'
  }
];
```

### Beta Channel

Support pre-release versions:

```javascript
// Check if user wants beta releases
if (req.body.channel === 'beta') {
  releases = await github.getAllReleases(); // Include prereleases
} else {
  releases = await github.getStableReleases(); // Exclude prereleases
}
```

### Webhook Integration

Receive GitHub webhooks on new releases to invalidate cache:

```javascript
app.post('/api/webhook/github', async (req, res) => {
  // Verify GitHub webhook signature
  const signature = req.headers['x-hub-signature-256'];
  const isValid = verifyGitHubSignature(req.body, signature);

  if (!isValid) {
    return res.status(401).json({ error: 'Invalid signature' });
  }

  // Clear cache on release published
  if (req.body.action === 'published') {
    const plugin = getPluginSlug(req.body.repository.name);
    cache.delete(`releases:${plugin}`);
    await purgeEdgeCache('/api/update-check');
  }

  res.json({ success: true });
});
```

### Analytics Dashboard

Track update checks and downloads:

```javascript
// Store metrics in lightweight DB (Vercel KV, Upstash Redis)
await redis.hincrby('metrics:update-checks', date, 1);
await redis.hincrby('metrics:downloads', date, 1);

// Dashboard endpoint
app.get('/api/analytics', async (req, res) => {
  const checks = await redis.hgetall('metrics:update-checks');
  const downloads = await redis.hgetall('metrics:downloads');

  res.json({ checks, downloads });
});
```

### Automatic Changelog Generation

Parse commit messages between releases:

```javascript
// Fetch commits between tags
const commits = await github.compareCommits(previousTag, currentTag);

// Parse conventional commits
const changelog = commits
  .map(c => c.commit.message)
  .filter(m => m.match(/^(feat|fix|docs|refactor):/))
  .map(m => {
    const [type, ...rest] = m.split(':');
    return `${type.toUpperCase()}: ${rest.join(':').trim()}`;
  });
```

## References

- [WordPress Plugin Update API](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/)
- [GitHub Releases API](https://docs.github.com/en/rest/releases)
- [Vercel Serverless Functions](https://vercel.com/docs/functions)
- [Express.js Documentation](https://expressjs.com/)
- [Semantic Versioning](https://semver.org/)

## Related Specifications

- `spec/plugin-update-checker.md` - WordPress plugin side implementation
- `spec/security.md` - Security requirements
- `spec/CHANGELOG.md` - Feature tracking
