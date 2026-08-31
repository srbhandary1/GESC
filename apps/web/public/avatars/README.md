# Project-owned 3D tutor avatars

`coach-female.glb` and `coach-male.glb` are original meshes baked by
`scripts/generate-tutor-glb.py`. They are not Ready Player Me downloads,
Mixamo characters, or scans of identified people.

| File | Role |
|---|---|
| `female_tutor.glb` | Lobby alias for the female tutor (dashboard selection) |
| `male_tutor.glb` | Lobby alias for the male tutor (dashboard selection) |
| `coach-female.glb` | Same mesh; baker canonical name |
| `coach-male.glb` | Same mesh; baker canonical name |

Place both `*_tutor.glb` files in this folder (`apps/web/public/avatars/`). Vite copies `public/` to the site root, so the React config paths `/avatars/female_tutor.glb` and `/avatars/male_tutor.glb` resolve in the browser and in the Capacitor Android WebView. Do not put `.glb` files under `src/` — Vite would try to bundle them.

The dashboard map is `apps/web/src/config/avatarConfig.json`.

Each file is glTF 2.0 binary with:

- a humanoid skeleton (`Hips`, `Spine`, `Spine1`, `Neck`, `Head`, shoulders, arms, hands);
- morph targets named exactly `viseme_sil`, `viseme_aa`, `viseme_E`, `viseme_I`, `viseme_O`, `viseme_U`, `viseme_PP`, `viseme_FF`, `viseme_TH`, `viseme_DD`, `jawOpen`;
- a sibling 1024×1024 albedo PNG (`coach-female.png` / `coach-male.png`) referenced by URI so the WebGL loader does not allocate `blob:` object URLs;
- fewer than 20 000 triangles.

Regenerate after baker changes:

```text
python3 scripts/generate-tutor-glb.py
```

The web app loads these through Three.js when WebGL is available. The SVG
`InteractiveAvatar` rig remains the fallback for jsdom tests, reduced-capability
WebViews, and loader failures. Android uses the same Vite bundle via Capacitor.
