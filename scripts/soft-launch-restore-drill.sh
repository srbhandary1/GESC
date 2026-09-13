#!/usr/bin/env bash
# Restore a soft-launch gzip dump into a temporary database. Never overwrites live.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
RUNTIME="${GE_SOFT_LAUNCH_RUNTIME:-${HOME}/ge-soft-launch}"
BACKUP_DIR="${RUNTIME}/backups"
OUT_DIR="${OPS_EVIDENCE_DIR:-${ROOT}/artifacts/ops}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
DB_STAMP="$(date -u +%Y%m%d%H%M%S)"
TMP_DB="ge_sl_r_${DB_STAMP}_$$"
COMPOSE_VALUE="${DOCKER_COMPOSE:-docker compose}"
SERVICE="${POSTGRES_SERVICE:-postgres}"
RTO_TARGET_SECONDS="${BACKUP_RESTORE_RTO_SECONDS:-7200}"
DUMP="${1:-}"

die() {
  printf '%s\n' "$*" >&2
  exit 1
}

if [[ ! "${SERVICE}" =~ ^[A-Za-z0-9][A-Za-z0-9_.-]{0,62}$ ]]; then
  die 'POSTGRES_SERVICE is not a safe Compose service name'
fi
if [[ ! "${RTO_TARGET_SECONDS}" =~ ^[1-9][0-9]*$ ]]; then
  die 'BACKUP_RESTORE_RTO_SECONDS must be a positive integer'
fi
if [[ ! "${TMP_DB}" =~ ^[A-Za-z_][A-Za-z0-9_]{0,62}$ ]]; then
  die 'Temporary restore database name is unsafe'
fi

load_postgres_identifiers() {
  local line key value
  POSTGRES_DB=""
  POSTGRES_USER=""
  [[ -f "${ROOT}/.env" ]] || die 'Repository root .env is missing'
  while IFS= read -r line || [[ -n "${line}" ]]; do
    case "${line}" in
      POSTGRES_DB=*|POSTGRES_USER=*)
        key="${line%%=*}"
        value="${line#*=}"
        value="${value%\"}"
        value="${value#\"}"
        printf -v "${key}" '%s' "${value}"
        ;;
    esac
  done < "${ROOT}/.env"
  [[ "${POSTGRES_DB}" =~ ^[A-Za-z_][A-Za-z0-9_]{0,62}$ ]] \
    || die 'POSTGRES_DB is missing or unsafe'
  [[ "${POSTGRES_USER}" =~ ^[A-Za-z_][A-Za-z0-9_]{0,62}$ ]] \
    || die 'POSTGRES_USER is missing or unsafe'
}

load_postgres_identifiers
DB_NAME="${POSTGRES_DB}"
DB_USER="${POSTGRES_USER}"

for identifier in "${DB_NAME}" "${DB_USER}" "${TMP_DB}"; do
  if [[ ! "${identifier}" =~ ^[A-Za-z_][A-Za-z0-9_]{0,62}$ ]]; then
    die 'Postgres database/user identifiers must be unquoted safe identifiers'
  fi
done

case "${TMP_DB}" in
  postgres|template0|template1|"${DB_NAME}"|global_english_test)
    die 'Refusing to use a protected database name for the restore drill'
    ;;
esac

if [[ -z "${DUMP}" ]]; then
  DUMP="$(
    find "${BACKUP_DIR}" -maxdepth 1 -type f -name 'soft-launch-????????T??????Z.sql.gz' \
      -printf '%T@ %p\n' 2>/dev/null | sort -n | tail -1 | cut -d' ' -f2-
  )"
fi
[[ -n "${DUMP}" && -f "${DUMP}" ]] || die "No soft-launch gzip dump found in ${BACKUP_DIR}"
base="$(basename "${DUMP}")"
[[ "${base}" =~ ^soft-launch-[0-9]{8}T[0-9]{6}Z\.sql\.gz$ ]] \
  || die 'Dump filename must be soft-launch-YYYYMMDDTHHMMSSZ.sql.gz'
gzip -t "${DUMP}"

read -r -a COMPOSE_COMMAND <<<"${COMPOSE_VALUE}"
if [[ "${#COMPOSE_COMMAND[@]}" -eq 0 ]]; then
  die 'DOCKER_COMPOSE must name a command'
fi

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

if ! "${COMPOSE_COMMAND[@]}" ps --status running --services 2>/dev/null \
  | grep -Fqx "${SERVICE}"; then
  die "Postgres service '${SERVICE}' is not running. Start with: make services-up"
fi

MIGRATION_QUERY="SELECT coalesce(string_agg(migration || ':' || batch::text, E'\\n' ORDER BY id), '') FROM migrations;"
SCHEMA_QUERY="SELECT coalesce(string_agg(format('%s:%s:%s:%s:%s', table_name, column_name, ordinal_position, data_type, is_nullable), E'\\n' ORDER BY table_name, ordinal_position), '') FROM information_schema.columns WHERE table_schema = 'public';"
DELETION_QUERY="SELECT concat_ws(':', (SELECT count(*) FROM voice_recordings), (SELECT count(*) FROM voice_recordings WHERE source_object_key IS NULL), (SELECT count(*) FROM voice_deletion_requests), (SELECT count(*) FROM voice_deletion_requests WHERE status IN ('completed', 'restricted')), (SELECT count(*) FROM privacy_erasure_requests), (SELECT count(*) FROM privacy_erasure_voice_deletions), (SELECT count(*) FROM outbox_events WHERE dead_lettered_at IS NOT NULL), (SELECT count(*) FROM institution_invites WHERE status IN ('accepted', 'revoked', 'expired') AND code_digest IS NULL), (SELECT count(*) FROM organization_administration_commands), (SELECT count(*) FROM organization_administration_events), (SELECT count(*) FROM learner_profile_current_goals), (SELECT count(*) FROM learner_goal_corrections), (SELECT count(*) FROM authentication_sessions WHERE selected_membership_id IS NOT NULL AND revoked_at IS NOT NULL), (SELECT count(*) FROM mobile_token_families WHERE selected_membership_id IS NOT NULL AND revoked_at IS NOT NULL), (SELECT count(*) FROM authentication_session_cleanup_requests WHERE status = 'completed'));"

source_migrations="$(query_database "${DB_NAME}" "${MIGRATION_QUERY}")"
source_schema="$(query_database "${DB_NAME}" "${SCHEMA_QUERY}")"
source_deletion_state="$(query_database "${DB_NAME}" "${DELETION_QUERY}")"

TMP_DB_CREATED=false
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
}
trap cleanup EXIT

start_epoch="$(date +%s)"
restore_start="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

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

# Restore into the empty database only. Live POSTGRES_DB is never dropped.
gunzip -c "${DUMP}" | postgres psql \
  -X \
  -U "${DB_USER}" \
  -d "${TMP_DB}" \
  -v ON_ERROR_STOP=1 \
  -q \
  >/dev/null

query_database "${TMP_DB}" 'SELECT 1;' >/dev/null
restore_end="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

restored_migrations="$(query_database "${TMP_DB}" "${MIGRATION_QUERY}")"
restored_schema="$(query_database "${TMP_DB}" "${SCHEMA_QUERY}")"
restored_deletion_state="$(query_database "${TMP_DB}" "${DELETION_QUERY}")"

if [[ "${source_migrations}" != "${restored_migrations}" ]]; then
  die 'Restored migration evidence differs from the live database'
fi
if [[ "${source_schema}" != "${restored_schema}" ]]; then
  die 'Restored application schema differs from the live database'
fi
if [[ "${source_deletion_state}" != "${restored_deletion_state}" ]]; then
  die 'Restored deletion/tombstone aggregates differ from the live database'
fi

end_epoch="$(date +%s)"
rto_seconds=$((end_epoch - start_epoch))
dump_bytes="$(wc -c <"${DUMP}" | tr -d ' ')"
commit="$(git -C "${ROOT}" rev-parse HEAD 2>/dev/null || printf 'unknown')"
if [[ ! "${commit}" =~ ^[0-9a-f]{40}$ ]]; then
  commit=unknown
fi
passed=true
if [[ "${rto_seconds}" -gt "${RTO_TARGET_SECONDS}" ]]; then
  passed=false
fi

mkdir -p "${OUT_DIR}" "${RUNTIME}/evidence"
chmod 700 "${RUNTIME}/evidence" 2>/dev/null || true
OUT_FILE="${OUT_DIR}/soft-launch-restore-drill-${STAMP}.json"
RUNTIME_EVIDENCE="${RUNTIME}/evidence/soft-launch-restore-drill-${STAMP}.json"
TEMP_EVIDENCE="${OUT_FILE}.tmp.$$"
cat >"${TEMP_EVIDENCE}" <<EOF
{
  "check": "wave4-soft-launch-gzip-restore",
  "passed": ${passed},
  "restore_started_at": "${restore_start}",
  "restore_completed_at": "${restore_end}",
  "rto_seconds": ${rto_seconds},
  "rto_target_seconds": ${RTO_TARGET_SECONDS},
  "rto_target_met": ${passed},
  "live_database_overwritten": false,
  "dump_bytes": ${dump_bytes},
  "application_column_schema_match": true,
  "migrations_match": true,
  "deletion_tombstone_state_match": true,
  "commit": "${commit}",
  "generated_at": "${STAMP}",
  "note": "Temporary database only. Not PITR. Same-host encrypted copy is not off-box USB."
}
EOF
chmod 600 "${TEMP_EVIDENCE}"
mv "${TEMP_EVIDENCE}" "${OUT_FILE}"
cp -f "${OUT_FILE}" "${RUNTIME_EVIDENCE}"
chmod 600 "${RUNTIME_EVIDENCE}"

printf 'Wrote %s (passed=%s rto_seconds=%s live_overwritten=false)\n' \
  "${OUT_FILE}" "${passed}" "${rto_seconds}"

if [[ "${passed}" != true ]]; then
  exit 1
fi
