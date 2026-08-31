---
status: accepted
date: 2026-08-30
deciders:
  - S R Bhandary as Accountable Product Owner and Technical Decision Owner
consulted:
  - Cursor agent as author and evidence recorder, not approver
informed: []
amends_master_plan: false
approval_evidence: []
approval_reference: Decision D-0240 in the active Cursor conversation accepting DR-0041 and overriding the Phase 1 freeze for a client viseme overlay
---

# ADR-0029: Client viseme overlay for Swathi without a new TTS provider

## Status

Accepted on 30 August 2026 as D-0240, amended on 31 August 2026 by D-0244
to complete the visible client integration and local PCM fallback, and amended
by D-0245 to make the visual rig coach-generic across the selected realistic
female/male coach. This ADR does **not** amend the master plan. It does not
authorize ElevenLabs, production/Play, or sending learner recordings to a
viseme or TTS vendor.

## Context

[DR-0041](../proposals/DR-0041_viseme_animation_rig_blueprint.md) specified a
future viseme-driven avatar. D-0240 accepted that blueprint for a **client**
overlay on Speak. Azure `kn-IN-SapnaNeural` remains the live guidance TTS
path. Learner hold-to-talk audio still follows `config/voice.php` and is not
forwarded for mouth-shape annotation.

## Decision

1. Add a project-owned `AnimationPayload` contract (`VisemeTimestamp`,
   `AvatarGesture`) in `@global-english/contracts`. Empty or corrupt payloads
   do not animate.
2. Drive mouth shapes from `HTMLAudioElement.currentTime` via
   `requestAnimationFrame` (`useVisemePlayer`). No new speech provider.
3. Render the SVG Swathi rig in an idle state before speech and animate it when
   a valid payload is present. A scoped error boundary returns to the existing
   `TutorPresence` photograph if the rig throws.
4. Gesture CSS (`ENCOURAGING_NOD`, `WARM_SMILE`, `ATTENTIVE_LEAN`) is
   decorative and never writes marks, mastery, or consent.
5. Backend: Extract viseme timestamps from Azure Speech Synthesis responses
   via `AzureVisemeExtractor`. Map Azure `VisemeId` and `AnimationOffset` into
   our contract's `VisemeTimestamp[]` format. Bundle into `AnimationPayload`
   and return via the `GuidanceSpeechService` response.
6. Azure's live REST WAV response contains no synthesis viseme event stream.
   When provider visemes are absent, derive bounded `rest`, `slightly_open`,
   `open`, and `wide` cues locally from the already validated 16-bit mono PCM
   envelope. No audio or transcript leaves the existing provider boundary for
   this derivation.
7. Expose the optional animation payload in OpenAPI and generated TypeScript
   contracts. `ConversationJourney` selects each reply's payload before audio
   playback and drives it from that reply's `HTMLAudioElement`.

## Consequences

- Speak displays the local Swathi SVG rig and lip movement for live Azure
  model audio. Provider event visemes remain more precise and take precedence
  if Azure supplies them in a future transport.
- PCM-envelope cues represent synchronized mouth openness, not phoneme-level
  pronunciation evidence, and must never be scored as such.
- Rive and ElevenLabs remain out of this checkpoint.
- Azure responses may now include optional viseme metadata. The extractor
  safely handles responses with no visemes (fallback to empty array).

## Security, privacy and tenancy impact

- Viseme arrays are mouth-shape labels and millisecond offsets only. They
  must not include transcripts, audio bytes, or learner identifiers.
- The overlay does not change voice upload, retention, or consent.
- Azure viseme extraction happens server-side on TTS synthesis responses only.
  No learner audio is transmitted or logged beyond the Azure Speech service
  boundary.
