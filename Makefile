SHELL := /bin/bash
.SHELLFLAGS := -c

# Docker Compose with plain progress output to avoid terminal corruption
DC := docker compose --progress=plain

.PHONY: $(shell awk '/^[a-zA-Z_-]+:.*?## / { print $$1 }' $(MAKEFILE_LIST) | sed 's/://')

help: ## Show this help
	@awk 'BEGIN { \
		FS = ":.*?## "; \
		printf "\033[0;34m\nAvailable commands:\n\033[0m"; \
	} \
	/^##@/ { \
		if (length(cmds) > 0) { \
			print cmds | "sort"; \
			close("sort"); \
			cmds = ""; \
		} \
		print "\n\033[0;34m" substr($$0, 5) "\033[0m"; \
		next; \
	} \
	/^[a-zA-Z_-]+:.*?## / { \
		cmds = cmds $$1 "\t" $$2 "\n"; \
	} \
	END { \
		if (length(cmds) > 0) { \
			print cmds | "sort"; \
			close("sort"); \
		} \
	}' $(MAKEFILE_LIST) | awk 'BEGIN {FS="\t"} {printf "  \033[0;32m%-26s\033[0m %s\n", $$1, $$2}'

##@ Setup

composer-install: ## Install Composer dependencies (Docker - guaranteed consistency)
	@echo -e "\033[0;33mInstalling Composer dependencies (Docker)...\033[0m"
	@XDEBUG_MODE=off $(DC) run --rm --no-TTY php composer install --prefer-dist --no-interaction
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
	@XDEBUG_MODE=off $(DC) run --rm --no-TTY php composer install --prefer-dist --no-interaction
	@echo -e "\033[0;32mDependencies synced!\033[0m"

sync-lockfiles: ## Sync both Composer and pnpm lockfiles (after branch switch, fresh clone)
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

init: ## Initialize project (copy .env) - Run this first!
	@echo -e "\033[0;33mInitializing configuration...\033[0m"
	@if [ ! -f .env ]; then \
		cp .env.example .env; \
		echo -e "\033[0;32m.env file created from example.\033[0m"; \
		echo -e "\033[0;31mIMPORTANT: Please edit .env before running 'make setup'!\033[0m"; \
	else \
		echo -e "\033[0;34m.env file already exists. Skipped.\033[0m"; \
	fi

setup: ## Create directories, install dev dependencies and ensure structure
	@if [ ! -f .env ]; then \
		echo -e "\033[0;31mError: .env file not found. Please run 'make init' first.\033[0m"; \
		exit 1; \
	fi
	@echo -e "\033[0;33mCreating project structure...\033[0m"

	# Source directories
	@mkdir -p src/php/App/{Http/{Controller,Middleware},Domain,Infrastructure/{Database,Cache}}
	@mkdir -p src/node/backend/{routes,controllers,services,middleware}
	@mkdir -p src/node/frontend
	@mkdir -p src/php/DevDashboard/{Controllers,Services,Views}

	# Resources directories (Frontend source)
	@mkdir -p resources/{js/components,css/components,images,fonts}

	# Public directory (Web root)
	@mkdir -p public/build

	# Tests (separated by language like src/)
	@mkdir -p tests/php/App/{Unit,Feature}
	@mkdir -p tests/node/backend/{unit,integration}
	@mkdir -p tests/node/frontend/{unit,integration}
	@mkdir -p tests/php/DevDashboard/{Services,Controllers}

	# Build & Coverage directories (excluded from IDE indexing)
	@mkdir -p build/{coverage/php,coverage/node,vitest-report}

	# Config & Templates
	@mkdir -p config templates

	# SSL/TLS Certificates
	@mkdir -p docker/certs

	# Documentation Output
	@mkdir -p docs/api/{php,node}
	@mkdir -p tools

	# Storage (Runtime data) - Set permissions
	@. ./.env && mkdir -p $${STORAGE_DIR:-./storage}/{app/{uploads,generated},cache,sessions,logs}
	@. ./.env && chmod 770 $${STORAGE_DIR:-./storage} -R

	# Backups directory (encrypted backups for all services)
	@mkdir -p backups/{db,minio,rabbitmq,elasticsearch}
	@chmod 700 backups backups/*

	@echo -e "\033[0;32mProject structure created!\033[0m"

	# SSL/TLS Certificate Check
	@echo -e "\033[0;33mChecking SSL/TLS certificates...\033[0m"
	@if [ ! -f docker/certs/cert.crt ]; then \
		echo -e "\033[0;34mSSL certificates not found. Generating self-signed certificates...\033[0m"; \
		$(MAKE) --silent ssl-selfsigned; \
	else \
		echo -e "\033[0;32mSSL certificates already exist.\033[0m"; \
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

	@echo -e "\033[0;32mSetup completed!\033[0m"
	@echo -e "\033[0;34mNote: For IDE code completion, run 'make composer-install-local' and 'make pnpm-install-local'\033[0m"

##@ Docker

build: ## Build Docker images (optionally specify service names: make build php nginx)
	@SERVICES="$(filter-out $@,$(MAKECMDGOALS))"; \
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
					assets-api) PROFILES="$$PROFILES --profile node-backend" ;; \
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
			if [ "$${ENABLE_MINIO:-false}" = "true" ]; then PROFILES="$$PROFILES --profile minio"; fi; \
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
					assets-api) PROFILES="$$PROFILES --profile node-backend" ;; \
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
			if [ "$${ENABLE_MINIO:-false}" = "true" ]; then PROFILES="$$PROFILES --profile minio"; fi; \
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
		if [ "$${ENABLE_MINIO:-false}" = "true" ]; then PROFILES="$$PROFILES --profile minio"; fi; \
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
	@XDEBUG_MODE=off $(DC) run --rm --no-TTY php composer $(CMD)

composer-update: ## Update Composer dependencies (updates composer.lock on host, vendor stays in container)
	@echo -e "\033[0;33mUpdating Composer dependencies...\033[0m"
	@XDEBUG_MODE=off $(DC) run --rm --no-TTY php composer update
	@echo -e "\033[0;32mDependencies updated!\033[0m"

down: ## Stop containers (optionally specify service names: make down php nginx)
	@SERVICES="$(filter-out $@,$(MAKECMDGOALS))"; \
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
			if [ "$${ENABLE_MINIO:-false}" = "true" ]; then PROFILES="$$PROFILES --profile minio"; fi; \
			if [ "$${ENABLE_RABBITMQ:-false}" = "true" ]; then PROFILES="$$PROFILES --profile rabbitmq"; fi; \
			if [ "$$ENV" = "production" ]; then \
				$(DC) -f compose.yaml -f compose.production.yaml $$PROFILES down; \
			else \
				$(DC) $$PROFILES down; \
			fi; \
		else \
			$(DC) down; \
		fi; \
	fi
	@echo -e "\033[0;32mContainers stopped!\033[0m"

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
			if [ "$${ENABLE_MINIO:-false}" = "true" ]; then PROFILES="$$PROFILES --profile minio"; fi; \
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

logs-minio: ## Show MinIO logs only
	@if [ -f .env ]; then \
		. ./.env && if [ "$$ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.production.yaml logs -f minio; \
		else \
			docker compose logs -f minio; \
		fi; \
	else \
		docker compose logs -f minio; \
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

pnpm: ## Execute pnpm command (e.g. make pnpm CMD="add -D vue")
	@# Docker bind mounts don't support atomic rename (EBUSY error)
	@# Solution: Run pnpm with lock file in temp location, then copy back
	@$(DC) run --rm --no-TTY node sh -c ' \
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

shell-php: ## Open shell in PHP container
	@docker compose exec php sh

shell-node: ## Open shell in Node container
	@docker compose exec node sh

shell-redis: ## Open shell in Redis container
	@docker compose exec redis sh

shell-postgres: ## Open shell in PostgreSQL container
	@docker compose exec postgres sh

shell-mariadb: ## Open shell in MariaDB container
	@docker compose exec mariadb sh

shell-mercure: ## Open shell in Mercure container
	@docker compose exec mercure sh

shell-meilisearch: ## Open shell in Meilisearch container
	@docker compose exec meilisearch sh

shell-elasticsearch: ## Open shell in Elasticsearch container
	@docker compose exec elasticsearch bash

shell-mailpit: ## Open shell in Mailpit container
	@docker compose exec mailpit sh

shell-minio: ## Open shell in MinIO container
	@docker compose exec minio sh

shell-rabbitmq: ## Open shell in RabbitMQ container
	@docker compose exec rabbitmq bash

status: ## Show running containers status and image disk usage
	@echo -e "\033[0;33mContainer Status:\033[0m"
	@docker compose ps
	@echo -e "\033[0;33m\nImage Disk Usage:\033[0m"
	@docker images | grep "$(COMPOSE_PROJECT_NAME:-zappzarapp)"

up: ## Start containers (optionally specify service names: make up php nginx)
	@if [ ! -f .env ]; then echo -e "\033[0;31mError: .env not found. Run 'make init' first.\033[0m"; exit 1; fi
	@. ./.env && if [ "$${ENV:-development}" = "production" ]; then \
		echo -e "\033[0;33m⚠️  WARNING: Running Compose in production mode.\033[0m"; \
		echo -e "\033[0;33m   For multi-node deployments, use 'make k8s-deploy' (Kubernetes).\033[0m"; \
		echo ""; \
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
		MISSING=""; \
		if [ "$${ENABLE_PHP:-true}" = "true" ] && ! docker image inspect zappzarapp-php >/dev/null 2>&1; then \
			MISSING="$$MISSING php"; \
		fi; \
		if [ "$${ENABLE_NODE:-true}" = "true" ] && ! docker image inspect zappzarapp-node >/dev/null 2>&1; then \
			MISSING="$$MISSING node"; \
		fi; \
		if ! docker image inspect zappzarapp-nginx >/dev/null 2>&1; then \
			MISSING="$$MISSING nginx"; \
		fi; \
		if [ "$${ENABLE_DATABASE:-true}" = "true" ] && [ "$${DB_TYPE:-postgres}" = "postgres" ] && ! docker image inspect zappzarapp-postgres >/dev/null 2>&1; then \
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
				assets-api) PROFILES="$$PROFILES --profile node-backend" ;; \
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
		if [ "$${ENABLE_MINIO:-false}" = "true" ]; then PROFILES="$$PROFILES --profile minio"; fi; \
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
	@$(DC) run --rm --no-TTY node pnpm install --frozen-lockfile
	@echo -e "\033[0;32mDependencies installed!\033[0m"

pnpm-update: ## Update Node.js dependencies (updates pnpm-lock.yaml on host)
	@echo -e "\033[0;33mUpdating Node.js dependencies...\033[0m"
	@# Docker bind mounts don't support atomic rename (EBUSY error)
	@# Solution: Run pnpm with lock file in temp location, then copy back
	@$(DC) run --rm --no-TTY node sh -c ' \
		cp /app/package.json /tmp/package.json && \
		cp /app/pnpm-lock.yaml /tmp/pnpm-lock.yaml 2>/dev/null || true && \
		cd /tmp && pnpm update && \
		cat /tmp/package.json > /app/package.json && \
		cat /tmp/pnpm-lock.yaml > /app/pnpm-lock.yaml \
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
	@$(DC) run --rm --no-TTY node pnpm install
	@echo -e "\033[0;32mDependencies synced!\033[0m"

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

frontend-clean: ## Remove existing frontend (keeps package.json placeholder)
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
	@find $(FRONTEND_DIR) -mindepth 1 ! -name 'package.json' -exec rm -rf {} + 2>/dev/null || true
	@echo -e "\033[0;32mFrontend directory cleaned!\033[0m"

frontend-nuxt: frontend-clean ## Scaffold Nuxt 3 frontend (interactive)
	@echo -e "\033[0;33mScaffolding Nuxt 3 frontend...\033[0m"
	@$(DC) run --rm -it node sh -c '\
		cd /app/src/node/frontend && \
		pnpm dlx nuxi@latest init . --packageManager pnpm --gitInit false --no-install && \
		sh /app/docker/node/frontend-patches/nuxt.post-install.sh .'
	@echo -e "\033[0;32mNuxt 3 scaffolded! Run 'make pnpm-sync' to install dependencies.\033[0m"

frontend-next: frontend-clean ## Scaffold Next.js frontend (interactive)
	@echo -e "\033[0;33mScaffolding Next.js frontend...\033[0m"
	@$(DC) run --rm -it node sh -c '\
		cd /app/src/node/frontend && \
		pnpm dlx create-next-app@latest . --use-pnpm --skip-install && \
		sh /app/docker/node/frontend-patches/next.post-install.sh .'
	@echo -e "\033[0;32mNext.js scaffolded! Run 'make pnpm-sync' to install dependencies.\033[0m"

frontend-remix: frontend-clean ## Scaffold React Router frontend (formerly Remix v2)
	@echo -e "\033[0;33mScaffolding React Router frontend...\033[0m"
	@$(DC) run --rm -it node sh -c '\
		TEMP_DIR=$$(mktemp -d) && \
		cd "$$TEMP_DIR" && \
		pnpm dlx create-react-router@latest frontend --no-install && \
		cp -r frontend/. /app/src/node/frontend/ && \
		rm -rf "$$TEMP_DIR" && \
		cd /app/src/node/frontend && \
		sh /app/docker/node/frontend-patches/remix.post-install.sh .'
	@echo -e "\033[0;32mReact Router scaffolded! Run 'make pnpm-sync' to install dependencies.\033[0m"

frontend-sveltekit: frontend-clean ## Scaffold SvelteKit frontend (interactive)
	@echo -e "\033[0;33mScaffolding SvelteKit frontend...\033[0m"
	@$(DC) run --rm -it node sh -c '\
		TEMP_DIR=$$(mktemp -d) && \
		cd "$$TEMP_DIR" && \
		pnpm dlx sv create frontend --template minimal --types ts --no-add-ons --no-install && \
		cp -r frontend/. /app/src/node/frontend/ && \
		rm -rf "$$TEMP_DIR" && \
		cd /app/src/node/frontend && \
		sh /app/docker/node/frontend-patches/sveltekit.post-install.sh .'
	@echo -e "\033[0;32mSvelteKit scaffolded! Run 'make pnpm-sync' to install dependencies.\033[0m"

node-build: ## Executes the frontend build inside the Node container (uses 'build' stage)
	@echo -e "\033[0;33mExecuting frontend build...\033[0m"
	@$(DC) run --rm --build --target build node pnpm run build

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
	@. ./.env && docker compose exec postgres psql -U $${DB_USER:-app} -d $${DB_NAME:-app}

postgres-dump: ## Create database backup (dump.sql)
	@echo -e "\033[0;33mCreating database backup...\033[0m"
	@. ./.env && docker compose exec postgres pg_dump -U $${DB_USER:-app} -d $${DB_NAME:-app} > dump.sql
	@echo -e "\033[0;32mBackup saved to dump.sql\033[0m"

postgres-restore: ## Restore database from dump.sql
	@if [ ! -f dump.sql ]; then \
		echo -e "\033[0;31mError: dump.sql not found!\033[0m"; \
		exit 1; \
	fi
	@echo -e "\033[0;33mRestoring database from dump.sql...\033[0m"
	@. ./.env && docker compose exec -T postgres psql -U $${DB_USER:-app} -d $${DB_NAME:-app} < dump.sql
	@echo -e "\033[0;32mDatabase restored!\033[0m"

mariadb-cli: ## Open MariaDB CLI
	@. ./.env && docker compose exec mariadb mariadb -u $${DB_USER:-app} -p$${DB_PASSWORD:-secret} $${DB_NAME:-app}

mariadb-dump: ## Create MariaDB database backup (dump.sql)
	@echo -e "\033[0;33mCreating MariaDB database backup...\033[0m"
	@. ./.env && docker compose exec mariadb mariadb-dump -u $${DB_USER:-app} -p$${DB_PASSWORD:-secret} $${DB_NAME:-app} > dump.sql
	@echo -e "\033[0;32mBackup saved to dump.sql\033[0m"

mariadb-restore: ## Restore MariaDB database from dump.sql
	@if [ ! -f dump.sql ]; then \
		echo -e "\033[0;31mError: dump.sql not found!\033[0m"; \
		exit 1; \
	fi
	@echo -e "\033[0;33mRestoring MariaDB database from dump.sql...\033[0m"
	@. ./.env && docker compose exec -T mariadb mariadb -u $${DB_USER:-app} -p$${DB_PASSWORD:-secret} $${DB_NAME:-app} < dump.sql
	@echo -e "\033[0;32mMariaDB database restored!\033[0m"

##@ Backup & Migrations

backup-all: ## Create backups of all enabled services (database, minio, rabbitmq, elasticsearch)
	@echo -e "\033[0;33m=== Creating backups of all enabled services ===${NC}\033[0m"
	@. ./.env && \
	if [ "$${ENABLE_DATABASE:-true}" = "true" ]; then \
		echo -e "\033[0;34m[1/4] Database backup...\033[0m"; \
		bash docker/scripts/backup-databases.sh; \
	else \
		echo -e "\033[0;37m[1/4] Database: skipped (disabled)\033[0m"; \
	fi
	@. ./.env && \
	if [ "$${ENABLE_MINIO:-false}" = "true" ]; then \
		echo -e "\033[0;34m[2/4] MinIO backup...\033[0m"; \
		bash docker/scripts/backup-minio.sh; \
	else \
		echo -e "\033[0;37m[2/4] MinIO: skipped (disabled)\033[0m"; \
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

backup-minio: ## Create encrypted MinIO backup (all buckets)
	@echo -e "\033[0;33mCreating MinIO backup...\033[0m"
	@if [ -n "$(RETENTION)" ]; then \
		bash docker/scripts/backup-minio.sh --retention $(RETENTION); \
	else \
		bash docker/scripts/backup-minio.sh; \
	fi

backup-minio-list: ## List all MinIO backups
	@echo -e "\033[0;33mAvailable MinIO backups:\033[0m"
	@if [ -d backups/minio ]; then \
		ls -lah backups/minio/*.tar.gz* 2>/dev/null || echo -e "\033[0;34mNo backups found.\033[0m"; \
	else \
		echo -e "\033[0;34mNo backups directory. Run 'make backup-minio' first.\033[0m"; \
	fi

backup-minio-restore: ## Restore MinIO from backup (interactive)
	@echo -e "\033[0;33mAvailable MinIO backups:\033[0m"
	@if [ -d backups/minio ]; then \
		ls -1 backups/minio/*.tar.gz* 2>/dev/null || echo "No backups found."; \
	else \
		echo "No backups directory."; \
		exit 1; \
	fi
	@echo ""
	@echo -e "\033[0;33mNote: Restore will overwrite existing MinIO data.\033[0m"
	@read -p "Enter backup filename (from ./backups/minio/): " BACKUP_FILE; \
	bash docker/scripts/restore-minio.sh "backups/minio/$$BACKUP_FILE"

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
	@. ./.env && \
	if [ "$${DB_TYPE:-postgres}" = "postgres" ]; then \
		echo -e "\033[0;34mRunning PostgreSQL migrations...\033[0m"; \
		for migration in migrations/postgresql/*.sql; do \
			if [ -f "$$migration" ]; then \
				echo -e "  Applying: $$(basename $$migration)"; \
				docker compose exec -T postgres psql -U $${DB_USER:-app} -d $${DB_NAME:-app} -f /dev/stdin < "$$migration" 2>&1 | grep -v "^$$" || true; \
			fi; \
		done; \
	elif [ "$${DB_TYPE:-postgres}" = "mariadb" ]; then \
		echo -e "\033[0;34mRunning MariaDB migrations...\033[0m"; \
		for migration in migrations/mariadb/*.sql; do \
			if [ -f "$$migration" ]; then \
				echo -e "  Applying: $$(basename $$migration)"; \
				docker compose exec -T mariadb mariadb -u $${DB_USER:-app} -p$${DB_PASSWORD:-secret} $${DB_NAME:-app} < "$$migration" 2>&1 | grep -v "^$$" || true; \
			fi; \
		done; \
	else \
		echo -e "\033[0;31mError: Unknown DB_TYPE '$${DB_TYPE}'\033[0m"; \
		exit 1; \
	fi
	@echo -e "\033[0;32mMigrations completed!\033[0m"

db-cleanup: ## Run retention policy cleanup (delete old logs)
	@echo -e "\033[0;33mRunning database cleanup (retention policy)...\033[0m"
	@if [ ! -f .env ]; then \
		echo -e "\033[0;31mError: .env not found. Run 'make init' first.\033[0m"; \
		exit 1; \
	fi
	@. ./.env && \
	RETENTION_DAYS=$${RETENTION_DAYS:-730}; \
	if [ "$${DB_TYPE:-postgres}" = "postgres" ]; then \
		echo -e "\033[0;34mPostgreSQL: Deleting audit_logs older than $$RETENTION_DAYS days...\033[0m"; \
		docker compose exec -T postgres psql -U $${DB_USER:-app} -d $${DB_NAME:-app} \
			-c "SELECT delete_old_logs('audit_logs', $$RETENTION_DAYS);" 2>/dev/null || \
			echo -e "\033[0;31mError: Run 'make db-migrations' first to create retention functions.\033[0m"; \
	elif [ "$${DB_TYPE:-postgres}" = "mariadb" ]; then \
		echo -e "\033[0;34mMariaDB: Deleting audit_logs older than $$RETENTION_DAYS days...\033[0m"; \
		docker compose exec -T mariadb mariadb -u $${DB_USER:-app} -p$${DB_PASSWORD:-secret} $${DB_NAME:-app} \
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

	@echo -e "\033[0;34m🌐 Nginx HTTP:\033[0m"
	@if [ -f .env ]; then . ./.env; fi; \
	NGINX_PORT=$${NGINX_PORT:-8080}; \
	if command -v curl >/dev/null 2>&1; then \
		HTTP_CODE=$$(curl -s -o /dev/null -w "%{http_code}" http://localhost:$$NGINX_PORT 2>/dev/null); \
		if [ "$$HTTP_CODE" = "200" ]; then \
			echo -e "\033[0;32m  ✅ HTTP 200 OK (port $$NGINX_PORT)\033[0m"; \
		else \
			echo -e "\033[0;31m  ❌ HTTP $$HTTP_CODE (port $$NGINX_PORT)\033[0m"; \
		fi; \
	else \
		echo -e "\033[0;33m  ⚠️  curl not found, skipping HTTP check\033[0m"; \
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

	@echo -e "\033[0;34m📦 MinIO (S3 Storage):\033[0m"
	@if [ -f .env ]; then . ./.env; fi; \
	if [ "$${ENABLE_MINIO:-false}" = "true" ]; then \
		if [ "$$(docker inspect --format='{{.State.Health.Status}}' $$(docker compose ps -q minio) 2>/dev/null)" = "healthy" ]; then \
			echo -e "\033[0;32m  ✅ Healthy\033[0m"; \
		else \
			echo -e "\033[0;31m  ❌ Unhealthy or not running\033[0m"; \
		fi; \
	else \
		echo -e "\033[0;37m  ⚪ Disabled (ENABLE_MINIO=false)\033[0m"; \
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
	@# Stop ALL containers and rebuild ALL images regardless of profile settings (fresh = complete reset)
	@if [ -f .env ]; then \
		. ./.env && if [ "$$ENV" = "production" ]; then \
			$(DC) -f compose.yaml -f compose.production.yaml --profile php --profile node --profile redis --profile postgres --profile mariadb --profile mercure --profile meilisearch --profile elasticsearch --profile mailpit --profile minio --profile rabbitmq down -v --rmi all && \
			echo -e "\033[0;34mBuilding Node image first (required by PHP and NGINX)...\033[0m" && \
			$(DC) -f compose.yaml -f compose.production.yaml --profile php --profile node --profile redis --profile postgres --profile mariadb --profile mercure --profile meilisearch --profile elasticsearch --profile mailpit --profile minio --profile rabbitmq build --no-cache node && \
			echo -e "\033[0;34mBuilding remaining images...\033[0m" && \
			$(DC) -f compose.yaml -f compose.production.yaml --profile php --profile node --profile redis --profile postgres --profile mariadb --profile mercure --profile meilisearch --profile elasticsearch --profile mailpit --profile minio --profile rabbitmq build --no-cache; \
		else \
			$(DC) --profile php --profile node --profile redis --profile postgres --profile mariadb --profile mercure --profile meilisearch --profile elasticsearch --profile mailpit --profile minio --profile rabbitmq down -v --rmi all && \
			$(DC) --profile php --profile node --profile redis --profile postgres --profile mariadb --profile mercure --profile meilisearch --profile elasticsearch --profile mailpit --profile minio --profile rabbitmq build --no-cache; \
		fi; \
	else \
		$(DC) --profile php --profile node --profile redis --profile postgres --profile mariadb --profile mercure --profile meilisearch --profile elasticsearch --profile mailpit --profile minio --profile rabbitmq down -v --rmi all && \
		$(DC) --profile php --profile node --profile redis --profile postgres --profile mariadb --profile mercure --profile meilisearch --profile elasticsearch --profile mailpit --profile minio --profile rabbitmq build --no-cache; \
	fi
	@echo -e "\033[0;33mInstalling dependencies...\033[0m"
	@$(MAKE) --silent composer-install
	@$(MAKE) --silent pnpm-install
	@$(MAKE) --silent up

rebuild: clean build up ## Complete rebuild

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
	@docker compose exec php php -d error_reporting=24575 vendor/bin/phpmd src/php,tests/php text phpmd.xml.dist

rector-check: ## Run Rector for automated refactoring analysis (dry-run)
	@echo -e "\033[0;33mRunning Rector analysis (dry-run)...\033[0m"
	@docker compose exec php composer rector-check

rector-fix: ## Apply Rector refactorings automatically
	@echo -e "\033[0;33mApplying Rector refactorings...\033[0m"
	@docker compose exec php composer rector-fix

check: cs-check analyse phpmd rector-check prettier-check type-check lint-node test validate lint-md lint-docker ## Run all checks (CI simulation)
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
	@docker run --rm -v $$(pwd)/.hadolint.yaml:/.config/hadolint.yaml -i hadolint/hadolint < docker/minio/Dockerfile
	@docker run --rm -v $$(pwd)/.hadolint.yaml:/.config/hadolint.yaml -i hadolint/hadolint < docker/rabbitmq/Dockerfile
	@echo -e "\033[0;32mDockerfile linting completed!\033[0m"

lint-md: ## Check Markdown files for style issues
	@echo -e "\033[0;33mChecking Markdown files...\033[0m"
	@docker compose exec node pnpm run lint:md
	@echo -e "\033[0;32mMarkdown check completed!\033[0m"

lint-md-fix: ## Fix Markdown style issues automatically
	@echo -e "\033[0;33mFixing Markdown files...\033[0m"
	@docker compose exec node pnpm run lint:md:fix
	@echo -e "\033[0;32mMarkdown files fixed!\033[0m"

lint-node: ## Run ESLint on TypeScript/JavaScript files
	@echo -e "\033[0;33mRunning ESLint...\033[0m"
	@docker compose exec node pnpm run lint
	@echo -e "\033[0;32mESLint check completed!\033[0m"

lint-node-fix: ## Fix ESLint issues automatically
	@echo -e "\033[0;33mFixing ESLint issues...\033[0m"
	@docker compose exec node pnpm run lint:fix
	@echo -e "\033[0;32mESLint issues fixed!\033[0m"

type-check: ## Run TypeScript type checking (static analysis)
	@echo -e "\033[0;33mRunning TypeScript type check...\033[0m"
	@docker compose exec node pnpm run type-check
	@echo -e "\033[0;32mTypeScript check completed!\033[0m"

prettier-check: ## Check code formatting with Prettier
	@echo -e "\033[0;33mChecking code formatting (Prettier)...\033[0m"
	@docker compose exec node pnpm run format:check
	@echo -e "\033[0;32mPrettier check completed!\033[0m"

prettier-fix: ## Fix code formatting with Prettier
	@echo -e "\033[0;33mFixing code formatting (Prettier)...\033[0m"
	@docker compose exec node pnpm run format
	@echo -e "\033[0;32mPrettier formatting applied!\033[0m"

outdated: ## Check for outdated Composer dependencies
	@echo -e "\033[0;33mChecking Composer for outdated packages...\033[0m"
	@docker compose exec php composer outdated
	@echo -e "\033[0;32mOutdated check completed!\033[0m"

depcheck: ## Find unused Node.js dependencies
	@echo -e "\033[0;33mChecking for unused dependencies...\033[0m"
	@docker compose exec node pnpm exec depcheck
	@echo -e "\033[0;32mDepcheck completed!\033[0m"

knip: ## Find dead code, unused exports and dependencies
	@echo -e "\033[0;33mRunning knip dead code detection...\033[0m"
	@docker compose exec node pnpm exec knip
	@echo -e "\033[0;32mKnip completed!\033[0m"

dive: ## Analyze Docker image layers and sizes
	@echo -e "\033[0;33mAnalyzing Docker image layers...\033[0m"
	@echo -e "\033[0;34mSelect image to analyze:\033[0m"
	@echo "  1) php"
	@echo "  2) node"
	@echo "  3) nginx"
	@read -p "Enter choice [1-3]: " choice; \
	case $$choice in \
		1) IMAGE=zappzarapp-php ;; \
		2) IMAGE=zappzarapp-node ;; \
		3) IMAGE=zappzarapp-nginx ;; \
		*) echo "Invalid choice"; exit 1 ;; \
	esac; \
	docker run --rm -it -v /var/run/docker.sock:/var/run/docker.sock wagoodman/dive:latest $$IMAGE

test: test-php test-node ## Run all tests (PHP + Node.js)
	@echo -e "\033[0;32mAll tests completed!\033[0m"

test-coverage: test-coverage-php test-coverage-node ## Generate coverage reports for PHP and Node.js
	@echo -e "\033[0;32mAll coverage reports generated!\033[0m"
	@echo -e "\033[0;34mPHP Coverage: build/coverage/php/index.html\033[0m"
	@echo -e "\033[0;34mNode.js Coverage: build/coverage/node/index.html\033[0m"

test-php: ## Run PHPUnit tests
	@echo -e "\033[0;33mRunning PHPUnit tests...\033[0m"
	@docker compose exec php composer test

test-php-debug: ## Run PHPUnit tests with Xdebug enabled
	@echo -e "\033[0;33mRunning PHPUnit with Xdebug (Step Debugging)...\033[0m"
	@docker compose exec php sh -c 'XDEBUG_MODE=develop,debug composer test'

test-coverage-php: ## Generate PHPUnit coverage report (HTML in build/coverage/php)
	@echo -e "\033[0;33mRunning PHPUnit with coverage report...\033[0m"
	@docker compose exec php sh -c 'XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html build/coverage/php --coverage-clover build/coverage/php/clover.xml'
	@echo -e "\033[0;32mPHP coverage report generated in build/coverage/php/index.html!\033[0m"

test-node: ## Run Vitest tests
	@echo -e "\033[0;33mRunning Vitest tests...\033[0m"
	@docker compose exec node pnpm test

test-node-watch: ## Run Vitest in watch mode
	@echo -e "\033[0;33mRunning Vitest in watch mode...\033[0m"
	@docker compose exec node pnpm test:watch

test-coverage-node: ## Generate Vitest coverage report (HTML in build/coverage/node)
	@echo -e "\033[0;33mRunning Vitest with coverage report...\033[0m"
	@docker compose exec node pnpm test:coverage
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

goss-test-build: ## Run GOSS build-time tests for all images
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

goss-test-minio: ## Test MinIO container (runtime)
	@tests/goss/runtime-tests.sh minio

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
	@if [ ! -f secrets/minio_root_user.txt ]; then \
		echo -e "\033[0;34mGenerating minio_root_user secret...\033[0m"; \
		echo "minioadmin" > secrets/minio_root_user.txt; \
		chmod 644 secrets/minio_root_user.txt; \
		echo -e "\033[0;32mminio_root_user secret generated.\033[0m"; \
	else \
		echo -e "\033[0;32mminio_root_user secret already exists.\033[0m"; \
	fi
	@if [ ! -f secrets/minio_root_password.txt ]; then \
		echo -e "\033[0;34mGenerating minio_root_password secret...\033[0m"; \
		openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | head -c 24 > secrets/minio_root_password.txt; \
		chmod 644 secrets/minio_root_password.txt; \
		echo -e "\033[0;32mminio_root_password secret generated.\033[0m"; \
	else \
		echo -e "\033[0;32mminio_root_password secret already exists.\033[0m"; \
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
	@if [ -f .env ]; then \
		. ./.env && \
		docker run --rm -v /var/run/docker.sock:/var/run/docker.sock \
			aquasec/trivy:latest image --format cyclonedx --output build/sbom-php.json \
			$${COMPOSE_PROJECT_NAME:-zappzarapp}-php:latest; \
	fi
	@echo -e "\033[0;32mSBOM generated in build/sbom-php.json!\033[0m"

security-scan: ## Scan Docker images for vulnerabilities
	@echo -e "\033[0;33mScanning images for vulnerabilities...\033[0m"
	@if [ -f .env ]; then \
		. ./.env && \
		echo "Scanning PHP image..." && \
		docker run --rm -v /var/run/docker.sock:/var/run/docker.sock \
			aquasec/trivy:latest image --severity HIGH,CRITICAL \
			$${COMPOSE_PROJECT_NAME:-zappzarapp}-php:latest 2>/dev/null || \
			echo "⚠️  Image not found. Run 'make build' first." && \
		echo "\nScanning Nginx image..." && \
		docker run --rm -v /var/run/docker.sock:/var/run/docker.sock \
			aquasec/trivy:latest image --severity HIGH,CRITICAL \
			$${COMPOSE_PROJECT_NAME:-zappzarapp}-nginx:latest 2>/dev/null || \
			echo "⚠️  Image not found. Run 'make build' first."; \
	fi
	@echo -e "\033[0;32mSecurity scan completed!\033[0m"

security-audit-node: ## Scan Node.js dependencies for known vulnerabilities
	@echo -e "\033[0;33mScanning Node.js dependencies with pnpm audit...\033[0m"
	@docker compose exec node pnpm audit

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

docs-php: $(PHPDOC_PHAR) ## Generate PHP API documentation using phpDocumentor
	@echo -e "\033[0;33mGenerating PHP API documentation...\033[0m"
	@docker compose exec php composer docs
	@echo -e "\033[0;33mApplying custom theme...\033[0m"
	@docker compose exec php sh -c '\
		CSS_CONTENT=$$(cat /var/www/html/documentation/assets/custom-phpdoc.css | tr "\n" " " | sed "s/  */ /g"); \
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
	@docker compose exec -u node node pnpm run docs:backend
	@echo -e "\033[0;33mSetting dynamic title...\033[0m"
	@docker compose exec -u node node sh -c '\
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
		docker compose exec -u node node pnpm run docs:frontend; \
		echo -e "\033[0;33mSetting dynamic title...\033[0m"; \
		docker compose exec -u node node sh -c '\
			PROJECT_NAME=$$(node -e "console.log(require(\"/app/package.json\").name.split(\"/\").pop().replace(/^./, c => c.toUpperCase()))"); \
			PROJECT_VERSION=$$(node -e "console.log(require(\"/app/package.json\").version || \"0.0.0\")"); \
			find /app/docs/api/node-frontend -name "*.html" -exec sed -i "s|<title>Node Frontend - v$$PROJECT_VERSION</title>|<title>$$PROJECT_NAME - Frontend - v$$PROJECT_VERSION</title>|g" {} \; ; \
			find /app/docs/api/node-frontend -name "*.html" -exec sed -i "s|>Node Frontend - v$$PROJECT_VERSION</a>|>$$PROJECT_NAME - Frontend</a>|g" {} \;'; \
		echo -e "\033[0;32mFrontend documentation generated in docs/api/node-frontend/\033[0m"; \
	fi

docs-clean: ## Remove generated documentation
	@echo -e "\033[0;33mCleaning documentation...\033[0m"
	@rm -rf docs/ .phpdoc/ tools/
	@echo -e "\033[0;32mDocumentation cleaned!\033[0m"

##@ SSL/TLS

ssl-selfsigned: ## Generate self-signed SSL certificate for development
	@echo -e "\033[0;33mGenerating self-signed SSL certificate...\033[0m"
	@if [ ! -f docker/certs/generate-selfsigned.sh ]; then \
		echo -e "\033[0;31mError: generate-selfsigned.sh not found!\033[0m"; \
		exit 1; \
	fi
	@bash docker/certs/generate-selfsigned.sh localhost
	@echo -e "\033[0;32mSelf-signed certificate generated!\033[0m"
	@echo -e "\033[0;34mTo enable HTTPS (Development):\033[0m"
	@echo -e "\033[0;34m  1. Uncomment SSL port and volumes in compose.yaml\033[0m"
	@echo -e "\033[0;34m  2. Restart: make restart\033[0m"

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
	if [ -f docker/certs/cert.crt ]; then \
		CERT_BEFORE=$$(openssl x509 -in docker/certs/cert.crt -noout -fingerprint 2>/dev/null || echo ""); \
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
	if [ -f docker/certs/cert.crt ]; then \
		CERT_AFTER=$$(openssl x509 -in docker/certs/cert.crt -noout -fingerprint 2>/dev/null || echo ""); \
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
	@if [ -f docker/certs/cert.crt ]; then \
		openssl x509 -in docker/certs/cert.crt -text -noout | grep -E "Subject:|Issuer:|Not Before|Not After|DNS:"; \
	else \
		echo -e "\033[0;31mNo certificate found. Generate one with:\033[0m"; \
		echo -e "\033[0;34m  - make ssl-selfsigned (development)\033[0m"; \
		echo -e "\033[0;34m  - make ssl-letsencrypt (production)\033[0m"; \
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
	@echo -e "\033[0;34m     For self-signed:   make ssl-selfsigned\033[0m"
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
		rm -rf docker/certs/*.crt docker/certs/*.key docker/certs/*.pem docker/certs/letsencrypt; \
		echo -e "\033[0;32mSSL certificates removed!\033[0m"; \
	else \
		echo -e "\033[0;34mOperation cancelled.\033[0m"; \
	fi

# Catch-all target for service names passed to up/down/restart
# This prevents Make from trying to build service names as targets
# Example: 'make up php nginx' - php and nginx are caught here
%:
	@:
