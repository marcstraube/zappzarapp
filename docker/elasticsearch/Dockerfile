# docker/elasticsearch/Dockerfile
# Elasticsearch - Distributed search and analytics engine
# https://www.elastic.co/elasticsearch/

ARG ELASTICSEARCH_VERSION=8.17.0

FROM docker.elastic.co/elasticsearch/elasticsearch:${ELASTICSEARCH_VERSION}

# Health check for Elasticsearch
# Uses the cluster health API with local node check
HEALTHCHECK --interval=15s --timeout=5s --start-period=60s --retries=5 \
    CMD curl -sf http://localhost:9200/_cluster/health?local=true | grep -qE '"status":"(green|yellow)"' || exit 1
