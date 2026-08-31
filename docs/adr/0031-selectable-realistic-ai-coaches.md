---
status: accepted
date: 2026-08-31
deciders:
  - S R Bhandary as Accountable Product Owner and Technical Decision Owner
consulted:
  - Cursor agent as implementer and evidence recorder, not approver
informed: []
amends_master_plan: false
approval_evidence: []
approval_reference: Accountable Product Owner instruction on 31 August 2026 to redesign the website with realistic selectable male and female avatars that can be renamed
---

# ADR-0031: Selectable realistic AI coaches with device-local display names

## Status

Accepted on 31 August 2026 and recorded as D-0245. This ADR amends D-0218 and
the Swathi-specific wording of ADR-0026 item 3 and ADR-0029. It does not
authorize production/Play, external avatar vendors, Rive, ElevenLabs, or a
server-side learner profile schema change.

Amended on 31 August 2026 by D-0246: the coach visual base is now the
project-owned inline-SVG `InteractiveAvatar` face rig (item 5 below), not a
licensed photograph. The Accountable Product Owner directed this after
reviewing the SVG rig as a superior fix for mouth-overlay clipping/drift on
mobile viewports and for having male/female art with no third-party image
licensing dependency. `CoachAvatar`/`SwathiAvatar`/`TutorPresence` keep their
existing public props (`locale`, `mood`, `size`, `audio`, `payload`,
`coachGender`, `coachName`); only their internal rendering changed. This
amendment does not change coach selection, renaming, governed default names,
prompts, provider contracts, or any authoritative record.

Amended on 31 August 2026 by D-0247: when WebGL is available, the same
`InteractiveAvatar` stage loads project-owned `coach-female.glb` /
`coach-male.glb` busts (humanoid skeleton + the named viseme morph-target
set) through Three.js. The SVG rig remains the fallback when WebGL is
absent, the GLB fails to load, or the renderer throws. This does not
introduce Ready Player Me, Rive, ElevenLabs, or a Flutter/native 3D
runtime; Android continues to wrap the same web bundle via Capacitor.

## Context

The product previously pinned the on-screen English teacher to Swathi. The
Accountable Product Owner has now directed a full learner-facing redesign with
realistic male and female AI coaches whose display names can be changed. The
existing Speak animation pipeline already supplies content-free mouth timing
and decorative gestures. Learner renaming must not alter authoritative prompts,
marks, mastery, consent, tenancy, retention, or provider payloads.

## Decision

1. The learner-facing coach system has two project-owned realistic coaches:
   one female and one male.
2. Governed default names are **Swathi** for the female coach and **Arjun** for
   the male coach. Server prompts, Azure output contracts, audit language, and
   provider-facing identity continue to use these governed defaults.
3. Learners may select either coach and enter a bounded device-local display
   name. The custom name is presentation-only, remains off authoritative
   records, and is never sent to AI or speech providers.
4. Role-play scene characters remain scene names and are not coach identities.
5. **(Superseded by D-0246, then D-0247.)** When WebGL is available the
   coach visual base is the project-owned glTF bust
   (`/avatars/coach-female.glb` or `/avatars/coach-male.glb`): a humanoid
   skeleton plus morph targets named `viseme_sil`, `viseme_aa`, `viseme_E`,
   `viseme_I`, `viseme_O`, `viseme_U`, `viseme_PP`, `viseme_FF`, `viseme_TH`,
   `viseme_DD`, and `jawOpen`. Streaming mouth-shape metadata maps onto those
   keys. The previous inline-SVG `InteractiveAvatar` rig remains the
   fallback when WebGL is missing or the GLB cannot be mounted. Existing
   local mouth-shape timing and gesture overlays remain decorative and are
   driven only by reply audio playback; learner audio is not sent to a viseme
   or animation vendor.
6. **(Superseded by D-0246, then D-0247.)** Empty, corrupt, or unsupported
   animation data falls back to the idle pose (SVG blink, or GLB rest morphs);
   an internal rendering failure of `CoachAvatar` still falls back to a
   minimal, dependency-free static initial badge that shares no code path
   with either rig.
7. Marketing, sign-in, Home, Speak, and Role-play surfaces use the selected
   coach consistently. Legacy Maya artwork may remain in the repository as
   historical assets but is not the active coach presentation.

## Security, privacy and tenancy impact

- No new server-side learner field is created.
- Coach selection and display-name preference are device-local presentation
  state and contain no credentials.
- No raw audio, transcript, provider secret, or learner identifier is included
  in animation payloads.
- Organization scoping, consent, retention, deletion, marks, mastery, and
  progression remain unchanged.

## Verification

Tests cover default and custom coach names, female/male portrait selection,
mouth animation, renderer fallback, localized controls, and the existing Speak
journey. Full verification and browser journeys are recorded in
`docs/QUALITY_REVIEW.md`.
