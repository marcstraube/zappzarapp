#!/bin/bash
# docker/hooks/pre-push-check.sh
# Pre-push dependency check - auto-installs if missing
#
# This script ensures dependencies are installed before running pre-push hooks.
# If dependencies are missing (e.g., after reset or fresh clone), it installs them.

set -euo pipefail

# Colors
YELLOW='\033[0;33m'
GREEN='\033[0;32m'
NC='\033[0m' # No Color

check_php_deps() {
    # Check if phpstan exists in the PHP container's vendor volume
    if docker compose run --rm -T php test -f /var/www/html/vendor/bin/phpstan 2>/dev/null; then
        return 0
    fi
    return 1
}

check_node_deps() {
    # Check if node_modules exists in the dev-tools container
    if COMPOSE_PROFILES=tools docker compose run --rm -T dev-tools test -d /app/node_modules/.bin 2>/dev/null; then
        return 0
    fi
    return 1
}

echo -e "${YELLOW}Checking dependencies for pre-push hooks...${NC}"

# Check PHP dependencies
if ! check_php_deps; then
    echo -e "${YELLOW}PHP dependencies missing - installing...${NC}"
    make composer-install
    echo -e "${GREEN}PHP dependencies installed${NC}"
fi

# Check Node dependencies
if ! check_node_deps; then
    echo -e "${YELLOW}Node dependencies missing - installing...${NC}"
    make pnpm-install
    echo -e "${GREEN}Node dependencies installed${NC}"
fi

echo -e "${GREEN}Dependencies OK${NC}"
