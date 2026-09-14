#!/usr/bin/env bash
# Run on Real-Studioz only. Builds and publishes the closed-beta APK plus web
# dist with Swathi naming and jezweb UX fixes.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
exec bash "${ROOT}/scripts/deploy-jezweb-c2-apk.sh"
