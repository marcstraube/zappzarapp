.PHONY: help build up down restart logs clean setup

help: ## Show this help
	@echo -e "\033[0;34mAvailable commands:\033[0m"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[0;32m%-15s\033[0m %s\n", $$1, $$2}'

setup: ## Create necessary directories and .env file
	@echo -e "\033[0;33mCreating project structure...\033[0m"
	@if [ ! -f .env ]; then \
		cp .env.example .env; \
		echo -e "\033[0;32m.env file created. Please adjust settings!\033[0m"; \
	fi
	@. ./.env && mkdir -p $${DATA_DIR:-./data} $${LOG_DIR:-./logs}/{app,nginx,php} app/src vendor
	@echo -e "\033[0;32mSetup completed!\033[0m"

build: ## Build Docker images
	@echo -e "\033[0;33mBuilding Docker images...\033[0m"
	@docker compose build
	@echo -e "\033[0;32mBuild completed!\033[0m"

up: ## Start containers
	@. ./.env && echo -e "\033[0;33mStarting containers in $${ENV^^} mode...\033[0m"
	@. ./.env && mkdir -p $${LOG_DIR:-./logs}/{app,nginx,php}
	@. ./.env && if [ "$$ENV" = "production" ]; then \
		docker compose -f compose.yaml -f compose.prod.yaml up -d; \
	else \
		docker compose up -d; \
	fi
	@echo -e "\033[0;32mContainers started!\033[0m"
	@echo -e "\033[0;34mNginx is running at http://localhost:$${NGINX_PORT:-8080}\033[0m"

down: ## Stop containers
	@echo -e "\033[0;33mStopping containers...\033[0m"
	@docker compose down
	@echo -e "\033[0;32mContainers stopped!\033[0m"

restart: down up ## Restart containers

logs: ## Show logs of all containers
	@docker compose logs -f

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
	@docker compose down -v
	@docker system prune -f
	@echo -e "\033[0;32mCleanup completed!\033[0m"

rebuild: clean build up ## Complete rebuild
