#!/bin/bash
# docker/hooks/pre-push-quality.sh
# Orchestrates all quality checks for pre-push hook
#
# Strategy:
#   - Type-based checks: Run checks only for changed file types
#   - Branch-aware: Extended checks only on develop/master
#   - Fast feedback: Feature branches get quick checks (~1-3 min)
#   - Full quality: develop/master get comprehensive checks (~5-15 min)

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CHANGE_DETECTOR="$SCRIPT_DIR/change-detector.sh"
HOOK_RUNNER="$SCRIPT_DIR/hook-runner.sh"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Track failures
FAILED_CHECKS=()

# Print section header
print_header() {
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
}

# Run a check and track failures
run_check() {
    local name="$1"
    shift
    local cmd=("$@")

    echo -e "${YELLOW}→ Running: $name${NC}"

    if "${cmd[@]}"; then
        echo -e "${GREEN}✓ $name passed${NC}"
        return 0
    else
        echo -e "${RED}✗ $name failed${NC}"
        FAILED_CHECKS+=("$name")
        return 1
    fi
}

# Check if we're on develop/master
is_extended_branch() {
    "$CHANGE_DETECTOR" on-branch develop master
}

# Check if file type changed
has_changes() {
    "$CHANGE_DETECTOR" has-changes "$1"
}

# ============================================================================
# 1. ALWAYS RUN (Dependency Validation)
# ============================================================================

print_header "Always: Dependency Validation"

# Composer validation (if composer.json or composer.lock changed)
if has_changes composer; then
    echo -e "${YELLOW}→ composer.json or composer.lock changed${NC}"
    run_check "Composer validate" \
        docker compose exec -T php composer validate || true
fi

# PNPM audit (if package.json or pnpm-lock.yaml changed)
if has_changes package; then
    echo -e "${YELLOW}→ package.json or pnpm-lock.yaml changed${NC}"
    run_check "PNPM audit" \
        "$HOOK_RUNNER" dev-tools pnpm audit --audit-level moderate || true
fi

# ============================================================================
# 2. TYPE-BASED CHECKS (All Branches)
# ============================================================================

print_header "Type-Based: Fast Quality Checks"

# PHP: Basic checks
if has_changes php; then
    echo -e "${YELLOW}→ PHP files changed${NC}"

    run_check "PHPStan" \
        "$HOOK_RUNNER" php vendor/bin/phpstan analyse --configuration=phpstan.neon --memory-limit=1G || true

    run_check "PHPUnit" \
        "$HOOK_RUNNER" php vendor/bin/phpunit || true
fi

# Node: Basic checks
if has_changes node; then
    echo -e "${YELLOW}→ Node files changed${NC}"

    run_check "TypeScript type-check" \
        "$HOOK_RUNNER" dev-tools pnpm run type-check || true

    run_check "ESLint" \
        "$HOOK_RUNNER" dev-tools pnpm run lint || true

    run_check "Vitest" \
        "$HOOK_RUNNER" dev-tools pnpm test || true
fi

# SQL: Linting
if has_changes sql; then
    echo -e "${YELLOW}→ SQL files changed${NC}"

    run_check "SQL lint" \
        make lint-sql || true
fi

# Shell: Linting
if has_changes shell; then
    echo -e "${YELLOW}→ Shell files changed${NC}"

    run_check "ShellCheck" \
        make lint-shell || true
fi

# Docker: Linting
if has_changes docker; then
    echo -e "${YELLOW}→ Docker files changed${NC}"

    run_check "hadolint (Dockerfile lint)" \
        make lint-docker || true
fi

# Compose: Validation
if has_changes compose; then
    echo -e "${YELLOW}→ Docker Compose files changed${NC}"

    run_check "Docker Compose validate" \
        make compose-validate || true
fi

# Markdown: Linting
if has_changes markdown; then
    echo -e "${YELLOW}→ Markdown files changed${NC}"

    run_check "Markdownlint" \
        "$HOOK_RUNNER" dev-tools pnpm run lint:md || true
fi

# Config YAML: Linting
if has_changes config; then
    echo -e "${YELLOW}→ Config files changed${NC}"

    run_check "YAML lint" \
        make lint-config || true
fi

# ============================================================================
# 3. EXTENDED CHECKS (develop/master only)
# ============================================================================

if is_extended_branch; then
    print_header "Extended: Comprehensive Quality Checks (develop/master only)"

    # PHP: Extended checks
    if has_changes php; then
        echo -e "${YELLOW}→ PHP files changed (extended checks)${NC}"

        run_check "PHPMD" \
            "$HOOK_RUNNER" php vendor/bin/phpmd src,tests text phpmd.xml.dist || true

        run_check "Rector" \
            "$HOOK_RUNNER" php vendor/bin/rector process --dry-run || true
    fi

    # Node: Extended checks
    if has_changes node; then
        echo -e "${YELLOW}→ Node files changed (extended checks)${NC}"

        run_check "Prettier check" \
            "$HOOK_RUNNER" dev-tools pnpm run format:check || true

        run_check "depcheck" \
            "$HOOK_RUNNER" dev-tools pnpm exec depcheck || true

        run_check "knip" \
            "$HOOK_RUNNER" dev-tools pnpm exec knip || true
    fi

    # Docker: Extended checks (GOSS tests)
    if has_changes docker || has_changes compose; then
        echo -e "${YELLOW}→ Docker environment changed (extended checks)${NC}"

        run_check "GOSS build tests" \
            make goss-test-build || true

        run_check "GOSS runtime tests" \
            make goss-test || true
    fi

    # Security: Static configuration checks
    if has_changes docker || has_changes nginx || has_changes compose; then
        echo -e "${YELLOW}→ Security configuration changed (extended checks)${NC}"

        run_check "Trivy config scan" \
            docker run --rm -v "$(pwd):/project" aquasec/trivy:latest config /project/docker --exit-code 0 --severity HIGH,CRITICAL || true

        run_check "Trivy secret scan" \
            docker run --rm -v "$(pwd):/project" aquasec/trivy:latest fs --scanners secret /project --exit-code 0 || true
    fi

    # Security: CSP syntax validation
    if has_changes php; then
        if grep -r "CspNonceHelper" src/php/App/Security/ >/dev/null 2>&1; then
            echo -e "${YELLOW}→ CSP configuration may have changed${NC}"

            run_check "CSP syntax validation" \
                docker compose exec -T php php -r "require 'vendor/autoload.php'; \
                    \$dev = \App\Security\CspNonceHelper::buildDevelopmentCspHeader(); \
                    \$prod = \App\Security\CspNonceHelper::buildProductionCspHeader(); \
                    echo 'Development CSP: ' . \$dev . PHP_EOL; \
                    echo 'Production CSP: ' . \$prod . PHP_EOL; \
                    if (empty(\$dev) || empty(\$prod)) { exit(1); }" || true
        fi
    fi

    # Always run linters on develop/master (consistency)
    echo -e "${YELLOW}→ Consistency checks (always on develop/master)${NC}"

    if ! has_changes sql; then
        run_check "SQL lint (consistency)" \
            make lint-sql || true
    fi

    if ! has_changes shell; then
        run_check "ShellCheck (consistency)" \
            make lint-shell || true
    fi

    if ! has_changes docker; then
        run_check "hadolint (consistency)" \
            make lint-docker || true
    fi

    if ! has_changes markdown; then
        run_check "Markdownlint (consistency)" \
            "$HOOK_RUNNER" dev-tools pnpm run lint:md || true
    fi

    if ! has_changes config; then
        run_check "YAML lint (consistency)" \
            make lint-config || true
    fi
else
    echo ""
    echo -e "${BLUE}ℹ Extended checks skipped (feature branch)${NC}"
    echo -e "${BLUE}  → Fast checks only for quick feedback${NC}"
    echo -e "${BLUE}  → Full checks will run on develop/master${NC}"
fi

# ============================================================================
# SUMMARY
# ============================================================================

print_header "Pre-Push Quality Check Summary"

if [[ ${#FAILED_CHECKS[@]} -eq 0 ]]; then
    echo -e "${GREEN}✓ All checks passed!${NC}"
    echo ""
    exit 0
else
    echo -e "${RED}✗ ${#FAILED_CHECKS[@]} check(s) failed:${NC}"
    for check in "${FAILED_CHECKS[@]}"; do
        echo -e "${RED}  - $check${NC}"
    done
    echo ""
    echo -e "${YELLOW}Fix the issues above before pushing.${NC}"
    echo ""
    exit 1
fi
