#!/usr/bin/env bash
# PH-06: synthetic load/soak against health endpoints (planning capacity, not SLO contract).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BASE_URL="${LOAD_SOAK_BASE_URL:-http://127.0.0.1:8000/api/v1}"
CONCURRENCY="${LOAD_SOAK_CONCURRENCY:-50}"
REQUESTS="${LOAD_SOAK_REQUESTS:-200}"
OUT_DIR="${ROOT}/artifacts/ops"
mkdir -p "${OUT_DIR}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
OUT_FILE="${OUT_DIR}/load-soak-${STAMP}.json"
RESULTS="${OUT_DIR}/.load-soak-results.$$"

if ! command -v curl >/dev/null 2>&1; then
  echo 'curl is required' >&2
  exit 1
fi

: >"${RESULTS}"
start_ns="$(date +%s%N 2>/dev/null || echo 0)"

run_one() {
  local path="$1"
  if curl -fsS -o /dev/null --max-time 5 "${BASE_URL}${path}"; then
    echo ok >>"${RESULTS}"
  else
    echo fail >>"${RESULTS}"
  fi
}

export -f run_one
export BASE_URL RESULTS

seq 1 "${REQUESTS}" | xargs -P "${CONCURRENCY}" -I{} bash -c 'run_one /health/live'

ok="$(grep -c '^ok$' "${RESULTS}" || true)"
fail="$(grep -c '^fail$' "${RESULTS}" || true)"
rm -f "${RESULTS}"

end_ns="$(date +%s%N 2>/dev/null || echo 0)"
if [ "${start_ns}" != 0 ] && [ "${end_ns}" != 0 ]; then
  elapsed_ms=$(( (end_ns - start_ns) / 1000000 ))
else
  elapsed_ms=0
fi

commit="$(git -C "${ROOT}" rev-parse HEAD 2>/dev/null || printf 'unknown')"

cat >"${OUT_FILE}" <<EOF
{
  "check": "PH-06",
  "base_url": "${BASE_URL}",
  "concurrency": ${CONCURRENCY},
  "requests": ${REQUESTS},
  "ok": ${ok},
  "fail": ${fail},
  "elapsed_ms": ${elapsed_ms},
  "pilot_peak_assumption": "50 concurrent voice sessions (master plan §20.2); this harness exercises health at concurrency=${CONCURRENCY}",
  "commit": "${commit}",
  "generated_at": "${STAMP}",
  "note": "Planning evidence only — not a production latency/availability contract"
}
EOF

printf 'Wrote %s (ok=%s fail=%s elapsed_ms=%s)\n' "${OUT_FILE}" "${ok}" "${fail}" "${elapsed_ms}"
