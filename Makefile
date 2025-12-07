.PHONY: help init setup dev-deps hooks-install build up down restart logs logs-nginx logs-php shell-php shell-nginx \
	composer clean prune rebuild status test coverage analyse cs-check cs-fix check lint-config security-scan \
	security-config security-sbom falco-run

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
	@$(MAKE) dev-deps
	@echo -e "\033[0;32mSetup completed (directories + dependencies)!\033[0m"

dev-deps: ## Install/update Composer dependencies (uses local composer if available)
	@echo -e "\033[0;33mManaging Composer dependencies...\033[0m"
	@if command -v composer >/dev/null 2>&1; then \
		echo "Using local Composer..."; \
		if [ ! -f "vendor/autoload.php" ]; then \
			composer install --prefer-dist --no-interaction; \
		else \
			composer install --prefer-dist --no-interaction --no-scripts; \
		fi; \
	else \
		echo "Local Composer not found, using Docker..."; \
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

up: ## Start containers
	@if [ ! -f .env ]; then echo -e "\033[0;31mError: .env not found. Run 'make init' first.\033[0m"; exit 1; fi
	@. ./.env && echo -e "\033[0;33mStarting containers in $${ENV^^:-production} mode...\033[0m"
	@. ./.env && mkdir -p $${LOG_DIR:-./logs}/{app,nginx,php}
	@. ./.env && if [ "$$ENV" = "production" ]; then \
		docker compose -f compose.yaml -f compose.prod.yaml up -d; \
	else \
		docker compose up -d; \
	fi
	@echo -e "\033[0;32mContainers started!\033[0m"
	@. ./.env && echo -e "\033[0;34mNginx is running at http://localhost:$${NGINX_PORT:-8080}\033[0m"

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

restart: down up ## Restart containers

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
	@docker compose logs -f nginx

logs-php: ## Show PHP logs only
	@docker compose logs -f php

shell-php: ## Open shell in PHP container
	@docker compose exec php sh

shell-nginx: ## Open shell in Nginx container
	@docker compose exec nginx sh

composer: ## Execute Composer command (e.g. make composer CMD="require vendor/package")
	@docker compose exec php composer $(CMD)

clean: ## Remove containers, volumes and images
	@echo -e "\033[0;33mCleaning up...\033[0m"
	@if [ -f .env ]; then \
		. ./.env && if [ "$$ENV" = "production" ]; then \
			docker compose -f compose.yaml -f compose.prod.yaml down -v; \
		else \
			docker compose down -v; \
		fi; \
	else \
		docker compose down -v; \
	fi
	@docker system prune -f
	@echo -e "\033[0;32mCleanup completed!\033[0m"

prune: ## Remove untagged/dangling images related to this project
	@echo -e "\033[0;33mPruning dangling images...\033[0m"
	@. ./.env && docker image prune -f --filter "label=com.docker.compose.project=$$COMPOSE_PROJECT_NAME"

rebuild: clean build up ## Complete rebuild

status: ## Show running containers status and image disk usage
	@echo -e "\033[0;33mContainer Status:\033[0m"
	@docker compose ps
	@echo -e "\033[0;33m\nImage Disk Usage:\033[0m"
	@docker images | grep $(COMPOSE_PROJECT_NAME:-docker-webdev)

##@ Quality Assurance

test: ## Run PHPUnit tests
	@echo -e "\033[0;33mRunning PHPUnit...\033[0m"
	@docker compose exec php composer test

coverage: ## Run PHPUnit and generate a Code Coverage report (HTML in build/coverage)
	@echo -e "\033[0;33mRunning PHPUnit with coverage report...\033[0m"
	@docker compose exec php composer test -- --coverage-html build/coverage
	@echo -e "\033[0;32mCoverage report generated in build/coverage!\033[0m"

analyse: ## Run PHPStan static analysis
	@echo -e "\033[0;33mRunning PHPStan...\033[0m"
	@docker compose exec php composer analyse

cs-check: ## Check coding style (dry-run)
	@echo -e "\033[0;33mChecking Coding Style...\033[0m"
	@docker compose exec php composer cs-check

cs-fix: ## Fix coding style automatically
	@echo -e "\033[0;33mFixing Coding Style...\033[0m"
	@docker compose exec php composer cs-fix

check: cs-check analyse test ## Run all checks (CI simulation)
	@echo -e "\033[0;32mAll checks passed!\033[0m"

lint-config: ## Validate YAML configuration files
	@echo -e "\033[0;33mValidating YAML configuration...\033[0m"
	@if command -v yamllint >/dev/null 2>&1; then \
		echo "Using local yamllint..."; \
		yamllint ./**/*.yaml; \
	else \
		echo "Local yamllint not found, using Docker container..."; \
		docker run --rm -v $$(pwd):/app -w /app cytopia/yamllint:latest ./**/*.yaml; \
	fi
	@echo -e "\033[0;32mYAML configuration check completed!\033[0m"

##@ Security

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

security-config: ## Check Dockerfiles for misconfigurations
	@echo -e "\033[0;33mScanning Dockerfiles for security issues...\033[0m"
	@docker run --rm -v $$(pwd):/project \
		aquasec/trivy:latest config /project/docker
	@echo -e "\033[0;32mConfiguration scan completed!\033[0m"

security-sbom: ## Generate a Software Bill of Materials (SBOM) using Trivy
	@echo -e "\033[0;33mGenerating SBOM for PHP image...\033[0m"
	@if [ -f .env ]; then \
		. ./.env && \
		docker run --rm -v /var/run/docker.sock:/var/run/docker.sock \
			aquasec/trivy:latest image --format cyclonedx --output build/sbom-php.json \
			$${COMPOSE_PROJECT_NAME:-docker-webdev}-php:latest; \
	fi
	@echo -e "\033[0;32mSBOM generated in build/sbom-php.json!\033[0m"

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