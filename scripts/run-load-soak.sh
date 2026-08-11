#!/usr/bin/env bash
# REL-01: synthetic health/readiness load drill with fail-closed evidence.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BASE_URL="${LOAD_SOAK_BASE_URL:-http://127.0.0.1:8000/api/v1}"
REQUEST_PATH="${LOAD_SOAK_PATH:-/health/ready}"
CONCURRENCY="${LOAD_SOAK_CONCURRENCY:-50}"
REQUESTS="${LOAD_SOAK_REQUESTS:-200}"
TIMEOUT_SECONDS="${LOAD_SOAK_TIMEOUT_SECONDS:-5}"
MAX_P95_MS="${LOAD_SOAK_MAX_P95_MS:-500}"
OUT_DIR="${OPS_EVIDENCE_DIR:-${ROOT}/artifacts/ops}"

require_positive_integer() {
  local name="$1"
  local value="$2"

  if [[ ! "${value}" =~ ^[1-9][0-9]*$ ]]; then
    printf '%s must be a positive integer\n' "${name}" >&2
    exit 2
  fi
}

require_positive_integer LOAD_SOAK_CONCURRENCY "${CONCURRENCY}"
require_positive_integer LOAD_SOAK_REQUESTS "${REQUESTS}"
require_positive_integer LOAD_SOAK_TIMEOUT_SECONDS "${TIMEOUT_SECONDS}"
require_positive_integer LOAD_SOAK_MAX_P95_MS "${MAX_P95_MS}"

if [[ ! "${BASE_URL}" =~ ^https?://[A-Za-z0-9.-]+(:[0-9]{1,5})?(/[A-Za-z0-9._~/-]*)?$ ]]; then
  echo 'LOAD_SOAK_BASE_URL must be an HTTP(S) origin with an optional safe path' >&2
  exit 2
fi

if [[ ! "${REQUEST_PATH}" =~ ^/[A-Za-z0-9._~/-]+$ ]]; then
  echo 'LOAD_SOAK_PATH must be an absolute URL path without a query or fragment' >&2
  exit 2
fi

if ! command -v curl >/dev/null 2>&1; then
  echo 'curl is required' >&2
  exit 1
fi

mkdir -p "${OUT_DIR}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
OUT_FILE="${OUT_DIR}/load-soak-${STAMP}.json"
RESULTS="$(mktemp "${OUT_DIR}/.load-soak-results.XXXXXX")"
LATENCIES="$(mktemp "${OUT_DIR}/.load-soak-latencies.XXXXXX")"

cleanup() {
  rm -f "${RESULTS}" "${LATENCIES}"
}
trap cleanup EXIT

run_one() {
  local metrics
  local code
  local elapsed

  if metrics="$(curl \
    --proto '=http,https' \
    --silent \
    --show-error \
    --output /dev/null \
    --max-time "${TIMEOUT_SECONDS}" \
    --write-out '%{http_code} %{time_total}' \
    "${BASE_URL}${REQUEST_PATH}")"; then
    read -r code elapsed <<<"${metrics}"

    if [[ "${code}" == 200 ]]; then
      printf 'ok %s\n' "${elapsed}" >>"${RESULTS}"

      return
    fi
  fi

  printf 'fail 0\n' >>"${RESULTS}"
}

export -f run_one
export BASE_URL REQUEST_PATH RESULTS TIMEOUT_SECONDS

start_ns="$(date +%s%N 2>/dev/null || printf '0')"
seq 1 "${REQUESTS}" \
  | xargs -P "${CONCURRENCY}" -n 1 bash -c 'run_one "$1"' _
end_ns="$(date +%s%N 2>/dev/null || printf '0')"

ok="$(awk '$1 == "ok" { count++ } END { print count + 0 }' "${RESULTS}")"
fail="$(awk '$1 == "fail" { count++ } END { print count + 0 }' "${RESULTS}")"
awk '$1 == "ok" { printf "%.3f\n", $2 * 1000 }' "${RESULTS}" \
  | sort -n >"${LATENCIES}"

read -r p50_ms p95_ms max_ms < <(
  awk '
    { values[NR] = $1 }
    END {
      if (NR == 0) {
        print "0 0 0"
        exit
      }
      p50 = int((NR * 50 + 99) / 100)
      p95 = int((NR * 95 + 99) / 100)
      printf "%.0f %.0f %.0f\n", values[p50], values[p95], values[NR]
    }
  ' "${LATENCIES}"
)

if [[ "${start_ns}" != 0 && "${end_ns}" != 0 ]]; then
  elapsed_ms=$(( (end_ns - start_ns) / 1000000 ))
else
  elapsed_ms=0
fi

passed=false
if [[ "${ok}" -eq "${REQUESTS}" && "${fail}" -eq 0 && "${p95_ms}" -le "${MAX_P95_MS}" ]]; then
  passed=true
fi

commit="$(git -C "${ROOT}" rev-parse HEAD 2>/dev/null || printf 'unknown')"
if [[ ! "${commit}" =~ ^[0-9a-f]{40}$ ]]; then
  commit=unknown
fi
working_tree_clean=true
if [[ -n "$(git -C "${ROOT}" status --porcelain 2>/dev/null || true)" ]]; then
  working_tree_clean=false
fi

TEMP_EVIDENCE="${OUT_FILE}.tmp.$$"
cat >"${TEMP_EVIDENCE}" <<EOF
{
  "check": "REL-01-load",
  "passed": ${passed},
  "base_url": "${BASE_URL}",
  "path": "${REQUEST_PATH}",
  "concurrency": ${CONCURRENCY},
  "requests": ${REQUESTS},
  "ok": ${ok},
  "fail": ${fail},
  "p50_ms": ${p50_ms},
  "p95_ms": ${p95_ms},
  "max_ms": ${max_ms},
  "maximum_p95_ms": ${MAX_P95_MS},
  "elapsed_ms": ${elapsed_ms},
  "commit": "${commit}",
  "working_tree_clean": ${working_tree_clean},
  "generated_at": "${STAMP}",
  "note": "Synthetic health/readiness drill; authenticated journey and production SLO evidence are not claimed"
}
EOF
mv "${TEMP_EVIDENCE}" "${OUT_FILE}"

printf 'Wrote %s (passed=%s ok=%s fail=%s p95_ms=%s)\n' \
  "${OUT_FILE}" "${passed}" "${ok}" "${fail}" "${p95_ms}"

if [[ "${passed}" != true ]]; then
  exit 1
fi
