SHELL := bash
.SHELLFLAGS := -c

# Docker Compose with plain progress output to avoid terminal corruption
DC := docker compose --progress=plain

# Run commands always use development context with all required profiles
# This ensures make pnpm/composer/etc. work regardless of .env settings
DC_RUN := COMPOSE_PROFILES=php,node,node-backend,dev-tools $(DC)

# Multi-image builds via docker buildx bake (docker-bake.hcl). Cross-image
# `COPY --from` resolves through named contexts (target:<name>) on a
# docker-container builder. The builder is auto-created on first use by
# `.buildx-ensure`.
BUILDX_BUILDER ?= zappbake
BAKE := docker buildx bake -f docker-bake.hcl --builder $(BUILDX_BUILDER)

# Browser opener: xdg-open (Linux), open (macOS), start (Windows/Git Bash)
OPEN_CMD := $(shell command -v xdg-open || command -v open || echo start)

# Alpine image for utility operations (ownership fixes, cleanup)
ALPINE_IMAGE ?= alpine:3.21

# SQL linter image (pinned: ':latest' made CI and local caches drift apart,
# e.g. new rules appearing only in CI - update deliberately, then run lint-sql)
SQLFLUFF_IMAGE ?= sqlfluff/sqlfluff:4.2.2

# ZAPPZARAPP_ENV supplied by the caller must win over the env files -- this is the
# documented `ZAPPZARAPP_ENV=production make ...` contract. Only the two supported
# values are honored: a command-line ZAPPZARAPP_ENV=... with any other value is a
# hard error (likely a typo), while an unsupported value inherited from the
# environment is ignored with a warning (a stray exported variable must not
# flip the build mode).
ifneq ($(strip $(ZAPPZARAPP_ENV)),)
  ifneq ($(filter $(ZAPPZARAPP_ENV),development production),)
    CALLER_ENV := $(ZAPPZARAPP_ENV)
  else ifeq ($(origin ZAPPZARAPP_ENV),command line)
    $(error Invalid ZAPPZARAPP_ENV '$(ZAPPZARAPP_ENV)' (supported: development, production))
  else
    $(warning Ignoring ZAPPZARAPP_ENV='$(ZAPPZARAPP_ENV)' from the environment (supported: development, production); using .env)
  endif
endif

# Load environment files in correct order:
# 1. .env (team defaults)
# 2. .env.production (if ZAPPZARAPP_ENV=production)
# 3. .env.local (local overrides)
# A caller-supplied ZAPPZARAPP_ENV (see CALLER_ENV above) beats all three files.
# A missing .env is tolerated here so targets fall back to defaults;
# validate-env is the place that reports it loudly.
define LOAD_ENV
[ -f .env ] && . ./.env || true; \
$(if $(CALLER_ENV),export ZAPPZARAPP_ENV="$(CALLER_ENV)"; )[ "$${ZAPPZARAPP_ENV:-development}" = "production" ] && [ -f .env.production ] && . ./.env.production || true; \
[ -f .env.local ] && . ./.env.local || true$(if $(CALLER_ENV),; export ZAPPZARAPP_ENV="$(CALLER_ENV)")
endef

# Helper to check development mode (guards dev-only targets)
define require_development
	@$(LOAD_ENV) && \
	if [ "$${ZAPPZARAPP_ENV:-development}" = "production" ]; then \
		echo -e "\033[0;31mError: $(1) is only available in development mode\033[0m"; \
		echo -e "\033[0;33mSet ZAPPZARAPP_ENV=development in .env to enable\033[0m"; \
		exit 1; \
	fi
endef

.PHONY: $(shell awk '/^[a-zA-Z0-9_-]+:.*## / { print $$1 }' $(MAKEFILE_LIST) | sed 's/://')

help: ## Show this help (FILTER=? for categories, FILTER=<name> to filter)
	@if [ "$(FILTER)" = "?" ] || [ "$(FILTER)" = "list" ]; then \
		printf "\n\033[0;34mAvailable categories:\033[0m\n"; \
		grep -oP '(?<=^##@ ).*' $(MAKEFILE_LIST) | while read -r cat; do \
			printf "  \033[0;32m%s\033[0m\n" "$$cat"; \
		done; \
		printf "\n\033[0;90mUsage: make help FILTER=<category>\033[0m\n"; \
	else \
		awk -v filter="$(FILTER)" 'BEGIN { \
			FS = ":.*?## "; \
			if (filter == "") { \
				printf "\n\033[0;34mAvailable commands:\033[0m\n"; \
			} else { \
				printf "\n\033[0;34mCommands matching \"%s\":\033[0m\n", filter; \
			} \
			show = (filter == "") ? 1 : 0; \
		} \
		/^##@/ { \
			if (show && length(cmds) > 0) { \
				print cmds | "sort"; \
				close("sort"); \
				cmds = ""; \
			} \
			category = substr($$0, 5); \
			if (filter == "" || tolower(category) ~ tolower(filter)) { \
				show = 1; \
				printf "\n\033[0;34m%s\033[0m\n", category; \
			} else { \
				show = 0; \
			} \
			next; \
		} \
		show && /^[a-zA-Z0-9_-]+:.*## / { \
			cmds = cmds $$1 "\t" $$2 "\n"; \
		} \
		END { \
			if (show && length(cmds) > 0) { \
				print cmds | "sort"; \
				close("sort"); \
			} \
			if (filter != "") { \
				printf "\n\033[0;90mCategories: "; \
				system("grep -oP \"(?<=^##@ ).*\" " ARGV[1] " | tr \"\\n\" \",\" | sed \"s/,$$//; s/,/, /g\""); \
				printf "\033[0m\n"; \
			} else { \
				printf "\n\033[0;90mTip: Use FILTER=? to list categories, FILTER=<name> to filter\033[0m\n"; \
			} \
		}' $(MAKEFILE_LIST) | awk 'BEGIN {FS="\t"} NF==2 {printf "  \033[0;32m%-35s\033[0m %s\n", $$1, $$2} NF!=2 {print}'; \
	fi

##@ Setup

composer-install: ## Install Composer dependencies (Docker - guaranteed consistency)
	@echo -e "\033[0;33mInstalling Composer dependencies (Docker)...\033[0m"
	@# Fix bind mount bug: if lockfile is directory or has wrong ownership, fix via Docker
	@# Note: Use USER_ID/GROUP_ID from .env (not host user) for Docker container compatibility
	@$(LOAD_ENV) && \
	if [ -d composer.lock ] || [ ! -f composer.lock ] || [ -f composer.lock -a ! -s composer.lock ]; then \
		docker run --rm -v "$(PWD):/app" -w /app $(ALPINE_IMAGE) sh -c \
			"rm -rf composer.lock && echo '{}' > composer.lock && chown $${USER_ID:-1000}:$${GROUP_ID:-1000} composer.lock"; \
	fi
	@XDEBUG_MODE=off $(DC_RUN) run --rm --no-TTY php composer install --prefer-dist --no-interaction
	@echo -e "\033[0;32mDependencies installed!\033[0m"

composer-install-local: ## Install/update Composer dependencies (Local - IDE code completion only)
	@if command -v composer >/dev/null 2>&1; then \
		echo -e "\033[0;33m⚠️  Using local Composer (version may differ from Docker).\033[0m"; \
		if [ ! -f "vendor/autoload.php" ]; then \
			composer install --prefer-dist --no-interaction --ignore-platform-reqs && \
			echo -e "\033[0;32mDependencies installed!\033[0m"; \
		else \
			composer install --prefer-dist --no-interaction --no-scripts --ignore-platform-reqs && \
			echo -e "\033[0;32mDependencies installed!\033[0m"; \
		fi; \
	else \
		echo -e "\033[0;31mError: Local Composer not found. Use 'make composer-install' instead.\033[0m"; \
		exit 1; \
	fi

composer-sync: ## Sync Composer dependencies (after composer.json changes)
	@echo -e "\033[0;33mSyncing Composer dependencies...\033[0m"
	@XDEBUG_MODE=off $(DC_RUN) run --rm --no-TTY php composer install --prefer-dist --no-interaction
	@echo -e "\033[0;32mDependencies synced!\033[0m"

lockfiles-sync: ## Sync both Composer and pnpm lockfiles (after branch switch, fresh clone)
	@echo -e "\033[0;33mSyncing all lockfiles...\033[0m"
	@$(MAKE) --silent composer-sync
	@$(MAKE) --silent pnpm-sync
	@echo -e "\033[0;32mAll lockfiles synced!\033[0m"

hooks-install: ## Install Git hooks using CaptainHook
	@if [ ! -f vendor/bin/captainhook ]; then \
		echo -e "\033[0;31mError: CaptainHook not found. Please ensure vendor dependencies are installed.\033[0m"; \
		exit 1; \
	fi
	@echo -e "\033[0;33mInstalling Git hooks with CaptainHook...\033[0m"
	@vendor/bin/captainhook install
	@./docker/hooks/install-worktree-guard.sh
	@echo -e "\033[0;32mGit hooks installed successfully in .git/hooks/!\033[0m"

init: ## Initialize project (create .env.local for local overrides) - Run this first!
	@echo -e "\033[0;33mInitializing local configuration...\033[0m"
	@if [ ! -f .env.local ]; then \
		sed -e "s/^USER_ID=.*/USER_ID=$$(id -u)/" \
		    -e "s/^GROUP_ID=.*/GROUP_ID=$$(id -g)/" \
		    .env.local.example > .env.local; \
		echo -e "\033[0;32m.env.local created from .env.local.example with your USER_ID=$$(id -u) and GROUP_ID=$$(id -g)\033[0m"; \
	else \
		echo -e "\033[0;34m.env.local already exists. Skipped.\033[0m"; \
	fi
	@echo -e "\033[0;32mInitialization complete!\033[0m"
	@echo -e "\033[0;34mNext: Run 'make setup' to build containers and install dependencies.\033[0m"

setup: ## Create directories, install dependencies (BOILERPLATE=1 to force file swaps)
	@if [ ! -f .env.local ]; then \
		echo -e "\033[0;33m.env.local not found. This file stores your local USER_ID/GROUP_ID.\033[0m"; \
		echo -e "\033[0;33mWithout it, defaults from .env are used (USER_ID=1000, GROUP_ID=1000).\033[0m"; \
		echo ""; \
		echo "  [i] Run 'make init' to auto-detect your IDs (recommended)"; \
		echo "  [c] Continue with defaults"; \
		echo ""; \
		read -p "Choice [i/c]: " choice; \
		case "$$choice" in \
			i|I) $(MAKE) init;; \
			c|C) echo "Continuing with defaults...";; \
			*) echo "Invalid choice. Running 'make init'..."; $(MAKE) init;; \
		esac; \
	fi
	@echo -e "\033[0;33mCreating project structure...\033[0m"

	# Source directories (top-level only - subdirs have existing code)
	# These must exist for server configs (nginx, php-fpm) even if user deletes code
	@mkdir -p src/php/App src/php/DevDashboard
	@mkdir -p src/node/backend src/node/frontend

	# Resources directories (Vite config references these)
	@mkdir -p resources/{js,css,images,fonts}

	# Public directory (Web root - nginx serves this)
	@mkdir -p public/build

	# Tests (paths referenced in vitest.config.ts, tsconfig.json, composer.json)
	@mkdir -p tests/php/App tests/php/DevDashboard
	@mkdir -p tests/node/backend

	# Build & Coverage directories (excluded from IDE indexing)
	@# Fix ownership FIRST if root-owned (from container test/coverage operations)
	@# Note: Use USER_ID/GROUP_ID from .env (not host user) for Docker container compatibility
	@if [ -d build ] && find build -user root 2>/dev/null | grep -q .; then \
		$(LOAD_ENV) && docker run --rm -v "$(PWD)/build:/build" $(ALPINE_IMAGE) chown -R $${USER_ID:-1000}:$${GROUP_ID:-1000} /build; \
	fi
	@mkdir -p build/coverage/{php,node} build/vitest-report dist

	# Config & Templates (app bootstrap references these)
	@mkdir -p config templates

	# Lockfiles (must exist as FILES before Docker bind mounts, otherwise Docker creates directories)
	@# Fix bind mount bug: remove if directories, ensure files exist with correct ownership
	@# Note: Use USER_ID/GROUP_ID from .env (not host user) for Docker container compatibility
	@if [ -d composer.lock ] || [ -d pnpm-lock.yaml ] || [ ! -f composer.lock ] || [ ! -f pnpm-lock.yaml ]; then \
		$(LOAD_ENV) && \
		docker run --rm -v "$(PWD):/app" -w /app $(ALPINE_IMAGE) sh -c \
			"rm -rf composer.lock pnpm-lock.yaml && touch composer.lock pnpm-lock.yaml && chown $${USER_ID:-1000}:$${GROUP_ID:-1000} composer.lock pnpm-lock.yaml"; \
	fi

	# SSL/TLS Certificates
	@mkdir -p docker/certs/{ca,nginx,internal}

	# Documentation Output & Tools
	@mkdir -p docs/api/{php,node} tools

	# Storage (Runtime data) - Set permissions
	@$(LOAD_ENV) && mkdir -p $${STORAGE_DIR:-./storage}/{app/{uploads,generated},cache,sessions,logs}
	@# Fix ownership if root-owned (from container operations) - only for default ./storage
	@# Note: Use USER_ID/GROUP_ID from .env (not host user) for Docker container compatibility
	@if [ -d storage ] && find storage -user root 2>/dev/null | grep -q .; then \
		$(LOAD_ENV) && docker run --rm -v "$(PWD)/storage:/storage" $(ALPINE_IMAGE) chown -R $${USER_ID:-1000}:$${GROUP_ID:-1000} /storage; \
	fi
	@$(LOAD_ENV) && chmod 770 $${STORAGE_DIR:-./storage} -R 2>/dev/null || true

	# Backups directory (encrypted backups for all services)
	@mkdir -p backups/{db,seaweedfs,rabbitmq,elasticsearch}
	@# Fix ownership if root-owned (from container backup operations)
	@# Note: Use USER_ID/GROUP_ID from .env (not host user) for Docker container compatibility
	@if find backups -user root 2>/dev/null | grep -q .; then \
		$(LOAD_ENV) && docker run --rm -v "$(PWD)/backups:/backups" $(ALPINE_IMAGE) chown -R $${USER_ID:-1000}:$${GROUP_ID:-1000} /backups; \
	fi
	@chmod 700 backups backups/* 2>/dev/null || true

	# Project AI knowledge directory - created in boilerplate mode only (see below)
	# Contributors use .zappzarapp/ai/ directly

	# Claude context directory (agent context)
	# zappzarapp.md = platform context (tracked, synced from upstream — never edited)
	# project.md    = app context (swapped for a clean template in boilerplate mode below)
	@mkdir -p .claude/context

	# Config example (personal_knowledge_path)
	@if [ ! -f .claude/config.local.md.example ]; then \
		cp .zappzarapp/ai/templates/config.local.md.example .claude/; \
	fi

	@echo -e "\033[0;32mProject structure created!\033[0m"

	# Boilerplate file swaps (README, CLAUDE.md, CHANGELOG)
	# Auto-detect: origin OR upstream → marcstraube/zappzarapp = contributor mode
	# Note: zappzarapp remote is for boilerplate users (added by boilerplate-sync)
	# Override: BOILERPLATE=1 make setup → force boilerplate mode (do swaps)
	@IS_BOILERPLATE_MODE=""; \
	IS_CONTRIBUTOR_MODE=""; \
	if [ "$(BOILERPLATE)" = "1" ]; then \
		IS_BOILERPLATE_MODE="true"; \
		echo -e "\033[0;34mBoilerplate mode forced via BOILERPLATE=1\033[0m"; \
	elif git remote get-url origin 2>/dev/null | grep -qE 'marcstraube/zappzarapp'; then \
		IS_CONTRIBUTOR_MODE="true"; \
		echo -e "\033[0;36m✓ Detected zappzarapp contributor (origin) - skipping file swaps\033[0m"; \
	elif git remote get-url upstream 2>/dev/null | grep -qE 'marcstraube/zappzarapp'; then \
		IS_CONTRIBUTOR_MODE="true"; \
		echo -e "\033[0;36m✓ Detected zappzarapp contributor (upstream) - skipping file swaps\033[0m"; \
	else \
		IS_BOILERPLATE_MODE="true"; \
	fi; \
	if [ -n "$$IS_BOILERPLATE_MODE" ]; then \
		if grep -q "zappzarapp-boilerplate-readme" README.md 2>/dev/null; then \
			echo -e "\033[0;33mSetting up project README...\033[0m"; \
			cp .zappzarapp/README.template.md README.md; \
			echo -e "\033[0;32mREADME.md replaced with project template.\033[0m"; \
		fi; \
		if grep -q "zappzarapp-boilerplate-claude" .claude/CLAUDE.md 2>/dev/null; then \
			echo -e "\033[0;33mSetting up Claude configuration...\033[0m"; \
			mv .claude/CLAUDE.md .zappzarapp/ai/CLAUDE.md; \
			cp .zappzarapp/ai/templates/CLAUDE.md .claude/CLAUDE.md; \
			echo -e "\033[0;32mCLAUDE.md replaced with generic template.\033[0m"; \
		fi; \
		if grep -q "zappzarapp-boilerplate-agents" AGENTS.md 2>/dev/null; then \
			echo -e "\033[0;33mSetting up AGENTS.md...\033[0m"; \
			mv AGENTS.md .zappzarapp/ai/AGENTS.md; \
			cp .zappzarapp/ai/templates/AGENTS.md AGENTS.md; \
			echo -e "\033[0;32mAGENTS.md replaced with generic template.\033[0m"; \
		fi; \
		if grep -q "zappzarapp-boilerplate-context" .claude/context/project.md 2>/dev/null; then \
			echo -e "\033[0;33mSetting up project context...\033[0m"; \
			mv .claude/context/project.md .zappzarapp/ai/context-project.md; \
			cp .zappzarapp/ai/templates/PROJECT.md .claude/context/project.md; \
			echo -e "\033[0;32mproject.md replaced with app-context template.\033[0m"; \
		fi; \
		if grep -q "zappzarapp - Changelog" CHANGELOG.md 2>/dev/null; then \
			echo -e "\033[0;33mSetting up CHANGELOG...\033[0m"; \
			mv CHANGELOG.md .zappzarapp/CHANGELOG.md; \
			cp .zappzarapp/CHANGELOG.template.md CHANGELOG.md; \
			echo -e "\033[0;32mCHANGELOG.md replaced with generic template.\033[0m"; \
		fi; \
		if [ ! -d .ai ] || [ ! -f .ai/LEARNINGS.md ]; then \
			echo -e "\033[0;33mSetting up project AI knowledge directory...\033[0m"; \
			mkdir -p .ai; \
			[ ! -f .ai/LEARNINGS.md ] && cp .zappzarapp/ai/templates/LEARNINGS.md .ai/; \
			echo -e "\033[0;32m.ai/ created with LEARNINGS inbox.\033[0m"; \
		fi; \
		if [ ! -d docs/adr ]; then \
			echo -e "\033[0;33mSetting up ADR directory...\033[0m"; \
			mkdir -p docs/adr; \
			cp .zappzarapp/ai/templates/adr/0000-template.md docs/adr/; \
			cp .zappzarapp/ai/templates/adr/README.md docs/adr/; \
			echo -e "\033[0;32mdocs/adr/ created (one document per ADR).\033[0m"; \
		fi; \
		for maint in $(BOILERPLATE_MAINTAINER_ONLY); do \
			if [ -f "$$maint" ]; then \
				rm -f "$$maint"; \
				echo -e "\033[0;32mRemoved maintainer-only file: $$maint\033[0m"; \
			fi; \
		done; \
	fi; \
	if [ -n "$$IS_CONTRIBUTOR_MODE" ]; then \
		$(MAKE) --silent ide-unlock; \
		echo -e "\033[0;36mℹ️  Contributor mode: IDE config files unlocked for committing\033[0m"; \
	fi

	# SSL/TLS Certificate Check
	@echo -e "\033[0;33mChecking SSL/TLS certificates...\033[0m"
	@if [ ! -f docker/certs/nginx/cert.crt ]; then \
		echo -e "\033[0;34mSSL certificates not found. Generating CA-signed certificates...\033[0m"; \
		$(MAKE) --silent ssl-internal; \
	else \
		echo -e "\033[0;32m✓ SSL certificates exist\033[0m"; \
	fi

	# Generate Docker Secrets (file-based)
	@$(MAKE) --silent secrets

	# Docker-dependent steps (skip with CI_TEST=1 for BATS integration tests)
	@if [ "$${CI_TEST:-}" != "1" ]; then \
		echo -e "\033[0;33mBuilding Docker images...\033[0m"; \
		$(MAKE) --silent build; \
		echo -e "\033[0;33mInstalling dependencies...\033[0m"; \
		$(MAKE) --silent composer-install; \
		$(MAKE) --silent pnpm-install; \
		echo -e "\033[0;33mStarting containers...\033[0m"; \
		$(MAKE) --silent up; \
		echo -e "\033[0;33mRunning database migrations...\033[0m"; \
		$(MAKE) --silent db-migrations 2>/dev/null || echo -e "\033[0;34mNo migrations to run or database not ready yet.\033[0m"; \
		echo -e "\033[0;33mGenerating API documentation...\033[0m"; \
		$(MAKE) --silent docs 2>/dev/null || echo -e "\033[0;34mAPI docs generation skipped (tools not yet available).\033[0m"; \
		$(MAKE) --silent ide-config; \
		echo -e "\033[0;33mSetting up local development tools...\033[0m"; \
		if command -v composer >/dev/null 2>&1; then \
			echo -e "\033[0;34m  Installing local PHP dependencies (for IDE + Git hooks)...\033[0m"; \
			$(MAKE) --silent composer-install-local 2>/dev/null && \
			if [ -f vendor/bin/captainhook ]; then \
				echo -e "\033[0;34m  Installing Git hooks (captainhook)...\033[0m"; \
				vendor/bin/captainhook install --force --skip-existing 2>/dev/null && \
				echo -e "\033[0;32m  ✓ Git hooks installed\033[0m"; \
			fi; \
		else \
			echo -e "\033[0;33m  ⚠ Local composer not found - skipping local PHP dependencies\033[0m"; \
			echo -e "\033[0;33m    To enable Git hooks manually:\033[0m"; \
			echo -e "\033[0;33m      1. Install composer: https://getcomposer.org/download/\033[0m"; \
			echo -e "\033[0;33m      2. make composer-install-local\033[0m"; \
			echo -e "\033[0;33m      3. vendor/bin/captainhook install\033[0m"; \
		fi; \
	else \
		echo -e "\033[0;34mSkipping Docker-dependent steps (CI_TEST=1)...\033[0m"; \
	fi

	# Optional: Trust internal CA for browser HTTPS (requires sudo)
	# Skip in CI mode (non-interactive) and when CA cert doesn't exist
	@if [ "$${CI_TEST:-}" != "1" ] && [ -f docker/certs/ca/ca.crt ]; then \
		echo ""; \
		echo -e "\033[0;33mTrust internal CA for browser HTTPS? (avoids certificate warnings)\033[0m"; \
		echo -e "\033[0;34mThis requires sudo and modifies your system's certificate store.\033[0m"; \
		read -p "Trust CA now? [y/N]: " trust_ca; \
		case "$$trust_ca" in \
			y|Y|yes|Yes|YES) \
				$(MAKE) ssl-trust-ca || echo -e "\033[0;33m⚠ CA trust failed - you can run 'make ssl-trust-ca' manually later\033[0m";; \
			*) \
				echo -e "\033[0;34mSkipped. Run 'make ssl-trust-ca' later to trust the CA.\033[0m";; \
		esac; \
	fi

	@echo ""
	@echo -e "\033[0;32m╔════════════════════════════════════════════════════════════╗\033[0m"
	@echo -e "\033[0;32m║ Setup complete!                                            ║\033[0m"
	@echo -e "\033[0;32m╠════════════════════════════════════════════════════════════╣\033[0m"
	@echo -e "\033[0;32m║\033[0m Your environment is up and running. Next steps:            \033[0;32m║\033[0m"
	@echo -e "\033[0;32m║\033[0m   make open-app     \033[0;34mOpen the app in your browser\033[0m           \033[0;32m║\033[0m"
	@echo -e "\033[0;32m║\033[0m   make down / up    \033[0;34mStop / start it later\033[0m                  \033[0;32m║\033[0m"
	@echo -e "\033[0;32m╚════════════════════════════════════════════════════════════╝\033[0m"

ide-config: ## Configure all IDE database connections (PHPStorm + VS Code)
	@$(MAKE) --silent ide-config-phpstorm
	@$(MAKE) --silent ide-config-vscode
	@$(MAKE) --silent ide-lock

ide-config-full: ## Update all IDE configs with custom ports from .env
	@$(MAKE) --silent ide-config-phpstorm-full
	@$(MAKE) --silent ide-config-vscode-full

ide-config-phpstorm: ## Configure PHPStorm database connections (.idea/dataSources.local.xml)
	@if [ ! -d .idea ]; then \
		echo -e "\033[0;33mSkipping PHPStorm config (.idea directory not found)\033[0m"; \
		exit 0; \
	fi
	@if [ ! -f secrets/db_password.txt ]; then \
		echo -e "\033[0;33mSkipping PHPStorm config (secrets not generated yet)\033[0m"; \
		exit 0; \
	fi
	@echo -e "\033[0;33mConfiguring PHPStorm database connections...\033[0m"
	@$(LOAD_ENV) && { \
		DB_NAME_VAL="$${DB_NAME:-app}"; \
		REMOTE_DB_NAME="$${DB_REMOTE_NAME:-production}"; \
		echo '<?xml version="1.0" encoding="UTF-8"?>'; \
		echo '<project version="4">'; \
		echo '  <component name="dataSourceStorageLocal" created-in="IntelliJ IDEA">'; \
		echo '    <!-- Local Development Databases -->'; \
		echo '    <data-source name="PostgreSQL (Docker)" uuid="postgres-zappzarapp">'; \
		echo '      <database-info product="" version="" jdbc-version="" driver-name="" driver-version="" dbms="POSTGRES" />'; \
		echo "      <user-name>$${DB_USER:-app}</user-name>"; \
		echo "      <schema-pattern>$${DB_NAME_VAL}.public</schema-pattern>"; \
		echo "      <default-schemas>$${DB_NAME_VAL}.public</default-schemas>"; \
		echo '    </data-source>'; \
		echo '    <data-source name="MariaDB (Docker)" uuid="mariadb-zappzarapp">'; \
		echo '      <database-info product="" version="" jdbc-version="" driver-name="" driver-version="" dbms="MARIADB" />'; \
		echo "      <user-name>$${DB_USER:-app}</user-name>"; \
		echo "      <schema-pattern>$${DB_NAME_VAL}.*</schema-pattern>"; \
		echo "      <default-schemas>$${DB_NAME_VAL}.*</default-schemas>"; \
		echo '    </data-source>'; \
		echo '    <!-- Remote Databases -->'; \
		echo '    <data-source name="PostgreSQL (Remote)" uuid="postgres-remote-zappzarapp">'; \
		echo '      <database-info product="" version="" jdbc-version="" driver-name="" driver-version="" dbms="POSTGRES" />'; \
		echo "      <user-name>$${DB_REMOTE_USER:-$${DB_USER:-app}}</user-name>"; \
		echo "      <schema-pattern>$${REMOTE_DB_NAME}.public</schema-pattern>"; \
		echo "      <default-schemas>$${REMOTE_DB_NAME}.public</default-schemas>"; \
		if [ -n "$${DB_REMOTE_SSH_HOST}" ]; then \
			echo '      <ssh-properties>'; \
			echo '        <enabled>true</enabled>'; \
			echo "        <proxy-host>$${DB_REMOTE_SSH_HOST}</proxy-host>"; \
			echo "        <proxy-port>$${DB_REMOTE_SSH_PORT:-22}</proxy-port>"; \
			echo "        <user>$${DB_REMOTE_SSH_USER}</user>"; \
			echo '        <use-password>false</use-password>'; \
			echo "        <private-key-path>$${DB_REMOTE_SSH_KEY:-~/.ssh/id_ed25519}</private-key-path>"; \
			echo '      </ssh-properties>'; \
		fi; \
		echo '    </data-source>'; \
		echo '    <data-source name="MariaDB (Remote)" uuid="mariadb-remote-zappzarapp">'; \
		echo '      <database-info product="" version="" jdbc-version="" driver-name="" driver-version="" dbms="MARIADB" />'; \
		echo "      <user-name>$${DB_REMOTE_USER:-$${DB_USER:-app}}</user-name>"; \
		echo "      <schema-pattern>$${REMOTE_DB_NAME}.*</schema-pattern>"; \
		echo "      <default-schemas>$${REMOTE_DB_NAME}.*</default-schemas>"; \
		if [ -n "$${DB_REMOTE_SSH_HOST}" ]; then \
			echo '      <ssh-properties>'; \
			echo '        <enabled>true</enabled>'; \
			echo "        <proxy-host>$${DB_REMOTE_SSH_HOST}</proxy-host>"; \
			echo "        <proxy-port>$${DB_REMOTE_SSH_PORT:-22}</proxy-port>"; \
			echo "        <user>$${DB_REMOTE_SSH_USER}</user>"; \
			echo '        <use-password>false</use-password>'; \
			echo "        <private-key-path>$${DB_REMOTE_SSH_KEY:-~/.ssh/id_ed25519}</private-key-path>"; \
			echo '      </ssh-properties>'; \
		fi; \
		echo '    </data-source>'; \
		echo '  </component>'; \
		echo '</project>'; \
	} > .idea/dataSources.local.xml
	@echo -e "\033[0;32mPHPStorm: .idea/dataSources.local.xml updated\033[0m"
	@$(LOAD_ENV) && if [ -n "$${DB_REMOTE_SSH_HOST}" ]; then \
		echo -e "\033[0;32m  Remote DB SSH tunnel configured: $${DB_REMOTE_SSH_HOST}\033[0m"; \
	else \
		echo -e "\033[0;34m  Tip: Configure DB_REMOTE_SSH_* in .env.local for remote DB access\033[0m"; \
	fi
	@echo -e "\033[0;34m  On first connection: cat secrets/db_password.txt → paste when prompted\033[0m"

ide-config-phpstorm-full: ## Update PHPStorm dataSources.xml (shared) with custom ports from .env
	@if [ ! -d .idea ]; then \
		echo -e "\033[0;33mSkipping PHPStorm config (.idea directory not found)\033[0m"; \
		exit 0; \
	fi
	@echo -e "\033[0;33m╔════════════════════════════════════════════════════════════════╗\033[0m"
	@echo -e "\033[0;33m║  Updating .idea/dataSources.xml (shared team file)             ║\033[0m"
	@echo -e "\033[0;33m║  To hide local changes from git:                               ║\033[0m"
	@echo -e "\033[0;33m║  git update-index --assume-unchanged .idea/dataSources.xml     ║\033[0m"
	@echo -e "\033[0;33m╚════════════════════════════════════════════════════════════════╝\033[0m"
	@$(LOAD_ENV) && { \
		echo '<?xml version="1.0" encoding="UTF-8"?>'; \
		echo '<project version="4">'; \
		echo '  <component name="DataSourceManagerImpl" format="xml" multifile-model="true">'; \
		echo '    <!-- Local Development Databases -->'; \
		echo '    <data-source source="LOCAL" name="PostgreSQL (Docker)" uuid="postgres-zappzarapp">'; \
		echo '      <driver-ref>postgresql</driver-ref>'; \
		echo '      <synchronize>true</synchronize>'; \
		echo '      <jdbc-driver>org.postgresql.Driver</jdbc-driver>'; \
		echo "      <jdbc-url>jdbc:postgresql://localhost:$${POSTGRES_PORT:-5432}/$${DB_NAME:-app}</jdbc-url>"; \
		echo '      <working-dir>$$ProjectFileDir$$</working-dir>'; \
		echo '      <driver-properties>'; \
		echo '        <property name="ApplicationName" value="PhpStorm" />'; \
		echo '      </driver-properties>'; \
		echo '    </data-source>'; \
		echo '    <data-source source="LOCAL" name="MariaDB (Docker)" uuid="mariadb-zappzarapp">'; \
		echo '      <driver-ref>mariadb</driver-ref>'; \
		echo '      <synchronize>true</synchronize>'; \
		echo '      <jdbc-driver>org.mariadb.jdbc.Driver</jdbc-driver>'; \
		echo "      <jdbc-url>jdbc:mariadb://localhost:$${MARIADB_PORT:-3306}/$${DB_NAME:-app}</jdbc-url>"; \
		echo '      <working-dir>$$ProjectFileDir$$</working-dir>'; \
		echo '    </data-source>'; \
		echo '    <!-- Remote Databases (via SSH tunnel) -->'; \
		echo '    <data-source source="LOCAL" name="PostgreSQL (Remote)" uuid="postgres-remote-zappzarapp">'; \
		echo '      <driver-ref>postgresql</driver-ref>'; \
		echo '      <synchronize>true</synchronize>'; \
		echo '      <jdbc-driver>org.postgresql.Driver</jdbc-driver>'; \
		echo "      <jdbc-url>jdbc:postgresql://$${DB_REMOTE_HOST:-localhost}:$${DB_REMOTE_PORT:-5432}/$${DB_REMOTE_NAME:-production}</jdbc-url>"; \
		echo '      <working-dir>$$ProjectFileDir$$</working-dir>'; \
		echo '      <driver-properties>'; \
		echo '        <property name="ApplicationName" value="PhpStorm" />'; \
		echo '      </driver-properties>'; \
		echo '    </data-source>'; \
		echo '    <data-source source="LOCAL" name="MariaDB (Remote)" uuid="mariadb-remote-zappzarapp">'; \
		echo '      <driver-ref>mariadb</driver-ref>'; \
		echo '      <synchronize>true</synchronize>'; \
		echo '      <jdbc-driver>org.mariadb.jdbc.Driver</jdbc-driver>'; \
		echo "      <jdbc-url>jdbc:mariadb://$${DB_REMOTE_HOST:-localhost}:$${DB_REMOTE_PORT:-3306}/$${DB_REMOTE_NAME:-production}</jdbc-url>"; \
		echo '      <working-dir>$$ProjectFileDir$$</working-dir>'; \
		echo '    </data-source>'; \
		echo '  </component>'; \
		echo '</project>'; \
	} > .idea/dataSources.xml
	@echo -e "\033[0;32mPHPStorm: .idea/dataSources.xml updated with custom ports\033[0m"
	@$(MAKE) --silent ide-config-phpstorm

ide-config-vscode: ## Configure VS Code SQLTools connections (.vscode/settings.json)
	@if [ ! -d .vscode ]; then \
		echo -e "\033[0;33mSkipping VS Code config (.vscode directory not found)\033[0m"; \
		exit 0; \
	fi
	@if ! command -v jq &> /dev/null; then \
		echo -e "\033[0;31mError: jq is required for VS Code config. Install with: apt install jq\033[0m"; \
		exit 1; \
	fi
	@echo -e "\033[0;33mConfiguring VS Code SQLTools connections...\033[0m"
	@$(LOAD_ENV) && \
	CONNECTIONS='[{"name":"PostgreSQL (Docker)","driver":"PostgreSQL","server":"localhost","port":'"$${POSTGRES_PORT:-5432}"',"database":"'"$${DB_NAME:-app}"'","username":"'"$${DB_USER:-app}"'","askForPassword":true},{"name":"MariaDB (Docker)","driver":"MariaDB","server":"localhost","port":'"$${MARIADB_PORT:-3306}"',"database":"'"$${DB_NAME:-app}"'","username":"'"$${DB_USER:-app}"'","askForPassword":true},{"name":"PostgreSQL (Remote)","driver":"PostgreSQL","server":"localhost","port":'"$${DB_REMOTE_PORT:-5432}"',"database":"'"$${DB_REMOTE_NAME:-production}"'","username":"'"$${DB_REMOTE_USER:-$${DB_USER:-app}}"'","askForPassword":true},{"name":"MariaDB (Remote)","driver":"MariaDB","server":"localhost","port":'"$${DB_REMOTE_PORT:-3306}"',"database":"'"$${DB_REMOTE_NAME:-production}"'","username":"'"$${DB_REMOTE_USER:-$${DB_USER:-app}}"'","askForPassword":true}]' && \
	jq --argjson conns "$$CONNECTIONS" '.["sqltools.connections"] = $$conns' .vscode/settings.json > .vscode/settings.json.tmp && \
	mv .vscode/settings.json.tmp .vscode/settings.json
	@echo -e "\033[0;32mVS Code: .vscode/settings.json updated\033[0m"
	@echo -e "\033[0;34m  On first connection: cat secrets/db_password.txt → paste when prompted\033[0m"
	@echo -e "\033[0;34m  Remote DB: Start SSH tunnel first (ssh -N -L 5432:db-host:5432 bastion)\033[0m"

ide-config-vscode-full: ## Update VS Code settings.json with custom ports (shows assume-unchanged hint)
	@if [ ! -d .vscode ]; then \
		echo -e "\033[0;33mSkipping VS Code config (.vscode directory not found)\033[0m"; \
		exit 0; \
	fi
	@echo -e "\033[0;33m╔════════════════════════════════════════════════════════════════╗\033[0m"
	@echo -e "\033[0;33m║  Updating .vscode/settings.json (shared team file)             ║\033[0m"
	@echo -e "\033[0;33m║  To hide local changes from git:                               ║\033[0m"
	@echo -e "\033[0;33m║  git update-index --assume-unchanged .vscode/settings.json     ║\033[0m"
	@echo -e "\033[0;33m╚════════════════════════════════════════════════════════════════╝\033[0m"
	@$(MAKE) --silent ide-config-vscode

ide-lock: ## Lock IDE config files from git tracking (prevents noise from composer install)
	@if [ -f .idea/php.xml ] && git ls-files --error-unmatch .idea/php.xml >/dev/null 2>&1; then \
		if git ls-files -v .idea/php.xml 2>/dev/null | grep -q '^S'; then \
			echo -e "\033[0;90m.idea/php.xml already locked (skip-worktree)\033[0m"; \
		else \
			git update-index --skip-worktree .idea/php.xml 2>/dev/null && \
			echo -e "\033[0;36mℹ️  .idea/php.xml locked (local changes ignored by git)\033[0m" || true; \
		fi; \
	fi

ide-unlock: ## Unlock IDE config files for committing (zappzarapp contributors only)
	@if [ -f .idea/php.xml ] && git ls-files --error-unmatch .idea/php.xml >/dev/null 2>&1; then \
		git update-index --no-skip-worktree .idea/php.xml 2>/dev/null && \
		echo -e "\033[0;32m✓ .idea/php.xml unlocked (changes visible to git)\033[0m" || true; \
	else \
		echo -e "\033[0;33m.idea/php.xml not tracked or not found\033[0m"; \
	fi

##@ Docker

build: ## Build Docker images (optionally specify service names: make build php nginx)
	@SERVICES="$(filter-out $@ rebuild,$(MAKECMDGOALS))"; \
	if [ -n "$$SERVICES" ]; then \
		echo -e "\033[0;33mBuilding images: $$SERVICES...\033[0m"; \
		if [ -f .env ]; then \
			$(LOAD_ENV) && if [ "$$ZAPPZARAPP_ENV" = "production" ]; then \
				NODE_TARGET_AUTO="assets"; \
				NODE_BACKEND_TARGET_AUTO="api"; \
				NGINX_TARGET_AUTO="production"; \
				PHP_TARGET_AUTO="production"; \
				case "$${NODE_MODE:-assets-api}" in \
					assets) NODE_TARGET_AUTO="assets"; NGINX_TARGET_AUTO="production"; PHP_TARGET_AUTO="production" ;; \
					api|assets-api) NODE_BACKEND_TARGET_AUTO="api"; NGINX_TARGET_AUTO="production"; PHP_TARGET_AUTO="production" ;; \
					framework) NODE_TARGET_AUTO="framework"; NGINX_TARGET_AUTO="production-proxy"; PHP_TARGET_AUTO="production-framework" ;; \
					framework-api) NODE_TARGET_AUTO="framework"; NODE_BACKEND_TARGET_AUTO="api"; NGINX_TARGET_AUTO="production-proxy"; PHP_TARGET_AUTO="production-framework" ;; \
				esac; \
				export NODE_TARGET="$${NODE_TARGET:-$$NODE_TARGET_AUTO}"; \
				export NODE_BACKEND_TARGET="$${NODE_BACKEND_TARGET:-$$NODE_BACKEND_TARGET_AUTO}"; \
				export NGINX_TARGET="$${NGINX_TARGET:-$$NGINX_TARGET_AUTO}"; \
				export PHP_TARGET="$${PHP_TARGET:-$$PHP_TARGET_AUTO}"; \
				$(DC) -f compose.yaml -f compose.production.yaml build $$SERVICES; \
			else \
				$(DC) build $$SERVICES; \
			fi; \
		else \
			$(DC) build $$SERVICES; \
		fi; \
	else \
		echo -e "\033[0;33mBuilding Docker images...\033[0m"; \
		if [ -f .env ]; then \
			$(LOAD_ENV) && \
			if [ "$${ZAPPZARAPP_ENV:-development}" != "production" ]; then \
				DEV_INFO=""; \
				if [ "$${ENABLE_NODE}" = "false" ]; then \
					echo -e "\033[0;33m⚠️  ENABLE_NODE=false: Vite HMR disabled, no frontend hot-reload.\033[0m"; \
					echo -e "\033[0;33m   DevDashboard (/_dev) still works (self-contained PHP).\033[0m"; \
					echo ""; \
				elif [ -z "$${NODE_MODE}" ] || [ "$${NODE_MODE}" = "idle" ]; then \
					export NODE_MODE="assets-api"; \
					DEV_INFO=" NODE_MODE=assets-api"; \
				fi; \
				if [ -n "$$DEV_INFO" ]; then \
					echo -e "\033[0;36mℹ️  Dev-defaults:$$DEV_INFO (override in .env)\033[0m"; \
				fi; \
			fi; \
			PROFILES=""; \
			if [ "$${ENABLE_DATABASE:-true}" = "true" ]; then \
				PROFILES="$$PROFILES --profile $${DB_TYPE:-postgres}"; \
			fi; \
			if [ "$${ENABLE_PHP:-true}" = "true" ]; then PROFILES="$$PROFILES --profile php"; fi; \
			if [ "$${ENABLE_NODE:-true}" = "true" ]; then \
				case "$${NODE_MODE:-assets-api}" in \
					assets|idle) PROFILES="$$PROFILES --profile node" ;; \
					api) PROFILES="$$PROFILES --profile node-backend" ;; \
					assets-api) PROFILES="$$PROFILES --profile node --profile node-backend" ;; \
					framework) PROFILES="$$PROFILES --profile node" ;; \
					framework-api) PROFILES="$$PROFILES --profile node --profile node-backend" ;; \
					*) PROFILES="$$PROFILES --profile node" ;; \
				esac; \
			fi; \
			if [ "$${ENABLE_REDIS:-true}" = "true" ]; then PROFILES="$$PROFILES --profile redis"; fi; \
			if [ "$${ENABLE_MERCURE:-false}" = "true" ]; then PROFILES="$$PROFILES --profile mercure"; fi; \
			if [ "$${ENABLE_MEILISEARCH:-false}" = "true" ]; then PROFILES="$$PROFILES --profile meilisearch"; fi; \
			if [ "$${ENABLE_ELASTICSEARCH:-false}" = "true" ]; then PROFILES="$$PROFILES --profile elasticsearch"; fi; \
			if [ "$${ENABLE_MAILPIT:-false}" = "true" ]; then PROFILES="$$PROFILES --profile mailpit"; fi; \
			if [ "$${ENABLE_SEAWEEDFS:-false}" = "true" ]; then PROFILES="$$PROFILES --profile seaweedfs"; fi; \
			if [ "$${ENABLE_RABBITMQ:-false}" = "true" ]; then PROFILES="$$PROFILES --profile rabbitmq"; fi; \
			if [ "$$ZAPPZARAPP_ENV" = "production" ]; then \
				NODE_TARGET_AUTO="assets"; \
				NODE_BACKEND_TARGET_AUTO="api"; \
				NGINX_TARGET_AUTO="production"; \
				PHP_TARGET_AUTO="production"; \
				case "$${NODE_MODE:-assets-api}" in \
					assets) NODE_TARGET_AUTO="assets"; NGINX_TARGET_AUTO="production"; PHP_TARGET_AUTO="production" ;; \
					api|assets-api) NODE_BACKEND_TARGET_AUTO="api"; NGINX_TARGET_AUTO="production"; PHP_TARGET_AUTO="production" ;; \
					framework) NODE_TARGET_AUTO="framework"; NGINX_TARGET_AUTO="production-proxy"; PHP_TARGET_AUTO="production-framework" ;; \
					framework-api) NODE_TARGET_AUTO="framework"; NODE_BACKEND_TARGET_AUTO="api"; NGINX_TARGET_AUTO="production-proxy"; PHP_TARGET_AUTO="production-framework" ;; \
				esac; \
				export NODE_TARGET="$${NODE_TARGET:-$$NODE_TARGET_AUTO}"; \
				export NODE_BACKEND_TARGET="$${NODE_BACKEND_TARGET:-$$NODE_BACKEND_TARGET_AUTO}"; \
				export NGINX_TARGET="$${NGINX_TARGET:-$$NGINX_TARGET_AUTO}"; \
				export PHP_TARGET="$${PHP_TARGET:-$$PHP_TARGET_AUTO}"; \
				echo -e "\033[0;34mBuilding Node images first (NODE_TARGET=$$NODE_TARGET, NGINX_TARGET=$$NGINX_TARGET, PHP_TARGET=$$PHP_TARGET)...\033[0m" && \
				$(DC) -f compose.yaml -f compose.production.yaml $$PROFILES build node node-backend 2>/dev/null || true && \
				docker tag $${COMPOSE_PROJECT_NAME:-zappzarapp}-node-backend:$$NODE_BACKEND_TARGET zappzarapp-node-backend:latest 2>/dev/null || true && \
				echo -e "\033[0;34mBuilding remaining images...\033[0m" && \
				$(DC) -f compose.yaml -f compose.production.yaml $$PROFILES build; \
			else \
				$(DC) $$PROFILES build; \
			fi; \
		else \
			$(DC) --profile php --profile node --profile node-backend --profile redis --profile postgres build; \
		fi; \
	fi
	@echo -e "\033[0;32mBuild completed!\033[0m"

build-no-cache: ## Build Docker images without cache (optionally specify service names: make build-no-cache php)
	@SERVICES="$(filter-out $@,$(MAKECMDGOALS))"; \
	if [ -n "$$SERVICES" ]; then \
		echo -e "\033[0;33mBuilding images (no cache): $$SERVICES...\033[0m"; \
		if [ -f .env ]; then \
			$(LOAD_ENV) && if [ "$$ZAPPZARAPP_ENV" = "production" ]; then \
				NODE_TARGET_AUTO="assets"; \
				NODE_BACKEND_TARGET_AUTO="api"; \
				NGINX_TARGET_AUTO="production"; \
				PHP_TARGET_AUTO="production"; \
				case "$${NODE_MODE:-assets-api}" in \
					assets) NODE_TARGET_AUTO="assets"; NGINX_TARGET_AUTO="production"; PHP_TARGET_AUTO="production" ;; \
					api|assets-api) NODE_BACKEND_TARGET_AUTO="api"; NGINX_TARGET_AUTO="production"; PHP_TARGET_AUTO="production" ;; \
					framework) NODE_TARGET_AUTO="framework"; NGINX_TARGET_AUTO="production-proxy"; PHP_TARGET_AUTO="production-framework" ;; \
					framework-api) NODE_TARGET_AUTO="framework"; NODE_BACKEND_TARGET_AUTO="api"; NGINX_TARGET_AUTO="production-proxy"; PHP_TARGET_AUTO="production-framework" ;; \
				esac; \
				export NODE_TARGET="$${NODE_TARGET:-$$NODE_TARGET_AUTO}"; \
				export NODE_BACKEND_TARGET="$${NODE_BACKEND_TARGET:-$$NODE_BACKEND_TARGET_AUTO}"; \
				export NGINX_TARGET="$${NGINX_TARGET:-$$NGINX_TARGET_AUTO}"; \
				export PHP_TARGET="$${PHP_TARGET:-$$PHP_TARGET_AUTO}"; \
				$(DC) -f compose.yaml -f compose.production.yaml build --no-cache $$SERVICES; \
			else \
				$(DC) build --no-cache $$SERVICES; \
			fi; \
		else \
			$(DC) build --no-cache $$SERVICES; \
		fi; \
	else \
		echo -e "\033[0;33mBuilding Docker images (no cache)...\033[0m"; \
		if [ -f .env ]; then \
			$(LOAD_ENV) && \
			PROFILES=""; \
			if [ "$${ENABLE_DATABASE:-true}" = "true" ]; then \
				PROFILES="$$PROFILES --profile $${DB_TYPE:-postgres}"; \
			fi; \
			if [ "$${ENABLE_PHP:-true}" = "true" ]; then PROFILES="$$PROFILES --profile php"; fi; \
			if [ "$${ENABLE_NODE:-true}" = "true" ]; then \
				case "$${NODE_MODE:-assets-api}" in \
					assets|idle) PROFILES="$$PROFILES --profile node" ;; \
					api) PROFILES="$$PROFILES --profile node-backend" ;; \
					assets-api) PROFILES="$$PROFILES --profile node --profile node-backend" ;; \
					framework) PROFILES="$$PROFILES --profile node" ;; \
					framework-api) PROFILES="$$PROFILES --profile node --profile node-backend" ;; \
					*) PROFILES="$$PROFILES --profile node" ;; \
				esac; \
			fi; \
			if [ "$${ENABLE_REDIS:-true}" = "true" ]; then PROFILES="$$PROFILES --profile redis"; fi; \
			if [ "$${ENABLE_MERCURE:-false}" = "true" ]; then PROFILES="$$PROFILES --profile mercure"; fi; \
			if [ "$${ENABLE_MEILISEARCH:-false}" = "true" ]; then PROFILES="$$PROFILES --profile meilisearch"; fi; \
			if [ "$${ENABLE_ELASTICSEARCH:-false}" = "true" ]; then PROFILES="$$PROFILES --profile elasticsearch"; fi; \
			if [ "$${ENABLE_MAILPIT:-false}" = "true" ]; then PROFILES="$$PROFILES --profile mailpit"; fi; \
			if [ "$${ENABLE_SEAWEEDFS:-false}" = "true" ]; then PROFILES="$$PROFILES --profile seaweedfs"; fi; \
			if [ "$${ENABLE_RABBITMQ:-false}" = "true" ]; then PROFILES="$$PROFILES --profile rabbitmq"; fi; \
			if [ "$$ZAPPZARAPP_ENV" = "production" ]; then \
				NODE_TARGET_AUTO="assets"; \
				NODE_BACKEND_TARGET_AUTO="api"; \
				NGINX_TARGET_AUTO="production"; \
				PHP_TARGET_AUTO="production"; \
				case "$${NODE_MODE:-assets-api}" in \
					assets) NODE_TARGET_AUTO="assets"; NGINX_TARGET_AUTO="production"; PHP_TARGET_AUTO="production" ;; \
					api|assets-api) NODE_BACKEND_TARGET_AUTO="api"; NGINX_TARGET_AUTO="production"; PHP_TARGET_AUTO="production" ;; \
					framework) NODE_TARGET_AUTO="framework"; NGINX_TARGET_AUTO="production-proxy"; PHP_TARGET_AUTO="production-framework" ;; \
					framework-api) NODE_TARGET_AUTO="framework"; NODE_BACKEND_TARGET_AUTO="api"; NGINX_TARGET_AUTO="production-proxy"; PHP_TARGET_AUTO="production-framework" ;; \
				esac; \
				export NODE_TARGET="$${NODE_TARGET:-$$NODE_TARGET_AUTO}"; \
				export NODE_BACKEND_TARGET="$${NODE_BACKEND_TARGET:-$$NODE_BACKEND_TARGET_AUTO}"; \
				export NGINX_TARGET="$${NGINX_TARGET:-$$NGINX_TARGET_AUTO}"; \
				export PHP_TARGET="$${PHP_TARGET:-$$PHP_TARGET_AUTO}"; \
				echo -e "\033[0;34mBuilding Node images first (NODE_TARGET=$$NODE_TARGET, NGINX_TARGET=$$NGINX_TARGET, PHP_TARGET=$$PHP_TARGET)...\033[0m" && \
				$(DC) -f compose.yaml -f compose.production.yaml $$PROFILES build --no-cache node node-backend 2>/dev/null || true && \
				docker tag $${COMPOSE_PROJECT_NAME:-zappzarapp}-node-backend:$$NODE_BACKEND_TARGET zappzarapp-node-backend:latest 2>/dev/null || true && \
				echo -e "\033[0;34mBuilding remaining images...\033[0m" && \
				$(DC) -f compose.yaml -f compose.production.yaml $$PROFILES build --no-cache; \
			else \
				$(DC) $$PROFILES build --no-cache; \
			fi; \
		else \
			$(DC) --profile php --profile node --profile node-backend --profile redis --profile postgres build --no-cache; \
		fi; \
	fi
	@echo -e "\033[0;32mBuild completed!\033[0m"

clean: ## Remove containers, networks and dangling images (keeps data volumes)
	@echo -e "\033[0;33mCleaning up...\033[0m"
	@if [ -f .env ]; then \
		$(LOAD_ENV) && \
		PROFILES=""; \
		if [ "$${ENABLE_DATABASE:-true}" = "true" ]; then \
			PROFILES="$$PROFILES --profile $${DB_TYPE:-postgres}"; \
		fi; \
		if [ "$${ENABLE_PHP:-true}" = "true" ]; then PROFILES="$$PROFILES --profile php"; fi; \
		if [ "$${ENABLE_NODE:-true}" = "true" ]; then PROFILES="$$PROFILES --profile node"; fi; \
		if [ "$${ENABLE_REDIS:-true}" = "true" ]; then PROFILES="$$PROFILES --profile redis"; fi; \
		if [ "$${ENABLE_MERCURE:-false}" = "true" ]; then PROFILES="$$PROFILES --profile mercure"; fi; \
		if [ "$${ENABLE_MEILISEARCH:-false}" = "true" ]; then PROFILES="$$PROFILES --profile meilisearch"; fi; \
		if [ "$${ENABLE_ELASTICSEARCH:-false}" = "true" ]; then PROFILES="$$PROFILES --profile elasticsearch"; fi; \
		if [ "$${ENABLE_MAILPIT:-false}" = "true" ]; then PROFILES="$$PROFILES --profile mailpit"; fi; \
		if [ "$${ENABLE_SEAWEEDFS:-false}" = "true" ]; then PROFILES="$$PROFILES --profile seaweedfs"; fi; \
		if [ "$${ENABLE_RABBITMQ:-false}" = "true" ]; then PROFILES="$$PROFILES --profile rabbitmq"; fi; \
		if [ "$$ZAPPZARAPP_ENV" = "production" ]; then \
			$(DC) -f compose.yaml -f compose.production.yaml $$PROFILES down; \
		else \
			$(DC) $$PROFILES down; \
		fi; \
	else \
		$(DC) --profile php --profile node --profile redis --profile postgres down; \
	fi
	@docker system prune -f
	@echo -e "\033[0;32mCleanup completed!\033[0m"

composer: ## Execute Composer command (e.g. make composer CMD="require --dev vendor/package")
	@XDEBUG_MODE=off $(DC_RUN) run --rm --no-TTY php composer $(CMD)

composer-update: ## Update Composer dependencies (updates composer.lock on host, vendor stays in container)
	@echo -e "\033[0;33mUpdating Composer dependencies...\033[0m"
	@XDEBUG_MODE=off $(DC_RUN) run --rm --no-TTY php composer update
	@echo -e "\033[0;32mDependencies updated!\033[0m"

down: ## Stop containers (optionally specify service names: make down php nginx)
	@SERVICES="$(filter-out $@ down-all goss-cleanup,$(MAKECMDGOALS))"; \
	if [ -n "$$SERVICES" ]; then \
		echo -e "\033[0;33mStopping services: $$SERVICES...\033[0m"; \
		if [ -f .env ]; then \
			$(LOAD_ENV) && if [ "$$ZAPPZARAPP_ENV" = "production" ]; then \
				$(DC) -f compose.yaml -f compose.production.yaml stop $$SERVICES; \
			else \
				$(DC) stop $$SERVICES; \
			fi; \
		else \
			$(DC) stop $$SERVICES; \
		fi; \
	else \
		echo -e "\033[0;33mStopping containers...\033[0m"; \
		ALL_PROFILES="--profile postgres --profile mariadb --profile php --profile node --profile node-backend --profile redis --profile mercure --profile meilisearch --profile elasticsearch --profile mailpit --profile seaweedfs --profile rabbitmq --profile adminer --profile pgadmin"; \
		if [ -f .env ]; then \
			$(LOAD_ENV) && if [ "$$ZAPPZARAPP_ENV" = "production" ]; then \
				$(DC) -f compose.yaml -f compose.production.yaml $$ALL_PROFILES down --remove-orphans; \
			else \
				$(DC) $$ALL_PROFILES down --remove-orphans; \
			fi; \
		else \
			$(DC) $$ALL_PROFILES down --remove-orphans; \
		fi; \
		GOSS_COUNT=$$(docker ps -q --filter "name=zappzarapp-goss-" 2>/dev/null | wc -l | tr -d ' '); \
		if [ "$$GOSS_COUNT" -gt 0 ]; then \
			echo -e "\033[0;36mℹ $$GOSS_COUNT Goss-Test-Container laufen noch (make goss-cleanup zum Aufräumen)\033[0m"; \
		fi; \
	fi
	@echo -e "\033[0;32mContainers stopped!\033[0m"

down-all: down goss-cleanup ## Stop ALL containers (dev/prod + all goss tests)
	@echo -e "\033[0;32m✓ All zappzarapp containers stopped\033[0m"

logs: ## Show logs (optionally specify service names: make logs php nginx)
	@SERVICES="$(filter-out $@,$(MAKECMDGOALS))"; \
	if [ -n "$$SERVICES" ]; then \
		if [ -f .env ]; then \
			$(LOAD_ENV) && if [ "$$ZAPPZARAPP_ENV" = "production" ]; then \
				docker compose -f compose.yaml -f compose.production.yaml logs -f $$SERVICES; \
			else \
				docker compose logs -f $$SERVICES; \
			fi; \
		else \
			docker compose logs -f $$SERVICES; \
		fi; \
	else \
		if [ -f .env ]; then \
			$(LOAD_ENV) && \
			PROFILES=""; \
			if [ "$${ENABLE_DATABASE:-true}" = "true" ]; then \
				PROFILES="$$PROFILES --profile $${DB_TYPE:-postgres}"; \
			fi; \
			if [ "$${ENABLE_PHP:-true}" = "true" ]; then PROFILES="$$PROFILES --profile php"; fi; \
			if [ "$${ENABLE_NODE:-true}" = "true" ]; then PROFILES="$$PROFILES --profile node"; fi; \
			if [ "$${ENABLE_REDIS:-true}" = "true" ]; then PROFILES="$$PROFILES --profile redis"; fi; \
			if [ "$${ENABLE_MERCURE:-false}" = "true" ]; then PROFILES="$$PROFILES --profile mercure"; fi; \
			if [ "$${ENABLE_MEILISEARCH:-false}" = "true" ]; then PROFILES="$$PROFILES --profile meilisearch"; fi; \
			if [ "$${ENABLE_ELASTICSEARCH:-false}" = "true" ]; then PROFILES="$$PROFILES --profile elasticsearch"; fi; \
			if [ "$${ENABLE_MAILPIT:-false}" = "true" ]; then PROFILES="$$PROFILES --profile mailpit"; fi; \
			if [ "$${ENABLE_SEAWEEDFS:-false}" = "true" ]; then PROFILES="$$PROFILES --profile seaweedfs"; fi; \
			if [ "$${ENABLE_RABBITMQ:-false}" = "true" ]; then PROFILES="$$PROFILES --profile rabbitmq"; fi; \
			if [ "$$ZAPPZARAPP_ENV" = "production" ]; then \
				docker compose -f compose.yaml -f compose.production.yaml $$PROFILES logs -f; \
			else \
				docker compose $$PROFILES logs -f; \
			fi; \
		else \
			docker compose --profile php --profile node --profile redis --profile postgres logs -f; \
		fi; \
	fi

logs-nginx: ## Show Nginx logs only
	@if [ -f .env ]; then \
		$(LOAD_ENV) && if [ "$$ZAPPZARAPP_ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.production.yaml logs -f nginx; \
		else \
			docker compose logs -f nginx; \
		fi; \
	else \
		docker compose logs -f nginx; \
	fi

logs-node: ## Show Node.js logs only
	@if [ -f .env ]; then \
		$(LOAD_ENV) && if [ "$$ZAPPZARAPP_ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.production.yaml logs -f node; \
		else \
			docker compose logs -f node; \
		fi; \
	else \
		docker compose logs -f node; \
	fi

logs-php: ## Show PHP logs only
	@if [ -f .env ]; then \
		$(LOAD_ENV) && if [ "$$ZAPPZARAPP_ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.production.yaml logs -f php; \
		else \
			docker compose logs -f php; \
		fi; \
	else \
		docker compose logs -f php; \
	fi

logs-mariadb: ## Show MariaDB logs only
	@if [ -f .env ]; then \
		$(LOAD_ENV) && if [ "$$ZAPPZARAPP_ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.production.yaml logs -f mariadb; \
		else \
			docker compose logs -f mariadb; \
		fi; \
	else \
		docker compose logs -f mariadb; \
	fi

logs-postgres: ## Show PostgreSQL logs only
	@if [ -f .env ]; then \
		$(LOAD_ENV) && if [ "$$ZAPPZARAPP_ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.production.yaml logs -f postgres; \
		else \
			docker compose logs -f postgres; \
		fi; \
	else \
		docker compose logs -f postgres; \
	fi

logs-redis: ## Show Redis logs only
	@if [ -f .env ]; then \
		$(LOAD_ENV) && if [ "$$ZAPPZARAPP_ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.production.yaml logs -f redis; \
		else \
			docker compose logs -f redis; \
		fi; \
	else \
		docker compose logs -f redis; \
	fi

logs-elasticsearch: ## Show Elasticsearch logs only
	@if [ -f .env ]; then \
		$(LOAD_ENV) && if [ "$$ZAPPZARAPP_ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.production.yaml logs -f elasticsearch; \
		else \
			docker compose logs -f elasticsearch; \
		fi; \
	else \
		docker compose logs -f elasticsearch; \
	fi

logs-mailpit: ## Show Mailpit logs only
	@if [ -f .env ]; then \
		$(LOAD_ENV) && if [ "$$ZAPPZARAPP_ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.production.yaml logs -f mailpit; \
		else \
			docker compose logs -f mailpit; \
		fi; \
	else \
		docker compose logs -f mailpit; \
	fi

logs-meilisearch: ## Show Meilisearch logs only
	@if [ -f .env ]; then \
		$(LOAD_ENV) && if [ "$$ZAPPZARAPP_ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.production.yaml logs -f meilisearch; \
		else \
			docker compose logs -f meilisearch; \
		fi; \
	else \
		docker compose logs -f meilisearch; \
	fi

logs-mercure: ## Show Mercure logs only
	@if [ -f .env ]; then \
		$(LOAD_ENV) && if [ "$$ZAPPZARAPP_ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.production.yaml logs -f mercure; \
		else \
			docker compose logs -f mercure; \
		fi; \
	else \
		docker compose logs -f mercure; \
	fi

logs-rabbitmq: ## Show RabbitMQ logs only
	@if [ -f .env ]; then \
		$(LOAD_ENV) && if [ "$$ZAPPZARAPP_ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.production.yaml logs -f rabbitmq; \
		else \
			docker compose logs -f rabbitmq; \
		fi; \
	else \
		docker compose logs -f rabbitmq; \
	fi

logs-seaweedfs: ## Show SeaweedFS logs only
	@if [ -f .env ]; then \
		$(LOAD_ENV) && if [ "$$ZAPPZARAPP_ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.production.yaml logs -f seaweedfs; \
		else \
			docker compose logs -f seaweedfs; \
		fi; \
	else \
		docker compose logs -f seaweedfs; \
	fi

logs-save: ## Export container logs to timestamped directory (SERVICES=php,node SINCE=2h)
	@# Create timestamped output directory
	@OUTPUT_DIR="logs/$$(date +%Y-%m-%d-%H%M)"; \
	mkdir -p "$$OUTPUT_DIR"; \
	echo -e "\033[0;33mExporting logs to $$OUTPUT_DIR...\033[0m"; \
	\
	# Load environment (respects .env → .env.production → .env.local) \
	$(LOAD_ENV); \
	if [ "$${ZAPPZARAPP_ENV:-development}" = "production" ]; then \
		DC_CMD="docker compose -f compose.yaml -f compose.production.yaml"; \
	else \
		DC_CMD="docker compose"; \
	fi; \
	\
	# Parse SINCE parameter (default: no time filter = all logs) \
	SINCE_ARG=""; \
	if [ -n "$(SINCE)" ]; then \
		SINCE_ARG="--since $(SINCE)"; \
	fi; \
	\
	# Get list of services to export \
	if [ -n "$(SERVICES)" ]; then \
		SERVICE_LIST="$$(echo "$(SERVICES)" | tr ',' ' ')"; \
	else \
		SERVICE_LIST="$$($$DC_CMD ps --format '{{.Service}}' 2>/dev/null | sort -u | tr '\n' ' ')"; \
	fi; \
	\
	# Export logs for each service \
	for SERVICE in $$SERVICE_LIST; do \
		echo "  Exporting $$SERVICE..."; \
		$$DC_CMD logs $$SINCE_ARG "$$SERVICE" > "$$OUTPUT_DIR/$$SERVICE.log" 2>&1 || true; \
	done; \
	\
	# Generate metadata file \
	echo "# Log Export Metadata" > "$$OUTPUT_DIR/metadata.txt"; \
	echo "" >> "$$OUTPUT_DIR/metadata.txt"; \
	echo "## Export Info" >> "$$OUTPUT_DIR/metadata.txt"; \
	echo "Timestamp: $$(date '+%Y-%m-%d %H:%M:%S %Z')" >> "$$OUTPUT_DIR/metadata.txt"; \
	echo "Time Filter: $${SINCE_ARG:-none (all logs)}" >> "$$OUTPUT_DIR/metadata.txt"; \
	echo "Services: $$SERVICE_LIST" >> "$$OUTPUT_DIR/metadata.txt"; \
	echo "" >> "$$OUTPUT_DIR/metadata.txt"; \
	\
	echo "## Git Info" >> "$$OUTPUT_DIR/metadata.txt"; \
	echo "Branch: $$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo 'unknown')" >> "$$OUTPUT_DIR/metadata.txt"; \
	echo "Commit: $$(git log -1 --format='%h %s' 2>/dev/null || echo 'unknown')" >> "$$OUTPUT_DIR/metadata.txt"; \
	echo "" >> "$$OUTPUT_DIR/metadata.txt"; \
	\
	echo "## Environment" >> "$$OUTPUT_DIR/metadata.txt"; \
	echo "ZAPPZARAPP_ENV: $${ZAPPZARAPP_ENV:-development}" >> "$$OUTPUT_DIR/metadata.txt"; \
	echo "DB_TYPE: $${DB_TYPE:-postgres}" >> "$$OUTPUT_DIR/metadata.txt"; \
	echo "NODE_MODE: $${NODE_MODE:-backend}" >> "$$OUTPUT_DIR/metadata.txt"; \
	echo "" >> "$$OUTPUT_DIR/metadata.txt"; \
	\
	echo "## Active Profiles (from COMPOSE_PROFILES)" >> "$$OUTPUT_DIR/metadata.txt"; \
	echo "$${COMPOSE_PROFILES:-none}" >> "$$OUTPUT_DIR/metadata.txt"; \
	echo "" >> "$$OUTPUT_DIR/metadata.txt"; \
	\
	echo "## Container Status" >> "$$OUTPUT_DIR/metadata.txt"; \
	$$DC_CMD ps >> "$$OUTPUT_DIR/metadata.txt" 2>&1; \
	\
	echo -e "\033[0;32m✓ Logs exported to $$OUTPUT_DIR\033[0m"; \
	echo "  Files:"; \
	ls -la "$$OUTPUT_DIR"

pnpm: ## Execute pnpm command (e.g. make pnpm CMD="add -D vue")
	@# Docker bind mounts don't support atomic rename (EBUSY error), so run pnpm
	@# in a temp workspace and copy the results back. pnpm-workspace.yaml must be
	@# present because pnpm 11 reads overrides/allowBuilds/auditConfig from it -
	@# which means the workspace package dirs it lists must exist there too.
	@$(DC_RUN) run --rm --no-TTY node sh -c ' \
		rm -rf /tmp/pnpm-cmd && \
		mkdir -p /tmp/pnpm-cmd/src/node/backend /tmp/pnpm-cmd/src/node/frontend && \
		cp /app/package.json /tmp/pnpm-cmd/package.json && \
		cp /app/pnpm-workspace.yaml /tmp/pnpm-cmd/pnpm-workspace.yaml && \
		cp /app/.npmrc /tmp/pnpm-cmd/.npmrc 2>/dev/null || true; \
		{ [ -s /app/pnpm-lock.yaml ] && cp /app/pnpm-lock.yaml /tmp/pnpm-cmd/pnpm-lock.yaml || true; } && \
		cp /app/src/node/backend/package.json /tmp/pnpm-cmd/src/node/backend/package.json && \
		cp /app/src/node/frontend/package.json /tmp/pnpm-cmd/src/node/frontend/package.json && \
		cd /tmp/pnpm-cmd && pnpm $(CMD) && \
		cat /tmp/pnpm-cmd/package.json > /app/package.json && \
		{ [ -s /tmp/pnpm-cmd/pnpm-lock.yaml ] && cat /tmp/pnpm-cmd/pnpm-lock.yaml > /app/pnpm-lock.yaml || true; } \
	'

prune: ## Remove untagged/dangling images related to this project
	@echo -e "\033[0;33mPruning dangling images...\033[0m"
	@$(LOAD_ENV) && docker image prune -f --filter "label=com.docker.compose.project=$$COMPOSE_PROJECT_NAME"

restart: ## Restart containers (optionally specify service names: make restart php nginx)
	@SERVICES="$(filter-out $@,$(MAKECMDGOALS))"; \
	if [ -n "$$SERVICES" ]; then \
		echo -e "\033[0;33mRestarting services: $$SERVICES...\033[0m"; \
		if [ -f .env ]; then \
			$(LOAD_ENV) && if [ "$$ZAPPZARAPP_ENV" = "production" ]; then \
				$(DC) -f compose.yaml -f compose.production.yaml restart $$SERVICES; \
			else \
				$(DC) restart $$SERVICES; \
			fi; \
		else \
			$(DC) restart $$SERVICES; \
		fi; \
		echo -e "\033[0;32mServices restarted!\033[0m"; \
	else \
		$(MAKE) down && $(MAKE) up; \
	fi

shell-nginx: ## Open shell in Nginx container
	@docker compose exec nginx sh

shell-node: ## Open shell in Node container
	@docker compose exec node sh

shell-php: ## Open shell in PHP container
	@docker compose exec php sh

shell-mariadb: ## Open shell in MariaDB container
	@docker compose exec mariadb sh

shell-postgres: ## Open shell in PostgreSQL container
	@docker compose exec postgres sh

shell-redis: ## Open shell in Redis container
	@docker compose exec redis sh

shell-elasticsearch: ## Open shell in Elasticsearch container
	@docker compose exec elasticsearch bash

shell-mailpit: ## Open shell in Mailpit container
	@docker compose exec mailpit sh

shell-meilisearch: ## Open shell in Meilisearch container
	@docker compose exec meilisearch sh

shell-mercure: ## Open shell in Mercure container
	@docker compose exec mercure sh

shell-rabbitmq: ## Open shell in RabbitMQ container
	@docker compose exec rabbitmq bash

shell-seaweedfs: ## Open shell in SeaweedFS container
	@docker compose exec seaweedfs sh

status: ## Show running containers status and image disk usage
	@echo -e "\033[0;33mContainer Status:\033[0m"
	@docker compose ps
	@echo -e "\033[0;33m\nImage Disk Usage:\033[0m"
	@docker images | grep "$(COMPOSE_PROJECT_NAME:-zappzarapp)"

up: ## Start containers (optionally specify service names: make up php nginx)
	@# Ensure lockfiles exist as files (not directories) to prevent Docker bind mount issues
	@if [ -d composer.lock ]; then rm -rf composer.lock; fi
	@if [ -d pnpm-lock.yaml ]; then rm -rf pnpm-lock.yaml; fi
	@if [ ! -f composer.lock ]; then touch composer.lock; fi
	@if [ ! -f pnpm-lock.yaml ]; then touch pnpm-lock.yaml; fi
	@$(LOAD_ENV); if [ "$${ZAPPZARAPP_ENV:-development}" = "production" ]; then \
		echo -e "\033[0;33m⚠️  WARNING: Running Compose in production mode.\033[0m"; \
		echo -e "\033[0;33m   For multi-node deployments, use 'make k8s-deploy' (Kubernetes).\033[0m"; \
		echo ""; \
	fi
	@$(LOAD_ENV); if [ "$${ZAPPZARAPP_ENV:-development}" = "production" ] && \
		[ "$${DB_TYPE:-postgres}" = "mariadb" ] && \
		[ -z "$${DB_SSL_CA}" ]; then \
		echo -e "\033[0;33m⚠️  WARNING: MariaDB in production without explicit SSL CA.\033[0m"; \
		echo -e "\033[0;33m   DB_SSL_CA is empty - connection uses self-signed internal cert.\033[0m"; \
		echo -e "\033[0;33m   Set DB_SSL_CA=system for cloud DBs or provide custom cert path.\033[0m"; \
		echo ""; \
	fi
	@# ═══════════════════════════════════════════════════════════════════════════
	@# IMAGE FRESHNESS VALIDATION
	@# Checks if Docker images are older than configuration files.
	@# Use SKIP_VALIDATION=1 to skip (CI), FORCE=1 to auto-rebuild.
	@# ═══════════════════════════════════════════════════════════════════════════
	@if [ "$(SKIP_VALIDATION)" != "1" ]; then \
		$(LOAD_ENV); \
		PROJECT="$${COMPOSE_PROJECT_NAME:-zappzarapp}"; \
		TAG="$$([ "$${ZAPPZARAPP_ENV:-development}" = "production" ] && echo "" || echo ":development")"; \
		STALE_FILES=""; \
		CHECKED_IMAGES=0; \
		for SERVICE in php node nginx; do \
			IMAGE="$$PROJECT-$${SERVICE}$${TAG}"; \
			IMAGE_TIME=$$(docker inspect -f '{{.Created}}' "$$IMAGE" 2>/dev/null); \
			if [ -n "$$IMAGE_TIME" ]; then \
				CHECKED_IMAGES=$$((CHECKED_IMAGES + 1)); \
				IMAGE_EPOCH=$$(date -d "$$IMAGE_TIME" +%s 2>/dev/null || echo "0"); \
				for f in docker/$$SERVICE/Dockerfile \
				         docker/$$SERVICE/entrypoint*.sh \
				         compose.yaml compose.override.yaml .env; do \
					if [ -f "$$f" ]; then \
						FILE_EPOCH=$$(stat -c %Y "$$f" 2>/dev/null || echo "0"); \
						if [ "$$FILE_EPOCH" -gt "$$IMAGE_EPOCH" ]; then \
							case "$$STALE_FILES" in \
								*"$$f"*) ;; \
								*) STALE_FILES="$$STALE_FILES $$f" ;; \
							esac; \
						fi; \
					fi; \
				done; \
			fi; \
		done; \
		if [ -n "$$STALE_FILES" ] && [ "$$CHECKED_IMAGES" -gt 0 ]; then \
			echo -e "\033[0;33m⚠️  Docker images may be outdated!\033[0m"; \
			echo -e "\033[0;33m   Changed since last build:$$STALE_FILES\033[0m"; \
			echo ""; \
			if [ "$(FORCE)" = "1" ]; then \
				echo -e "\033[0;34m→ Stopping containers and rebuilding (FORCE=1)...\033[0m"; \
				$(MAKE) down && $(MAKE) build; \
			elif [ -t 0 ]; then \
				echo -n "   Rebuild now? [y/N] "; \
				read -r answer; \
				if [ "$$answer" = "y" ] || [ "$$answer" = "Y" ]; then \
					$(MAKE) down && $(MAKE) build; \
				else \
					echo -e "\033[0;90m   Skipped. Run 'make rebuild' to update images.\033[0m"; \
				fi; \
			else \
				echo -e "\033[0;90m   Run 'make rebuild' or 'make up FORCE=1' to update.\033[0m"; \
			fi; \
			echo ""; \
		fi; \
	fi
	@SERVICES="$(filter-out $@,$(MAKECMDGOALS))"; \
	if [ -n "$$SERVICES" ]; then \
		echo -e "\033[0;33mStarting services: $$SERVICES...\033[0m"; \
		$(LOAD_ENV) && if [ "$$ZAPPZARAPP_ENV" = "production" ]; then \
			$(DC) -f compose.yaml -f compose.production.yaml start $$SERVICES; \
		else \
			$(DC) start $$SERVICES; \
		fi; \
		echo -e "\033[0;32mServices started!\033[0m"; \
	else \
		$(LOAD_ENV); \
		if [ "$${ENABLE_MAILPIT:-false}" = "true" ] && [ "$${ZAPPZARAPP_ENV:-development}" = "production" ]; then \
			echo -e "\033[0;33m⚠️  WARNING: Mailpit is enabled but ZAPPZARAPP_ENV=production.\033[0m"; \
			echo -e "\033[0;33m   Mailpit won't start (compose.production.yaml sets replicas: 0).\033[0m"; \
			echo -e "\033[0;33m   Set ENABLE_MAILPIT=false to suppress this warning.\033[0m"; \
			echo ""; \
		fi; \
		GOSS_RUNNING=$$(docker ps -q --filter "name=zappzarapp-goss-" 2>/dev/null | wc -l | tr -d ' '); \
		if [ "$$GOSS_RUNNING" -gt 0 ]; then \
			echo -e "\033[0;33m⚠ $$GOSS_RUNNING Goss-Test-Container laufen parallel\033[0m"; \
			echo -e "  \033[0;36mBei Problemen: make goss-cleanup\033[0m"; \
			echo ""; \
		fi; \
		if [ "$${ZAPPZARAPP_ENV:-development}" != "production" ]; then \
			DEV_INFO=""; \
			if [ "$${ENABLE_NODE}" = "false" ]; then \
				echo -e "\033[0;33m⚠️  ENABLE_NODE=false: Vite HMR disabled, no frontend hot-reload.\033[0m"; \
				echo -e "\033[0;33m   DevDashboard (/_dev) still works (self-contained PHP).\033[0m"; \
				echo ""; \
			elif [ -z "$${NODE_MODE}" ] || [ "$${NODE_MODE}" = "idle" ]; then \
				export NODE_MODE="assets-api"; \
				DEV_INFO=" NODE_MODE=assets-api"; \
			fi; \
			if [ -n "$$DEV_INFO" ]; then \
				echo -e "\033[0;36mℹ️  Dev-defaults:$$DEV_INFO (override in .env)\033[0m"; \
			fi; \
		fi; \
		MISSING=""; \
		PROJECT="$${COMPOSE_PROJECT_NAME:-zappzarapp}"; \
		TAG="$$([ "$${ZAPPZARAPP_ENV:-development}" = "production" ] && echo "" || echo ":development")"; \
		if [ "$${ENABLE_PHP:-true}" = "true" ] && ! docker image inspect $$PROJECT-php$$TAG >/dev/null 2>&1; then \
			MISSING="$$MISSING php"; \
		fi; \
		if [ "$${ENABLE_NODE:-true}" = "true" ] && ! docker image inspect $$PROJECT-node$$TAG >/dev/null 2>&1; then \
			MISSING="$$MISSING node"; \
		fi; \
		if ! docker image inspect $$PROJECT-nginx$$TAG >/dev/null 2>&1; then \
			MISSING="$$MISSING nginx"; \
		fi; \
		if [ "$${ENABLE_DATABASE:-true}" = "true" ] && [ "$${DB_TYPE:-postgres}" = "postgres" ] && ! docker image inspect $$PROJECT-postgres >/dev/null 2>&1; then \
			MISSING="$$MISSING postgres"; \
		fi; \
		if [ -n "$$MISSING" ]; then \
			echo -e "\033[0;31mError: Required images not found:$$MISSING\033[0m"; \
			echo -e "\033[0;31mRun 'make build' first.\033[0m"; \
			exit 1; \
		fi; \
		PROFILES=""; \
		if [ "$${ENABLE_DATABASE:-true}" = "true" ]; then \
			PROFILES="$$PROFILES --profile $${DB_TYPE:-postgres}"; \
		fi; \
		if [ "$${ENABLE_PHP:-true}" = "true" ]; then PROFILES="$$PROFILES --profile php"; fi; \
		if [ "$${ENABLE_NODE:-true}" = "true" ]; then \
			case "$${NODE_MODE:-assets-api}" in \
				assets|idle) PROFILES="$$PROFILES --profile node" ;; \
				api) PROFILES="$$PROFILES --profile node-backend" ;; \
				assets-api) PROFILES="$$PROFILES --profile node --profile node-backend" ;; \
				framework) PROFILES="$$PROFILES --profile node" ;; \
				framework-api) PROFILES="$$PROFILES --profile node --profile node-backend" ;; \
				*) PROFILES="$$PROFILES --profile node" ;; \
			esac; \
		fi; \
		if [ "$${ENABLE_REDIS:-true}" = "true" ]; then PROFILES="$$PROFILES --profile redis"; fi; \
		if [ "$${ENABLE_MERCURE:-false}" = "true" ]; then PROFILES="$$PROFILES --profile mercure"; fi; \
		if [ "$${ENABLE_MEILISEARCH:-false}" = "true" ]; then PROFILES="$$PROFILES --profile meilisearch"; fi; \
		if [ "$${ENABLE_ELASTICSEARCH:-false}" = "true" ]; then PROFILES="$$PROFILES --profile elasticsearch"; fi; \
		if [ "$${ENABLE_MAILPIT:-false}" = "true" ]; then PROFILES="$$PROFILES --profile mailpit"; fi; \
		if [ "$${ENABLE_SEAWEEDFS:-false}" = "true" ]; then PROFILES="$$PROFILES --profile seaweedfs"; fi; \
		if [ "$${ENABLE_RABBITMQ:-false}" = "true" ]; then PROFILES="$$PROFILES --profile rabbitmq"; fi; \
		if [ "$${ENABLE_ADMINER:-false}" = "true" ]; then PROFILES="$$PROFILES --profile adminer"; fi; \
		if [ "$${ENABLE_PGADMIN:-false}" = "true" ]; then PROFILES="$$PROFILES --profile pgadmin"; fi; \
		echo -e "\033[0;33mStarting containers in $${ZAPPZARAPP_ENV:-development} mode...\033[0m"; \
		if [ "$$ZAPPZARAPP_ENV" = "production" ]; then \
			NODE_TARGET_AUTO="assets"; \
			NODE_BACKEND_TARGET_AUTO="api"; \
			NGINX_TARGET_AUTO="production"; \
			PHP_TARGET_AUTO="production"; \
			case "$${NODE_MODE:-assets-api}" in \
				assets) NODE_TARGET_AUTO="assets"; NGINX_TARGET_AUTO="production"; PHP_TARGET_AUTO="production" ;; \
				api|assets-api) NODE_BACKEND_TARGET_AUTO="api"; NGINX_TARGET_AUTO="production"; PHP_TARGET_AUTO="production" ;; \
				framework) NODE_TARGET_AUTO="framework"; NGINX_TARGET_AUTO="production-proxy"; PHP_TARGET_AUTO="production-framework" ;; \
				framework-api) NODE_TARGET_AUTO="framework"; NODE_BACKEND_TARGET_AUTO="api"; NGINX_TARGET_AUTO="production-proxy"; PHP_TARGET_AUTO="production-framework" ;; \
			esac; \
			export NODE_TARGET="$${NODE_TARGET:-$$NODE_TARGET_AUTO}"; \
			export NODE_BACKEND_TARGET="$${NODE_BACKEND_TARGET:-$$NODE_BACKEND_TARGET_AUTO}"; \
			export NGINX_TARGET="$${NGINX_TARGET:-$$NGINX_TARGET_AUTO}"; \
			export PHP_TARGET="$${PHP_TARGET:-$$PHP_TARGET_AUTO}"; \
			$(DC) -f compose.yaml -f compose.production.yaml $$PROFILES up -d; \
		else \
			$(DC) $$PROFILES up -d; \
		fi; \
		echo -e "\033[0;32mContainers started!\033[0m"; \
		echo -e "\033[0;34mNginx is running at http://localhost:$${NGINX_PORT:-8080}\033[0m"; \
	fi

##@ Kubernetes Deployment

k8s-deploy: ## Deploy to Kubernetes using Helm
	@if [ ! -d "kubernetes" ]; then \
		echo -e "\033[0;31mError: kubernetes/ directory not found\033[0m"; \
		exit 1; \
	fi
	@echo -e "\033[0;33mDeploying to Kubernetes...\033[0m"
	@$(LOAD_ENV) && \
	NAMESPACE="$${KUBE_NAMESPACE:-zappzarapp}" && \
	VALUES_FILE="kubernetes/values.yaml" && \
	if [ "$${ZAPPZARAPP_ENV:-development}" = "production" ]; then \
		VALUES_FILE="kubernetes/values.production.yaml"; \
	fi && \
	if [ "$${ZAPPZARAPP_ENV:-development}" != "production" ] && command -v helm >/dev/null 2>&1; then \
		MISSING=""; \
		for img in $$(helm template zappzarapp ./kubernetes -f "$$VALUES_FILE" 2>/dev/null | grep -E '^[[:space:]]*image:' | grep -oE 'zappzarapp-[a-z0-9-]+:[^"]+' | sort -u); do \
			docker image inspect "$$img" >/dev/null 2>&1 || MISSING="$$MISSING $$img"; \
		done; \
		if [ -n "$$MISSING" ]; then \
			echo -e "\033[0;31mError: image(s) not found in the local Docker daemon:\033[0m$$MISSING"; \
			echo -e "\033[0;33mThe chart deploys the hardened zappzarapp-* built images. Build the\033[0m"; \
			echo -e "\033[0;33menabled ones first with 'make k8s-build'. For minikube, run\033[0m"; \
			echo -e "\033[0;33m'eval \$$(minikube docker-env)' beforehand so the images land in the cluster.\033[0m"; \
			exit 1; \
		fi; \
	fi && \
	helm upgrade --install zappzarapp ./kubernetes \
		--namespace "$$NAMESPACE" \
		--create-namespace \
		-f "$$VALUES_FILE" && \
	echo -e "\033[0;32mDeployed to Kubernetes!\033[0m" && \
	echo -e "\033[0;34mView status with: make k8s-status\033[0m"

# NOTE: the delegating build call below uses a literal `make`, not the usual
# recursive-make variable, on purpose: GNU make executes recipe lines that
# reference that variable even under `make -n`, which would invoke helm during a
# dry run. A literal `make` keeps `make -n k8s-build` inert and CI-safe.
#
# The chart references every image as zappzarapp-<service>:latest and mounts no
# application source, so the four multi-target services (nginx, php, node,
# node-backend) must be built from their PRODUCTION targets (the development
# targets contain no app code — they expect Compose bind mounts) and retagged
# to :latest. The target map derives from the chart render itself (single
# source of truth, same as the service list): the node frontend only exists in
# framework modes, so its presence in the render selects the framework targets
# (node=framework, nginx=production-proxy, php=production-framework); .env
# NODE_MODE is deliberately NOT consulted — a Chart/.env mismatch must not
# produce wrong images. Explicit NODE_TARGET/NGINX_TARGET/PHP_TARGET overrides
# still win. node-backend builds first because the php/nginx production builds
# copy its baked assets via the node-backend-assets docker-image context.
k8s-build: ## Build the images the Helm chart will deploy (enabled services derived from values.yaml)
	@if ! command -v helm >/dev/null 2>&1; then \
		echo -e "\033[0;31mError: helm is required for 'make k8s-build'\033[0m"; \
		exit 1; \
	fi
	@$(LOAD_ENV); \
	VALUES_FILE="kubernetes/values.yaml"; \
	[ "$${ZAPPZARAPP_ENV:-development}" = "production" ] && VALUES_FILE="kubernetes/values.production.yaml"; \
	SERVICES=$$(helm template zappzarapp ./kubernetes -f "$$VALUES_FILE" 2>/dev/null | grep -E '^[[:space:]]*image:' | grep -oE 'zappzarapp-[a-z0-9-]+' | sed 's/^zappzarapp-//' | sort -u | tr '\n' ' '); \
	if [ -z "$$(echo $$SERVICES | tr -d '[:space:]')" ]; then \
		echo -e "\033[0;31mError: could not derive any services from the rendered chart\033[0m"; \
		helm template zappzarapp ./kubernetes -f "$$VALUES_FILE" >/dev/null || true; \
		exit 1; \
	fi; \
	echo -e "\033[0;33mChart-enabled services:\033[0m $$SERVICES"; \
	CORE=""; REST=""; \
	for svc in $$SERVICES; do \
		case "$$svc" in \
			nginx|php|node|node-backend) CORE="$$CORE $$svc" ;; \
			*) REST="$$REST $$svc" ;; \
		esac; \
	done; \
	if [ -n "$$(echo $$REST | tr -d '[:space:]')" ]; then \
		make --no-print-directory build $$REST; \
	fi; \
	if [ -n "$$(echo $$CORE | tr -d '[:space:]')" ]; then \
		NODE_TARGET_AUTO="assets"; \
		NODE_BACKEND_TARGET_AUTO="api"; \
		NGINX_TARGET_AUTO="production"; \
		PHP_TARGET_AUTO="production"; \
		case " $$CORE " in \
			*" node "*) NODE_TARGET_AUTO="framework"; NGINX_TARGET_AUTO="production-proxy"; PHP_TARGET_AUTO="production-framework" ;; \
		esac; \
		export NODE_TARGET="$${NODE_TARGET:-$$NODE_TARGET_AUTO}"; \
		export NODE_BACKEND_TARGET="$${NODE_BACKEND_TARGET:-$$NODE_BACKEND_TARGET_AUTO}"; \
		export NGINX_TARGET="$${NGINX_TARGET:-$$NGINX_TARGET_AUTO}"; \
		export PHP_TARGET="$${PHP_TARGET:-$$PHP_TARGET_AUTO}"; \
		PROJECT="$${COMPOSE_PROJECT_NAME:-zappzarapp}"; \
		echo -e "\033[0;33mBuilding chart images from production targets:\033[0m$$CORE (NODE_TARGET=$$NODE_TARGET, NGINX_TARGET=$$NGINX_TARGET, PHP_TARGET=$$PHP_TARGET)"; \
		case " $$CORE " in \
			*" php "*|*" nginx "*|*" node-backend "*) \
				$(DC) -f compose.yaml -f compose.production.yaml build node-backend || exit 1; \
				docker tag "$$PROJECT-node-backend:$$NODE_BACKEND_TARGET" zappzarapp-node-backend:latest || exit 1 ;; \
		esac; \
		REMAINING="$$(echo " $$CORE " | sed 's/ node-backend / /' )"; \
		if [ -n "$$(echo $$REMAINING | tr -d '[:space:]')" ]; then \
			$(DC) -f compose.yaml -f compose.production.yaml build $$REMAINING || exit 1; \
		fi; \
		for svc in $$CORE; do \
			case "$$svc" in \
				nginx) SRC_TAG="$$NGINX_TARGET" ;; \
				php) SRC_TAG="$$PHP_TARGET" ;; \
				node) SRC_TAG="$$NODE_TARGET" ;; \
				node-backend) continue ;; \
			esac; \
			docker tag "$$PROJECT-$$svc:$$SRC_TAG" "zappzarapp-$$svc:latest" || exit 1; \
		done; \
	fi

k8s-remove: ## Remove deployment from Kubernetes
	@$(LOAD_ENV) && \
	NAMESPACE="$${KUBE_NAMESPACE:-zappzarapp}" && \
	echo -e "\033[0;33mRemoving deployment from Kubernetes...\033[0m" && \
	helm uninstall zappzarapp --namespace "$$NAMESPACE" 2>/dev/null || echo "Release not found" && \
	echo -e "\033[0;32mDeployment removed!\033[0m"

k8s-status: ## Show Kubernetes deployment status
	@$(LOAD_ENV) && \
	NAMESPACE="$${KUBE_NAMESPACE:-zappzarapp}" && \
	echo -e "\033[0;34mNamespace: $$NAMESPACE\033[0m" && \
	echo "" && \
	echo -e "\033[0;33mHelm Release:\033[0m" && \
	helm status zappzarapp --namespace "$$NAMESPACE" 2>/dev/null || echo "Release not found" && \
	echo "" && \
	echo -e "\033[0;33mPods:\033[0m" && \
	kubectl get pods -n "$$NAMESPACE" 2>/dev/null || echo "No pods found" && \
	echo "" && \
	echo -e "\033[0;33mServices:\033[0m" && \
	kubectl get services -n "$$NAMESPACE" 2>/dev/null || echo "No services found"

k8s-logs: ## Show Kubernetes logs: make k8s-logs [pod]
	@POD="$(filter-out $@,$(MAKECMDGOALS))"; \
	$(LOAD_ENV) && \
	NAMESPACE="$${KUBE_NAMESPACE:-zappzarapp}" && \
	if [ -n "$$POD" ]; then \
		kubectl logs -f "$$POD" -n "$$NAMESPACE"; \
	else \
		echo -e "\033[0;33mUsage: make k8s-logs <pod-name>\033[0m"; \
		echo "Available pods:"; \
		kubectl get pods -n "$$NAMESPACE" --no-headers -o custom-columns=":metadata.name" 2>/dev/null; \
	fi

##@ Production Testing

test-production: ## Test production build with ZAPPZARAPP_ENV-configured services (smart, respects .env)
	@echo -e "\033[0;34m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m"
	@echo -e "\033[0;34m  Production Test: ZAPPZARAPP_ENV-aware configuration\033[0m"
	@echo -e "\033[0;34m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m"
	@# Ensure we're in production mode (check ZAPPZARAPP_ENV variable first, then .env file)
	@if [ ! -f .env ]; then \
		echo -e "\033[0;31mError: .env file not found\033[0m"; \
		exit 1; \
	fi
	@# LOAD_ENV honors a caller-supplied ZAPPZARAPP_ENV (CI context) over the env files
	@CURRENT_ENV=$$($(LOAD_ENV) && echo "$${ZAPPZARAPP_ENV:-development}"); \
	if [ "$$CURRENT_ENV" != "production" ]; then \
		echo -e "\033[0;31mError: ZAPPZARAPP_ENV must be 'production'\033[0m"; \
		echo -e "\033[0;33mCurrent: ZAPPZARAPP_ENV=$$CURRENT_ENV\033[0m"; \
		echo -e "\033[0;36mHint: Set ZAPPZARAPP_ENV=production in .env (local) or as environment variable (CI)\033[0m"; \
		exit 1; \
	fi
	@echo ""
	@echo -e "\033[0;36mℹ️  Services activated based on .env configuration:\033[0m"
	@$(LOAD_ENV) && \
	echo -e "   \033[0;32m✓\033[0m nginx (always)" && \
	if [ "$${ENABLE_PHP:-true}" = "true" ]; then echo -e "   \033[0;32m✓\033[0m php"; fi && \
	if [ "$${ENABLE_NODE:-true}" = "true" ]; then \
		case "$${NODE_MODE:-assets-api}" in \
			assets|idle|framework) echo -e "   \033[0;32m✓\033[0m node (NODE_MODE=$${NODE_MODE})";; \
			api) echo -e "   \033[0;32m✓\033[0m node-backend (NODE_MODE=$${NODE_MODE})";; \
			assets-api|framework-api) echo -e "   \033[0;32m✓\033[0m node + node-backend (NODE_MODE=$${NODE_MODE})";; \
		esac; \
	fi && \
	if [ "$${ENABLE_DATABASE:-true}" = "true" ]; then echo -e "   \033[0;32m✓\033[0m $${DB_TYPE:-postgres} (database)"; fi && \
	if [ "$${ENABLE_REDIS:-true}" = "true" ]; then echo -e "   \033[0;32m✓\033[0m redis"; fi && \
	if [ "$${ENABLE_MERCURE:-false}" = "true" ]; then echo -e "   \033[0;32m✓\033[0m mercure"; fi && \
	if [ "$${ENABLE_MEILISEARCH:-false}" = "true" ]; then echo -e "   \033[0;32m✓\033[0m meilisearch"; fi && \
	if [ "$${ENABLE_ELASTICSEARCH:-false}" = "true" ]; then echo -e "   \033[0;32m✓\033[0m elasticsearch"; fi && \
	if [ "$${ENABLE_SEAWEEDFS:-false}" = "true" ]; then echo -e "   \033[0;32m✓\033[0m seaweedfs"; fi && \
	if [ "$${ENABLE_RABBITMQ:-false}" = "true" ]; then echo -e "   \033[0;32m✓\033[0m rabbitmq"; fi
	@echo ""
	@# Build profile list (same logic as 'make up')
	@$(LOAD_ENV) && \
	PROFILES=""; \
	if [ "$${ENABLE_DATABASE:-true}" = "true" ]; then \
		PROFILES="$$PROFILES --profile $${DB_TYPE:-postgres}"; \
	fi; \
	if [ "$${ENABLE_PHP:-true}" = "true" ]; then PROFILES="$$PROFILES --profile php"; fi; \
	if [ "$${ENABLE_NODE:-true}" = "true" ]; then \
		case "$${NODE_MODE:-assets-api}" in \
			assets|idle) PROFILES="$$PROFILES --profile node" ;; \
			api) PROFILES="$$PROFILES --profile node-backend" ;; \
			assets-api) PROFILES="$$PROFILES --profile node --profile node-backend" ;; \
			framework) PROFILES="$$PROFILES --profile node" ;; \
			framework-api) PROFILES="$$PROFILES --profile node --profile node-backend" ;; \
			*) PROFILES="$$PROFILES --profile node" ;; \
		esac; \
	fi; \
	if [ "$${ENABLE_REDIS:-true}" = "true" ]; then PROFILES="$$PROFILES --profile redis"; fi; \
	if [ "$${ENABLE_MERCURE:-false}" = "true" ]; then PROFILES="$$PROFILES --profile mercure"; fi; \
	if [ "$${ENABLE_MEILISEARCH:-false}" = "true" ]; then PROFILES="$$PROFILES --profile meilisearch"; fi; \
	if [ "$${ENABLE_ELASTICSEARCH:-false}" = "true" ]; then PROFILES="$$PROFILES --profile elasticsearch"; fi; \
	if [ "$${ENABLE_SEAWEEDFS:-false}" = "true" ]; then PROFILES="$$PROFILES --profile seaweedfs"; fi; \
	if [ "$${ENABLE_RABBITMQ:-false}" = "true" ]; then PROFILES="$$PROFILES --profile rabbitmq"; fi; \
	echo -e "\033[0;33m▶ Starting services...\033[0m"; \
	ZAPPZARAPP_ENV=production $(DC) -f compose.yaml -f compose.production.yaml $$PROFILES up -d; \
	echo -e "\033[0;32m✓ Services started\033[0m"; \
	echo ""; \
	echo -e "\033[0;33m▶ Waiting for services to be ready...\033[0m"; \
	sleep 10; \
	echo -e "\033[0;32m✓ Initial wait completed\033[0m"; \
	echo ""; \
	echo -e "\033[0;33m▶ Running health checks...\033[0m"; \
	FAILED=0; \
	echo -n "   nginx... "; \
	if timeout 30 sh -c 'until curl -sf http://localhost:8080/health > /dev/null 2>&1; do sleep 1; done' 2>/dev/null; then \
		echo -e "\033[0;32m✓\033[0m"; \
	else \
		echo -e "\033[0;31m✗ (timeout)\033[0m"; \
		FAILED=1; \
	fi; \
	if [ "$${ENABLE_DATABASE:-true}" = "true" ]; then \
		if [ "$${DB_TYPE:-postgres}" = "postgres" ]; then \
			echo -n "   postgres... "; \
			if timeout 30 sh -c 'until ZAPPZARAPP_ENV=production docker compose -f compose.yaml -f compose.production.yaml exec -T postgres pg_isready -U app > /dev/null 2>&1; do sleep 1; done' 2>/dev/null; then \
				echo -e "\033[0;32m✓\033[0m"; \
			else \
				echo -e "\033[0;31m✗ (timeout)\033[0m"; \
				FAILED=1; \
			fi; \
		elif [ "$${DB_TYPE}" = "mariadb" ]; then \
			echo -n "   mariadb... "; \
			if timeout 30 sh -c 'until ZAPPZARAPP_ENV=production docker compose -f compose.yaml -f compose.production.yaml exec -T mariadb mariadb -u app -p$${DB_PASSWORD} -e "SELECT 1" > /dev/null 2>&1; do sleep 1; done' 2>/dev/null; then \
				echo -e "\033[0;32m✓\033[0m"; \
			else \
				echo -e "\033[0;31m✗ (timeout)\033[0m"; \
				FAILED=1; \
			fi; \
		fi; \
	fi; \
	if [ "$${ENABLE_REDIS:-true}" = "true" ]; then \
		echo -n "   redis... "; \
		if timeout 30 sh -c 'until ZAPPZARAPP_ENV=production docker compose -f compose.yaml -f compose.production.yaml exec -T redis redis-cli --tls --insecure ping > /dev/null 2>&1; do sleep 1; done' 2>/dev/null; then \
			echo -e "\033[0;32m✓\033[0m"; \
		else \
			echo -e "\033[0;31m✗ (timeout)\033[0m"; \
			FAILED=1; \
		fi; \
	fi; \
	echo ""; \
	if [ $$FAILED -eq 0 ]; then \
		echo -e "\033[0;32m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m"; \
		echo -e "\033[0;32m  ✓ Production test passed\033[0m"; \
		echo -e "\033[0;32m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m"; \
	else \
		echo -e "\033[0;31m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m"; \
		echo -e "\033[0;31m  ✗ Production test failed\033[0m"; \
		echo -e "\033[0;31m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m"; \
		echo ""; \
		echo -e "\033[0;33m▶ Container logs:\033[0m"; \
		$(DC) logs --tail=50; \
		exit 1; \
	fi

test-production-minimal: ## Test production build with minimal services (nginx + app + db only)
	@echo -e "\033[0;34m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m"
	@echo -e "\033[0;34m  Production Test: Minimal (Core Services)\033[0m"
	@echo -e "\033[0;34m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m"
	@if [ ! -f .env ]; then \
		echo -e "\033[0;31mError: .env file not found\033[0m"; \
		exit 1; \
	fi
	@echo ""
	@echo -e "\033[0;36mℹ️  Starting minimal core services:\033[0m"
	@$(LOAD_ENV) && \
	echo -e "   \033[0;32m✓\033[0m nginx" && \
	if [ "$${ENABLE_PHP:-true}" = "true" ]; then echo -e "   \033[0;32m✓\033[0m php"; fi && \
	if [ "$${ENABLE_NODE:-true}" = "true" ]; then \
		case "$${NODE_MODE:-assets-api}" in \
			api) echo -e "   \033[0;32m✓\033[0m node-backend";; \
			*) echo -e "   \033[0;32m✓\033[0m node";; \
		esac; \
	fi && \
	echo -e "   \033[0;32m✓\033[0m $${DB_TYPE:-postgres} (database)"
	@echo ""
	@$(LOAD_ENV) && \
	PROFILES="--profile $${DB_TYPE:-postgres}"; \
	if [ "$${ENABLE_PHP:-true}" = "true" ]; then PROFILES="$$PROFILES --profile php"; fi; \
	if [ "$${ENABLE_NODE:-true}" = "true" ]; then \
		case "$${NODE_MODE:-assets-api}" in \
			api) PROFILES="$$PROFILES --profile node-backend" ;; \
			*) PROFILES="$$PROFILES --profile node" ;; \
		esac; \
	fi; \
	echo -e "\033[0;33m▶ Starting services...\033[0m"; \
	ZAPPZARAPP_ENV=production $(DC) -f compose.yaml -f compose.production.yaml $$PROFILES up -d; \
	echo -e "\033[0;32m✓ Services started\033[0m"; \
	echo ""; \
	echo -e "\033[0;33m▶ Waiting for services to be ready...\033[0m"; \
	sleep 10; \
	echo -e "\033[0;32m✓ Initial wait completed\033[0m"; \
	echo ""; \
	echo -e "\033[0;33m▶ Running health checks...\033[0m"; \
	FAILED=0; \
	echo -n "   nginx... "; \
	if timeout 30 sh -c 'until curl -sf http://localhost:8080/health > /dev/null 2>&1; do sleep 1; done' 2>/dev/null; then \
		echo -e "\033[0;32m✓\033[0m"; \
	else \
		echo -e "\033[0;31m✗ (timeout)\033[0m"; \
		FAILED=1; \
	fi; \
	if [ "$${DB_TYPE:-postgres}" = "postgres" ]; then \
		echo -n "   postgres... "; \
		if timeout 30 sh -c 'until ZAPPZARAPP_ENV=production docker compose -f compose.yaml -f compose.production.yaml exec -T postgres pg_isready -U app > /dev/null 2>&1; do sleep 1; done' 2>/dev/null; then \
			echo -e "\033[0;32m✓\033[0m"; \
		else \
			echo -e "\033[0;31m✗ (timeout)\033[0m"; \
			FAILED=1; \
		fi; \
	elif [ "$${DB_TYPE}" = "mariadb" ]; then \
		echo -n "   mariadb... "; \
		if timeout 30 sh -c 'until ZAPPZARAPP_ENV=production docker compose -f compose.yaml -f compose.production.yaml exec -T mariadb mariadb -u app -p$${DB_PASSWORD} -e "SELECT 1" > /dev/null 2>&1; do sleep 1; done' 2>/dev/null; then \
			echo -e "\033[0;32m✓\033[0m"; \
		else \
			echo -e "\033[0;31m✗ (timeout)\033[0m"; \
			FAILED=1; \
		fi; \
	fi; \
	echo ""; \
	if [ $$FAILED -eq 0 ]; then \
		echo -e "\033[0;32m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m"; \
		echo -e "\033[0;32m  ✓ Minimal production test passed\033[0m"; \
		echo -e "\033[0;32m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m"; \
	else \
		echo -e "\033[0;31m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m"; \
		echo -e "\033[0;31m  ✗ Minimal production test failed\033[0m"; \
		echo -e "\033[0;31m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m"; \
		echo ""; \
		echo -e "\033[0;33m▶ Container logs:\033[0m"; \
		$(DC) logs --tail=50; \
		exit 1; \
	fi

test-production-full: ## Test production build with ALL services (comprehensive, ignores ENABLE_*)
	@echo -e "\033[0;34m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m"
	@echo -e "\033[0;34m  Production Test: Full (All Services)\033[0m"
	@echo -e "\033[0;34m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m"
	@echo ""
	@echo -e "\033[0;36mℹ️  Starting ALL available services:\033[0m"
	@echo -e "   \033[0;32m✓\033[0m nginx"
	@echo -e "   \033[0;32m✓\033[0m php"
	@echo -e "   \033[0;32m✓\033[0m node + node-backend"
	@echo -e "   \033[0;32m✓\033[0m postgres"
	@echo -e "   \033[0;32m✓\033[0m mariadb"
	@echo -e "   \033[0;32m✓\033[0m redis"
	@echo -e "   \033[0;32m✓\033[0m elasticsearch"
	@echo -e "   \033[0;32m✓\033[0m meilisearch"
	@echo -e "   \033[0;32m✓\033[0m mercure"
	@echo -e "   \033[0;32m✓\033[0m rabbitmq"
	@echo -e "   \033[0;32m✓\033[0m seaweedfs"
	@echo ""
	@echo -e "\033[0;33m▶ Starting services...\033[0m"
	@ZAPPZARAPP_ENV=production $(DC) -f compose.yaml -f compose.production.yaml \
		--profile php \
		--profile node \
		--profile node-backend \
		--profile postgres \
		--profile mariadb \
		--profile redis \
		--profile elasticsearch \
		--profile meilisearch \
		--profile mercure \
		--profile rabbitmq \
		--profile seaweedfs \
		up -d
	@echo -e "\033[0;32m✓ Services started\033[0m"
	@echo ""
	@echo -e "\033[0;33m▶ Waiting for services to be ready (this may take a while)...\033[0m"
	@sleep 20
	@echo -e "\033[0;32m✓ Initial wait completed\033[0m"
	@echo ""
	@echo -e "\033[0;33m▶ Running health checks...\033[0m"
	@FAILED=0; \
	echo -n "   nginx... "; \
	if timeout 30 sh -c 'until curl -sf http://localhost:8080/health > /dev/null 2>&1; do sleep 1; done' 2>/dev/null; then \
		echo -e "\033[0;32m✓\033[0m"; \
	else \
		echo -e "\033[0;31m✗ (timeout)\033[0m"; \
		FAILED=1; \
	fi; \
	echo -n "   postgres... "; \
	if timeout 30 sh -c 'until ZAPPZARAPP_ENV=production docker compose -f compose.yaml -f compose.production.yaml exec -T postgres pg_isready -U app > /dev/null 2>&1; do sleep 1; done' 2>/dev/null; then \
		echo -e "\033[0;32m✓\033[0m"; \
	else \
		echo -e "\033[0;31m✗ (timeout)\033[0m"; \
		FAILED=1; \
	fi; \
	echo -n "   redis... "; \
	if timeout 30 sh -c 'until ZAPPZARAPP_ENV=production docker compose -f compose.yaml -f compose.production.yaml exec -T redis redis-cli --tls --insecure ping > /dev/null 2>&1; do sleep 1; done' 2>/dev/null; then \
		echo -e "\033[0;32m✓\033[0m"; \
	else \
		echo -e "\033[0;31m✗ (timeout)\033[0m"; \
		FAILED=1; \
	fi; \
	echo -n "   elasticsearch... "; \
	if timeout 60 sh -c 'until curl -sf http://localhost:9200/_cluster/health > /dev/null 2>&1; do sleep 2; done' 2>/dev/null; then \
		echo -e "\033[0;32m✓\033[0m"; \
	else \
		echo -e "\033[0;33m⚠ (timeout, may not be critical)\033[0m"; \
	fi; \
	echo ""; \
	if [ $$FAILED -eq 0 ]; then \
		echo -e "\033[0;32m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m"; \
		echo -e "\033[0;32m  ✓ Full production test passed\033[0m"; \
		echo -e "\033[0;32m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m"; \
	else \
		echo -e "\033[0;31m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m"; \
		echo -e "\033[0;31m  ✗ Full production test failed\033[0m"; \
		echo -e "\033[0;31m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m"; \
		echo ""; \
		echo -e "\033[0;33m▶ Container logs:\033[0m"; \
		$(DC) logs --tail=50; \
		exit 1; \
	fi


##@ Node.js Development

node-dev-full: ## Start full-stack development (Vite + Node.js backend with PM2)
	@echo -e "\033[0;33mStarting full-stack development environment...\033[0m"
	@echo -e "\033[0;34mVite HMR: http://localhost:5173\033[0m"
	@echo -e "\033[0;34mNode.js API: http://localhost:3000\033[0m"
	@echo -e "\033[0;34mNginx Proxy: http://localhost:8080\033[0m"
	@docker compose exec node pnpm run dev:full

node-dev-vite: ## Start only Vite dev server with PM2
	@echo -e "\033[0;33mStarting Vite dev server...\033[0m"
	@docker compose exec node pnpm run dev:vite

node-dev-backend: ## Start only Node.js backend with PM2
	@echo -e "\033[0;33mStarting Node.js backend server...\033[0m"
	@docker compose exec node pnpm run dev:backend

node-frontend-dev: ## Start Node frontend framework dev server (Next.js, Nuxt, etc.)
	@echo -e "\033[0;33mStarting Node frontend framework dev server...\033[0m"
	@docker compose exec node pnpm run frontend:dev

node-frontend-build: ## Build Node frontend framework (Next.js, Nuxt, etc.)
	@echo -e "\033[0;33mBuilding Node frontend framework...\033[0m"
	@docker compose exec node pnpm run frontend:build

node-frontend-start: ## Start Node frontend framework production server
	@echo -e "\033[0;33mStarting Node frontend framework production server...\033[0m"
	@docker compose exec node pnpm run frontend:start

pnpm-install: ## Install Node.js dependencies (Docker - guaranteed consistency)
	@echo -e "\033[0;33mInstalling Node.js dependencies (Docker)...\033[0m"
	@# Fix bind mount bug: if lockfile is directory or missing, fix via Docker
	@# Note: Use USER_ID/GROUP_ID from .env (not host user) for Docker container compatibility
	@$(LOAD_ENV) && \
	if [ -d pnpm-lock.yaml ] || [ ! -f pnpm-lock.yaml ]; then \
		docker run --rm -v "$(PWD):/app" -w /app $(ALPINE_IMAGE) sh -c \
			"rm -rf pnpm-lock.yaml && touch pnpm-lock.yaml && chown $${USER_ID:-1000}:$${GROUP_ID:-1000} pnpm-lock.yaml"; \
	fi
	@# If lockfile exists and is valid AND not in CI mode, use --frozen-lockfile
	@# In CI (compose.ci.yaml), lockfile is not mounted so always use normal install
	@# Docker bind mounts don't support atomic rename (EBUSY error when pnpm writes lockfile)
	@# Solution: When generating lockfile, run in temp location and copy back
	@if echo "$${COMPOSE_FILE:-}" | grep -q "compose.ci.yaml"; then \
		echo -e "\033[0;33m  CI mode: lockfile not mounted, generating inside container...\033[0m"; \
		$(DC_RUN) run --rm --no-TTY --user root --entrypoint "" -e CI=true node pnpm install; \
	elif [ -s pnpm-lock.yaml ]; then \
		$(DC_RUN) run --rm --no-TTY --user root --entrypoint "" -e CI=true node pnpm install --frozen-lockfile; \
	else \
		echo -e "\033[0;33m  No valid lockfile found, generating...\033[0m"; \
		$(DC_RUN) run --rm --no-TTY --user root --entrypoint "" -e CI=true node sh -c ' \
			mkdir -p /tmp/pnpm-install/src/node/backend /tmp/pnpm-install/src/node/frontend && \
			cp /app/package.json /tmp/pnpm-install/ && \
			cp /app/pnpm-workspace.yaml /tmp/pnpm-install/ && \
			cp /app/src/node/backend/package.json /tmp/pnpm-install/src/node/backend/ && \
			cp /app/src/node/frontend/package.json /tmp/pnpm-install/src/node/frontend/ && \
			cd /tmp/pnpm-install && pnpm install && \
			cat /tmp/pnpm-install/pnpm-lock.yaml > /app/pnpm-lock.yaml && \
			cd /app && pnpm install --frozen-lockfile \
		'; \
	fi
	@echo -e "\033[0;32mDependencies installed!\033[0m"

pnpm-update: ## Update Node.js dependencies (updates pnpm-lock.yaml on host)
	@echo -e "\033[0;33mUpdating Node.js dependencies...\033[0m"
	@# Docker bind mounts don't support atomic rename (EBUSY error)
	@# Solution: Run pnpm with lock file in temp location, then copy back
	@# Must include workspace files for pnpm to resolve all packages
	@$(DC_RUN) run --rm --no-TTY --user root --entrypoint "" -e CI=true node sh -c ' \
		mkdir -p /tmp/pnpm-update/src/node/backend /tmp/pnpm-update/src/node/frontend && \
		cp /app/package.json /tmp/pnpm-update/package.json && \
		cp /app/pnpm-workspace.yaml /tmp/pnpm-update/pnpm-workspace.yaml && \
		cp /app/pnpm-lock.yaml /tmp/pnpm-update/pnpm-lock.yaml 2>/dev/null || true && \
		cp /app/src/node/backend/package.json /tmp/pnpm-update/src/node/backend/package.json && \
		cp /app/src/node/frontend/package.json /tmp/pnpm-update/src/node/frontend/package.json && \
		cd /tmp/pnpm-update && pnpm update && \
		cat /tmp/pnpm-update/package.json > /app/package.json && \
		cat /tmp/pnpm-update/pnpm-lock.yaml > /app/pnpm-lock.yaml \
	'
	@echo -e "\033[0;32mDependencies updated!\033[0m"

pnpm-install-local: ## Install Node.js dependencies (Local - IDE code completion only)
	@if command -v pnpm >/dev/null 2>&1; then \
		echo -e "\033[0;33m⚠️  Using local pnpm (version may differ from Docker).\033[0m"; \
		pnpm install; \
		echo -e "\033[0;32mDependencies installed!\033[0m"; \
	else \
		echo -e "\033[0;31mError: Local pnpm not found. Use 'make pnpm-install' instead.\033[0m"; \
		exit 1; \
	fi

pnpm-sync: ## Sync Node.js dependencies (after package.json changes, e.g., frontend scaffold)
	@echo -e "\033[0;33mSyncing Node.js dependencies...\033[0m"
	@# Re-resolve the lockfile to match changed manifests, then install from it.
	@# Docker bind mounts don't support atomic rename (EBUSY error when pnpm
	@# writes the lockfile), so resolve in a temp location and copy back via cat.
	@# --no-frozen-lockfile overrides the frozen default that CI=true implies;
	@# unlike pnpm-update this does not upgrade dependencies within their ranges.
	@$(DC_RUN) run --rm --no-TTY --user root --entrypoint "" -e CI=true node sh -c ' \
		mkdir -p /tmp/pnpm-sync/src/node/backend /tmp/pnpm-sync/src/node/frontend && \
		cp /app/package.json /tmp/pnpm-sync/package.json && \
		cp /app/pnpm-workspace.yaml /tmp/pnpm-sync/pnpm-workspace.yaml && \
		{ [ -s /app/pnpm-lock.yaml ] && cp /app/pnpm-lock.yaml /tmp/pnpm-sync/pnpm-lock.yaml || true; } && \
		cp /app/src/node/backend/package.json /tmp/pnpm-sync/src/node/backend/package.json && \
		cp /app/src/node/frontend/package.json /tmp/pnpm-sync/src/node/frontend/package.json && \
		cd /tmp/pnpm-sync && pnpm install --no-frozen-lockfile --lockfile-only && \
		cat /tmp/pnpm-sync/pnpm-lock.yaml > /app/pnpm-lock.yaml && \
		cd /app && pnpm install --frozen-lockfile \
	'
	@echo -e "\033[0;32mDependencies synced!\033[0m"

pnpm-upgrade: ## Upgrade pnpm package manager to latest version
	@echo -e "\033[0;33mUpgrading pnpm to latest version...\033[0m"
	@CURRENT=$$(grep -o '"pnpm@[^"]*"' package.json | tr -d '"') && \
	$(DC_RUN) run --rm --no-TTY node sh -c ' \
		LATEST=$$(npm view pnpm version) && \
		npm pkg set packageManager=pnpm@$$LATEST \
	' && \
	NEW=$$(grep -o '"pnpm@[^"]*"' package.json | tr -d '"') && \
	NEW_VERSION=$${NEW#pnpm@} && \
	sed -i "s/^ARG PNPM_VERSION=.*/ARG PNPM_VERSION=$$NEW_VERSION/" docker/node/Dockerfile && \
	echo -e "\033[0;32mpnpm upgraded: $$CURRENT → $$NEW (package.json + docker/node/Dockerfile)\033[0m"
	@$(MAKE) pnpm-sync

# ─────────────────────────────────────────────────────────────────────────────
# FRONTEND SCAFFOLDING
# ─────────────────────────────────────────────────────────────────────────────
# These commands scaffold a Node.js frontend framework in src/node/frontend/
# Each framework is configured to work with the zappzarapp infrastructure:
# - Port 3001 (frontend), with proxy to backend on port 3000
# - Docker-compatible (0.0.0.0 host binding)
# - Workspace-compatible (@zappzarapp/frontend package name)

FRONTEND_DIR := src/node/frontend
FRONTEND_PATCHES := docker/node/frontend-patches

node-frontend-clean: ## Remove existing frontend (keeps package.json placeholder)
	@# Check if node container is running (for later restart hint)
	$(eval NODE_WAS_RUNNING := $(shell docker compose ps --status running 2>/dev/null | grep -q "node" && echo "yes" || echo "no"))
	@# Warn if node container is running (dev server file watchers might get confused)
	@if [ "$(NODE_WAS_RUNNING)" = "yes" ]; then \
		echo -e "\033[0;33m⚠️  Node container is running. Dev server file watchers may cause issues.\033[0m"; \
		read -p "Continue anyway? [y/N]: " CONTINUE; \
		[ "$$CONTINUE" = "y" ] || [ "$$CONTINUE" = "Y" ] || exit 1; \
	fi
	@# Check if frontend has scaffolded content (more than just package.json)
	@FILE_COUNT=$$(find $(FRONTEND_DIR) -mindepth 1 ! -name 'package.json' | wc -l); \
	if [ "$$FILE_COUNT" -gt 0 ]; then \
		echo -e "\033[0;31m!!! WARNING: Frontend directory contains scaffolded content. !!!\033[0m"; \
		ls -la $(FRONTEND_DIR); \
		read -p "Are you sure you want to delete it? Type 'YES' to confirm: " CONFIRM; \
		if [ "$$CONFIRM" != "YES" ]; then \
			echo -e "\033[0;34mOperation cancelled.\033[0m"; \
			exit 1; \
		fi; \
	fi
	@echo -e "\033[0;33mCleaning frontend directory...\033[0m"
	@# Run inside container to handle root-owned files (.nuxt/, .next/, etc.)
	@$(DC_RUN) run --rm --no-TTY --user root --entrypoint "" node sh -c ' \
		cd /app/src/node/frontend && \
		find . -mindepth 1 ! -name "package.json" -exec rm -rf {} + 2>/dev/null || true'
	@# Also clean host-side files not visible to container (e.g., node_modules shadowed by volume)
	@find $(FRONTEND_DIR) -mindepth 1 ! -name 'package.json' -exec rm -rf {} + 2>/dev/null || true
	@# Warn if host node_modules still exists (root-owned, needs manual cleanup)
	@if [ -d "$(FRONTEND_DIR)/node_modules" ]; then \
		echo -e "\033[0;33m⚠️  Warning: $(FRONTEND_DIR)/node_modules could not be deleted (likely root-owned).\033[0m"; \
		echo -e "\033[0;33m   To fix: sudo rm -rf $(FRONTEND_DIR)/node_modules\033[0m"; \
	fi
	@# Restore placeholder package.json if deleted
	@if [ ! -f "$(FRONTEND_DIR)/package.json" ]; then \
		echo '{"name": "@zappzarapp/frontend","version": "0.0.0","private": true,"scripts": {"info": "echo Run make node-frontend-nuxt, node-frontend-next, node-frontend-remix, or node-frontend-sveltekit to scaffold a frontend"}}' > $(FRONTEND_DIR)/package.json; \
	fi
	@echo -e "\033[0;32mFrontend directory cleaned!\033[0m"
	@if [ "$(NODE_WAS_RUNNING)" = "yes" ]; then \
		echo -e "\033[0;36mNext: make node-frontend-{nuxt|next|remix|sveltekit}, then make pnpm-sync, then make restart\033[0m"; \
	else \
		echo -e "\033[0;36mNext: make node-frontend-{nuxt|next|remix|sveltekit}, then make pnpm-sync, then make up\033[0m"; \
	fi

node-frontend-nuxt: node-frontend-clean ## Scaffold Nuxt 3 frontend (interactive)
	@echo -e "\033[0;33mScaffolding Nuxt 3 frontend...\033[0m"
	@$(DC_RUN) run --rm -it node sh -c '\
		cd /app/src/node/frontend && \
		pnpm dlx nuxi@latest init . --packageManager pnpm --gitInit false --no-install && \
		sh /app/docker/node/frontend-patches/nuxt.post-install.sh .'
	@echo -e "\033[0;32mNuxt 3 scaffolded! Run 'make pnpm-sync' to install dependencies.\033[0m"

node-frontend-next: node-frontend-clean ## Scaffold Next.js frontend (interactive)
	@echo -e "\033[0;33mScaffolding Next.js frontend...\033[0m"
	@$(DC_RUN) run --rm -it node sh -c '\
		cd /app/src/node/frontend && \
		pnpm dlx create-next-app@latest . --use-pnpm --skip-install && \
		sh /app/docker/node/frontend-patches/next.post-install.sh .'
	@echo -e "\033[0;32mNext.js scaffolded! Run 'make pnpm-sync' to install dependencies.\033[0m"

node-frontend-remix: node-frontend-clean ## Scaffold React Router frontend (formerly Remix v2)
	@echo -e "\033[0;33mScaffolding React Router frontend...\033[0m"
	@$(DC_RUN) run --rm -it node sh -c '\
		TEMP_DIR=$$(mktemp -d) && \
		cd "$$TEMP_DIR" && \
		pnpm dlx create-react-router@latest frontend --no-install && \
		cp -r frontend/. /app/src/node/frontend/ && \
		rm -rf "$$TEMP_DIR" && \
		cd /app/src/node/frontend && \
		sh /app/docker/node/frontend-patches/remix.post-install.sh .'
	@echo -e "\033[0;32mReact Router scaffolded! Run 'make pnpm-sync' to install dependencies.\033[0m"

node-frontend-sveltekit: node-frontend-clean ## Scaffold SvelteKit frontend (interactive)
	@echo -e "\033[0;33mScaffolding SvelteKit frontend...\033[0m"
	@$(DC_RUN) run --rm -it node sh -c '\
		TEMP_DIR=$$(mktemp -d) && \
		cd "$$TEMP_DIR" && \
		pnpm dlx sv create frontend --template minimal --types ts --no-add-ons --no-install && \
		cp -r frontend/. /app/src/node/frontend/ && \
		rm -rf "$$TEMP_DIR" && \
		cd /app/src/node/frontend && \
		sh /app/docker/node/frontend-patches/sveltekit.post-install.sh .'
	@echo -e "\033[0;32mSvelteKit scaffolded! Run 'make pnpm-sync' to install dependencies.\033[0m"

node-build: ## Executes the frontend build inside the Node container
	@echo -e "\033[0;33mExecuting frontend build...\033[0m"
	@# Clean Vite temp directory to avoid cross-UID permission issues in CI
	@rm -rf node_modules/.vite-temp 2>/dev/null || true
	@$(DC_RUN) run --rm node pnpm run build

node-up: ## Starts the Node service alongside the standard stack (Uses the default 'assets' target)
	@echo -e "\033[0;33mStarting Node service (assets target)...\033[0m"
	# NODE_TARGET is unset, so compose.yaml defaults to the 'assets' target (sleep infinity).
	@$(MAKE) --silent up

node-api-up: ## Starts the Node.js Backend API Server (uses 'api' target) alongside the stack
	@echo -e "\033[0;33mStarting Node.js Backend API Server (api target)...\033[0m"
	# Sets NODE_TARGET environment variable to switch the build target to 'api'.
	@NODE_TARGET="api" $(MAKE) --silent up

node-framework-up: ## Starts the Node.js Frontend Server (Next.js, Nuxt, etc., uses 'framework' target)
	@echo -e "\033[0;33mStarting Node.js Frontend Server (framework target)...\033[0m"
	# Sets NODE_TARGET environment variable to switch the build target to 'framework'.
	@NODE_TARGET="framework" $(MAKE) --silent up

node-dev: ## Start Vite dev server with HMR (Hot Module Replacement)
	@echo -e "\033[0;33mStarting Vite dev server with HMR...\033[0m"
	@echo -e "\033[0;34mAccess: http://localhost:5173\033[0m"
	@echo -e "\033[0;34mProxy via Nginx: http://localhost:8080\033[0m"
	@docker compose exec node pnpm run dev

node-server-dev: ## Start Node.js backend in development watch mode (tsx watch)
	@echo -e "\033[0;33mStarting Node.js backend in watch mode...\033[0m"
	@echo -e "\033[0;34mAccess: http://localhost:3000/health\033[0m"
	@docker compose exec node pnpm run backend:dev

node-server-build: ## Build Node.js backend (TypeScript -> JavaScript)
	@echo -e "\033[0;33mBuilding Node.js backend...\033[0m"
	@docker compose exec node pnpm run backend:build
	@echo -e "\033[0;32mBackend built successfully! Output: src/node/backend/dist/\033[0m"

node-pm2-status: ## Show PM2 process status
	@docker compose exec node pnpm run pm2:status

node-pm2-logs: ## Show PM2 logs
	@docker compose exec node pnpm run pm2:logs

node-pm2-restart: ## Restart PM2 processes
	@docker compose exec node pnpm run pm2:restart

node-pm2-stop: ## Stop PM2 processes
	@docker compose exec node pnpm run pm2:stop

##@ Database & Cache

redis-cli: ## Open Redis CLI
	@docker compose exec redis redis-cli

redis-flush: ## Flush all Redis data (DANGEROUS!)
	@echo -e "\033[0;31m⚠️  WARNING: This will delete ALL data in Redis!\033[0m"
	@read -p "Type 'YES' to confirm: " CONFIRM; \
	if [ "$$CONFIRM" = "YES" ]; then \
		docker compose exec redis redis-cli FLUSHALL; \
		echo -e "\033[0;32mRedis flushed!\033[0m"; \
	else \
		echo -e "\033[0;34mOperation cancelled.\033[0m"; \
	fi

redis-monitor: ## Monitor Redis commands in real-time
	@docker compose exec redis redis-cli MONITOR

postgres-cli: ## Open PostgreSQL CLI (psql)
	@$(LOAD_ENV) && . ./docker/scripts/parse-db-url.sh && \
		docker compose exec postgres psql -U "$$DB_USER" -d "$$DB_NAME"

postgres-dump: ## Create database backup (dump.sql)
	@echo -e "\033[0;33mCreating database backup...\033[0m"
	@$(LOAD_ENV) && . ./docker/scripts/parse-db-url.sh && \
		docker compose exec postgres pg_dump -U "$$DB_USER" -d "$$DB_NAME" > dump.sql
	@echo -e "\033[0;32mBackup saved to dump.sql\033[0m"

postgres-restore: ## Restore database from dump.sql
	@if [ ! -f dump.sql ]; then \
		echo -e "\033[0;31mError: dump.sql not found!\033[0m"; \
		exit 1; \
	fi
	@echo -e "\033[0;33mRestoring database from dump.sql...\033[0m"
	@$(LOAD_ENV) && . ./docker/scripts/parse-db-url.sh && \
		docker compose exec -T postgres psql -U "$$DB_USER" -d "$$DB_NAME" < dump.sql
	@echo -e "\033[0;32mDatabase restored!\033[0m"

mariadb-cli: ## Open MariaDB CLI
	@$(LOAD_ENV) && . ./docker/scripts/parse-db-url.sh && \
		docker compose exec mariadb mariadb -u "$$DB_USER" -p"$$DB_PASSWORD" "$$DB_NAME"

mariadb-dump: ## Create MariaDB database backup (dump.sql)
	@echo -e "\033[0;33mCreating MariaDB database backup...\033[0m"
	@$(LOAD_ENV) && . ./docker/scripts/parse-db-url.sh && \
		docker compose exec mariadb mariadb-dump -u "$$DB_USER" -p"$$DB_PASSWORD" "$$DB_NAME" > dump.sql
	@echo -e "\033[0;32mBackup saved to dump.sql\033[0m"

mariadb-restore: ## Restore MariaDB database from dump.sql
	@if [ ! -f dump.sql ]; then \
		echo -e "\033[0;31mError: dump.sql not found!\033[0m"; \
		exit 1; \
	fi
	@echo -e "\033[0;33mRestoring MariaDB database from dump.sql...\033[0m"
	@$(LOAD_ENV) && . ./docker/scripts/parse-db-url.sh && \
		docker compose exec -T mariadb mariadb -u "$$DB_USER" -p"$$DB_PASSWORD" "$$DB_NAME" < dump.sql
	@echo -e "\033[0;32mMariaDB database restored!\033[0m"

##@ Database Tools

adminer-up: ## Start Adminer database UI (development only)
	$(call require_development,Adminer)
	@echo -e "\033[0;34mStarting Adminer...\033[0m"
	@$(DC) --profile adminer up -d adminer
	@echo -e "\033[0;32mAdminer available at: https://localhost:$${NGINX_SSL_PORT:-8443}/_dev/adminer/\033[0m"

adminer-down: ## Stop Adminer
	$(call require_development,Adminer)
	@$(DC) --profile adminer stop adminer

pgadmin-up: ## Start pgAdmin PostgreSQL UI (development only)
	$(call require_development,pgAdmin)
	@echo -e "\033[0;34mStarting pgAdmin...\033[0m"
	@$(DC) --profile pgadmin up -d pgadmin
	@echo -e "\033[0;32mpgAdmin available at: https://localhost:$${NGINX_SSL_PORT:-8443}/_dev/pgadmin/\033[0m"

pgadmin-down: ## Stop pgAdmin
	$(call require_development,pgAdmin)
	@$(DC) --profile pgadmin stop pgadmin

db-tools-up: ## Start all database tools (Adminer + pgAdmin)
	$(call require_development,Database tools)
	@$(MAKE) adminer-up
	@$(MAKE) pgadmin-up

db-tools-down: ## Stop all database tools
	$(call require_development,Database tools)
	@$(MAKE) adminer-down
	@$(MAKE) pgadmin-down

postgres-cli-enhanced: ## Open PostgreSQL CLI with auto-complete (pgcli)
	$(call require_development,pgcli)
	@$(LOAD_ENV) && . ./docker/scripts/parse-db-url.sh && \
		docker compose exec php pgcli -h postgres -U "$$DB_USER" -d "$$DB_NAME"

mariadb-cli-enhanced: ## Open MariaDB CLI with auto-complete (mycli)
	$(call require_development,mycli)
	@$(LOAD_ENV) && . ./docker/scripts/parse-db-url.sh && \
		docker compose exec php mycli -h mariadb -u "$$DB_USER" -p"$$DB_PASSWORD" "$$DB_NAME"

sqlite-cli: ## Open SQLite CLI - Usage: make sqlite-cli FILE=storage/app.sqlite
	$(call require_development,litecli)
	@if [ -z "$(FILE)" ]; then \
		echo -e "\033[0;31mUsage: make sqlite-cli FILE=path/to/database.sqlite\033[0m"; \
		exit 1; \
	fi
	@docker compose exec php litecli /var/www/html/$(FILE)

##@ Elasticsearch

.PHONY: es-setup-api-key es-health es-api-key

es-setup-api-key: ## Generate Elasticsearch API key (run after ES is healthy)
	@echo -e "\033[0;33mGenerating Elasticsearch API key...\033[0m"
	@BOOTSTRAP_PW=$$(cat secrets/elasticsearch_bootstrap_password.txt 2>/dev/null || cat secrets/elasticsearch_bootstrap_password.example.txt) && \
	docker compose exec -T elasticsearch curl -sk \
		-u "elastic:$$BOOTSTRAP_PW" \
		-X POST "https://localhost:9200/_security/api_key" \
		-H "Content-Type: application/json" \
		-d '{"name": "zappzarapp-dev", "role_descriptors": {"all_access": {"cluster": ["all"], "indices": [{"names": ["*"], "privileges": ["all"]}]}}}' \
		| jq -r '.encoded' > secrets/elasticsearch_api_key.txt
	@echo -e "\033[0;32mAPI key saved to secrets/elasticsearch_api_key.txt\033[0m"

es-health: ## Check Elasticsearch cluster health
	@API_KEY=$$(cat secrets/elasticsearch_api_key.txt 2>/dev/null) && \
	if [ -z "$$API_KEY" ]; then \
		echo -e "\033[0;31mError: No API key found. Run: make es-setup-api-key\033[0m"; \
		exit 1; \
	fi && \
	docker compose exec -T elasticsearch curl -sk \
		-H "Authorization: ApiKey $$API_KEY" \
		"https://localhost:9200/_cluster/health" | jq .

es-api-key: ## Show Elasticsearch API key
	@cat secrets/elasticsearch_api_key.txt 2>/dev/null || echo -e "\033[0;31mNo API key found. Run: make es-setup-api-key\033[0m"

##@ Backup & Migrations

backup-all: ## Create backups of all enabled services (database, seaweedfs, rabbitmq, elasticsearch)
	@echo -e "\033[0;33m=== Creating backups of all enabled services ===${NC}\033[0m"
	@$(LOAD_ENV) && \
	if [ "$${ENABLE_DATABASE:-true}" = "true" ]; then \
		echo -e "\033[0;34m[1/4] Database backup...\033[0m"; \
		bash docker/scripts/backup-databases.sh; \
	else \
		echo -e "\033[0;37m[1/4] Database: skipped (disabled)\033[0m"; \
	fi
	@$(LOAD_ENV) && \
	if [ "$${ENABLE_SEAWEEDFS:-false}" = "true" ]; then \
		echo -e "\033[0;34m[2/4] SeaweedFS backup...\033[0m"; \
		bash docker/scripts/backup-seaweedfs.sh; \
	else \
		echo -e "\033[0;37m[2/4] SeaweedFS: skipped (disabled)\033[0m"; \
	fi
	@$(LOAD_ENV) && \
	if [ "$${ENABLE_RABBITMQ:-false}" = "true" ]; then \
		echo -e "\033[0;34m[3/4] RabbitMQ backup...\033[0m"; \
		bash docker/scripts/backup-rabbitmq.sh; \
	else \
		echo -e "\033[0;37m[3/4] RabbitMQ: skipped (disabled)\033[0m"; \
	fi
	@$(LOAD_ENV) && \
	if [ "$${ENABLE_ELASTICSEARCH:-false}" = "true" ]; then \
		echo -e "\033[0;34m[4/4] Elasticsearch backup...\033[0m"; \
		bash docker/scripts/backup-elasticsearch.sh; \
	else \
		echo -e "\033[0;37m[4/4] Elasticsearch: skipped (disabled)\033[0m"; \
	fi
	@echo -e "\033[0;32m=== All backups complete ===${NC}\033[0m"

backup-db: ## Create encrypted database backup (GDPR-compliant, RETENTION=days to override)
	@echo -e "\033[0;33mCreating encrypted database backup...\033[0m"
	@if [ -n "$(RETENTION)" ]; then \
		bash docker/scripts/backup-databases.sh --retention $(RETENTION); \
	else \
		bash docker/scripts/backup-databases.sh; \
	fi
	@echo -e "\033[0;34mBackups are stored in ./backups/db/\033[0m"

backup-db-list: ## List all database backups
	@echo -e "\033[0;33mAvailable database backups:\033[0m"
	@if [ -d backups/db ]; then \
		ls -lah backups/db/*.sql.gz* 2>/dev/null || echo -e "\033[0;34mNo backups found.\033[0m"; \
	else \
		echo -e "\033[0;34mNo backups directory. Run 'make backup-db' first.\033[0m"; \
	fi

backup-db-restore: ## Restore database from backup (interactive)
	@echo -e "\033[0;33mAvailable database backups:\033[0m"
	@if [ -d backups/db ]; then \
		ls -1 backups/db/*.sql.gz* 2>/dev/null || echo "No backups found."; \
	else \
		echo "No backups directory."; \
		exit 1; \
	fi
	@echo ""
	@read -p "Enter backup filename (from ./backups/db/): " BACKUP_FILE; \
	bash docker/scripts/restore-database.sh "backups/db/$$BACKUP_FILE"

backup-seaweedfs: ## Create encrypted SeaweedFS backup (all buckets)
	@echo -e "\033[0;33mCreating SeaweedFS backup...\033[0m"
	@if [ -n "$(RETENTION)" ]; then \
		bash docker/scripts/backup-seaweedfs.sh --retention $(RETENTION); \
	else \
		bash docker/scripts/backup-seaweedfs.sh; \
	fi

backup-seaweedfs-list: ## List all SeaweedFS backups
	@echo -e "\033[0;33mAvailable SeaweedFS backups:\033[0m"
	@if [ -d backups/seaweedfs ]; then \
		ls -lah backups/seaweedfs/*.tar.gz* 2>/dev/null || echo -e "\033[0;34mNo backups found.\033[0m"; \
	else \
		echo -e "\033[0;34mNo backups directory. Run 'make backup-seaweedfs' first.\033[0m"; \
	fi

backup-seaweedfs-restore: ## Restore SeaweedFS from backup (interactive)
	@echo -e "\033[0;33mAvailable SeaweedFS backups:\033[0m"
	@if [ -d backups/seaweedfs ]; then \
		ls -1 backups/seaweedfs/*.tar.gz* 2>/dev/null || echo "No backups found."; \
	else \
		echo "No backups directory."; \
		exit 1; \
	fi
	@echo ""
	@echo -e "\033[0;33mNote: Restore will overwrite existing SeaweedFS data.\033[0m"
	@read -p "Enter backup filename (from ./backups/seaweedfs/): " BACKUP_FILE; \
	bash docker/scripts/restore-seaweedfs.sh "backups/seaweedfs/$$BACKUP_FILE"

backup-rabbitmq: ## Export RabbitMQ definitions (exchanges, queues, bindings)
	@echo -e "\033[0;33mCreating RabbitMQ definitions backup...\033[0m"
	@if [ -n "$(RETENTION)" ]; then \
		bash docker/scripts/backup-rabbitmq.sh --retention $(RETENTION); \
	else \
		bash docker/scripts/backup-rabbitmq.sh; \
	fi

backup-rabbitmq-list: ## List all RabbitMQ backups
	@echo -e "\033[0;33mAvailable RabbitMQ backups:\033[0m"
	@if [ -d backups/rabbitmq ]; then \
		ls -lah backups/rabbitmq/*.json* 2>/dev/null || echo -e "\033[0;34mNo backups found.\033[0m"; \
	else \
		echo -e "\033[0;34mNo backups directory. Run 'make backup-rabbitmq' first.\033[0m"; \
	fi

backup-rabbitmq-restore: ## Restore RabbitMQ definitions from backup (interactive)
	@echo -e "\033[0;33mAvailable RabbitMQ backups:\033[0m"
	@if [ -d backups/rabbitmq ]; then \
		ls -1 backups/rabbitmq/*.json* 2>/dev/null || echo "No backups found."; \
	else \
		echo "No backups directory."; \
		exit 1; \
	fi
	@echo ""
	@read -p "Enter backup filename (from ./backups/rabbitmq/): " BACKUP_FILE; \
	bash docker/scripts/restore-rabbitmq.sh "backups/rabbitmq/$$BACKUP_FILE"

backup-elasticsearch: ## Create Elasticsearch snapshot (all indices)
	@echo -e "\033[0;33mCreating Elasticsearch snapshot...\033[0m"
	@if [ -n "$(RETENTION)" ]; then \
		bash docker/scripts/backup-elasticsearch.sh --retention $(RETENTION); \
	else \
		bash docker/scripts/backup-elasticsearch.sh; \
	fi

backup-elasticsearch-list: ## List all Elasticsearch backups
	@echo -e "\033[0;33mAvailable Elasticsearch backups:\033[0m"
	@if [ -d backups/elasticsearch ]; then \
		ls -lah backups/elasticsearch/*.tar.gz* 2>/dev/null || echo -e "\033[0;34mNo backups found.\033[0m"; \
	else \
		echo -e "\033[0;34mNo backups directory. Run 'make backup-elasticsearch' first.\033[0m"; \
	fi

backup-elasticsearch-restore: ## Restore Elasticsearch from backup (interactive)
	@echo -e "\033[0;33mAvailable Elasticsearch backups:\033[0m"
	@if [ -d backups/elasticsearch ]; then \
		ls -1 backups/elasticsearch/*.tar.gz* 2>/dev/null || echo "No backups found."; \
	else \
		echo "No backups directory."; \
		exit 1; \
	fi
	@echo ""
	@echo -e "\033[0;33mNote: Restore will overwrite existing Elasticsearch indices.\033[0m"
	@read -p "Enter backup filename (from ./backups/elasticsearch/): " BACKUP_FILE; \
	bash docker/scripts/restore-elasticsearch.sh "backups/elasticsearch/$$BACKUP_FILE"

db-migrations: ## Run database migrations (encryption helpers, audit logs)
	@echo -e "\033[0;33mRunning database migrations...\033[0m"
	@if [ ! -f .env ]; then \
		echo -e "\033[0;31mError: .env not found. Run 'make init' first.\033[0m"; \
		exit 1; \
	fi
	@$(LOAD_ENV) && . ./docker/scripts/parse-db-url.sh && \
	if [ "$$DB_TYPE" = "postgres" ]; then \
		echo -e "\033[0;34mRunning PostgreSQL migrations...\033[0m"; \
		for migration in migrations/postgresql/*.sql; do \
			if [ -f "$$migration" ]; then \
				echo -e "  Applying: $$(basename $$migration)"; \
				docker compose exec -T postgres psql -U "$$DB_USER" -d "$$DB_NAME" -f /dev/stdin < "$$migration" 2>&1 | grep -v "^$$" || true; \
			fi; \
		done; \
	elif [ "$$DB_TYPE" = "mariadb" ]; then \
		echo -e "\033[0;34mRunning MariaDB migrations...\033[0m"; \
		for migration in migrations/mariadb/*.sql; do \
			if [ -f "$$migration" ]; then \
				echo -e "  Applying: $$(basename $$migration)"; \
				docker compose exec -T mariadb mariadb -u "$$DB_USER" -p"$$DB_PASSWORD" "$$DB_NAME" < "$$migration" 2>&1 | grep -v "^$$" || true; \
			fi; \
		done; \
	else \
		echo -e "\033[0;31mError: Unknown DB_TYPE '$$DB_TYPE'\033[0m"; \
		exit 1; \
	fi
	@echo -e "\033[0;32mMigrations completed!\033[0m"

db-cleanup: ## Run retention policy cleanup (delete old logs)
	@echo -e "\033[0;33mRunning database cleanup (retention policy)...\033[0m"
	@if [ ! -f .env ]; then \
		echo -e "\033[0;31mError: .env not found. Run 'make init' first.\033[0m"; \
		exit 1; \
	fi
	@$(LOAD_ENV) && . ./docker/scripts/parse-db-url.sh && \
	RETENTION_DAYS=$${RETENTION_DAYS:-730}; \
	if [ "$$DB_TYPE" = "postgres" ]; then \
		echo -e "\033[0;34mPostgreSQL: Deleting audit_logs older than $$RETENTION_DAYS days...\033[0m"; \
		docker compose exec -T postgres psql -U "$$DB_USER" -d "$$DB_NAME" \
			-c "SELECT delete_old_logs('audit_logs', $$RETENTION_DAYS);" 2>/dev/null || \
			echo -e "\033[0;31mError: Run 'make db-migrations' first to create retention functions.\033[0m"; \
	elif [ "$$DB_TYPE" = "mariadb" ]; then \
		echo -e "\033[0;34mMariaDB: Deleting audit_logs older than $$RETENTION_DAYS days...\033[0m"; \
		docker compose exec -T mariadb mariadb -u "$$DB_USER" -p"$$DB_PASSWORD" "$$DB_NAME" \
			-e "CALL delete_old_logs('audit_logs', $$RETENTION_DAYS, @deleted); SELECT @deleted AS deleted_rows;" 2>/dev/null || \
			echo -e "\033[0;31mError: Run 'make db-migrations' first to create retention procedures.\033[0m"; \
	fi
	@echo -e "\033[0;32mCleanup completed!\033[0m"

##@ Workflow

check-health: ## Check application health by container status for all services
	@echo -e "\033[0;33mChecking Container Health Status...\033[0m\n"

	@echo -e "\033[0;34m📦 PHP-FPM:\033[0m"
	@$(LOAD_ENV); \
	if [ "$${ENABLE_PHP:-true}" = "true" ]; then \
		if [ "$$(docker inspect --format='{{.State.Health.Status}}' $$(docker compose ps -q php) 2>/dev/null)" = "healthy" ]; then \
			echo -e "\033[0;32m  ✅ Healthy\033[0m"; \
		else \
			echo -e "\033[0;31m  ❌ Unhealthy or not running\033[0m"; \
		fi; \
	else \
		echo -e "\033[0;37m  ⚪ Disabled (ENABLE_PHP=false)\033[0m"; \
	fi
	@echo ""

	@echo -e "\033[0;34m🟢 Node.js:\033[0m"
	@$(LOAD_ENV); \
	if [ "$${ENABLE_NODE:-true}" = "true" ]; then \
		if [ "$$(docker inspect --format='{{.State.Health.Status}}' $$(docker compose ps -q node) 2>/dev/null)" = "healthy" ]; then \
			echo -e "\033[0;32m  ✅ Healthy (mode: $${NODE_MODE:-assets-api})\033[0m"; \
		else \
			echo -e "\033[0;31m  ❌ Unhealthy or not running\033[0m"; \
		fi; \
	else \
		echo -e "\033[0;37m  ⚪ Disabled (ENABLE_NODE=false)\033[0m"; \
	fi
	@echo ""

	@echo -e "\033[0;34m💾 Database:\033[0m"
	@$(LOAD_ENV); \
	if [ "$${ENABLE_DATABASE:-true}" = "true" ]; then \
		DB_TYPE=$${DB_TYPE:-postgres}; \
		if [ "$$(docker inspect --format='{{.State.Health.Status}}' $$(docker compose ps -q $$DB_TYPE) 2>/dev/null)" = "healthy" ]; then \
			echo -e "\033[0;32m  ✅ $$DB_TYPE is Healthy\033[0m"; \
		else \
			echo -e "\033[0;31m  ❌ $$DB_TYPE is Unhealthy or not running\033[0m"; \
		fi; \
	else \
		echo -e "\033[0;37m  ⚪ Disabled (ENABLE_DATABASE=false)\033[0m"; \
	fi
	@echo ""

	@echo -e "\033[0;34m🔴 Redis:\033[0m"
	@$(LOAD_ENV); \
	if [ "$${ENABLE_REDIS:-true}" = "true" ]; then \
		if [ "$$(docker inspect --format='{{.State.Health.Status}}' $$(docker compose ps -q redis) 2>/dev/null)" = "healthy" ]; then \
			echo -e "\033[0;32m  ✅ Healthy\033[0m"; \
		else \
			echo -e "\033[0;31m  ❌ Unhealthy or not running\033[0m"; \
		fi; \
	else \
		echo -e "\033[0;37m  ⚪ Disabled (ENABLE_REDIS=false)\033[0m"; \
	fi
	@echo ""

	@echo -e "\033[0;34m🌐 Nginx HTTPS:\033[0m"
	@$(LOAD_ENV); \
	NGINX_SSL_PORT=$${NGINX_SSL_PORT:-8443}; \
	if command -v curl >/dev/null 2>&1; then \
		HTTP_CODE=$$(curl -sk -o /dev/null -w "%{http_code}" https://localhost:$$NGINX_SSL_PORT 2>/dev/null); \
		if [ "$$HTTP_CODE" = "200" ]; then \
			echo -e "\033[0;32m  ✅ HTTPS 200 OK (port $$NGINX_SSL_PORT)\033[0m"; \
		else \
			echo -e "\033[0;31m  ❌ HTTPS $$HTTP_CODE (port $$NGINX_SSL_PORT)\033[0m"; \
		fi; \
	else \
		echo -e "\033[0;33m  ⚠️  curl not found, skipping HTTPS check\033[0m"; \
	fi
	@echo ""

	@echo -e "\033[0;34m📡 Mercure (Real-time):\033[0m"
	@$(LOAD_ENV); \
	if [ "$${ENABLE_MERCURE:-false}" = "true" ]; then \
		if [ "$$(docker inspect --format='{{.State.Health.Status}}' $$(docker compose ps -q mercure) 2>/dev/null)" = "healthy" ]; then \
			echo -e "\033[0;32m  ✅ Healthy\033[0m"; \
		else \
			echo -e "\033[0;31m  ❌ Unhealthy or not running\033[0m"; \
		fi; \
	else \
		echo -e "\033[0;37m  ⚪ Disabled (ENABLE_MERCURE=false)\033[0m"; \
	fi
	@echo ""

	@echo -e "\033[0;34m🔍 Meilisearch:\033[0m"
	@$(LOAD_ENV); \
	if [ "$${ENABLE_MEILISEARCH:-false}" = "true" ]; then \
		if [ "$$(docker inspect --format='{{.State.Health.Status}}' $$(docker compose ps -q meilisearch) 2>/dev/null)" = "healthy" ]; then \
			echo -e "\033[0;32m  ✅ Healthy\033[0m"; \
		else \
			echo -e "\033[0;31m  ❌ Unhealthy or not running\033[0m"; \
		fi; \
	else \
		echo -e "\033[0;37m  ⚪ Disabled (ENABLE_MEILISEARCH=false)\033[0m"; \
	fi
	@echo ""

	@echo -e "\033[0;34m🔎 Elasticsearch:\033[0m"
	@$(LOAD_ENV); \
	if [ "$${ENABLE_ELASTICSEARCH:-false}" = "true" ]; then \
		if [ "$$(docker inspect --format='{{.State.Health.Status}}' $$(docker compose ps -q elasticsearch) 2>/dev/null)" = "healthy" ]; then \
			echo -e "\033[0;32m  ✅ Healthy\033[0m"; \
		else \
			echo -e "\033[0;31m  ❌ Unhealthy or not running\033[0m"; \
		fi; \
	else \
		echo -e "\033[0;37m  ⚪ Disabled (ENABLE_ELASTICSEARCH=false)\033[0m"; \
	fi
	@echo ""

	@echo -e "\033[0;34m📧 Mailpit (Email Testing):\033[0m"
	@$(LOAD_ENV); \
	if [ "$${ENABLE_MAILPIT:-false}" = "true" ]; then \
		if [ "$$(docker inspect --format='{{.State.Health.Status}}' $$(docker compose ps -q mailpit) 2>/dev/null)" = "healthy" ]; then \
			echo -e "\033[0;32m  ✅ Healthy\033[0m"; \
		else \
			echo -e "\033[0;31m  ❌ Unhealthy or not running\033[0m"; \
		fi; \
	else \
		echo -e "\033[0;37m  ⚪ Disabled (ENABLE_MAILPIT=false)\033[0m"; \
	fi
	@echo ""

	@echo -e "\033[0;34m📦 SeaweedFS (S3 Storage):\033[0m"
	@$(LOAD_ENV); \
	if [ "$${ENABLE_SEAWEEDFS:-false}" = "true" ]; then \
		if [ "$$(docker inspect --format='{{.State.Health.Status}}' $$(docker compose ps -q seaweedfs) 2>/dev/null)" = "healthy" ]; then \
			echo -e "\033[0;32m  ✅ Healthy\033[0m"; \
		else \
			echo -e "\033[0;31m  ❌ Unhealthy or not running\033[0m"; \
		fi; \
	else \
		echo -e "\033[0;37m  ⚪ Disabled (ENABLE_SEAWEEDFS=false)\033[0m"; \
	fi
	@echo ""

	@echo -e "\033[0;34m🐰 RabbitMQ (Message Broker):\033[0m"
	@$(LOAD_ENV); \
	if [ "$${ENABLE_RABBITMQ:-false}" = "true" ]; then \
		if [ "$$(docker inspect --format='{{.State.Health.Status}}' $$(docker compose ps -q rabbitmq) 2>/dev/null)" = "healthy" ]; then \
			echo -e "\033[0;32m  ✅ Healthy\033[0m"; \
		else \
			echo -e "\033[0;31m  ❌ Unhealthy or not running\033[0m"; \
		fi; \
	else \
		echo -e "\033[0;37m  ⚪ Disabled (ENABLE_RABBITMQ=false)\033[0m"; \
	fi

	@echo ""

open-app: ## Open the application in the browser
	@$(LOAD_ENV); \
	$(OPEN_CMD) "https://localhost:$${NGINX_SSL_PORT:-8443}"

open-dashboard: ## Open the Dev Dashboard in the browser
	@$(LOAD_ENV); \
	$(OPEN_CMD) "https://localhost:$${NGINX_SSL_PORT:-8443}/_dev/"

open-docs: ## Open generated API documentation in the browser (run 'make docs' first)
	@opened=0; \
	for f in docs/api/php/index.html docs/api/node-backend/index.html docs/api/node-frontend/index.html; do \
		if [ -f "$$f" ]; then $(OPEN_CMD) "$$f"; opened=1; fi; \
	done; \
	if [ "$$opened" = "0" ]; then \
		echo -e "\033[0;33mNo generated documentation found - run 'make docs' first\033[0m"; \
	fi

open-coverage: ## Open test coverage reports in the browser (run 'make test-coverage' first)
	@opened=0; \
	for f in build/coverage/php/index.html build/coverage/node/index.html; do \
		if [ -f "$$f" ]; then $(OPEN_CMD) "$$f"; opened=1; fi; \
	done; \
	if [ "$$opened" = "0" ]; then \
		echo -e "\033[0;33mNo coverage reports found - run 'make test-coverage' first\033[0m"; \
	fi

fresh: ## Complete clean slate rebuild, removing all data volumes (DANGEROUS!)
	@echo -e "\033[0;31m!!! WARNING: You are about to remove all containers, images, AND data volumes (e.g. database). !!!\033[0m"
	@read -p "Are you sure you want to proceed? Type 'YES' to confirm: " CONFIRM_FRESH; \
	if [ "$$CONFIRM_FRESH" != "YES" ]; then \
		echo -e "\033[0;34mOperation cancelled.\033[0m"; \
		exit 1; \
	fi
	@echo -e "\033[0;33mProceeding with fresh rebuild (no cache)...\033[0m"
	@# Clean up Goss test containers first (must be rebuilt with new images)
	@$(MAKE) --silent goss-cleanup
	@# Stop ALL containers and rebuild ALL images regardless of profile settings (fresh = complete reset)
	@if [ -f .env ]; then \
		$(LOAD_ENV) && if [ "$$ZAPPZARAPP_ENV" = "production" ]; then \
			$(DC) -f compose.yaml -f compose.production.yaml --profile php --profile node --profile redis --profile postgres --profile mariadb --profile mercure --profile meilisearch --profile elasticsearch --profile mailpit --profile seaweedfs --profile rabbitmq --profile adminer --profile pgadmin down -v --rmi all && \
			echo -e "\033[0;34mBuilding Node images first (node-backend supplies Vite assets to PHP + NGINX)...\033[0m" && \
			NODE_BACKEND_TARGET="$${NODE_BACKEND_TARGET:-api}"; \
			$(DC) -f compose.yaml -f compose.production.yaml --profile php --profile node --profile redis --profile postgres --profile mariadb --profile mercure --profile meilisearch --profile elasticsearch --profile mailpit --profile seaweedfs --profile rabbitmq --profile adminer --profile pgadmin build --no-cache node node-backend && \
			docker tag $${COMPOSE_PROJECT_NAME:-zappzarapp}-node-backend:$$NODE_BACKEND_TARGET zappzarapp-node-backend:latest && \
			echo -e "\033[0;34mBuilding remaining images...\033[0m" && \
			$(DC) -f compose.yaml -f compose.production.yaml --profile php --profile node --profile redis --profile postgres --profile mariadb --profile mercure --profile meilisearch --profile elasticsearch --profile mailpit --profile seaweedfs --profile rabbitmq --profile adminer --profile pgadmin build --no-cache; \
		else \
			$(DC) --profile php --profile node --profile redis --profile postgres --profile mariadb --profile mercure --profile meilisearch --profile elasticsearch --profile mailpit --profile seaweedfs --profile rabbitmq --profile adminer --profile pgadmin down -v --rmi all && \
			$(DC) --profile php --profile node --profile redis --profile postgres --profile mariadb --profile mercure --profile meilisearch --profile elasticsearch --profile mailpit --profile seaweedfs --profile rabbitmq --profile adminer --profile pgadmin build --no-cache; \
		fi; \
	else \
		$(DC) --profile php --profile node --profile redis --profile postgres --profile mariadb --profile mercure --profile meilisearch --profile elasticsearch --profile mailpit --profile seaweedfs --profile rabbitmq --profile adminer --profile pgadmin down -v --rmi all && \
		$(DC) --profile php --profile node --profile redis --profile postgres --profile mariadb --profile mercure --profile meilisearch --profile elasticsearch --profile mailpit --profile seaweedfs --profile rabbitmq --profile adminer --profile pgadmin build --no-cache; \
	fi
	@echo -e "\033[0;33mInstalling dependencies...\033[0m"
	@$(MAKE) --silent composer-install
	@$(MAKE) --silent pnpm-install
	@$(MAKE) --silent up

# Internal target: Core reset logic without confirmation (used by reset and reset-full)
_reset-core:
	@echo ""
	@# Check for mountpoints in directories we're about to clean
	@echo -e "\033[0;33m[1/5] Checking for mountpoints...\033[0m"
	@MOUNTPOINT_FOUND=0; \
	for dir in storage build public/build docs/api tools; do \
		if [ -d "$$dir" ]; then \
			while IFS= read -r mnt; do \
				if [ -n "$$mnt" ]; then \
					echo -e "\033[0;33m  ⚠ Mountpoint detected: $$mnt - will skip cleanup\033[0m"; \
					MOUNTPOINT_FOUND=1; \
				fi; \
			done < <(find "$$dir" -type d -exec sh -c 'mountpoint -q "$$1" 2>/dev/null && echo "$$1"' _ {} \;); \
		fi; \
	done; \
	if [ "$$MOUNTPOINT_FOUND" = "1" ]; then \
		echo -e "\033[0;33m  Directories with mountpoints will be skipped.\033[0m"; \
	else \
		echo -e "\033[0;32m  ✓ No mountpoints detected\033[0m"; \
	fi
	@echo -e "\033[0;33m[2/5] Cleaning up Goss test resources...\033[0m"
	@$(MAKE) --silent goss-cleanup
	@echo -e "\033[0;33m[3/5] Removing dependencies, lockfiles and build artifacts...\033[0m"
	@# Use Docker to remove directories that may have root ownership (from container operations)
	@# Combined into single run to avoid multiple image pulls
	@docker run --rm -v "$(PWD):/app" -w /app $(ALPINE_IMAGE) sh -c 'rm -rf vendor node_modules .pnpm-store composer.lock pnpm-lock.yaml build dist 2>/dev/null' || true
	@# Fallback: try local rm for any remaining files (user-owned)
	@rm -rf vendor node_modules .pnpm-store composer.lock pnpm-lock.yaml 2>/dev/null || true
	@echo -e "\033[0;33m[4/5] Removing generated files...\033[0m"
	@# Skip directories that are mountpoints
	@if ! mountpoint -q storage 2>/dev/null; then \
		find storage -type f ! -name '.gitkeep' -delete 2>/dev/null || true; \
	else \
		echo -e "\033[0;33m  Skipping storage/ (mountpoint)\033[0m"; \
	fi
	@rm -rf build dist public/build docs/api tools 2>/dev/null || true
	@rm -f .env.local 2>/dev/null || true
	@rm -rf .ai 2>/dev/null || true
	@echo -e "\033[0;33m[5/5] Stopping and removing project Docker resources...\033[0m"
	@# Stop and remove all project containers, volumes, images, networks (including orphans)
	@$(DC) --profile php --profile node --profile node-backend --profile redis --profile postgres --profile mariadb --profile mercure --profile meilisearch --profile elasticsearch --profile mailpit --profile seaweedfs --profile rabbitmq down -v --rmi all --remove-orphans 2>/dev/null || true
	@# Project-scoped cleanup of what compose down cannot see: leftover labeled
	@# containers/volumes/networks plus the project-named images (retagged
	@# zappzarapp-*:latest for k8s, bats/goss tool images). Resources of other
	@# projects on this host are deliberately left untouched.
	@$(LOAD_ENV) && \
	PROJECT="$${COMPOSE_PROJECT_NAME:-zappzarapp}" && \
	{ docker ps -aq --filter "label=com.docker.compose.project=$$PROJECT" 2>/dev/null | xargs -r docker rm -f 2>/dev/null || true; } && \
	{ docker volume ls -q --filter "label=com.docker.compose.project=$$PROJECT" 2>/dev/null | xargs -r docker volume rm -f 2>/dev/null || true; } && \
	{ docker network ls -q --filter "label=com.docker.compose.project=$$PROJECT" 2>/dev/null | xargs -r docker network rm 2>/dev/null || true; } && \
	{ { docker images -q --filter "reference=$$PROJECT-*"; docker images -q --filter "reference=zappzarapp-*"; } 2>/dev/null | sort -u | xargs -r docker rmi -f 2>/dev/null || true; }
	@# Clear the project builder's build cache (named buildx builder only — the
	@# host's shared classic builder cache is not this project's to wipe)
	@docker buildx prune --builder $(BUILDX_BUILDER) -af 2>/dev/null || true
	@# PRUNE_SYSTEM=1 escape hatch: restore the old system-wide wipe
	@if [ "$${PRUNE_SYSTEM:-0}" = "1" ]; then \
		echo -e "\033[0;33m  PRUNE_SYSTEM=1: pruning ALL unused Docker resources system-wide...\033[0m"; \
		docker system prune -af --volumes 2>/dev/null || true; \
		docker builder prune -af 2>/dev/null || true; \
	fi

reset: ## Reset Docker and generated files (keeps secrets/certs)
	@echo -e "\033[0;33m╔══════════════════════════════════════════════════════════════════╗\033[0m"
	@echo -e "\033[0;33m║  RESET - Remove Docker resources and generated files             ║\033[0m"
	@echo -e "\033[0;33m╠══════════════════════════════════════════════════════════════════╣\033[0m"
	@echo -e "\033[0;33m║  This will remove:                                               ║\033[0m"
	@echo -e "\033[0;33m║  • This project's Docker containers/images/volumes/networks      ║\033[0m"
	@echo -e "\033[0;33m║  • All Goss test resources                                       ║\033[0m"
	@echo -e "\033[0;33m║  • storage/ contents (uploads, cache) - if not a mountpoint      ║\033[0m"
	@echo -e "\033[0;33m║  • vendor/, node_modules/, .pnpm-store/ (dependencies)           ║\033[0m"
	@echo -e "\033[0;33m║  • composer.lock, pnpm-lock.yaml (lockfiles)                     ║\033[0m"
	@echo -e "\033[0;33m║  • .env.local (local overrides)                                  ║\033[0m"
	@echo -e "\033[0;33m║  • build/, dist/, public/build/, docs/api/, tools/ (generated)   ║\033[0m"
	@echo -e "\033[0;33m║  • .ai/ (project AI knowledge created by setup)                  ║\033[0m"
	@echo -e "\033[0;33m╠══════════════════════════════════════════════════════════════════╣\033[0m"
	@echo -e "\033[0;33m║  KEEPS: secrets/, docker/certs/, source code, other projects     ║\033[0m"
	@echo -e "\033[0;33m║  Use 'make reset-full' to also remove secrets and reset code.    ║\033[0m"
	@echo -e "\033[0;33m╚══════════════════════════════════════════════════════════════════╝\033[0m"
	@echo ""
	@read -p "Type 'RESET' to confirm: " CONFIRM_RESET; \
	if [ "$$CONFIRM_RESET" != "RESET" ]; then \
		echo -e "\033[0;34mOperation cancelled.\033[0m"; \
		exit 1; \
	fi
	@$(MAKE) --silent _reset-core
	@echo ""
	@echo -e "\033[0;32m✓ Factory reset complete!\033[0m"
	@echo -e "\033[0;36mTo start fresh, run: make setup && make up\033[0m"

reset-full: ## Full factory reset - removes EVERYTHING including secrets (DANGEROUS!)
	@echo -e "\033[0;31m╔══════════════════════════════════════════════════════════════════╗\033[0m"
	@echo -e "\033[0;31m║  !!! FULL FACTORY RESET - THIS WILL DELETE EVERYTHING !!!        ║\033[0m"
	@echo -e "\033[0;31m╠══════════════════════════════════════════════════════════════════╣\033[0m"
	@echo -e "\033[0;31m║  This will remove everything from 'make reset' PLUS:             ║\033[0m"
	@echo -e "\033[0;31m║  • secrets/ (all generated secrets)                              ║\033[0m"
	@echo -e "\033[0;31m║  • docker/certs/{ca,nginx,internal}/ (certificate directories)    ║\033[0m"
	@echo -e "\033[0;31m║  • Source code reset via git checkout                            ║\033[0m"
	@echo -e "\033[0;31m║  • README.md, CHANGELOG.md, .claude/CLAUDE.md reset to boilerplate║\033[0m"
	@echo -e "\033[0;31m╚══════════════════════════════════════════════════════════════════╝\033[0m"
	@echo ""
	@read -p "Type 'RESET-FULL' to confirm full factory reset: " CONFIRM_RESET; \
	if [ "$$CONFIRM_RESET" != "RESET-FULL" ]; then \
		echo -e "\033[0;34mOperation cancelled.\033[0m"; \
		exit 1; \
	fi
	@$(MAKE) --silent _reset-core
	@echo ""
	@echo -e "\033[0;33mRemoving secrets...\033[0m"
	@rm -rf secrets/* 2>/dev/null || true
	@echo -e "\033[0;33mRemoving generated certificates...\033[0m"
	@rm -rf docker/certs/ca docker/certs/nginx docker/certs/internal 2>/dev/null || true
	@rm -f docker/certs/*.srl 2>/dev/null || true
	@echo -e "\033[0;36m  Hint: if you ran 'make ssl-trust-ca', the system trust store still\033[0m"
	@echo -e "\033[0;36m  trusts the removed CA — run 'make ssl-untrust-ca' to clean it up.\033[0m"
	@echo -e "\033[0;33mResetting source code to boilerplate defaults...\033[0m"
	@git checkout -- src/ tests/ resources/ config/ templates/ public/index.php 2>/dev/null || \
		echo -e "\033[0;31m  ⚠ git checkout failed - source code not reset\033[0m"
	@echo -e "\033[0;33mResetting README.md, CLAUDE.md, AGENTS.md, and CHANGELOG.md to boilerplate state...\033[0m"
	@git checkout -- README.md 2>/dev/null || \
		echo -e "\033[0;31m  ⚠ README.md not reset (not tracked or modified)\033[0m"
	@git checkout -- .claude/CLAUDE.md 2>/dev/null || \
		echo -e "\033[0;31m  ⚠ CLAUDE.md not reset (not tracked or modified)\033[0m"
	@git checkout -- AGENTS.md 2>/dev/null || \
		echo -e "\033[0;31m  ⚠ AGENTS.md not reset (not tracked or modified)\033[0m"
	@git checkout -- CHANGELOG.md 2>/dev/null || \
		echo -e "\033[0;31m  ⚠ CHANGELOG.md not reset (not tracked or modified)\033[0m"
	@git checkout -- .claude/context/project.md .claude/context/zappzarapp.md 2>/dev/null || \
		echo -e "\033[0;31m  ⚠ Claude context not reset (not tracked or modified)\033[0m"
	@rm -f .zappzarapp/CHANGELOG.md 2>/dev/null || true
	@rm -f .zappzarapp/ai/CLAUDE.md 2>/dev/null || true
	@rm -f .zappzarapp/ai/AGENTS.md 2>/dev/null || true
	@rm -f .zappzarapp/ai/context-project.md 2>/dev/null || true
	@echo -e "\033[0;32m✓ Full factory reset complete! Project is now in boilerplate state.\033[0m"

# =============================================================================
# Boilerplate Sync - Update infrastructure from zappzarapp upstream
# =============================================================================

ZAPPZARAPP_UPSTREAM := https://github.com/marcstraube/zappzarapp.git
ZAPPZARAPP_BRANCH ?= master

# Files/directories to sync from upstream (infrastructure)
BOILERPLATE_SYNC_PATHS := \
	.zappzarapp \
	.claude/context/zappzarapp.md \
	docker \
	.github \
	.gitlab-ci.yml \
	.editorconfig \
	.markdownlint.json \
	.prettierrc \
	.shellcheckrc \
	captainhook.json \
	eslint.config.js \
	phpstan.neon \
	phpunit.xml \
	tsconfig.json \
	vite.config.ts \
	vitest.config.ts

# Files to NEVER sync (project-specific, even if in sync paths)
BOILERPLATE_EXCLUDE := \
	.zappzarapp/CHANGELOG.md \
	.zappzarapp/ai/CLAUDE.md \
	.zappzarapp/ai/AGENTS.md \
	.zappzarapp/ai/context-project.md

# Maintainer-only files: useful only in the zappzarapp boilerplate repo itself
# (e.g. the GitHub->GitLab push-mirror that live-tests the shipped .gitlab-ci.yml
# against a real GitLab instance). They are removed for derived projects by
# `make setup` (boilerplate mode) and stripped again after `boilerplate-sync`,
# so a user's repo never carries a workflow that needs the maintainer's secret.
BOILERPLATE_MAINTAINER_ONLY := \
	.github/workflows/gitlab-mirror.yml

boilerplate-sync: ## Sync infrastructure from zappzarapp upstream (preserves project files)
	@echo -e "\033[0;36m╔════════════════════════════════════════════════════════════╗\033[0m"
	@echo -e "\033[0;36m║           Boilerplate Sync - Infrastructure Update         ║\033[0m"
	@echo -e "\033[0;36m╚════════════════════════════════════════════════════════════╝\033[0m"
	@echo ""
	@# Check if this IS the zappzarapp repo (should not sync to itself)
	@if git remote get-url origin 2>/dev/null | grep -qE 'marcstraube/zappzarapp'; then \
		echo -e "\033[0;31mError: Cannot sync zappzarapp to itself.\033[0m"; \
		echo -e "\033[0;33mThis command is for projects derived from zappzarapp.\033[0m"; \
		exit 1; \
	fi
	@# Ensure zappzarapp remote exists
	@if ! git remote get-url zappzarapp >/dev/null 2>&1; then \
		echo -e "\033[0;33mAdding zappzarapp remote...\033[0m"; \
		git remote add zappzarapp $(ZAPPZARAPP_UPSTREAM); \
		echo -e "\033[0;32m✓ Remote 'zappzarapp' added\033[0m"; \
	else \
		echo -e "\033[0;32m✓ Remote 'zappzarapp' exists\033[0m"; \
	fi
	@echo ""
	@echo -e "\033[0;33mFetching from zappzarapp...\033[0m"
	@git fetch zappzarapp $(ZAPPZARAPP_BRANCH)
	@echo ""
	@echo -e "\033[0;33mFiles to sync:\033[0m"
	@echo -e "\033[0;34m  Infrastructure: .zappzarapp/, .claude/context/zappzarapp.md, docker/, .github/, config files\033[0m"
	@echo -e "\033[0;34m  Excluded: README.md, CHANGELOG.md, .claude/CLAUDE.md, .claude/context/project.md, src/, tests/\033[0m"
	@echo ""
	@# Show what would change
	@echo -e "\033[0;33mChanges from upstream:\033[0m"
	@CHANGES_FOUND=0; \
	for path in $(BOILERPLATE_SYNC_PATHS); do \
		if git ls-tree -r --name-only zappzarapp/$(ZAPPZARAPP_BRANCH) -- "$$path" >/dev/null 2>&1; then \
			DIFF=$$(git diff HEAD...zappzarapp/$(ZAPPZARAPP_BRANCH) --stat -- "$$path" 2>/dev/null | head -20); \
			if [ -n "$$DIFF" ]; then \
				echo "$$DIFF"; \
				CHANGES_FOUND=1; \
			fi; \
		fi; \
	done; \
	if [ "$$CHANGES_FOUND" = "0" ]; then \
		echo -e "\033[0;32m  No changes - already up to date!\033[0m"; \
		exit 0; \
	fi
	@echo ""
	@read -p "Apply these changes? [y/N] " CONFIRM; \
	if [ "$$CONFIRM" != "y" ] && [ "$$CONFIRM" != "Y" ]; then \
		echo -e "\033[0;34mSync cancelled.\033[0m"; \
		exit 0; \
	fi
	@echo ""
	@echo -e "\033[0;33mApplying changes...\033[0m"
	@# Checkout infrastructure files from upstream
	@for path in $(BOILERPLATE_SYNC_PATHS); do \
		if git ls-tree -r --name-only zappzarapp/$(ZAPPZARAPP_BRANCH) -- "$$path" >/dev/null 2>&1; then \
			git checkout zappzarapp/$(ZAPPZARAPP_BRANCH) -- "$$path" 2>/dev/null || true; \
		fi; \
	done
	@# Restore excluded files (project-specific that may have been in synced dirs)
	@for excluded in $(BOILERPLATE_EXCLUDE); do \
		git checkout HEAD -- "$$excluded" 2>/dev/null || true; \
	done
	@# Strip maintainer-only files pulled in via the .github/ sync (this target
	@# already refuses to run on the zappzarapp repo itself, so removal is safe)
	@for maint in $(BOILERPLATE_MAINTAINER_ONLY); do \
		if [ -f "$$maint" ]; then \
			rm -f "$$maint"; \
			echo -e "\033[0;34m  Stripped maintainer-only file: $$maint\033[0m"; \
		fi; \
	done
	@# Show files that need manual review (not auto-synced)
	@echo ""
	@echo -e "\033[0;33m┌─────────────────────────────────────────────────────────────┐\033[0m"
	@echo -e "\033[0;33m│  Files requiring manual review (not auto-synced):           │\033[0m"
	@echo -e "\033[0;33m└─────────────────────────────────────────────────────────────┘\033[0m"
	@echo ""
	@echo -e "\033[0;36mMakefile\033[0m — may have project customizations"
	@git diff HEAD...zappzarapp/$(ZAPPZARAPP_BRANCH) --stat -- Makefile 2>/dev/null | head -5 || echo "  (no changes)"
	@echo ""
	@echo -e "\033[0;36m.gitignore\033[0m — may have project-specific ignores"
	@git diff HEAD...zappzarapp/$(ZAPPZARAPP_BRANCH) --stat -- .gitignore 2>/dev/null | head -5 || echo "  (no changes)"
	@echo ""
	@echo -e "\033[0;36mcomposer.json\033[0m — may have project dependencies"
	@git diff HEAD...zappzarapp/$(ZAPPZARAPP_BRANCH) --stat -- composer.json 2>/dev/null | head -5 || echo "  (no changes)"
	@echo ""
	@echo -e "\033[0;36mpackage.json\033[0m — may have project dependencies"
	@git diff HEAD...zappzarapp/$(ZAPPZARAPP_BRANCH) --stat -- package.json 2>/dev/null | head -5 || echo "  (no changes)"
	@echo ""
	@echo -e "\033[0;34mTo view full diff:  git diff HEAD...zappzarapp/$(ZAPPZARAPP_BRANCH) -- <file>\033[0m"
	@echo -e "\033[0;34mTo apply changes:   git checkout zappzarapp/$(ZAPPZARAPP_BRANCH) -- <file>\033[0m"
	@echo ""
	@echo -e "\033[0;32m✓ Infrastructure synced from zappzarapp!\033[0m"
	@echo -e "\033[0;33mReview changes with: git status && git diff --cached\033[0m"
	@echo -e "\033[0;33mCommit with: git commit -m \"chore: sync infrastructure from zappzarapp\"\033[0m"

boilerplate-diff: ## Show diff between local and zappzarapp upstream (dry-run)
	@# Ensure remote exists
	@if ! git remote get-url zappzarapp >/dev/null 2>&1; then \
		echo -e "\033[0;33mAdding zappzarapp remote...\033[0m"; \
		git remote add zappzarapp $(ZAPPZARAPP_UPSTREAM); \
	fi
	@git fetch zappzarapp $(ZAPPZARAPP_BRANCH) 2>/dev/null
	@echo -e "\033[0;36mDifferences from zappzarapp/$(ZAPPZARAPP_BRANCH):\033[0m"
	@echo ""
	@for path in $(BOILERPLATE_SYNC_PATHS); do \
		if git ls-tree -r --name-only zappzarapp/$(ZAPPZARAPP_BRANCH) -- "$$path" >/dev/null 2>&1; then \
			DIFF=$$(git diff HEAD...zappzarapp/$(ZAPPZARAPP_BRANCH) --stat -- "$$path" 2>/dev/null); \
			if [ -n "$$DIFF" ]; then \
				echo -e "\033[0;33m$$path:\033[0m"; \
				echo "$$DIFF"; \
				echo ""; \
			fi; \
		fi; \
	done
	@echo -e "\033[0;34mFor full diff: git diff HEAD...zappzarapp/$(ZAPPZARAPP_BRANCH) -- <path>\033[0m"

customize: ## List shipped templates that still contain customization placeholders
	@marker="zappzarapp:customize"; \
	if git rev-parse --git-dir >/dev/null 2>&1; then \
		files=$$(git grep -lI "$$marker" -- '*.md' ':(exclude).zappzarapp/' 2>/dev/null || true); \
	else \
		files=$$(grep -rlI --include='*.md' --exclude-dir=.zappzarapp --exclude-dir=node_modules --exclude-dir=vendor --exclude-dir=build "$$marker" . 2>/dev/null || true); \
	fi; \
	if [ -z "$$files" ]; then \
		echo -e "\033[0;32m✓ All shipped templates are customized (no placeholders left).\033[0m"; \
	else \
		echo -e "\033[0;33mTemplates still needing customization:\033[0m"; \
		for f in $$files; do \
			n=$$(grep -c "$$marker" "$$f" 2>/dev/null || echo 0); \
			echo -e "  \033[0;36m$$f\033[0m — $$n placeholder(s)"; \
		done; \
		echo ""; \
		echo -e "Replace each '$$marker' marker with your content, then re-run \033[0;36mmake customize\033[0m."; \
	fi

ai-setup: ## Initialize labels and milestones for /tasks command (auto-detects GitHub/GitLab)
	@REMOTE_URL=$$(git remote get-url origin 2>/dev/null); \
	if echo "$$REMOTE_URL" | grep -qE 'github\.com'; then \
		PLATFORM="github"; \
	elif echo "$$REMOTE_URL" | grep -qE 'gitlab\.com|gitlab\.'; then \
		PLATFORM="gitlab"; \
	else \
		echo -e "\033[0;31mError: Could not detect platform (GitHub/GitLab) from remote URL\033[0m"; \
		exit 1; \
	fi; \
	echo -e "\033[0;33mDetected platform: $$PLATFORM\033[0m"; \
	echo ""; \
	if [ "$$PLATFORM" = "github" ]; then \
		if ! command -v gh >/dev/null 2>&1; then \
			echo -e "\033[0;31mError: GitHub CLI (gh) not installed. See https://cli.github.com/\033[0m"; \
			exit 1; \
		fi; \
		if ! gh auth status >/dev/null 2>&1; then \
			echo -e "\033[0;31mError: Not authenticated. Run 'gh auth login' first.\033[0m"; \
			exit 1; \
		fi; \
		echo -e "\033[0;33mInitializing GitHub labels...\033[0m"; \
		./.zappzarapp/scripts/init-github-labels.sh; \
		echo ""; \
		echo -e "\033[0;33mCreating 'Backlog' milestone (if not exists)...\033[0m"; \
		if gh api repos/{owner}/{repo}/milestones --jq '.[] | select(.title=="Backlog")' 2>/dev/null | grep -q .; then \
			echo -e "\033[0;36m  'Backlog' milestone already exists\033[0m"; \
		else \
			gh api repos/{owner}/{repo}/milestones --method POST \
				-f title="Backlog" \
				-f description="Consciously deferred tasks, not scheduled for upcoming releases" \
				>/dev/null 2>&1 && \
			echo -e "\033[0;32m  Created 'Backlog' milestone\033[0m"; \
		fi; \
	elif [ "$$PLATFORM" = "gitlab" ]; then \
		if ! command -v glab >/dev/null 2>&1; then \
			echo -e "\033[0;31mError: GitLab CLI (glab) not installed. See https://gitlab.com/gitlab-org/cli\033[0m"; \
			exit 1; \
		fi; \
		if ! glab auth status >/dev/null 2>&1; then \
			echo -e "\033[0;31mError: Not authenticated. Run 'glab auth login' first.\033[0m"; \
			exit 1; \
		fi; \
		echo -e "\033[0;33mInitializing GitLab labels...\033[0m"; \
		./.zappzarapp/scripts/init-gitlab-labels.sh; \
		echo ""; \
		echo -e "\033[0;33mCreating 'Backlog' milestone (if not exists)...\033[0m"; \
		if glab api projects/:id/milestones 2>/dev/null | jq -e '.[] | select(.title=="Backlog")' >/dev/null 2>&1; then \
			echo -e "\033[0;36m  'Backlog' milestone already exists\033[0m"; \
		else \
			glab api projects/:id/milestones --method POST \
				-f title="Backlog" \
				-f description="Consciously deferred tasks, not scheduled for upcoming releases" \
				>/dev/null 2>&1 && \
			echo -e "\033[0;32m  Created 'Backlog' milestone\033[0m"; \
		fi; \
		echo ""; \
		echo -e "\033[0;36mNote: GitLab Issue Boards are label-based. Create a board and add lists for status labels.\033[0m"; \
	fi; \
	echo ""; \
	echo -e "\033[0;32m✓ AI setup complete! You can now use /tasks\033[0m"

rebuild: ## Complete rebuild
	@$(MAKE) clean
	@$(MAKE) build
	@$(MAKE) up SKIP_VALIDATION=1

##@ Quality Assurance

analyse: analyse-php analyse-node  ## Run static analysis (PHP + Node)

analyse-php: ## Run PHPStan static analysis (ARGS="path/to/dir" for specific paths)
	@echo -e "\033[0;33mRunning PHPStan...\033[0m"
	@docker compose exec php composer analyse -- $(ARGS)

phpmd: ## Run PHPMD (PHP Mess Detector) for code quality analysis
	@echo -e "\033[0;33mRunning PHPMD (Mess Detector)...\033[0m"
	@docker compose exec php php -d error_reporting=24575 vendor/bin/phpmd src/php,tests/php text phpmd.xml.dist --exclude '*DatabaseConfig*'

rector-check: ## Run Rector for automated refactoring analysis (ARGS="path/to/dir" for specific paths)
	@echo -e "\033[0;33mRunning Rector analysis (dry-run)...\033[0m"
	@docker compose exec php composer rector-check -- $(ARGS)

rector-fix: ## Apply Rector refactorings automatically (ARGS="path/to/dir" for specific paths)
	@echo -e "\033[0;33mApplying Rector refactorings...\033[0m"
	@docker compose exec php composer rector-fix -- $(ARGS)

# Shared static-check set (no coverage, no audit) — the fast common core of
# `check` and `ci`. Not exposed in `make help` (no `## ` comment) so it needs an
# explicit .PHONY; the auto-.PHONY at the top only covers documented targets.
.PHONY: _check-static
_check-static: cs-check analyse-php phpmd rector-check prettier-check analyse-node lint-node deps-validate compose-validate validate-env lint-md lint-sql lint-docker lint-shell

check: _check-static test ## Run static checks + tests without coverage (fast pre-check)
	@echo -e "\033[0;32mAll checks passed!\033[0m"

ci: _check-static test-coverage-php coverage-check-php test-coverage-node audit ## Faithful CI gate simulation (adds PHP coverage strictness + dependency audit)
	@echo -e "\033[0;32mCI simulation passed!\033[0m"

audit: ## Audit dependencies for known vulnerabilities (composer audit + pnpm audit, mirrors CI)
	@echo -e "\033[0;33mAuditing Composer dependencies...\033[0m"
	@docker compose exec php composer audit
	@echo -e "\033[0;33mAuditing pnpm dependencies...\033[0m"
	@docker compose exec node pnpm audit
	@echo -e "\033[0;32mDependency audit passed!\033[0m"

cs-check: ## Check coding style (ARGS="path/to/file.php" for specific files)
	@echo -e "\033[0;33mChecking Coding Style...\033[0m"
	@docker compose exec php composer cs-check -- $(ARGS)

cs-fix: ## Fix coding style automatically (ARGS="path/to/file.php" for specific files)
	@echo -e "\033[0;33mFixing Coding Style...\033[0m"
	@docker compose exec php composer cs-fix -- $(ARGS)

cs-fix-all: ## Fix coding style aggressively on all files (uses config from .php-cs-fixer.dist.php)
	@echo -e "\033[0;33mFixing Coding Style aggressively on all files...\033[0m"
	@docker compose exec php vendor/bin/php-cs-fixer fix --allow-risky=yes

lint-config: ## Validate YAML configuration files
	@echo -e "\033[0;33mValidating YAML configuration...\033[0m"
	@# Lint every YAML file in the repo. yamllint auto-discovers .yamllint
	@# (rules + ignores). Image major is pinned to avoid `:latest` drift.
	@docker run --rm -v $$(pwd):/app -w /app cytopia/yamllint:1 .
	@echo -e "\033[0;32mYAML configuration check completed!\033[0m"

lint-docker: ## Lint Dockerfiles with hadolint
	@echo -e "\033[0;33mLinting Dockerfiles with hadolint...\033[0m"
	@docker run --rm -v $$(pwd)/.hadolint.yaml:/.config/hadolint.yaml -i hadolint/hadolint:v2.15.0 < docker/php/Dockerfile
	@docker run --rm -v $$(pwd)/.hadolint.yaml:/.config/hadolint.yaml -i hadolint/hadolint:v2.15.0 < docker/node/Dockerfile
	@docker run --rm -v $$(pwd)/.hadolint.yaml:/.config/hadolint.yaml -i hadolint/hadolint:v2.15.0 < docker/nginx/Dockerfile
	@docker run --rm -v $$(pwd)/.hadolint.yaml:/.config/hadolint.yaml -i hadolint/hadolint:v2.15.0 < docker/postgres/Dockerfile
	@docker run --rm -v $$(pwd)/.hadolint.yaml:/.config/hadolint.yaml -i hadolint/hadolint:v2.15.0 < docker/mariadb/Dockerfile
	@docker run --rm -v $$(pwd)/.hadolint.yaml:/.config/hadolint.yaml -i hadolint/hadolint:v2.15.0 < docker/redis/Dockerfile
	@docker run --rm -v $$(pwd)/.hadolint.yaml:/.config/hadolint.yaml -i hadolint/hadolint:v2.15.0 < docker/mercure/Dockerfile
	@docker run --rm -v $$(pwd)/.hadolint.yaml:/.config/hadolint.yaml -i hadolint/hadolint:v2.15.0 < docker/meilisearch/Dockerfile
	@docker run --rm -v $$(pwd)/.hadolint.yaml:/.config/hadolint.yaml -i hadolint/hadolint:v2.15.0 < docker/elasticsearch/Dockerfile
	@docker run --rm -v $$(pwd)/.hadolint.yaml:/.config/hadolint.yaml -i hadolint/hadolint:v2.15.0 < docker/mailpit/Dockerfile
	@docker run --rm -v $$(pwd)/.hadolint.yaml:/.config/hadolint.yaml -i hadolint/hadolint:v2.15.0 < docker/seaweedfs/Dockerfile
	@docker run --rm -v $$(pwd)/.hadolint.yaml:/.config/hadolint.yaml -i hadolint/hadolint:v2.15.0 < docker/rabbitmq/Dockerfile
	@echo -e "\033[0;32mDockerfile linting completed!\033[0m"

## Lints the repo's git-tracked Markdown (markdownlint + Prettier) in a pinned
## node image, matching the lint-docker/lint-config pattern. Scope is every
## tracked .md, including .claude/ tooling docs. The pinned tools install into a
## cached parent dir (build/tmp/doclint) so Prettier resolves prettier-plugin-toml
## (declared in .prettierrc.json) via ESM lookup from the repo root. --user keeps
## written files owned by the caller.
DOCLINT_SETUP = cd /work && npm init -y >/dev/null 2>&1 && npm install --no-audit --no-fund --loglevel=error markdownlint-cli2@0.23.1 prettier@3.9.6 prettier-plugin-toml@2.0.6 >/dev/null && cd /work/repo

lint-md: ## Check Markdown across the whole repo (markdownlint + Prettier)
	@echo -e "\033[0;33mChecking Markdown (markdownlint + Prettier)...\033[0m"
	@mkdir -p build/tmp/doclint
	@docker run --rm --user $$(id -u):$$(id -g) -e HOME=/work \
		-e MDFILES="$$(git ls-files -- '*.md' | tr '\n' ' ')" \
		-v $$(pwd)/build/tmp/doclint:/work -v $$(pwd):/work/repo:ro -w /work/repo node:24-alpine \
		sh -c '$(DOCLINT_SETUP) && ../node_modules/.bin/markdownlint-cli2 $$MDFILES && ../node_modules/.bin/prettier --check $$MDFILES'
	@echo -e "\033[0;32mMarkdown check completed!\033[0m"

lint-md-fix: ## Fix Markdown across the whole repo (markdownlint + Prettier)
	@echo -e "\033[0;33mFixing Markdown (markdownlint + Prettier)...\033[0m"
	@mkdir -p build/tmp/doclint
	@docker run --rm --user $$(id -u):$$(id -g) -e HOME=/work \
		-e MDFILES="$$(git ls-files -- '*.md' | tr '\n' ' ')" \
		-v $$(pwd)/build/tmp/doclint:/work -v $$(pwd):/work/repo -w /work/repo node:24-alpine \
		sh -c '$(DOCLINT_SETUP) && ../node_modules/.bin/markdownlint-cli2 --fix $$MDFILES ; ../node_modules/.bin/prettier --write $$MDFILES'
	@echo -e "\033[0;32mMarkdown files fixed!\033[0m"

lint-sql: ## Check SQL files for style issues (PostgreSQL + MariaDB)
	@echo -e "\033[0;33mChecking SQL files...\033[0m"
	@if [ -d "migrations/postgresql" ] && [ -n "$$(ls -A migrations/postgresql/*.sql 2>/dev/null)" ]; then \
		echo -e "\033[0;90m  Checking PostgreSQL migrations...\033[0m"; \
		docker run --rm -u $$(id -u):$$(id -g) -v "$$(pwd):/sql" $(SQLFLUFF_IMAGE) lint \
			--dialect postgres \
			--config /sql/.sqlfluff \
			/sql/migrations/postgresql/ || exit 1; \
	fi
	@if [ -d "migrations/mariadb" ] && [ -n "$$(ls -A migrations/mariadb/*.sql 2>/dev/null)" ]; then \
		echo -e "\033[0;90m  Checking MariaDB migrations...\033[0m"; \
		docker run --rm -u $$(id -u):$$(id -g) -v "$$(pwd):/sql" $(SQLFLUFF_IMAGE) lint \
			--dialect mysql \
			--config /sql/.sqlfluff \
			--ignore parsing,lexing \
			/sql/migrations/mariadb/ || exit 1; \
	fi
	@echo -e "\033[0;32mSQL check completed!\033[0m"

lint-sql-fix: ## Fix SQL style issues automatically
	@echo -e "\033[0;33mFixing SQL files...\033[0m"
	@if [ -d "migrations/postgresql" ] && [ -n "$$(ls -A migrations/postgresql/*.sql 2>/dev/null)" ]; then \
		echo -e "\033[0;90m  Fixing PostgreSQL migrations...\033[0m"; \
		docker run --rm -u $$(id -u):$$(id -g) -v "$$(pwd):/sql" $(SQLFLUFF_IMAGE) fix \
			--dialect postgres \
			--config /sql/.sqlfluff \
			--force \
			/sql/migrations/postgresql/; \
	fi
	@if [ -d "migrations/mariadb" ] && [ -n "$$(ls -A migrations/mariadb/*.sql 2>/dev/null)" ]; then \
		echo -e "\033[0;90m  Fixing MariaDB migrations...\033[0m"; \
		docker run --rm -u $$(id -u):$$(id -g) -v "$$(pwd):/sql" $(SQLFLUFF_IMAGE) fix \
			--dialect mysql \
			--config /sql/.sqlfluff \
			--ignore parsing,lexing \
			--force \
			/sql/migrations/mariadb/; \
	fi
	@echo -e "\033[0;32mSQL files fixed!\033[0m"

lint-node: ## Run ESLint on TypeScript/JavaScript files (ARGS="path/to/file.ts" for specific files)
	@echo -e "\033[0;33mRunning ESLint...\033[0m"
	@$(DC) run --rm -T dev-tools pnpm run lint -- $(ARGS)
	@echo -e "\033[0;32mESLint check completed!\033[0m"

lint-node-fix: ## Fix ESLint issues automatically (ARGS="path/to/file.ts" for specific files)
	@echo -e "\033[0;33mFixing ESLint issues...\033[0m"
	@$(DC) run --rm -T dev-tools pnpm run lint:fix -- $(ARGS)
	@echo -e "\033[0;32mESLint issues fixed!\033[0m"

lint-shell: ## Lint shell scripts with ShellCheck
	@echo -e "\033[0;33mLinting shell scripts (ShellCheck)...\033[0m"
	@SHELL_FILES=$$(find docker -name "*.sh" -type f 2>/dev/null; find tests/bats -name "*.bash" -type f 2>/dev/null); \
	if [ -n "$$SHELL_FILES" ]; then \
		docker run --rm -v "$(PWD):/mnt:ro" -w /mnt koalaman/shellcheck:stable \
			--severity=warning --color=always $$SHELL_FILES \
		|| (echo -e "\033[0;31mShellCheck found issues!\033[0m" && exit 1); \
	else \
		echo -e "\033[0;33mNo shell scripts found to lint.\033[0m"; \
	fi
	@echo -e "\033[0;32mShellCheck completed!\033[0m"

analyse-node: ## Run TypeScript type checking (ARGS="path/to/file.ts" for specific files)
	@echo -e "\033[0;33mRunning TypeScript type check...\033[0m"
	@$(DC) run --rm -T dev-tools pnpm run type-check $(if $(ARGS),-- $(ARGS))
	@echo -e "\033[0;32mTypeScript check completed!\033[0m"

prettier-check: ## Check code formatting with Prettier (ARGS="path/to/file.ts" for specific files)
	@echo -e "\033[0;33mChecking code formatting (Prettier)...\033[0m"
	@$(DC) run --rm -T dev-tools $(if $(ARGS),pnpm exec prettier --check $(ARGS),pnpm run format:check)
	@echo -e "\033[0;32mPrettier check completed!\033[0m"

prettier-fix: ## Fix code formatting with Prettier (ARGS="path/to/file.ts" for specific files)
	@echo -e "\033[0;33mFixing code formatting (Prettier)...\033[0m"
	@$(DC) run --rm -T dev-tools $(if $(ARGS),pnpm exec prettier --write $(ARGS),pnpm run format)
	@echo -e "\033[0;32mPrettier formatting applied!\033[0m"

outdated: outdated-php outdated-node ## Check for outdated PHP + Node.js dependencies

outdated-php: ## Check for outdated Composer packages
	@echo -e "\033[0;33mChecking Composer for outdated packages...\033[0m"
	@docker compose exec php composer outdated

outdated-node: ## Check for outdated pnpm packages (workspace-wide, informational)
	@echo -e "\033[0;33mChecking pnpm for outdated packages...\033[0m"
	# `pnpm outdated` exits non-zero when anything is outdated; this is an
	# informational report (not a gate), so keep it from aborting the run.
	@docker compose exec node pnpm -r outdated || true

# Pinned Renovate image for the local dry-run (bump manually; Renovate itself is
# not yet active on this repo — activation is planned for the v1.0 fresh repo).
RENOVATE_VERSION ?= 44.5

renovate: ## Dry-run Renovate locally (local platform, no PRs) — validates renovate.json + lists pending updates
	@echo -e "\033[0;33mRunning Renovate dry-run (local platform, no PRs, LOG_LEVEL=$${LOG_LEVEL:-info})...\033[0m"
	@# A GitHub token lets Renovate read GitHub-datasource release metadata
	@# (timestamps) and avoids API rate limits, so far fewer updates are held as
	@# "pending" by minimumReleaseAge. Read it from the env or .env.local; runs
	@# fine without one (just less complete locally). NEVER put it in tracked .env.
	@GH_TOKEN="$${GITHUB_COM_TOKEN:-}"; \
	if [ -z "$$GH_TOKEN" ] && [ -f .env.local ]; then \
		GH_TOKEN="$$(. ./.env.local >/dev/null 2>&1; printf '%s' "$${GITHUB_COM_TOKEN:-}")"; \
	fi; \
	if [ -n "$$GH_TOKEN" ]; then \
		echo -e "\033[0;34m  Using GITHUB_COM_TOKEN for release-metadata lookups (fewer 'pending' updates)\033[0m"; \
	else \
		echo -e "\033[0;33m  No GITHUB_COM_TOKEN set — GitHub-datasource timestamps may be missing; set it in .env.local for better local fidelity\033[0m"; \
	fi; \
	docker run --rm \
		-e RENOVATE_PLATFORM=local \
		-e RENOVATE_DRY_RUN=full \
		-e LOG_LEVEL="$${LOG_LEVEL:-info}" \
		$${GH_TOKEN:+-e GITHUB_COM_TOKEN="$$GH_TOKEN"} \
		-v "$$(pwd):/usr/src/app" \
		-w /usr/src/app \
		renovate/renovate:$(RENOVATE_VERSION)
	@echo -e "\033[0;32mRenovate dry-run completed (no changes made)!\033[0m"

depcheck: ## Find unused Node.js dependencies
	@echo -e "\033[0;33mChecking for unused dependencies...\033[0m"
	@$(DC) run --rm -T dev-tools pnpm exec depcheck
	@echo -e "\033[0;32mDepcheck completed!\033[0m"

knip: ## Find dead code, unused exports and dependencies
	@echo -e "\033[0;33mRunning knip dead code detection...\033[0m"
	@$(DC) run --rm -T dev-tools pnpm exec knip
	@echo -e "\033[0;32mKnip completed!\033[0m"

dive: ## Analyze Docker image layers and sizes
	@echo -e "\033[0;33mAnalyzing Docker image layers...\033[0m"
	@echo -e "\033[0;34mSelect image to analyze:\033[0m"
	@echo "  1) php"
	@echo "  2) node"
	@echo "  3) nginx"
	@read -p "Enter choice [1-3]: " choice; \
	PROJECT="$${COMPOSE_PROJECT_NAME:-zappzarapp}"; \
	case $$choice in \
		1) IMAGE="$$PROJECT-php" ;; \
		2) IMAGE="$$PROJECT-node" ;; \
		3) IMAGE="$$PROJECT-nginx" ;; \
		*) echo "Invalid choice"; exit 1 ;; \
	esac; \
	docker run --rm -it -v /var/run/docker.sock:/var/run/docker.sock wagoodman/dive:latest $$IMAGE

test: ## Run all tests (PHP + Node.js) - continues even if some fail
	@failed=0; \
	$(MAKE) test-php || failed=1; \
	$(MAKE) test-node || failed=1; \
	echo ""; \
	echo "════════════════════════════════════════════════════════════"; \
	echo "                      TEST SUMMARY                          "; \
	echo "════════════════════════════════════════════════════════════"; \
	if [ $$failed -eq 1 ]; then \
		echo -e "\033[0;31m  Result: FAILED - Some tests did not pass\033[0m"; \
		echo "════════════════════════════════════════════════════════════"; \
		exit 1; \
	fi; \
	echo -e "\033[0;32m  Result: PASSED - All tests successful\033[0m"; \
	echo "════════════════════════════════════════════════════════════"

test-coverage: ## Generate coverage reports for PHP and Node.js - continues even if some fail
	@failed=0; \
	$(MAKE) test-coverage-php || failed=1; \
	$(MAKE) test-coverage-node || failed=1; \
	echo ""; \
	echo "════════════════════════════════════════════════════════════"; \
	echo "                   COVERAGE SUMMARY                         "; \
	echo "════════════════════════════════════════════════════════════"; \
	echo -e "\033[0;34m  PHP Coverage:     build/coverage/php/index.html\033[0m"; \
	echo -e "\033[0;34m  Node.js Coverage: build/coverage/node/index.html\033[0m"; \
	if [ $$failed -eq 1 ]; then \
		echo -e "\033[0;31m  Result: FAILED - Some tests did not pass\033[0m"; \
		echo "════════════════════════════════════════════════════════════"; \
		exit 1; \
	fi; \
	echo -e "\033[0;32m  Result: PASSED - All coverage reports generated\033[0m"; \
	echo "════════════════════════════════════════════════════════════"

test-php: ## Run PHPUnit tests (ARGS="--filter testName" for specific tests)
	@echo -e "\033[0;33mRunning PHPUnit tests...\033[0m"
	@if docker compose ps -q php 2>/dev/null | grep -q .; then \
		docker compose exec php composer test -- $(ARGS); \
	else \
		docker compose run --rm php composer test -- $(ARGS); \
	fi

test-php-debug: ## Run PHPUnit tests with Xdebug enabled
	@echo -e "\033[0;33mRunning PHPUnit with Xdebug (Step Debugging)...\033[0m"
	@if docker compose ps -q php 2>/dev/null | grep -q .; then \
		docker compose exec php sh -c 'XDEBUG_MODE=develop,debug composer test'; \
	else \
		docker compose run --rm php sh -c 'XDEBUG_MODE=develop,debug composer test'; \
	fi

test-coverage-php: ## Generate PHPUnit coverage report (ARGS="--filter testName" for specific tests)
	@echo -e "\033[0;33mRunning PHPUnit with coverage report...\033[0m"
	@if docker compose ps -q php 2>/dev/null | grep -q .; then \
		docker compose exec php sh -c 'XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html build/coverage/php --coverage-clover build/coverage/php/clover.xml $(ARGS)'; \
	else \
		docker compose run --rm php sh -c 'XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html build/coverage/php --coverage-clover build/coverage/php/clover.xml $(ARGS)'; \
	fi
	@echo -e "\033[0;32mPHP coverage report generated in build/coverage/php/index.html!\033[0m"

coverage-check-php: ## Verify PHP coverage meets PHP_COVERAGE_MIN (set in .env; ratchet - raise, never lower)
	@if [ ! -f build/coverage/php/clover.xml ]; then \
		echo -e "\033[0;31mNo clover.xml found - run 'make test-coverage-php' first\033[0m"; \
		exit 1; \
	fi
	@$(LOAD_ENV); \
	MIN="$${PHP_COVERAGE_MIN:-70}"; \
	echo -e "\033[0;33mChecking PHP coverage threshold (>= $$MIN%)...\033[0m"; \
	grep -o '<metrics[^>]*>' build/coverage/php/clover.xml | tail -1 | \
	awk -v min="$$MIN" ' \
		match($$0, /coveredstatements="[0-9]+"/) { covered = substr($$0, RSTART + 19, RLENGTH - 20) } \
		match($$0, / statements="[0-9]+"/) { total = substr($$0, RSTART + 13, RLENGTH - 14) } \
		END { \
			if (total == 0) { print "No statements found in clover.xml"; exit 1 } \
			pct = covered / total * 100; \
			printf "Overall PHP coverage: %.2f%% (%d/%d statements)\n", pct, covered, total; \
			if (pct + 0.005 < min) { printf "FAIL: below minimum of %d%%\n", min; exit 1 } \
			printf "OK: meets minimum of %d%%\n", min; \
		}'
	@echo -e "\033[0;32mPHP coverage threshold check passed!\033[0m"

test-node: ## Run Vitest tests (ARGS="path/to/test.ts" for specific tests)
	@echo -e "\033[0;33mRunning Vitest tests...\033[0m"
	@$(DC) run --rm -T dev-tools pnpm test -- $(ARGS)

test-node-watch: ## Run Vitest in watch mode (interactive)
	@echo -e "\033[0;33mRunning Vitest in watch mode...\033[0m"
	@$(DC) run --rm dev-tools pnpm test:watch

test-coverage-node: ## Generate Vitest coverage report (ARGS="path/to/test.ts" for specific tests)
	@echo -e "\033[0;33mRunning Vitest with coverage report...\033[0m"
	@$(DC) run --rm -T dev-tools pnpm test:coverage -- $(ARGS)
	@echo -e "\033[0;32mNode.js coverage report generated in build/coverage/node/index.html!\033[0m"

##@ GOSS Container Tests
#
# Two-phase testing strategy:
# 1. Build-time: GOSS tests run during docker build (--target test)
# 2. Runtime: Integration tests verify running containers
#
# Build-time tests catch: Missing files, broken configs, wrong versions
# Runtime tests catch: Network issues, TLS, service communication

goss-test: ## Run runtime integration tests for all running containers
	@echo -e "\033[0;33mRunning runtime integration tests...\033[0m"
	@tests/goss/runtime-tests.sh all

goss-build: ## Build GOSS testing tool image (required for build-time tests)
	@echo -e "\033[0;34mBuilding GOSS image...\033[0m"
	@docker build -t zappzarapp-goss:latest -f docker/goss/Dockerfile . >/dev/null

.PHONY: .buildx-ensure
.buildx-ensure:
	@docker buildx inspect $(BUILDX_BUILDER) >/dev/null 2>&1 || \
		docker buildx create --name $(BUILDX_BUILDER) --driver docker-container >/dev/null

goss-test-build: .buildx-ensure ## Run GOSS build-time tests for all images (docker buildx bake)
	@echo -e "\033[0;33mRunning GOSS build-time tests (docker buildx bake)...\033[0m"
	@# The `test` group builds goss + node-backend as linked contexts and runs
	@# `RUN goss validate` inside every test stage — a green build IS the passing
	@# suite. Any failing stage fails the whole bake (real gating).
	@$(BAKE) test && \
		echo -e "\033[0;32m✓ All build-time tests passed!\033[0m" || \
		{ echo -e "\033[0;31m✗ Build-time tests failed\033[0m"; exit 1; }

goss-test-all: goss-test-build goss-test ## Run both build-time and runtime tests
	@echo -e "\033[0;32m✓ All GOSS tests (build + runtime) completed!\033[0m"

# Individual service runtime tests
goss-test-nginx: ## Test nginx container (runtime)
	@tests/goss/runtime-tests.sh nginx

goss-test-php: ## Test PHP container (runtime)
	@tests/goss/runtime-tests.sh php

goss-test-node-backend: ## Test node-backend container (runtime)
	@tests/goss/runtime-tests.sh node-backend

goss-test-node-frontend: ## Test node-frontend container (runtime)
	@tests/goss/runtime-tests.sh node-frontend

goss-test-postgres: ## Test PostgreSQL container (runtime)
	@tests/goss/runtime-tests.sh postgres

goss-test-mariadb: ## Test MariaDB container (runtime)
	@tests/goss/runtime-tests.sh mariadb

goss-test-redis: ## Test Redis container (runtime)
	@tests/goss/runtime-tests.sh redis

goss-test-mercure: ## Test Mercure container (runtime)
	@tests/goss/runtime-tests.sh mercure

goss-test-meilisearch: ## Test Meilisearch container (runtime)
	@tests/goss/runtime-tests.sh meilisearch

goss-test-elasticsearch: ## Test Elasticsearch container (runtime)
	@tests/goss/runtime-tests.sh elasticsearch

goss-test-mailpit: ## Test Mailpit container (runtime)
	@tests/goss/runtime-tests.sh mailpit

goss-test-seaweedfs: ## Test SeaweedFS container (runtime)
	@tests/goss/runtime-tests.sh seaweedfs

goss-test-rabbitmq: ## Test RabbitMQ container (runtime)
	@tests/goss/runtime-tests.sh rabbitmq

# Preset test targets (build + start + runtime test + stop)
goss-test-preset: ## Test a preset (PRESET=dev-fullstack, VERBOSE=1 for details)
	@if [ -z "$(PRESET)" ]; then \
		echo -e "\033[0;31mError: PRESET not specified. Usage: make goss-test-preset PRESET=fullstack\033[0m"; \
		exit 1; \
	fi
	@if [ ! -f "tests/goss/presets/$(PRESET).env" ]; then \
		echo -e "\033[0;31mError: tests/goss/presets/$(PRESET).env not found\033[0m"; \
		echo -e "\033[0;34mAvailable presets:\033[0m"; \
		ls -1 tests/goss/presets/*.env 2>/dev/null | xargs -n1 basename | sed 's/.env//'; \
		exit 1; \
	fi
	@echo -e "\033[0;33mTesting preset: $(PRESET)\033[0m"
	@VERBOSE=$(VERBOSE) tests/goss/preset-runner.sh $(PRESET) "up -d --wait --build --force-recreate"
	@tests/goss/runtime-tests.sh all --env-file tests/goss/presets/$(PRESET).env || (VERBOSE=$(VERBOSE) tests/goss/preset-runner.sh $(PRESET) "down -v" && exit 1)
	@VERBOSE=$(VERBOSE) tests/goss/preset-runner.sh $(PRESET) "down -v"

# Development Presets (use host-mounted volumes, require dependencies)
goss-test-dev-fullstack: ## [DEV] Full-Stack (PHP + Node + Postgres + Redis)
	@$(MAKE) --no-print-directory goss-test-preset PRESET=dev-fullstack

goss-test-dev-php-only: ## [DEV] PHP-Only (PHP + Postgres + Redis)
	@$(MAKE) --no-print-directory goss-test-preset PRESET=dev-php-only

goss-test-dev-node-only: ## [DEV] Node-Only (Node + Postgres + Redis)
	@$(MAKE) --no-print-directory goss-test-preset PRESET=dev-node-only

goss-test-dev-minimal: ## [DEV] Minimal (Nginx only)
	@$(MAKE) --no-print-directory goss-test-preset PRESET=dev-minimal

goss-test-dev-fullstack-mariadb: ## [DEV] Full-Stack with MariaDB
	@$(MAKE) --no-print-directory goss-test-preset PRESET=dev-fullstack-mariadb

goss-test-dev-fullstack-optional: ## [DEV] Full-Stack with all optional services
	@$(MAKE) --no-print-directory goss-test-preset PRESET=dev-fullstack-optional

goss-test-dev-framework: ## [DEV] Framework mode (Nuxt/Next + Express)
	@$(MAKE) --no-print-directory goss-test-preset PRESET=dev-framework

goss-test-dev-assets: ## [DEV] Assets-only (Vite HMR, no Express)
	@$(MAKE) --no-print-directory goss-test-preset PRESET=dev-assets

goss-test-dev-idle: ## [DEV] Idle mode (Node container idle)
	@$(MAKE) --no-print-directory goss-test-preset PRESET=dev-idle

# Production Presets (self-contained images, no host dependencies)
goss-test-prod-fullstack: ## [PROD] Full-Stack (PHP + Node + Postgres + Redis)
	@$(MAKE) --no-print-directory goss-test-preset PRESET=prod-fullstack

goss-test-prod-fullstack-mariadb: ## [PROD] Full-Stack with MariaDB
	@$(MAKE) --no-print-directory goss-test-preset PRESET=prod-fullstack-mariadb

goss-test-prod-fullstack-optional: ## [PROD] Full-Stack with all optional services
	@$(MAKE) --no-print-directory goss-test-preset PRESET=prod-fullstack-optional

goss-test-prod-php-only: ## [PROD] PHP-Only (PHP + Postgres + Redis)
	@$(MAKE) --no-print-directory goss-test-preset PRESET=prod-php-only

goss-test-prod-node-only: ## [PROD] Node-Only (Node + Postgres + Redis)
	@$(MAKE) --no-print-directory goss-test-preset PRESET=prod-node-only

goss-test-prod-minimal: ## [PROD] Minimal (Nginx only)
	@$(MAKE) --no-print-directory goss-test-preset PRESET=prod-minimal

# Matrix Tests (VERBOSE=1 for full output)
goss-test-matrix: ## Run ALL preset tests (dev + prod, VERBOSE=1 for details)
	@echo -e "\033[0;33mRunning GOSS test matrix (all presets)...\033[0m"
	@FAILED=0; \
	for preset in tests/goss/presets/*.env; do \
		PRESET_NAME=$$(basename "$$preset" .env); \
		echo -e "\n\033[0;35m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m"; \
		echo -e "\033[0;35mPreset: $$PRESET_NAME\033[0m"; \
		echo -e "\033[0;35m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m"; \
		$(MAKE) --no-print-directory goss-test-preset PRESET=$$PRESET_NAME VERBOSE=$(VERBOSE) || FAILED=1; \
	done; \
	if [ $$FAILED -eq 1 ]; then \
		echo -e "\033[0;31m✗ Some preset tests failed!\033[0m"; \
		exit 1; \
	fi
	@echo -e "\033[0;32m✓ All preset tests passed!\033[0m"

goss-test-matrix-dev: ## Run development preset tests only (VERBOSE=1 for details)
	@echo -e "\033[0;33mRunning GOSS test matrix (development presets)...\033[0m"
	@FAILED=0; \
	for preset in tests/goss/presets/dev-*.env; do \
		PRESET_NAME=$$(basename "$$preset" .env); \
		echo -e "\n\033[0;35m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m"; \
		echo -e "\033[0;35mPreset: $$PRESET_NAME\033[0m"; \
		echo -e "\033[0;35m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m"; \
		$(MAKE) --no-print-directory goss-test-preset PRESET=$$PRESET_NAME VERBOSE=$(VERBOSE) || FAILED=1; \
	done; \
	if [ $$FAILED -eq 1 ]; then \
		echo -e "\033[0;31m✗ Some development preset tests failed!\033[0m"; \
		exit 1; \
	fi
	@echo -e "\033[0;32m✓ All development preset tests passed!\033[0m"

goss-test-matrix-prod: ## Run production preset tests only (CI/CD, VERBOSE=1 for details)
	@echo -e "\033[0;33mRunning GOSS test matrix (production presets)...\033[0m"
	@FAILED=0; \
	for preset in tests/goss/presets/prod-*.env; do \
		PRESET_NAME=$$(basename "$$preset" .env); \
		echo -e "\n\033[0;35m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m"; \
		echo -e "\033[0;35mPreset: $$PRESET_NAME\033[0m"; \
		echo -e "\033[0;35m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m"; \
		$(MAKE) --no-print-directory goss-test-preset PRESET=$$PRESET_NAME VERBOSE=$(VERBOSE) || FAILED=1; \
	done; \
	if [ $$FAILED -eq 1 ]; then \
		echo -e "\033[0;31m✗ Some production preset tests failed!\033[0m"; \
		exit 1; \
	fi
	@echo -e "\033[0;32m✓ All production preset tests passed!\033[0m"

goss-cleanup: ## Remove all Goss test containers, networks, and volumes
	@echo -e "\033[0;33mCleaning up Goss test resources...\033[0m"
	@GOSS_CONTAINERS=$$(docker ps -aq --filter "name=zappzarapp-goss-" 2>/dev/null); \
	if [ -n "$$GOSS_CONTAINERS" ]; then \
		echo "  Stopping and removing containers..."; \
		docker rm -f $$GOSS_CONTAINERS 2>/dev/null || true; \
	else \
		echo "  No Goss containers found"; \
	fi; \
	GOSS_NETWORKS=$$(docker network ls -q --filter "name=zappzarapp-goss-" 2>/dev/null); \
	if [ -n "$$GOSS_NETWORKS" ]; then \
		echo "  Removing networks..."; \
		docker network rm $$GOSS_NETWORKS 2>/dev/null || true; \
	fi; \
	GOSS_VOLUMES=$$(docker volume ls -q --filter "name=zappzarapp-goss-" 2>/dev/null); \
	if [ -n "$$GOSS_VOLUMES" ]; then \
		echo "  Removing volumes..."; \
		docker volume rm $$GOSS_VOLUMES 2>/dev/null || true; \
	fi
	@echo -e "\033[0;32m✓ Goss cleanup complete\033[0m"

##@ BATS Makefile Tests
#
# BATS (Bash Automated Testing System) tests for Makefile targets
# Validates: Help system, environment loading, Docker commands, presets
#

BATS_IMAGE := zappzarapp-bats:latest
BATS_CONTAINER := zappzarapp-bats
INTEGRATION_PRESET ?= dev-fullstack-optional

bats-build: ## Build BATS testing tool image
	@echo -e "\033[0;34mBuilding BATS image...\033[0m"
	@docker build -t $(BATS_IMAGE) -f docker/bats/Dockerfile . --target production
	@echo -e "\033[0;32m✓ BATS image built successfully\033[0m"

bats-test: ## Run BATS tests for Makefile targets (requires built image)
	@if ! docker image inspect $(BATS_IMAGE) >/dev/null 2>&1; then \
		echo -e "\033[0;33mBats image not found, building...\033[0m"; \
		$(MAKE) --silent bats-build; \
	fi
	@echo -e "\033[0;33mRunning BATS tests...\033[0m"
	@docker run --rm \
		-v /var/run/docker.sock:/var/run/docker.sock \
		-v "$(PWD):$(PWD)" \
		-w "$(PWD)" \
		--network host \
		$(BATS_IMAGE) tests/bats/
	@echo -e "\033[0;32m✓ BATS tests complete\033[0m"

bats-test-file: ## Run specific BATS test file (FILE=make-help.bats)
	@if [ -z "$(FILE)" ]; then \
		echo -e "\033[0;31mError: FILE parameter required (e.g., make bats-test-file FILE=make-help.bats)\033[0m"; \
		exit 1; \
	fi
	@docker run --rm \
		-v /var/run/docker.sock:/var/run/docker.sock \
		-v "$(PWD):$(PWD)" \
		-w "$(PWD)" \
		--network host \
		$(BATS_IMAGE) "tests/bats/$(FILE)"

bats-test-verbose: ## Run BATS tests with verbose output (TAP format)
	@docker run --rm \
		-v /var/run/docker.sock:/var/run/docker.sock \
		-v "$(PWD):$(PWD)" \
		-w "$(PWD)" \
		--network host \
		$(BATS_IMAGE) --tap tests/bats/

bats-test-local: ## Run BATS tests locally (requires bats installed)
	@if ! command -v bats &>/dev/null; then \
		echo -e "\033[0;31mError: bats not found. Install with: brew install bats-core\033[0m"; \
		exit 1; \
	fi
	@bats tests/bats/

bats-test-junit: ## Run BATS tests with JUnit XML output (for CI/CD)
	@mkdir -p build
	@docker run --rm \
		-v /var/run/docker.sock:/var/run/docker.sock \
		-v "$(PWD):$(PWD)" \
		-w "$(PWD)" \
		--network host \
		$(BATS_IMAGE) --formatter junit tests/bats/ > build/bats-report.xml || \
		(cat build/bats-report.xml && exit 1)
	@echo -e "\033[0;32m✓ JUnit report: build/bats-report.xml\033[0m"

bats-test-integration: ## Run BATS integration tests (requires running containers)
	@echo -e "\033[0;33mRunning BATS integration tests...\033[0m"
	@echo -e "\033[0;34mNote: This requires containers to be running (make up)\033[0m"
	@# In CI: use root (can delete files created by containers)
	@# Locally: use host user UID/GID (preserves file ownership)
	@if [ -n "$${CI:-}" ]; then \
		docker run --rm \
			-v /var/run/docker.sock:/var/run/docker.sock \
			-v "$(PWD):$(PWD)" \
			-w "$(PWD)" \
			--network host \
			--user root \
			-e INTEGRATION_PRESET=$(INTEGRATION_PRESET) \
			$${COMPOSE_FILE:+-e COMPOSE_FILE=$$COMPOSE_FILE} \
			$(BATS_IMAGE) tests/bats/integration/; \
	else \
		docker run --rm \
			-v /var/run/docker.sock:/var/run/docker.sock \
			-v "$(PWD):$(PWD)" \
			-w "$(PWD)" \
			--network host \
			--user "$$(id -u):$$(id -g)" \
			--group-add "$$(stat -c %g /var/run/docker.sock)" \
			-e INTEGRATION_PRESET=$(INTEGRATION_PRESET) \
			$${COMPOSE_FILE:+-e COMPOSE_FILE=$$COMPOSE_FILE} \
			$(BATS_IMAGE) tests/bats/integration/; \
	fi
	@echo -e "\033[0;32m✓ BATS integration tests complete\033[0m"

bats-test-integration-file: ## Run specific BATS integration test file (FILE=lint.bats)
	@if [ -z "$(FILE)" ]; then \
		echo -e "\033[0;31mError: FILE parameter required (e.g., make bats-test-integration-file FILE=lint.bats)\033[0m"; \
		exit 1; \
	fi
	@# In CI: use root (can delete files created by containers)
	@# Locally: use host user UID/GID (preserves file ownership)
	@if [ -n "$${CI:-}" ]; then \
		docker run --rm \
			-v /var/run/docker.sock:/var/run/docker.sock \
			-v "$(PWD):$(PWD)" \
			-w "$(PWD)" \
			--network host \
			--user root \
			-e INTEGRATION_PRESET=$(INTEGRATION_PRESET) \
			-e BATS_ENABLE_DESTRUCTIVE=$(BATS_ENABLE_DESTRUCTIVE) \
			$${COMPOSE_FILE:+-e COMPOSE_FILE=$$COMPOSE_FILE} \
			$(BATS_IMAGE) "tests/bats/integration/$(FILE)"; \
	else \
		docker run --rm \
			-v /var/run/docker.sock:/var/run/docker.sock \
			-v "$(PWD):$(PWD)" \
			-w "$(PWD)" \
			--network host \
			--user "$$(id -u):$$(id -g)" \
			--group-add "$$(stat -c %g /var/run/docker.sock)" \
			-e INTEGRATION_PRESET=$(INTEGRATION_PRESET) \
			-e BATS_ENABLE_DESTRUCTIVE=$(BATS_ENABLE_DESTRUCTIVE) \
			$${COMPOSE_FILE:+-e COMPOSE_FILE=$$COMPOSE_FILE} \
			$(BATS_IMAGE) "tests/bats/integration/$(FILE)"; \
	fi

bats-test-all: ## Run all BATS tests (unit + integration)
	@echo -e "\033[0;33mRunning all BATS tests...\033[0m"
	@$(MAKE) bats-test
	@$(MAKE) bats-test-integration

bats-test-destructive: ## Run BATS destructive tests (⚠️ WARNING: modifies data!)
	@echo -e "\033[0;31m⚠️  WARNING: Running destructive tests!\033[0m"
	@echo -e "\033[0;31mThese tests will:\033[0m"
	@echo -e "\033[0;31m  - Stop and remove containers\033[0m"
	@echo -e "\033[0;31m  - Delete volumes and data\033[0m"
	@echo -e "\033[0;31m  - Remove Docker images\033[0m"
	@echo -e "\033[0;31m  - Flush Redis\033[0m"
	@echo -e ""
	@read -p "Are you sure you want to continue? [y/N] " confirm; \
	if [ "$$confirm" != "y" ] && [ "$$confirm" != "Y" ]; then \
		echo "Aborted."; \
		exit 1; \
	fi
	@# In CI: use root (can delete files created by containers)
	@# Locally: use host user UID/GID (preserves file ownership)
	@if [ -n "$${CI:-}" ]; then \
		docker run --rm \
			-v /var/run/docker.sock:/var/run/docker.sock \
			-v "$(PWD):$(PWD)" \
			-w "$(PWD)" \
			--network host \
			--user root \
			-e BATS_ENABLE_DESTRUCTIVE=true \
			$${COMPOSE_FILE:+-e COMPOSE_FILE=$$COMPOSE_FILE} \
			$(BATS_IMAGE) tests/bats/integration/destructive.bats; \
	else \
		docker run --rm \
			-v /var/run/docker.sock:/var/run/docker.sock \
			-v "$(PWD):$(PWD)" \
			-w "$(PWD)" \
			--network host \
			--user "$$(id -u):$$(id -g)" \
			--group-add "$$(stat -c %g /var/run/docker.sock)" \
			-e BATS_ENABLE_DESTRUCTIVE=true \
			$${COMPOSE_FILE:+-e COMPOSE_FILE=$$COMPOSE_FILE} \
			$(BATS_IMAGE) tests/bats/integration/destructive.bats; \
	fi

deps-validate: ## Validate dependency lockfiles (composer.lock, pnpm-lock.yaml)
	@echo -e "\033[0;33mValidating Composer configuration...\033[0m"
	@docker compose exec php composer validate
	@echo -e "\033[0;32m✓ Composer configuration is valid!\033[0m"
	@echo ""
	@echo -e "\033[0;33mValidating pnpm lockfile...\033[0m"
	@if [ ! -f package.json ]; then \
		echo -e "\033[0;31mError: package.json not found\033[0m"; \
		exit 1; \
	fi
	@if [ ! -f pnpm-lock.yaml ]; then \
		echo -e "\033[0;31mError: pnpm-lock.yaml not found\033[0m"; \
		exit 1; \
	fi
	@echo "✓ package.json exists"
	@echo "✓ pnpm-lock.yaml exists"
	@echo -e "\033[0;32m✓ pnpm lockfile is present!\033[0m"

compose-validate: ## Validate Docker Compose configuration files
	@echo -e "\033[0;33mValidating Docker Compose files...\033[0m"
	@docker compose config --quiet && \
		echo -e "\033[0;32m  ✓ compose.yaml valid\033[0m"
	@docker compose -f compose.yaml -f compose.production.yaml config --quiet && \
		echo -e "\033[0;32m  ✓ compose.yaml + compose.production.yaml valid\033[0m"
	@docker compose -f compose.yaml -f compose.ci.yaml config --quiet && \
		echo -e "\033[0;32m  ✓ compose.yaml + compose.ci.yaml valid\033[0m"
	@echo -e "\033[0;32m✓ All compose configurations valid\033[0m"

validate-env: ## Validate .env configuration for production readiness
	@echo -e "\033[0;33mValidating .env configuration...\033[0m"
	@if [ ! -f .env ]; then \
		echo -e "\033[0;31m  ✗ .env file not found\033[0m"; \
		echo -e "\033[0;36m  Hint: .env ships committed in the repo; restore it with 'git restore .env'\033[0m"; \
		exit 1; \
	fi
	@echo -e "\033[0;32m  ✓ .env file exists\033[0m"
	@# Check if .env can be sourced (basic syntax validation)
	@if ! sh -c '. ./.env' 2>/dev/null; then \
		echo -e "\033[0;31m  ✗ .env file has syntax errors\033[0m"; \
		exit 1; \
	fi
	@echo -e "\033[0;32m  ✓ .env syntax valid\033[0m"
	@# Check for required variables
	@MISSING_VARS=""; \
	$(LOAD_ENV); \
	if [ -z "$$COMPOSE_PROJECT_NAME" ]; then MISSING_VARS="$$MISSING_VARS COMPOSE_PROJECT_NAME"; fi; \
	if [ -z "$$DB_TYPE" ]; then MISSING_VARS="$$MISSING_VARS DB_TYPE"; fi; \
	if [ -z "$$DB_HOST" ]; then MISSING_VARS="$$MISSING_VARS DB_HOST"; fi; \
	if [ -n "$$MISSING_VARS" ]; then \
		echo -e "\033[0;31m  ✗ Required variables missing:$$MISSING_VARS\033[0m"; \
		exit 1; \
	fi
	@echo -e "\033[0;32m  ✓ Required variables present\033[0m"
	@# Warn if ZAPPZARAPP_ENV is not production (non-fatal, just informative)
	@CURRENT_ENV=$$($(LOAD_ENV) && echo "$${ZAPPZARAPP_ENV:-development}"); \
	if [ "$$CURRENT_ENV" != "production" ]; then \
		echo -e "\033[0;33m  ⚠ ZAPPZARAPP_ENV=$$CURRENT_ENV (production builds require ZAPPZARAPP_ENV=production)\033[0m"; \
	else \
		echo -e "\033[0;32m  ✓ ZAPPZARAPP_ENV=production\033[0m"; \
	fi
	@echo -e "\033[0;32m✓ .env configuration valid\033[0m"

##@ Security

secrets: ## Generate missing Docker Secrets (idempotent)
	@mkdir -p secrets
	# Mode 755: Directory readable by all (needed for bind-mount in Docker Compose)
	# For stricter security, use Kubernetes with native K8s Secrets + securityContext.fsGroup
	@chmod 755 secrets
	@echo -e "\033[0;33mChecking Docker Secrets...\033[0m"
	@if [ ! -f secrets/db_password.txt ]; then \
		echo -e "\033[0;34mGenerating db_password secret...\033[0m"; \
		openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | head -c 24 > secrets/db_password.txt; \
		chmod 644 secrets/db_password.txt; \
		echo -e "\033[0;32mdb_password secret generated.\033[0m"; \
	else \
		echo -e "\033[0;32mdb_password secret already exists.\033[0m"; \
	fi
	@if [ ! -f secrets/db_root_password.txt ]; then \
		echo -e "\033[0;34mGenerating db_root_password secret...\033[0m"; \
		openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | head -c 24 > secrets/db_root_password.txt; \
		chmod 644 secrets/db_root_password.txt; \
		echo -e "\033[0;32mdb_root_password secret generated.\033[0m"; \
	else \
		echo -e "\033[0;32mdb_root_password secret already exists.\033[0m"; \
	fi
	@if [ ! -f secrets/encryption_key.txt ]; then \
		echo -e "\033[0;34mGenerating encryption_key secret...\033[0m"; \
		openssl rand -base64 32 > secrets/encryption_key.txt; \
		chmod 644 secrets/encryption_key.txt; \
		echo -e "\033[0;32mencryption_key secret generated.\033[0m"; \
	else \
		echo -e "\033[0;32mencryption_key secret already exists.\033[0m"; \
	fi
	@if [ ! -f secrets/backup_encryption_key.txt ]; then \
		echo -e "\033[0;34mGenerating backup_encryption_key secret...\033[0m"; \
		openssl rand -base64 32 > secrets/backup_encryption_key.txt; \
		chmod 644 secrets/backup_encryption_key.txt; \
		echo -e "\033[0;32mbackup_encryption_key secret generated.\033[0m"; \
	else \
		echo -e "\033[0;32mbackup_encryption_key secret already exists.\033[0m"; \
	fi
	@if [ ! -f secrets/meilisearch_master_key.txt ]; then \
		echo -e "\033[0;34mGenerating meilisearch_master_key secret...\033[0m"; \
		openssl rand -base64 32 | tr -dc 'a-zA-Z0-9' | head -c 32 > secrets/meilisearch_master_key.txt; \
		chmod 644 secrets/meilisearch_master_key.txt; \
		echo -e "\033[0;32mmeilisearch_master_key secret generated.\033[0m"; \
	else \
		echo -e "\033[0;32mmeilisearch_master_key secret already exists.\033[0m"; \
	fi
	@if [ ! -f secrets/seaweedfs_access_key.txt ]; then \
		echo -e "\033[0;34mGenerating seaweedfs_access_key secret...\033[0m"; \
		openssl rand -base64 16 | tr -dc 'a-zA-Z0-9' | head -c 20 > secrets/seaweedfs_access_key.txt; \
		chmod 644 secrets/seaweedfs_access_key.txt; \
		echo -e "\033[0;32mseaweedfs_access_key secret generated.\033[0m"; \
	else \
		echo -e "\033[0;32mseaweedfs_access_key secret already exists.\033[0m"; \
	fi
	@if [ ! -f secrets/seaweedfs_secret_key.txt ]; then \
		echo -e "\033[0;34mGenerating seaweedfs_secret_key secret...\033[0m"; \
		openssl rand -base64 32 | tr -dc 'a-zA-Z0-9' | head -c 40 > secrets/seaweedfs_secret_key.txt; \
		chmod 644 secrets/seaweedfs_secret_key.txt; \
		echo -e "\033[0;32mseaweedfs_secret_key secret generated.\033[0m"; \
	else \
		echo -e "\033[0;32mseaweedfs_secret_key secret already exists.\033[0m"; \
	fi
	@if [ ! -f secrets/rabbitmq_user.txt ]; then \
		echo -e "\033[0;34mGenerating rabbitmq_user secret...\033[0m"; \
		echo "app" > secrets/rabbitmq_user.txt; \
		chmod 644 secrets/rabbitmq_user.txt; \
		echo -e "\033[0;32mrabbitmq_user secret generated.\033[0m"; \
	else \
		echo -e "\033[0;32mrabbitmq_user secret already exists.\033[0m"; \
	fi
	@if [ ! -f secrets/rabbitmq_password.txt ]; then \
		echo -e "\033[0;34mGenerating rabbitmq_password secret...\033[0m"; \
		openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | head -c 24 > secrets/rabbitmq_password.txt; \
		chmod 644 secrets/rabbitmq_password.txt; \
		echo -e "\033[0;32mrabbitmq_password secret generated.\033[0m"; \
	else \
		echo -e "\033[0;32mrabbitmq_password secret already exists.\033[0m"; \
	fi
	@if [ ! -f secrets/pgadmin_password.txt ]; then \
		echo -e "\033[0;34mGenerating pgadmin_password secret...\033[0m"; \
		openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | head -c 24 > secrets/pgadmin_password.txt; \
		chmod 644 secrets/pgadmin_password.txt; \
		echo -e "\033[0;32mpgadmin_password secret generated.\033[0m"; \
	else \
		echo -e "\033[0;32mpgadmin_password secret already exists.\033[0m"; \
	fi
	@if [ ! -f secrets/mercure_jwt_secret.txt ]; then \
		echo -e "\033[0;34mGenerating mercure_jwt_secret secret...\033[0m"; \
		openssl rand -base64 32 > secrets/mercure_jwt_secret.txt; \
		chmod 644 secrets/mercure_jwt_secret.txt; \
		echo -e "\033[0;32mmercure_jwt_secret secret generated.\033[0m"; \
	else \
		echo -e "\033[0;32mmercure_jwt_secret secret already exists.\033[0m"; \
	fi
	@if [ ! -f secrets/elasticsearch_bootstrap_password.txt ]; then \
		echo -e "\033[0;34mGenerating elasticsearch_bootstrap_password secret...\033[0m"; \
		openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | head -c 24 > secrets/elasticsearch_bootstrap_password.txt; \
		chmod 600 secrets/elasticsearch_bootstrap_password.txt; \
		echo -e "\033[0;32melasticsearch_bootstrap_password secret generated.\033[0m"; \
		echo -e "\033[0;34m  Note: API key must be generated after ES starts: make es-setup-api-key\033[0m"; \
	else \
		echo -e "\033[0;32melasticsearch_bootstrap_password secret already exists.\033[0m"; \
	fi
	@echo -e "\033[0;32mSecrets check completed!\033[0m"

secrets-rotate-passwords: ## Rotate database passwords only (safe, keeps encryption keys)
	@echo -e "\033[0;33m╔══════════════════════════════════════════════════════════════════════╗\033[0m"
	@echo -e "\033[0;33m║  Rotating database passwords (encryption keys preserved)             ║\033[0m"
	@echo -e "\033[0;33m╠══════════════════════════════════════════════════════════════════════╣\033[0m"
	@echo -e "\033[0;33m║  • Containers must be restarted after rotation                       ║\033[0m"
	@echo -e "\033[0;33m║  • Update external connections using these passwords!                ║\033[0m"
	@echo -e "\033[0;33m╚══════════════════════════════════════════════════════════════════════╝\033[0m"
	@read -p "Continue? (yes/no): " confirm && [ "$$confirm" = "yes" ] || (echo "Aborted."; exit 1)
	@echo -e "\033[0;33mRotating database passwords...\033[0m"
	@rm -f secrets/db_password.txt secrets/db_root_password.txt
	@$(MAKE) --silent secrets
	@$(MAKE) --silent ide-config
	@echo -e "\033[0;32mPasswords rotated. Run 'make down && make up' to apply changes.\033[0m"

secrets-rotate: ## Rotate ALL secrets (DANGER: breaks existing backups!)
	@echo -e "\033[0;31m╔══════════════════════════════════════════════════════════════════════╗\033[0m"
	@echo -e "\033[0;31m║  ⚠️  WARNING: This will delete and regenerate ALL secrets!           ║\033[0m"
	@echo -e "\033[0;31m╠══════════════════════════════════════════════════════════════════════╣\033[0m"
	@echo -e "\033[0;31m║  • Containers must be restarted after rotation                       ║\033[0m"
	@echo -e "\033[0;31m║  • Database passwords will change - update external connections!     ║\033[0m"
	@echo -e "\033[0;31m║  • BACKUP_ENCRYPTION_KEY change = existing backups unreadable!       ║\033[0m"
	@echo -e "\033[0;31m║    → Back up old key first: cat secrets/backup_encryption_key.txt   ║\033[0m"
	@echo -e "\033[0;31m╚══════════════════════════════════════════════════════════════════════╝\033[0m"
	@read -p "Are you sure? (yes/no): " confirm && [ "$$confirm" = "yes" ] || (echo "Aborted."; exit 1)
	@echo -e "\033[0;33mRotating secrets...\033[0m"
	@rm -f secrets/db_password.txt secrets/db_root_password.txt secrets/encryption_key.txt secrets/backup_encryption_key.txt
	@$(MAKE) --silent secrets
	@echo -e "\033[0;33mSecrets rotated. Run 'make down && make up' to apply changes.\033[0m"

.PHONY: check-cors
check-cors: ## Show current CORS configuration
	@echo -e "\033[0;36m╔══════════════════════════════════════════════════════════════════════╗\033[0m"
	@echo -e "\033[0;36m║  CORS Configuration (Cross-Origin Resource Sharing)                  ║\033[0m"
	@echo -e "\033[0;36m╚══════════════════════════════════════════════════════════════════════╝\033[0m"
	@echo ""
	@echo -e "\033[0;33mCORS_ORIGINS (from running containers):\033[0m"
	@docker compose exec php printenv CORS_ORIGINS 2>/dev/null && echo -e "\033[0;32m  PHP container: OK\033[0m" || echo -e "\033[0;31m  PHP container not running\033[0m"
	@docker compose exec node printenv CORS_ORIGINS 2>/dev/null && echo -e "\033[0;32m  Node container: OK\033[0m" || echo -e "\033[0;31m  Node container not running\033[0m"
	@echo ""
	@echo -e "\033[0;33mSource files:\033[0m"
	@if [ -f .env ]; then \
		echo -e "\033[0;34m  .env:\033[0m $$(grep "^CORS_ORIGINS=" .env | cut -d'=' -f2)"; \
	fi
	@if [ -f .env.local ]; then \
		echo -e "\033[0;33m  .env.local (OVERRIDE):\033[0m $$(grep "^CORS_ORIGINS=" .env.local | cut -d'=' -f2)"; \
	fi
	@if [ -f .env.production ]; then \
		echo -e "\033[0;35m  .env.production (PROD):\033[0m $$(grep "^CORS_ORIGINS=" .env.production | cut -d'=' -f2)"; \
	fi
	@echo ""
	@echo -e "\033[0;36mSecurity Check:\033[0m"
	@if grep -q "^CORS_ORIGINS=\*" .env 2>/dev/null; then \
		echo -e "\033[0;31m  ⚠️  WARNING: .env uses CORS_ORIGINS=* (insecure default!)\033[0m"; \
	else \
		echo -e "\033[0;32m  ✓ .env has restrictive CORS_ORIGINS (secure default)\033[0m"; \
	fi
	@if [ -f .env.local ] && grep -q "^CORS_ORIGINS=\*" .env.local 2>/dev/null; then \
		echo -e "\033[0;33m  ℹ️  INFO: .env.local uses CORS_ORIGINS=* (development override)\033[0m"; \
	fi
	@if [ -f .env.production ] && grep -q "^CORS_ORIGINS=\*" .env.production 2>/dev/null; then \
		echo -e "\033[0;31m  ❌ CRITICAL: .env.production uses CORS_ORIGINS=* (NEVER use in production!)\033[0m"; \
		echo -e "\033[0;31m  This is a severe security risk. Deployment blocked.\033[0m"; \
		exit 1; \
	else \
		if [ -f .env.production ]; then \
			echo -e "\033[0;32m  ✓ .env.production has restrictive CORS_ORIGINS (secure)\033[0m"; \
		fi \
	fi
	@echo ""
	@echo -e "\033[0;36mDocumentation:\033[0m .zappzarapp/docs/security/CORS.md"

falco-run: ## Start Falco for Runtime Security Monitoring (requires root/sudo on Linux)
	@echo -e "\033[0;33mStarting Falco for runtime monitoring...\033[0m"
	@echo -e "\033[0;31mNote: Falco runs with --privileged and monitors ALL containers on the host.\033[0m"
	@docker run --rm -it \
		--name falco-monitor \
		--privileged \
		-v /var/run/docker.sock:/host/var/run/docker.sock \
		-v /dev:/host/dev \
		-v /proc:/host/proc:ro \
		falcosecurity/falco:latest

security-config: ## Check Dockerfiles for misconfigurations
	@echo -e "\033[0;33mScanning Dockerfiles for security issues...\033[0m"
	@docker run --rm -v $$(pwd):/project \
		aquasec/trivy:latest config /project/docker
	@echo -e "\033[0;32mConfiguration scan completed!\033[0m"

security-deps: ## Scan Composer dependencies for known vulnerabilities (uses local security-check if available)
	@echo -e "\033[0;33mScanning Composer dependencies...\033[0m"
	@if command -v security-check >/dev/null 2>&1; then \
		echo "Using local security-check..."; \
		security-check; \
	else \
		echo "Local security-check not found, using Docker..."; \
		docker compose exec php composer security-check; \
	fi
	@echo -e "\033[0;32mDependency scan completed!\033[0m"

security-sbom: ## Generate a Software Bill of Materials (SBOM) using Trivy
	@echo -e "\033[0;33mGenerating SBOM for PHP image...\033[0m"
	@mkdir -p build
	@if [ -f .env ]; then \
		$(LOAD_ENV) && \
		PROJECT=$${COMPOSE_PROJECT_NAME:-zappzarapp} && \
		TAG=$$(docker images --format "{{.Tag}}" "$${PROJECT}-php" 2>/dev/null | grep -E "^(development|latest)$$" | head -1) && \
		if [ -z "$$TAG" ]; then echo -e "\033[0;31mNo PHP image found (development or latest)\033[0m"; exit 1; fi && \
		docker run --rm -v /var/run/docker.sock:/var/run/docker.sock \
			-v "$$(pwd)/build:/output" \
			aquasec/trivy:latest image --format cyclonedx --output /output/sbom-php.json \
			$${PROJECT}-php:$${TAG}; \
	fi
	@echo -e "\033[0;32mSBOM generated in build/sbom-php.json!\033[0m"

security-scan: ## Scan all existing Docker images for vulnerabilities
	@echo -e "\033[0;33mScanning images for vulnerabilities...\033[0m"
	@$(LOAD_ENV) && \
	PROJECT=$${COMPOSE_PROJECT_NAME:-zappzarapp} && \
	SCANNED=0 && \
	for IMAGE in php nginx node node-backend \
	             postgres mariadb redis \
	             elasticsearch meilisearch \
	             rabbitmq mercure mailpit seaweedfs; do \
		FULL_IMAGE="$${PROJECT}-$${IMAGE}:latest"; \
		if docker image inspect "$$FULL_IMAGE" >/dev/null 2>&1; then \
			[ $$SCANNED -gt 0 ] && echo ""; \
			echo "Scanning $$IMAGE image..."; \
			docker run --rm -v /var/run/docker.sock:/var/run/docker.sock \
				aquasec/trivy:latest image --severity HIGH,CRITICAL "$$FULL_IMAGE"; \
			SCANNED=$$((SCANNED + 1)); \
		fi; \
	done && \
	if [ $$SCANNED -eq 0 ]; then \
		echo "⚠️  No images found. Run 'make build' first."; \
	else \
		echo "" && echo "Scanned $$SCANNED image(s)."; \
	fi
	@echo -e "\033[0;32mSecurity scan completed!\033[0m"

security-audit-node: ## Scan Node.js dependencies for known vulnerabilities
	@echo -e "\033[0;33mScanning Node.js dependencies with pnpm audit...\033[0m"
	@$(DC) run --rm -T dev-tools pnpm audit

security-zap-start: ## Start services in production mode for ZAP scanning (respects .env ENABLE_* flags)
	@echo -e "\033[0;33mStopping any running containers...\033[0m"
	@$(LOAD_ENV); \
	PROFILES=""; \
	if [ "$${ENABLE_PHP:-true}" = "true" ]; then PROFILES="$$PROFILES --profile php"; fi; \
	if [ "$${ENABLE_DATABASE:-true}" = "true" ]; then PROFILES="$$PROFILES --profile $${DB_TYPE:-postgres}"; fi; \
	if [ "$${ENABLE_REDIS:-true}" = "true" ]; then PROFILES="$$PROFILES --profile redis"; fi; \
	if [ "$${ENABLE_NODE:-true}" = "true" ]; then \
		case "$${NODE_MODE:-assets-api}" in \
			assets|idle) PROFILES="$$PROFILES --profile node" ;; \
			api) PROFILES="$$PROFILES --profile node-backend" ;; \
			assets-api) PROFILES="$$PROFILES --profile node --profile node-backend" ;; \
			framework) PROFILES="$$PROFILES --profile node" ;; \
			framework-api) PROFILES="$$PROFILES --profile node --profile node-backend" ;; \
			*) PROFILES="$$PROFILES --profile node-backend" ;; \
		esac; \
	fi; \
	if [ "$${ENABLE_MERCURE:-false}" = "true" ]; then PROFILES="$$PROFILES --profile mercure"; fi; \
	if [ "$${ENABLE_MEILISEARCH:-false}" = "true" ]; then PROFILES="$$PROFILES --profile meilisearch"; fi; \
	if [ "$${ENABLE_ELASTICSEARCH:-false}" = "true" ]; then PROFILES="$$PROFILES --profile elasticsearch"; fi; \
	if [ "$${ENABLE_SEAWEEDFS:-false}" = "true" ]; then PROFILES="$$PROFILES --profile seaweedfs"; fi; \
	$(DC) -f compose.yaml -f compose.production.yaml $$PROFILES down || true
	@echo -e "\033[0;33mStarting services in production mode (forcing container recreation for template processing)...\033[0m"
	@$(LOAD_ENV); \
	PROFILES=""; \
	if [ "$${ENABLE_PHP:-true}" = "true" ]; then PROFILES="$$PROFILES --profile php"; fi; \
	if [ "$${ENABLE_DATABASE:-true}" = "true" ]; then PROFILES="$$PROFILES --profile $${DB_TYPE:-postgres}"; fi; \
	if [ "$${ENABLE_REDIS:-true}" = "true" ]; then PROFILES="$$PROFILES --profile redis"; fi; \
	if [ "$${ENABLE_NODE:-true}" = "true" ]; then \
		case "$${NODE_MODE:-assets-api}" in \
			assets|idle) PROFILES="$$PROFILES --profile node" ;; \
			api) PROFILES="$$PROFILES --profile node-backend" ;; \
			assets-api) PROFILES="$$PROFILES --profile node --profile node-backend" ;; \
			framework) PROFILES="$$PROFILES --profile node" ;; \
			framework-api) PROFILES="$$PROFILES --profile node --profile node-backend" ;; \
			*) PROFILES="$$PROFILES --profile node-backend" ;; \
		esac; \
	fi; \
	if [ "$${ENABLE_MERCURE:-false}" = "true" ]; then PROFILES="$$PROFILES --profile mercure"; fi; \
	if [ "$${ENABLE_MEILISEARCH:-false}" = "true" ]; then PROFILES="$$PROFILES --profile meilisearch"; fi; \
	if [ "$${ENABLE_ELASTICSEARCH:-false}" = "true" ]; then PROFILES="$$PROFILES --profile elasticsearch"; fi; \
	if [ "$${ENABLE_SEAWEEDFS:-false}" = "true" ]; then PROFILES="$$PROFILES --profile seaweedfs"; fi; \
	NODE_BACKEND_TARGET="$${NODE_BACKEND_TARGET:-api}"; \
	echo -e "\033[0;34mBuilding node-backend image first (NODE_BACKEND_TARGET=$$NODE_BACKEND_TARGET)...\033[0m" && \
	ZAPPZARAPP_ENV=production $(DC) -f compose.yaml -f compose.production.yaml $$PROFILES build node-backend && \
	echo -e "\033[0;34mTagging node-backend image as :latest for nginx COPY...\033[0m" && \
	docker tag $${COMPOSE_PROJECT_NAME:-zappzarapp}-node-backend:$$NODE_BACKEND_TARGET zappzarapp-node-backend:latest && \
	echo -e "\033[0;32m✓ node-backend image tagged successfully\033[0m" && \
	echo -e "\033[0;34mStarting all services...\033[0m" && \
	ZAPPZARAPP_ENV=production $(DC) -f compose.yaml -f compose.production.yaml $$PROFILES up -d --build --force-recreate
	@echo -e "\033[0;33mWaiting for services to be ready...\033[0m"
	@sleep 10
	@echo -e "\033[0;32m✓ Services ready for ZAP scan\033[0m"
	@echo -e "\033[0;36mℹ️  Run: make security-zap-scan\033[0m"

security-zap-full-start: ## Start ALL services for comprehensive ZAP scanning (ignores .env, forces all ENABLE_*)
	@echo -e "\033[0;33mStopping any running containers...\033[0m"
	@$(LOAD_ENV); \
	PROFILES="--profile php --profile $${DB_TYPE:-postgres} --profile redis --profile node --profile node-backend --profile mercure --profile meilisearch --profile elasticsearch --profile seaweedfs"; \
	$(DC) -f compose.yaml -f compose.production.yaml $$PROFILES down || true
	@echo -e "\033[0;33mStarting ALL services in production mode for comprehensive scan (forcing container recreation)...\033[0m"
	@echo -e "\033[0;36mℹ️  Full mode: Testing maximum attack surface (all Node.js services: frontend + backend API)\033[0m"
	@$(LOAD_ENV); \
	PROFILES="--profile php --profile $${DB_TYPE:-postgres} --profile redis --profile node --profile node-backend --profile mercure --profile meilisearch --profile elasticsearch --profile seaweedfs"; \
	NODE_BACKEND_TARGET="$${NODE_BACKEND_TARGET:-api}"; \
	echo -e "\033[0;34mBuilding node-backend image first (NODE_BACKEND_TARGET=$$NODE_BACKEND_TARGET)...\033[0m" && \
	ZAPPZARAPP_ENV=production $(DC) -f compose.yaml -f compose.production.yaml $$PROFILES build node-backend && \
	echo -e "\033[0;34mTagging node-backend image as :latest for nginx COPY...\033[0m" && \
	docker tag $${COMPOSE_PROJECT_NAME:-zappzarapp}-node-backend:$$NODE_BACKEND_TARGET zappzarapp-node-backend:latest && \
	echo -e "\033[0;32m✓ node-backend image tagged successfully\033[0m" && \
	echo -e "\033[0;34mStarting all services...\033[0m" && \
	ZAPPZARAPP_ENV=production $(DC) -f compose.yaml -f compose.production.yaml $$PROFILES up -d --build --force-recreate
	@echo -e "\033[0;33mWaiting for services to be ready...\033[0m"
	@sleep 15
	@echo -e "\033[0;32m✓ All services ready for comprehensive ZAP scan\033[0m"
	@echo -e "\033[0;36mℹ️  Run: make security-zap-scan\033[0m"

security-zap-scan: ## Run ZAP scan (requires running services)
	@echo -e "\033[0;33mChecking if services are running...\033[0m"
	@if ! docker compose ps | grep -q "Up"; then \
		echo -e "\033[0;31mError: No services running\033[0m"; \
		echo -e "\033[0;33mRun: make security-zap-start\033[0m"; \
		exit 1; \
	fi
	@# Check ZAPPZARAPP_ENV from running PHP container (most reliable)
	@CONTAINER_ENV=$$(docker compose exec -T php printenv ZAPPZARAPP_ENV 2>/dev/null | tr -d '\r\n' || echo "unknown"); \
	if [ "$$CONTAINER_ENV" != "production" ]; then \
		echo -e "\033[0;33m⚠️  WARNING: PHP container is running in $$CONTAINER_ENV mode\033[0m"; \
		echo -e "\033[0;33m   For accurate CSP testing, restart with: make security-zap-start\033[0m"; \
		read -p "Continue anyway? [y/N] " -n 1 -r; \
		echo; \
		if [[ ! $$REPLY =~ ^[Yy]$$ ]]; then \
			exit 1; \
		fi; \
	fi
	@if [ ! -f .zap/rules.tsv ]; then \
		echo -e "\033[0;33m⚠️  .zap/rules.tsv not found, using default rules\033[0m"; \
		ZAP_CONFIG=""; \
	else \
		ZAP_CONFIG="-c .zap/rules.tsv"; \
	fi; \
	echo -e "\033[0;33mRunning ZAP baseline scan...\033[0m"; \
	docker run --rm --network host \
		-v $(PWD):/zap/wrk:rw \
		-t ghcr.io/zaproxy/zaproxy:stable \
		zap-baseline.py \
		-t http://localhost:8080 \
		-r zap-report.html \
		-J zap-report.json \
		$$ZAP_CONFIG \
		-a -j || true
	@echo -e "\033[0;32m✓ ZAP scan complete\033[0m"
	@echo -e "\033[0;36mReport: zap-report.html\033[0m"

security-zap-stop: ## Stop services after ZAP scanning
	@echo -e "\033[0;33mStopping services...\033[0m"
	@ZAPPZARAPP_ENV=production $(MAKE) down
	@echo -e "\033[0;32m✓ Services stopped\033[0m"

security-zap: ## ZAP scan lifecycle respecting .env (start -> scan -> stop)
	@echo -e "\033[0;34m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m"
	@echo -e "\033[0;34m  OWASP ZAP Security Scan (.env config)\033[0m"
	@echo -e "\033[0;34m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m"
	@$(MAKE) security-zap-start
	@$(MAKE) security-zap-scan
	@$(MAKE) security-zap-stop

security-zap-full: ## Comprehensive ZAP scan of ALL services (start-full -> scan -> stop)
	@echo -e "\033[0;34m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m"
	@echo -e "\033[0;34m  OWASP ZAP Security Scan - COMPREHENSIVE (all services)\033[0m"
	@echo -e "\033[0;34m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m"
	@$(MAKE) security-zap-full-start
	@$(MAKE) security-zap-scan
	@$(MAKE) security-zap-stop

##@ Documentation

PHPDOC_VERSION := 3.9.1
PHPDOC_URL := https://github.com/phpDocumentor/phpDocumentor/releases/download/v$(PHPDOC_VERSION)/phpDocumentor.phar
PHPDOC_PHAR := tools/phpdoc.phar

docs: docs-php docs-node ## Generate all API documentation (PHP + Node)

$(PHPDOC_PHAR):
	@echo -e "\033[0;33mDownloading phpDocumentor v$(PHPDOC_VERSION)...\033[0m"
	@mkdir -p tools
	@curl -L $(PHPDOC_URL) -o $(PHPDOC_PHAR)
	@chmod +x $(PHPDOC_PHAR)
	@echo -e "\033[0;32mphpDocumentor downloaded to $(PHPDOC_PHAR)\033[0m"

docs-php: ## Generate PHP API documentation using phpDocumentor
	@echo -e "\033[0;33mEnsuring phpDocumentor is available...\033[0m"
	@# Download phpdoc.phar inside PHP container if not present (run as root for bind mount permissions)
	@docker compose exec -u root php sh -c '[ -f tools/phpdoc.phar ] || (mkdir -p tools && curl -fsSL "https://github.com/phpDocumentor/phpDocumentor/releases/download/v$(PHPDOC_VERSION)/phpDocumentor.phar" -o tools/phpdoc.phar && chmod +x tools/phpdoc.phar && echo "phpDocumentor v$(PHPDOC_VERSION) downloaded")'
	@echo -e "\033[0;33mGenerating PHP API documentation...\033[0m"
	@# Ensure output directory exists with proper permissions (cross-UID in CI)
	@mkdir -p docs/api/php build/tmp && chmod 777 docs/api docs/api/php 2>/dev/null || true
	@# Empty the output dir as root BEFORE generating: a cross-UID chown here
	@# leaves subdirectories that phpDocumentor (running as www-data in the php
	@# container, e.g. host root in CI vs www-data:82) cannot delete, so it
	@# silently keeps a stale index.html. Removing as root always succeeds.
	@docker run --rm -v "$$(pwd)/docs:/docs" $(ALPINE_IMAGE) sh -c 'rm -rf /docs/api/php/* /docs/api/php/.[!.]* 2>/dev/null || true'
	@touch build/tmp/.docs-php.stamp
	@docker compose exec php composer docs
	@# phpDocumentor can report success without having written - verify freshness
	@if [ ! docs/api/php/index.html -nt build/tmp/.docs-php.stamp ]; then \
		echo -e "\033[0;31mError: docs/api/php/index.html was not regenerated\033[0m"; \
		exit 1; \
	fi
	@echo -e "\033[0;33mApplying custom theme...\033[0m"
	@docker compose exec php sh -c '\
		CSS_CONTENT=$$(cat /var/www/html/.zappzarapp/docs/assets/custom-phpdoc.css | tr "\n" " " | sed "s/  */ /g"); \
		find /var/www/html/docs/api/php -name "*.html" -exec sed -i "s|</head>|<style>$$CSS_CONTENT</style></head>|" {} \;'
	@echo -e "\033[0;33mSetting favicon...\033[0m"
	@docker compose exec php sh -c '\
		find /var/www/html/docs/api/php -name "*.html" -exec sed -i "s|images/favicon.ico|/favicon.svg|g" {} \;'
	@echo -e "\033[0;33mSetting dynamic title...\033[0m"
	@docker compose exec php sh -c '\
		PROJECT_NAME=$$(php -r "echo ucfirst(explode(\"/\", json_decode(file_get_contents(\"/var/www/html/composer.json\"), true)[\"name\"])[1] ?? \"App\");"); \
		PROJECT_VERSION=$$(php -r "echo json_decode(file_get_contents(\"/var/www/html/composer.json\"), true)[\"version\"] ?? \"0.0.0\";"); \
		find /var/www/html/docs/api/php -name "*.html" -exec sed -i "s|<title>PHP API</title>|<title>$$PROJECT_NAME - PHP API - v$$PROJECT_VERSION</title>|g" {} \; ; \
		find /var/www/html/docs/api/php -name "*.html" -exec sed -i "s|>PHP API</a>|>$$PROJECT_NAME - PHP API</a>|g" {} \;'
	@# Fix ownership (docs generated as root, fix to host user)
	@$(LOAD_ENV) && docker run --rm -v "$$(pwd)/docs:/docs" $(ALPINE_IMAGE) chown -R $${USER_ID:-1000}:$${GROUP_ID:-1000} /docs/api/php
	@echo -e "\033[0;32mPHP documentation generated in docs/api/php/\033[0m"

docs-node: docs-node-backend docs-node-frontend ## Generate all Node/TypeScript API documentation

docs-node-backend: ## Generate Node.js Backend API documentation using TypeDoc
	@echo -e "\033[0;33mGenerating Node.js Backend API documentation...\033[0m"
	@# Ensure output directory exists with proper permissions (cross-UID in CI)
	@mkdir -p docs/api/node-backend build/tmp && chmod 777 docs/api docs/api/node-backend 2>/dev/null || true
	@# Empty the output dir as root BEFORE generating: a cross-UID chown here
	@# leaves subdirectories that TypeDoc (running as a different UID in the node
	@# container, e.g. host root in CI vs node:1000) cannot delete, so it silently
	@# keeps a stale index.html. Removing as root always succeeds.
	@docker run --rm -v "$$(pwd)/docs:/docs" $(ALPINE_IMAGE) sh -c 'rm -rf /docs/api/node-backend/* /docs/api/node-backend/.[!.]* 2>/dev/null || true'
	@touch build/tmp/.docs-node-backend.stamp
	@$(DC_RUN) run --rm node pnpm run docs:backend
	@# TypeDoc can report success without having written - verify freshness
	@if [ ! docs/api/node-backend/index.html -nt build/tmp/.docs-node-backend.stamp ]; then \
		echo -e "\033[0;31mError: docs/api/node-backend/index.html was not regenerated\033[0m"; \
		exit 1; \
	fi
	@echo -e "\033[0;33mSetting dynamic title...\033[0m"
	@$(DC_RUN) run --rm node sh -c '\
		set -e; \
		PROJECT_NAME=$$(node -e "console.log(require(\"/app/package.json\").name.split(\"/\").pop().replace(/^./, c => c.toUpperCase()))"); \
		PROJECT_VERSION=$$(node -e "console.log(require(\"/app/package.json\").version || \"0.0.0\")"); \
		find /app/docs/api/node-backend -name "*.html" -print0 | xargs -0 sed -i "s|<title>Node Backend API - v$$PROJECT_VERSION</title>|<title>$$PROJECT_NAME - Backend API - v$$PROJECT_VERSION</title>|g"; \
		find /app/docs/api/node-backend -name "*.html" -print0 | xargs -0 sed -i "s|>Node Backend API - v$$PROJECT_VERSION</a>|>$$PROJECT_NAME - Backend API</a>|g"'
	@# Fix ownership (docs may be generated with different UID)
	@$(LOAD_ENV) && docker run --rm -v "$$(pwd)/docs:/docs" $(ALPINE_IMAGE) chown -R $${USER_ID:-1000}:$${GROUP_ID:-1000} /docs/api/node-backend
	@echo -e "\033[0;32mBackend documentation generated in docs/api/node-backend/\033[0m"

docs-node-frontend: ## Generate Node.js Frontend documentation using TypeDoc
	@if [ -z "$$(find src/node/frontend -name '*.ts' -o -name '*.tsx' 2>/dev/null | grep -v node_modules | head -1)" ]; then \
		echo -e "\033[0;33mNo TypeScript files in src/node/frontend/ - skipping frontend docs\033[0m"; \
	else \
		echo -e "\033[0;33mGenerating Node.js Frontend documentation...\033[0m"; \
		mkdir -p docs/api/node-frontend build/tmp && chmod 777 docs/api docs/api/node-frontend 2>/dev/null || true; \
		docker run --rm -v "$$(pwd)/docs:/docs" $(ALPINE_IMAGE) sh -c 'rm -rf /docs/api/node-frontend/* /docs/api/node-frontend/.[!.]* 2>/dev/null || true'; \
		touch build/tmp/.docs-node-frontend.stamp; \
		$(DC_RUN) run --rm node pnpm run docs:frontend; \
		if [ ! docs/api/node-frontend/index.html -nt build/tmp/.docs-node-frontend.stamp ]; then \
			echo -e "\033[0;31mError: docs/api/node-frontend/index.html was not regenerated\033[0m"; \
			exit 1; \
		fi; \
		echo -e "\033[0;33mSetting dynamic title...\033[0m"; \
		$(DC_RUN) run --rm node sh -c '\
			set -e; \
			PROJECT_NAME=$$(node -e "console.log(require(\"/app/package.json\").name.split(\"/\").pop().replace(/^./, c => c.toUpperCase()))"); \
			PROJECT_VERSION=$$(node -e "console.log(require(\"/app/package.json\").version || \"0.0.0\")"); \
			find /app/docs/api/node-frontend -name "*.html" -print0 | xargs -0 sed -i "s|<title>Node Frontend - v$$PROJECT_VERSION</title>|<title>$$PROJECT_NAME - Frontend - v$$PROJECT_VERSION</title>|g"; \
			find /app/docs/api/node-frontend -name "*.html" -print0 | xargs -0 sed -i "s|>Node Frontend - v$$PROJECT_VERSION</a>|>$$PROJECT_NAME - Frontend</a>|g"'; \
		$(LOAD_ENV) && docker run --rm -v "$$(pwd)/docs:/docs" $(ALPINE_IMAGE) chown -R $${USER_ID:-1000}:$${GROUP_ID:-1000} /docs/api/node-frontend; \
		echo -e "\033[0;32mFrontend documentation generated in docs/api/node-frontend/\033[0m"; \
	fi

docs-clean: ## Remove generated documentation
	@echo -e "\033[0;33mCleaning documentation...\033[0m"
	@rm -rf docs/api/ .phpdoc/ tools/
	@echo -e "\033[0;32mDocumentation cleaned!\033[0m"

##@ SSL/TLS

ssl-ca: ## Generate internal Certificate Authority
	@echo -e "\033[0;33mGenerating internal CA...\033[0m"
	@mkdir -p docker/certs/ca
	@chmod +x docker/certs/generate-ca.sh
	@docker/certs/generate-ca.sh

ssl-internal: ssl-ca ## Generate internal service certificates (signed by CA)
	@echo -e "\033[0;33mGenerating internal certificates...\033[0m"
	@mkdir -p docker/certs/{nginx,internal}
	@chmod +x docker/certs/generate-internal.sh
	@docker/certs/generate-internal.sh

# NOTE: the help delegations in ssl-trust-ca/ssl-untrust-ca use a literal
# `make` on purpose: GNU make executes recipe lines that reference the
# recursive-make variable even under `make -n`, which would run the sudo
# branches during a dry run. A literal `make` keeps the dry run inert.
ssl-trust-ca: ## Trust internal CA in your system (auto-detects OS, requires sudo)
	@if [ ! -f docker/certs/ca/ca.crt ]; then \
		echo -e "\033[0;31mError: CA certificate not found at docker/certs/ca/ca.crt\033[0m"; \
		echo -e "\033[0;34mRun 'make ssl-internal' first to generate certificates.\033[0m"; \
		exit 1; \
	fi
	@OS_TYPE=""; \
	DISTRO=""; \
	if [ "$$(uname)" = "Darwin" ]; then \
		OS_TYPE="macos"; \
	elif [ "$$(uname)" = "Linux" ]; then \
		OS_TYPE="linux"; \
		if [ -f /etc/os-release ]; then \
			. /etc/os-release; \
			case "$$ID" in \
				arch|manjaro|endeavouros) DISTRO="arch";; \
				debian|ubuntu|linuxmint|pop) DISTRO="debian";; \
				fedora|rhel|centos|rocky|alma) DISTRO="rhel";; \
				opensuse*|sles) DISTRO="suse";; \
				*) \
					if [ -f /etc/debian_version ]; then DISTRO="debian"; \
					elif [ -f /etc/redhat-release ]; then DISTRO="rhel"; \
					elif command -v trust >/dev/null 2>&1; then DISTRO="arch"; \
					fi;; \
			esac; \
		fi; \
	fi; \
	if [ -z "$$OS_TYPE" ] || [ "$$OS_TYPE" = "linux" -a -z "$$DISTRO" ]; then \
		echo -e "\033[0;33mCould not detect OS automatically.\033[0m"; \
		make --silent ssl-trust-ca-help; \
		exit 0; \
	fi; \
	echo -e "\033[0;33mTrusting internal CA certificate...\033[0m"; \
	echo -e "\033[0;34mDetected: $$OS_TYPE $${DISTRO:+($$DISTRO)}\033[0m"; \
	case "$$OS_TYPE-$$DISTRO" in \
		macos-) \
			echo -e "\033[0;34mAdding CA to macOS System Keychain (requires sudo)...\033[0m"; \
			sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain docker/certs/ca/ca.crt && \
			echo -e "\033[0;32m✓ CA trusted in macOS System Keychain\033[0m";; \
		linux-arch) \
			echo -e "\033[0;34mAdding CA via p11-kit trust anchor (requires sudo)...\033[0m"; \
			sudo trust anchor --store docker/certs/ca/ca.crt && \
			echo -e "\033[0;32m✓ CA trusted via trust anchor\033[0m";; \
		linux-debian) \
			echo -e "\033[0;34mAdding CA to Debian/Ubuntu trust store (requires sudo)...\033[0m"; \
			sudo cp docker/certs/ca/ca.crt /usr/local/share/ca-certificates/zappzarapp-ca.crt && \
			sudo update-ca-certificates && \
			echo -e "\033[0;32m✓ CA trusted in system store\033[0m";; \
		linux-rhel) \
			echo -e "\033[0;34mAdding CA to RHEL/Fedora trust store (requires sudo)...\033[0m"; \
			sudo cp docker/certs/ca/ca.crt /etc/pki/ca-trust/source/anchors/zappzarapp-ca.crt && \
			sudo update-ca-trust && \
			echo -e "\033[0;32m✓ CA trusted in system store\033[0m";; \
		linux-suse) \
			echo -e "\033[0;34mAdding CA to openSUSE trust store (requires sudo)...\033[0m"; \
			sudo cp docker/certs/ca/ca.crt /etc/pki/trust/anchors/zappzarapp-ca.crt && \
			sudo update-ca-certificates && \
			echo -e "\033[0;32m✓ CA trusted in system store\033[0m";; \
		*) \
			echo -e "\033[0;33mUnsupported OS/distro combination.\033[0m"; \
			make --silent ssl-trust-ca-help; \
			exit 0;; \
	esac
	@echo ""
	@echo -e "\033[0;34mNote: Browser trust may require additional steps:\033[0m"
	@echo -e "\033[0;34m  - Firefox: Import docker/certs/ca/ca.crt in Settings > Privacy > Certificates\033[0m"
	@echo -e "\033[0;34m  - Chrome/Edge: Usually trusts system store after restart\033[0m"

ssl-trust-ca-help: ## Show manual instructions to trust internal CA
	@echo "============================================================================"
	@echo "To trust the internal CA in your system:"
	@echo "============================================================================"
	@echo ""
	@echo "Linux (Arch/Manjaro):"
	@echo "  sudo trust anchor --store docker/certs/ca/ca.crt"
	@echo ""
	@echo "Linux (Debian/Ubuntu):"
	@echo "  sudo cp docker/certs/ca/ca.crt /usr/local/share/ca-certificates/zappzarapp-ca.crt"
	@echo "  sudo update-ca-certificates"
	@echo ""
	@echo "Linux (RHEL/Fedora):"
	@echo "  sudo cp docker/certs/ca/ca.crt /etc/pki/ca-trust/source/anchors/zappzarapp-ca.crt"
	@echo "  sudo update-ca-trust"
	@echo ""
	@echo "Linux (openSUSE):"
	@echo "  sudo cp docker/certs/ca/ca.crt /etc/pki/trust/anchors/zappzarapp-ca.crt"
	@echo "  sudo update-ca-certificates"
	@echo ""
	@echo "macOS:"
	@echo "  sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain docker/certs/ca/ca.crt"
	@echo ""
	@echo "Windows:"
	@echo "  Import docker/certs/ca/ca.crt into 'Trusted Root Certification Authorities'"
	@echo "============================================================================"

# Works even after the CA files were deleted (reset-full/ssl-clean): the
# Debian/RHEL/SUSE stores are addressed by the fixed installed file name,
# macOS by the certificate CN, Arch by the p11-kit label. macOS/Arch loop
# until every accumulated copy is gone — repeated trust/reset cycles leave
# orphaned anchors behind (observed live: three on one host); Arch falls
# back to file-based removal if the label URI is not supported.
ssl-untrust-ca: ## Remove internal CA from your system trust store (auto-detects OS, requires sudo)
	@OS_TYPE=""; \
	DISTRO=""; \
	if [ "$$(uname)" = "Darwin" ]; then \
		OS_TYPE="macos"; \
	elif [ "$$(uname)" = "Linux" ]; then \
		OS_TYPE="linux"; \
		if [ -f /etc/os-release ]; then \
			. /etc/os-release; \
			case "$$ID" in \
				arch|manjaro|endeavouros) DISTRO="arch";; \
				debian|ubuntu|linuxmint|pop) DISTRO="debian";; \
				fedora|rhel|centos|rocky|alma) DISTRO="rhel";; \
				opensuse*|sles) DISTRO="suse";; \
				*) \
					if [ -f /etc/debian_version ]; then DISTRO="debian"; \
					elif [ -f /etc/redhat-release ]; then DISTRO="rhel"; \
					elif command -v trust >/dev/null 2>&1; then DISTRO="arch"; \
					fi;; \
			esac; \
		fi; \
	fi; \
	if [ -z "$$OS_TYPE" ] || [ "$$OS_TYPE" = "linux" -a -z "$$DISTRO" ]; then \
		echo -e "\033[0;33mCould not detect OS automatically.\033[0m"; \
		make --silent ssl-untrust-ca-help; \
		exit 0; \
	fi; \
	echo -e "\033[0;33mRemoving internal CA from the system trust store...\033[0m"; \
	echo -e "\033[0;34mDetected: $$OS_TYPE $${DISTRO:+($$DISTRO)}\033[0m"; \
	case "$$OS_TYPE-$$DISTRO" in \
		macos-) \
			if ! security find-certificate -c "Zappzarapp Internal CA" /Library/Keychains/System.keychain >/dev/null 2>&1; then \
				echo -e "\033[0;32m✓ No Zappzarapp CA in the System Keychain — nothing to remove\033[0m"; \
			else \
				echo -e "\033[0;34mRemoving CA from macOS System Keychain (requires sudo)...\033[0m"; \
				N=0; \
				while [ $$N -lt 10 ] && security find-certificate -c "Zappzarapp Internal CA" /Library/Keychains/System.keychain >/dev/null 2>&1; do \
					sudo security delete-certificate -c "Zappzarapp Internal CA" /Library/Keychains/System.keychain || break; \
					N=$$((N+1)); \
				done; \
				if security find-certificate -c "Zappzarapp Internal CA" /Library/Keychains/System.keychain >/dev/null 2>&1; then \
					echo -e "\033[0;31m⚠ Some CA copies could not be removed\033[0m"; exit 1; \
				fi; \
				echo -e "\033[0;32m✓ CA removed from macOS System Keychain ($$N instance(s))\033[0m"; \
			fi;; \
		linux-arch) \
			BEFORE=$$(trust list 2>/dev/null | grep -c "label: Zappzarapp Internal CA" || true); \
			if [ "$$BEFORE" -eq 0 ]; then \
				echo -e "\033[0;32m✓ No Zappzarapp CA in the trust store — nothing to remove\033[0m"; \
			else \
				echo -e "\033[0;34mRemoving CA via p11-kit trust anchor (requires sudo)...\033[0m"; \
				N=0; \
				while [ $$N -lt 10 ] && trust list 2>/dev/null | grep -q "label: Zappzarapp Internal CA"; do \
					sudo trust anchor --remove "pkcs11:object=Zappzarapp%20Internal%20CA" 2>/dev/null || break; \
					N=$$((N+1)); \
				done; \
				if trust list 2>/dev/null | grep -q "label: Zappzarapp Internal CA"; then \
					if [ -f docker/certs/ca/ca.crt ]; then sudo trust anchor --remove docker/certs/ca/ca.crt || true; fi; \
				fi; \
				REMAINING=$$(trust list 2>/dev/null | grep -c "label: Zappzarapp Internal CA" || true); \
				if [ "$$REMAINING" -gt 0 ]; then \
					echo -e "\033[0;31m⚠ $$REMAINING trust anchor(s) could not be removed (see 'trust list')\033[0m"; exit 1; \
				fi; \
				echo -e "\033[0;32m✓ CA removed from trust anchors ($$((BEFORE-REMAINING)) instance(s))\033[0m"; \
			fi;; \
		linux-debian) \
			echo -e "\033[0;34mRemoving CA from Debian/Ubuntu trust store (requires sudo)...\033[0m"; \
			sudo rm -f /usr/local/share/ca-certificates/zappzarapp-ca.crt && \
			sudo update-ca-certificates --fresh >/dev/null && \
			echo -e "\033[0;32m✓ CA removed from system store\033[0m";; \
		linux-rhel) \
			echo -e "\033[0;34mRemoving CA from RHEL/Fedora trust store (requires sudo)...\033[0m"; \
			sudo rm -f /etc/pki/ca-trust/source/anchors/zappzarapp-ca.crt && \
			sudo update-ca-trust && \
			echo -e "\033[0;32m✓ CA removed from system store\033[0m";; \
		linux-suse) \
			echo -e "\033[0;34mRemoving CA from openSUSE trust store (requires sudo)...\033[0m"; \
			sudo rm -f /etc/pki/trust/anchors/zappzarapp-ca.crt && \
			sudo update-ca-certificates && \
			echo -e "\033[0;32m✓ CA removed from system store\033[0m";; \
		*) \
			echo -e "\033[0;33mUnsupported OS/distro combination.\033[0m"; \
			make --silent ssl-untrust-ca-help; \
			exit 0;; \
	esac
	@echo ""
	@echo -e "\033[0;34mNote: if you imported the CA into Firefox manually, remove it there\033[0m"
	@echo -e "\033[0;34mtoo: Settings > Privacy > Certificates > 'Zappzarapp Internal CA'.\033[0m"

ssl-untrust-ca-help: ## Show manual instructions to remove the internal CA from the trust store
	@echo "============================================================================"
	@echo "To remove the internal CA from your system trust store:"
	@echo "============================================================================"
	@echo ""
	@echo "Linux (Arch/Manjaro):"
	@echo "  sudo trust anchor --remove docker/certs/ca/ca.crt"
	@echo "  # or, if the file is already gone:"
	@echo "  sudo trust anchor --remove \"pkcs11:object=Zappzarapp%20Internal%20CA\""
	@echo ""
	@echo "Linux (Debian/Ubuntu):"
	@echo "  sudo rm /usr/local/share/ca-certificates/zappzarapp-ca.crt"
	@echo "  sudo update-ca-certificates --fresh"
	@echo ""
	@echo "Linux (RHEL/Fedora):"
	@echo "  sudo rm /etc/pki/ca-trust/source/anchors/zappzarapp-ca.crt"
	@echo "  sudo update-ca-trust"
	@echo ""
	@echo "Linux (openSUSE):"
	@echo "  sudo rm /etc/pki/trust/anchors/zappzarapp-ca.crt"
	@echo "  sudo update-ca-certificates"
	@echo ""
	@echo "macOS:"
	@echo "  sudo security delete-certificate -c \"Zappzarapp Internal CA\" /Library/Keychains/System.keychain"
	@echo ""
	@echo "Windows:"
	@echo "  Remove 'Zappzarapp Internal CA' from 'Trusted Root Certification Authorities'"
	@echo "============================================================================"

ssl-letsencrypt: ## Setup Let's Encrypt SSL certificate (production)
	@echo -e "\033[0;33mSetting up Let's Encrypt certificate...\033[0m"
	@if [ ! -f docker/certs/setup-letsencrypt.sh ]; then \
		echo -e "\033[0;31mError: setup-letsencrypt.sh not found!\033[0m"; \
		exit 1; \
	fi
	@# Check for DOMAIN in .env or prompt for it
	@DOMAIN=""; \
	if [ -f .env ] && grep -q "^DOMAIN=" .env; then \
		DOMAIN=$$(grep "^DOMAIN=" .env | cut -d'=' -f2); \
		echo -e "\033[0;32m✓ Using domain from .env: $$DOMAIN\033[0m"; \
	fi; \
	if [ -z "$$DOMAIN" ]; then \
		read -p "Enter your domain (e.g., example.com): " DOMAIN; \
		if [ -n "$$DOMAIN" ] && [ -f .env ]; then \
			if grep -q "^#DOMAIN=" .env; then \
				sed -i.bak "s|^#DOMAIN=.*|DOMAIN=$$DOMAIN|" .env && rm -f .env.bak; \
			elif grep -q "^DOMAIN=" .env; then \
				sed -i.bak "s|^DOMAIN=.*|DOMAIN=$$DOMAIN|" .env && rm -f .env.bak; \
			else \
				echo "DOMAIN=$$DOMAIN" >> .env; \
			fi; \
			echo -e "\033[0;32m✓ DOMAIN saved to .env\033[0m"; \
		fi; \
	fi; \
	read -p "Enter your email (for renewal notifications): " EMAIL; \
	bash docker/certs/setup-letsencrypt.sh $$DOMAIN $$EMAIL
	@echo -e "\033[0;34mTo enable HTTPS (Production):\033[0m"
	@echo -e "\033[0;34m  1. Run: make ssl-prod-enable\033[0m"
	@echo -e "\033[0;34m  2. Deploy: ZAPPZARAPP_ENV=production make build && make up\033[0m"

ssl-renew: ## Renew Let's Encrypt certificate and reload all SSL services
	@echo -e "\033[0;33mRenewing Let's Encrypt certificate...\033[0m"
	@CERT_CHANGED=false; \
	CERT_BEFORE=""; \
	if [ -f docker/certs/nginx/cert.crt ]; then \
		CERT_BEFORE=$$(openssl x509 -in docker/certs/nginx/cert.crt -noout -fingerprint 2>/dev/null || echo ""); \
	fi; \
	if command -v certbot >/dev/null 2>&1; then \
		sudo certbot renew --quiet; \
	else \
		docker run --rm --name certbot \
			-v $$(pwd)/docker/certs/letsencrypt:/etc/letsencrypt \
			-v $$(pwd)/public:/var/www/html \
			certbot/certbot renew --quiet; \
	fi; \
	CERT_AFTER=""; \
	if [ -f docker/certs/nginx/cert.crt ]; then \
		CERT_AFTER=$$(openssl x509 -in docker/certs/nginx/cert.crt -noout -fingerprint 2>/dev/null || echo ""); \
	fi; \
	if [ "$$CERT_BEFORE" != "$$CERT_AFTER" ] && [ -n "$$CERT_AFTER" ]; then \
		CERT_CHANGED=true; \
	fi; \
	if [ "$$CERT_CHANGED" = "true" ]; then \
		echo -e "\033[0;32m✓ Certificate renewed! Reloading services...\033[0m"; \
		$(MAKE) --silent ssl-reload-services; \
	else \
		echo -e "\033[0;33mNo certificates were renewed (not due yet).\033[0m"; \
	fi

ssl-reload-services: ## Reload all SSL-dependent services after certificate renewal
	@echo -e "\033[0;33mReloading SSL-dependent services...\033[0m"
	@# Nginx: graceful reload (reads certs directly from volume)
	@if docker compose ps nginx --status running -q 2>/dev/null | grep -q .; then \
		echo -e "  Reloading nginx (graceful)..."; \
		docker compose exec -T nginx nginx -s reload 2>/dev/null && \
			echo -e "  \033[0;32m✓ nginx reloaded\033[0m" || \
			echo -e "  \033[0;33m⚠ nginx not running\033[0m"; \
	fi
	@# PostgreSQL: restart required (dev mode copies certs via entrypoint)
	@if docker compose ps postgres --status running -q 2>/dev/null | grep -q .; then \
		echo -e "  Restarting postgres (entrypoint re-copies certs)..."; \
		$(DC) restart postgres 2>/dev/null && \
			echo -e "  \033[0;32m✓ postgres restarted\033[0m" || \
			echo -e "  \033[0;33m⚠ postgres not running\033[0m"; \
	fi
	@# MariaDB: restart required (entrypoint re-copies certs, no graceful SSL reload)
	@if docker compose ps mariadb --status running -q 2>/dev/null | grep -q .; then \
		echo -e "  Restarting mariadb (entrypoint re-copies certs)..."; \
		$(DC) restart mariadb 2>/dev/null && \
			echo -e "  \033[0;32m✓ mariadb restarted\033[0m" || \
			echo -e "  \033[0;33m⚠ mariadb not running\033[0m"; \
	fi
	@# Redis: restart required (no graceful TLS reload)
	@if docker compose ps redis --status running -q 2>/dev/null | grep -q .; then \
		echo -e "  Restarting redis (no graceful TLS reload)..."; \
		$(DC) restart redis 2>/dev/null && \
			echo -e "  \033[0;32m✓ redis restarted\033[0m" || \
			echo -e "  \033[0;33m⚠ redis not running\033[0m"; \
	fi
	@echo -e "\033[0;32m✓ All SSL services reloaded!\033[0m"

ssl-info: ## Show SSL certificate information
	@echo -e "\033[0;33mSSL Certificate Information:\033[0m"
	@echo ""
	@# CA certificate
	@if [ -f docker/certs/ca/ca.crt ]; then \
		echo -e "\033[0;36m=== Internal CA ===\033[0m"; \
		openssl x509 -in docker/certs/ca/ca.crt -noout -subject -dates | sed 's/^/  /'; \
		echo ""; \
	fi
	@# Nginx certificate
	@if [ -f docker/certs/nginx/cert.crt ]; then \
		echo -e "\033[0;36m=== Nginx Certificate ===\033[0m"; \
		openssl x509 -in docker/certs/nginx/cert.crt -noout -subject -issuer -dates | sed 's/^/  /'; \
		echo ""; \
	fi
	@# Internal certificate
	@if [ -f docker/certs/internal/cert.crt ]; then \
		echo -e "\033[0;36m=== Internal Services Certificate ===\033[0m"; \
		openssl x509 -in docker/certs/internal/cert.crt -noout -subject -issuer -dates | sed 's/^/  /'; \
		echo ""; \
	fi
	@# No certificates found
	@if [ ! -f docker/certs/ca/ca.crt ] && [ ! -f docker/certs/nginx/cert.crt ]; then \
		echo -e "\033[0;31mNo certificates found. Generate with:\033[0m"; \
		echo -e "\033[0;34m  make ssl-internal   (development - CA-signed)\033[0m"; \
		echo -e "\033[0;34m  make ssl-letsencrypt (production)\033[0m"; \
	fi

ssl-prod-enable: ## Enable SSL/TLS for production (generates ssl-production.conf from template)
	@echo -e "\033[0;33mEnabling SSL/TLS for production...\033[0m"
	@if [ ! -f docker/nginx/conf.d/ssl-production.conf.template ]; then \
		echo -e "\033[0;31mError: ssl-production.conf.template not found!\033[0m"; \
		exit 1; \
	fi
	@if [ -f docker/nginx/conf.d/ssl-production.conf ]; then \
		echo -e "\033[0;33mssl-production.conf already exists.\033[0m"; \
		read -p "Overwrite? (y/N): " OVERWRITE; \
		if [ "$$OVERWRITE" != "y" ] && [ "$$OVERWRITE" != "Y" ]; then \
			echo -e "\033[0;34mOperation cancelled.\033[0m"; \
			exit 0; \
		fi; \
	fi
	@# Check for DOMAIN in .env or prompt for it
	@DOMAIN=""; \
	if [ -f .env ] && grep -q "^DOMAIN=" .env; then \
		DOMAIN=$$(grep "^DOMAIN=" .env | cut -d'=' -f2); \
		echo -e "\033[0;32m✓ Using domain from .env: $$DOMAIN\033[0m"; \
	fi; \
	if [ -z "$$DOMAIN" ]; then \
		read -p "Enter your domain (e.g., example.com): " DOMAIN; \
		if [ -n "$$DOMAIN" ]; then \
			if [ -f .env ]; then \
				if grep -q "^#DOMAIN=" .env; then \
					sed -i.bak "s|^#DOMAIN=.*|DOMAIN=$$DOMAIN|" .env && rm -f .env.bak; \
				elif grep -q "^DOMAIN=" .env; then \
					sed -i.bak "s|^DOMAIN=.*|DOMAIN=$$DOMAIN|" .env && rm -f .env.bak; \
				else \
					echo "DOMAIN=$$DOMAIN" >> .env; \
				fi; \
				echo -e "\033[0;32m✓ DOMAIN saved to .env\033[0m"; \
			else \
				echo -e "\033[0;33m⚠️  No .env file found. Run 'make init' first.\033[0m"; \
			fi; \
		else \
			echo -e "\033[0;31mError: Domain is required!\033[0m"; \
			exit 1; \
		fi; \
	fi; \
	NGINX_SSL_PORT=$${NGINX_SSL_PORT:-8443}; \
	if [ -f .env ] && grep -q "^NGINX_SSL_PORT=" .env; then \
		NGINX_SSL_PORT=$$(grep "^NGINX_SSL_PORT=" .env | cut -d'=' -f2); \
	fi; \
	export DOMAIN NGINX_SSL_PORT; \
	envsubst '$$DOMAIN $$NGINX_SSL_PORT' < docker/nginx/conf.d/ssl-production.conf.template > docker/nginx/conf.d/ssl-production.conf
	@echo -e "\033[0;32m✓ ssl-production.conf created with your domain!\033[0m"
	@echo ""
	@echo -e "\033[0;34mNext steps:\033[0m"
	@echo -e "\033[0;34m  1. Generate SSL certificate:\033[0m"
	@echo -e "\033[0;34m     For Let's Encrypt: make ssl-letsencrypt\033[0m"
	@echo -e "\033[0;34m     For internal CA:   make ssl-internal\033[0m"
	@echo ""
	@echo -e "\033[0;34m  2. Deploy:\033[0m"
	@echo -e "\033[0;34m     ZAPPZARAPP_ENV=production make build && make up\033[0m"
	@echo ""
	@echo -e "\033[0;34m  3. Setup auto-renewal (cron):\033[0m"
	@echo -e "\033[0;34m     0 0 * * * cd $(PWD) && make ssl-renew >> /var/log/ssl-renew.log 2>&1\033[0m"

ssl-clean: ## Remove all SSL certificates (WARNING: Destructive!)
	@echo -e "\033[0;31m⚠️  WARNING: This will delete all SSL certificates!\033[0m"
	@read -p "Type 'YES' to confirm: " CONFIRM; \
	if [ "$$CONFIRM" = "YES" ]; then \
		rm -rf docker/certs/ca docker/certs/nginx docker/certs/internal; \
		rm -rf docker/certs/*.srl docker/certs/letsencrypt; \
		echo -e "\033[0;32mSSL certificates removed!\033[0m"; \
		echo -e "\033[0;36m  Hint: if you ran 'make ssl-trust-ca', the system trust store still\033[0m"; \
		echo -e "\033[0;36m  trusts the removed CA — run 'make ssl-untrust-ca' to clean it up.\033[0m"; \
	else \
		echo -e "\033[0;34mOperation cancelled.\033[0m"; \
	fi

# Catch-all target for service names passed to up/down/restart/logs/build
# This prevents Make from trying to build service names as targets
# Example: 'make up php nginx' - php and nginx are caught here
# If called directly without a valid parent target, throw an error
TARGETS_WITH_ARGS := build build-no-cache down down-all k8s-logs logs logs-save prune restart up
EMPTY :=
SPACE := $(EMPTY) $(EMPTY)
TARGETS_PATTERN := $(subst $(SPACE),|,$(TARGETS_WITH_ARGS))

%:
	@if ! echo " $(MAKECMDGOALS) " | grep -qE " ($(TARGETS_PATTERN)) "; then \
		echo -e "\033[0;31mError: Unknown target '$@'\033[0m"; \
		echo -e "\033[0;90mRun 'make help' for available targets.\033[0m"; \
		exit 1; \
	fi
