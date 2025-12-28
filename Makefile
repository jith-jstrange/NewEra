# Newera WordPress Plugin - Makefile
# Simplifies common development and deployment tasks

.PHONY: help install build deploy test clean up down logs shell db-backup db-restore

# Default target
.DEFAULT_GOAL := help

# Colors for output
BLUE := \033[0;34m
GREEN := \033[0;32m
YELLOW := \033[0;33m
NC := \033[0m # No Color

##@ General

help: ## Display this help message
	@echo "$(BLUE)Newera WordPress Plugin - Available Commands$(NC)"
	@echo ""
	@awk 'BEGIN {FS = ":.*##"; printf "Usage:\n  make $(YELLOW)<target>$(NC)\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  $(GREEN)%-20s$(NC) %s\n", $$1, $$2 } /^##@/ { printf "\n$(BLUE)%s$(NC)\n", substr($$0, 5) } ' $(MAKEFILE_LIST)

##@ Installation & Setup

install: ## Install all dependencies (Composer + NPM)
	@echo "$(BLUE)Installing dependencies...$(NC)"
	@if [ -f composer.json ]; then composer install; fi
	@if [ -f package.json ]; then npm install; fi
	@echo "$(GREEN)✓ Dependencies installed$(NC)"

setup: ## Initial setup - create .env and install dependencies
	@echo "$(BLUE)Setting up project...$(NC)"
	@if [ ! -f .env ]; then cp .env.example .env; echo "$(YELLOW)⚠ Created .env file - please configure it!$(NC)"; fi
	@make install
	@echo "$(GREEN)✓ Setup complete$(NC)"

##@ Development

dev: ## Start development environment
	@echo "$(BLUE)Starting development environment...$(NC)"
	@docker-compose up -d
	@echo "$(GREEN)✓ Development environment started$(NC)"
	@echo "Access WordPress at: http://localhost:8080"

dev-watch: ## Start development with asset watching
	@echo "$(BLUE)Starting development environment with asset watching...$(NC)"
	@docker-compose up -d
	@npm run watch

build: ## Build production assets
	@echo "$(BLUE)Building production assets...$(NC)"
	@npm run build
	@echo "$(GREEN)✓ Assets built$(NC)"

build-dev: ## Build development assets
	@echo "$(BLUE)Building development assets...$(NC)"
	@npm run build:dev
	@echo "$(GREEN)✓ Development assets built$(NC)"

##@ Docker Operations

up: ## Start Docker containers
	@docker-compose up -d

down: ## Stop Docker containers
	@docker-compose down

restart: ## Restart Docker containers
	@docker-compose restart

logs: ## View container logs
	@docker-compose logs -f

logs-wordpress: ## View WordPress container logs
	@docker-compose logs -f wordpress

logs-db: ## View database container logs
	@docker-compose logs -f db

shell: ## Access WordPress container shell
	@docker-compose exec wordpress bash

shell-db: ## Access database container shell
	@docker-compose exec db mysql -u wordpress -pwordpress wordpress

ps: ## Show container status
	@docker-compose ps

##@ Testing & Quality

test: ## Run PHP tests
	@echo "$(BLUE)Running tests...$(NC)"
	@if [ -f phpunit.xml ]; then \
		docker-compose exec wordpress vendor/bin/phpunit; \
	else \
		echo "$(YELLOW)No phpunit.xml found$(NC)"; \
	fi

test-coverage: ## Run tests with coverage
	@echo "$(BLUE)Running tests with coverage...$(NC)"
	@docker-compose exec wordpress vendor/bin/phpunit --coverage-html coverage

lint: ## Run linters (PHP + JS + CSS)
	@echo "$(BLUE)Running linters...$(NC)"
	@npm run lint

lint-fix: ## Auto-fix linting issues
	@echo "$(BLUE)Fixing linting issues...$(NC)"
	@npm run format

##@ Database Operations

db-backup: ## Backup database
	@echo "$(BLUE)Backing up database...$(NC)"
	@mkdir -p backups
	@docker-compose exec -T db mysqldump -u wordpress -pwordpress wordpress > backups/backup-$$(date +%Y%m%d-%H%M%S).sql
	@echo "$(GREEN)✓ Database backed up to backups/$(NC)"

db-restore: ## Restore database from latest backup
	@echo "$(BLUE)Restoring database from latest backup...$(NC)"
	@docker-compose exec -T db mysql -u wordpress -pwordpress wordpress < $$(ls -t backups/*.sql | head -1)
	@echo "$(GREEN)✓ Database restored$(NC)"

db-reset: ## Reset database (WARNING: Deletes all data!)
	@echo "$(YELLOW)⚠ WARNING: This will delete all database data!$(NC)"
	@read -p "Are you sure? [y/N]: " confirm; \
	if [ "$$confirm" = "y" ]; then \
		docker-compose down -v; \
		docker-compose up -d; \
		echo "$(GREEN)✓ Database reset$(NC)"; \
	fi

##@ Deployment

deploy: ## Deploy to production (build assets + optimize)
	@echo "$(BLUE)Deploying to production...$(NC)"
	@npm run deploy
	@./deploy.sh
	@echo "$(GREEN)✓ Deployment complete$(NC)"

deploy-quick: ## Quick deploy without rebuilding containers
	@echo "$(BLUE)Quick deploying...$(NC)"
	@npm run build
	@docker-compose restart
	@echo "$(GREEN)✓ Quick deployment complete$(NC)"

##@ Cleanup

clean: ## Clean build artifacts and caches
	@echo "$(BLUE)Cleaning build artifacts...$(NC)"
	@rm -rf dist/
	@rm -rf node_modules/.cache/
	@rm -rf vendor/
	@echo "$(GREEN)✓ Clean complete$(NC)"

clean-all: clean ## Clean everything including dependencies
	@echo "$(BLUE)Cleaning all dependencies...$(NC)"
	@rm -rf node_modules/
	@rm -f package-lock.json
	@rm -f composer.lock
	@echo "$(GREEN)✓ Deep clean complete$(NC)"

clean-docker: ## Remove all Docker containers and volumes
	@echo "$(YELLOW)⚠ This will remove all Docker data$(NC)"
	@docker-compose down -v
	@echo "$(GREEN)✓ Docker cleanup complete$(NC)"

##@ Utilities

info: ## Display project information
	@echo "$(BLUE)Project Information$(NC)"
	@echo "Name: Newera WordPress Plugin"
	@echo "Version: 1.0.0"
	@echo "PHP Version: $$(docker-compose exec wordpress php -v | head -1)"
	@echo "WordPress Port: $$(grep WORDPRESS_PORT .env | cut -d '=' -f2 || echo '8080')"
	@echo "Database Status: $$(docker-compose ps db | grep Up && echo '✓ Running' || echo '✗ Stopped')"
	@echo "WordPress Status: $$(docker-compose ps wordpress | grep Up && echo '✓ Running' || echo '✗ Stopped')"

health: ## Check health of all services
	@echo "$(BLUE)Checking service health...$(NC)"
	@docker-compose ps
	@echo ""
	@curl -s http://localhost:8080 > /dev/null && echo "$(GREEN)✓ WordPress is accessible$(NC)" || echo "$(YELLOW)⚠ WordPress not accessible$(NC)"

update: ## Update dependencies
	@echo "$(BLUE)Updating dependencies...$(NC)"
	@composer update
	@npm update
	@echo "$(GREEN)✓ Dependencies updated$(NC)"
