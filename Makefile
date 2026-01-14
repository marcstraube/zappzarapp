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

composer-install: ## Install/update Composer dependencies (Docker - guaranteed consistency)
	@echo -e "\033[0;33mManaging Composer dependencies (Docker)...\033[0m"
	@if [ ! -f "vendor/autoload.php" ]; then \
		XDEBUG_MODE=off $(DC) run --rm --no-TTY php composer install --prefer-dist --no-interaction; \
	else \
		XDEBUG_MODE=off $(DC) run --rm --no-TTY php composer install --prefer-dist --no-interaction --no-scripts; \
	fi
	@echo -e "\033[0;32mDependencies ready!\033[0m"

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
	@mkdir -p src/node/App/{routes,controllers,services,middleware}
	@mkdir -p src/php/DevDashboard/{Controllers,Services,Views}

	# Resources directories (Frontend source)
	@mkdir -p resources/{js/components,css/components,images,fonts}

	# Public directory (Web root)
	@mkdir -p public/build

	# Tests (separated by language like src/)
	@mkdir -p tests/php/App/{Unit,Feature}
	@mkdir -p tests/node/App/{unit,integration}
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

	# Backups directory (encrypted database dumps)
	@mkdir -p backups
	@chmod 700 backups

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
	@echo -e "\033[0;33mStarting containers (dependencies will install automatically)...\033[0m"
	@$(MAKE) --silent up
	@echo -e "\033[0;33mWaiting for dependencies to install (30-60 seconds)...\033[0m"
	@sleep 45
	@echo -e "\033[0;33mSyncing lock files from containers to host...\033[0m"
	@$(MAKE) --silent sync-lockfiles
	@echo -e "\033[0;32mSetup completed (directories + dependencies + lock files)!\033[0m"
	@echo -e "\033[0;34mNote: For IDE code completion, run 'make composer-install-local' and 'make node-install-local'\033[0m"

##@ Docker

build: ## Build Docker images
	@echo -e "\033[0;33mBuilding Docker images...\033[0m"
	@if [ -f .env ]; then \
		. ./.env && \
		PROFILES=""; \
		if [ "$${ENABLE_DATABASE:-true}" = "true" ]; then \
			PROFILES="$$PROFILES --profile $${DB_TYPE:-postgres}"; \
		fi; \
		if [ "$${ENABLE_PHP:-true}" = "true" ]; then PROFILES="$$PROFILES --profile php"; fi; \
		if [ "$${ENABLE_NODE:-true}" = "true" ]; then PROFILES="$$PROFILES --profile node"; fi; \
		if [ "$${ENABLE_REDIS:-true}" = "true" ]; then PROFILES="$$PROFILES --profile redis"; fi; \
		if [ "$$ENV" = "production" ]; then \
			echo -e "\033[0;34mBuilding Node image first (required by PHP and NGINX)...\033[0m" && \
			$(DC) -f compose.yaml -f compose.production.yaml $$PROFILES build node && \
			echo -e "\033[0;34mBuilding remaining images...\033[0m" && \
			$(DC) -f compose.yaml -f compose.production.yaml $$PROFILES build; \
		else \
			$(DC) $$PROFILES build; \
		fi; \
	else \
		$(DC) --profile php --profile node --profile redis --profile postgres build; \
	fi
	@echo -e "\033[0;32mBuild completed!\033[0m"

build-no-cache: ## Build Docker images without cache
	@echo -e "\033[0;33mBuilding Docker images (no cache)...\033[0m"
	@if [ -f .env ]; then \
		. ./.env && \
		PROFILES=""; \
		if [ "$${ENABLE_DATABASE:-true}" = "true" ]; then \
			PROFILES="$$PROFILES --profile $${DB_TYPE:-postgres}"; \
		fi; \
		if [ "$${ENABLE_PHP:-true}" = "true" ]; then PROFILES="$$PROFILES --profile php"; fi; \
		if [ "$${ENABLE_NODE:-true}" = "true" ]; then PROFILES="$$PROFILES --profile node"; fi; \
		if [ "$${ENABLE_REDIS:-true}" = "true" ]; then PROFILES="$$PROFILES --profile redis"; fi; \
		if [ "$$ENV" = "production" ]; then \
			echo -e "\033[0;34mBuilding Node image first (required by PHP and NGINX)...\033[0m" && \
			$(DC) -f compose.yaml -f compose.production.yaml $$PROFILES build --no-cache node && \
			echo -e "\033[0;34mBuilding remaining images...\033[0m" && \
			$(DC) -f compose.yaml -f compose.production.yaml $$PROFILES build --no-cache; \
		else \
			$(DC) $$PROFILES build --no-cache; \
		fi; \
	else \
		$(DC) --profile php --profile node --profile redis --profile postgres build --no-cache; \
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

composer: ## Execute Composer command in running container (e.g. make composer CMD="require vendor/package")
	@docker compose exec -u www-data php composer $(CMD)

composer-update: ## Update Composer dependencies (updates composer.lock on host, vendor stays in container)
	@echo -e "\033[0;33mUpdating Composer dependencies...\033[0m"
	@XDEBUG_MODE=off $(DC) run --rm --no-TTY -u $${USER_ID:-1000}:$${GROUP_ID:-1000} --entrypoint composer php update
	@echo -e "\033[0;32mDependencies updated!\033[0m"

down: ## Stop containers
	@echo -e "\033[0;33mStopping containers...\033[0m"
	@# Kill docker compose watch process using saved PID
	@if [ -f .docker-watch.pid ]; then \
		kill -9 $$(cat .docker-watch.pid) 2>/dev/null || true; \
		rm -f .docker-watch.pid; \
	fi
	@# Fallback: kill any remaining watch processes (excluding current shell)
	@pgrep -f "docker.*compose.*watch" | grep -v $$$$ | xargs -r kill -9 2>/dev/null || true
	@sleep 2
	@if [ -f .env ]; then \
		. ./.env && \
		PROFILES=""; \
		if [ "$${ENABLE_DATABASE:-true}" = "true" ]; then \
			PROFILES="$$PROFILES --profile $${DB_TYPE:-postgres}"; \
		fi; \
		if [ "$${ENABLE_PHP:-true}" = "true" ]; then PROFILES="$$PROFILES --profile php"; fi; \
		if [ "$${ENABLE_NODE:-true}" = "true" ]; then PROFILES="$$PROFILES --profile node"; fi; \
		if [ "$${ENABLE_REDIS:-true}" = "true" ]; then PROFILES="$$PROFILES --profile redis"; fi; \
		if [ "$$ENV" = "production" ]; then \
			$(DC) -f compose.yaml -f compose.production.yaml $$PROFILES down; \
		else \
			$(DC) $$PROFILES down; \
		fi; \
	else \
		$(DC) down; \
	fi
	@echo -e "\033[0;32mContainers stopped!\033[0m"

logs: ## Show logs of all containers
	@if [ -f .env ]; then \
		. ./.env && \
		PROFILES=""; \
		if [ "$${ENABLE_DATABASE:-true}" = "true" ]; then \
			PROFILES="$$PROFILES --profile $${DB_TYPE:-postgres}"; \
		fi; \
		if [ "$${ENABLE_PHP:-true}" = "true" ]; then PROFILES="$$PROFILES --profile php"; fi; \
		if [ "$${ENABLE_NODE:-true}" = "true" ]; then PROFILES="$$PROFILES --profile node"; fi; \
		if [ "$${ENABLE_REDIS:-true}" = "true" ]; then PROFILES="$$PROFILES --profile redis"; fi; \
		if [ "$$ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.production.yaml $$PROFILES logs -f; \
		else \
			docker compose $$PROFILES logs -f; \
		fi; \
	else \
		docker compose --profile php --profile node --profile redis --profile postgres logs -f; \
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

pnpm: ## Execute pnpm command in running container (e.g. make pnpm CMD="add vue")
	@docker compose exec node pnpm $(CMD)

prune: ## Remove untagged/dangling images related to this project
	@echo -e "\033[0;33mPruning dangling images...\033[0m"
	@. ./.env && docker image prune -f --filter "label=com.docker.compose.project=$$COMPOSE_PROJECT_NAME"

restart: down up ## Restart containers

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

status: ## Show running containers status and image disk usage
	@echo -e "\033[0;33mContainer Status:\033[0m"
	@docker compose ps
	@echo -e "\033[0;33m\nImage Disk Usage:\033[0m"
	@docker images | grep "$(COMPOSE_PROJECT_NAME:-docker-webdev)"

up: ## Start enabled containers (based on .env ENABLE_* flags)
	@if [ ! -f .env ]; then echo -e "\033[0;31mError: .env not found. Run 'make init' first.\033[0m"; exit 1; fi
	@# Check if required images exist
	@. ./.env && \
	MISSING=""; \
	if [ "$${ENABLE_PHP:-true}" = "true" ] && ! docker image inspect docker-webdev-php >/dev/null 2>&1; then \
		MISSING="$$MISSING php"; \
	fi; \
	if [ "$${ENABLE_NODE:-true}" = "true" ] && ! docker image inspect docker-webdev-node >/dev/null 2>&1; then \
		MISSING="$$MISSING node"; \
	fi; \
	if ! docker image inspect docker-webdev-nginx >/dev/null 2>&1; then \
		MISSING="$$MISSING nginx"; \
	fi; \
	if [ "$${ENABLE_DATABASE:-true}" = "true" ] && [ "$${DB_TYPE:-postgres}" = "postgres" ] && ! docker image inspect docker-webdev-postgres >/dev/null 2>&1; then \
		MISSING="$$MISSING postgres"; \
	fi; \
	if [ -n "$$MISSING" ]; then \
		echo -e "\033[0;31mError: Required images not found:$$MISSING\033[0m"; \
		echo -e "\033[0;31mRun 'make build' first.\033[0m"; \
		exit 1; \
	fi
	@. ./.env && \
	PROFILES=""; \
	if [ "$${ENABLE_DATABASE:-true}" = "true" ]; then \
		PROFILES="$$PROFILES --profile $${DB_TYPE:-postgres}"; \
	fi; \
	if [ "$${ENABLE_PHP:-true}" = "true" ]; then PROFILES="$$PROFILES --profile php"; fi; \
	if [ "$${ENABLE_NODE:-true}" = "true" ]; then PROFILES="$$PROFILES --profile node"; fi; \
	if [ "$${ENABLE_REDIS:-true}" = "true" ]; then PROFILES="$$PROFILES --profile redis"; fi; \
	echo -e "\033[0;33mStarting containers in $${ENV:-development} mode...\033[0m"; \
	if [ "$$ENV" = "production" ]; then \
		NODE_TARGET_AUTO="asset-server"; \
		case "$${NODE_MODE:-full-stack}" in \
			full-stack|backend-only) NODE_TARGET_AUTO="app-server" ;; \
		esac; \
		export NODE_TARGET="$${NODE_TARGET:-$$NODE_TARGET_AUTO}"; \
		$(DC) -f compose.yaml -f compose.production.yaml $$PROFILES up -d; \
	else \
		echo -e "\033[0;34mStarting containers...\033[0m"; \
		$(DC) $$PROFILES up -d; \
		echo -e "\033[0;34mStarting Docker Compose Watch (cross-platform file sync)...\033[0m"; \
		setsid $(DC) $$PROFILES watch < /dev/null > /dev/null 2>&1 & \
		echo $$! > .docker-watch.pid; \
	fi
	@echo -e "\033[0;32mContainers started!\033[0m"
	@. ./.env && echo -e "\033[0;34mNginx is running at http://localhost:$${NGINX_PORT:-8080}\033[0m"
	@if [ -f .docker-watch.pid ]; then echo -e "\033[0;34mDocker Compose Watch is running (PID: $$(cat .docker-watch.pid))\033[0m"; fi

##@ Node.js Development

node-dev-full: ## Start full-stack development (Vite + Node.js backend with PM2)
	@echo -e "\033[0;33mStarting full-stack development environment...\033[0m"
	@echo -e "\033[0;34mVite HMR: http://localhost:5173\033[0m"
	@echo -e "\033[0;34mNode.js API: http://localhost:3000\033[0m"
	@echo -e "\033[0;34mNginx Proxy: http://localhost:8080\033[0m"
	@docker compose exec node pnpm run dev:full

node-dev-frontend: ## Start only Vite dev server with PM2
	@echo -e "\033[0;33mStarting Vite dev server...\033[0m"
	@docker compose exec node pnpm run dev:frontend

node-dev-backend: ## Start only Node.js backend with PM2
	@echo -e "\033[0;33mStarting Node.js backend server...\033[0m"
	@docker compose exec node pnpm run dev:backend

node-install: ## Install Node.js dependencies (Docker - requires ENV=development)
	@echo -e "\033[0;33mInstalling Node.js dependencies (Docker)...\033[0m"
	@if [ -f .env ]; then \
		. ./.env && if [ "$$ENV" = "production" ]; then \
			echo -e "\033[0;31mError: node-install requires ENV=development in .env file.\033[0m"; \
			echo -e "\033[0;34mFor production builds, dependencies are installed during 'make build' (see Dockerfile build stage).\033[0m"; \
			echo -e "\033[0;34mTo use node-install, temporarily set ENV=development in .env, or use 'make node-install-local'.\033[0m"; \
			exit 1; \
		fi; \
	fi
	@echo -e "\033[0;34mFixing node_modules permissions...\033[0m"
	@docker compose exec --user root node chown -R node:node /app/node_modules
	@docker compose exec node sh -c 'TMPDIR=/tmp pnpm install'
	@echo -e "\033[0;32mDependencies installed!\033[0m"

node-update: ## Update Node.js dependencies (updates pnpm-lock.yaml on host, node_modules stays in container)
	@echo -e "\033[0;33mUpdating Node.js dependencies...\033[0m"
	@# Copy files to avoid Linux bind mount atomic rename issues
	@CONTAINER=$$(docker create --entrypoint sh \
		-v docker-webdev_node_modules:/app/node_modules \
		docker-webdev-node:latest -c 'pnpm update') && \
	docker cp package.json $$CONTAINER:/app/package.json && \
	docker cp pnpm-lock.yaml $$CONTAINER:/app/pnpm-lock.yaml 2>/dev/null || true && \
	docker start -a $$CONTAINER && \
	docker cp $$CONTAINER:/app/package.json ./package.json && \
	docker cp $$CONTAINER:/app/pnpm-lock.yaml ./pnpm-lock.yaml && \
	docker rm $$CONTAINER >/dev/null
	@echo -e "\033[0;32mDependencies updated!\033[0m"

node-install-local: ## Install Node.js dependencies (Local - IDE code completion only)
	@if command -v pnpm >/dev/null 2>&1; then \
		echo -e "\033[0;33m⚠️  Using local pnpm (version may differ from Docker).\033[0m"; \
		pnpm install; \
		echo -e "\033[0;32mDependencies installed!\033[0m"; \
	else \
		echo -e "\033[0;31mError: Local pnpm not found. Use 'make node-install' instead.\033[0m"; \
		exit 1; \
	fi

node-build: ## Executes the frontend build inside the Node container (uses 'build' stage)
	@echo -e "\033[0;33mExecuting frontend build...\033[0m"
	@$(DC) run --rm --build --target build node pnpm run build

node-up: ## Starts the Node service alongside the standard stack (Uses the default 'asset-server' target)
	@echo -e "\033[0;33mStarting Node service (asset-server target)...\033[0m"
	# NODE_TARGET is unset, so compose.yaml defaults to the 'asset-server' target (sleep infinity).
	@$(MAKE) --silent up

node-app-server-up: ## Starts the Node.js App Server (long-running, uses 'app-server' target) alongside the stack
	@echo -e "\033[0;33mStarting Node.js App Server (app-server target)...\033[0m"
	# Sets NODE_TARGET environment variable to switch the build target to 'app-server'.
	@NODE_TARGET="app-server" $(MAKE) --silent up

node-dev: ## Start Vite dev server with HMR (Hot Module Replacement)
	@echo -e "\033[0;33mStarting Vite dev server with HMR...\033[0m"
	@echo -e "\033[0;34mAccess: http://localhost:5173\033[0m"
	@echo -e "\033[0;34mProxy via Nginx: http://localhost:8080\033[0m"
	@docker compose exec node pnpm run dev

node-server-dev: ## Start Node.js backend in development watch mode (tsx watch)
	@echo -e "\033[0;33mStarting Node.js backend in watch mode...\033[0m"
	@echo -e "\033[0;34mAccess: http://localhost:3000/health\033[0m"
	@docker compose exec node pnpm run server:dev

node-server-build: ## Build Node.js backend (TypeScript -> JavaScript)
	@echo -e "\033[0;33mBuilding Node.js backend...\033[0m"
	@docker compose exec node pnpm run server:build
	@echo -e "\033[0;32mBackend built successfully! Output: dist/server.js\033[0m"

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

backup: ## Create encrypted database backup (GDPR-compliant, RETENTION=days to override)
	@echo -e "\033[0;33mCreating encrypted database backup...\033[0m"
	@if [ -n "$(RETENTION)" ]; then \
		bash docker/scripts/backup-databases.sh --retention $(RETENTION); \
	else \
		bash docker/scripts/backup-databases.sh; \
	fi
	@echo -e "\033[0;34mBackups are stored in ./backups/\033[0m"

backup-list: ## List all available backups
	@echo -e "\033[0;33mAvailable backups:\033[0m"
	@if [ -d backups ]; then \
		ls -lah backups/*.sql.gz* 2>/dev/null || echo -e "\033[0;34mNo backups found.\033[0m"; \
	else \
		echo -e "\033[0;34mNo backups directory. Run 'make backup' first.\033[0m"; \
	fi

restore: ## Restore database from backup (interactive)
	@echo -e "\033[0;33mAvailable backups:\033[0m"
	@if [ -d backups ]; then \
		ls -1 backups/*.sql.gz* 2>/dev/null || echo "No backups found."; \
	else \
		echo "No backups directory."; \
		exit 1; \
	fi
	@echo ""
	@read -p "Enter backup filename (from ./backups/): " BACKUP_FILE; \
	bash docker/scripts/restore-database.sh "backups/$$BACKUP_FILE"

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
			$(DC) -f compose.yaml -f compose.production.yaml --profile php --profile node --profile redis --profile postgres --profile mariadb down -v --rmi all && \
			echo -e "\033[0;34mBuilding Node image first (required by PHP and NGINX)...\033[0m" && \
			$(DC) -f compose.yaml -f compose.production.yaml --profile php --profile node --profile redis --profile postgres --profile mariadb build --no-cache node && \
			echo -e "\033[0;34mBuilding remaining images...\033[0m" && \
			$(DC) -f compose.yaml -f compose.production.yaml --profile php --profile node --profile redis --profile postgres --profile mariadb build --no-cache; \
		else \
			$(DC) --profile php --profile node --profile redis --profile postgres --profile mariadb down -v --rmi all && \
			$(DC) --profile php --profile node --profile redis --profile postgres --profile mariadb build --no-cache; \
		fi; \
	else \
		$(DC) --profile php --profile node --profile redis --profile postgres --profile mariadb down -v --rmi all && \
		$(DC) --profile php --profile node --profile redis --profile postgres --profile mariadb build --no-cache; \
	fi
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

check: cs-check analyse phpmd rector-check test validate ## Run all checks (CI simulation)
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

lint-md: ## Check Markdown files for style issues
	@echo -e "\033[0;33mChecking Markdown files...\033[0m"
	@docker compose exec node pnpm run lint:md
	@echo -e "\033[0;32mMarkdown check completed!\033[0m"

lint-md-fix: ## Fix Markdown style issues automatically
	@echo -e "\033[0;33mFixing Markdown files...\033[0m"
	@docker compose exec node pnpm run lint:md:fix
	@echo -e "\033[0;32mMarkdown files fixed!\033[0m"

outdated: ## Check for outdated Composer dependencies
	@echo -e "\033[0;33mChecking Composer for outdated packages...\033[0m"
	@docker compose exec php composer outdated
	@echo -e "\033[0;32mOutdated check completed!\033[0m"

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

validate: ## Validate composer.json/lock and package.json/lock files
	@echo -e "\033[0;33mValidating Composer configuration...\033[0m"
	@docker compose exec php composer validate --strict
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
	@chmod 700 secrets
	@echo -e "\033[0;33mChecking Docker Secrets...\033[0m"
	@if [ ! -f secrets/db_password.txt ]; then \
		echo -e "\033[0;34mGenerating db_password secret...\033[0m"; \
		openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | head -c 24 > secrets/db_password.txt; \
		chmod 600 secrets/db_password.txt; \
		echo -e "\033[0;32mdb_password secret generated.\033[0m"; \
	else \
		echo -e "\033[0;32mdb_password secret already exists.\033[0m"; \
	fi
	@if [ ! -f secrets/db_root_password.txt ]; then \
		echo -e "\033[0;34mGenerating db_root_password secret...\033[0m"; \
		openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | head -c 24 > secrets/db_root_password.txt; \
		chmod 600 secrets/db_root_password.txt; \
		echo -e "\033[0;32mdb_root_password secret generated.\033[0m"; \
	else \
		echo -e "\033[0;32mdb_root_password secret already exists.\033[0m"; \
	fi
	@if [ ! -f secrets/encryption_key.txt ]; then \
		echo -e "\033[0;34mGenerating encryption_key secret...\033[0m"; \
		openssl rand -base64 32 > secrets/encryption_key.txt; \
		chmod 600 secrets/encryption_key.txt; \
		echo -e "\033[0;32mencryption_key secret generated.\033[0m"; \
	else \
		echo -e "\033[0;32mencryption_key secret already exists.\033[0m"; \
	fi
	@if [ ! -f secrets/backup_encryption_key.txt ]; then \
		echo -e "\033[0;34mGenerating backup_encryption_key secret...\033[0m"; \
		openssl rand -base64 32 > secrets/backup_encryption_key.txt; \
		chmod 600 secrets/backup_encryption_key.txt; \
		echo -e "\033[0;32mbackup_encryption_key secret generated.\033[0m"; \
	else \
		echo -e "\033[0;32mbackup_encryption_key secret already exists.\033[0m"; \
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
			$${COMPOSE_PROJECT_NAME:-docker-webdev}-php:latest; \
	fi
	@echo -e "\033[0;32mSBOM generated in build/sbom-php.json!\033[0m"

security-scan: ## Scan Docker images for vulnerabilities
	@echo -e "\033[0;33mScanning images for vulnerabilities...\033[0m"
	@if [ -f .env ]; then \
		. ./.env && \
		echo "Scanning PHP image..." && \
		docker run --rm -v /var/run/docker.sock:/var/run/docker.sock \
			aquasec/trivy:latest image --severity HIGH,CRITICAL \
			$${COMPOSE_PROJECT_NAME:-docker-webdev}-php:latest 2>/dev/null || \
			echo "⚠️  Image not found. Run 'make build' first." && \
		echo "\nScanning Nginx image..." && \
		docker run --rm -v /var/run/docker.sock:/var/run/docker.sock \
			aquasec/trivy:latest image --severity HIGH,CRITICAL \
			$${COMPOSE_PROJECT_NAME:-docker-webdev}-nginx:latest 2>/dev/null || \
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
	@echo -e "\033[0;32mPHP documentation generated in docs/api/php/\033[0m"

docs-node: ## Generate Node/TypeScript API documentation using TypeDoc
	@echo -e "\033[0;33mGenerating Node/TypeScript API documentation...\033[0m"
	@docker compose exec node pnpm run docs
	@echo -e "\033[0;32mNode documentation generated in docs/api/node/\033[0m"

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
