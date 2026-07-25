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

## Docker Socket Access Without Root

Running containers with `--user root` changes file ownership to root:root on
bind-mounted volumes. Instead, run as the host user and add the Docker socket's
group for socket access:

```bash
docker run --rm \
    -v /var/run/docker.sock:/var/run/docker.sock \
    -v "$(pwd):$(pwd)" -w "$(pwd)" \
    --user "$(id -u):$(id -g)" \
    --group-add "$(stat -c %g /var/run/docker.sock)" \
    my-image command
```

- `--user "$(id -u):$(id -g)"` — runs as the host user, files keep correct
  ownership
- `--group-add "$(stat -c %g /var/run/docker.sock)"` — adds the docker group for
  socket access

**Note:** The `stat -c %g` syntax is Linux-specific. On macOS, use `stat -f %g`.
