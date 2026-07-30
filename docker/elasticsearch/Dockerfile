# docker/elasticsearch/Dockerfile
# Elasticsearch - Distributed search and analytics engine
# https://www.elastic.co/elasticsearch/

ARG ELASTICSEARCH_VERSION=8.19.19

FROM docker.elastic.co/elasticsearch/elasticsearch:${ELASTICSEARCH_VERSION}

# Apply available OS security patches. The Elasticsearch image is Ubuntu-based;
# it ships as uid 1000, so switch to root only for the upgrade, then restore.
# Trivy runs with ignore-unfixed, so reported OS-package CVEs have a fix here.
# (The bundled Java JARs — netty, jackson-databind, ... — remain; only an
# upstream Elasticsearch release can update those.)
USER root
RUN apt-get update && apt-get upgrade -y \
    && rm -rf /var/lib/apt/lists/* \
    # Strip setuid/setgid bits (Dockle CIS-DI-0008); Elasticsearch does not use
    # the base image's OS PAM/shadow helpers.
    && find / -xdev -type f \( -perm -4000 -o -perm -2000 \) -exec chmod -s {} \;
USER 1000:0

# Health check for Elasticsearch (HTTPS with bootstrap password authentication)
# Uses bootstrap password from secrets (available before API key generation)
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=5 \
    CMD curl -sk -u "elastic:$(cat /run/secrets/elasticsearch_bootstrap_password.txt 2>/dev/null || echo '')" \
        "https://localhost:9200/_cluster/health" | grep -q '"status"' || exit 1
