SHELL := bash
.SHELLFLAGS := -c

# Docker Compose with plain progress output to avoid terminal corruption
DC := docker compose --progress=plain

# Run commands always use development context with all required profiles
# This ensures make pnpm/composer/etc. work regardless of .env settings
DC_RUN := COMPOSE_PROFILES=php,node,node-backend,dev-tools $(DC)

# Alpine image for utility operations (ownership fixes, cleanup)
ALPINE_IMAGE ?= alpine:3.21

# Load environment files in correct order:
# 1. .env (team defaults)
# 2. .env.production (if ENV=production)
# 3. .env.local (local overrides)
define LOAD_ENV
. ./.env; \
[ "$${ENV:-development}" = "production" ] && [ -f .env.production ] && . ./.env.production || true; \
[ -f .env.local ] && . ./.env.local || true
endef

.PHONY: $(shell awk '/^[a-zA-Z_-]+:.*?## / { print $$1 }' $(MAKEFILE_LIST) | sed 's/://')

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
		show && /^[a-zA-Z_-]+:.*?## / { \
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
	@. ./.env && \
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
	@echo -e "\033[0;32mGit hooks installed successfully in .git/hooks/!\033[0m"

init: ## Initialize project (create .env.local for local overrides) - Run this first!
	@echo -e "\033[0;33mInitializing local configuration...\033[0m"
	@if [ ! -f .env.local ]; then \
		echo "# Local Environment Overrides" > .env.local; \
		echo "# This file is gitignored - safe for personal settings and secrets" >> .env.local; \
		echo "#" >> .env.local; \
		echo "# Adjust USER_ID/GROUP_ID to match your host user (run: id -u && id -g)" >> .env.local; \
		echo "USER_ID=$$(id -u)" >> .env.local; \
		echo "GROUP_ID=$$(id -g)" >> .env.local; \
		echo "" >> .env.local; \
		echo "# Uncomment to override ports if conflicts exist:" >> .env.local; \
		echo "#POSTGRES_PORT=5433" >> .env.local; \
		echo "#MARIADB_PORT=3307" >> .env.local; \
		echo "#NGINX_PORT=8081" >> .env.local; \
		echo "" >> .env.local; \
		echo "# Remote DB SSH tunnel (for IDE access to production):" >> .env.local; \
		echo "#DB_REMOTE_SSH_HOST=bastion.example.com" >> .env.local; \
		echo "#DB_REMOTE_SSH_PORT=22" >> .env.local; \
		echo "#DB_REMOTE_SSH_USER=your-username" >> .env.local; \
		echo "#DB_REMOTE_SSH_KEY=~/.ssh/id_ed25519" >> .env.local; \
		echo -e "\033[0;32m.env.local created with your USER_ID=$$(id -u) and GROUP_ID=$$(id -g)\033[0m"; \
	else \
		echo -e "\033[0;34m.env.local already exists. Skipped.\033[0m"; \
	fi
	@echo -e "\033[0;32mInitialization complete!\033[0m"
	@echo -e "\033[0;34mNext: Run 'make setup' to build containers and install dependencies.\033[0m"

setup: ## Create directories, install dev dependencies and ensure structure
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
	@mkdir -p build/coverage/{php,node} build/vitest-report dist

	# Config & Templates (app bootstrap references these)
	@mkdir -p config templates

	# Lockfiles (must exist as FILES before Docker bind mounts, otherwise Docker creates directories)
	@# Fix bind mount bug: remove if directories, ensure files exist with correct ownership
	@# Note: Use USER_ID/GROUP_ID from .env (not host user) for Docker container compatibility
	@if [ -d composer.lock ] || [ -d pnpm-lock.yaml ] || [ ! -f composer.lock ] || [ ! -f pnpm-lock.yaml ]; then \
		. ./.env && \
		docker run --rm -v "$(PWD):/app" -w /app $(ALPINE_IMAGE) sh -c \
			"rm -rf composer.lock pnpm-lock.yaml && touch composer.lock pnpm-lock.yaml && chown $${USER_ID:-1000}:$${GROUP_ID:-1000} composer.lock pnpm-lock.yaml"; \
	fi

	# SSL/TLS Certificates
	@mkdir -p docker/certs/{ca,nginx,internal}

	# Documentation Output & Tools
	@mkdir -p docs/api/{php,node} tools

	# Storage (Runtime data) - Set permissions
	@. ./.env && mkdir -p $${STORAGE_DIR:-./storage}/{app/{uploads,generated},cache,sessions,logs}
	@# Fix ownership if root-owned (from container operations) - only for default ./storage
	@# Note: Use USER_ID/GROUP_ID from .env (not host user) for Docker container compatibility
	@if [ -d storage ] && find storage -user root 2>/dev/null | grep -q .; then \
		. ./.env && docker run --rm -v "$(PWD)/storage:/storage" $(ALPINE_IMAGE) chown -R $${USER_ID:-1000}:$${GROUP_ID:-1000} /storage; \
	fi
	@. ./.env && chmod 770 $${STORAGE_DIR:-./storage} -R 2>/dev/null || true

	# Backups directory (encrypted backups for all services)
	@mkdir -p backups/{db,seaweedfs,rabbitmq,elasticsearch}
	@# Fix ownership if root-owned (from container backup operations)
	@# Note: Use USER_ID/GROUP_ID from .env (not host user) for Docker container compatibility
	@if find backups -user root 2>/dev/null | grep -q .; then \
		. ./.env && docker run --rm -v "$(PWD)/backups:/backups" $(ALPINE_IMAGE) chown -R $${USER_ID:-1000}:$${GROUP_ID:-1000} /backups; \
	fi
	@chmod 700 backups backups/* 2>/dev/null || true

	# Project AI knowledge directory (decisions, learnings, references)
	@mkdir -p .ai
	@if [ ! -f .ai/LEARNINGS.md ]; then cp .zappzarapp/ai/templates/LEARNINGS.md .ai/; fi
	@if [ ! -f .ai/DECISIONS.md ]; then cp .zappzarapp/ai/templates/DECISIONS.md .ai/; fi
	@if [ ! -f .ai/REFERENCES.md ]; then cp .zappzarapp/ai/templates/REFERENCES.md .ai/; fi

	# Claude context directory (project-specific agent context)
	@mkdir -p .claude/context
	@if [ ! -f .claude/context/project.md ]; then cp .zappzarapp/ai/templates/PROJECT.md .claude/context/project.md; fi

	@echo -e "\033[0;32mProject structure created!\033[0m"

	# README Setup (replace boilerplate README with user template)
	@if grep -q "zappzarapp-boilerplate-readme" README.md 2>/dev/null; then \
		echo -e "\033[0;33mSetting up project README...\033[0m"; \
		cp .zappzarapp/README.template.md README.md; \
		echo -e "\033[0;32mREADME.md replaced with project template.\033[0m"; \
		echo -e "\033[0;34mBoilerplate docs remain in .zappzarapp/docs/\033[0m"; \
	fi

	# CLAUDE.md Setup (swap boilerplate Claude instructions with generic template)
	@if grep -q "zappzarapp-boilerplate-claude" .claude/CLAUDE.md 2>/dev/null; then \
		echo -e "\033[0;33mSetting up Claude configuration...\033[0m"; \
		mv .claude/CLAUDE.md .zappzarapp/CLAUDE.md; \
		cp .zappzarapp/CLAUDE.template.md .claude/CLAUDE.md; \
		echo -e "\033[0;32mCLAUDE.md replaced with generic template.\033[0m"; \
		echo -e "\033[0;34mOriginal boilerplate CLAUDE.md moved to .zappzarapp/CLAUDE.md\033[0m"; \
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

	@echo -e "\033[0;33mBuilding Docker images...\033[0m"
	@$(MAKE) --silent build

	@echo -e "\033[0;33mInstalling dependencies...\033[0m"
	@$(MAKE) --silent composer-install
	@$(MAKE) --silent pnpm-install

	@echo -e "\033[0;33mStarting containers...\033[0m"
	@$(MAKE) --silent up

	# Run database migrations (encryption helpers, audit logs)
	@echo -e "\033[0;33mRunning database migrations...\033[0m"
	-@$(MAKE) --silent db-migrations 2>/dev/null || echo -e "\033[0;34mNo migrations to run or database not ready yet.\033[0m"

	# Generate API documentation
	@echo -e "\033[0;33mGenerating API documentation...\033[0m"
	-@$(MAKE) --silent docs 2>/dev/null || echo -e "\033[0;34mAPI docs generation skipped (tools not yet available).\033[0m"

	# Configure IDE database connections
	@$(MAKE) --silent ide-config

	# Local development tools + Git hooks (optional - requires local composer)
	@echo -e "\033[0;33mSetting up local development tools...\033[0m"
	@if command -v composer >/dev/null 2>&1; then \
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
	fi

	@echo ""
	@echo -e "\033[0;32m╔════════════════════════════════════════════════════════════╗\033[0m"
	@echo -e "\033[0;32m║ Setup complete!                                            ║\033[0m"
	@echo -e "\033[0;32m╠════════════════════════════════════════════════════════════╣\033[0m"
	@echo -e "\033[0;32m║\033[0m Next steps:                                                \033[0;32m║\033[0m"
	@echo -e "\033[0;32m║\033[0m   make up          \033[0;34mStart development environment\033[0m         \033[0;32m║\033[0m"
	@echo -e "\033[0;32m║\033[0m                                                            \033[0;32m║\033[0m"
	@echo -e "\033[0;32m║\033[0m Optional:                                                  \033[0;32m║\033[0m"
	@echo -e "\033[0;32m║\033[0m   make ai-sync     \033[0;34mSync config to AI tools\033[0m               \033[0;32m║\033[0m"
	@echo -e "\033[0;32m╚════════════════════════════════════════════════════════════╝\033[0m"

ide-config: ## Configure all IDE database connections (PHPStorm + VS Code)
	@$(MAKE) --silent ide-config-phpstorm
	@$(MAKE) --silent ide-config-vscode

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

##@ Docker

build: ## Build Docker images (optionally specify service names: make build php nginx)
	@SERVICES="$(filter-out $@ rebuild,$(MAKECMDGOALS))"; \
	if [ -n "$$SERVICES" ]; then \
		echo -e "\033[0;33mBuilding images: $$SERVICES...\033[0m"; \
		if [ -f .env ]; then \
			. ./.env && if [ "$$ENV" = "production" ]; then \
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
			. ./.env && \
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
			if [ "$$ENV" = "production" ]; then \
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
			. ./.env && if [ "$$ENV" = "production" ]; then \
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
			. ./.env && \
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
			if [ "$$ENV" = "production" ]; then \
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
		. ./.env && \
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
		if [ "$$ENV" = "production" ]; then \
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
			. ./.env && if [ "$$ENV" = "production" ]; then \
				$(DC) -f compose.yaml -f compose.production.yaml stop $$SERVICES; \
			else \
				$(DC) stop $$SERVICES; \
			fi; \
		else \
			$(DC) stop $$SERVICES; \
		fi; \
	else \
		echo -e "\033[0;33mStopping containers...\033[0m"; \
		ALL_PROFILES="--profile postgres --profile mariadb --profile php --profile node --profile node-backend --profile redis --profile mercure --profile meilisearch --profile elasticsearch --profile mailpit --profile seaweedfs --profile rabbitmq"; \
		if [ -f .env ]; then \
			. ./.env && if [ "$$ENV" = "production" ]; then \
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
			. ./.env && if [ "$$ENV" = "production" ]; then \
				docker compose -f compose.yaml -f compose.production.yaml logs -f $$SERVICES; \
			else \
				docker compose logs -f $$SERVICES; \
			fi; \
		else \
			docker compose logs -f $$SERVICES; \
		fi; \
	else \
		if [ -f .env ]; then \
			. ./.env && \
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
			if [ "$$ENV" = "production" ]; then \
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
		. ./.env && if [ "$$ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.production.yaml logs -f nginx; \
		else \
			docker compose logs -f nginx; \
		fi; \
	else \
		docker compose logs -f nginx; \
	fi

logs-node: ## Show Node.js logs only
	@if [ -f .env ]; then \
		. ./.env && if [ "$$ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.production.yaml logs -f node; \
		else \
			docker compose logs -f node; \
		fi; \
	else \
		docker compose logs -f node; \
	fi

logs-php: ## Show PHP logs only
	@if [ -f .env ]; then \
		. ./.env && if [ "$$ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.production.yaml logs -f php; \
		else \
			docker compose logs -f php; \
		fi; \
	else \
		docker compose logs -f php; \
	fi

logs-mariadb: ## Show MariaDB logs only
	@if [ -f .env ]; then \
		. ./.env && if [ "$$ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.production.yaml logs -f mariadb; \
		else \
			docker compose logs -f mariadb; \
		fi; \
	else \
		docker compose logs -f mariadb; \
	fi

logs-postgres: ## Show PostgreSQL logs only
	@if [ -f .env ]; then \
		. ./.env && if [ "$$ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.production.yaml logs -f postgres; \
		else \
			docker compose logs -f postgres; \
		fi; \
	else \
		docker compose logs -f postgres; \
	fi

logs-redis: ## Show Redis logs only
	@if [ -f .env ]; then \
		. ./.env && if [ "$$ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.production.yaml logs -f redis; \
		else \
			docker compose logs -f redis; \
		fi; \
	else \
		docker compose logs -f redis; \
	fi

logs-elasticsearch: ## Show Elasticsearch logs only
	@if [ -f .env ]; then \
		. ./.env && if [ "$$ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.production.yaml logs -f elasticsearch; \
		else \
			docker compose logs -f elasticsearch; \
		fi; \
	else \
		docker compose logs -f elasticsearch; \
	fi

logs-mailpit: ## Show Mailpit logs only
	@if [ -f .env ]; then \
		. ./.env && if [ "$$ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.production.yaml logs -f mailpit; \
		else \
			docker compose logs -f mailpit; \
		fi; \
	else \
		docker compose logs -f mailpit; \
	fi

logs-meilisearch: ## Show Meilisearch logs only
	@if [ -f .env ]; then \
		. ./.env && if [ "$$ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.production.yaml logs -f meilisearch; \
		else \
			docker compose logs -f meilisearch; \
		fi; \
	else \
		docker compose logs -f meilisearch; \
	fi

logs-mercure: ## Show Mercure logs only
	@if [ -f .env ]; then \
		. ./.env && if [ "$$ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.production.yaml logs -f mercure; \
		else \
			docker compose logs -f mercure; \
		fi; \
	else \
		docker compose logs -f mercure; \
	fi

logs-rabbitmq: ## Show RabbitMQ logs only
	@if [ -f .env ]; then \
		. ./.env && if [ "$$ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.production.yaml logs -f rabbitmq; \
		else \
			docker compose logs -f rabbitmq; \
		fi; \
	else \
		docker compose logs -f rabbitmq; \
	fi

logs-seaweedfs: ## Show SeaweedFS logs only
	@if [ -f .env ]; then \
		. ./.env && if [ "$$ENV" = "production" ]; then \
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
	if [ "$${ENV:-development}" = "production" ]; then \
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
	echo "ENV: $${ENV:-development}" >> "$$OUTPUT_DIR/metadata.txt"; \
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
	@# Docker bind mounts don't support atomic rename (EBUSY error)
	@# Solution: Run pnpm with lock file in temp location, then copy back
	@$(DC_RUN) run --rm --no-TTY node sh -c ' \
		cp /app/package.json /tmp/package.json && \
		cp /app/pnpm-lock.yaml /tmp/pnpm-lock.yaml 2>/dev/null || true && \
		cd /tmp && pnpm $(CMD) && \
		cat /tmp/package.json > /app/package.json && \
		cat /tmp/pnpm-lock.yaml > /app/pnpm-lock.yaml \
	'

prune: ## Remove untagged/dangling images related to this project
	@echo -e "\033[0;33mPruning dangling images...\033[0m"
	@. ./.env && docker image prune -f --filter "label=com.docker.compose.project=$$COMPOSE_PROJECT_NAME"

restart: ## Restart containers (optionally specify service names: make restart php nginx)
	@SERVICES="$(filter-out $@,$(MAKECMDGOALS))"; \
	if [ -n "$$SERVICES" ]; then \
		echo -e "\033[0;33mRestarting services: $$SERVICES...\033[0m"; \
		if [ -f .env ]; then \
			. ./.env && if [ "$$ENV" = "production" ]; then \
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
	@$(LOAD_ENV); if [ "$${ENV:-development}" = "production" ]; then \
		echo -e "\033[0;33m⚠️  WARNING: Running Compose in production mode.\033[0m"; \
		echo -e "\033[0;33m   For multi-node deployments, use 'make k8s-deploy' (Kubernetes).\033[0m"; \
		echo ""; \
	fi
	@$(LOAD_ENV); if [ "$${ENV:-development}" = "production" ] && \
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
		TAG="$$([ "$${ENV:-development}" = "production" ] && echo "" || echo ":development")"; \
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
		. ./.env && if [ "$$ENV" = "production" ]; then \
			$(DC) -f compose.yaml -f compose.production.yaml start $$SERVICES; \
		else \
			$(DC) start $$SERVICES; \
		fi; \
		echo -e "\033[0;32mServices started!\033[0m"; \
	else \
		. ./.env && \
		if [ "$${ENABLE_MAILPIT:-false}" = "true" ] && [ "$${ENV:-development}" = "production" ]; then \
			echo -e "\033[0;33m⚠️  WARNING: Mailpit is enabled but ENV=production.\033[0m"; \
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
		MISSING=""; \
		PROJECT="$${COMPOSE_PROJECT_NAME:-zappzarapp}"; \
		TAG="$$([ "$${ENV:-development}" = "production" ] && echo "" || echo ":development")"; \
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
		echo -e "\033[0;33mStarting containers in $${ENV:-development} mode...\033[0m"; \
		if [ "$$ENV" = "production" ]; then \
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
	@. ./.env 2>/dev/null && \
	NAMESPACE="$${KUBE_NAMESPACE:-zappzarapp}" && \
	VALUES_FILE="kubernetes/values.yaml" && \
	if [ "$${ENV:-development}" = "production" ]; then \
		VALUES_FILE="kubernetes/values.production.yaml"; \
	fi && \
	helm upgrade --install zappzarapp ./kubernetes \
		--namespace "$$NAMESPACE" \
		--create-namespace \
		-f "$$VALUES_FILE" && \
	echo -e "\033[0;32mDeployed to Kubernetes!\033[0m" && \
	echo -e "\033[0;34mView status with: make k8s-status\033[0m"

k8s-remove: ## Remove deployment from Kubernetes
	@. ./.env 2>/dev/null && \
	NAMESPACE="$${KUBE_NAMESPACE:-zappzarapp}" && \
	echo -e "\033[0;33mRemoving deployment from Kubernetes...\033[0m" && \
	helm uninstall zappzarapp --namespace "$$NAMESPACE" 2>/dev/null || echo "Release not found" && \
	echo -e "\033[0;32mDeployment removed!\033[0m"

k8s-status: ## Show Kubernetes deployment status
	@. ./.env 2>/dev/null && \
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
	. ./.env 2>/dev/null && \
	NAMESPACE="$${KUBE_NAMESPACE:-zappzarapp}" && \
	if [ -n "$$POD" ]; then \
		kubectl logs -f "$$POD" -n "$$NAMESPACE"; \
	else \
		echo -e "\033[0;33mUsage: make k8s-logs <pod-name>\033[0m"; \
		echo "Available pods:"; \
		kubectl get pods -n "$$NAMESPACE" --no-headers -o custom-columns=":metadata.name" 2>/dev/null; \
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
	@. ./.env && \
	if [ -d pnpm-lock.yaml ] || [ ! -f pnpm-lock.yaml ]; then \
		docker run --rm -v "$(PWD):/app" -w /app $(ALPINE_IMAGE) sh -c \
			"rm -rf pnpm-lock.yaml && touch pnpm-lock.yaml && chown $${USER_ID:-1000}:$${GROUP_ID:-1000} pnpm-lock.yaml"; \
	fi
	@# If lockfile exists and is valid AND not in CI mode, use --frozen-lockfile
	@# In CI (compose.ci.yaml), lockfile is not mounted so always use normal install
	@if echo "$${COMPOSE_FILE:-}" | grep -q "compose.ci.yaml"; then \
		echo -e "\033[0;33m  CI mode: lockfile not mounted, generating inside container...\033[0m"; \
		$(DC_RUN) run --rm --no-TTY --user root --entrypoint "" -e CI=true node pnpm install; \
	elif [ -s pnpm-lock.yaml ]; then \
		$(DC_RUN) run --rm --no-TTY --user root --entrypoint "" -e CI=true node pnpm install --frozen-lockfile; \
	else \
		echo -e "\033[0;33m  No valid lockfile found, generating...\033[0m"; \
		$(DC_RUN) run --rm --no-TTY --user root --entrypoint "" -e CI=true node pnpm install; \
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
	@$(DC_RUN) run --rm --no-TTY --user root --entrypoint "" -e CI=true node pnpm install
	@echo -e "\033[0;32mDependencies synced!\033[0m"

pnpm-upgrade: ## Upgrade pnpm package manager to latest version
	@echo -e "\033[0;33mUpgrading pnpm to latest version...\033[0m"
	@CURRENT=$$(grep -o '"pnpm@[^"]*"' package.json | tr -d '"') && \
	$(DC_RUN) run --rm --no-TTY node sh -c ' \
		LATEST=$$(npm view pnpm version) && \
		npm pkg set packageManager=pnpm@$$LATEST \
	' && \
	NEW=$$(grep -o '"pnpm@[^"]*"' package.json | tr -d '"') && \
	echo -e "\033[0;32mpnpm upgraded: $$CURRENT → $$NEW\033[0m"
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
	@. ./.env && . ./docker/scripts/parse-db-url.sh && \
		docker compose exec postgres psql -U "$$DB_USER" -d "$$DB_NAME"

postgres-dump: ## Create database backup (dump.sql)
	@echo -e "\033[0;33mCreating database backup...\033[0m"
	@. ./.env && . ./docker/scripts/parse-db-url.sh && \
		docker compose exec postgres pg_dump -U "$$DB_USER" -d "$$DB_NAME" > dump.sql
	@echo -e "\033[0;32mBackup saved to dump.sql\033[0m"

postgres-restore: ## Restore database from dump.sql
	@if [ ! -f dump.sql ]; then \
		echo -e "\033[0;31mError: dump.sql not found!\033[0m"; \
		exit 1; \
	fi
	@echo -e "\033[0;33mRestoring database from dump.sql...\033[0m"
	@. ./.env && . ./docker/scripts/parse-db-url.sh && \
		docker compose exec -T postgres psql -U "$$DB_USER" -d "$$DB_NAME" < dump.sql
	@echo -e "\033[0;32mDatabase restored!\033[0m"

mariadb-cli: ## Open MariaDB CLI
	@. ./.env && . ./docker/scripts/parse-db-url.sh && \
		docker compose exec mariadb mariadb -u "$$DB_USER" -p"$$DB_PASSWORD" "$$DB_NAME"

mariadb-dump: ## Create MariaDB database backup (dump.sql)
	@echo -e "\033[0;33mCreating MariaDB database backup...\033[0m"
	@. ./.env && . ./docker/scripts/parse-db-url.sh && \
		docker compose exec mariadb mariadb-dump -u "$$DB_USER" -p"$$DB_PASSWORD" "$$DB_NAME" > dump.sql
	@echo -e "\033[0;32mBackup saved to dump.sql\033[0m"

mariadb-restore: ## Restore MariaDB database from dump.sql
	@if [ ! -f dump.sql ]; then \
		echo -e "\033[0;31mError: dump.sql not found!\033[0m"; \
		exit 1; \
	fi
	@echo -e "\033[0;33mRestoring MariaDB database from dump.sql...\033[0m"
	@. ./.env && . ./docker/scripts/parse-db-url.sh && \
		docker compose exec -T mariadb mariadb -u "$$DB_USER" -p"$$DB_PASSWORD" "$$DB_NAME" < dump.sql
	@echo -e "\033[0;32mMariaDB database restored!\033[0m"

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
	@. ./.env && \
	if [ "$${ENABLE_DATABASE:-true}" = "true" ]; then \
		echo -e "\033[0;34m[1/4] Database backup...\033[0m"; \
		bash docker/scripts/backup-databases.sh; \
	else \
		echo -e "\033[0;37m[1/4] Database: skipped (disabled)\033[0m"; \
	fi
	@. ./.env && \
	if [ "$${ENABLE_SEAWEEDFS:-false}" = "true" ]; then \
		echo -e "\033[0;34m[2/4] SeaweedFS backup...\033[0m"; \
		bash docker/scripts/backup-seaweedfs.sh; \
	else \
		echo -e "\033[0;37m[2/4] SeaweedFS: skipped (disabled)\033[0m"; \
	fi
	@. ./.env && \
	if [ "$${ENABLE_RABBITMQ:-false}" = "true" ]; then \
		echo -e "\033[0;34m[3/4] RabbitMQ backup...\033[0m"; \
		bash docker/scripts/backup-rabbitmq.sh; \
	else \
		echo -e "\033[0;37m[3/4] RabbitMQ: skipped (disabled)\033[0m"; \
	fi
	@. ./.env && \
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
	@. ./.env && . ./docker/scripts/parse-db-url.sh && \
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
	@. ./.env && . ./docker/scripts/parse-db-url.sh && \
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
	@if [ -f .env ]; then . ./.env; fi; \
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
	@if [ -f .env ]; then . ./.env; fi; \
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
	@if [ -f .env ]; then . ./.env; fi; \
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
	@if [ -f .env ]; then . ./.env; fi; \
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
	@if [ -f .env ]; then . ./.env; fi; \
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
	@if [ -f .env ]; then . ./.env; fi; \
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
	@if [ -f .env ]; then . ./.env; fi; \
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
	@if [ -f .env ]; then . ./.env; fi; \
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
	@if [ -f .env ]; then . ./.env; fi; \
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
	@if [ -f .env ]; then . ./.env; fi; \
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
	@if [ -f .env ]; then . ./.env; fi; \
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
		. ./.env && if [ "$$ENV" = "production" ]; then \
			$(DC) -f compose.yaml -f compose.production.yaml --profile php --profile node --profile redis --profile postgres --profile mariadb --profile mercure --profile meilisearch --profile elasticsearch --profile mailpit --profile seaweedfs --profile rabbitmq down -v --rmi all && \
			echo -e "\033[0;34mBuilding Node image first (required by PHP and NGINX)...\033[0m" && \
			$(DC) -f compose.yaml -f compose.production.yaml --profile php --profile node --profile redis --profile postgres --profile mariadb --profile mercure --profile meilisearch --profile elasticsearch --profile mailpit --profile seaweedfs --profile rabbitmq build --no-cache node && \
			echo -e "\033[0;34mBuilding remaining images...\033[0m" && \
			$(DC) -f compose.yaml -f compose.production.yaml --profile php --profile node --profile redis --profile postgres --profile mariadb --profile mercure --profile meilisearch --profile elasticsearch --profile mailpit --profile seaweedfs --profile rabbitmq build --no-cache; \
		else \
			$(DC) --profile php --profile node --profile redis --profile postgres --profile mariadb --profile mercure --profile meilisearch --profile elasticsearch --profile mailpit --profile seaweedfs --profile rabbitmq down -v --rmi all && \
			$(DC) --profile php --profile node --profile redis --profile postgres --profile mariadb --profile mercure --profile meilisearch --profile elasticsearch --profile mailpit --profile seaweedfs --profile rabbitmq build --no-cache; \
		fi; \
	else \
		$(DC) --profile php --profile node --profile redis --profile postgres --profile mariadb --profile mercure --profile meilisearch --profile elasticsearch --profile mailpit --profile seaweedfs --profile rabbitmq down -v --rmi all && \
		$(DC) --profile php --profile node --profile redis --profile postgres --profile mariadb --profile mercure --profile meilisearch --profile elasticsearch --profile mailpit --profile seaweedfs --profile rabbitmq build --no-cache; \
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
	@echo -e "\033[0;33m[5/5] Stopping and removing all Docker resources...\033[0m"
	@# Stop and remove all project containers, volumes, images, networks (including orphans)
	@$(DC) --profile php --profile node --profile node-backend --profile redis --profile postgres --profile mariadb --profile mercure --profile meilisearch --profile elasticsearch --profile mailpit --profile seaweedfs --profile rabbitmq down -v --rmi all --remove-orphans 2>/dev/null || true
	@# Remove any remaining containers from this project
	@docker ps -aq --filter "label=com.docker.compose.project=zappzarapp" 2>/dev/null | xargs -r docker rm -f 2>/dev/null || true
	@# Clear all unused Docker resources (images, containers, networks, volumes)
	@docker system prune -af --volumes 2>/dev/null || true
	@# Clear build cache to remove stale layer references
	@docker builder prune -af 2>/dev/null || true

reset: ## Reset Docker and generated files (keeps secrets/certs)
	@echo -e "\033[0;33m╔══════════════════════════════════════════════════════════════════╗\033[0m"
	@echo -e "\033[0;33m║  RESET - Remove Docker resources and generated files             ║\033[0m"
	@echo -e "\033[0;33m╠══════════════════════════════════════════════════════════════════╣\033[0m"
	@echo -e "\033[0;33m║  This will remove:                                               ║\033[0m"
	@echo -e "\033[0;33m║  • All Docker containers, images, volumes, networks              ║\033[0m"
	@echo -e "\033[0;33m║  • All Goss test resources                                       ║\033[0m"
	@echo -e "\033[0;33m║  • storage/ contents (uploads, cache) - if not a mountpoint      ║\033[0m"
	@echo -e "\033[0;33m║  • vendor/, node_modules/, .pnpm-store/ (dependencies)           ║\033[0m"
	@echo -e "\033[0;33m║  • composer.lock, pnpm-lock.yaml (lockfiles)                     ║\033[0m"
	@echo -e "\033[0;33m║  • .env.local (local overrides)                                  ║\033[0m"
	@echo -e "\033[0;33m║  • build/, dist/, public/build/, docs/api/, tools/ (generated)   ║\033[0m"
	@echo -e "\033[0;33m║  • .ai/ (project AI knowledge created by setup)                  ║\033[0m"
	@echo -e "\033[0;33m╠══════════════════════════════════════════════════════════════════╣\033[0m"
	@echo -e "\033[0;33m║  KEEPS: secrets/, docker/certs/, source code                     ║\033[0m"
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
	@echo -e "\033[0;31m║  • README.md, .claude/CLAUDE.md reset to boilerplate             ║\033[0m"
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
	@echo -e "\033[0;33mResetting source code to boilerplate defaults...\033[0m"
	@git checkout -- src/ tests/ resources/ config/ templates/ public/index.php 2>/dev/null || \
		echo -e "\033[0;31m  ⚠ git checkout failed - source code not reset\033[0m"
	@echo -e "\033[0;33mResetting README.md and CLAUDE.md to boilerplate state...\033[0m"
	@git checkout -- README.md 2>/dev/null || \
		echo -e "\033[0;31m  ⚠ README.md not reset (not tracked or modified)\033[0m"
	@git checkout -- .claude/CLAUDE.md 2>/dev/null || \
		echo -e "\033[0;31m  ⚠ CLAUDE.md not reset (not tracked or modified)\033[0m"
	@echo -e "\033[0;32m✓ Full factory reset complete! Project is now in boilerplate state.\033[0m"

# AI Sync configuration (can be overridden via .env or command line)
# Command line: make ai-commands-sync FROM=claude TO=gemini
# Or via .env.local: AI_SYNC_FROM=claude, AI_SYNC_TO=gemini
AI_SYNC_FROM ?= $(or $(FROM),$(shell grep -E '^AI_SYNC_FROM=' .env.local .env 2>/dev/null | head -1 | cut -d= -f2))
AI_SYNC_TO ?= $(or $(TO),$(shell grep -E '^AI_SYNC_TO=' .env.local .env 2>/dev/null | head -1 | cut -d= -f2))
# ai-command-converter only supports Claude ↔ Gemini
AI_COMMAND_TOOLS := claude gemini
# rulesync supports more tools
AI_RULES_TOOLS := claude gemini cursor copilot cline roo

ai-commands-sync: ## Sync AI commands between tools (FROM=claude TO=gemini, or configure in .env)
	@if [ -z "$(AI_SYNC_FROM)" ]; then \
		echo -e "\033[0;33mUsage:\033[0m make ai-commands-sync FROM=claude TO=gemini"; \
		echo -e "       Or set AI_SYNC_FROM (and optionally AI_SYNC_TO) in .env.local"; \
		echo -e "\033[0;34mSupported tools (commands):\033[0m $(AI_COMMAND_TOOLS)"; \
		echo -e "\033[0;36mNote: For rules, use 'make ai-rules-sync' (supports more tools)\033[0m"; \
		exit 1; \
	fi
	@if ! echo "$(AI_COMMAND_TOOLS)" | grep -qw "$(AI_SYNC_FROM)"; then \
		echo -e "\033[0;31mError: Invalid FROM='$(AI_SYNC_FROM)'\033[0m"; \
		echo -e "\033[0;34mSupported tools (commands):\033[0m $(AI_COMMAND_TOOLS)"; \
		exit 1; \
	fi
	@# Validate TO tools (supports comma or space separated list)
	@if [ -n "$(AI_SYNC_TO)" ]; then \
		for tool in $$(echo "$(AI_SYNC_TO)" | tr ',' ' '); do \
			if ! echo "$(AI_COMMAND_TOOLS)" | grep -qw "$$tool"; then \
				echo -e "\033[0;31mError: Invalid TO tool '$$tool'\033[0m"; \
				echo -e "\033[0;34mSupported tools (commands):\033[0m $(AI_COMMAND_TOOLS)"; \
				exit 1; \
			fi; \
		done; \
	fi
	@if [ "$(AI_SYNC_FROM)" != "claude" ]; then \
		echo -e "\033[0;33m⚠️  Warning: Syncing from '$(AI_SYNC_FROM)' instead of 'claude' (project default)\033[0m"; \
		echo -e "\033[0;36m   This project uses Claude as the source of truth for commands.\033[0m"; \
	fi
	@echo -e "\033[0;33mSyncing AI commands...\033[0m"
	@if [ -z "$(AI_SYNC_TO)" ]; then \
		echo -e "\033[0;34m  Source: $(AI_SYNC_FROM) → Target: all other tools\033[0m"; \
		for tool in $(AI_COMMAND_TOOLS); do \
			if [ "$$tool" != "$(AI_SYNC_FROM)" ]; then \
				echo -e "\033[0;36m  Syncing $(AI_SYNC_FROM) → $$tool...\033[0m"; \
				mkdir -p .$$tool/commands; \
				$(DC_RUN) run --rm --no-TTY dev-tools npx ai-command-converter batch \
					.$(AI_SYNC_FROM)/commands .$$tool/commands --format $$tool --force || \
					echo -e "\033[0;31m  ✗ Failed to sync to $$tool\033[0m"; \
			fi; \
		done; \
	else \
		echo -e "\033[0;34m  Source: $(AI_SYNC_FROM) → Target: $(AI_SYNC_TO)\033[0m"; \
		for tool in $$(echo "$(AI_SYNC_TO)" | tr ',' ' '); do \
			if [ "$$tool" != "$(AI_SYNC_FROM)" ]; then \
				echo -e "\033[0;36m  Syncing $(AI_SYNC_FROM) → $$tool...\033[0m"; \
				mkdir -p .$$tool/commands; \
				$(DC_RUN) run --rm --no-TTY dev-tools npx ai-command-converter batch \
					.$(AI_SYNC_FROM)/commands .$$tool/commands --format $$tool --force || \
					echo -e "\033[0;31m  ✗ Failed to sync to $$tool\033[0m"; \
			fi; \
		done; \
	fi
	@echo -e "\033[0;32m✓ AI commands sync complete!\033[0m"

ai-rules-sync: ## Sync AI rules to all configured tools (Claude, Gemini, Cursor, Copilot, etc.)
	@echo -e "\033[0;33mSyncing AI rules...\033[0m"
	@echo -e "\033[0;34mSupported tools (rules):\033[0m $(AI_RULES_TOOLS)"
	@if [ ! -d .rulesync ]; then \
		echo -e "\033[0;33m  Initializing rulesync...\033[0m"; \
		$(DC_RUN) run --rm --no-TTY dev-tools pnpm exec rulesync init; \
	fi
	@echo -e "\033[0;36m  Reading from .rulesync/*.md, generating for all configured targets...\033[0m"
	@$(DC_RUN) run --rm --no-TTY dev-tools pnpm exec rulesync generate
	@echo -e "\033[0;32m✓ AI rules sync complete!\033[0m"

ai-sync: ai-commands-sync ai-rules-sync ## Sync both AI commands and rules

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

renovate: ## Run Renovate dependency scanner
	@echo -e "\033[0;33mRunning Renovate dependency scanner...\033[0m"
	@if [ ! -f .env ]; then echo -e "\033[0;31mError: .env not found. Run 'make init' first.\033[0m"; exit 1; fi
	@. ./.env; \
	RENOVATE_MANAGER=$${RENOVATE_PLATFORM:-filesystem}; \
	RENOVATE_REPO=$${RENOVATE_REPO_SLUG}; \
	RENOVATE_BASEDIR=/app; \
	echo -e "\033[0;34mRenovate Mode: $${RENOVATE_MANAGER}\033[0m"; \
	docker run \
		--rm \
		--volume "$$(pwd):$${RENOVATE_BASEDIR}" \
		-e GITHUB_COM_TOKEN="$${GITHUB_COM_TOKEN}" \
		-e GITLAB_COM_TOKEN="$${GITLAB_COM_TOKEN}" \
		renovate/renovate \
			--manager=$${RENOVATE_MANAGER} \
			--baseDir="$${RENOVATE_BASEDIR}" \
			--baseDirIs="$${RENOVATE_BASEDIR}" \
			--hostRules=[] \
			--dryRun=false \
			$${RENOVATE_REPO}
	@echo -e "\033[0;32mRenovate run completed.\033[0m"

##@ Quality Assurance

analyse: ## Run PHPStan static analysis
	@echo -e "\033[0;33mRunning PHPStan...\033[0m"
	@docker compose exec php composer analyse

phpmd: ## Run PHPMD (PHP Mess Detector) for code quality analysis
	@echo -e "\033[0;33mRunning PHPMD (Mess Detector)...\033[0m"
	@docker compose exec php php -d error_reporting=24575 vendor/bin/phpmd src/php,tests/php text phpmd.xml.dist --exclude '*DatabaseConfig*'

rector-check: ## Run Rector for automated refactoring analysis (dry-run)
	@echo -e "\033[0;33mRunning Rector analysis (dry-run)...\033[0m"
	@docker compose exec php composer rector-check

rector-fix: ## Apply Rector refactorings automatically
	@echo -e "\033[0;33mApplying Rector refactorings...\033[0m"
	@docker compose exec php composer rector-fix

check: cs-check analyse phpmd rector-check prettier-check type-check lint-node test validate lint-md lint-sql lint-docker lint-shell ## Run all checks (CI simulation)
	@echo -e "\033[0;32mAll checks passed!\033[0m"

cs-check: ## Check coding style (dry-run)
	@echo -e "\033[0;33mChecking Coding Style...\033[0m"
	@docker compose exec php composer cs-check

cs-fix: ## Fix coding style automatically (uses composer alias)
	@echo -e "\033[0;33mFixing Coding Style...\033[0m"
	@docker compose exec php composer cs-fix

cs-fix-all: ## Fix coding style aggressively on all files (uses config from .php-cs-fixer.dist.php)
	@echo -e "\033[0;33mFixing Coding Style aggressively on all files...\033[0m"
	@docker compose exec php vendor/bin/php-cs-fixer fix --allow-risky=yes

lint-config: ## Validate YAML configuration files
	@echo -e "\033[0;33mValidating YAML configuration...\033[0m"
	@docker run --rm -v $$(pwd):/app -w /app cytopia/yamllint:latest ./**/*.yaml
	@echo -e "\033[0;32mYAML configuration check completed!\033[0m"

lint-docker: ## Lint Dockerfiles with hadolint
	@echo -e "\033[0;33mLinting Dockerfiles with hadolint...\033[0m"
	@docker run --rm -v $$(pwd)/.hadolint.yaml:/.config/hadolint.yaml -i hadolint/hadolint < docker/php/Dockerfile
	@docker run --rm -v $$(pwd)/.hadolint.yaml:/.config/hadolint.yaml -i hadolint/hadolint < docker/node/Dockerfile
	@docker run --rm -v $$(pwd)/.hadolint.yaml:/.config/hadolint.yaml -i hadolint/hadolint < docker/nginx/Dockerfile
	@docker run --rm -v $$(pwd)/.hadolint.yaml:/.config/hadolint.yaml -i hadolint/hadolint < docker/postgres/Dockerfile
	@docker run --rm -v $$(pwd)/.hadolint.yaml:/.config/hadolint.yaml -i hadolint/hadolint < docker/mariadb/Dockerfile
	@docker run --rm -v $$(pwd)/.hadolint.yaml:/.config/hadolint.yaml -i hadolint/hadolint < docker/redis/Dockerfile
	@docker run --rm -v $$(pwd)/.hadolint.yaml:/.config/hadolint.yaml -i hadolint/hadolint < docker/mercure/Dockerfile
	@docker run --rm -v $$(pwd)/.hadolint.yaml:/.config/hadolint.yaml -i hadolint/hadolint < docker/meilisearch/Dockerfile
	@docker run --rm -v $$(pwd)/.hadolint.yaml:/.config/hadolint.yaml -i hadolint/hadolint < docker/elasticsearch/Dockerfile
	@docker run --rm -v $$(pwd)/.hadolint.yaml:/.config/hadolint.yaml -i hadolint/hadolint < docker/mailpit/Dockerfile
	@docker run --rm -v $$(pwd)/.hadolint.yaml:/.config/hadolint.yaml -i hadolint/hadolint < docker/seaweedfs/Dockerfile
	@docker run --rm -v $$(pwd)/.hadolint.yaml:/.config/hadolint.yaml -i hadolint/hadolint < docker/rabbitmq/Dockerfile
	@echo -e "\033[0;32mDockerfile linting completed!\033[0m"

lint-md: ## Check Markdown files for style issues
	@echo -e "\033[0;33mChecking Markdown files...\033[0m"
	@$(DC) run --rm -T dev-tools pnpm run lint:md
	@echo -e "\033[0;32mMarkdown check completed!\033[0m"

lint-md-fix: ## Fix Markdown style issues automatically
	@echo -e "\033[0;33mFixing Markdown files...\033[0m"
	@$(DC) run --rm -T dev-tools pnpm run lint:md:fix
	@echo -e "\033[0;32mMarkdown files fixed!\033[0m"

lint-sql: ## Check SQL files for style issues (PostgreSQL + MariaDB)
	@echo -e "\033[0;33mChecking SQL files...\033[0m"
	@if [ -d "migrations/postgresql" ] && [ -n "$$(ls -A migrations/postgresql/*.sql 2>/dev/null)" ]; then \
		echo -e "\033[0;90m  Checking PostgreSQL migrations...\033[0m"; \
		docker run --rm -u $$(id -u):$$(id -g) -v "$$(pwd):/sql" sqlfluff/sqlfluff:latest lint \
			--dialect postgres \
			--config /sql/.sqlfluff \
			/sql/migrations/postgresql/ || exit 1; \
	fi
	@if [ -d "migrations/mariadb" ] && [ -n "$$(ls -A migrations/mariadb/*.sql 2>/dev/null)" ]; then \
		echo -e "\033[0;90m  Checking MariaDB migrations...\033[0m"; \
		docker run --rm -u $$(id -u):$$(id -g) -v "$$(pwd):/sql" sqlfluff/sqlfluff:latest lint \
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
		docker run --rm -u $$(id -u):$$(id -g) -v "$$(pwd):/sql" sqlfluff/sqlfluff:latest fix \
			--dialect postgres \
			--config /sql/.sqlfluff \
			--force \
			/sql/migrations/postgresql/; \
	fi
	@if [ -d "migrations/mariadb" ] && [ -n "$$(ls -A migrations/mariadb/*.sql 2>/dev/null)" ]; then \
		echo -e "\033[0;90m  Fixing MariaDB migrations...\033[0m"; \
		docker run --rm -u $$(id -u):$$(id -g) -v "$$(pwd):/sql" sqlfluff/sqlfluff:latest fix \
			--dialect mysql \
			--config /sql/.sqlfluff \
			--ignore parsing,lexing \
			--force \
			/sql/migrations/mariadb/; \
	fi
	@echo -e "\033[0;32mSQL files fixed!\033[0m"

lint-node: ## Run ESLint on TypeScript/JavaScript files
	@echo -e "\033[0;33mRunning ESLint...\033[0m"
	@$(DC) run --rm -T dev-tools pnpm run lint
	@echo -e "\033[0;32mESLint check completed!\033[0m"

lint-node-fix: ## Fix ESLint issues automatically
	@echo -e "\033[0;33mFixing ESLint issues...\033[0m"
	@$(DC) run --rm -T dev-tools pnpm run lint:fix
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

type-check: ## Run TypeScript type checking (static analysis)
	@echo -e "\033[0;33mRunning TypeScript type check...\033[0m"
	@$(DC) run --rm -T dev-tools pnpm run type-check
	@echo -e "\033[0;32mTypeScript check completed!\033[0m"

prettier-check: ## Check code formatting with Prettier
	@echo -e "\033[0;33mChecking code formatting (Prettier)...\033[0m"
	@$(DC) run --rm -T dev-tools pnpm run format:check
	@echo -e "\033[0;32mPrettier check completed!\033[0m"

prettier-fix: ## Fix code formatting with Prettier
	@echo -e "\033[0;33mFixing code formatting (Prettier)...\033[0m"
	@$(DC) run --rm -T dev-tools pnpm run format
	@echo -e "\033[0;32mPrettier formatting applied!\033[0m"

outdated: ## Check for outdated Composer dependencies
	@echo -e "\033[0;33mChecking Composer for outdated packages...\033[0m"
	@docker compose exec php composer outdated
	@echo -e "\033[0;32mOutdated check completed!\033[0m"

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

test-php: ## Run PHPUnit tests
	@echo -e "\033[0;33mRunning PHPUnit tests...\033[0m"
	@if docker compose ps -q php 2>/dev/null | grep -q .; then \
		docker compose exec php composer test; \
	else \
		docker compose run --rm php composer test; \
	fi

test-php-debug: ## Run PHPUnit tests with Xdebug enabled
	@echo -e "\033[0;33mRunning PHPUnit with Xdebug (Step Debugging)...\033[0m"
	@if docker compose ps -q php 2>/dev/null | grep -q .; then \
		docker compose exec php sh -c 'XDEBUG_MODE=develop,debug composer test'; \
	else \
		docker compose run --rm php sh -c 'XDEBUG_MODE=develop,debug composer test'; \
	fi

test-coverage-php: ## Generate PHPUnit coverage report (HTML in build/coverage/php)
	@echo -e "\033[0;33mRunning PHPUnit with coverage report...\033[0m"
	@if docker compose ps -q php 2>/dev/null | grep -q .; then \
		docker compose exec php sh -c 'XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html build/coverage/php --coverage-clover build/coverage/php/clover.xml'; \
	else \
		docker compose run --rm php sh -c 'XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html build/coverage/php --coverage-clover build/coverage/php/clover.xml'; \
	fi
	@echo -e "\033[0;32mPHP coverage report generated in build/coverage/php/index.html!\033[0m"

test-node: ## Run Vitest tests
	@echo -e "\033[0;33mRunning Vitest tests...\033[0m"
	@$(DC) run --rm -T dev-tools pnpm test

test-node-watch: ## Run Vitest in watch mode (interactive)
	@echo -e "\033[0;33mRunning Vitest in watch mode...\033[0m"
	@$(DC) run --rm dev-tools pnpm test:watch

test-coverage-node: ## Generate Vitest coverage report (HTML in build/coverage/node)
	@echo -e "\033[0;33mRunning Vitest with coverage report...\033[0m"
	@$(DC) run --rm -T dev-tools pnpm test:coverage
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

goss-test-build: goss-build ## Run GOSS build-time tests for all images
	@echo -e "\033[0;33mRunning GOSS build-time tests...\033[0m"
	@echo -e "\033[0;34mBuilding PHP with test stage...\033[0m"
	@docker build --target test -f docker/php/Dockerfile . >/dev/null && echo -e "\033[0;32m✓ PHP tests passed\033[0m" || echo -e "\033[0;31m✗ PHP tests failed\033[0m"
	@echo -e "\033[0;34mBuilding nginx with test stage...\033[0m"
	@docker build --target test -f docker/nginx/Dockerfile . >/dev/null && echo -e "\033[0;32m✓ nginx tests passed\033[0m" || echo -e "\033[0;31m✗ nginx tests failed\033[0m"
	@echo -e "\033[0;34mBuilding node-backend with test stage...\033[0m"
	@docker build --target test-api -f docker/node/Dockerfile . >/dev/null && echo -e "\033[0;32m✓ node-backend tests passed\033[0m" || echo -e "\033[0;31m✗ node-backend tests failed\033[0m"
	@echo -e "\033[0;34mBuilding postgres with test stage...\033[0m"
	@docker build --target test -f docker/postgres/Dockerfile . >/dev/null && echo -e "\033[0;32m✓ postgres tests passed\033[0m" || echo -e "\033[0;31m✗ postgres tests failed\033[0m"
	@echo -e "\033[0;34mBuilding redis with test stage...\033[0m"
	@docker build --target test -f docker/redis/Dockerfile . >/dev/null && echo -e "\033[0;32m✓ redis tests passed\033[0m" || echo -e "\033[0;31m✗ redis tests failed\033[0m"
	@echo -e "\033[0;32m✓ All build-time tests completed!\033[0m"

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
	@docker run --rm \
		-v /var/run/docker.sock:/var/run/docker.sock \
		-v "$(PWD):$(PWD)" \
		-w "$(PWD)" \
		--network host \
		--user root \
		-e INTEGRATION_PRESET=$(INTEGRATION_PRESET) \
		-e COMPOSE_FILE=$${COMPOSE_FILE:-} \
		$(BATS_IMAGE) tests/bats/integration/
	@echo -e "\033[0;32m✓ BATS integration tests complete\033[0m"

bats-test-integration-file: ## Run specific BATS integration test file (FILE=lint.bats)
	@if [ -z "$(FILE)" ]; then \
		echo -e "\033[0;31mError: FILE parameter required (e.g., make bats-test-integration-file FILE=lint.bats)\033[0m"; \
		exit 1; \
	fi
	@docker run --rm \
		-v /var/run/docker.sock:/var/run/docker.sock \
		-v "$(PWD):$(PWD)" \
		-w "$(PWD)" \
		--network host \
		--user root \
		-e INTEGRATION_PRESET=$(INTEGRATION_PRESET) \
		-e BATS_ENABLE_DESTRUCTIVE=$(BATS_ENABLE_DESTRUCTIVE) \
		-e COMPOSE_FILE=$${COMPOSE_FILE:-} \
		$(BATS_IMAGE) "tests/bats/integration/$(FILE)"

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
	@docker run --rm \
		-v /var/run/docker.sock:/var/run/docker.sock \
		-v "$(PWD):$(PWD)" \
		-w "$(PWD)" \
		--network host \
		--user root \
		-e BATS_ENABLE_DESTRUCTIVE=true \
		-e COMPOSE_FILE=$${COMPOSE_FILE:-} \
		$(BATS_IMAGE) tests/bats/integration/destructive.bats

validate: ## Validate composer.json/lock and package.json/lock files
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
		cp secrets/elasticsearch_bootstrap_password.example.txt secrets/elasticsearch_bootstrap_password.txt 2>/dev/null || \
		echo "dev-bootstrap-password" > secrets/elasticsearch_bootstrap_password.txt; \
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
	@if [ -f .env.production ] && grep -q "^CORS_ORIGINS=\*" .env.production 2>/dev/null; then \
		echo -e "\033[0;31m  ⚠️  DANGER: .env.production uses CORS_ORIGINS=* (NEVER use in production!)\033[0m"; \
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
		. ./.env && \
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
	@if [ -f .env ]; then . ./.env; fi && \
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
	@docker compose exec php composer docs
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
	@echo -e "\033[0;32mPHP documentation generated in docs/api/php/\033[0m"

docs-node: docs-node-backend docs-node-frontend ## Generate all Node/TypeScript API documentation

docs-node-backend: ## Generate Node.js Backend API documentation using TypeDoc
	@echo -e "\033[0;33mGenerating Node.js Backend API documentation...\033[0m"
	@# Ensure output directory exists with proper permissions (cross-UID in CI)
	@mkdir -p docs/api/node-backend && chmod 777 docs/api docs/api/node-backend 2>/dev/null || true
	@$(DC_RUN) run --rm node pnpm run docs:backend
	@echo -e "\033[0;33mSetting dynamic title...\033[0m"
	@$(DC_RUN) run --rm node sh -c '\
		PROJECT_NAME=$$(node -e "console.log(require(\"/app/package.json\").name.split(\"/\").pop().replace(/^./, c => c.toUpperCase()))"); \
		PROJECT_VERSION=$$(node -e "console.log(require(\"/app/package.json\").version || \"0.0.0\")"); \
		find /app/docs/api/node-backend -name "*.html" -exec sed -i "s|<title>Node Backend API - v$$PROJECT_VERSION</title>|<title>$$PROJECT_NAME - Backend API - v$$PROJECT_VERSION</title>|g" {} \; ; \
		find /app/docs/api/node-backend -name "*.html" -exec sed -i "s|>Node Backend API - v$$PROJECT_VERSION</a>|>$$PROJECT_NAME - Backend API</a>|g" {} \;'
	@echo -e "\033[0;32mBackend documentation generated in docs/api/node-backend/\033[0m"

docs-node-frontend: ## Generate Node.js Frontend documentation using TypeDoc
	@if [ -z "$$(find src/node/frontend -name '*.ts' -o -name '*.tsx' 2>/dev/null | grep -v node_modules | head -1)" ]; then \
		echo -e "\033[0;33mNo TypeScript files in src/node/frontend/ - skipping frontend docs\033[0m"; \
	else \
		echo -e "\033[0;33mGenerating Node.js Frontend documentation...\033[0m"; \
		mkdir -p docs/api/node-frontend && chmod 777 docs/api docs/api/node-frontend 2>/dev/null || true; \
		$(DC_RUN) run --rm node pnpm run docs:frontend; \
		echo -e "\033[0;33mSetting dynamic title...\033[0m"; \
		$(DC_RUN) run --rm node sh -c '\
			PROJECT_NAME=$$(node -e "console.log(require(\"/app/package.json\").name.split(\"/\").pop().replace(/^./, c => c.toUpperCase()))"); \
			PROJECT_VERSION=$$(node -e "console.log(require(\"/app/package.json\").version || \"0.0.0\")"); \
			find /app/docs/api/node-frontend -name "*.html" -exec sed -i "s|<title>Node Frontend - v$$PROJECT_VERSION</title>|<title>$$PROJECT_NAME - Frontend - v$$PROJECT_VERSION</title>|g" {} \; ; \
			find /app/docs/api/node-frontend -name "*.html" -exec sed -i "s|>Node Frontend - v$$PROJECT_VERSION</a>|>$$PROJECT_NAME - Frontend</a>|g" {} \;'; \
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

ssl-trust-ca: ## Show instructions to trust internal CA in your system
	@echo "============================================================================"
	@echo "To trust the internal CA in your system:"
	@echo "============================================================================"
	@echo ""
	@echo "Linux (Arch):"
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
	@echo "macOS:"
	@echo "  sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain docker/certs/ca/ca.crt"
	@echo ""
	@echo "Windows:"
	@echo "  Import docker/certs/ca/ca.crt into 'Trusted Root Certification Authorities'"
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
	@echo -e "\033[0;34m  2. Deploy: ENV=production make build && make up\033[0m"

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
	@echo -e "\033[0;34m     ENV=production make build && make up\033[0m"
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
