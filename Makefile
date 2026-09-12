SHELL := /bin/sh

PHP ?= php
PNPM ?= pnpm
COMPOSER_CMD ?= composer
DOCKER_COMPOSE ?= docker compose

API_DIR := apps/api
WEB_DIR := apps/web
WEB_FILTER := @global-english/web
MARKETING_FILTER := @global-english/marketing
COMPOSER_VERSION := 2.10.2

.DEFAULT_GOAL := help

.PHONY: help bootstrap check-toolchain check-local-auth-env dev demo demo-build demo-serve demo-client demo-client-check demo-client-status demo-client-stop demo-client-tunnel demo-client-tunnel-status demo-client-tunnel-stop demo-android android-closed-beta services-up services-down verify test e2e demo-e2e demo-dist-e2e ai-eval openapi migrate seed build load-soak backup-restore-drill staged-deploy-dry-run marketing marketing-build

help:
	@printf '%s\n' \
		'make bootstrap     Install locked dependencies and prepare ignored local environment files' \
		'make dev           Start local services, API, web application and queue worker' \
		'make demo          Start the synthetic in-memory browser demo on 127.0.0.1' \
		'make demo-build    Build the distributable synthetic demo bundle into apps/web/dist-demo' \
		'make demo-serve    Serve the built synthetic demo bundle on 127.0.0.1:4175' \
		'make demo-client   Serve the synthetic demo on 0.0.0.0:8080 for static-IP client testing' \
		'make demo-client-tunnel  Temporary HTTPS Cloudflare tunnel for remote client testing' \
		'make demo-android  Build the Android demo APK from the synthetic demo bundle' \
		'make android-closed-beta  Build the PR-014 production-path closed-beta Android APK' \
		'make verify        Run formatting, static analysis, tests and dependency audits' \
		'make test          Run deterministic backend and frontend tests' \
		'make e2e           Run the critical web browser journeys' \
		'make demo-e2e      Run the synthetic local-demo browser journey' \
		'make demo-dist-e2e Run the browser journey against the built demo bundle' \
		'make ai-eval       Run locked AI evaluation; speech complete when SPEECH_BENCHMARK_RUN_ID is set' \
		'make openapi       Validate OpenAPI and check generated-client drift' \
		'make migrate       Apply local Laravel migrations' \
		'make seed          Load safe local Laravel seed data' \
		'make build         Build the API configuration, React production bundle and marketing site' \
		'make marketing     Start the public marketing homepage on 127.0.0.1:5174' \
		'make marketing-build  Build the static marketing homepage into apps/marketing/dist' \
		'make load-soak     PH-06 health load/soak harness (requires reachable API)' \
		'make backup-restore-drill  PH-07 non-production Postgres dump/restore drill' \
		'make staged-deploy-dry-run PH-09 build + staged deploy dry-run evidence'

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

demo:
	@sh scripts/run-local-demo.sh

demo-build:
	$(PNPM) --filter $(WEB_FILTER) build:demo

demo-serve:
	$(PNPM) --filter $(WEB_FILTER) preview:demo

# Opt-in remote client testing over the workstation static IP. Does not
# change the loopback-only make demo / make demo-serve defaults. See
# docs/CLIENT_DEMO_RUNBOOK.md.
demo-client:
	@bash scripts/run-client-static-ip-demo.sh start

demo-client-check:
	@bash scripts/run-client-static-ip-demo.sh check

demo-client-status:
	@bash scripts/run-client-static-ip-demo.sh status

demo-client-stop:
	@bash scripts/run-client-static-ip-demo.sh stop

demo-client-tunnel:
	@bash scripts/run-client-demo-tunnel.sh start

demo-client-tunnel-status:
	@bash scripts/run-client-demo-tunnel.sh status

demo-client-tunnel-stop:
	@bash scripts/run-client-demo-tunnel.sh stop

# Requires JAVA_HOME pointing to JDK 21 and ANDROID_HOME pointing to an
# Android SDK with platform 36. See README "Android demo APK".
# Explicitly clears CAPACITOR_APP so the demo tree/identity is never overwritten.
demo-android: demo-build
	cd $(WEB_DIR) && env -u CAPACITOR_APP $(PNPM) exec cap sync android
	cd $(WEB_DIR)/android && ./gradlew assembleDebug

# PR-014 production-path closed beta (com.globalenglish.app). Requires
# VITE_MOBILE_API_ORIGIN. See docs/PR-014_ANDROID_CLOSED_BETA_BUILD.md.
# Does not build or modify the demo APK identity (D-0078).
android-closed-beta:
	@test -n "$${VITE_MOBILE_API_ORIGIN}" || (printf '%s\n' 'Set VITE_MOBILE_API_ORIGIN to the absolute API origin reachable from the device (see docs/PR-014_ANDROID_CLOSED_BETA_BUILD.md).' && exit 1)
	cd $(WEB_DIR) && VITE_MOBILE_API_ORIGIN="$${VITE_MOBILE_API_ORIGIN}" VITE_MOBILE_OAUTH_CLIENT_ID="$${VITE_MOBILE_OAUTH_CLIENT_ID}" VITE_MOBILE_OAUTH_REDIRECT_URI="$${VITE_MOBILE_OAUTH_REDIRECT_URI}" VITE_MOBILE_OAUTH_AUTHORIZE_URL="$${VITE_MOBILE_OAUTH_AUTHORIZE_URL}" VITE_MOBILE_OAUTH_TOKEN_URL="$${VITE_MOBILE_OAUTH_TOKEN_URL}" $(PNPM) run build
	cd $(WEB_DIR) && CAPACITOR_APP=production $(PNPM) exec cap sync android
	cd $(WEB_DIR)/android-app && ./gradlew assembleDebug

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

demo-e2e:
	$(PNPM) --filter $(WEB_FILTER) e2e:demo

demo-dist-e2e:
	$(PNPM) --filter $(WEB_FILTER) e2e:demo-dist

ai-eval:
	cd $(API_DIR) && $(PHP) artisan ai:evaluate
	@if [ -n "$${SPEECH_BENCHMARK_RUN_ID}" ]; then \
		cd $(API_DIR) && $(PHP) artisan speech:benchmark --phase=complete --run="$${SPEECH_BENCHMARK_RUN_ID}"; \
	else \
		echo 'speech benchmark skipped (set SPEECH_BENCHMARK_RUN_ID to include the PR-009 speech half)'; \
	fi

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
	$(PNPM) --filter $(MARKETING_FILTER) build

marketing:
	$(PNPM) --filter $(MARKETING_FILTER) dev

marketing-build:
	$(PNPM) --filter $(MARKETING_FILTER) build

load-soak:
	@chmod +x scripts/run-load-soak.sh
	@bash scripts/run-load-soak.sh

backup-restore-drill:
	@chmod +x scripts/run-backup-restore-drill.sh
	@bash scripts/run-backup-restore-drill.sh

staged-deploy-dry-run:
	@chmod +x scripts/run-staged-deploy-dry-run.sh
	@bash scripts/run-staged-deploy-dry-run.sh
