#!/usr/bin/env bash
# Run on Real-Studioz. Rebuilds the served web dist and reloads the public HTTP
# listener so browsers pick up catalogue/UI changes (e.g. Swathi naming).
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
export PATH="${ROOT}/tmp/toolchain/node/bin:${PATH:-}"
git fetch origin cursor/learner-variant-c-jezweb-3f88
git checkout cursor/learner-variant-c-jezweb-3f88
git pull origin cursor/learner-variant-c-jezweb-3f88
make soft-launch-refresh-web
make soft-launch-reload-public-http 2>/dev/null || make soft-launch-restart
echo "Web refreshed. Hard-reload browsers (Ctrl+Shift+R) or clear site data if UI still looks stale."
echo "Verify: curl -s http://117.254.108.143:8080/assets/marketing-landing-*.js | rg -o 'shell.mira.name[^\\\`]*\\\`[^\\\`]*\\\`' | head -1"
