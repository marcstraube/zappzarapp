# docker/elasticsearch/Dockerfile
# Elasticsearch - Distributed search and analytics engine
# https://www.elastic.co/elasticsearch/

ARG ELASTICSEARCH_VERSION=8.19.19

FROM docker.elastic.co/elasticsearch/elasticsearch:${ELASTICSEARCH_VERSION}

# Health check for Elasticsearch (HTTPS with bootstrap password authentication)
# Uses bootstrap password from secrets (available before API key generation)
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=5 \
    CMD curl -sk -u "elastic:$(cat /run/secrets/elasticsearch_bootstrap_password.txt 2>/dev/null || echo '')" \
        "https://localhost:9200/_cluster/health" | grep -q '"status"' || exit 1
