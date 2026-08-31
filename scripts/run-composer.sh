#!/bin/sh
# Composer 2.10.2 from the PHP 8.4 verify image. See scripts/run-php84.sh.

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
  -e COMPOSER_HOME=/tmp/composer \
  -v "$root:/work" \
  -w "$workdir" \
  ge-php84-pgsql:local \
  composer "$@"
