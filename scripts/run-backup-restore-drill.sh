#!/usr/bin/env bash
# PH-07: non-production Postgres dump/restore drill.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT_DIR="${ROOT}/artifacts/ops"
mkdir -p "${OUT_DIR}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
OUT_FILE="${OUT_DIR}/backup-restore-${STAMP}.json"
TMP_DB="ge_restore_drill_${STAMP}"
DUMP_FILE="${OUT_DIR}/backup-restore-${STAMP}.dump"

COMPOSE="${DOCKER_COMPOSE:-docker compose}"
SERVICE="${POSTGRES_SERVICE:-postgres}"
DB_NAME="${POSTGRES_DB:-global_english}"
DB_USER="${POSTGRES_USER:-global_english}"

start_epoch="$(date +%s)"

if ! ${COMPOSE} ps --status running --services 2>/dev/null | grep -qx "${SERVICE}"; then
  echo "Postgres service '${SERVICE}' is not running. Start with: make services-up" >&2
  exit 1
fi

dump_start="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
${COMPOSE} exec -T "${SERVICE}" pg_dump -U "${DB_USER}" -d "${DB_NAME}" -Fc >"${DUMP_FILE}"
dump_end="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

${COMPOSE} exec -T "${SERVICE}" psql -U "${DB_USER}" -d postgres -v ON_ERROR_STOP=1 \
  -c "DROP DATABASE IF EXISTS \"${TMP_DB}\";" \
  -c "CREATE DATABASE \"${TMP_DB}\" OWNER \"${DB_USER}\";"

restore_start="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
${COMPOSE} exec -T "${SERVICE}" pg_restore -U "${DB_USER}" -d "${TMP_DB}" --clean --if-exists <"${DUMP_FILE}"
${COMPOSE} exec -T "${SERVICE}" psql -U "${DB_USER}" -d "${TMP_DB}" -v ON_ERROR_STOP=1 \
  -c "SELECT 1 AS restore_ok;" >/dev/null
restore_end="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

${COMPOSE} exec -T "${SERVICE}" psql -U "${DB_USER}" -d postgres -v ON_ERROR_STOP=1 \
  -c "DROP DATABASE IF EXISTS \"${TMP_DB}\";"

end_epoch="$(date +%s)"
rto_seconds=$((end_epoch - start_epoch))
commit="$(git -C "${ROOT}" rev-parse HEAD 2>/dev/null || printf 'unknown')"
dump_bytes="$(wc -c <"${DUMP_FILE}" | tr -d ' ')"

cat >"${OUT_FILE}" <<EOF
{
  "check": "PH-07",
  "dump_started_at": "${dump_start}",
  "dump_completed_at": "${dump_end}",
  "restore_started_at": "${restore_start}",
  "restore_completed_at": "${restore_end}",
  "rto_seconds": ${rto_seconds},
  "rpo_planning_minutes": 15,
  "rto_planning_seconds": 7200,
  "rto_met_planning_target": $([ "${rto_seconds}" -le 7200 ] && echo true || echo false),
  "dump_bytes": ${dump_bytes},
  "temporary_database": "${TMP_DB}",
  "commit": "${commit}",
  "generated_at": "${STAMP}",
  "note": "Non-production logical dump/restore only; WAL/PITR not claimed"
}
EOF

rm -f "${DUMP_FILE}"
printf 'Wrote %s (rto_seconds=%s)\n' "${OUT_FILE}" "${rto_seconds}"
