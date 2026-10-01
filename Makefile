SHELL := /bin/sh
COMPOSE := docker compose
PHP_DEV := $(COMPOSE) --profile tools run --rm -T php-dev
APP_URL ?= http://localhost:$${HTTP_PORT:-8080}

.DEFAULT_GOAL := help
.PHONY: help env up down fresh restart ps logs migrate seed install test test-backend test-frontend e2e stan cs cs-fix lint

help: ## Show available commands
	@grep -E '^[a-zA-Z0-9_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

env: ## Create .env from .env.example (if missing)
	@test -f .env || (cp .env.example .env && echo "Created .env from .env.example")

up: env ## Build images and start the whole stack (app, worker, db, rabbitmq, nginx)
	$(COMPOSE) up -d --build
	@echo "Waiting for the API..."; \
	for i in $$(seq 1 60); do curl -fsS http://localhost:$${HTTP_PORT:-8080}/api/health >/dev/null 2>&1 && break; sleep 2; done; \
	curl -fsS http://localhost:$${HTTP_PORT:-8080}/api/health && echo && echo "Ready: http://localhost:$${HTTP_PORT:-8080} (Swagger: /api/docs)"

down: ## Stop the stack
	$(COMPOSE) down

fresh: ## Stop the stack and DELETE all data (DB, uploads), then start again
	$(COMPOSE) down -v
	$(MAKE) up

restart: ## Restart app containers
	$(COMPOSE) restart app worker nginx

ps: ## Show containers
	$(COMPOSE) ps

logs: ## Follow app + worker logs
	$(COMPOSE) logs -f app worker

migrate: ## Run DB migrations (also run automatically on app start)
	$(COMPOSE) exec app php bin/console migrations:migrate --no-interaction

seed: ## Load fixtures into products/product_attributes/product_images (purges existing data!)
	$(COMPOSE) exec app php bin/console fixtures:load

install: env ## Install backend (composer, via docker) and frontend (npm) dependencies for local dev/tests
	$(COMPOSE) up -d db
	$(PHP_DEV) composer install --no-interaction
	cd frontend && npm ci

test: test-backend test-frontend ## Run backend (PHPUnit) and frontend (Karma) tests

test-backend: env ## PHPUnit: unit + integration (PostgreSQL) + functional API tests
	$(COMPOSE) up -d db
	$(PHP_DEV) vendor/bin/phpunit

test-frontend: ## Angular unit tests (headless Chrome)
	cd frontend && npx ng test --watch=false --browsers=ChromeHeadless

e2e: ## Playwright e2e tests against the running stack (run `make up` first)
	cd frontend && npx playwright install chromium && E2E_BASE_URL=$(APP_URL) npx playwright test

stan: ## PHPStan (level 6)
	$(PHP_DEV) vendor/bin/phpstan analyse --no-progress

cs: ## PHP CS Fixer (dry run)
	$(PHP_DEV) vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## PHP CS Fixer (apply)
	$(PHP_DEV) vendor/bin/php-cs-fixer fix

lint: stan cs ## Static analysis + code style
