#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PUBLIC="${ROOT}/apps/web/public/tutor"
AVATARS="${PUBLIC}/avatars"
LICENSE="${PUBLIC}/LICENSE-tutor-avatars.txt"
BASE_URL="${GE_TUTOR_GLB_VERIFY_URL:-http://127.0.0.1:8080}"

echo "Installed avatar files:"
find "${AVATARS}" -maxdepth 2 \( -type f -name 'swathi-*.glb' -o -type f -name 'scene.gltf' -o -type f -name 'model.gltf' \) -print | sort | while IFS= read -r file; do
  size="$(wc -c < "${file}" | tr -d ' ')"
  printf '  %s (%s bytes)\n' "${file#${AVATARS}/}" "${size}"
done

if [[ ! -f "${AVATARS}/manifest.json" ]]; then
  echo "ERROR: manifest.json missing" >&2
  exit 1
fi

echo
echo "Manifest:"
cat "${AVATARS}/manifest.json"

if cmp -s "${LICENSE}" "${PUBLIC}/LICENSE-tutor-avatars.TEMPLATE.txt" || \
  grep -q 'Fill this file after purchase' "${LICENSE}"; then
  echo >&2
  echo "ERROR: LICENSE-tutor-avatars.txt is still the unfilled template." >&2
  echo "The app will not expose these models until the actual grant is recorded." >&2
  exit 1
fi

echo
echo "HTTP checks (${BASE_URL}):"
python3 - <<'PY' "${AVATARS}/manifest.json" "${PUBLIC}/LICENSE-tutor-avatars.txt" "${BASE_URL}"
import json
import sys
import urllib.error
import urllib.request
from pathlib import Path

manifest = json.loads(Path(sys.argv[1]).read_text())
license_path = Path(sys.argv[2])
base_url = sys.argv[3].rstrip("/")

def check(path: str) -> None:
    url = f"{base_url}{path}"
    request = urllib.request.Request(url, method="HEAD")
    try:
        with urllib.request.urlopen(request, timeout=5) as response:
            content_type = response.headers.get("content-type", "")
            length = response.headers.get("content-length", "?")
            ok_types = (
                "model/gltf",
                "model/gltf+json",
                "text/plain",
                "application/json",
            )
            ok = any(token in content_type for token in ok_types)
            status = "OK" if ok and "text/html" not in content_type else "BAD"
            print(f"  [{status}] {path} -> {content_type} ({length} bytes)")
    except urllib.error.HTTPError as error:
        print(f"  [HTTP {error.code}] {path}")
    except Exception as error:
        print(f"  [ERR] {path}: {error}")

print(f"  [{'OK' if license_path.is_file() else 'MISSING'}] LICENSE-tutor-avatars.txt on disk")
check(manifest["license_path"])
check("/tutor/avatars/manifest.json")
for avatar in manifest.get("avatars", []):
    check(avatar["path"])
PY
