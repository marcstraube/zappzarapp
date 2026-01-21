# Docker Standards

## Linting

- Tool: Hadolint
- Target: `make lint-docker`
- No auto-fix available — fix errors manually

## Best Practices

- Multi-stage builds for smaller images
- Explicit versions for base images (not `:latest`)
- `USER` instruction for non-root execution
- `HEALTHCHECK` in all production images
- Copy only necessary files (use `.dockerignore`)
- Combine RUN commands to reduce layers
