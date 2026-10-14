#!/usr/bin/env bash
# VPS-specific recovery mount. Never silently fall back to persistent storage.
set -euo pipefail
ge_recovery=/srv/global-spoken-english/releases/adr0041/apps/api/storage/app/conversation-recovery
if [[ "${1:-check}" == mount ]]; then
  [[ $(id -u) == 0 ]]
  [[ ! -L "$ge_recovery" ]]
  if ! mountpoint -q "$ge_recovery"; then
    install -d -o globalenglish -g globalenglish -m 0700 "$ge_recovery"
    [[ -z $(find "$ge_recovery" -mindepth 1 -maxdepth 1 -print -quit) ]]
    mount -t tmpfs -o noswap,nodev,nosuid,noexec,size=256M,mode=0700,uid=1000,gid=1000 ge-conversation-recovery "$ge_recovery"
  fi
fi
mountpoint -q "$ge_recovery"
[[ $(findmnt -n -o FSTYPE --target "$ge_recovery") == tmpfs ]]
ge_options=",$(findmnt -n -o OPTIONS --target "$ge_recovery"),"
for ge_option in noswap nodev nosuid noexec; do
  [[ "$ge_options" == *",$ge_option,"* ]]
done
[[ $(stat -c '%a:%u:%g' "$ge_recovery") == 700:1000:1000 ]]
