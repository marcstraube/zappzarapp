# docker-bake.hcl
#
# Single build graph for every image that participates in cross-image file
# copies (goss binary, Vite assets from node-backend). The php/nginx/postgres/
# redis/node test + production stages COPY files from other images; bake builds
# all targets in one graph and wires the copies via named `contexts`
# (`target:<name>`), so BuildKit links the source target's rootfs directly and
# the gha/registry layer cache applies.
#
# The `COPY --from=` references and the context keys below are TAGLESS (`goss`,
# `node-backend-assets`): a bake `contexts` map key that contains a colon is
# silently ignored at solve time (only the CLI `--build-context` flag accepts
# colon keys), so a tagless key is what BuildKit matches to the linked target.
# Verified on buildx 0.33.
#
# Usage:
#   docker buildx bake --load <target|group>          # local / --load into docker
#   docker buildx bake test                            # build-time GOSS validation
#   CI passes per-target cache via --set (see .github/workflows, .gitlab-ci.yml).

variable "PROJECT" {
  # Mirrors COMPOSE_PROJECT_NAME (.env). Image names must match what compose /
  # `make up` expect, so bake --load produces the right local tags.
  default = "zappzarapp"
}

# ═══════════════════════════════════════════════════════════════════════════
# UTILITY / SHARED-SOURCE IMAGES
# ═══════════════════════════════════════════════════════════════════════════

# GOSS binary source — consumed by every *-test target via the `goss` context.
target "goss" {
  context    = "."
  dockerfile = "docker/goss/Dockerfile"
  tags       = ["${PROJECT}-goss:latest"]
}

# node-backend `api` stage — provides /app/public/build (Vite assets) consumed by
# the php/nginx production stages via the `node-backend-assets` context.
target "node-backend" {
  context    = "."
  dockerfile = "docker/node/Dockerfile"
  target     = "api"
  tags       = ["${PROJECT}-node-backend:api", "${PROJECT}-node-backend:latest"]
}

# ═══════════════════════════════════════════════════════════════════════════
# DEVELOPMENT STACK (integration `make up`)
# ═══════════════════════════════════════════════════════════════════════════

target "php" {
  context    = "."
  dockerfile = "docker/php/Dockerfile"
  target     = "development"
  tags       = ["${PROJECT}-php:development"]
}

target "node" {
  context    = "."
  dockerfile = "docker/node/Dockerfile"
  target     = "development"
  tags       = ["${PROJECT}-node:development"]
}

target "dev-tools" {
  context    = "."
  dockerfile = "docker/node/Dockerfile"
  target     = "development"
  tags       = ["${PROJECT}-dev-tools:development"]
}

target "nginx" {
  context    = "."
  dockerfile = "docker/nginx/Dockerfile"
  target     = "development"
  tags       = ["${PROJECT}-nginx:development"]
}

target "postgres" {
  context    = "."
  dockerfile = "docker/postgres/Dockerfile"
  target     = "final"
  tags       = ["${PROJECT}-postgres:latest"]
}

target "redis" {
  context    = "."
  dockerfile = "docker/redis/Dockerfile"
  target     = "base"
  tags       = ["${PROJECT}-redis:latest"]
}

# ═══════════════════════════════════════════════════════════════════════════
# PRODUCTION IMAGES
# ═══════════════════════════════════════════════════════════════════════════

target "php-production" {
  context    = "."
  dockerfile = "docker/php/Dockerfile"
  target     = "production"
  contexts   = { node-backend-assets = "target:node-backend" }
  tags       = ["${PROJECT}-php:production"]
}

target "nginx-production" {
  context    = "."
  dockerfile = "docker/nginx/Dockerfile"
  target     = "production"
  contexts   = { node-backend-assets = "target:node-backend" }
  tags       = ["${PROJECT}-nginx:production"]
}

# NOTE: postgres has no separate production stage (dev and prod both build the
# `final` target), and compose derives the image name `zappzarapp-postgres`
# (:latest) with no `image:` key — so the shared `postgres` target above serves
# both `make up` and the production stack; no postgres-production target needed.

# ═══════════════════════════════════════════════════════════════════════════
# BUILD-TIME GOSS TEST STAGES (CI only)
# ═══════════════════════════════════════════════════════════════════════════
# GOSS validation runs inside `RUN goss validate` at build time, so a successful
# build IS the passing test — `output = cacheonly` avoids loading throwaway images.

target "php-test" {
  context    = "."
  dockerfile = "docker/php/Dockerfile"
  target     = "test"
  contexts   = { goss = "target:goss" }
  output     = ["type=cacheonly"]
}

target "nginx-test" {
  context    = "."
  dockerfile = "docker/nginx/Dockerfile"
  target     = "test"
  # nginx `test` is FROM production → it also needs the node-backend Vite assets.
  contexts = {
    goss                = "target:goss"
    node-backend-assets = "target:node-backend"
  }
  output = ["type=cacheonly"]
}

target "postgres-test" {
  context    = "."
  dockerfile = "docker/postgres/Dockerfile"
  target     = "test"
  contexts   = { goss = "target:goss" }
  output     = ["type=cacheonly"]
}

target "redis-test" {
  context    = "."
  dockerfile = "docker/redis/Dockerfile"
  target     = "test"
  contexts   = { goss = "target:goss" }
  output     = ["type=cacheonly"]
}

target "node-test-api" {
  context    = "."
  dockerfile = "docker/node/Dockerfile"
  target     = "test-api"
  contexts   = { goss = "target:goss" }
  output     = ["type=cacheonly"]
}

target "node-test-framework" {
  context    = "."
  dockerfile = "docker/node/Dockerfile"
  target     = "test-framework"
  contexts   = { goss = "target:goss" }
  output     = ["type=cacheonly"]
}

# ═══════════════════════════════════════════════════════════════════════════
# GROUPS
# ═══════════════════════════════════════════════════════════════════════════

group "default" {
  targets = ["test"]
}

# Build-time GOSS validation — the target that `make goss-test-build` builds.
# Includes node-test-framework: its node-frontend.yaml spec is framework-agnostic,
# so the frontend Docker stage stays green on the bare boilerplate and after any
# framework is installed. Framework-specific build output is checked at runtime.
group "test" {
  targets = [
    "php-test", "nginx-test", "postgres-test",
    "redis-test", "node-test-api", "node-test-framework",
  ]
}

# Development stack loaded for integration `make up`.
group "dev" {
  targets = ["php", "node", "nginx", "postgres", "redis", "dev-tools"]
}

# Production images (build-production job / `make build` production path).
group "production" {
  targets = ["php-production", "nginx-production", "postgres", "node-backend"]
}
