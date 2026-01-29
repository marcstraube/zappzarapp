# Missing API Documentation Warnings

**Status:** Planned **Size:** Small **Scope:** feature **Created:** 2026-01-21
**Planning:** Not required

## Context

Improve developer experience when API docs are not yet generated.

## Goal

Show warnings/hints when API documentation is missing, guiding users to run
`make docs` (or `make docs-php` / `make docs-node`).

## Subtask 1: DevDashboard Welcome Page

**Location:** DevDashboard (`/dev/`)

1. Check if `docs/api/php/` and `docs/api/node/` exist
2. Display warning banner if missing
3. Show appropriate make command based on what's missing

**Files:**

- `src/php/DevDashboard/Controller/WelcomeController.php`
- `templates/dev-dashboard/welcome.php`

## Subtask 2: Nginx /docs Page

**Location:** Static docs page (`/docs/`)

**Options:**

- A: Hide links to non-existent docs entirely
- B: Show links with warning icon/text
- C: Redirect to info page explaining how to generate

**Implementation:**

1. Check if `docs/api/php/index.html` and `docs/api/node/index.html` exist
2. Conditionally show/hide or annotate links

**Files:**

- Depends on how `/docs` is served (static HTML or PHP template)
