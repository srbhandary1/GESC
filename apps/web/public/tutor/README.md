# Swathi 3D tutor

**Default:** the app shows the **professional portrait** (`tutor-maya.png`) with
speaking animation while Swathi talks.

**Professional 3D** requires purchased GLB models with app redistribution
rights. The app loads avatars from **`/tutor/avatars/`** only when
`LICENSE-tutor-avatars.txt` contains a completed grant and the chosen GLB file
exists. The untouched template is deliberately rejected.

For lip sync, the model must contain either ARKit-compatible facial morph
targets (including `jawOpen`) or an actual jaw bone. A body-only Mixamo skeleton
can drive shoulders, arms and hands, but it cannot move lips. Static meshes can
only receive whole-model ambient movement.

Do **not** add models without a matching license file. GLB binaries stay outside
git (see `apps/web/.gitignore`).

## Import downloaded GLB files

Copy your three `.glb` files into a folder, then from the repo root:

```text
make import-tutor-glb SOURCE=/path/to/GLB
make soft-launch-refresh-web
```

The import script installs up to three files as:

```text
apps/web/public/tutor/avatars/swathi-1.glb
apps/web/public/tutor/avatars/swathi-2.glb
apps/web/public/tutor/avatars/swathi-3.glb
apps/web/public/tutor/LICENSE-tutor-avatars.txt
```

Copy `LICENSE-tutor-avatars.TEMPLATE.txt` to `LICENSE-tutor-avatars.txt` and
fill in the public vendor, SKU, date, license type, and licensee fields if the
import script created it from the template. Keep order IDs, invoices, account
email addresses, and payment details outside the public web root.

From a Mac when the Linux server is reachable:

```text
# Verify local folder (all files and subfolders)
ls -lahR ~/Downloads/GLB/

# Copy the entire tree — models may live in subfolders, not only the top level
scp -r ~/Downloads/GLB/. \
  srbhandary@100.89.51.18:/home/srbhandary/projects/global-spoken-english/apps/web/public/tutor/avatars/incoming/

ssh srbhandary@100.89.51.18 \
  'cd /home/srbhandary/projects/global-spoken-english && make import-tutor-glb SOURCE=apps/web/public/tutor/avatars/incoming && make verify-tutor-glb'
```

Hard-refresh Learn / Speak. Open the account menu and choose **Tutor character**
to switch between Portrait and the imported GLB models.

Staff invoices may stay outside git. The **license grant** must sit in
`apps/web/public/tutor/`.
