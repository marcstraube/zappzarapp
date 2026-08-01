# Contributing to zappzarapp

Thanks for your interest in contributing!

The full contribution guide lives in
[`.zappzarapp/docs/CONTRIBUTING.md`](.zappzarapp/docs/CONTRIBUTING.md) —
including development setup, coding standards, and the review process.

## Quick Version

```bash
make init        # create .env
make setup       # build images, install dependencies, generate secrets/certs
make up          # start the stack
make check       # fast pre-check: static checks + tests (no coverage) for quick iteration
make ci          # faithful CI gate simulation (coverage strictness + audit) — run before pushing
```

- Branch from `develop` (`feature/…`, `fix/…`, `chore/…`, `docs/…`)
- Commit messages follow
  [Conventional Commits](https://www.conventionalcommits.org/) — enforced by a
  git hook
- `make ci` must be green before pushing (it mirrors the CI gate: PHP coverage
  strictness + dependency audit); `make check` is the faster no-coverage
  pre-check, and the pre-push hook runs the critical subset automatically
- By participating you agree to our [Code of Conduct](CODE_OF_CONDUCT.md)
- Security issues: see [SECURITY.md](SECURITY.md) — never file them as public
  issues
