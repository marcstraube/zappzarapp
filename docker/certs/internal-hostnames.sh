#!/bin/bash
# shellcheck shell=bash
# Single source of truth for the internal service hostnames: the leaf
# certificate SANs (generate-internal.sh) and the CA's nameConstraints
# (generate-ca.sh) must always cover the same set - a name missing here
# fails certificate verification for that service.
# shellcheck disable=SC2034  # consumed by the sourcing scripts
INTERNAL_HOSTNAMES="localhost nginx node node-backend php mariadb postgres redis elasticsearch mailpit meilisearch mercure rabbitmq seaweedfs"
# shellcheck disable=SC2034
INTERNAL_IP="127.0.0.1"
