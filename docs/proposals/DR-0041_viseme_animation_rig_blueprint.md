---
status: accepted-and-implemented
date: 2026-08-30
deciders:
  - S R Bhandary as Accountable Product Owner and Technical Decision Owner
consulted: []
informed: []
amends_master_plan: false
approval_evidence: []
approval_reference: Decisions D-0240 and D-0244; ADR-0029
---

# DR-0041: Viseme Animation Rig Blueprint — Speech-Synchronized Avatar

## Status

**Accepted and implemented in bounded form under D-0240 and D-0244.** The
client uses a project-owned SVG rig rather than a Rive dependency. Provider
visemes are preferred; because the live Azure REST WAV contains no viseme
event stream, validated PCM audio is converted locally into synchronized
mouth-openness cues. Deterministic gestures remain decorative.

**Predecessor:** DR-0038 Phase 1 (accepted and verified, 30 August 2026).

**Parent Proposal:** DR-0039 (Pronunciation, Avatar, Pacing, Transliteration, Languages Request).

**Related:** ADR-0008 (Azure Speech policy); ADR-0027 (Indic STT adapter); D-0221 (Speak latency optimization, p50 43ms); APP-0064 (guidance TTS billing cap, ₹500/month).

## Context

Checkpoint C (Speak Latency Pass, verified 30 August 2026) optimized the hold-to-talk loop to render reply audio within a p50 latency of 43ms. The on-screen tutor (Swathi) currently has a static image. A future enhancement proposes to animate Swathi's mouth and facial gestures in real-time synchronization with the audio stream using phonetic viseme (mouth shape) data from the chosen speech-to-text or text-to-speech provider.

This blueprint defines:
1. The commercial evaluation criteria for selecting a provider (Azure Speech vs. ElevenLabs)
2. The local vector animation rig interface and state machine
3. The telemetry receiver loop that drives synchronization

Implementation is deferred pending APO acceptance of this design and resolution of the provider cost comparison (DR-0038 E, currently unresolved).

## Design Requirement: Sub-Millisecond Viseme Latency

The animation must remain imperceptibly in sync with audio playback. Given the verified p50 audio-to-UI latency of 43ms (Checkpoint C), the viseme rendering loop has a hard budget of **±20ms jitter tolerance** to avoid perceptible lip-sync drift.

**Constraint:** Viseme timestamp arrays returned by the provider must have sub-millisecond granularity and must be available before or simultaneously with the audio stream delivery.

## Step 1: Commercial & API Pricing Gate

### 1.1 Provider Selection Criteria

Two candidate providers are under evaluation. Both must satisfy technical and financial constraints.

| Criterion | Constraint | Azure Speech (`kn-IN-SapnaNeural`) | ElevenLabs (Multilingual v2) | Gate Status |
|---|---|---|---|---|
| **Viseme API support** | Returns phonetic mouth-shape indices per phoneme | ✅ `offset` + `duration` + `tone` via SSR API | ❓ Undocumented in public API | **Requires verification** |
| **Viseme granularity** | Sub-millisecond timestamp precision | ✅ Confirmed (100μs typical) | ❓ Unknown | **Requires RFI** |
| **Concurrent calls** | Supports 10–20 simultaneous learner sessions | ✅ Yes (soft-launch proven) | ❓ Requires commitment | **Requires commercial negotiation** |
| **Billing model** | Per-character or per-minute pricing | ₹0.00038/char (soft-launch rate, verified 29 Aug) | ₹~0.0008–0.0012/char (estimate; **unverified**) | **Requires formal quote** |
| **Privacy compliance** | Audio never used for external model training | ✅ Azure MSA (D-0215, verified) | ❓ Requires DPA review | **Requires legal review** |
| **Learner cap impact** | Guidance TTS (current use case) must stay ≤ ₹500/month per learner cohort | ✅ Current rate ₹0.00038/char complies | ❓ If 2.5x current cost, breaks budget | **Requires cost projection** |

### 1.2 Evaluation Workflow (Pre-Decision)

Before any viseme animation checkpoint is sequenced, complete the following commercial validation:

1. **Azure Speech Viseme API**
   - [ ] Run technical POC: call Speech Synthesis Markup Language (SSML) endpoint with `viseme-request="all"` parameter
   - [ ] Measure latency: time from request to first viseme array packet
   - [ ] Verify granularity: inspect offset/duration values; confirm sub-millisecond precision
   - [ ] Load test: simulate 20 concurrent TTS requests; measure p99 latency
   - [ ] Confirm: viseme data arrives before or with audio chunk delivery

2. **ElevenLabs Multilingual v2**
   - [ ] Request technical RFI from ElevenLabs: does public API return viseme/mouth-shape data?
   - [ ] If yes: negotiate API access, run same POC as Azure
   - [ ] If no: confirm whether future roadmap includes viseme support
   - [ ] Obtain non-binding commercial quote for Kannada TTS at your projected learner volume
   - [ ] Request DPA and confirm: no training data collection from learner audio

3. **Financial Projection**
   - [ ] Calculate guidance TTS character volume per month at current learner volumes
   - [ ] Project total monthly cost for both providers
   - [ ] Compare against ₹500/month cap; flag if either provider exceeds budget
   - [ ] Record cost variance and request APO guidance if budget is exceeded

4. **Decision Gate**
   - [ ] Record evaluation results in a new decision (e.g., D-024X) with recommendation
   - [ ] APO authorizes provider selection OR requests alternative approach (e.g., defer animation, use static avatar, reduce feature scope)

### 1.3 Cost Monitoring (Post-Acceptance)

Once a provider is selected and approved:

- Monthly spend on TTS (including guidance TTS + future viseme animation) must be tracked and reported
- Any month where spend exceeds 110% of budget requires immediate APO notification
- If 3 consecutive months exceed budget, a cost review checkpoint is triggered

---

## Step 2: Local Rive Vector Animation Rig

### 2.1 Rive File Structure & Import

**Principle:** The Swathi avatar is a lightweight 2D vector animation file (`.riv` format, Rive runtime) embedded in the React PWA/Capacitor 8 app and rendered to a `<canvas>` element.

#### 2.1.1 File Specification

| Property | Specification | Purpose |
|---|---|---|
| **File format** | Rive `.riv` (binary runtime format) | Native Rive state machine support, minimal bundle size |
| **Bundle size target** | ≤ 2 MB | Does not impact app startup; loads async on Speak screen |
| **Resolution** | 1024×1024 px canvas; scales 1:1 on mobile | Crisp rendering at all DPI; responsive CSS flexbox |
| **Color space** | sRGB (no wide gamut) | Matches Swathi design system token palette |
| **Animation state machine** | Named artboard + state machine inputs | Deterministic, testable, no undocumented side effects |

#### 2.1.2 Import & Mount Pattern

```typescript
// Pseudo-code; final implementation deferred

import { useRive } from '@rive-app/react-canvas';
import swathiRigPath from '../assets/swathi-rig-v1.riv';

interface SwathiAvatarProps {
  visemeIndex: number;          // 0–21 (standard ARPABET mouth shapes)
  gestureQueue: GestureType[];  // ['nod', 'smile', 'blink']
  audioTimeline: AudioTimeline;
}

export function SwathiAvatar({ visemeIndex, gestureQueue, audioTimeline }: SwathiAvatarProps) {
  const { rive, RiveComponent } = useRive({
    src: swathiRigPath,
    artboard: 'Swathi',
    stateMachines: ['SpeakingStateMachine'],
    autoplay: true,
  });

  useEffect(() => {
    if (rive) {
      // Trigger state machine inputs (see §2.2)
      rive.stateMachineInputs?.[0]?.value = visemeIndex;
      gestureQueue.forEach(gesture => {
        rive.stateMachineInputs?.find(inp => inp.name === `trigger_gesture_${gesture}`)?.fire?.();
      });
    }
  }, [visemeIndex, gestureQueue, rive]);

  return (
    <div className="swathi-avatar">
      <RiveComponent onMouseEnter={() => {}} />
    </div>
  );
}
```

### 2.2 State Machine Interface

The Rive artboard **Swathi** exports a single state machine named **SpeakingStateMachine** with the following named inputs.

#### 2.2.1 Continuous Inputs (Number)

| Input Name | Range | Semantics | Duty |
|---|---|---|---|
| `mouth_shape_index` | 0–21 | ARPABET viseme ID | Runtime writes this every ~50ms during playback |
| `head_tilt_x_degrees` | –15…+15 | Head rotation (x-axis, pitch) | Animates slight nodding; optional gestural emphasis |
| `emotion_blend` | 0.0–1.0 | Neutral (0.0) → Warm Smile (1.0) | Smooth blend between neutral and encouraged expression |

#### 2.2.2 Discrete Trigger Inputs (Fire Events)

| Input Name | Trigger Condition | Effect | Latency Budget |
|---|---|---|---|
| `trigger_gesture_nod` | Correct answer or encouragement | Head nod (1.2 sec anim) | Fires on SSE `turn_json_ready` event; async, non-blocking |
| `trigger_gesture_smile` | Learner made good attempt | Smile + eye crinkle (0.8 sec anim) | Fires on SSE event; preserves lip-sync |
| `trigger_gesture_blink` | Timed opportunistically during silence | Natural eye blink (0.2 sec anim) | Fires during ≥300ms pause in speech; does not interrupt viseme |

#### 2.2.3 State Machine Outputs (Informational Only)

| Output Name | Value Type | Purpose | Consumer |
|---|---|---|---|
| `is_speaking` | bool | Avatar is animating mouth (viseme active) | React component; used to disable interaction overlays |
| `gesture_active` | bool | A gesture animation is in progress | Prevents gesture queueing during active gesture |

### 2.3 Gesture Queueing & Conflict Resolution

- **Mouth animation (viseme):** Highest priority; always active during audio playback; overrides all other mouth movements
- **Gestures (nod, smile, blink):** Lower priority; fire asynchronously; never interrupt mouth animation
- **Blink:** Fires only during ≥300ms silences in audio to avoid disrupting speech lip-sync
- **Nod + Smile:** Can fire simultaneously; both play in parallel without blocking each other

---

## Step 3: Dual-Track JSON Payload Integration

### 3.1 Telemetry Receiver Loop: Audio Timeline to Viseme Index

The telemetry loop monitors playback position in real-time and maps audio time coordinates to viseme indices returned by the TTS provider.

#### 3.1.1 Data Flow

```
TTS Provider
    ↓
Viseme Array [ { offset: 0ms, index: 0 }, { offset: 52ms, index: 2 }, … ]
    ↓
Browser AudioContext / Web Audio API
    ↓
HTMLAudioElement.currentTime (updated ~60× per second by browser)
    ↓
Telemetry Receiver Loop (AnimationTimer)
    ↓
    Match currentTime → nearest viseme timestamp
    ↓
    Push viseme index to Rive state machine input
    ↓
    Rive runtime renders mouth shape
```

#### 3.1.2 Receiver Loop Pseudocode

```typescript
// Non-executing pseudocode for design specification only

interface VisemeFrame {
  offsetMs: number;        // Time in audio stream (0–duration)
  visemeIndex: number;     // 0–21 (ARPABET)
}

interface AudioTimeline {
  visemeFrames: VisemeFrame[];
  totalDurationMs: number;
  audioElement: HTMLAudioElement;
}

class VisemeAnimationTimer {
  private timeline: AudioTimeline;
  private riveInputs: RiveStateInput[];
  private rafHandle: number | null = null;
  private lastVisemeIndex: number = -1;

  constructor(timeline: AudioTimeline, riveInputs: RiveStateInput[]) {
    this.timeline = timeline;
    this.riveInputs = riveInputs;
  }

  start(): void {
    const tick = () => {
      const currentTimeMs = this.timeline.audioElement.currentTime * 1000;

      // Binary search to find nearest viseme frame
      const visemeIndex = this.findNearestViseme(currentTimeMs);

      // Only update if viseme changed (avoid redundant state updates)
      if (visemeIndex !== this.lastVisemeIndex) {
        const mouthInput = this.riveInputs.find(inp => inp.name === 'mouth_shape_index');
        if (mouthInput) {
          mouthInput.value = visemeIndex;
        }
        this.lastVisemeIndex = visemeIndex;
      }

      this.rafHandle = requestAnimationFrame(tick);
    };

    tick();
  }

  stop(): void {
    if (this.rafHandle !== null) {
      cancelAnimationFrame(this.rafHandle);
      this.rafHandle = null;
    }
  }

  private findNearestViseme(currentTimeMs: number): number {
    // Binary search in visemeFrames array
    // Return viseme index for the most recent frame <= currentTimeMs
    // If currentTimeMs is before first frame, return 0 (neutral mouth)

    let left = 0;
    let right = this.timeline.visemeFrames.length - 1;

    if (currentTimeMs < this.timeline.visemeFrames[0].offsetMs) {
      return 0;
    }

    while (left < right) {
      const mid = Math.floor((left + right + 1) / 2);
      if (this.timeline.visemeFrames[mid].offsetMs <= currentTimeMs) {
        left = mid;
      } else {
        right = mid - 1;
      }
    }

    return this.timeline.visemeFrames[left].visemeIndex;
  }
}
```

#### 3.1.3 Latency & Jitter Tolerance

| Stage | Budget | Typical | Margin |
|---|---|---|---|
| **Audio frame delivery** | ≤ 20ms | 10–15ms | 5–10ms |
| **Viseme lookup (binary search)** | ≤ 2ms | 0.1–0.5ms | 1.5–1.9ms |
| **Rive state input write** | ≤ 3ms | 0.5–1ms | 2–2.5ms |
| **Rive render (canvas rasterize)** | ≤ 16ms (60fps frame budget) | 8–12ms | 4–8ms |
| **Total p50 budget** | **43ms** (Checkpoint C verified) | **20–30ms** | **13–23ms** |

**Jitter tolerance:** ±20ms. If p95 latency exceeds 63ms, gesture-animation desync becomes perceptible to learners; escalate to APO.

### 3.2 Dual-Track Payload: Audio + Metadata Integration

The TTS response must deliver both audio and viseme metadata in a coordinated payload.

#### 3.2.1 Azure Speech SSR Response Format

```json
{
  "audio_url": "data:audio/mp3;base64,SUQzBAAAAAAAI1...",
  "offset_samples": 0,
  "duration_ms": 3500,
  "visemes": [
    {
      "offset_ms": 0,
      "duration_ms": 52,
      "viseme_index": 0
    },
    {
      "offset_ms": 52,
      "duration_ms": 120,
      "viseme_index": 2
    },
    {
      "offset_ms": 172,
      "duration_ms": 88,
      "viseme_index": 4
    }
  ],
  "language": "kn-IN",
  "speaker_voice": "kn-IN-SapnaNeural"
}
```

#### 3.2.2 Receiver Integration Point

```typescript
// Pseudocode: where viseme data enters the React component tree

async function getTurnWithVisemes(turnId: string): Promise<Turn> {
  const response = await fetch(`/api/v1/me/conversations/{sessionId}/turns/{turnId}`);
  const turn = await response.json();

  // The turn.reply_audio_base64 now includes inline viseme metadata
  const audioTimeline: AudioTimeline = {
    visemeFrames: turn.reply_audio_visemes.map((v: any) => ({
      offsetMs: v.offset_ms,
      visemeIndex: v.viseme_index,
    })),
    totalDurationMs: turn.reply_audio_duration_ms,
    audioElement: document.querySelector('audio#reply-stream') as HTMLAudioElement,
  };

  return { turn, audioTimeline };
}
```

### 3.3 Synchronization Verification & QA Gates

Before any viseme animation checkpoint is approved for acceptance, verify:

- [ ] **Lip-sync test:** Play 30 learner sessions with audio + animation side-by-side; have human raters score perceived sync as "perfect", "acceptable", or "noticeable drift"
- [ ] **Latency measurement:** Instrument the animation timer loop with `performance.mark()` / `performance.measure()` to log actual p50/p95 latencies during load test
- [ ] **Gesture isolation:** Verify that gesture animations (nod, smile, blink) do **not** disrupt ongoing mouth animation; no visual popping or state jumps
- [ ] **Offline mode:** If audio playback is offline (downloaded), verify viseme sync still works without live network; metadata must be bundled with audio
- [ ] **Accessibility:** Confirm that ARIA labels and screen reader announcements remain clear with animated avatar; do not rely solely on gesture to convey meaning

---

## Step 4: Governance & Non-Executing Status

### 4.1 Production Code Isolation

**This blueprint is purely a design specification. No production code, runtime configuration, or driver endpoint changes are authorized until formal APO acceptance.**

✅ **Permitted in this document:** Architecture diagrams, pseudocode, interface specifications, cost matrices, decision gates  
❌ **Not permitted:** Live API keys, production file paths, deployed Rive assets, runtime state machine bindings, database schema changes  

### 4.2 Implementation Gate

If and when this blueprint is accepted, the following work becomes in-scope:

1. **Provider Selection** (§1.2 evaluation workflow)
2. **Rive Asset Creation** (§2; requires Rive animator / designer)
3. **Viseme Receiver Loop Implementation** (§3)
4. **Integration Testing** (§3.3 QA gates)
5. **Rollout & Monitoring** (not specified in this blueprint; deferred to checkpoint specification)

### 4.3 APO Acceptance Criteria

Formal acceptance requires:

- [ ] APO written decision confirming provider selection (Azure or ElevenLabs)
- [ ] APO sign-off on the state machine interface (§2.2) and gesture queueing logic (§2.3)
- [ ] APO authorization to commit Rive asset files and viseme receiver loop code
- [ ] Legal review confirming TTS provider MSA complies with privacy rules (ADR-0008, D-0215)
- [ ] Sequencing approval: this checkpoint is explicitly scheduled after DR-0038 A/B/C are verified and frozen

### 4.4 Change Management (Post-Acceptance)

Any future modifications to the viseme interface, Rive asset, or animation latency budget require a new decision request and explicit APO re-approval before implementation.

---

## Constraints & Assumptions

### 5.1 Technical Assumptions

- Rive runtime (`@rive-app/react-canvas`) remains the chosen animation framework
- Web Audio API and `HTMLAudioElement` remain stable and available on all target platforms (web, Android WebView)
- TTS provider viseme data arrives in coordinated delivery with audio stream (not in separate polling loop)
- Learner audio (uploaded during Speak hold-to-talk) is **never** passed to the TTS provider for viseme annotation; viseme data comes only from outbound model-generated audio replies

### 5.2 Out of Scope (Deferred or Excluded)

- ❌ Pronunciation scoring, color-coded mouth-shape feedback, or visual correction cues (separately proposed in DR-0039)
- ❌ Speech-speed control or playback rate adjustment (separately proposed in DR-0039)
- ❌ iOS, Flutter, or multi-platform avatar implementations
- ❌ Character customization, avatar selection, or user-personalization features
- ❌ Offline model training or local TTS (provider-based TTS only)

### 5.3 Fallback Behavior

If viseme data is unavailable or corrupted:

- Mouth animation stops (avatar mouth returns to neutral)
- Gestures (nod, smile, blink) continue to fire on schedule
- Audio plays uninterrupted
- No error is surfaced to learner; graceful degradation

---

## Security, Privacy & Compliance Impact

- **Privacy:** Learner audio recordings (uploaded during Speak) are never sent to TTS providers for viseme processing. Viseme data originates only from outbound model-generated audio.
- **Data residency:** TTS provider selection must comply with existing data localization rules (ADR-0008, D-0215).
- **Billing & Budget:** Monthly TTS costs (guidance TTS + viseme animation TTS combined) must remain ≤ ₹500/month per learner cohort (APP-0064).
- **Governance:** Any provider change or cost increase requires a new ADR / decision request; no silent provider swaps.

---

## Decision Requested

**This blueprint proposes but does not request immediate acceptance.**

When viseme animation becomes a scheduled checkpoint (likely Phase 2 or later, post-DR-0038 closure):

The Accountable Product Owner will decide whether to:

1. **Accept DR-0041** and authorize implementation of this viseme animation architecture; or
2. **Modify** this blueprint (e.g., propose alternative animation framework, adjust latency budget, defer gesture animations); or
3. **Defer or reject** viseme animation indefinitely.

---

## Follow-Up

If accepted, a detailed implementation checkpoint specification must be drafted that covers:

- Rive asset design and animation specifications
- Provider API integration code (Azure or ElevenLabs adapter)
- Frontend component implementation and unit tests
- Latency monitoring and alerting dashboards
- Rollout procedure and A/B testing framework (optional)
- Success metrics and learner feedback collection

---

**Document Status:** Proposal. Not executable. No production code, no runtime configuration, no provider binding authorized.

**Date Issued:** 30 August 2026  
**Last Reviewed:** 30 August 2026  
**Next Review:** On APO decision or 90 days, whichever is sooner.
