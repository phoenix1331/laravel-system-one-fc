.DEFAULT_GOAL := help
.PHONY: help install dev migrate seed fresh test coverage lint analyse audit tinker artisan play scenarios

APP := php

help: ## list available targets
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-10s\033[0m %s\n", $$1, $$2}'

install: ## install dependencies, create .env, migrate, seed and build assets
	@composer install
	@$(APP) -r "file_exists('.env') || copy('.env.example', '.env');"
	@$(APP) artisan key:generate
	@$(APP) artisan migrate --seed --force
	@npm install
	@npm run build

dev: ## start the server, queue worker, logs and Vite
	@$(APP) artisan dev

migrate: ## run migrations
	@$(APP) artisan migrate

seed: ## run seeders
	@$(APP) artisan db:seed

fresh: ## drop all tables, migrate and seed
	@$(APP) artisan migrate:fresh --seed

test: ## run the test suite (in-memory SQLite)
	@$(APP) artisan test

coverage: ## run tests with coverage, fail below 90%
	@$(APP) artisan test --coverage --min=90

scenarios: ## run real-model scenario tests against Ollama
	@RUN_OLLAMA_SCENARIOS=true $(APP) artisan test --group=ollama

lint: ## fix code style with Pint
	@./vendor/bin/pint

analyse: ## run Larastan static analysis
	@./vendor/bin/phpstan analyse --memory-limit=1G

audit: ## scan dependencies for known vulnerabilities
	@osv-scanner --lockfile=composer.lock

tinker: ## open Tinker
	@$(APP) artisan tinker

play: ## play a terminal match, e.g. make play ARGS="'Bala Town' 'Penybont' --seed=42"
	@$(APP) artisan fc:play $(ARGS)

artisan: ## run any artisan command, e.g. make artisan ARGS="route:list"
	@$(APP) artisan $(ARGS)
