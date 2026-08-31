# UX Simplification Audit — first-time mobile learner walkthrough

Date: 31 August 2026. Checkpoint series: **D-0260** (UX simplification).
Trigger: client feedback that the mobile learner app is overwhelming, has too
much information, a lot of clutter, and confusing flow and navigation.

Method: a first-time-user walkthrough of every learner-facing screen in
`apps/web` at phone widths (320–412 CSS pixels), from first open through the
first speaking session. Authority files read first:
`AGENTS.md`, master-plan §7.1, `docs/STATUS.md`,
[ADR-0026](adr/0026-five-destination-learner-navigation.md),
[ADR-0028](adr/0028-meaningful-speaking-streak-and-delayed-review.md).

Every visible element is classified:

- **keep** — stays as the primary content of the screen;
- **demote** — stays on the screen but collapsed, smaller or secondary;
- **defer** — moves behind an explicit user action (disclosure, sheet, menu,
  later screen);
- **remove** — leaves this screen (functionality relocates; nothing governed is
  deleted).

Deterministic behaviour (marks, mastery, unlock order, streak, delayed-review
scheduling, consent semantics, tenancy) is out of scope: only presentation,
information architecture and copy. Operator console and the local demo
Phase-1 shell are out of scope.

This document is the inventory. The accepted decision is
[ADR-0032](adr/0032-four-destination-navigation-and-calm-ion-theme.md).

---

## First-time path (as shipped)

```
Guest: MarketingLanding (every hash except #sign-in)
  → Sign in (web: #sign-in password form; Android: system-browser PKCE)
    → Authenticated shell: sticky header + five-tab nav + locale
      → #home: TutorHomeHero + onboarding card (nav already live)
        → after POST /me/onboarding: hero + streak + mission + offline + videos
          → Continue: mission Learn → Speak → Check → Result
        or #learn: numbered levels → four session tabs + stacked panels
          → Ask AI Tutor embeds the full Speak conversation UI on the same page
        or #speak: coach picker + Everyday/Situation + ~12 interactive regions
        or #progress: stats / empty CTA
        or #tuition: waitlist form labelled "1:1"
```

The core confusion is **three overlapping learning products** that all involve
listening and speaking, with different stage names:

| Product | File | User-visible stages |
|---|---|---|
| Home mission | `LearnerJourney.tsx` | Learn → Speak → Check |
| Learn level | `LevelPathPanel.tsx` | Introduction → Practice → Conversation → Interaction |
| Speak room | `SpeakRoom.tsx` / `ConversationJourney.tsx` | Everyday warmup/listen/talk/reflect, or Situation observe/rehearse/perform/reflect |

Learn's Interaction button jumps to Speak. Learn's "Ask AI Tutor" embeds
`ConversationJourney` inside the level page. A first-time user cannot form a
stable mental model of "where learning happens."

---

## 0. Authenticated chrome (every learner destination)

Files: `apps/web/src/App.tsx`,
`packages/design-system/src/layout/ApplicationFrame.tsx`,
`packages/design-system/src/navigation/PrimaryNavigation.tsx`,
`apps/web/src/tutor/tutorExperience.css`.

Present as soon as a learner is signed in, including during onboarding.

| Element | Verdict | Notes |
|---|---|---|
| Skip link → `#main-content` | keep | Accessibility contract (D-0032). |
| Brand logo | keep | Header. |
| "Signed in." + membership-type badge + organization name | demote | Moves into Me. Header keeps logo + one account affordance to `#me`. No username is available (session is ULID-only; D-0254). |
| Header **Account** toggle (`PasswordAccountSecurityPanel`) | defer | Account security becomes a Me section. |
| Header **Sign out** | defer | Moves into Me. |
| Header locale `<select>` (kn-IN / en-IN) | defer | Moves into Me. |
| Five-tab nav: Home · Learn · Speak · Progress · 1:1 | remove (as five) | Four destinations: **Home · Learn · Speak · Me**. ADR-0032 amends ADR-0026. `#progress` / `#tuition` / `#one-to-one` deep-link into Me. `#practice` still aliases Speak. |
| "1:1" label | remove | Cryptic for a waitlist form. Becomes a labelled section inside Me. |
| Emoji `::before` icons (🏠 🎧 🗣️ ✨ 👤) where still present | remove | Inline SVG icons, `aria-hidden`, visible text labels stay. |
| Sticky (not fixed) bottom-styled nav on ≤48rem | demote | Fixed bottom tab bar; `env(safe-area-inset-bottom)`; main content padded so the bar cannot cover the primary button. 48px targets. |

Hash parser today (`destinationFromHash` in `App.tsx`): `#home`, `#learn`,
`#speak`/`#practice`, `#progress`, `#tuition`/`#one-to-one`. Unknown hashes
fall through to Home.

---

## 1. Marketing landing (guest, every hash except `#sign-in`)

File: `apps/web/src/marketing/MarketingLanding.tsx`.
Guests never see primary nav (D-0251).

| Element | Verdict | Notes |
|---|---|---|
| Hero slideshow (5 banners) + "AI-guided English practice" badge + title + lede | keep | The single guest hero. |
| CTA: Sign in to start | keep | Web → `#sign-in`. Android → `beginAuthorization()` (D-0258). |
| CTA: See the four sessions (`#how-it-works`) | demote | Secondary text link, not a second equal-weight button. |
| CTA: Download the Android app | keep | Required by D-0257/D-0259; hidden inside the native Android app. |
| How it works — 4 numbered session cards (job / AI / output) | keep | Core pedagogy explanation. |
| 5 pedagogy cards | demote | First-time readers stop after two card rows. Condense. |
| 3 why-image cards | demote | Condense with the pedagogy band. |
| 3 benefit / difference cards | demote | Condense. |
| 3 showcase screenshots | keep | Concrete product preview. |
| Footer / brand | keep | |

Finding: the landing is a long brochure. That is acceptable as marketing. It
must not be the density model for authenticated screens.

---

## 2. Sign-in (`#sign-in` and Android PKCE)

Files: `apps/web/src/auth/WebPasswordSignInPanel.tsx`, `apps/web/src/App.tsx`
header, `apps/web/src/marketing/MarketingLanding.tsx`.

### Web (Sanctum cookie)

| Element | Verdict | Notes |
|---|---|---|
| Username, password, show-password, Sign in | keep | ADR-0021/0022. |
| Password recovery link | keep | |
| Invitation-code enrollment block | demote | Behind a disclosure ("Have an invitation code?"). |
| Tutor avatar + marketing badge above the form | demote | Compact strip; the form is the point of the page. |

Standalone HTML `password-establishment.html` / `password-recovery.html`
are out of this series.

### Android (Passport PKCE)

| Element | Verdict | Notes |
|---|---|---|
| Header / hero **Sign in** calling `beginAuthorization()` | keep | D-0258. No WebView password form (ADR-0002 / ADR-0012). |
| Dedicated `#sign-in` PKCE action | keep | |

---

## 3. First-run onboarding (`#home`, `home.onboarding.completed === false`)

File: `apps/web/src/learner/LearnerJourney.tsx` (`OnboardingForm`).
API: existing `POST /me/onboarding` (`adult_confirmed` + `goal_key`). **Do
not change the API.**

`App.tsx` still mounts `TutorHomeHero` above `LearnerJourney`, so a brand-new
learner sees the full-height coach hero **and** the onboarding card. Primary
nav is already live, so the user can open Speak with no goal and no adult
confirmation recorded.

| Element | Verdict | Notes |
|---|---|---|
| Title "Set up your learning goal" + intro | keep | One short welcome line in the gated flow. |
| 18+ confirmation checkbox | keep | Master plan §7.3 / audience rule. |
| Goal radios (everyday / workplace / travel) | keep | |
| **Save and continue** | keep | Same POST body. |
| Full `TutorHomeHero` (large avatar, greeting, Learn CTA) during onboarding | remove | The form must be the only content. |
| Live 4/5-tab primary nav during onboarding | remove | Gate nav until onboarding completes (reuse guest `hidePrimaryNavigation`). |
| Coach choice (Swathi / Arjun, no rename) | add | First coach selection belongs here so Speak does not open on a picker. Rename stays later in Speak (ADR-0031, device-local). |

There is no placement test, product tutorial, or recording-consent step in
this form. Voice consent stays on first Speak hold-to-talk (ADR-0007) — do
not fold it into onboarding in this series.

Finding: onboarding must become a short, **gated**, one-decision-per-screen
flow (welcome → adult+goal → coach), then land on Home with nav visible.

---

## 4. Home (`#home`, onboarding complete)

Files: `apps/web/src/App.tsx` (`learner-stack`),
`apps/web/src/tutor/TutorPresence.tsx` (`TutorHomeHero`),
`apps/web/src/learner/LearnerJourney.tsx`,
`apps/web/src/learner/LevelVideosPanel.tsx`.

Home is **not** a stats wall. It is still too many competing surfaces.

| Element | Verdict | Notes |
|---|---|---|
| `TutorHomeHero`: coach name eyebrow, greeting, "after you listen…" body, **Open Learn** CTA, hero-size `InteractiveAvatar` | demote | Compact greeting strip (small avatar, one line). **Remove the `#learn` CTA** — it competes with mission Continue (two primary actions). Large avatar stays on Speak. |
| "Today's learning" heading | keep | |
| Streak pill (0 / 1 / n meaningful speaking days) | keep | Advisory chrome only (ADR-0028). Neutral on a broken streak. No calendar, no percentage. |
| Today's mission / Review due card: eyebrow, governed title, bilingual guidance, estimated minutes, **Continue** | keep | The one dominant action. Due review outranks today's mission (ADR-0028 / §7.2). |
| Empty-enrollment copy | keep | |
| Decorative orbit rings on the mission card | demote | Visual noise around the primary button. |
| Offline access card (personal-device warning, enrollment checkbox, live status, **Save this mission for offline use**) | defer | Collapse to a "Save for offline" row under the mission card. Expanding must still show the personal-device checkbox and copy **before** enrollment (ADR-0006 / ADR-0007). |
| `LevelVideosPanel` (title, level buttons, `<video>`) | defer | Collapsed "Level videos" disclosure at the bottom, closed by default. Hidden when the catalog is empty (already). |

Not on Home today (and must not be added): XP, charts, leaderboard, coach
picker, 1:1 form, level list.

### 4a. Home mission in-flow (same hash, replaces the stack)

`MissionExperience` in `LearnerJourney.tsx`. Functionally keep the three
steps; reduce chrome so each step fits **one instruction, one media control,
one primary button** above the fold at 360px.

| Element | Verdict | Notes |
|---|---|---|
| Mission eyebrow + governed title | keep | |
| **Learn:** Kannada guidance, model sentence, audio player, transcript, Continue to speaking | keep | Transcript may sit in a disclosure if it pushes the button below the fold. |
| **Speak:** instruction, tap-mode `RecordingTurn` (Start/Pause/Resume/Stop/Cancel, waveform, playback, Record again, Continue without recording), Continue to the check | keep | One primary path. "Continue without recording" stays (deterministic path when mic is blocked). |
| **Check:** one radio knowledge check, Submit | keep | |
| **Result:** can-do line; check correct/incorrect; knowledge mastered/developing; spoken **Unassessed**; local capture; evidence (first/latest/best); next review datetime; optional per-objective progress; Try again | demote | Keep can-do + next action above the fold; evidence/progress behind a disclosure. Spoken remains unassessed (AI never writes marks). |

---

## 5. Learn (`#learn`)

File: `apps/web/src/learner/LevelPathPanel.tsx` (`LevelSessionJourney` in the
same file). Hierarchy is **level number + concept → four sequential sessions**.
There is no course → theme → unit tree.

Unlock order (`PART_PREREQUISITE`) is deterministic (ADR-0025) and must not
change.

### Level list

| Element | Verdict | Notes |
|---|---|---|
| Eyebrow "Curriculum path", title, lead | demote | One short lead. |
| Ready-to-start cards: index, "Level n", concept (or "Ready"), Open | keep | Add an "n of 4 sessions done" hint from existing completion state. No thumbnails, XP or list-level progress bars. |
| Empty catalog copy | keep | |
| "Coming next" `<details>` (count in the summary, then a numbered pending list) | demote | Summary-only by default is already correct; do not dump every pending level number unless expanded. |

### Inside a level (densest Learn state)

Header + **four tabs** + **every panel stacked** + teacher stage. Tapping Ask
AI Tutor / Open room mounts the entire `ConversationJourney` under the same
page (D-0254 already removed the duplicate static avatar once talk starts;
the remaining density is the embedded Speak UI itself).

| Element | Verdict | Notes |
|---|---|---|
| Back "All levels", "Level n", session objective, session lead | keep | |
| Four session **tabs** (Introduction / Practice / Conversation / Interaction) with active/done/locked | remove (as tabs) | Vertical stepper/checklist. Each part opens alone with a back control. Unlock order unchanged. |
| Part intro (name, hint, Kannada summary; or English patterns + Kannada `<details>`) | keep | Kannada summary first for `kn-IN`. |
| Listen panel (Play/Pause/Resume/Replay) | keep | Auto-start tutor talk when intro audio ends may stay, but the embedded Speak UI must not appear on this page. |
| Do-this activity bullets | keep | Below the primary action. |
| Optional quick check (`<details>`, radios, Submit, score %) | keep | Stay behind the existing disclosure. |
| Static `TutorPresence` while not talking | demote | Not needed on every part once talk lives on Speak. |
| **Ask AI Tutor** / **Open free-speech room** embedding `ConversationJourney` | remove | Navigate to `#speak` with session context (extend the existing `onOpenRoleplay` pattern). Completing talk returns to the stepper. Completion rules stay server-authoritative. |
| **Try a live role-play** (Interaction) | keep | Already navigates to Speak situation mode. |
| **Mark session complete** | keep | |

---

## 6. Speak (`#speak`)

Files: `apps/web/src/conversation/SpeakRoom.tsx`,
`ConversationJourney.tsx`, `VoicePracticePanel.tsx`, `AiMemoryPanel.tsx`,
`apps/web/src/coach/CoachSelector.tsx`, `RoleplayJourney.tsx`,
`roleplayCatalog.ts`.

This is the densest learner screen: roughly **twelve interactive regions** on
one phone view.

### Speak room chrome

| Element | Verdict | Notes |
|---|---|---|
| `CoachSelector`: eyebrow, title, two radio cards with avatars (Swathi / Arjun), rename field (max 32), Use default name | defer | Compact header (avatar + name + "Change"). Full picker including rename opens in a sheet/modal. Names stay device-local and are never sent to providers (ADR-0031). |
| Everyday talk / A situation tablist | keep | |

### Everyday talk — idle (`ConversationJourney` `kind: idle`)

| Element | Verdict | Notes |
|---|---|---|
| `SwathiAvatar` / viseme stage | keep | The visual centre once talk starts; idle can be compact. |
| Eyebrow + "Speak with {coach}" title | keep | |
| Full AI disclosure (AI-not-human, advisory, no marks) expanded | demote | One-line summary + "More" `<details>` everywhere (the `embedded` variant already does this). Consent semantics unchanged. |
| Practice-type `<select>` (Confidence chat / Skill focus / Review) | defer | Collapsed "Practice settings"; default Confidence chat. |
| Coaching-mode `<select>` (Gentle / Tutor / Fluency) | defer | Same disclosure; default Gentle. |
| **Talk with {coach}** / Start Skill Focus / Start Review | keep | The one idle primary button. |

### Everyday talk — in session

| Element | Verdict | Notes |
|---|---|---|
| Stage line (warmup / listen / talk / reflect) | demote | One §7.4 status line is enough; do not also show a parallel "Stage:" label if it duplicates loop phase. |
| Persona / "I am {coach}…" | demote | One line or fold into disclosure. |
| "Hear an example" `<details>` + Play model English | keep | Already a disclosure. |
| `VoicePracticePanel`: consent (Allow {coach} to hear me / purpose buttons), **Hold to talk** / Release to send, Cancel, 24-bar meter, loop status (Listening / Processing / Preparing / {coach} is speaking), low-confidence "heard" + Use this / Discard / type correction, Manage consent `<details>`, insecure-HTTP block + open-secure-page | keep | ADR-0007. Consent must still complete before the first hold-to-talk. Hold-to-talk + §7.4 status are the visual centre. |
| Secondary buttons: Say that again, Type instead, Skip this turn, Hint in my language, Finish practice | demote | Two visible (Type instead, Finish); the rest in a "More" overflow. All remain keyboard-reachable. |
| Type-instead textarea + Send | keep | Shown only after Type instead. |
| You / Teacher transcript bubbles | keep | |
| Priority correction (clearer English + L1 explanation + retry hint) | keep | Content unchanged. |
| Play model English (replay reply) | keep | |
| `AiMemoryPanel` (view/edit/delete, max 5) | defer | Collapsed "What {coach} remembers". Hidden when embedded (already). |
| Full turn-status event list (accepted / transcribing / generating / synthesizing / completed / failed) | demote | Current turn only; history behind a disclosure. |
| Reflect: carry sentence + Start again | keep | |
| Dev-only `SpeakLoopTimingOverlay` | keep (dev) | Not in production builds. |

Do not change the turn pipeline, correction object, coaching modes, or
provider orchestration in this series (`make ai-eval` is not required).

### Situation / Role-play

| Element | Verdict | Notes |
|---|---|---|
| 12 flat scene tiles with emoji + English name (`roleplayCatalog.ts`) | demote | Group into 3–4 labelled categories (Daily life, Work, Travel, Services). Scene names stay scene names (D-0254 / ADR-0031). |
| Observe → Rehearse → Perform → Reflect | keep | Current auto-skip to Perform may stay; do not re-introduce extra mandatory stages. |
| Hold-to-talk / type fallback / example `<details>` | keep | Same Speak declutter rules as Everyday. |
| Reflect: advisory met/unmet objectives | keep | Advisory only. |
| Back to everyday talk | keep | |

Suggested grouping (presentation only):

- Daily life: grocery, restaurant, school parent meeting, phone support
- Work: job interview, office meeting, bank counter
- Travel: hotel check-in, airport check-in, bus/train ticket
- Services: clinic reception, doctor appointment

---

## 7. Progress (`#progress`)

File: `apps/web/src/learner/ProgressDashboard.tsx`.
After ADR-0032 this content lives inside **Me**; the hash still works.

| Element | Verdict | Notes |
|---|---|---|
| Empty state + **Go to today's mission** → `#home` | keep | Copy aligned with the new Home. |
| Stat: meaningful speaking days | keep | Plain-language label (e.g. "Days you practised speaking"). Metric unchanged (ADR-0028). |
| Reviews completed on time | keep | Plain-language label. |
| Reviews completed late | keep | Plain-language label. |
| Reviews missed (only if due/pending > 0) | keep | |
| Speaking minutes (`speaking_minutes` already on the API, unrendered) | add | One extra stat card. No charts. |
| Improvement highlights (first-attempt passes, improved-on-retry, recurring-error lines) | keep | |
| Mastery-by-objective grouped list (`{key} - Mastered | Practice needed`) | demote | Collapse concept groups; expand groups that need practice. No scores or spoken state on the row (already). |

Do not render Learn's level path here (ADR-0028 item 5 already forbids it).

---

## 8. 1:1 (`#tuition`)

File: `apps/web/src/learner/TuitionRequestPanel.tsx`.
After ADR-0032 this content lives inside **Me**; `#tuition` / `#one-to-one`
still work. This is not booking, payment, or an in-app call (ADR-0026 item 5,
restated by ADR-0032).

| Element | Verdict | Notes |
|---|---|---|
| Title + lead sentence | keep | |
| Institute contact links (phone / WhatsApp / email when configured) | keep | Default visible state. Empty values stay hidden. |
| Waitlist form (preferred window, topic, notes, Send request) | defer | Expands on demand. |
| Success / error alerts | keep | |
| Request list (topic · window · Submitted / Seen by staff) | keep | Below the expanded form. |

---

## 9. Target Me destination (`#me`)

New hub. Not a current screen. Composition order (ADR-0032):

1. Account summary (membership type, organization)
2. Progress (`ProgressDashboard`, section id for `#progress`)
3. 1:1 waitlist + institute contacts (section id for `#tuition`)
4. Account security (`PasswordAccountSecurityPanel`, inline expand)
5. Interface locale
6. Sign out

---

## 10. Terminology

Users currently meet: mission, level, session, part, stage, turn, practice,
talk, 1:1, meaningful speaking day, delayed review.

Unify user-visible vocabulary in D-0260.9:

- **mission** — Home daily flow only;
- **level** / **session** — Learn;
- **practice** — Speak.

All governed strings change in `packages/localization` catalogues, not inline
JSX. New kn-IN strings stay `pending-independent-review` (D-0030 / D-0210).

---

## 11. Theme (calmed ion)

Files: `packages/design-system/src/styles/{tokens,base,components}.css`.
D-0250 (ion studio) stands: void indigo canvas `#06050f`, electric cyan
actions `#2ee6ff`, magenta accent `#d61d6e`. Dark-only. No light theme in
this series.

Computed contrast of the **current** muted token (normal text, WCAG 2.2):

| Pair | Ratio | AA 4.5:1 |
|---|---|---|
| `--ge-color-text-muted` `#b9b1d4` on `--ge-color-surface` `#13111f` | **9.14:1** | pass (also AAA) |
| same on `--ge-color-canvas` `#06050f` | 9.94:1 | pass |
| `--ge-color-action-text` `#041218` on `--ge-color-action` `#2ee6ff` | 12.56:1 | pass |

The readability complaint is competing **visual noise** (grid overlay,
layered radial glows, neon panel halos), not a failing muted hex. D-0260.2
must not darken muted text. After glow/grid removal, re-verify ≥4.5:1.

| Element | Verdict | Notes |
|---|---|---|
| Ion identity (canvas, cyan action, magenta accent) | keep | |
| Background grid overlay (D-0250 body) | remove | |
| Layered radial cyan/magenta/violet body glows | demote | At most one subtle canvas gradient. |
| Neon glow/shadow panel halos | demote | Flat elevated surfaces. |
| `--ge-color-text-muted` `#b9b1d4` | keep | Already 9.14:1 on surface. Preserve the AA floor. |
| Type scale / display font | keep | `--ge-font-family-display` may use Fraunces if it actually renders; otherwise drop the unused import. |
| Dark-only `color-scheme` | keep | A light theme would be a separate ADR. |

---

## 12. Sequencing (one sub-checkpoint at a time)

1. **D-0260.1** — this audit + ADR-0032 + master-plan §7.1 + STATUS / DECISIONS
   / QUALITY_REVIEW (docs only). **Complete.**
2. **D-0260.2** — theme calm-down (tokens / base / components CSS). **Complete.**
3. **D-0260.3** — four-destination nav + Me hub + header simplification. **Complete.**
4. **D-0260.4** — gated three-step onboarding. **Complete.**
5. **D-0260.5** — Home single-action simplification. **Complete.**
6. **D-0260.6** — Learn stepper; no embedded Speak UI. **Complete.**
7. **D-0260.7** — Speak progressive disclosure + grouped role-play scenes. **Complete.**
8. **D-0260.8** — Progress / 1:1 plain language inside Me. **Complete.**
9. **D-0260.9** — terminology unification, accessibility sweep, closeout. **Complete.**
   Mobile-width captures: `docs/ux-simplification/home-360.png`,
   `learn-stepper-360.png`, `speak-idle-360.png`, `speak-session-360.png`,
   `me-360.png`.

Do not start a later sub-checkpoint until the previous one has `make verify`
(and `make e2e` when a journey changed). This series is closed.
