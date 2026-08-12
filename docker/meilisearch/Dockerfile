# docker/meilisearch/Dockerfile
# Meilisearch - Lightning fast, typo-tolerant search engine
# https://www.meilisearch.com/

ARG MEILISEARCH_VERSION=1.51

FROM getmeili/meilisearch:v${MEILISEARCH_VERSION}

# The image has no dedicated service user; the production preset runs the
# container as uid 1000. Chown the data directory so named volumes inherit
# that ownership on first creation.
RUN mkdir -p /meili_data && chown -R 1000:1000 /meili_data

# Copy entrypoint script for secrets handling
COPY --chmod=0755 docker/meilisearch/entrypoint.sh /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]

# Health check for Meilisearch (HTTPS for zero-trust internal TLS)
# Meilisearch exposes a health endpoint at /health
# Use 127.0.0.1 instead of localhost to avoid IPv6 resolution issues
HEALTHCHECK --interval=10s --timeout=3s --start-period=10s --retries=3 \
    CMD wget --no-verbose --tries=1 --spider --no-check-certificate https://127.0.0.1:7700/health || exit 1
