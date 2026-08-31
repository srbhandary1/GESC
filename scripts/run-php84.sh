#!/bin/sh
# Run the pinned PHP 8.4 CLI (pcntl + pdo_pgsql) when the host php is not 8.4.
# Usage: make verify PHP="$PWD/scripts/run-php84.sh" COMPOSER_CMD="$PWD/scripts/run-composer.sh"

set -eu

root="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
case "$PWD" in
"$root")
  workdir="/work"
  ;;
"$root"/*)
  workdir="/work/${PWD#"$root"/}"
  ;;
*)
  workdir="/work"
  ;;
esac

exec docker run --rm --network host \
  -e HOME=/tmp \
  -v "$root:/work" \
  -w "$workdir" \
  ge-php84-pgsql:local \
  php "$@"
