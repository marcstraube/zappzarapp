#!/bin/bash
# GOSS Preset Runner
# Runs docker compose with correct profiles based on preset configuration
#
# Usage: ./tests/goss/preset-runner.sh <preset-name> <command> [--verbose]
# Example: ./tests/goss/preset-runner.sh fullstack "up -d --wait --build"
#
# Options:
#   --verbose    Show full docker compose output (default: quiet)
#
# Environment:
#   VERBOSE=1    Same as --verbose flag
#
# For development presets (ENV=development):
#   - Checks if dependencies are installed
#   - Installs them if missing (composer, pnpm)
#   - Uses host-mounted volumes
#
# For production presets (ENV=production):
#   - Uses self-contained images
#   - No dependency check needed

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Parse arguments
PRESET="${1:-}"
COMMAND="${2:-}"
VERBOSE="${VERBOSE:-0}"

# Check for --verbose flag
if [[ "${3:-}" == "--verbose" ]] || [[ "${VERBOSE}" == "1" ]]; then
    VERBOSE=1
fi

if [ -z "$PRESET" ] || [ -z "$COMMAND" ]; then
    echo -e "${RED}Usage: $0 <preset-name> <command>${NC}"
    echo "Example: $0 fullstack 'up -d --wait --build'"
    exit 1
fi

ENV_FILE="tests/goss/presets/${PRESET}.env"

if [ ! -f "$ENV_FILE" ]; then
    echo -e "${RED}Error: Preset file not found: $ENV_FILE${NC}"
    exit 1
fi

# Source the preset file to get configuration
# shellcheck disable=SC1090
source "$ENV_FILE"

# ============================================================================
# Development Dependency Check
# ============================================================================
check_and_install_dependencies() {
    local needs_composer=false
    local needs_pnpm=false

    # Check PHP dependencies
    if [ "${ENABLE_PHP:-false}" = "true" ]; then
        if [ ! -d "vendor" ] || [ ! -f "vendor/autoload.php" ]; then
            echo -e "${YELLOW}[preset-runner] PHP dependencies missing (vendor/)${NC}"
            needs_composer=true
        fi
    fi

    # Check Node dependencies
    if [ "${ENABLE_NODE:-false}" = "true" ]; then
        if [ ! -d "node_modules" ] || [ ! -d "node_modules/.pnpm" ]; then
            echo -e "${YELLOW}[preset-runner] Node dependencies missing (node_modules/)${NC}"
            needs_pnpm=true
        fi
    fi

    # Install missing dependencies
    if [ "$needs_composer" = true ]; then
        echo -e "${BLUE}[preset-runner] Installing Composer dependencies...${NC}"
        # Use docker to run composer if available, otherwise fail gracefully
        if docker compose --env-file .env run --rm --no-deps php composer install --no-interaction --prefer-dist 2>/dev/null; then
            echo -e "${GREEN}[preset-runner] Composer dependencies installed${NC}"
        else
            echo -e "${YELLOW}[preset-runner] Could not install via Docker, trying local composer...${NC}"
            if command -v composer &>/dev/null; then
                composer install --no-interaction --prefer-dist
                echo -e "${GREEN}[preset-runner] Composer dependencies installed (local)${NC}"
            else
                echo -e "${RED}[preset-runner] ERROR: Cannot install Composer dependencies${NC}"
                echo -e "${RED}Run 'make composer-install' manually first${NC}"
                exit 1
            fi
        fi
    fi

    if [ "$needs_pnpm" = true ]; then
        echo -e "${BLUE}[preset-runner] Installing Node dependencies...${NC}"
        if command -v pnpm &>/dev/null; then
            pnpm install --frozen-lockfile 2>/dev/null || pnpm install
            echo -e "${GREEN}[preset-runner] Node dependencies installed${NC}"
        else
            echo -e "${RED}[preset-runner] ERROR: pnpm not found${NC}"
            echo -e "${RED}Run 'make pnpm-install' manually first${NC}"
            exit 1
        fi
    fi
}

# ============================================================================
# Profile Calculation
# ============================================================================
calculate_profiles() {
    PROFILES=""

    # Database profile
    if [ "${ENABLE_DATABASE:-false}" = "true" ]; then
        PROFILES="$PROFILES --profile ${DB_TYPE:-postgres}"
    fi

    # PHP profile
    if [ "${ENABLE_PHP:-false}" = "true" ]; then
        PROFILES="$PROFILES --profile php"
    fi

    # Node profiles based on NODE_MODE
    if [ "${ENABLE_NODE:-false}" = "true" ]; then
        case "${NODE_MODE:-assets-api}" in
            assets|idle)
                PROFILES="$PROFILES --profile node"
                ;;
            api)
                PROFILES="$PROFILES --profile node-backend"
                ;;
            assets-api)
                PROFILES="$PROFILES --profile node --profile node-backend"
                ;;
            framework)
                PROFILES="$PROFILES --profile node"
                ;;
            framework-api)
                PROFILES="$PROFILES --profile node --profile node-backend"
                ;;
            *)
                PROFILES="$PROFILES --profile node"
                ;;
        esac
    fi

    # Redis profile
    if [ "${ENABLE_REDIS:-false}" = "true" ]; then
        PROFILES="$PROFILES --profile redis"
    fi

    # Optional services profiles
    if [ "${ENABLE_MERCURE:-false}" = "true" ]; then
        PROFILES="$PROFILES --profile mercure"
    fi
    if [ "${ENABLE_MEILISEARCH:-false}" = "true" ]; then
        PROFILES="$PROFILES --profile meilisearch"
    fi
    if [ "${ENABLE_ELASTICSEARCH:-false}" = "true" ]; then
        PROFILES="$PROFILES --profile elasticsearch"
    fi
    if [ "${ENABLE_MAILPIT:-false}" = "true" ]; then
        PROFILES="$PROFILES --profile mailpit"
    fi
    if [ "${ENABLE_MINIO:-false}" = "true" ]; then
        PROFILES="$PROFILES --profile minio"
    fi
    if [ "${ENABLE_RABBITMQ:-false}" = "true" ]; then
        PROFILES="$PROFILES --profile rabbitmq"
    fi
}

# ============================================================================
# Main
# ============================================================================

# For development presets: check dependencies before starting
if [ "${ENV:-development}" = "development" ]; then
    # Only check dependencies for "up" commands
    if [[ "$COMMAND" == *"up"* ]]; then
        echo -e "${BLUE}[preset-runner] Development mode - checking dependencies...${NC}"
        check_and_install_dependencies
    fi
fi

# Calculate profiles
calculate_profiles

# Determine compose files
COMPOSE_FILES="-f compose.yaml"
if [ "${ENV:-development}" = "production" ]; then
    COMPOSE_FILES="$COMPOSE_FILES -f compose.production.yaml"
else
    # Development uses override
    if [ -f "compose.override.yaml" ]; then
        COMPOSE_FILES="$COMPOSE_FILES -f compose.override.yaml"
    fi
fi

# Show info only in verbose mode
if [ "$VERBOSE" = "1" ]; then
    echo -e "${BLUE}[preset-runner] Preset: $PRESET (${ENV:-development})${NC}"
    echo -e "${BLUE}[preset-runner] Profiles:$PROFILES${NC}"
fi

# Execute docker compose with calculated profiles
# shellcheck disable=SC2086
if [ "$VERBOSE" = "1" ]; then
    exec docker compose $COMPOSE_FILES --env-file "$ENV_FILE" $PROFILES $COMMAND
else
    # Quiet mode: suppress output, show spinner for long operations
    if [[ "$COMMAND" == *"up"* ]]; then
        echo -ne "${BLUE}[preset-runner]${NC} Building and starting ${PRESET}... "
        if docker compose $COMPOSE_FILES --env-file "$ENV_FILE" $PROFILES $COMMAND >/dev/null 2>&1; then
            echo -e "${GREEN}done${NC}"
            exit 0
        else
            echo -e "${RED}failed${NC}"
            echo -e "${YELLOW}Run with VERBOSE=1 for details${NC}"
            exit 1
        fi
    elif [[ "$COMMAND" == *"down"* ]]; then
        echo -ne "${BLUE}[preset-runner]${NC} Stopping ${PRESET}... "
        if docker compose $COMPOSE_FILES --env-file "$ENV_FILE" $PROFILES $COMMAND >/dev/null 2>&1; then
            echo -e "${GREEN}done${NC}"
            exit 0
        else
            echo -e "${RED}failed${NC}"
            exit 1
        fi
    else
        # For other commands, run normally
        exec docker compose $COMPOSE_FILES --env-file "$ENV_FILE" $PROFILES $COMMAND
    fi
fi
