# Task 15: Separate Claude Context Files (Boilerplate vs Project)

## Priority

MEDIUM - Future Backlog

## Estimated Effort

2-3 hours

## Context

Currently `.claude/context/project.md` contains minimal architecture info. It doesn't
distinguish between:
- What zappzarapp IS (platform, tech stack, principles)
- What the USER'S application IS (business domain, custom modules)

This creates confusion for Claude agents about whether they're working on:
1. The zappzarapp boilerplate itself (contributor mode)
2. An application built WITH zappzarapp (user mode)

## Current State

- Single `project.md` with minimal content (18 lines)
- No distinction between platform context and app context
- No guidance for users to document their application
- Contributors edit boilerplate context directly in source

## Target State

1. Two context files with clear separation:
   - `zappzarapp.md` - Platform/boilerplate context (what zappzarapp is)
   - `project.md` - User's application context (what their app does)

2. Different handling for user vs contributor:
   - **User mode**: `zappzarapp.md` tracked (gets upstream updates), `project.md` untracked (local)
   - **Contributor mode**: `zappzarapp.md` symlinked to source, both tracked

3. Integrated into existing `make setup` workflow

## Implementation Outline

### 1. Source Files in `.zappzarapp/context/`

```
.zappzarapp/context/
├── zappzarapp.md                      # Platform context (source of truth)
├── project.md.template                # Template for users
└── project-dev.md.template            # Template for contributors
```

### 2. zappzarapp.md Content

```markdown
# Zappzarapp Platform Context

DO NOT EDIT - This describes the zappzarapp platform itself.
For your app-specific context, edit `project.md`.

## Platform Overview
- Dual-language web development platform (PHP 8.4 + Node.js 24)
- Security-by-design, GDPR-ready
- Docker-based, production-ready from day one

## Tech Stack
- Backend: PHP 8.4 (PHP-FPM), Node.js 24
- Frontend: Vite, TypeScript, HMR
- Web Server: Nginx (reverse proxy, SSL/TLS)
- Databases: PostgreSQL (default), MariaDB (optional)
- Cache: Redis

## Architecture
- Modular design (App, DevDashboard modules)
- Network segmentation: internal/external/secure
- Vite build: resources/ → public/build/

## Core Principles
- Security First: Secrets via Docker Secrets, internal TLS, CSP
- GDPR Compliance: Audit logging, encryption, retention policies
- Testability: All code requires tests (Unit/Feature)
- No Quick Fixes: Security → Architecture → Performance → Simplicity

## Test Conventions
- PHP: tests/php/{Module}/Unit/ and tests/php/{Module}/Feature/
- Node: tests/node/backend/unit/ and tests/node/backend/integration/
- Coverage: build/coverage/
```

### 3. project.md.template Content

```markdown
# Project Context

USER-EDITABLE - Describe YOUR application here.

## Application Overview

[Describe what your app does, target users, business domain]

Example:
- E-commerce platform for sustainable fashion
- B2B SaaS for inventory management
- Internal tool for customer support

## Custom Modules

[List app-specific modules beyond DevDashboard]

Example:
- Shop (products, cart, checkout)
- Blog (articles, comments)
- CRM (customers, leads, tickets)

## Business Logic

[Key business rules, workflows, domain concepts]

Example:
- Orders require email verification before processing
- Premium users get priority support tickets
- Subscription renewal happens 7 days before expiry

## External Services

[APIs, third-party services, integrations]

Example:
- Stripe for payments
- SendGrid for transactional emails
- Algolia for product search

## Special Requirements

[Performance, compliance, data handling requirements]

Example:
- HIPAA compliance for patient data
- 99.9% uptime SLA for checkout flow
- GDPR right-to-be-forgotten implementation
```

### 4. Makefile Integration (in `setup` target)

```makefile
setup:
    # ... existing logic ...

    # Context setup
    @echo "Setting up Claude context files..."
    @mkdir -p .claude/context

    @if [ "$(BOILERPLATE)" = "1" ]; then \
        # Contributor mode: Symlink for direct editing
        ln -sf ../../.zappzarapp/context/zappzarapp.md .claude/context/zappzarapp.md; \
        cp .zappzarapp/context/project-dev.md.template .claude/context/project.md; \
    else \
        # User mode: Copies
        cp .zappzarapp/context/zappzarapp.md .claude/context/; \
        cp .zappzarapp/context/project.md.template .claude/context/project.md; \
        # Make project.md local (like .idea/php.xml pattern)
        git update-index --skip-worktree .claude/context/project.md 2>/dev/null || true; \
    fi
```

### 5. Makefile Integration (in `reset` target)

```makefile
reset:
    # ... existing logic ...
    @echo "Resetting Claude context files..."
    @rm -f .claude/context/{zappzarapp,project}.md
    @git update-index --no-skip-worktree .claude/context/project.md 2>/dev/null || true
```

### 6. Optional Make Target (for convenience)

```makefile
context-track: ## Re-track project.md for team documentation
	@git update-index --no-skip-worktree .claude/context/project.md 2>/dev/null || true
	@git add .claude/context/project.md
	@echo "project.md is now tracked. Commit to share with team."
```

### 7. BATS Tests

```bash
@test "make setup: zappzarapp.md exists" {
    make setup
    [ -f .claude/context/zappzarapp.md ]
}

@test "make setup: project.md exists" {
    make setup
    [ -f .claude/context/project.md ]
}

@test "make setup: project.md is untracked" {
    make setup
    ! git ls-files --error-unmatch .claude/context/project.md
}

@test "BOILERPLATE=1 make setup: zappzarapp.md is symlink" {
    BOILERPLATE=1 make setup
    [ -L .claude/context/zappzarapp.md ]
}

@test "BOILERPLATE=1 make setup: both files tracked" {
    BOILERPLATE=1 make setup
    git ls-files --error-unmatch .claude/context/zappzarapp.md
    git ls-files --error-unmatch .claude/context/project.md
}
```

## Benefits

1. **Clear Separation**: Agents understand platform vs application context
2. **Upstream Updates**: Users can `git pull` platform updates to `zappzarapp.md`
3. **Local Customization**: `project.md` doesn't pollute `git status`
4. **Team Documentation**: Optional tracking of `project.md` for shared context
5. **Contributor Workflow**: Symlink makes it obvious what's editable

## Migration Strategy

For existing projects after upgrade:
1. `make reset` removes old single `project.md`
2. `make setup` creates both new context files
3. Users manually migrate any custom content from backup

## Notes

- Uses existing `--skip-worktree` pattern (like `.idea/php.xml`)
- No new targets needed (integrates into `setup`/`reset`)
- Optional `context-track` target for team documentation use case
- GOSS tests not needed (`.claude/` not in containers)
