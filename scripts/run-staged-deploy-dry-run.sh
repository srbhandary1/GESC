#!/usr/bin/env bash
# PH-09: staged deploy dry-run (build + artifact checklist).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT_DIR="${ROOT}/artifacts/ops"
mkdir -p "${OUT_DIR}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
OUT_FILE="${OUT_DIR}/staged-deploy-dry-run.json"

cd "${ROOT}"
make build

web_dist_ok=false
if [ -d "${ROOT}/apps/web/dist" ] && [ -f "${ROOT}/apps/web/dist/index.html" ]; then
  web_dist_ok=true
fi

api_ok=false
if [ -f "${ROOT}/apps/api/bootstrap/cache/packages.php" ] || [ -d "${ROOT}/apps/api/bootstrap/cache" ]; then
  api_ok=true
fi

commit="$(git rev-parse HEAD 2>/dev/null || printf 'unknown')"

cat >"${OUT_FILE}" <<EOF
{
  "check": "PH-09",
  "commit": "${commit}",
  "generated_at": "${STAMP}",
  "web_dist_present": ${web_dist_ok},
  "api_bootstrap_cache_present": ${api_ok},
  "rollback_checklist": [
    "Retain previous dist/image tag until health checks pass",
    "Repoint staging static host/proxy to previous artifact",
    "Re-check /health/live and /health/ready",
    "Spot-check Learn home and operator console"
  ],
  "note": "Dry-run only — not production cutover or Play promotion"
}
EOF

printf 'Wrote %s\n' "${OUT_FILE}"
if [ "${web_dist_ok}" != true ]; then
  echo 'web dist missing after make build' >&2
  exit 1
fi
