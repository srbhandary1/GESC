#!/usr/bin/env bash
# LPL-07 repository rollout gate — validates release-critical LPL behaviour in
# developer/CI environments. Production release acceptance remains separate.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
API_DIR="${ROOT_DIR}/apps/api"
WEB_DIR="${ROOT_DIR}/apps/web"
PHP_BIN="${GE_PHP:-${ROOT_DIR}/tmp/toolchain/php/php}"
if [[ ! -x "${PHP_BIN}" ]]; then
  PHP_BIN=php
fi
PHPUNIT_STORAGE_DIR="${PHPUNIT_STORAGE_DIR:-/tmp/global-spoken-english-phpunit-$(id -u)}"
EVIDENCE_DIR="${ROOT_DIR}/docs/evidence/lpl-01"
EVIDENCE_FILE="${EVIDENCE_DIR}/lpl07-rollout-gate-result.json"
LPL05_EVIDENCE="${EVIDENCE_DIR}/lpl05-commissioning-wave-result.json"
TIMESTAMP="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"

ok=0
fail=0
results_json='[]'

record() {
  local name="$1"
  local status="$2"
  local detail="${3:-}"

  if [[ "${status}" == "passed" ]]; then
    printf '[PASS] %s\n' "${name}"
    ok=$((ok + 1))
  else
    printf '[FAIL] %s\n' "${name}"
    fail=$((fail + 1))
  fi

  results_json="$(
    printf '%s' "${results_json}" | jq \
      --arg name "${name}" \
      --arg status "${status}" \
      --arg detail "${detail}" \
      '. + [{name: $name, status: $status, detail: $detail}]'
  )"
}

run_postgres_phpunit_filter() {
  local name="$1"
  local filter="$2"

  if (
    cd "${API_DIR}" &&
      LARAVEL_STORAGE_PATH="${PHPUNIT_STORAGE_DIR}" \
      "${PHP_BIN}" vendor/bin/phpunit \
        --configuration=phpunit.postgres.xml \
        --filter="${filter}"
  ); then
    record "${name}" passed
  else
    record "${name}" failed "phpunit.postgres --filter=${filter}"
  fi
}

run_artisan_test_filter() {
  local name="$1"
  local filter="$2"

  if (
    cd "${API_DIR}" &&
      LARAVEL_STORAGE_PATH="${PHPUNIT_STORAGE_DIR}" \
      "${PHP_BIN}" artisan test --filter="${filter}"
  ); then
    record "${name}" passed
  else
    record "${name}" failed "artisan test --filter=${filter}"
  fi
}

printf 'LPL-07 rollout gate\n\n'

if [[ -f "${LPL05_EVIDENCE}" ]] && jq -e '.all_complete == true' "${LPL05_EVIDENCE}" >/dev/null; then
  record 'lpl-07-production-commissioning-evidence' passed \
    "docs/evidence/lpl-01/lpl05-commissioning-wave-result.json"
else
  record 'lpl-07-production-commissioning-evidence' failed \
    "missing or incomplete ${LPL05_EVIDENCE}"
fi

run_postgres_phpunit_filter \
  'lpl-07-commissioning-audit-structure' \
  'DirectPathwayCommissioningAuditTest'
run_artisan_test_filter \
  'lpl-07-review-due-blocking' \
  'ReviewDueBlockingServiceTest|LevelCheckpointReviewDueTest'
run_artisan_test_filter \
  'lpl-07-checkpoint-variable-parts' \
  'LevelCheckpointVariablePartsTest'

if (
  cd "${WEB_DIR}" &&
    pnpm exec playwright test e2e/level-path-offline.spec.ts
); then
  record 'lpl-07-playwright-level-path-offline' passed
else
  record 'lpl-07-playwright-level-path-offline' failed \
    'playwright test e2e/level-path-offline.spec.ts'
fi

mkdir -p "${EVIDENCE_DIR}"
jq -n \
  --arg checkpoint 'LPL-07' \
  --arg recorded_at "${TIMESTAMP}" \
  --argjson gates "${results_json}" \
  --argjson passed "${ok}" \
  --argjson failed "${fail}" \
  '{
    checkpoint: $checkpoint,
    recorded_at: $recorded_at,
    gates_passed: $passed,
    gates_failed: $failed,
    all_passed: ($failed == 0),
    gates: $gates
  }' >"${EVIDENCE_FILE}"

printf '\nEvidence: %s\n' "${EVIDENCE_FILE}"
printf 'Summary: %s passed, %s failed\n' "${ok}" "${fail}"

if [[ "${fail}" -gt 0 ]]; then
  exit 1
fi
