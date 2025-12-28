SHELL := /bin/bash
.SHELLFLAGS := -c

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
	}' $(MAKEFILE_LIST) | awk 'BEGIN {FS="\t"} {printf "  \033[0;32m%-20s\033[0m %s\n", $$1, $$2}'

##@ Setup

composer-install: ## Install/update Composer dependencies (Docker - guaranteed consistency)
	@echo -e "\033[0;33mManaging Composer dependencies (Docker)...\033[0m"
	@if [ ! -f "vendor/autoload.php" ]; then \
		XDEBUG_MODE=off docker compose run --rm --no-TTY php composer install --prefer-dist --no-interaction; \
	else \
		XDEBUG_MODE=off docker compose run --rm --no-TTY php composer install --prefer-dist --no-interaction --no-scripts; \
	fi
	@echo -e "\033[0;32mDependencies ready!\033[0m"

composer-install-local: ## Install/update Composer dependencies (Local - IDE code completion only)
	@if command -v composer >/dev/null 2>&1; then \
		echo -e "\033[0;33m⚠️  Using local Composer (version may differ from Docker).\033[0m"; \
		echo -e "\033[0;34mFor guaranteed consistency, use 'make composer-install' instead.\033[0m"; \
		if [ ! -f "vendor/autoload.php" ]; then \
			composer install --prefer-dist --no-interaction; \
		else \
			composer install --prefer-dist --no-interaction --no-scripts; \
		fi; \
		echo -e "\033[0;32mDependencies installed!\033[0m"; \
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
	@. ./.env && mkdir -p vendor

	# Source directories
	@mkdir -p src/php/{Http/{Controller,Middleware},Domain,Infrastructure/{Database,Cache}}
	@mkdir -p src/node/{routes,controllers,services,middleware}

	# Resources directories (Frontend source)
	@mkdir -p resources/{js/components,css/components,images,fonts}

	# Public directory (Web root)
	@mkdir -p public/build

	# Tests (separated by language like src/)
	@mkdir -p tests/php/{Unit,Feature}
	@mkdir -p tests/node/{unit,integration}

	# Build & Coverage directories (excluded from IDE indexing)
	@mkdir -p build/{coverage,vitest-report}

	# Config & Templates
	@mkdir -p config templates

	# Storage (Runtime data) - Set permissions
	@. ./.env && mkdir -p $${STORAGE_DIR:-./storage}/{app/{uploads,generated},cache,sessions}
	@. ./.env && chmod 770 $${STORAGE_DIR:-./storage} -R

	@echo -e "\033[0;32mProject structure created!\033[0m"
	@$(MAKE) --silent composer-install
	@$(MAKE) --silent node-install
	@echo -e "\033[0;32mSetup completed (directories + dependencies)!\033[0m"

##@ Docker

build: ## Build Docker images
	@echo -e "\033[0;33mBuilding Docker images...\033[0m"
	@if [ -f .env ]; then \
		. ./.env && if [ "$$ENV" = "production" ]; then \
			echo -e "\033[0;34mBuilding Node image first (required by PHP and NGINX)...\033[0m" && \
			docker compose -f compose.yaml -f compose.prod.yaml build node && \
			echo -e "\033[0;34mBuilding remaining images...\033[0m" && \
			docker compose -f compose.yaml -f compose.prod.yaml build; \
		else \
			docker compose build; \
		fi; \
	else \
		docker compose build; \
	fi
	@echo -e "\033[0;32mBuild completed!\033[0m"

clean: ## Remove containers, networks and dangling images (keeps data volumes)
	@echo -e "\033[0;33mCleaning up...\033[0m"
	@if [ -f .env ]; then \
		. ./.env && if [ "$$ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.prod.yaml down; \
		else \
			docker compose down; \
		fi; \
	else \
		docker compose down; \
	fi
	@docker system prune -f
	@echo -e "\033[0;32mCleanup completed!\033[0m"

composer: ## Execute Composer command in running container (e.g. make composer CMD="require vendor/package")
	@docker compose exec php composer $(CMD)

down: ## Stop containers
	@echo -e "\033[0;33mStopping containers...\033[0m"
	@if [ -f .env ]; then \
		. ./.env && if [ "$$ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.prod.yaml down; \
		else \
			docker compose down; \
		fi; \
	else \
		docker compose down; \
	fi
	@echo -e "\033[0;32mContainers stopped!\033[0m"

logs: ## Show logs of all containers
	@if [ -f .env ]; then \
		. ./.env && if [ "$$ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.prod.yaml logs -f; \
		else \
			docker compose logs -f; \
		fi; \
	else \
		docker compose logs -f; \
	fi

logs-nginx: ## Show Nginx logs only
	@if [ -f .env ]; then \
		. ./.env && if [ "$$ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.prod.yaml logs -f nginx; \
		else \
			docker compose logs -f nginx; \
		fi; \
	else \
		docker compose logs -f nginx; \
	fi

logs-php: ## Show PHP logs only
	@if [ -f .env ]; then \
		. ./.env && if [ "$$ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.prod.yaml logs -f php; \
		else \
			docker compose logs -f php; \
		fi; \
	else \
		docker compose logs -f php; \
	fi

logs-node: ## Show Node.js logs only
	@if [ -f .env ]; then \
		. ./.env && if [ "$$ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.prod.yaml logs -f node; \
		else \
			docker compose logs -f node; \
		fi; \
	else \
		docker compose logs -f node; \
	fi

logs-redis: ## Show Redis logs only
	@if [ -f .env ]; then \
		. ./.env && if [ "$$ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.prod.yaml logs -f redis; \
		else \
			docker compose logs -f redis; \
		fi; \
	else \
		docker compose logs -f redis; \
	fi

logs-postgres: ## Show PostgreSQL logs only
	@if [ -f .env ]; then \
		. ./.env && if [ "$$ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.prod.yaml logs -f postgres; \
		else \
			docker compose logs -f postgres; \
		fi; \
	else \
		docker compose logs -f postgres; \
	fi

logs-mariadb: ## Show MariaDB logs only
	@if [ -f .env ]; then \
		. ./.env && if [ "$$ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.prod.yaml logs -f mariadb; \
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
	@$(MAKE) --silent up-core

up-core:
	@if [ ! -f .env ]; then echo -e "\033[0;31mError: .env not found. Run 'make init' first.\033[0m"; exit 1; fi
	@. ./.env && \
	PROFILES="--profile $${DB_TYPE:-postgres}"; \
	SERVICES="nginx"; \
	if [ "$${ENABLE_PHP:-true}" = "true" ]; then PROFILES="$$PROFILES --profile php"; SERVICES="$$SERVICES php"; fi; \
	if [ "$${ENABLE_NODE:-true}" = "true" ]; then PROFILES="$$PROFILES --profile node"; SERVICES="$$SERVICES node"; fi; \
	if [ "$${ENABLE_REDIS:-true}" = "true" ]; then PROFILES="$$PROFILES --profile redis"; SERVICES="$$SERVICES redis"; fi; \
	echo -e "\033[0;33mStarting containers in $${ENV^^:-production} mode...\033[0m"; \
	echo -e "\033[0;34mActive services: $$SERVICES\033[0m"; \
	echo -e "\033[0;34mDatabase: $${DB_TYPE:-postgres}\033[0m"; \
	if [ "$$ENV" = "production" ]; then \
		docker compose -f compose.yaml -f compose.prod.yaml $$PROFILES up -d $$SERVICES $(SERVICES); \
	else \
		docker compose $$PROFILES up -d $$SERVICES $(SERVICES); \
	fi
	@echo -e "\033[0;32mContainers started!\033[0m"
	@. ./.env && echo -e "\033[0;34mNginx is running at http://localhost:$${NGINX_PORT:-8080}\033[0m"

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

node-install-local: ## Install Node.js dependencies (Local - IDE code completion only)
	@if command -v pnpm >/dev/null 2>&1; then \
		echo -e "\033[0;33m⚠️  Using local pnpm (version may differ from Docker).\033[0m"; \
		echo -e "\033[0;34mFor guaranteed consistency, use 'make node-install' instead.\033[0m"; \
		pnpm install; \
		echo -e "\033[0;32mDependencies installed!\033[0m"; \
	else \
		echo -e "\033[0;31mError: Local pnpm not found. Use 'make node-install' instead.\033[0m"; \
		exit 1; \
	fi

node-build: ## Executes the frontend build inside the Node container (uses 'build' stage)
	@echo -e "\033[0;33mExecuting frontend build...\033[0m"
	@docker compose run --rm --build --target build node pnpm run build

node-up: ## Starts the Node service alongside the standard stack (Uses the default 'asset-server' target)
	@echo -e "\033[0;33mStarting Node service (asset-server target)...\033[0m"
	# NODE_TARGET is unset, so compose.yaml defaults to the 'asset-server' target (sleep infinity).
	@$(MAKE) --silent up-core SERVICES="node"

node-app-server-up: ## Starts the Node.js App Server (long-running, uses 'app-server' target) alongside the stack
	@echo -e "\033[0;33mStarting Node.js App Server (app-server target)...\033[0m"
	# Sets NODE_TARGET environment variable to switch the build target to 'app-server'.
	@NODE_TARGET="app-server" $(MAKE) --silent up-core SERVICES="node"

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

##@ Workflow

check-health: ## Check application health by container status for all services
	@echo -e "\033[0;33mChecking Container Health Status...\033[0m\n"

	@echo -e "\033[0;34m📦 PHP-FPM:\033[0m"
	@if [ "$$(docker inspect --format='{{.State.Health.Status}}' $$(docker compose ps -q php) 2>/dev/null)" = "healthy" ]; then \
		echo -e "\033[0;32m  ✅ Healthy\033[0m"; \
	else \
		echo -e "\033[0;31m  ❌ Unhealthy or not running\033[0m"; \
	fi

	@echo -e "\n\033[0;34m💾 Database:\033[0m"
	@if [ -f .env ]; then . ./.env; fi; \
	DB_TYPE=$${DB_TYPE:-postgres}; \
	if [ "$$(docker inspect --format='{{.State.Health.Status}}' $$(docker compose ps -q $$DB_TYPE) 2>/dev/null)" = "healthy" ]; then \
		echo -e "\033[0;32m  ✅ $$DB_TYPE is Healthy\033[0m"; \
	else \
		echo -e "\033[0;31m  ❌ $$DB_TYPE is Unhealthy or not running\033[0m"; \
	fi

	@echo -e "\n\033[0;34m🔴 Redis:\033[0m"
	@if [ "$$(docker inspect --format='{{.State.Health.Status}}' $$(docker compose ps -q redis) 2>/dev/null)" = "healthy" ]; then \
		echo -e "\033[0;32m  ✅ Healthy\033[0m"; \
	else \
		echo -e "\033[0;31m  ❌ Unhealthy or not running\033[0m"; \
	fi

	@echo -e "\n\033[0;34m🌐 Nginx HTTP:\033[0m"
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
	@echo -e "\033[0;33mProceeding with fresh rebuild...\033[0m"
	@if [ -f .env ]; then \
		. ./.env && if [ "$$ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.prod.yaml down -v --rmi all; \
		else \
			docker compose down -v --rmi all; \
		fi; \
	else \
		docker compose down -v --rmi all; \
	fi
	@$(MAKE) --silent build
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
	@docker compose exec php composer phpmd

check: cs-check analyse phpmd test ## Run all checks (CI simulation)
	@echo -e "\033[0;32mAll checks passed!\033[0m"

cs-check: ## Check coding style (dry-run)
	@echo -e "\033[0;33mChecking Coding Style...\033[0m"
	@docker compose exec php composer cs-check

cs-fix: ## Fix coding style automatically (uses composer alias)
	@echo -e "\033[0;33mFixing Coding Style...\033[0m"
	@docker compose exec php composer cs-fix

cs-fix-all: ## Fix coding style aggressively on all files (forces fix on source dir)
	@echo -e "\033[0;33mFixing Coding Style aggressively on all files...\033[0m"
	@docker compose exec php vendor/bin/php-cs-fixer fix /var/www/html/src/php/

lint-config: ## Validate YAML configuration files (uses local YAMLlint if available)
	@echo -e "\033[0;33mValidating YAML configuration...\033[0m"
	@if command -v yamllint >/dev/null 2>&1; then \
		echo "Using local YAMLlint..."; \
		yamllint ./**/*.yaml; \
	else \
		echo "Local YAMLlint not found, using Docker container..."; \
		docker run --rm -v $$(pwd):/app -w /app cytopia/yamllint:latest ./**/*.yaml; \
	fi
	@echo -e "\033[0;32mYAML configuration check completed!\033[0m"

outdated: ## Check for outdated Composer dependencies (uses local Composer if available)
	@echo -e "\033[0;33mChecking Composer for outdated packages...\033[0m"
	@if command -v composer >/dev/null 2>&1; then \
		echo "Using local Composer..."; \
		composer outdated; \
	else \
		echo "Local Composer not found, using Docker container..."; \
		docker compose exec php composer outdated; \
	fi
	@echo -e "\033[0;32mOutdated check completed!\033[0m"

test: test-php test-node ## Run all tests (PHP + Node.js)
	@echo -e "\033[0;32mAll tests completed!\033[0m"

test-coverage: test-coverage-php test-coverage-node ## Generate coverage reports for PHP and Node.js
	@echo -e "\033[0;32mAll coverage reports generated!\033[0m"
	@echo -e "\033[0;34mPHP Coverage: build/coverage/index.html (PHPUnit)\033[0m"
	@echo -e "\033[0;34mNode.js Coverage: build/coverage/index.html (Vitest)\033[0m"
	@echo -e "\033[0;33mNote: Both reports use the same directory. Run separately to avoid conflicts.\033[0m"

test-php: ## Run PHPUnit tests
	@echo -e "\033[0;33mRunning PHPUnit tests...\033[0m"
	@docker compose exec php composer test

test-php-debug: ## Run PHPUnit tests with Xdebug enabled
	@echo -e "\033[0;33mRunning PHPUnit with Xdebug (Step Debugging)...\033[0m"
	@docker compose exec php sh -c 'XDEBUG_MODE=develop,debug composer test'

test-coverage-php: ## Generate PHPUnit coverage report (HTML in build/coverage)
	@echo -e "\033[0;33mRunning PHPUnit with coverage report...\033[0m"
	@docker compose exec php sh -c 'XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html build/coverage --coverage-clover build/coverage/clover.xml'
	@echo -e "\033[0;32mPHP coverage report generated in build/coverage/index.html!\033[0m"

test-node: ## Run Vitest tests
	@echo -e "\033[0;33mRunning Vitest tests...\033[0m"
	@docker compose exec node pnpm test

test-node-watch: ## Run Vitest in watch mode
	@echo -e "\033[0;33mRunning Vitest in watch mode...\033[0m"
	@docker compose exec node pnpm test:watch

test-coverage-node: ## Generate Vitest coverage report (HTML in build/coverage)
	@echo -e "\033[0;33mRunning Vitest with coverage report...\033[0m"
	@docker compose exec node pnpm test:coverage
	@echo -e "\033[0;32mNode.js coverage report generated in build/coverage!\033[0m"

validate: ## Validate composer.json and composer.lock files (uses local Composer if available)
	@echo -e "\033[0;33mValidating Composer configuration...\033[0m"
	@if command -v composer >/dev/null 2>&1; then \
		echo "Using local Composer..."; \
		composer validate --strict; \
	else \
		echo "Local Composer not found, using Docker..."; \
		docker compose exec php composer validate --strict; \
	fi
	@echo -e "\033[0;32mComposer configuration is valid!\033[0m"

##@ Security

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