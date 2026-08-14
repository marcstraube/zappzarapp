# docker/elasticsearch/Dockerfile
# Elasticsearch - Distributed search and analytics engine
# https://www.elastic.co/elasticsearch/

ARG ELASTICSEARCH_VERSION=9.4.4

FROM docker.elastic.co/elasticsearch/elasticsearch:${ELASTICSEARCH_VERSION}

# Apply available OS security patches. The base OS differs per major line:
# 8.x images are Ubuntu-based (apt-get), 9.x images are RHEL/UBI-based
# (microdnf) — detect the package manager so both lines build. The image
# ships as uid 1000, so switch to root only for the upgrade, then restore.
# Trivy runs with ignore-unfixed, so reported OS-package CVEs have a fix here.
# (The bundled Java JARs — netty, jackson-databind, ... — remain; only an
# upstream Elasticsearch release can update those.)
USER root
# DL3040 is a false positive here: `microdnf clean all` IS in the else
# branch, but hadolint cannot see through the if/else.
# hadolint ignore=DL3040
RUN if command -v apt-get >/dev/null 2>&1; then \
        apt-get update && apt-get upgrade -y && rm -rf /var/lib/apt/lists/*; \
    else \
        microdnf upgrade -y && microdnf clean all; \
    fi \
    # Strip setuid/setgid bits (Dockle CIS-DI-0008); Elasticsearch does not use
    # the base image's OS PAM/shadow helpers.
    && find / -xdev -type f \( -perm -4000 -o -perm -2000 \) -exec chmod -s {} \; \
    # Snapshot repository mountpoint (path.repo): must exist in the image
    # with the server uid as owner, so the named volume mounted here
    # inherits an ownership Elasticsearch (uid 1000) can write to
    && mkdir -p /usr/share/elasticsearch/backup \
    && chown 1000:0 /usr/share/elasticsearch/backup
USER 1000:0

# Health check for Elasticsearch (HTTPS with bootstrap password authentication)
# Uses the bootstrap password from secrets (available before API key
# generation); it travels as a curl config on stdin (-K -), never in argv
# (command lines are visible to other processes in the container)
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=5 \
    CMD printf 'user = "elastic:%s"\n' "$(cat /run/secrets/elasticsearch_bootstrap_password.txt 2>/dev/null || echo '')" \
        | curl -sk -K - "https://localhost:9200/_cluster/health" | grep -q '"status"' || exit 1
