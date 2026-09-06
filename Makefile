SHELL := /bin/sh

PHP ?= php
PNPM ?= pnpm
COMPOSER_CMD ?= composer
DOCKER_COMPOSE ?= docker compose

API_DIR := apps/api
WEB_DIR := apps/web
WEB_FILTER := @global-english/web
COMPOSER_VERSION := 2.10.2
PHPUNIT_STORAGE_DIR ?= /tmp/global-spoken-english-phpunit-$(shell id -u)

.DEFAULT_GOAL := help

.PHONY: help bootstrap check-toolchain check-local-auth-env dev demo demo-build demo-serve demo-client demo-client-check demo-client-status demo-client-stop demo-client-tunnel demo-client-tunnel-status demo-client-tunnel-stop demo-android android-closed-beta android-release-bundle services-up services-down media-tools media-services-up verify test e2e demo-e2e demo-dist-e2e ai-eval ai-eval-review-export ai-eval-human-review openapi release-contract soft-launch-contract-test migrate seed build load-soak backup-restore-drill staged-deploy-dry-run soft-launch-setup soft-launch-start soft-launch-stop soft-launch-restart soft-launch-refresh-web soft-launch-status soft-launch-tunnel soft-launch-tunnel-stop soft-launch-configure-stable soft-launch-health soft-launch-ready soft-launch-backup soft-launch-restore-drill soft-launch-observe soft-launch-observe-install soft-launch-stable-prereq soft-launch-systemd-install wave6-indic-preflight

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
		'make android-release-bundle Build a signed production-path Android App Bundle' \
		'make soft-launch-setup   Create APP-0053 outside-repo soft-launch + Azure evidence scaffolds' \
		'make soft-launch-start   Start real product soft-launch stack (API/web/queue/Caddy)' \
		'make soft-launch-refresh-web  Rebuild served web only when inputs changed' \
		'make soft-launch-tunnel  Cloudflare HTTPS tunnel for soft-launch mic/remote access' \
		'make soft-launch-configure-stable HOST=ai.example.com  Configure stable direct HTTPS' \
		'make soft-launch-status  Soft-launch process and health status' \
		'make soft-launch-ready   Soft-launch readiness checklist' \
		'make soft-launch-backup  Dump soft-launch Postgres, encrypt, copy ciphertext offsite' \
		'make soft-launch-restore-drill  Restore latest gzip dump into a temporary database' \
		'make soft-launch-observe  Content-free API/queue/provider-5xx check' \
		'make soft-launch-observe-install Install the per-minute user observe timer' \
		'make soft-launch-stable-prereq  Fail-closed DNS/TLS check for ai.globalspokenenglish.com' \
		'make soft-launch-systemd-install Install, enable and start the persistent user service' \
		'make wave6-indic-preflight  Wave 6 automated Indic STT env/DI/tests preflight (not mic staff matrix)' \
		'make media-tools   Build the signed, pinned local ffprobe inspection tool' \
		'make media-services-up Start the opt-in loopback ClamAV media scanner' \
		'make verify        Run formatting, static analysis, tests and dependency audits' \
		'make test          Run deterministic backend and frontend tests' \
		'make e2e           Run the critical web browser journeys' \
		'make demo-e2e      Run the synthetic local-demo browser journey' \
		'make demo-dist-e2e Run the browser journey against the built demo bundle' \
		'make ai-eval       Run locked AI evaluation; speech complete when SPEECH_BENCHMARK_RUN_ID is set' \
		'make openapi       Validate OpenAPI and check generated-client drift' \
		'make migrate       Apply local Laravel migrations' \
		'make seed          Load safe local Laravel seed data' \
		'make build         Build the API configuration and React production bundle' \
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
	mkdir -p $(WEB_DIR)/dist-demo/downloads
	cp $(WEB_DIR)/android/app/build/outputs/apk/debug/app-debug.apk \
		$(WEB_DIR)/dist-demo/downloads/global-english-demo.apk

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
	cd $(API_DIR) && LARAVEL_STORAGE_PATH=$(PHPUNIT_STORAGE_DIR) $(PHP) artisan test
	cd $(API_DIR) && LARAVEL_STORAGE_PATH=$(PHPUNIT_STORAGE_DIR) $(PHP) vendor/bin/phpunit --configuration=phpunit.postgres.xml
	$(PNPM) install --frozen-lockfile
	$(PNPM) audit --audit-level=high
	$(PNPM) run locale:check
	$(MAKE) openapi
	$(PNPM) run lint
	$(PNPM) run typecheck
	$(PNPM) run test

test:
	cd $(API_DIR) && LARAVEL_STORAGE_PATH=$(PHPUNIT_STORAGE_DIR) $(PHP) artisan test
	cd $(API_DIR) && LARAVEL_STORAGE_PATH=$(PHPUNIT_STORAGE_DIR) $(PHP) vendor/bin/phpunit --configuration=phpunit.postgres.xml
	$(PNPM) run test

e2e:
	cd $(API_DIR) && LARAVEL_STORAGE_PATH=$(PHPUNIT_STORAGE_DIR) $(PHP) artisan test tests/Integration/MediaTranscriptionReconciliationApiTest.php
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

release-contract:
	$(PNPM) run release:check

soft-launch-contract-test:
	@sh scripts/test-soft-launch-environment.sh
	@bash scripts/test-soft-launch-observe.sh

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

load-soak:
	@chmod +x scripts/run-load-soak.sh
	@bash scripts/run-load-soak.sh

backup-restore-drill:
	@chmod +x scripts/run-backup-restore-drill.sh
	@bash scripts/run-backup-restore-drill.sh

staged-deploy-dry-run:
	@chmod +x scripts/run-staged-deploy-dry-run.sh
	@bash scripts/run-staged-deploy-dry-run.sh

# APP-0053 Ubuntu invite-only soft launch (real product; not make demo).
soft-launch-setup:
	@chmod +x scripts/setup-soft-launch-workspace.sh scripts/install-soft-launch-caddy.sh
	@bash scripts/setup-soft-launch-workspace.sh

soft-launch-start:
	@chmod +x scripts/run-soft-launch.sh scripts/install-soft-launch-caddy.sh
	@test -x "$${HOME}/.local/bin/caddy" || bash scripts/install-soft-launch-caddy.sh
	@bash scripts/run-soft-launch.sh start

soft-launch-stop:
	@chmod +x scripts/run-soft-launch.sh
	@bash scripts/run-soft-launch.sh stop

soft-launch-restart:
	@chmod +x scripts/run-soft-launch.sh
	@bash scripts/run-soft-launch.sh restart

soft-launch-refresh-web:
	@chmod +x scripts/run-soft-launch.sh
	@bash scripts/run-soft-launch.sh refresh-web

soft-launch-status:
	@chmod +x scripts/run-soft-launch.sh
	@bash scripts/run-soft-launch.sh status

soft-launch-tunnel:
	@chmod +x scripts/run-soft-launch.sh
	@bash scripts/run-soft-launch.sh tunnel

soft-launch-tunnel-stop:
	@chmod +x scripts/run-soft-launch.sh
	@bash scripts/run-soft-launch.sh tunnel-stop

soft-launch-configure-stable:
	@test -n "$(HOST)" || (printf '%s\n' 'Set HOST to the lowercase DNS hostname without a scheme, path or port.' && exit 1)
	@GE_SOFT_LAUNCH_STABLE_HOST="$(HOST)" bash scripts/run-soft-launch.sh configure-stable-host

soft-launch-health:
	@chmod +x scripts/run-soft-launch.sh
	@bash scripts/run-soft-launch.sh health

soft-launch-ready:
	@chmod +x scripts/check-soft-launch-readiness.sh
	@bash scripts/check-soft-launch-readiness.sh

soft-launch-backup:
	@chmod +x scripts/run-soft-launch.sh scripts/soft-launch-encrypt-backup.sh
	@bash scripts/run-soft-launch.sh backup

soft-launch-restore-drill:
	@chmod +x scripts/soft-launch-restore-drill.sh
	@bash scripts/soft-launch-restore-drill.sh

soft-launch-observe:
	@chmod +x scripts/soft-launch-observe.sh
	@bash scripts/soft-launch-observe.sh

soft-launch-observe-install:
	@chmod +x scripts/install-soft-launch-observe-timer.sh scripts/soft-launch-observe.sh
	@bash scripts/install-soft-launch-observe-timer.sh

soft-launch-stable-prereq:
	@chmod +x scripts/check-stable-host-prerequisites.sh
	@bash scripts/check-stable-host-prerequisites.sh

soft-launch-systemd-install:
	@chmod +x scripts/install-soft-launch-systemd.sh
	@bash scripts/install-soft-launch-systemd.sh --start

wave6-indic-preflight:
	@chmod +x scripts/wave6-indic-stt-preflight.sh
	@bash scripts/wave6-indic-stt-preflight.sh
