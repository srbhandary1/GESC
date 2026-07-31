#!/bin/sh

set -eu

repository_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
expected_node=$(cat "$repository_root/.node-version")
expected_pnpm=$(sed -n 's/.*"packageManager": "pnpm@\([^"]*\)".*/\1/p' "$repository_root/package.json")
local_node_bin="$repository_root/tmp/toolchain/node/bin"

if [ -x "$local_node_bin/node" ] && [ -x "$local_node_bin/pnpm" ]; then
    PATH="$local_node_bin:$PATH"
    export PATH
    COREPACK_HOME="$repository_root/tmp/toolchain/corepack"
    COREPACK_DEFAULT_TO_LATEST=0
    export COREPACK_HOME COREPACK_DEFAULT_TO_LATEST
fi

if ! command -v node >/dev/null 2>&1 || [ "$(node --version | sed 's/^v//')" != "$expected_node" ]; then
    echo "Node.js $expected_node is required. Run make bootstrap after installing the pinned toolchain." >&2
    exit 1
fi

if ! command -v pnpm >/dev/null 2>&1 || [ "$(pnpm --version)" != "$expected_pnpm" ]; then
    echo "pnpm $expected_pnpm is required. Run make bootstrap after installing the pinned toolchain." >&2
    exit 1
fi

cd "$repository_root"
exec env VITE_LOCAL_DEMO=true pnpm --filter @global-english/web dev --host 127.0.0.1 --port 5173
