SHELL := /bin/sh

PHP ?= php
PNPM ?= pnpm
COMPOSER_CMD ?= composer
DOCKER_COMPOSE ?= docker compose

API_DIR := apps/api
WEB_FILTER := @global-english/web
COMPOSER_VERSION := 2.10.2

.DEFAULT_GOAL := help

.PHONY: help bootstrap check-toolchain dev services-up services-down verify test e2e ai-eval openapi migrate seed build

help:
	@printf '%s\n' \
		'make bootstrap   Install locked dependencies and prepare ignored local environment files' \
		'make dev         Start local services, API, web application and queue worker' \
		'make verify      Run formatting, static analysis, tests and dependency audits' \
		'make test        Run deterministic backend and frontend tests' \
		'make e2e         Run the web bootstrap browser test' \
		'make ai-eval     Run AI evaluation when introduced by PR-011' \
		'make openapi     Validate OpenAPI when introduced by PR-002' \
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

dev: services-up
	$(PNPM) run dev

verify: check-toolchain
	$(COMPOSER_CMD) --working-dir=$(API_DIR) validate --strict --no-check-publish
	$(COMPOSER_CMD) --working-dir=$(API_DIR) audit --locked
	$(PHP) $(API_DIR)/vendor/bin/pint --test
	$(PHP) $(API_DIR)/vendor/bin/phpstan analyse --configuration=$(API_DIR)/phpstan.neon --memory-limit=1G
	cd $(API_DIR) && $(PHP) artisan test
	$(PNPM) install --frozen-lockfile
	$(PNPM) audit --prod --audit-level=high
	$(PNPM) run lint
	$(PNPM) run typecheck
	$(PNPM) run test

test:
	cd $(API_DIR) && $(PHP) artisan test
	$(PNPM) run test

e2e:
	$(PNPM) run e2e

ai-eval:
	@printf '%s\n' 'AI evaluation is not implemented before PR-011.' >&2
	@exit 2

openapi:
	@printf '%s\n' 'OpenAPI validation is not implemented before PR-002.' >&2
	@exit 2

migrate:
	cd $(API_DIR) && $(PHP) artisan migrate

seed:
	cd $(API_DIR) && $(PHP) artisan db:seed

build:
	@cd $(API_DIR) && trap '$(PHP) artisan optimize:clear >/dev/null 2>&1 || true' EXIT; \
		APP_ENV=production APP_DEBUG=false $(PHP) artisan optimize
	$(PNPM) --filter $(WEB_FILTER) build
