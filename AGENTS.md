<!-- zappzarapp-boilerplate-agents -->

# AGENTS.md

Guidance for AI coding agents working on the zappzarapp boilerplate. Claude Code
additionally reads `.claude/` (skills, hooks, agent definitions); every other
tool should start here.

## Project

Docker-based PHP/Node development platform (boilerplate). Key directories:
`src/` (application code), `tests/` (PHPUnit, Vitest, BATS, GOSS), `docker/`
(container definitions), `.zappzarapp/` (boilerplate docs and standards).

## Commands

- `make up` / `make down` — start/stop the development environment
- `make check` — full quality gate (run before pushing)
- `make test` — run all tests
- `make help` — list all targets by category

Do not call composer, pnpm, or npx directly — use the make targets; they run the
tools inside the containers with the correct versions.

## Code Standards

Read the matching file in `.zappzarapp/standards/` before writing code:
`php.md`, `node.md`, `shell.md`, `sql.md`, `markdown.md`, `docker.md`.

## Workflow

- Feature branches: `<type>/<slug>` (`feature/`, `fix/`, `chore/`, `docs/`);
  never commit directly to your protected/default branch(es)
- Conventional commit format, enforced via commitlint (CaptainHook)
- Record gotchas in `.zappzarapp/ai/LEARNINGS.md`
- Record architecture decisions as one ADR document per decision under
  `.zappzarapp/docs/adr/`

## Documentation

- Index: `.zappzarapp/docs/README.md`
- Architecture decisions: `.zappzarapp/docs/adr/README.md`
- Troubleshooting: `.zappzarapp/docs/TROUBLESHOOTING.md`
