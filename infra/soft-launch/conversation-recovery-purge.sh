#!/usr/bin/env bash
# Pinned additive-schema cleanup survives application-code rollback.
set -euo pipefail
/usr/local/libexec/ge-conversation-recovery-memory check
cd /srv/global-spoken-english/releases/student-conversation-20260925
source scripts/lib/soft-launch-environment.sh
load_soft_launch_environment
exec /srv/global-spoken-english/toolchain/php/php apps/api/artisan conversation:purge-recovery
