# DR-0039 — Pronunciation map, viseme avatar, speech pacing, transliteration, Indic activation

## Status

**Avatar slice (item 2) accepted** (6 September 2026) under product-owner instruction,
recorded as **D-0287** (v1), **D-0289** (original bone-rig v2), **D-0290**
(licensed GLB loader), **D-0291** (original sculpt v3), and **D-0292** (toon
teacher v4). Phase **v1** shipped viseme timelines and a primitive bust.
**D-0289** added a project-owned joint hierarchy. **D-0292** is the default 3D
teacher: toon-shaded original geometry (teal shirt, auburn ponytail, gold
glasses) with visible mouth and hand motion. **D-0290** still loads
`/tutor/swathi.glb` only when that file and an in-folder license grant are both
present. Azure Speech SDK sidecar, a purchased binary, and voice clone remain
later.

**D-0294** (7 September 2026) repairs missing playback-state wiring in Role-play
and separately synthesized opening speech, and expands numbered Mixamo body-joint
motion. An asset audit found that all three locally imported candidates lack
facial morph targets and a jaw bone; only candidate 2 has a body skeleton, and
its bundled license is CC-BY-NC-4.0. The central grant is still the unfilled
template, so these candidates are now rejected by runtime discovery. True
professional lip sync remains blocked on an eligible facial-rigged GLB.

- optional `model_avatar_viseme_timeline` / `guidance_avatar_viseme_timeline` on
  `ConversationSpeechResult`;
- server-side **text-duration-estimator-v1** viseme frames aligned to returned WAV
  duration (Azure REST TTS cannot emit visemes today);
- lazy-loaded **Swathi** toon teacher (D-0292) in Speak / Learn Conversation
  surfaces only;
- portrait PNG + reduced-motion fallback; no WebGL on guest marketing (D-0279).

**Items 1, 3, 4 and 5 remain proposed** and are not authorized by D-0287:

1. advisory pronunciation colour map;
3. learner speech-speed control;
4. Kanglish transliteration display;
5. te / ta / ml activation gates beyond existing governance.

## Request

The product owner’s “Phase 1 – Multi-Language Ready” brief (30 August 2026) asked for
five additions on top of the reconciled DR-0038 baseline. This document separates them
so each can be accepted or sequenced independently.

### Item 2 — Viseme-driven animated tutor (this slice)

Restore the **D-0213** character-led tutor requirement with a **female 3D Swathi**
identity:

- precise **lip sync** driven by viseme timelines;
- simple **hand and shoulder gestures** while speaking;
- surfaces: **Speak** (everyday talk), **Learn → Conversation**, and Role-play Perform
  (same `TutorPresence` contract);
- **not** the guest Futuristic Learning Studio hero (UX-01 anti-goal: no WebGL landing).

Historical gap: D-0213 was accepted 29 August 2026 but delivery stayed on static
`tutor-maya.png` orbits. Azure guidance TTS uses REST WAV synthesis without viseme
events (Speech SDK only).

## Current product

- `TutorPresence.tsx` — two PNG portraits and CSS mood orbits.
- TTS — `GuidanceSpeechService` / `ConversationTurnService` return base64 WAV only.
- Client playback — `playBase64Audio` with no mouth timeline.
- Teacher identity — **Swathi** (D-0218); filenames may remain `tutor-maya.png`.

## Conflicts and constraints

| Topic | Constraint |
|---|---|
| Guest landing WebGL | D-0279 / UX-01: abstract disclosed AI presence only |
| Azure REST TTS | No viseme callback; v1 uses duration-aligned estimator |
| Android performance | 3D lazy-loaded; reduced-motion and low-power fallback to PNG |
| Marks / mastery | Avatar is presentational; no AI authority change |
| Provider leakage | Viseme `source` is contract metadata, not a vendor SDK type |

## Accepted v1 design (D-0287)

### API

OpenAPI adds:

- `AvatarVisemeFrame` — `{ offset_ms, viseme_id }` where `viseme_id` is 0–21 (Azure
  viseme set);
- `AvatarVisemeTimeline` — `{ source, duration_ms, frames[] }`;
- optional timelines on `ConversationSpeechResult` for model and guidance audio.

`source` values:

- `text-duration-estimator-v1` — shipped in v1;
- `azure-speech-sdk-v2` — reserved for a future SDK bridge sidecar.

### Server

`AvatarVisemeTimelineEstimator` maps normalized English text to viseme segments, weights
vowels/consonants, and scales segment boundaries to inspected WAV `duration_ms` via
`PcmWavInspector`. Null when no playable audio.

### Client

- Dependencies: `three`, `@react-three/fiber`, `@react-three/drei` (conversation chunk
  only via `React.lazy`).
- `SwathiAvatarScene` — original project-owned bone hierarchy (hips→spine→chest→
  neck/head/jaw and clavicle→arm→hand). Viseme timeline drives the jaw. Not a
  third-party GLB.
- `TutorPresence` — `enableAvatar3d` prop; falls back to PNG when
  `prefers-reduced-motion: reduce` or WebGL init fails.
- `playBase64Audio` — `onTimeUpdate` for lip-sync clock.
- Client-side estimator fallback when timeline absent (demo / legacy responses).

### Phased follow-up

| Phase | Scope |
|---|---|
| v2 avatar rig (D-0289) | Original project-owned bone hierarchy (spine, neck, head, jaw, clavicles, arms, hands) with viseme-driven jaw; not a purchased Mixamo/RPM GLB |
| v2 later | Azure Speech SDK sidecar → `azure-speech-sdk-v2` authoritative timelines |
| v2 loader (D-0290) | Load `/tutor/swathi.glb` when the GLB and `LICENSE-swathi-glb.txt` are both present; ARKit morph map from Azure visemes; keep D-0289 rig otherwise |
| v4 teacher (D-0292) | Toon-shaded original Swathi (`original-sculpt-v4`): teal shirt, auburn ponytail, gold glasses; visible syllable mouth and teaching-hand motion; Fab look reference only |
| v3 | Shoulder/hand IK tied to emphasis markers; pronunciation colour map overlay |
| v4 | Speech-speed control + Kanglish transliteration + te/ta/ml activation gates |

## Verification

- PHPUnit unit tests for estimator segment boundaries and WAV alignment.
- Vitest for viseme weight interpolation and reduced-motion fallback selection.
- Speak conversation journey still passes existing latency instrumentation (DR-0038 C).
- Guest marketing Playwright contract unchanged (no canvas/WebGL on landing).

## Authority

Product-owner confirmation to proceed, 6 September 2026. Implements the avatar portion
of DR-0039 only; does not authorize production, Play, new providers, or guest WebGL.
