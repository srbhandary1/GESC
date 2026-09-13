#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SOURCE="${1:-${GE_TUTOR_GLB_SOURCE:-}}"
TARGET="${ROOT}/apps/web/public/tutor/avatars"
MANIFEST="${TARGET}/manifest.json"
LICENSE="${ROOT}/apps/web/public/tutor/LICENSE-tutor-avatars.txt"
LICENSE_TEMPLATE="${ROOT}/apps/web/public/tutor/LICENSE-tutor-avatars.TEMPLATE.txt"

if [[ -z "${SOURCE}" ]]; then
  echo "Usage: import-tutor-glb.sh <source-directory>" >&2
  echo "  or set GE_TUTOR_GLB_SOURCE" >&2
  exit 1
fi

if [[ ! -d "${SOURCE}" ]]; then
  echo "Source directory not found: ${SOURCE}" >&2
  exit 1
fi

mkdir -p "${TARGET}"

echo "Source inventory (${SOURCE}):"
find "${SOURCE}" \( -type f -o -type d \) ! -name '.DS_Store' -print | sort | while IFS= read -r entry; do
  if [[ -d "${entry}" ]]; then
    printf '  [dir]  %s\n' "${entry#${SOURCE}/}"
  else
    size="$(wc -c < "${entry}" | tr -d ' ')"
    printf '  [file] %s (%s bytes)\n' "${entry#${SOURCE}/}" "${size}"
  fi
done

python3 - <<'PY' "${SOURCE}" "${TARGET}" "${MANIFEST}"
import json
import re
import shutil
import sys
from pathlib import Path

source = Path(sys.argv[1])
target = Path(sys.argv[2])
manifest_path = Path(sys.argv[3])

def human_label(name: str) -> str:
    cleaned = re.sub(r"[_\-]+", " ", name).strip()
    return cleaned.title() if cleaned else name

packages: list[dict[str, str]] = []

for path in sorted(source.iterdir()):
    if path.name.startswith("."):
        continue
    if path.is_file() and path.suffix.lower() == ".glb":
        packages.append(
            {
                "kind": "file",
                "label": human_label(path.stem),
                "source": path,
            }
        )
        continue
    if not path.is_dir():
        continue

    glbs = sorted(path.rglob("*.glb"))
    preferred = [
        candidate
        for candidate in glbs
        if candidate.name.lower() in {"model.glb", "scene.glb"}
    ]
    chosen_glb = preferred[0] if preferred else (glbs[0] if glbs else None)
    if chosen_glb is not None:
        packages.append(
            {
                "kind": "file",
                "label": human_label(path.name),
                "source": chosen_glb,
            }
        )
        continue

    gltfs = sorted(path.rglob("*.gltf"))
    preferred_gltf = [
        candidate
        for candidate in gltfs
        if candidate.name.lower() in {"scene.gltf", "model.gltf"}
    ]
    chosen_gltf = preferred_gltf[0] if preferred_gltf else (gltfs[0] if gltfs else None)
    if chosen_gltf is not None:
        packages.append(
            {
                "kind": "folder",
                "label": human_label(path.name),
                "source": path,
                "entry": chosen_gltf.relative_to(path),
            }
        )

if not packages:
    raise SystemExit(f"No .glb or .gltf packages found under {source}")

if len(packages) > 3:
    print(
        f"Found {len(packages)} model packages; using the first three.",
        file=sys.stderr,
    )
    packages = packages[:3]

for slot in range(1, 4):
    slot_path = target / f"swathi-{slot}"
    legacy_file = target / f"swathi-{slot}.glb"
    if slot_path.is_dir():
        shutil.rmtree(slot_path)
    if legacy_file.is_file():
        legacy_file.unlink()

avatars = []
for index, package in enumerate(packages, start=1):
    slot = f"swathi-{index}"
    if package["kind"] == "file":
        destination = target / f"{slot}.glb"
        shutil.copy2(package["source"], destination)
        public_path = f"/tutor/avatars/{slot}.glb"
        print(f"Installed {slot}.glb <= {package['source'].relative_to(source)}")
    else:
        destination_dir = target / slot
        if destination_dir.exists():
            shutil.rmtree(destination_dir)
        shutil.copytree(
            package["source"],
            destination_dir,
            ignore=shutil.ignore_patterns(".DS_Store"),
        )
        entry = package["entry"].as_posix()
        public_path = f"/tutor/avatars/{slot}/{entry}"
        print(
            f"Installed {slot}/ <= {package['source'].relative_to(source)} "
            f"(entry {entry})"
        )

    avatars.append(
        {
            "id": slot,
            "label": package["label"],
            "path": public_path,
        }
    )

manifest_path.write_text(
    json.dumps(
        {
            "license_path": "/tutor/LICENSE-tutor-avatars.txt",
            "avatars": avatars,
        },
        indent=2,
    )
    + "\n"
)
PY

if [[ ! -f "${LICENSE}" && -f "${LICENSE_TEMPLATE}" ]]; then
  cp "${LICENSE_TEMPLATE}" "${LICENSE}"
  echo "Created ${LICENSE} from template — fill in purchase details before production."
fi

echo "Tutor GLB import complete. Run: make verify-tutor-glb"
