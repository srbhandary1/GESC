SHELL := /bin/sh

PHP ?= php
PNPM ?= pnpm
COMPOSER_CMD ?= composer
DOCKER_COMPOSE ?= docker compose

API_DIR := apps/api
WEB_FILTER := @global-english/web
COMPOSER_VERSION := 2.10.2

.DEFAULT_GOAL := help

.PHONY: help bootstrap check-toolchain check-local-auth-env dev services-up services-down verify test e2e ai-eval openapi migrate seed build

help:
	@printf '%s\n' \
		'make bootstrap   Install locked dependencies and prepare ignored local environment files' \
		'make dev         Start local services, API, web application and queue worker' \
		'make verify      Run formatting, static analysis, tests and dependency audits' \
		'make test        Run deterministic backend and frontend tests' \
		'make e2e         Run the critical web browser journeys' \
		'make ai-eval     Run the locked PR-009 synthetic speech benchmark' \
		'make openapi     Validate OpenAPI and check generated-client drift' \
		'make migrate     Apply local Laravel migrations' \
		'make seed        Load safe local Laravel seed data' \
		'make build       Build the API configuration and React production bundle'

check-toolchain:
	@sh scripts/check-toolchain.sh
	@$(COMPOSER_CMD) --version --no-ansi | grep -q 'Composer version $(COMPOSER_VERSION) '

bootstrap: check-toolchain
	@test -f .env || cp .env.example .env
	@test -f $(API_DIR)/.env || cp $(API_DIR)/.env.example $(API_DIR)/.env
	$(COMPOSER_CMD) --working-dir=$(API_DIR) install --no-interaction --prefer-dist
	$(PNPM) install --frozen-lockfile
	@grep -q '^APP_KEY=base64:' $(API_DIR)/.env || $(PHP) $(API_DIR)/artisan key:generate

services-up:
	$(DOCKER_COMPOSE) up -d --wait

services-down:
	$(DOCKER_COMPOSE) down

check-local-auth-env:
	@sh scripts/check-local-auth-env.sh

dev: check-local-auth-env
	$(MAKE) services-up
	$(PNPM) run dev

verify: check-toolchain
	$(COMPOSER_CMD) --working-dir=$(API_DIR) validate --strict --no-check-all --no-check-publish
	$(COMPOSER_CMD) --working-dir=$(API_DIR) audit --locked
	cd $(API_DIR) && $(PHP) vendor/bin/pint --test
	$(PHP) $(API_DIR)/vendor/bin/phpstan analyse --configuration=$(API_DIR)/phpstan.neon --memory-limit=1G
	cd $(API_DIR) && $(PHP) artisan test
	cd $(API_DIR) && $(PHP) vendor/bin/phpunit --configuration=phpunit.postgres.xml
	$(PNPM) install --frozen-lockfile
	$(PNPM) audit --audit-level=high
	$(PNPM) run locale:check
	$(MAKE) openapi
	$(PNPM) run lint
	$(PNPM) run typecheck
	$(PNPM) run test

test:
	cd $(API_DIR) && $(PHP) artisan test
	cd $(API_DIR) && $(PHP) vendor/bin/phpunit --configuration=phpunit.postgres.xml
	$(PNPM) run test

e2e:
	$(PNPM) run e2e

ai-eval:
	cd $(API_DIR) && $(PHP) artisan speech:benchmark

openapi:
	$(PNPM) run openapi:check

migrate:
	cd $(API_DIR) && $(PHP) artisan migrate

seed:
	cd $(API_DIR) && $(PHP) artisan db:seed

build:
	@cd $(API_DIR) || exit 1; \
		build_status=0; \
		APP_ENV=production APP_DEBUG=false $(PHP) artisan optimize || build_status=$$?; \
		cleanup_status=0; \
		APP_ENV=production APP_DEBUG=false $(PHP) artisan config:clear || cleanup_status=$$?; \
		if [ $$cleanup_status -eq 0 ]; then \
			APP_ENV=production APP_DEBUG=false CACHE_STORE=array $(PHP) artisan optimize:clear || cleanup_status=$$?; \
		fi; \
		test $$build_status -eq 0 && test $$cleanup_status -eq 0
	$(PNPM) --filter $(WEB_FILTER) build
