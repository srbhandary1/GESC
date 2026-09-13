#!/usr/bin/env bash
# REL-01: non-production Postgres logical backup/restore drill.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT_DIR="${OPS_EVIDENCE_DIR:-${ROOT}/artifacts/ops}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
DB_STAMP="$(date -u +%Y%m%d%H%M%S)"
OUT_FILE="${OUT_DIR}/backup-restore-${STAMP}.json"
TMP_DB="ge_restore_drill_${DB_STAMP}_$$"
COMPOSE_VALUE="${DOCKER_COMPOSE:-docker compose}"
SERVICE="${POSTGRES_SERVICE:-postgres}"
DB_NAME="${POSTGRES_DB:-global_english}"
DB_USER="${POSTGRES_USER:-global_english}"
RTO_TARGET_SECONDS="${BACKUP_RESTORE_RTO_SECONDS:-7200}"

if [[ ! "${SERVICE}" =~ ^[A-Za-z0-9][A-Za-z0-9_.-]{0,62}$ ]]; then
  echo 'POSTGRES_SERVICE is not a safe Compose service name' >&2
  exit 2
fi

for identifier in "${DB_NAME}" "${DB_USER}" "${TMP_DB}"; do
  if [[ ! "${identifier}" =~ ^[A-Za-z_][A-Za-z0-9_]{0,62}$ ]]; then
    echo 'Postgres database/user identifiers must be unquoted safe identifiers' >&2
    exit 2
  fi
done

if [[ ! "${RTO_TARGET_SECONDS}" =~ ^[1-9][0-9]*$ ]]; then
  echo 'BACKUP_RESTORE_RTO_SECONDS must be a positive integer' >&2
  exit 2
fi

read -r -a COMPOSE_COMMAND <<<"${COMPOSE_VALUE}"
if [[ "${#COMPOSE_COMMAND[@]}" -eq 0 ]]; then
  echo 'DOCKER_COMPOSE must name a command' >&2
  exit 2
fi

mkdir -p "${OUT_DIR}"
DUMP_FILE="$(mktemp --suffix=.dump "${OUT_DIR}/.backup-restore.XXXXXX")"
chmod 600 "${DUMP_FILE}"
TMP_DB_CREATED=false

postgres() {
  "${COMPOSE_COMMAND[@]}" exec -T "${SERVICE}" "$@"
}

query_database() {
  local database="$1"
  local query="$2"

  postgres psql \
    -X \
    -U "${DB_USER}" \
    -d "${database}" \
    -v ON_ERROR_STOP=1 \
    -Atq \
    -c "${query}"
}

cleanup() {
  if [[ "${TMP_DB_CREATED}" == true ]]; then
    postgres psql \
      -X \
      -U "${DB_USER}" \
      -d postgres \
      -v ON_ERROR_STOP=1 \
      -q \
      -c "DROP DATABASE IF EXISTS \"${TMP_DB}\" WITH (FORCE);" \
      >/dev/null 2>&1 || true
  fi

  rm -f "${DUMP_FILE}"
}
trap cleanup EXIT

if ! "${COMPOSE_COMMAND[@]}" ps --status running --services 2>/dev/null \
  | grep -Fqx "${SERVICE}"; then
  printf "Postgres service '%s' is not running. Start with: make services-up\n" \
    "${SERVICE}" >&2
  exit 1
fi

MIGRATION_QUERY="SELECT coalesce(string_agg(migration || ':' || batch::text, E'\\n' ORDER BY id), '') FROM migrations;"
SCHEMA_QUERY="SELECT coalesce(string_agg(format('%s:%s:%s:%s:%s', table_name, column_name, ordinal_position, data_type, is_nullable), E'\\n' ORDER BY table_name, ordinal_position), '') FROM information_schema.columns WHERE table_schema = 'public';"
DELETION_QUERY="SELECT concat_ws(':', (SELECT count(*) FROM voice_recordings), (SELECT count(*) FROM voice_recordings WHERE source_object_key IS NULL), (SELECT count(*) FROM voice_deletion_requests), (SELECT count(*) FROM voice_deletion_requests WHERE status IN ('completed', 'restricted')), (SELECT count(*) FROM privacy_erasure_requests), (SELECT count(*) FROM privacy_erasure_voice_deletions), (SELECT count(*) FROM outbox_events WHERE dead_lettered_at IS NOT NULL), (SELECT count(*) FROM institution_invites WHERE status IN ('accepted', 'revoked', 'expired') AND code_digest IS NULL), (SELECT count(*) FROM organization_administration_commands), (SELECT count(*) FROM organization_administration_events), (SELECT count(*) FROM learner_profile_current_goals), (SELECT count(*) FROM learner_goal_corrections), (SELECT count(*) FROM authentication_sessions WHERE selected_membership_id IS NOT NULL AND revoked_at IS NOT NULL), (SELECT count(*) FROM mobile_token_families WHERE selected_membership_id IS NOT NULL AND revoked_at IS NOT NULL), (SELECT count(*) FROM authentication_session_cleanup_requests WHERE status = 'completed'));"

migration_count="$(query_database "${DB_NAME}" 'SELECT count(*) FROM migrations;')"
critical_table_count="$(query_database "${DB_NAME}" "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_name IN ('voice_recordings', 'voice_deletion_requests', 'privacy_erasure_requests', 'privacy_erasure_voice_deletions', 'outbox_events', 'organization_administration_commands', 'organization_administration_events', 'learner_profile_current_goals', 'learner_goal_corrections', 'authentication_session_cleanup_requests');")"

if [[ ! "${migration_count}" =~ ^[1-9][0-9]*$ || "${critical_table_count}" != 10 ]]; then
  echo 'Source database is not fully migrated or lacks deletion/authority tables' >&2
  exit 1
fi

source_migrations="$(query_database "${DB_NAME}" "${MIGRATION_QUERY}")"
source_schema="$(query_database "${DB_NAME}" "${SCHEMA_QUERY}")"
source_deletion_state="$(query_database "${DB_NAME}" "${DELETION_QUERY}")"
start_epoch="$(date +%s)"
dump_start="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
postgres pg_dump \
  -U "${DB_USER}" \
  -d "${DB_NAME}" \
  -Fc \
  --no-password >"${DUMP_FILE}"
dump_end="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

if [[ ! -s "${DUMP_FILE}" ]]; then
  echo 'Logical backup is empty' >&2
  exit 1
fi
postgres pg_restore --list <"${DUMP_FILE}" >/dev/null

postgres psql \
  -X \
  -U "${DB_USER}" \
  -d postgres \
  -v ON_ERROR_STOP=1 \
  -q \
  -c "DROP DATABASE IF EXISTS \"${TMP_DB}\" WITH (FORCE);" \
  -c "CREATE DATABASE \"${TMP_DB}\" OWNER \"${DB_USER}\";" \
  >/dev/null
TMP_DB_CREATED=true

restore_start="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
postgres pg_restore \
  -U "${DB_USER}" \
  -d "${TMP_DB}" \
  --clean \
  --if-exists \
  --exit-on-error <"${DUMP_FILE}"
query_database "${TMP_DB}" 'SELECT 1;' >/dev/null
restore_end="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

restored_migrations="$(query_database "${TMP_DB}" "${MIGRATION_QUERY}")"
restored_schema="$(query_database "${TMP_DB}" "${SCHEMA_QUERY}")"
restored_deletion_state="$(query_database "${TMP_DB}" "${DELETION_QUERY}")"

if [[ "${source_migrations}" != "${restored_migrations}" ]]; then
  echo 'Restored migration evidence differs from the source database' >&2
  exit 1
fi
if [[ "${source_schema}" != "${restored_schema}" ]]; then
  echo 'Restored application schema differs from the source database' >&2
  exit 1
fi
if [[ "${source_deletion_state}" != "${restored_deletion_state}" ]]; then
  echo 'Restored deletion/tombstone state differs from the source database' >&2
  exit 1
fi

end_epoch="$(date +%s)"
rto_seconds=$((end_epoch - start_epoch))
dump_bytes="$(wc -c <"${DUMP_FILE}" | tr -d ' ')"
commit="$(git -C "${ROOT}" rev-parse HEAD 2>/dev/null || printf 'unknown')"
if [[ ! "${commit}" =~ ^[0-9a-f]{40}$ ]]; then
  commit=unknown
fi
working_tree_clean=true
if [[ -n "$(git -C "${ROOT}" status --porcelain 2>/dev/null || true)" ]]; then
  working_tree_clean=false
fi

passed=false
if [[ "${rto_seconds}" -le "${RTO_TARGET_SECONDS}" ]]; then
  passed=true
fi

TEMP_EVIDENCE="${OUT_FILE}.tmp.$$"
cat >"${TEMP_EVIDENCE}" <<EOF
{
  "check": "REL-01-backup-restore",
  "passed": ${passed},
  "dump_started_at": "${dump_start}",
  "dump_completed_at": "${dump_end}",
  "restore_started_at": "${restore_start}",
  "restore_completed_at": "${restore_end}",
  "rto_seconds": ${rto_seconds},
  "rto_target_seconds": ${RTO_TARGET_SECONDS},
  "rto_target_met": ${passed},
  "rpo_target_minutes": 15,
  "pitr_tested": false,
  "dump_bytes": ${dump_bytes},
  "application_column_schema_match": true,
  "migrations_match": true,
  "deletion_tombstone_state_match": true,
  "commit": "${commit}",
  "working_tree_clean": ${working_tree_clean},
  "generated_at": "${STAMP}",
  "note": "Non-production logical dump/restore evidence; WAL/PITR and production recovery are not claimed"
}
EOF
mv "${TEMP_EVIDENCE}" "${OUT_FILE}"

printf 'Wrote %s (passed=%s rto_seconds=%s)\n' \
  "${OUT_FILE}" "${passed}" "${rto_seconds}"

if [[ "${passed}" != true ]]; then
  exit 1
fi
