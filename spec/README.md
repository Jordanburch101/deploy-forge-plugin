# Specification Documentation

**Last Updated:** 2025-11-09

## Overview

This directory contains comprehensive technical specifications for the WordPress Deploy Forge plugin. These documents serve as the single source of truth for architecture, design decisions, and implementation details.

## Purpose

The `spec/` directory provides:

- **Architecture Documentation** - System design and component interactions
- **Technical Specifications** - Detailed implementation requirements
- **API Contracts** - External service integrations
- **Security Standards** - Security requirements and best practices
- **Testing Procedures** - Quality assurance methodologies
- **Change History** - Feature tracking and version history

## Document Index

### Core Specifications

| Document                                             | Description                                         | Audience                |
| ---------------------------------------------------- | --------------------------------------------------- | ----------------------- |
| **[architecture.md](architecture.md)**               | System architecture, components, data flow          | Developers, Architects  |
| **[requirements.md](requirements.md)**               | Functional and non-functional requirements          | Product, QA, Developers |
| **[database.md](database.md)**                       | Database schema, queries, migrations                | Developers, DBAs        |
| **[api-integration.md](api-integration.md)**         | GitHub API, webhooks, REST endpoints                | Developers, DevOps      |
| **[security.md](security.md)**                       | Security requirements, threat model, best practices | Security, Developers    |
| **[deployment-workflow.md](deployment-workflow.md)** | Deployment lifecycle, states, error handling        | Developers, Support     |
| **[testing.md](testing.md)**                         | Testing strategies, test cases, quality assurance   | QA, Developers          |

### Change Management

| Document                         | Description                                             | Audience |
| -------------------------------- | ------------------------------------------------------- | -------- |
| **[CHANGELOG.md](CHANGELOG.md)** | Feature history, version tracking, planned enhancements | All      |
| **[README.md](README.md)**       | This file - spec directory overview                     | All      |

## How to Use These Specifications

### For Developers

**Before implementing a feature:**

1. Review [requirements.md](requirements.md) for functional requirements
2. Check [architecture.md](architecture.md) for system design
3. Review [security.md](security.md) for security considerations
4. Update [CHANGELOG.md](CHANGELOG.md) with planned changes

**During implementation:**

1. Follow patterns documented in architecture
2. Implement security requirements from security.md
3. Write tests according to testing.md
4. Update relevant specs if design changes

**After implementation:**

1. Update CHANGELOG.md with implementation details
2. Add migration notes if schema changed
3. Update specs to reflect any design changes

### For Claude Code

When working on this codebase, Claude should:

1. **Reference these specs** before making changes
2. **Update CHANGELOG.md** for all significant changes
3. **Never create new feature markdown files** in root or app-docs
4. **Add new specs here** if documenting new major subsystems
5. **Keep specs in sync** with actual implementation

**Documentation Policy (from CLAUDE.md):**

- ✅ Update `spec/CHANGELOG.md` for feature changes
- ✅ Update relevant spec files for design changes
- ✅ Create new spec files for major new subsystems
- ❌ DO NOT create files like `NEW-FEATURE.md`, `IMPLEMENTATION-NOTE.md`, etc.
- ❌ DO NOT add documentation to `app-docs/` (legacy, archived)

### For QA/Testing

1. Use [requirements.md](requirements.md) for acceptance criteria
2. Follow [testing.md](testing.md) for test procedures
3. Reference [deployment-workflow.md](deployment-workflow.md) for workflow testing
4. Check [security.md](security.md) for security test cases

### For Support

1. Reference [deployment-workflow.md](deployment-workflow.md) for deployment issues
2. Check [api-integration.md](api-integration.md) for webhook problems
3. Review [CHANGELOG.md](CHANGELOG.md) for recent changes

## Specification Maintenance

### When to Update Specs

**Update immediately when:**

- Architecture changes (components, data flow)
- Database schema changes
- API contracts change
- Security requirements change
- New features added

**Update regularly:**

- CHANGELOG.md (with every feature/fix)
- Testing procedures (as tests evolve)
- Requirements (as product evolves)

### Versioning

Specifications are versioned with the plugin:

- Specs track current implementation
- Historical versions preserved in git history
- Breaking changes noted in CHANGELOG.md

### Style Guide

**Markdown Formatting:**

- Use `#` for main title (document name)
- Use `##` for major sections
- Use `###` for subsections
- Include "Last Updated" date at top
- Use tables for structured data
- Use code blocks for examples
- Use emojis sparingly (checkmarks for status)

**Code Examples:**

```php
// Always include language identifier
// Include context comments
// Keep examples concise but complete
```

**Cross-References:**

- Link to other specs: `[architecture](architecture.md)`
- Link to code: `includes/class-example.php:123`
- Link to external docs: `[WordPress Codex](https://codex.wordpress.org/)`

## Document Relationships

```
requirements.md (WHAT)
    ├── Defines: What the system must do
    └── Used by: All other specs

architecture.md (HOW)
    ├── Defines: How the system is structured
    └── Implements: requirements.md

database.md (WHERE)
    ├── Defines: Where data is stored
    └── Implements: architecture.md

api-integration.md (WHEN/HOW)
    ├── Defines: External service interactions
    └── Implements: architecture.md

security.md (SAFELY)
    ├── Defines: How to protect assets
    └── Applies to: All specs

deployment-workflow.md (PROCESS)
    ├── Defines: Deployment lifecycle
    └── Implements: requirements.md + architecture.md

testing.md (VERIFICATION)
    ├── Defines: How to verify correctness
    └── Validates: All specs

CHANGELOG.md (HISTORY)
    ├── Tracks: All changes over time
    └── References: All specs
```

## Living Documentation

These specifications are **living documents** that evolve with the codebase:

- ✅ Keep in sync with implementation
- ✅ Update when design changes
- ✅ Reflect actual behavior
- ❌ Don't let specs become stale
- ❌ Don't document what you wish it did

## Contributing

When adding new content:

1. **Choose the right document** - Don't duplicate information
2. **Update "Last Updated" date** - Track freshness
3. **Follow existing structure** - Consistency matters
4. **Be specific** - Vague specs are useless
5. **Include examples** - Show, don't just tell
6. **Cross-reference** - Link related information

## Questions?

If you're unsure:

- Where to document something → Start with CHANGELOG.md
- How much detail → Enough for someone to implement it
- When to create new spec → When documenting a new major subsystem

---

**These specifications are maintained by the development team and should be the first reference for any questions about system design, implementation, or behavior.**
