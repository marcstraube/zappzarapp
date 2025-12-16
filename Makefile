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
	}' $(MAKEFILE_LIST) | awk 'BEGIN {FS="\t"} {printf "  \033[0;32m%-15s\033[0m %s\n", $$1, $$2}'

##@ Setup

dev-deps: ## Install/update Composer dependencies (uses local Composer if available)
	@echo -e "\033[0;33mManaging Composer dependencies...\033[0m"
	@if command -v composer >/dev/null 2>&1; then \
		echo "Using local Composer..."; \
		if [ ! -f "vendor/autoload.php" ]; then \
			composer install --prefer-dist --no-interaction; \
		else \
			composer install --prefer-dist --no-interaction --no-scripts; \
		fi; \
	else \
		echo "Local Composer not found, using Docker container..."; \
		XDEBUG_MODE=off docker compose run --rm --no-TTY php sh -c '\
			if [ ! -f "vendor/autoload.php" ]; then \
				composer install --prefer-dist --no-interaction; \
			else \
				composer install --prefer-dist --no-interaction --no-scripts; \
			fi'; \
	fi
	@echo -e "\033[0;32mDependencies ready!\033[0m"

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
	@. ./.env && mkdir -p $${DATA_DIR:-./data} $${LOG_DIR:-./logs}/{app,nginx,php} app/src tests vendor
	@echo -e "\033[0;32mProject structure created!\033[0m"
	@$(MAKE) --silent dev-deps
	@echo -e "\033[0;32mSetup completed (directories + dependencies)!\033[0m"

##@ Docker

build: ## Build Docker images
	@echo -e "\033[0;33mBuilding Docker images...\033[0m"
	@if [ -f .env ]; then \
		. ./.env && if [ "$$ENV" = "production" ]; then \
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

composer: ## Execute Composer command (e.g. make composer CMD="require vendor/package")
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

prune: ## Remove untagged/dangling images related to this project
	@echo -e "\033[0;33mPruning dangling images...\033[0m"
	@. ./.env && docker image prune -f --filter "label=com.docker.compose.project=$$COMPOSE_PROJECT_NAME"

restart: down up ## Restart containers

shell-nginx: ## Open shell in Nginx container
	@docker compose exec nginx sh

shell-php: ## Open shell in PHP container
	@docker compose exec php sh

status: ## Show running containers status and image disk usage
	@echo -e "\033[0;33mContainer Status:\033[0m"
	@docker compose ps
	@echo -e "\033[0;33m\nImage Disk Usage:\033[0m"
	@docker images | grep "$(COMPOSE_PROJECT_NAME:-docker-webdev)"

up: ## Start core containers (Nginx, PHP)
	@$(MAKE) --silent up-core

up-core:
	@if [ ! -f .env ]; then echo -e "\033[0;31mError: .env not found. Run 'make init' first.\033[0m"; exit 1; fi
	@. ./.env && echo -e "\033[0;33mStarting containers in $${ENV^^:-production} mode...\033[0m"
	@. ./.env && mkdir -p $${LOG_DIR:-./logs}/{app,nginx,php}
	@. ./.env && if [ "$$ENV" = "production" ]; then \
		docker compose -f compose.yaml -f compose.prod.yaml up -d nginx php $(SERVICES); \
	else \
		docker compose up -d nginx php $(SERVICES); \
	fi
	@echo -e "\033[0;32mContainers started!\033[0m"
	@. ./.env && echo -e "\033[0;34mNginx is running at http://localhost:$${NGINX_PORT:-8080}\033[0m"

##@ Node Commands

node-build: ## Executes the frontend build inside the Node container (uses 'build' stage)
	@echo -e "\033[0;33mExecuting frontend build...\033[0m"
	@docker compose run --rm --build --target build node pnpm run build

node-shell: ## Starts a shell in the Node container (Development Target)
	@docker compose exec node /bin/sh

node-up: ## Starts the Node service alongside the standard stack (Uses the default 'asset-server' target)
	@echo -e "\033[0;33mStarting Node service (asset-server target)...\033[0m"
	# NODE_TARGET is unset, so compose.yaml defaults to the 'asset-server' target (sleep infinity).
	@$(MAKE) --silent up-core SERVICES="node"

node-app-server-up: ## Starts the Node.js App Server (long-running, uses 'app-server' target) alongside the stack
	@echo -e "\033[0;33mStarting Node.js App Server (app-server target)...\033[0m"
	# Sets NODE_TARGET environment variable to switch the build target to 'app-server'.
	@NODE_TARGET="app-server" $(MAKE) --silent up-core SERVICES="node"

##@ Workflow

check-health: ## Check application health by container status, PHP-FPM and Nginx HTTP response
	@echo -e "\033[0;33mChecking Container Health Status (PHP-FPM & Nginx)...\033[0m"
	@if [ "$$(docker inspect --format='{{.State.Health.Status}}' $$(docker compose ps -q php))" = "healthy" ]; then \
		echo -e "\033[0;32m✅ PHP-FPM Service is Healthy (Container Status).\033[0m"; \
	else \
		echo -e "\033[0;31m❌ PHP-FPM Service is Not Healthy (Container Status). Run 'docker inspect $$(docker compose ps -q php)' for details.\033[0m"; \
		exit 1; \
	fi
	@echo -e "\n\033[0;33mChecking HTTP Health (Nginx)...\033[0m"
	@if [ -f .env ]; then . ./.env; fi; \
	NGINX_PORT=$${NGINX_PORT:-8080}; \
	if command -v curl >/dev/null 2>&1; then \
		HTTP_CODE=$$(curl -s -o /dev/null -w "%{http_code}" http://localhost:$$NGINX_PORT); \
		if [ "$$HTTP_CODE" = "200" ]; then \
			echo -e "\033[0;32m✅ Nginx/Application is running and returns 200 OK on port $$NGINX_PORT.\033[0m"; \
		else \
			echo -e "\033[0;31m❌ Error: Application returned HTTP code $$HTTP_CODE on port $$NGINX_PORT.\033[0m"; \
			exit 1; \
		fi; \
	else \
		echo -e "\033[0;31m❌ Error: 'curl' not found. Cannot perform HTTP health check.\033[0m"; \
	fi

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

check: cs-check analyse test ## Run all checks (CI simulation)
	@echo -e "\033[0;32mAll checks passed!\033[0m"

coverage: ## Run PHPUnit and generate a Code Coverage report (HTML in build/coverage)
	@echo -e "\033[0;33mRunning PHPUnit with coverage report...\033[0m"
	@docker compose exec php composer test -- --coverage-html build/coverage
	@echo -e "\033[0;32mCoverage report generated in build/coverage!\033[0m"

cs-check: ## Check coding style (dry-run)
	@echo -e "\033[0;33mChecking Coding Style...\033[0m"
	@docker compose exec php composer cs-check

cs-fix: ## Fix coding style automatically (uses composer alias)
	@echo -e "\033[0;33mFixing Coding Style...\033[0m"
	@docker compose exec php composer cs-fix

cs-fix-all: ## Fix coding style aggressively on all files (forces fix on source dir)
	@echo -e "\033[0;33mFixing Coding Style aggressively on all files...\033[0m"
	@docker compose exec php vendor/bin/php-cs-fixer fix /var/www/html/app/

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

test: ## Run PHPUnit tests
	@echo -e "\033[0;33mRunning PHPUnit...\033[0m"
	@docker compose exec php composer test

test-debug: ## Run PHPUnit tests with Xdebug enabled
	@echo -e "\033[0;33mRunning PHPUnit with Xdebug (Step Debugging)...\033[0m"
	@docker compose exec php sh -c 'XDEBUG_MODE=develop,debug composer test'

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