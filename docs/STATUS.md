# Repository status

## Current checkpoint

| Field | Value |
|---|---|
| Checkpoint | D-0262: Android Sign in must open system-browser PKCE |
| Status | **D-0262 complete.** Android first-screen Sign in buttons did nothing: the hero no-op’d while session restore was still checking, failed PKCE had no visible error, and the homepage APK could ship with an empty native `BuildConfig.MOBILE_API_ORIGIN` so Custom Tabs rejected every authorize URL. Hero/header now start PKCE immediately; packaging fails closed without a baked origin; testers must install a newly published APK. |
| Canonical baseline | `ENTERPRISE_PRODUCT_MASTER_PLAN.md` version 3.11 |
| Updated date | 31 August 2026 |
| Git branch | `codex/confidence-conversation` |
| Product code | Four-destination learner IA (ADR-0032). SVG tutors (D-0249 restored by D-0261); GLB/WebGL viseme stage remains config-gated. Indic STT (ADR-0027 / ADR-0030); streak + delayed review (ADR-0028); Progress inside Me; calmed ion theme (ADR-0032). |
| Next required action | Testers uninstall the previous APK and install the D-0262 build (`make soft-launch-android-apk`). Independent human review of preview-locale catalogue strings remains outstanding. This does not authorize production/Play. |
| Production/public release | Not authorized |
| Soft launch | Translation-preview web build. Live Azure OpenAI and guidance TTS; Sarvam Indic learner STT active for the Direct organization; SVG `InteractiveAvatar` coaches on all clients (GLB stage config-gated off). Public entry remains static-IP HTTP on port 8080; microphone recording uses optional HTTPS on port 8443. Android is a debug-signed Capacitor APK sideload, not Play. |

## D-0262: Android Sign in must open system-browser PKCE — 31 August 2026

- Accountable Product Owner report: tapping the several Sign in controls on the Android first screen still did nothing.
- Android does not use the web password form. Those buttons must start Passport Authorization Code with S256 PKCE in Chrome Custom Tabs (D-0180 / D-0258). Three independent defects kept that from happening:
  1. The marketing **Sign in to start** handler returned immediately while `session.status === 'checking'`, so early taps were discarded. The header Sign in control was also hidden until restore finished.
  2. `scripts/publish-soft-launch-android-apk.sh` baked the origin into the Vite bundle, then ran Gradle in a second shell without `VITE_MOBILE_API_ORIGIN`. `BuildConfig.MOBILE_API_ORIGIN` was empty, so `GlobalEnglishSystemBrowserPlugin.open` rejected every authorize URL. The JS catch set the session to unavailable with no visible marketing error.
  3. Android 11+ package visibility did not declare Custom Tabs / VIEW queries, so a device without a visible browser provider could fail the same way.
- Change: hero and header start PKCE immediately; `#sign-in` shows the PKCE action during restore; a failed start shows `session.unavailable` on the hero. Gradle `assemble*`/`bundle*` fail closed unless the origin is on the D-0258 allowlist. The publish script exports the origin into Gradle. Manifest declares Custom Tabs queries.
- Testers must install a newly published APK. The previous homepage binary can still contain an empty native origin.
- Published this session: `make soft-launch-android-apk` produced `global-spoken-english.apk` (59,155,938 bytes) for `http://117.254.108.143:8080`. Native `BuildConfig.MOBILE_API_ORIGIN` and the packaged JS both contain that origin. Live `GET /downloads/global-spoken-english.apk` is 200 `application/octet-stream`.
- No API, schema, consent or catalogue-approval change.

## D-0261: SVG tutor gate, compact preview notice, PHP 8.4 pcntl image — 31 August 2026

- Accountable Product Owner: the current GLB busts still fail the quality bar.
- Restored D-0249: `apps/web/src/config/avatarConfig.json` `"tutorRenderer": "svg"`. GLB code path unchanged.
- Preview notice (D-0030) is a collapsed disclosure; full text remains reachable. kn-IN and other preview locales are **not** marked approved.
- Compact Home greeting CSS now actually wins over the 14rem art rule; mission Continue is in the first 360×780 viewport above the fixed nav.
- PHP 8.4 test image: `scripts/php84-test.Dockerfile` / `make php84-test-image` (`pcntl`, `pdo_pgsql`, Composer 2.10.2). `make verify PHP=$PWD/scripts/run-php84.sh COMPOSER_CMD=$PWD/scripts/run-composer.sh` satisfies the toolchain pin. Postgres PHPUnit **325/325**.
- Public shell cache `v17`. No API change. `make ai-eval` not required.

## D-0260: UX simplification series — 31 August 2026

- Client feedback (via the Accountable Product Owner): the mobile learner app is overwhelming — too much information, a lot of clutter, and confusing flow and navigation.
- **D-0260.1 (docs only, complete).** First-time-user audit in `docs/UX_SIMPLIFICATION_AUDIT.md`. ADR-0032 accepted, **amending ADR-0026**: four destinations **Home · Learn · Speak · Me**. Master plan §7.1 is version 3.11.
- **D-0260.2–.9 (product code, complete).** Calmed ion tokens (no grid overlay, one canvas gradient, muted `#b9b1d4` on `#13111f` remains **9.14:1**). Four-tab fixed bottom nav with inline SVG icons; Me hub absorbs Progress, 1:1, account security, locale and sign out; `#progress`/`#tuition`/`#one-to-one` deep-link into Me. Gated three-step onboarding (welcome / 18+ + goal / coach, same `POST /me/onboarding`). Home: compact greeting, mission/review card as the only primary action (ADR-0028 unchanged), offline and level videos collapsed. Learn: vertical session stepper, one part per screen, Ask AI Tutor navigates to `#speak`. Speak: compact coach header, collapsed practice settings, hold-to-talk centre, grouped role-play scenes. Progress/1:1 plain language inside Me; speaking minutes rendered. User-visible vocabulary: **mission** (Home), **level/session** (Learn), **practice** (Speak).
- OpenAPI and generated client types were **not** regenerated for this series: no API contract changed.
- `make ai-eval` was not run and is not required: no prompt, model, provider or orchestration change.
- Mobile-width captures: `docs/ux-simplification/home-360.png`, `learn-stepper-360.png`, `speak-idle-360.png`, `speak-session-360.png`, `me-360.png`.
- Determinism, consent (ADR-0007), personal-device enrollment (ADR-0006/0007), session unlock order (ADR-0025), streak advisory-only (ADR-0028), Sanctum/PKCE and tenancy are unchanged. kn-IN remains `pending-independent-review`.

## D-0259: Mobile APK download must complete and resume — 31 August 2026

- Accountable Product Owner report: the APK download on a phone paused by itself and could not be resumed.
- Cause: the file is ~43 MB. Chrome on Android treats HTTP APKs as insecure/dangerous, and the homepage used an HTML `download` attribute (in-page blob fetch with no resume). Caddy also sent `Cache-Control: no-store` and `Content-Disposition: attachment` without a filename, so the system download manager could not keep a partial file.
- Change: `/downloads/*` is excluded from gzip, sent as `Content-Type: application/octet-stream` with `X-Content-Type-Options: nosniff` and `filename="global-spoken-english.apk"`, and a short public cache so Range resume works. The homepage link is a normal GET (no `download` attribute). Service worker `v15`.
- Testers cancel the paused job and start a new download after a hard refresh. Same-Wi-Fi testers must use `192.168.1.4`, not the WAN IP.
- Verified: MarketingLanding 2/2; `scripts/test-soft-launch-environment.sh` passed; Caddyfile adapt OK; after `make soft-launch-restart`, live `GET /downloads/global-spoken-english.apk` is 200 `application/octet-stream` `filename="global-spoken-english.apk"` `public, max-age=300` `Accept-Ranges: bytes` `Content-Length: 44702838`; `Range: bytes=20000000-` returns 206.

## D-0258: Android first-screen sign-in opens system-browser PKCE — 31 August 2026

- Accountable Product Owner report: in the Android app, tapping Sign in did nothing; the first page showed several sign-in buttons and message links, all non-responsive.
- Cause (two independent defects on the same first screen):
  1. The native `GlobalEnglishSystemBrowserPlugin` required `https` for `/oauth/authorize`. D-0257 baked the APK to `http://117.254.108.143:8080`, so Custom Tabs rejected every authorize URL and the header catch set the session to unavailable without opening a browser.
  2. Marketing hero CTAs were web hash links (`#sign-in`, `#how-it-works`) plus an APK download. Android does not use Sanctum password forms; `#sign-in` therefore showed no credential UI. Hash links also do not start PKCE.
- Change: the plugin allowlist now matches `androidPkceEnvironment.ts` (HTTPS, emulator/loopback HTTP, invite-only IPv4 HTTP `:8080`). The Android marketing hero **Sign in to start** button calls `beginAuthorization()`. In-app APK download is hidden. **How it works** scrolls in-page. The dedicated `#sign-in` page also exposes the PKCE action.
- Testers must install a newly published APK; the previous binary still contains the HTTPS-only plugin.
- Verified: targeted web unit tests 29/29; Gradle `testDebugUnitTest assembleDebug` succeeded; `make soft-launch-android-apk` published `global-spoken-english.apk` for `http://117.254.108.143:8080`.

## D-0257: Keep HTTP :8080, add :8443, homepage Android APK download — 31 August 2026


- Accountable Product Owner questions: whether to close 8080 and open 8443; whether Android can be shared for external testing via `http://192.168.1.4:8080`; homepage should offer an APK until Play Store.
- **Ports:** keep TCP **8080** (public HTTP, cookies, APK, Android API). Open TCP **8443** as well (browser microphone). Closing 8080 would break D-0248 sign-in and the APK origin.
- **LAN vs WAN:** `192.168.1.4` is this host on Wi-Fi. External testers must use `http://117.254.108.143:8080`. Same-Wi-Fi testers must not use the public IP (hairpin NAT).
- **Android:** production-path `com.globalenglish.app` debug APK, API origin `http://117.254.108.143:8080`. Capacitor WebView is `https://localhost`, so the microphone is a secure context even when the API is HTTP. Cleartext is allowlisted only for that IPv4:8080 pair plus loopback. Play AAB remains HTTPS-only.
- Homepage CTA downloads `/downloads/global-spoken-english.apk`. Change `ANDROID_APP_DOWNLOAD_HREF` when Play replaces sideload. Service worker `v14` does not cache `/downloads/`.
- Cache: public service worker `global-english-public-shell-v14`. Localization catalogues revision `.5`.
- Verified: localization validate + 31/31; soft-launch env contract; targeted web tests 9/9; Playwright bootstrap+learner-journey 11/11; `make soft-launch-android-apk` produced a 15 MB debug APK aimed at `http://117.254.108.143:8080`; live `GET /downloads/global-spoken-english.apk` returns 200 `application/vnd.android.package-archive`.

## D-0256: LAN/WAN HTTPS :8443 for browser microphone capture — 31 August 2026

- Accountable Product Owner report: "Microphone recording is unavailable" on `http://192.168.1.4:8080/#learn`.
- Cause: Chromium/Safari/Firefox do not expose `navigator.mediaDevices` on plain HTTP except loopback. `BrowserAudioRecorder.isSupported()` is correctly false; JavaScript cannot override that browser rule.
- Change: keep D-0248 HTTP `:8080` as the public entry (non-Secure cookies, no redirect to HTTPS). Add a second Caddy site on `:8443` with `tls internal` serving the same SPA and API. `persist_public_origin` adds `{public_ip}:8443` and `{lan_ip}:8443` to `SANCTUM_STATEFUL_DOMAINS` while `SESSION_SECURE_COOKIE` stays `false`. On an insecure non-loopback page, Learn/Speak recording shows `learner.recording.blocked_insecure` and a link to `https://{host}:8443{path}{hash}`.
- Cache: public service worker `global-english-public-shell-v13`. Localization catalogues revision `.4` for the two new recording keys.
- The `:8443` certificate is Caddy's local CA, not a public certificate. Testers accept the browser warning once. HTTPS is a different origin from HTTP, so the session cookie does not carry over — sign in again on `:8443`.
- WAN testers also need the inner router to forward TCP `8443` to this host, the same way `8080` is already forwarded.
- Verified: localization validate (0 errors, 5 expected pending-review warnings) and 31/31 tests; `scripts/test-soft-launch-environment.sh` passed; web recording unit tests 36/36; Playwright `bootstrap`+`learner-journey` 11/11; live Chromium probe `http://192.168.1.4:8080/#learn` has `isSecureContext=false` / no `getUserMedia`, `https://192.168.1.4:8443/#learn` has `isSecureContext=true` / `getUserMedia` present; `make soft-launch-ready` 34 pass / 1 pre-existing WAN-forward warn / 0 fail.

## D-0255: Authenticated workspace fills the application main column — 31 August 2026

- Accountable Product Owner report: after sign-in, page contents are "not aligned to the width of the page on the screen."
- Cause: D-0254 removed page-level caps in `LevelSessionJourney.css` / `ConversationJourney.css`, but `tutorExperience.css` (imported later) still enforced stale pre-ion caps: `.level-path, .level-session { max-width: 44rem; }` (~704px) and `.conversation-journey { max-width: 40rem; }`. On a 1440px viewport the shell column is ~1216px wide, so Learn content sat in a narrow left column with a large empty margin to the right — misaligned with the header and nav above.
- Change: removed those two max-width overrides from `tutorExperience.css`; added `.shell-content:not(.shell-content--gate):not(.shell-content--landing)` workspace rules in `App.css` so authenticated destination roots (`.level-path`, `.speak-room`, `.progress-dashboard`, `.learner-stack`, `.operator-console`, etc.) explicitly fill the centred main column (`width: 100%; max-width: none`). Paragraph-level `max-width` inside panels is unchanged for readability.
- Cache: public service worker `global-english-public-shell-v12`.
- Verified: rebuilt `dist`; Playwright layout measurement shows `#learn` `.level-path` width 1216px matching `.shell-content` (was 704px); `#speak` `.speak-room` unchanged at 1216px; `pnpm exec playwright test e2e/bootstrap.spec.ts e2e/learner-journey.spec.ts` 11/11 pass.

## D-0254: Post-theme bug sweep — duplicate avatar, sign-in grid leak, tab contrast, page width, account role — 31 August 2026

- Accountable Product Owner report (five items in one message): (1) the sign-in tile had "items spread all over ... and not aligned"; (2) "tiles and items placed on all pages are narrowly placed and not wide and placed across the page"; (3) on `#learn`, clicking "Ask AI Tutor" launches "a duplicate avatar that speaks"; (4) on `#speak`, "Everyday talk text on the button is not visible as the button color is the same"; (5) "the account button on the top does not show the account type, profile etc."
- **Duplicate avatar (`LevelPathPanel.tsx`).** The level-session "teacher stage" always rendered a static `TutorPresence` avatar. Clicking Ask AI Tutor/Open room set `boundSession`, which mounted `ConversationJourney` (embedded) directly below — and `ConversationJourney` renders its own `SwathiAvatar` with real audio/viseme sync. Two avatars were on screen at once, with the top one merely flipping to a cosmetic "speaking" mood while the live one actually spoke. Fix: the static `TutorPresence` now renders only while `!boundSession`; once the live conversation opens, its own avatar is the single avatar shown. `Ask AI Tutor`/`Open room`, Try role-play, and Complete controls are unchanged.
- **Sign-in grid leak (`App.css`).** `.web-password-sign-in` gets a two-column split (`minmax(17rem,.9fr) minmax(25rem,1.1fr)`) at ≥52rem for its visual pane + form card. The *embedded* variant (used on the dedicated `#sign-in` page and the account panel) never renders the visual pane (`WebPasswordSignInPanel` returns `null` for it when `embedded`), but the media-query selector `.web-password-sign-in` still matched the embedded element too, since both classes sit on the same node. The result: the lone form card was auto-placed into the narrow first column, stranding it at roughly 40% width with a dead second column beside it — reading as "spread/misaligned." Fix: the two-column rule (and the visual-pane `display: grid`) is now scoped with `:not(.web-password-sign-in--embedded)`, and the embedded variant gets an explicit single-column `grid-template-columns` for defense in depth.
- **Speak tab contrast (`tutorExperience.css`).** `App.css` styles the selected practice-mode pill as `background: var(--ge-color-action)` (cyan) with `color: var(--ge-color-action-text)` (near-black) — good contrast by design. `tutorExperience.css`, imported after `App.css`, still carried a pre-ion-theme override of the *identical selector* (`.shell-practice-switch button[aria-selected='true'] { background: linear-gradient(180deg, #1c1830, var(--ge-color-surface)); }`) plus a hard-coded container background, both predating the D-0250 token system. Same-specificity + later-in-bundle meant the stale rule won for `background` while the near-black `color` from `App.css` still applied — near-black text on a near-black gradient, i.e. invisible. Fix: removed the two stale declarations; the cyan-pill/dark-text selected state now renders as designed. Confirmed via computed-style check: selected tab background is `rgb(46,230,255)` with text `rgb(4,18,24)` (previously background stayed `rgba(0,0,0,0)` — fully transparent — while text stayed near-black).
- **Page width (`design-system/tokens.css`, `LevelSessionJourney.css`, `ProgressDashboard.css`, `ConversationJourney.css`).** The shared shell caps main content at `--ge-content-width` (raised 72rem → 80rem), but several page-level containers carried their own *tighter* caps well inside that budget and were not centered, so they rendered as a narrow column hugging the left edge with a large empty margin to the right: `.level-path`/`.level-session` (Learn) at 48rem, `.progress-dashboard` at 800px, `.speak-room` at 64rem, `.conversation-journey` at 56rem. All four now stretch to `width: 100%` of the shell; their internal `auto-fit` grids (level list, progress summary/mastery grids) pick up more columns automatically. Paragraph-level text inside these containers keeps its own narrower `max-width` (unchanged) for readability.
- **Account role (`App.tsx`, `App.css`, all six locale catalogues).** The header account menu showed only "Signed in." plus the organization name — never the membership type, and the API (`UserIdentitySummary`) intentionally exposes no username/profile field (ULID-only, by design, per the no-PII-in-session-payload posture). Added a `session.membership_type.{learner,trainer,organization_admin}` badge next to the status text, sourced from `current_membership.membership_type` (already present on the session payload, no API change). New keys added to `en-IN`/`kn-IN`/`ml-IN`/`ta-IN`/`te-IN`/`hi-IN` catalogues (English text for the non-`en-IN` locales, matching the existing untranslated precedent for `operator.people.membership_type.*` role labels); governance `catalogueHash`/`revision`/`sourceHash` recomputed for all six locales (`*.31.3`) and `pnpm --filter @global-english/localization run validate` passes. A user-visible display name/username is not available from the current API contract; surfacing one would require a backend contract change and is flagged as a follow-up, not attempted here.
- **Superseded for microphone:** D-0256 adds optional HTTPS `:8443` for capture; HTTP `:8080` still cannot open the microphone.
- Cache: public service worker `global-english-public-shell-v11`.
- Verified: `pnpm run lint` (0 warnings) and `pnpm run typecheck` clean at the repo root; `pnpm --filter @global-english/web test` 323/324 (pre-existing D-0249 `avatarConfig.test.ts` SVG-vs-GLB failure only, unrelated); `pnpm --filter @global-english/design-system test` 11/11; `pnpm --filter @global-english/localization test` 31/31 and `run validate` clean; `pnpm --filter @global-english/web e2e` 22/22 (axe-clean shell unaffected by the width/contrast changes); preview dist rebuilt and the sign-in tile, Speak tabs, and header account badge confirmed via authenticated Playwright screenshots against the rebuilt `dist`.
- Known limitations: the broader "make the whole theme more futuristic" feedback is open-ended and not fully addressed by this pass, which targeted the five concrete defects reported; a learner-facing username/profile display remains unavailable pending a backend contract change; the pre-existing `avatarConfig.test.ts` SVG/GLB assertion mismatch (D-0249) is untouched.

## D-0253: Hero copy vignette replaces the blocking glass card — 31 August 2026

- Accountable Product Owner report: after D-0252, "the huge tile blocks the scrolling image on the homepage."
- Cause: D-0252 fixed the slideshow's own darkening, but replaced it with a bordered, backdrop-blurred glass "card" (`.marketing-hero__copy`) up to 44rem wide with 3.5rem padding, a visible border, and a heavy box-shadow — a large opaque rectangle sitting on top of the now-visible banner.
- Change (`MarketingLanding.css` only): the card's border, box-shadow, and `backdrop-filter` are removed. `.marketing-hero__copy` is now a borderless `radial-gradient` vignette (darkest at the text center, transparent by ~80% out) with no hard rectangular edge, so the photo shows through immediately around and behind the text instead of being blocked by a tile. The title gained an added solid dark text-shadow layer (on top of the existing neon glow) and the lede gained its own text-shadow, since legibility can no longer lean on an opaque backdrop.
- Cache: public service worker `global-english-public-shell-v10`.
- Verified: `pnpm run e2e` 22/22 (axe-clean shell unaffected — no new contrast violations); preview dist rebuilt (`index-vPotzEc7.css`) and confirmed live at `http://192.168.1.4:8080` via screenshot at desktop and 390px widths — both show full faces/shoulders visible left and right of the text with no visible card edge.

## D-0252: Marketing hero visibility and neon card polish — 31 August 2026

- Accountable Product Owner report: the homepage slideshow behind the hero text was "barely visible" and the theme read as "a black page and nothing more" next to getsupernova.ai.
- Cause: `.marketing-hero__media` rendered banner photos at 42% opacity, then `.marketing-hero__scrim` laid a near-opaque dark gradient (up to 0.94 alpha) over almost the whole frame, so the slideshow was doubly darkened.
- Change (`MarketingLanding.css` only, no markup/copy change):
  - Banner opacity raised to ~0.88 (0.8 on the ≤30rem breakpoint) with a duotone cyan/magenta filter instead of a flat dark wash.
  - The old full-hero scrim is now a light top/bottom vignette (for header/button legibility) plus corner color glows; the middle of the frame stays bright.
  - `.marketing-hero__copy` is now a glassmorphism spotlight card (blurred, bordered, glowing) that carries text contrast, so the image no longer needs to be darkened to keep the headline readable.
  - Headline gets a neon cyan/magenta text-glow; the AI badge pulses gently (disabled under `prefers-reduced-motion`).
  - `.marketing-section--light`/`--dark` gained a second corner glow (magenta/violet) instead of one flat radial, plus a thin gradient seam between sections so the page reads as one lit stage instead of stacked black panels.
  - Path/pedagogy/why/showcase/benefit card hover states use a colored cyan glow shadow instead of a plain black shadow.
- Cache: public service worker `global-english-public-shell-v9`.
- Verified: `pnpm exec tsc -b`; ESLint 0 warnings; vitest 71/72 files (see known limitation); `pnpm run e2e` 22/22 (axe-clean shell unaffected); preview dist rebuilt (`index-DKTegzMa.css`) and confirmed live at `http://192.168.1.4:8080` with the new duotone hero visible.

## D-0251: Guest learner hashes keep the marketing landing — 31 August 2026

- Accountable Product Owner report: the “landing page” showed only `Spoken English learning` / Learn / the four-session description / the sign-in hint. That copy is the guest Learn chrome (`page.learn.*` + `session.sign_in_hint`), not a CSS-hidden marketing hero.
- Cause: `showMarketingLanding` required `destination === 'home'`. A guest URL of `/#learn` (used on the earlier Cloudflare origin) unmounted `MarketingLanding` and left the empty gate.
- Change: guests see `MarketingLanding` on every hash except `#sign-in`. After authentication, `#learn` and the other destinations still open the real learner journeys.
- Cache: public service worker `global-english-public-shell-v8`.
- Removed unused `InteractiveAvatar` import from `MarketingLanding` (hero coaches stay off the slideshow per the overlap instruction).

## D-0250: Ion studio visual system — 31 August 2026

- Accountable Product Owner instruction: abandon the existing academy green/cream theme and revamp every page, form, and button to a modern futuristic look. SuperNova branding is still not copied (D-0215). No governed localization copy changed.
- **New token system** in `packages/design-system`: void indigo canvas (`#06050f`), electric cyan actions (`#2ee6ff` on `#041218`, 12.5:1), magenta accent (`#d61d6e` with white text 4.9:1), violet brass/eyebrow. `color-scheme: dark`. Geometric UI stack for display type. Glass panels, cyan focus rings, neon primary buttons.
- **Surfaces restyled:** marketing landing (grid + orbs, all-dark bands, glass cards), sticky glass header/nav, sign-in split card, Home/Learn/Speak/Progress/1:1, coach selector, operator console, demo shell, error boundary, hold-to-talk glow, form inputs and submit buttons.
- **Cache:** public service worker `global-english-public-shell-v7`.
- Tutors stay SVG (`tutorRenderer: "svg"`). GLB path unchanged.
- Verified: `pnpm --filter @global-english/web typecheck`; ESLint 0 warnings; vitest 72 files / 323 tests; `pnpm run e2e` 21/21 (axe-clean shell, 320-px/enlarged-text reflow, skip-link/sign-in); preview dist rebuilt (`index-DpkVkPmw.css`) and confirmed live at `http://192.168.1.4:8080` with `--ge-color-action: #2ee6ff` and ion frame classes. PHP/API untouched.

## D-0249: Site-wide visual/UX enhancement pass; SVG tutors as the visual base — 31 August 2026

- Accountable Product Owner instruction: analyze and enhance the whole website's pages, forms, buttons, flow, layouts, animations, and images, using getsupernova.ai as a quality reference. Per D-0215, SuperNova branding is not copied; only composition and motion quality were benchmarked. No governed copy changed — every visible string still resolves from the existing localization catalogues.
- **Tutor visual regression found and fixed.** Live-browser inspection of the deployed `:8080` site showed both hero coaches painting as garbled dark blobs: the D-0247 procedural GLBs load and render, but the face atlas/UV mapping is wrong and the hard-coded close-up camera makes it worse. `avatarConfig.json` gained `"tutorRenderer": "svg"`; `InteractiveAvatar` only mounts `GltfTutorStage` when it is `gltf`, so every tutor surface (marketing hero, sign-in header, Home, Speak, coach selector) renders the reviewed SVG rig. The GLB code path, assets, and contract tests are unchanged and re-enable by config after a quality-passing bake.
- **Marketing landing redesign** (`MarketingLanding.tsx/.css`): larger Fraunces display headline; animated aurora wash and banner slideshow behind the hero; coach duo on a lit "stage" with float animation; overlapping portrait cluster; gradient primary CTA with hover lift; IntersectionObserver scroll-reveal for below-the-fold sections (visible-by-default without JS, fully disabled under `prefers-reduced-motion`); the four-session method as 4-across numbered cards on wide screens; pedagogy cards with gradient top rules; hover lift/zoom on why/benefit/showcase cards; showcase screenshots in device-style frames; the "One path, two kinds of support" card image switched from the badly-cropping `club.jpg` banner to `guest.jpg` (contain).
- **Shell polish** (design-system `components.css`): header backdrop gains saturation blur and a soft shadow; the active navigation destination is marked with an emerald underline + keyline instead of brass; nav links get a hover raise (disabled under reduced motion).
- **Sign-in flow fix:** the "Use the HTTPS link to sign in…" alert and its `secureContext` check were removed from `WebPasswordSignInPanel` — under D-0248 the sanctioned origin is plain HTTP with non-Secure session cookies, so the warning was factually wrong and scared invitees away. Mobile hero banner strip also fixed (flex `align-items: stretch` at ≤30rem; it previously collapsed to zero width).
- SVG rig hair gradient lightened (`#5b3a25 → #2e1d12`) so both coaches read clearly against the pale avatar frame; learner cards gained a subtle hover lift with a reduced-motion companion.
- Verified: `pnpm typecheck` (all packages); `pnpm lint` (0 warnings); `apps/web` vitest 72 files / 323 tests passed; `pnpm --filter @global-english/web build`; `pnpm run e2e` 21/21 passed (includes axe-clean shell, 320-px/enlarged-text reflow, keyboard skip-link/sign-in); desktop + 390-px mobile screenshots of the deployed `:8080` site reviewed in-browser. PHP/API untouched, so `make verify`'s composer/PHP stages were not re-run.

## D-0248: Static-IP HTTP public origin — 31 August 2026

- Public origin is `http://117.254.108.143:8080`. `GE_SOFT_LAUNCH_SERVE_PUBLIC_HTTP=true` keeps Caddy serving the SPA and API on `:8080` instead of redirecting to a rotating tunnel, and the supervisor no longer starts or requires `cloudflared`.
- `persist_public_origin` now parses `http://` hosts, writes `APP_URL` and `SANCTUM_STATEFUL_DOMAINS` for `117.254.108.143:8080`, and keeps `SESSION_SECURE_COOKIE=false`. Tailscale hostnames are no longer injected into Sanctum.
- Public Caddy on `:8080` also routes `/oauth/*`, `/auth/mobile/*`, `/health/live` and `/health/ready` to Laravel so Android PKCE and health probes match the loopback proxy.
- Browser microphone capture is not a secure context on this HTTP origin (except `localhost`). Speak/hold-to-talk from phones needs a later HTTPS hostname.
- Verified: `scripts/test-soft-launch-environment.sh`; `make soft-launch-ready` 29 pass / 0 fail; local `http://127.0.0.1:8080` health 200 after service restart; Caddy bound on `*:8080`. Hairpin NAT timed out from this host to `117.254.108.143:8080` (expected on this LAN).

## D-0247: Project-owned GLB tutors with viseme morph targets — 31 August 2026

- Baked two original glTF 2.0 binary busts with `scripts/generate-tutor-glb.py`: `apps/web/public/avatars/coach-female.glb` (7856 tris, 4544 verts, ~887 KB) and `coach-male.glb` (7008 tris, 4040 verts, ~789 KB), each with a sibling 1024×1024 albedo PNG referenced by URI (so the WebGL loader does not create `blob:` object URLs). Each file has the humanoid bones `Hips`/`Spine`/`Spine1`/`Neck`/`Head` plus shoulders/arms/hands and morph targets named exactly `viseme_sil`, `viseme_aa`, `viseme_E`, `viseme_I`, `viseme_O`, `viseme_U`, `viseme_PP`, `viseme_FF`, `viseme_TH`, `viseme_DD`, `jawOpen`.
- These are project-owned procedural meshes, not Ready Player Me, Mixamo, or photographic scans. A DCC artist can replace either `.glb` 1:1 if they keep those morph names, joint names, the triangle budget, and the single-atlas constraint.
- WebGL path: `GltfTutorStage` dynamically imports Three.js `0.185.1` and `GLTFLoader`, drives morph influences from the existing Speak `mouth_shape` timeline, and nods/leans the `Head` bone for `AvatarGesture`. `import.meta.env.BASE_URL` is used so Capacitor's relative `./` demo base still resolves the assets.
- Fallback: jsdom tests and WebViews without `WebGLRenderingContext` keep the SVG `InteractiveAvatar` rig. A GLB load failure calls back into that same SVG path. `CoachAvatar`'s independent `StaticCoachBadge` is unchanged.
- Recorded as **D-0247**, amending [ADR-0031](adr/0031-selectable-realistic-ai-coaches.md) items 5–6.
- Verified: `pnpm exec tsc -b`; ESLint on the new files; `pnpm exec vitest run` in `apps/web` (71 files / 321 tests, including GLB contract tests that parse the binary for viseme names, bones, triangle cap, and 1024 atlas); `pnpm run build`; `pnpm run e2e` (21/21). PHP/API was not touched.

## D-0246: InteractiveAvatar SVG rig replaces the photographic coach everywhere — 31 August 2026

- `InteractiveAvatar` (previously a standalone, unused component — see the entry below) is now the coach visual base across the app. `CoachAvatar.tsx` was rewritten to render it internally instead of a `<img>` portrait + separate SVG mouth overlay; `SwathiAvatar`, and every screen that already consumed `CoachAvatar`/`SwathiAvatar` (`ConversationJourney`'s Speak header), needed **no prop changes** — only `CoachAvatar`'s internals changed.
- `TutorPresence` and `TutorHomeHero` (Home greeting card, Role-play header, Level-session teacher stage, sign-in header) now render `InteractiveAvatar` directly instead of a static `coach-{gender}-{neutral,speaking}.webp`/`-hero.webp` image plus hand-rolled orbit-ring `<span>`s; the ring, idle blink, and figcaption are now owned by `InteractiveAvatar` itself.
- `CoachSelector`'s per-coach preview thumbnails and `MarketingLanding`'s hero coaches (female + male) now render small/hero `InteractiveAvatar` instances instead of static webp images.
- Extended `InteractiveAvatar` itself (`apps/web/src/components/InteractiveAvatar.tsx`) to support this reuse:
  - `coachGender: 'female' | 'male'` selects distinct hair silhouette, brow weight/shape, collar color, earrings (female only), and a light facial-hair accent (male only); skin, eyes, cheeks, nose, and the mouth rig are shared.
  - `presence` (`idle`/`listening`/`processing`/`preparing`/`speaking`) drives a cosmetic two-ring ambient glow (reusing the color language of the previous `tutor-presence__orbit`) and, for `speaking`/real `isSpeaking`, a gentle breathing pulse — independent of the real viseme-driven mouth, which still only activates with real `audio`+`visemeTimestamps`.
  - Sizing moved from the inner stage `<div>` to the outer `<figure>` itself (percentage-sized inner stage), so callers can override the rendered width with ordinary CSS by qualifying a parent-class selector (e.g. `.marketing-hero__coaches .marketing-hero__coach`) — a two-class selector reliably outweighs the component's own single-class Tailwind width utility regardless of stylesheet load order. `MarketingLanding.css` and `TutorPresence.css` were updated to this pattern (including their `prefers-reduced-motion` overrides, which needed the same selector specificity to keep winning).
  - Every `animate-avatar-*`/ring/pulse utility now has a `motion-reduce:animate-none` companion, restoring the reduced-motion guarantee the old CSS provided explicitly.
  - `caption` now accepts a `ReactNode` (not just `string`), so `CoachAvatar`/`TutorPresence` can still pass `<LocalizedMessage>` for the status line and keep the existing translation-fallback-reporting behavior (`useInterfaceFallbackReporting`, `data-message-review-status`) instead of a plain resolved string.
- Hardened the error-boundary fallback: `CoachAvatarErrorBoundary`'s fallback is now a genuinely independent `StaticCoachBadge` (a plain initial-letter badge, no SVG rig, no viseme/contracts parsing) rather than a second `InteractiveAvatar` instance — re-rendering the same rig inside its own error boundary would not have been resilient to a systemic rig failure.
- Removed the now-dead `CoachAvatar.css` and the six superseded `coach-{female,male}-{hero,neutral,speaking}.webp` portraits plus `COACH_ASSET_PROVENANCE.md` (confirmed unreferenced first).
- Recorded as **D-0246**, amending [ADR-0031](adr/0031-selectable-realistic-ai-coaches.md) items 5–6 (see `docs/DECISIONS.md`). No change to coach selection, renaming, governed default names, prompts, provider contracts, marks, mastery, consent, retention, or tenancy.
- Android note: the Android app is the same React/Vite bundle wrapped by Capacitor (no separate native avatar code), so this change ships to Android on the next `npx cap sync` + Android build; no native Android files were touched.
- Verified: `pnpm run typecheck`, `pnpm run lint` (both zero warnings, full workspace), `pnpm run test` (full workspace: 358 tests passed across `packages/localization`, `packages/design-system`, and `apps/web`; `apps/web` alone: 69 files / 316 tests), `pnpm run build` (production Vite build succeeds; no new warnings beyond the pre-existing JS chunk-size advisory), and `pnpm run e2e` (all 21 Playwright journeys pass, including the axe-clean accessibility check and the Speak journey's mouth-rig/gesture assertions, updated to scope by test id/region now that the coach selector adds extra `InteractiveAvatar` instances to the Speak page). PHP/API was not touched in this checkpoint and was not re-run.

## InteractiveAvatar SVG face rig + Tailwind CSS toolchain — 31 August 2026

- Added a new, self-contained `InteractiveAvatar` component (`apps/web/src/components/InteractiveAvatar.tsx`) as an inline-SVG alternative face rig, separate from the existing photographic `CoachAvatar`. Head, eyes, eyebrows and mouth all live in one `viewBox="0 0 200 200"` coordinate space (no CSS-positioned overlay on a separate image), so the mouth cannot clip or drift from the face across viewport sizes.
- The mouth is driven by a bespoke `requestAnimationFrame` viseme controller that samples a target `<audio>` element's `currentTime` each frame, maps provider viseme codes onto a small canonical shape set (`rest`/`closed`/`A`/`E`/`I`/`O`/`U`) via an always-total alias table, and republishes only on change. Missing/empty viseme metadata or a `null` audio element resolves to `rest` with no console errors — the idle-blink loop (a `scaleY` blink on each eye, plus an idle head sway) runs unconditionally, so the fallback is always visually present.
- Gesture playback reuses the existing `AvatarGesture` contract (`ENCOURAGING_NOD` → nod, `ATTENTIVE_LEAN` → tilt, `WARM_SMILE` → smile), triggering a hardware-accelerated CSS `transform` keyframe animation once per gesture pulse and auto-returning to idle after the animation duration.
- **New toolchain dependency:** added `tailwindcss` and `@tailwindcss/vite` (both pinned exactly, `4.3.3`) to `apps/web`, wired via the official Vite plugin. The Tailwind entry stylesheet (`apps/web/src/styles/tailwind.css`) intentionally imports only the `theme` and `utilities` layers — **not** `preflight` (Tailwind's global browser-default reset) — so the existing hand-built design-system CSS and all other component stylesheets are unaffected. Utility classes are opt-in per component. Custom gesture/blink/sway keyframes are declared in the same file via `@theme`.
- `InteractiveAvatar` is not yet imported by any screen; it ships fully implemented and tested as a standalone component pending a follow-up decision on whether/where it replaces or supplements `CoachAvatar`.
- Verified: `pnpm exec tsc -b` (typecheck), `pnpm run lint` (full workspace, zero warnings), `pnpm exec vitest run` (apps/web full suite: 316 tests passed, up from 309), and `pnpm run build` (production Vite build succeeds; CSS bundle 137 kB / 22 kB gzip, no new build warnings beyond the pre-existing JS chunk-size advisory). No PHP, API, contract, or OpenAPI surface changed.

## DR-0038 C: Speak hold-to-talk latency — 30 August 2026

- Client loop now logs `record_stop` → `wav_ready` → `upload_done` → `transcript_ready` → `turn_json_ready` → `first_audio` as millisecond durations only (no transcript, audio, or PII). Dev overlay is `aria-hidden`.
- Hold UI shows listening / processing / preparing / speaking (§7.4) with a live waveform while held. Cancel aborts the in-flight upload and turn (`AbortSignal`).
- PCM with audible speech encodes WAV immediately and does not wait for the backup MediaRecorder `stop`. Reply audio plays when the turn JSON arrives, in parallel with `GET .../events`. Sanctum CSRF is reused after the first fetch on that API instance.
- Type-instead and low-confidence “Swathi heard” retry are unchanged. No new provider, no ledger change, no consent-flow change.
- Client-only synthetic release-to-first-audio on this machine (21 samples, 80ms recorder wait + 40ms events RTT model): **p50 125→43ms, p95 127→44ms**. Live Azure STT/model/TTS was not measured and is unchanged.
- Verified: `pnpm run lint`, `pnpm run typecheck`, `pnpm run test` (295 web tests), `pnpm run locale:check`. PHP not touched.

## CI `verify` e2e failures fixed locally — 30 August 2026

- CI run 33314026223 on PR #3 cleared PHPStan and web lint but failed `make e2e` with 12 Playwright failures. Root causes, all fixed in `745ab6c` and `9818fe2` with the full 21-test suite passing at `--workers=1` (CI's configuration): (1) brass eyebrow text `#8f6f28` failed WCAG AA contrast at 13px bold on light surfaces — token darkened to `#7d6124` (≥4.5:1 on surface, panel and notice backgrounds); (2) `.operator-console__notice` inherited the dark heading panel's light text onto its own light notice background (1.18:1) — explicit ink colour added; (3) the e2e `home()` stub predated checkpoint A's `streak`/`due_review` fields, so the Home render threw and three learner journeys timed out — stub updated; (4) headless recording had no capture device (`playwright.config.ts` passed the non-existent `--use-fake-device-for-media-capture` flag) — the spec now synthesizes an oscillator `MediaStream` for `getUserMedia`, the config carries the real `--use-fake-device-for-media-stream` flag, recording press windows widened to 1200ms for the 4096-frame PCM buffers, and the stale `audio/webm` receipt-mime assertion accepts the canonical opus-tagged variant; (5) marketing landing overflowed horizontally at 320px/400% enlarged text (grid children `min-width:auto`, rem paddings) — `min-width: 0`, `overflow-wrap: anywhere` and px card padding at narrow widths; (6) the invited-account journey never stubbed `/sanctum/csrf-cookie`, which the credential adapter requires — stub plus XSRF cookie added, matching the operator-shell pattern; (7) the header sign-in link toggles its href on state and React flushed the click's state update before navigation, so clicking navigated to the flipped target — the handler now owns the navigation explicitly.
- Home e2e journeys reference the new `learner.home.continue` label from the flagship Home commit (`d3697b6`). Commits `d3697b6`–`9818fe2` are pushed.
- CI run [33317082476](https://github.com/srbhandary1/GESC/actions/runs/33317082476) passed all four jobs (`verify` 9m15s, `android`, `operations`, `release-gate`). Full `make verify` inside `verify` exercised Pint, PHPStan at zero, SQLite and PostgreSQL integration suites (including ADR-0028 cross-tenant and mastery-evidence constraint tests), contract and locale validation, lint, typecheck, workspace tests, 21 Playwright journeys, and build. Checkpoint A is accepted per D-0220.

## DR-0038 B: Progress read-model API — 30 August 2026

- `GET /api/v1/me/progress-dashboard` returns organization-scoped mastery by objective, speaking activity (meaningful speaking days + completed turn digests/durations), first-attempt vs retry improvement, delayed-review performance, and recurring-error trends. No schema changes; advisory AI values never appear as marks.
- OpenAPI **0.35.0** and generated contracts updated. Feature tests cover auth and contract shape on SQLite; PostgreSQL cross-tenant isolation test is in `ProgressDashboardTenancyTest` (CI only — this host has no `pdo_pgsql`).
- Progress tab UI (`#progress`) still renders `LevelPathPanel`; that remains the next B slice.

## Checkpoint A verified — 30 August 2026

- Acceptance evidence: GitHub Actions run [33317082476](https://github.com/srbhandary1/GESC/actions/runs/33317082476) on PR [#3](https://github.com/srbhandary1/GESC/pull/3) at `9818fe2`. Recorded as [D-0220](DECISIONS.md); quality review in [QUALITY_REVIEW.md](QUALITY_REVIEW.md#dr-0038-a-daily-loop--30-august-2026).
- Delivered: meaningful-speaking-day projector and streak reader, deterministic delayed-review scheduler, migration `2026_08_30_000270_create_daily_mission_home_read_models`, Home API `streak` and `due_review`, regenerated contracts, localized flagship Home UI (`LearnerJourney`), privacy-inventory revision, and ADR-0028 PostgreSQL constraint coverage.
- B and C remain unstarted. Production/Play, curriculum publication, and independent Kannada UI review stay unauthorized.

## Checkpoint A implemented, verification blocked — 30 August 2026

- Added `MeaningfulSpeakingDayPredicate`, `MeaningfulSpeakingDayProjector`, `MeaningfulSpeakingStreakCalculator`, `MeaningfulSpeakingStreakReader`, `DelayedReviewScheduler`, migration `2026_08_30_000270_create_daily_mission_home_read_models`, `config/review.php` (provisional intervals `[1, 3, 7, 14]`), Home API fields, regenerated contracts, localized Home UI, and a privacy-inventory revision.
- The projector reads only `completed_at`, `assistant_reply_digest` and `failed_stage`, is organization-scoped, uses `insertOrIgnore` against a unique constraint, and persists the resolved timezone. `organizations.timezone` already exists with an `Asia/Kolkata` default, so the in-transaction lookup cannot fail the way the 29 August ledger CHECK did.
- **`make verify` has never run for this checkpoint.** `scripts/check-toolchain.sh` compares `.php-version` (8.4.23) against the installed runtime; this host has only PHP 8.5.4 and no 8.4 binary, so the gate exits before any check. This is pre-existing and unrelated to checkpoint A.
- **No integration test has run on this host.** `tests/Integration` is registered only in `phpunit.postgres.xml`, and PHP 8.5.4 here has no `pdo_pgsql` extension, so every integration case errors with "could not find driver". Any earlier report of passing integration tests for this checkpoint could not have executed. CI pins PHP 8.4.23 and is the only place these run.
- Runnable locally: `tests/Unit` + `tests/Feature` = 206 tests, 201 passed. The 5 failures are pre-existing OTP/auth 401s (`AccountSetupRecoveryTest`, `InviteAwareOtpAuthenticationTest`, `WebSessionPolicyTest`) on the password-only branch; none touch the checkpoint A path. Pint passes.
- **Two ADR-0028 test areas were missing and are now written:** active cross-tenant rejection, and proof that a review occurrence cannot be created without deterministic mastery evidence. Idempotency of replayed mastery events was already covered inside the PR-006 lifecycle test. Both new blocks are guarded to `pgsql` because they assert a Postgres trigger and composite foreign keys, so they are exercised only in CI.
- `ConversationTurnService` gained only the projector hook. The guidance-TTS synthesis and second `ai_cost_ledger` row in that file are pre-existing 29 August work (migration `2026_08_29_000250` predates this slice); checkpoint A added no provider call and no spend.
- Stale expectations in `ConversationFakeProviderTest` and `CurriculumPublicationLifecycleTest` were updated to match that pre-existing guidance-TTS behaviour. A pre-existing `storage/.../voice` permission denial still affects the curriculum lifecycle test.
- First CI run on PR [#3](https://github.com/srbhandary1/GESC/pull/3) (run 33308251724): `operations` pass, `android` pass, **`verify` fail**, `release-gate` fail as a dependant. `verify` stopped at PHPStan with 50 errors, at `Makefile:148`. PHPStan runs before any test phase, so that run never executed tests.
- PHPStan is now **0 errors** locally (independently re-run after the cleanup). Pint passes. SQLite PHPUnit remains 206 tests / 201 passed / 5 pre-existing OTP-session 401s.
- Root cause of 38 of the 46 remaining errors: `LessonPracticeBlueprint` had no `@property` docblock, so Larastan typed `canonical_json` as `string` (the migration's `$table->json()`). `LevelSessionDeliveryService` was never wrong; it was not edited.
- Latent runtime bug fixed: `bootstrap/app.php` called `env('TRUSTED_PROXIES')` inside `withMiddleware`, which runs before the environment file loads, so a `.env` value was silently ignored. Proxies now come from `config/trustedproxy.php` at request time. Default remains `127.0.0.1,::1`. **If a live `.env` already has a broader `TRUSTED_PROXIES`, that value will start taking effect.**
- **Superseded (30 August 2026):** CI run [33317082476](https://github.com/srbhandary1/GESC/actions/runs/33317082476) passed full `make verify`, including the Integration suite and both pgsql-guarded ADR-0028 constraint tests. Checkpoint A is **accepted** per D-0220; see "Checkpoint A verified" above.
- A reconciled product brief ("Master Planning Brief: Phase 1 – Reconciled") was received and folded into DR-0038 as an addendum. It confirms the APP-0069 / D-0219 decisions and changes no accepted decision; the addendum adopts its claim-grounding rules and strict A→B→C sequence, and corrects its Sarvam line against D-0217 / ADR-0027 §6.
- A third product-owner specification ("Phase 1 – Multi-Language Ready") arrived on 30 August. Its reappearing Flutter / Miss Nova / attendance / 19-level / Notice Board items were confirmed by the product owner as stale template text; D-0219 is unchanged. Its five genuinely new items — advisory pronunciation colour map, viseme-driven animated tutor, speech-speed control, Kanglish transliteration, and te/ta/ml activation gates — are drafted as [DR-0039](DR-0039_PRONUNCIATION_AVATAR_PACING_TRANSLITERATION_LANGUAGES_REQUEST.md), proposed, implementation sequenced after DR-0038 A/B/C are verified.

## DR-0038 acceptance: daily loop, Progress, Speak latency — 30 August 2026

- A product-owner "Master Orchestration Specification (Phase 1 – Revised)" proposed a Flutter rewrite, Sarvam/GPT-4o/ElevenLabs as named architecture, 19 levels with two pre-levels, an attendance percentage gauge, a Notice Board, a teacher rename to "Miss Nova", and a compute-profile orchestration protocol. DR-0038 separated it into seven decidable items.
- **Accepted (APP-0069 / D-0219):** A — Daily Mission Home with a meaningful-speaking-day streak and deterministic delayed review; B — Progress conforms to master plan §7.3 and stops rendering the Learn level path; C — Speak latency pass with no new provider.
- **Confirmed:** client stays React PWA + Capacitor 8 (Flutter/iOS deferred, §9.2); levels stay generic and Levels 2–17 keep APP-0065/0066/0067 authored content; no attendance percentage in canonical records; Notice Board deferred; teacher remains Swathi.
- **Not accepted:** DR-0038 E (Sarvam driver flip stays under D-0217; ElevenLabs deferred pending a costed comparison against Azure `kn-IN-SapnaNeural`), and the compute-profile orchestration protocol.
- ADR-0028 amends master plan **§7.2** only. DR-0038 had claimed §7.1; that was wrong and is corrected in APP-0069, ADR-0028 and the DR itself. §7.3 already required the Progress contract, so B is conformance rather than an amendment.
- The proposed `LegacyAudioSyncService` pulling from `globalspokenenglish.com` is not authorized. Teaching audio was re-ingested on 29 August from local object storage only, under the ADR-0016 rights path.
- Master plan is version 3.9. No product code changed in this slice.

## Guidance TTS cost ledger + Swathi identity — 29 August 2026

- Speak/Learn hold-to-talk failed after STT with `conversation.error` (“Maya is unavailable”) because `POST .../turns` returned HTTP 500. Azure OpenAI and guidance TTS succeeded, then inserting `ai_cost_ledger` for `azure-speech-guidance` violated `ai_cost_ledger_values_check` (APP-0064 TTS was never added to the CHECK). The turn transaction rolled back, so no reply was stored.
- Migration `2026_08_29_000250` allowlists `provider_key = azure-speech-guidance` with `model_version` prefix `soft-launch-guidance-tts-v1:`.
- Android turn-event SSE now prefixes the baked API origin when `events_url` is a relative `/api/v1/...` path.
- D-0218: the on-screen teacher is Swathi end-to-end (prompts, Azure output contract, UI catalogues `ui-*-2026-08-29.6`). Role-play scene characters are unchanged. Illustration files may remain `tutor-maya.png`.
- Master plan §7.1 Speak wording is version 3.8.

## Android voice answers + lesson question after audio — 29 August 2026

- Capacitor keeps WAV uploads only when the FormData part is a named `File` and native fetch still sees `init.body` as FormData. An unnamed `Blob` becomes `[object Blob]`; wrapping FormData in a `Request` first turns it into a stream that Capacitor UTF-8-decodes.
- After Introduction audio, Maya asks a lesson question from the blueprint (`Can I use your pen?`, not the internal “Ask one Can I…?” coach note) and waits for a spoken answer. If the English needs work, the correction block shows clearer English plus the Kannada (or UI-locale) why. Marks and mastery stay unchanged.
- Azure Speech STT/TTS is the voice path. Azure OpenAI from this Ubuntu host is reachable (allowlist confirmed 29 August 2026).

## Android lesson-audio playback — 29 August 2026

- Learn session players used a native `<audio src="/api/v1/me/levels/.../audio">`. On Android that request hits the Capacitor WebView origin without a Passport bearer, so the play control stayed disabled.
- Level-session audio now uses `ModelAudioPlayer` and, on Android, downloads bytes through the authenticated fetch adapter into a revocable blob URL (same private-media boundary as model audio and lesson video). Web/PWA still uses cookie same-origin media.
- Path allowlist is `/api/v1/me/levels/{n}/sessions/{part}/audio` only. Native `<audio controls>` is no longer the Learn player.

## Five-destination nav + Indic STT port — 29 August 2026

- DR-0037 **B** accepted: primary nav is Home · Learn · Speak · Progress · 1:1. Learn is the four-session level path. Speak is the hold-to-talk room. 1:1 is the waitlist plus optional institute contact links from env. `#practice` aliases Speak. Master plan §7.1 is version 3.7 (ADR-0026 / D-0216).
- Indic recorded STT HTTP adapter is implemented behind `RecordedSpeechToTextProvider` (`VOICE_PROVIDER_DRIVER=indic`, translate-to-English). Soft-launch stays Azure. No live Indic keys. ADR-0027 / D-0217.
- UI catalogues `ui-*-2026-08-29.5`. OpenAPI `0.33.0`. Kannada remains `pending-independent-review`.

## Speak room hold-to-talk — 29 August 2026

- Practice tabs are **Levels | Speak**. Speak is everyday talk with Maya plus a situation picker. Confidence Conversation and Role-play are no longer sibling tabs.
- After one session consent, a single hold button records; release uploads, auto-accepts a confident transcript, and sends the turn. Low-confidence results still show what Maya heard for fix/retry. Type instead remains.
- Talk and Perform start immediately. Warm-up, Listen, Observe, and Rehearse are optional “Hear an example” details, not gates.
- Four-session names stay Introduction → Practice → Conversation → Interaction (hear the rule → short drills → guided talk → free speech). Later review stays Progress.
- ADR-0007 per-turn submit is amended by D-0215. DR-0037 A is accepted; B (1:1 last nav item) is not.
- UI catalogues `ui-*-2026-08-29.4`. Kannada remains `pending-independent-review`. Copy is original Maya phrasing, not SuperNova branding.

## Maya identity + disclosure copy — 29 August 2026

- Talk-stage Azure replies could invent a personal name (heard as Swathi) because `friend.turn.v1` only said “patient Friend coach” while the UI teacher is Maya. Active prompts now pin Maya and forbid any other name. Azure output contract requires `assistant_reply_en` spoken as Maya.
- Duplicate “About this AI Friend” came from a visually hidden heading plus the same summary text. Disclosure is now “About Maya”; product copy names Maya; voice consent steps are unchanged.
- UI catalogues `ui-*-2026-08-29.3`. Kannada remains `pending-independent-review`.
- PostgreSQL integration PHPUnit inherited live `DB_*` values via `$_SERVER` even when `phpunit.postgres.xml` set `global_english_test`. That refreshed the live schema during this slice. Live data was restored from `~/ge-soft-launch/backups/soft-launch-20260813T060939Z.sql.gz`, migrations `000200`–`000240` were reapplied, APP-0062–APP-0067 and Level 1 were recommissioned from local object storage (no legacy site), credentials were re-established for `admin` / `northstar` / `srbhandary`, and a new snapshot was written at `~/ge-soft-launch/backups/soft-launch-20260829T073234Z.sql.gz`. `tests/bootstrap.postgres.php` now forces the test database into putenv/`$_ENV`/`$_SERVER`, and tests refuse to boot against `global_english`.

## Teaching-audio restore + sign-in page — 29 August 2026

- Levels 2–17 teaching MP3s were still in local object storage after the 13 August database restore. They were copied from that store only (no legacy-site access), re-ingested under APP-0062 (63 accepted, 4 Level 1 hashes already present), then commissioned APP-0063–APP-0067. Level 1 was re-attached from the existing APP-0047 assets. Live DB now has 67/67 inventory hashes and all 17 levels available on Practice → Levels path.
- `http://127.0.0.1:5173/#sign-in` could render as an empty main area when the shell enter animation froze at opacity 0 (`animation-fill-mode: both`) or when a stale Vite HMR tab failed to reload modules. The shell content now stays visible if the animation does not run, the hash no longer targets a colliding `id="sign-in"`, and the dedicated sign-in view scrolls to the top.

## Learner tutor UI + in-session teacher — 29 August 2026

- Local Vite sign-in (`127.0.0.1:5173`) failed because the live API treated that origin as non-stateful and used `__Host-` Secure cookies. Soft-launch now includes `127.0.0.1:5173` in Sanctum stateful domains and uses a non-`__Host-` session cookie so password sign-in works on loopback HTTP; the HTTPS tunnel origin remains listed.
- Username HTML `pattern` is Chromium `/v`-flag safe (`[A-Za-z][A-Za-z0-9._-]{3,63}`).
- Product owner required a Super Nova-class animated learner app: live role-play, mistake help in the chosen language, and a mock teacher after voice training — without stopping for extra process.
- Maya is the on-screen teacher (original character art). Home, levels, conversation and role-play share pulse/orbit motion, chat bubbles, and a large speak control. Android uses the same web UI plus a dark status/navigation bar.
- After Introduction/Practice audio ends, the bound tutor session starts in place (no tab jump). Practice MCQ is an optional details check; it still gates Practice complete in the API.
- UI catalogues `ui-*-2026-08-29.1`. Kannada remains `pending-independent-review`.

## Soft-launch sign-in restore — 15 August 2026

- Live Postgres had been emptied (schema replayed as migration batch 1; `users` = 0). Password POSTs were CSRF-complete and recorded `password.sign_in` failures with `known_user: false`.
- Restored `~/ge-soft-launch/backups/soft-launch-20260813T060939Z.sql.gz` after a safety dump of the empty database, then applied migrations `000200`–`000230`. Authentication mode is `password_only` again (two username/password credentials).
- Lesson-practice rows from APP-0063–APP-0067 (14 August) are not in that backup. Role-play catalog was re-seeded. Levels 2–17 private drafts need the existing commission commands if those lessons must return.
- Marketing landing now uses owned Global Academy photos from https://globalspokenenglish.com in place of Unsplash tiles. Legacy blog/news images were not imported.
- Sign-in page now has a Home close control back to the marketing landing.
- After the 13 August restore, owner-provided passwords for the two username accounts were reset through `PasswordCredentialService::resetPassword` (argon2id). The secrets are not stored in the repository.

## APP-0068 — Neural listen voice + playable recording — 14 August 2026

- Warm-up, Listen, Observe and Rehearse now call `POST /conversation-sessions/{id}/speech` so Azure `en-IN-NeerjaNeural` speaks those lines. The phone `speechSynthesis` voice is no longer the product path.
- Azure SSML uses a slightly slower `prosody rate="0.92"` for a more natural read. Friend replies already used neural WAV when TTS succeeded.
- Microphone capture records PCM and reviews a WAV. The previous MediaRecorder blob could look recorded but play as silence.
- OpenAPI `0.32.0`.

## APP-0068 — Soft-launch voice-first learner STT — 14 August 2026

- APO stated typed text is only on request and everything else should be voice based.
- Conversation and Role-play default to speaking. Type instead reveals the text box.
- Warm-up, Listen, Observe, Rehearse and Friend replies auto-play spoken English. Transcript correction typing is behind Type instead.
- Listen/warmup/Role-play model lines use Azure neural WAV (`POST /conversation-sessions/{id}/speech`). Device `speechSynthesis` is not the product voice.
- Microphone capture records PCM and reviews a WAV so local playback is audible.
- Soft-launch may bind Azure recorded STT for the Direct organization allowlist. Production stays off.
- Live enablement is invite-only Direct (`VOICE_ALLOWED_ORGANIZATION_IDS` for `01KYS97G7MX374P303XJHDXT72`) on the APP-0064 Speech resource. Production/Play `.env.example` remains `VOICE_UPLOAD_ENABLED=false`.
- Live TTS→STT roundtrip on the same Speech resource returned a non-empty English transcript (`stt_contains_market: true`, confidence 9936 bps). A consented browser-microphone learner upload was not captured.
- Microphone capture now stops Friend/listen playback before `getUserMedia`, retries a busy mic once, and accepts browser MIME suffixes such as `audio/mp4;codecs=mp4a.40.2`.

## APP-0067 — Named Kannada review of authored Levels 2–17 — 14 August 2026

- The Accountable Product Owner stated `Level 2-17 Kannada approved.`
- Direct drafts are revision 4 with APP-0067 Kannada review metadata (evidence `docs/evidence/lpl-01/levels-2-17-kannada-review-result.json`; 16 levels; `kannada_independent_review: true`). Publication remains blocked by `curriculum_publication_gate`.
- Kannada UI catalogue is still `pending-independent-review`. Role-play remains `synthetic_unreviewed`.

## Honest listen-then-type copy + Kannada UI preview — 14 August 2026

- Soft-launch Caddy was serving an ordinary `approved-only` `dist/`, so choosing Kannada still showed English chrome. `scripts/run-soft-launch.sh` now builds with `VITE_UI_TRANSLATION_PREVIEW=true` and stamps `apps/web/dist/.soft-launch-ui-preview`. Production/Play builds stay approved-only (D-0210).
- Conversation / Role-play / marketing no longer advertise microphone speaking as available. That honesty copy was later superseded for invite-only Direct by APP-0068 voice-first STT; production/Play remain off.
- Kannada UI catalogue remains `pending-independent-review`. Named Kannada *lesson* review of Levels 2–17 `kannada_summary` is recorded under APP-0067.

## APP-0066 — Named English academic review of authored Levels 2–17 — 14 August 2026

- The Accountable Product Owner stated `Levels 2–17 English approved`.
- Direct drafts are revision 3 with APP-0066 English review metadata (evidence `docs/evidence/lpl-01/levels-2-17-english-review-result.json`). Kannada `independent_review` remains false. Publication remains blocked.

## APP-0065 — Authored levels, Role-play scripts, 1:1 request — 14 August 2026

- Levels 2–17 lesson text is authored in `AuthoredFoundationLessons` (not APP-0063 templates). Live Direct drafts were replaced on 14 August 2026 (16 levels, revision 2; evidence `docs/evidence/lpl-01/levels-2-17-authored-replace-result.json`). Named English review is recorded under APP-0066; Kannada independent review remains outstanding.
- Role-play still discloses `synthetic_unreviewed`. Each of the 12 scenarios now has Observe model lines, Rehearse chunks and a Perform prompt.
- Marketing no longer says “hire”. Signed-in learners submit `/me/tuition-requests`; operators with `learners.read` see and acknowledge the queue. No payments, calendar or trainer marketplace.
- Privacy inventory is `learner-privacy-v5` (tuition request text is redacted on erasure). OpenAPI `0.32.0`.

## Release-gate repair — 14 August 2026

- Four sessions are ordered in the UI and API (`session_locked`); Practice complete requires a passing check.
- Level-bound AI Tutor hands the created session into Confidence Conversation instead of starting a second generic Friend.
- Authenticated Home shows the daily mission; Progress shows the levels path.
- Role-play Perform requires a typed learner turn; reflect no longer shows evidence digests.
- Practice tabs are semantic `role="tab"` / `tabpanel`. Sign-in username uses a Chromium-valid pattern, duplicate `#sign-in` IDs removed, header Sign in focuses the form.
- Missing `operator.password.confirm_hint` restored. Kannada production fallback notice is explicit; catalogue is not marked approved.

## Learner marketing four-session path + pedagogy — 14 August 2026

- Level session HTTP hosts no longer call `crypto.randomUUID()` (plain LAN/static-IP HTTP). English UI shows English lesson copy; Kannada stays behind a disclosure.
- New “What you get here” tiles: hybrid AI + human courses, in-app 1:1 tuition, one path with two kinds of support. No competitor names.
- Licensed Unsplash photographs in `apps/web/public/marketing/features/` with attribution.
- Web chrome uses `logo-web.png`; native Android uses `logo-android.png` (APP lockup).
- Guest primary Learn/Practice/Progress nav is hidden until sign-in.

## Learner UI locale + chrome polish — 14 August 2026

- `INTERFACE_LOCALES` = kn-IN + en-IN (other Phase-1 catalogues retained for API/tests only).
- Marketing landing fully localized; sign-in lives in the join section.
- Level path / practice surface strings move through localization catalogues.
- Design tokens: smaller `--ge-font-size-*` / hero sizes; quieter practice tabs + landing motion.

## Soft-launch guidance TTS + Role-play catalog — 14 August 2026

- APO exact instruction authorized Kannada+English guidance TTS under soft-launch
  and ≥10 additional common Role-plays (APP-0064 / D-0205).
- Soft-launch DI binds `SoftLaunchGuidanceAzureTextToSpeechProvider` when
  `CONVERSATION_GUIDANCE_TTS_LIVE_REQUESTS_ENABLED=true`; failures fall back to
  text-first. Turn speech may include model/guidance WAV base64.
- Role-play catalog: 12 scenarios (clinic + 11 common). Seed via FoundationSeeder
  or `php artisan conversation:seed-roleplay-catalog`.
- Production `CONVERSATION_PROVIDER_DRIVER=disabled` and guidance TTS off posture
  unchanged for real production promote / Play.

## Live Azure conversation enablement — 14 August 2026

- APO instructed: enable Azure under ₹500 and stop using fake conversation
  providers for the active non-production stack.
- Driver: `azure-openai-dev`; kill-switch on; spend evidence APP-0022
  `cap_inr=500`; `LIVE_AUTHORIZATION.txt` set to the ₹500 phrase.
- Verified as `northstar`: conversation-session **201**, turn **201**,
  `priority_correction.explanation_locale=kn-IN` with Kannada
  `explanation_l1`; clinic reception role-play session **201**.
- Speech for ordinary Confidence Conversation now uses soft-launch guidance TTS
  under APP-0064 when enabled (`SoftLaunchGuidanceAzureTextToSpeechProvider`),
  with text-first fallback. Confirmed-text STT remains; full learner STT DI is
  still separately gated.
- Production `CONVERSATION_PROVIDER_DRIVER=disabled` posture unchanged for
  real production promote / Play.

## Levels 2–17 private auto-commission — 14 August 2026

- Accountable Product Owner authorized APP-0063 / DR-0034 / D-0204:
  auto-create blueprints, provisional English + Kannada approvals, and attach
  four APP-0062 `lesson_audio` parts per level for Direct private pilot use.
- Evidence:
  [levels-2-17-private-commission-result.json](evidence/lpl-01/levels-2-17-private-commission-result.json)
  (`levels_commissioned: 16`; Level 1 already attached under APP-0061).
- Learner delivery verification: all 17 levels `available`.
- Quality debt (must fix before publication): template objectives/checks,
  provisional Kannada summaries, placeholder metadata transcripts where
  needed, Level 15 Practice reuses the combined Introduction/Practice asset.
- Curriculum publication and production/public release remain unauthorized.

## Closed checkpoint — PR-011 evaluation harness and memory controls

- Accepted under [APP-0020](APPROVALS.md#app-0020) at
  `220628f4d4c69284af0b1f2534e8902f3b272088` with recorded limitations
  ([PR-011_APO_ACCEPTANCE_PACKAGE.md](PR-011_APO_ACCEPTANCE_PACKAGE.md)).
- Delivered: learner-visible `/me/ai-memory` CRUD + audit; Practice
  `AiMemoryPanel`; context allowlist caps (5×200); hash-locked
  `ai:evaluate` suite with human-review export/import and zero-cost fake
  comparison report; D-0090 / D-0091.
- Branch artifact: `codex/pr-011-evaluation-memory`. Real model providers,
  auto-ingest of conversation into memory, production and Play remain
  unauthorized by that acceptance.
- Re-verified on 14 August 2026 on `codex/confidence-conversation`:
  `LearnerMemoryTest` + `AiEvaluationRunnerTest` (11 passed),
  `AiMemoryPanel` vitest (2 passed), `artisan ai:evaluate`
  (`passed_count=15`, `estimated_cost_microunits=0`).

## Legacy teaching-audio inventory — 14 August 2026

- Owner-designated legacy admin was used read-only to inventory all 17 Audio
  lessons. Credentials and object URLs are not stored in the repository.
- Hash-only evidence:
  [levels-1-17-teaching-audio-hash-inventory.json](evidence/lpl-01/levels-1-17-teaching-audio-hash-inventory.json)
  — 67 unique MP3s / ~300 MB; Level 1 matches APP-0047 exactly.
- Bytes remain in ephemeral local `/tmp` staging only. No Git binary commit of
  media was performed.
- [DR-0033](DR-0033_LEVELS_2_17_TEACHING_AUDIO_PROCESSING_REQUEST.md) Option A
  is accepted under APP-0062 / D-0203. Exact-hash private non-production
  `lesson_audio` ingest completed for the inventory: 4 Level 1 hashes skipped
  (already present under APP-0047), 63 new assets accepted, 63 APP-0062 Direct
  pilot rights confirmations recorded. DB verification: 67/67 inventory hashes
  present as `audio/mpeg` `lesson_audio` with approved program-scoped rights on
  program `01KZNK3BWD8A8NJ5A5JW0ZVZMT`.
- Ingest evidence (no credentials, no media bytes):
  [levels-2-17-lesson-audio-ingest-result.json](evidence/lpl-01/levels-2-17-lesson-audio-ingest-result.json).
- Attachment, publication, production/public release, and Assignment audio
  remain excluded until their own gates.

## Level 1 private four-session attachment — 14 August 2026

- Under APP-0061, Direct Level 1 concept `can` is now a private unpublished
  draft with English + Kannada reviews approved and all four APP-0047
  `lesson_audio` assets attached.
- Evidence:
  [level1-private-attachment-result.json](evidence/lpl-01/level1-private-attachment-result.json),
  [level1-attachment-readiness.json](evidence/lpl-01/level1-attachment-readiness.json)
  (`state: private_draft_attached`).
- Learner delivery reports Level 1 `available` with four sessions; Levels 2–17
  remain `commissioning_pending` until blueprints/reviews exist.
- Private pilot delivery now streams lesson audio from
  `GET /api/v1/me/levels/{n}/sessions/{part}/audio` (session-scoped, not
  enrollment/mission media gates) and starts bound free-speech / AI Tutor
  sessions when `CONVERSATION_PROVIDER_DRIVER=fake` in local. Verified as
  learner `northstar`: audio HTTP 200 / playable MPEG; conversation-sessions
  HTTP 201; UI handoff to Confidence Conversation.
- Curriculum publication and production/public release remain unauthorized.

## All-levels four-session learner journey — 14 August 2026

- Product-owner accepted DR-0032 **Option A** under APP-0061 / D-0201 /
  ADR-0025 and authorized a single continuous implementation stroke.
- Master plan version **3.6** records Introduction → Practice → Conversation →
  Interaction for every configured level.
- Implemented in this stroke:
  - operator `lesson-practice` blueprint create/review/attach APIs;
  - learner `/me/levels` catalog and session delivery with deterministic Check
    scoring and participation completions;
  - Levels 1–17 shells (`commissioning_pending` until attached);
  - Practice UI `LevelPathPanel` as the default practice surface;
  - OpenAPI `0.30.0` + generated contracts;
  - SQLite and PostgreSQL `LevelSessionDeliveryTest` (3 tests / 12 assertions);
  - web `LevelPathPanel` vitest.
- Blanket development waiver does **not** invent Levels 2–17 media, authorize
  production/public release, AI mastery, auto-memory ingest or removal of
  tenant/security controls. Exact Level 1 asset attachment in the live Direct
  database and curriculum publication remain separate operator actions.

## One-login role-group operator authorization — 14 August 2026

- The product owner approved the immediately preceding one-login proposal and
  expressly asked to remove the repeated enterprise-style authentication
  ceremony in favor of username/password and role-based groups. APP-0060,
  D-0193 and ADR-0023 record the exact contextual approval and accepted active-
  session risk; master-plan version 3.5 is canonical.
- Routine learner/enrollment, branch/cohort, trainer/appointment, content,
  publication, Media Library, transcription reconciliation, privacy and
  session-revocation routes no longer use `auth.password-confirmed`. Every
  route remains first-party Web only, selected-organization scoped and subject
  to exact command-time role/permission and resource checks.
- Nine administrator-authority routes retain current-password confirmation:
  staff invitation issue/revocation, role-group create/update/retire, staff
  role assignment/revocation and staff membership activation/deactivation.
  The two platform account-recovery routes retain their exact capability plus
  fresh confirmation. Credential changes still verify the current password.
- The user-facing **Username and password security** surface is now the smaller
  optional **Account settings** surface. It says routine work needs no further
  password check and reserves its confirmation control for administrator-
  access changes.
- The completed `lpl01-reconciliation.html` aid and its special Caddy handling
  are removed. The soft-launch contract now fails if that page or handler is
  restored. Future campaigns belong in the ordinary authenticated Media/
  Content workflow; the completed campaign has no retained candidate to show.
- OpenAPI `0.29.0`, generated clients, UI catalogue revision
  `ui-*-2026-08-14.10`, the administrator guide and governance matrix match the
  new boundary. No migration, schema, credential, role assignment, tenant data,
  provider call or deployment was performed.
- Required gates pass: `make verify` (177 SQLite tests / 1,935 assertions; 315
  PostgreSQL tests / 4,469 assertions; 282 package/Web tests plus static,
  contract, locale and audit checks), `make e2e` (4 reconciliation API tests /
  103 assertions and 21 Chromium journeys) and `make build`. `make ai-eval`
  was not required because no prompt, model, provider or orchestration changed.

## LPL-01 human transcript reconciliation — 14 August 2026

- The authorized Direct operator completed the private HTTPS workflow for
  campaign `01KZP5129TNAQPE542XM15604C`, listening to each complete exact
  asset, inspecting every returned segment and selecting `confirmed` for
  Introduction, Practice, Conversation and Interaction.
- The four append-only reconciliations created these English metadata
  revisions and content hashes without placing transcript text in repository
  evidence:

| Part | Metadata revision | Content hash |
|---|---|---|
| Introduction | `01KZZB3DJFKAN8BQD30K6WJGV6` | `2e8335f5998dace0a619acbd24945beafbb3b9a2e427bd71dd01f473b3b17e0b` |
| Practice | `01KZZBHZXE93YYHPGNKFJZ8EBY` | `a185249064c4e153237e1a54f8375fe1e7e4fac2ce45525684a929ea174a6298` |
| Conversation | `01KZZBPQSS5ECMMGEBGAA2W3M4` | `830bba76c97bb19d3b132da8bd7a021cb5160be146592cfffcaaaa810bc48635` |
| Interaction | `01KZZC03NX6QGCWGMD9WX1AH1D` | `5db31dd35f5d9fe1f0d02fc116e2793f718bf6b4cff1dde00c25c93ec8ba2d37` |

- The scheduled retention worker produced four completed candidate-deletion
  receipts, each on its first deletion attempt. The campaign moved from
  `provider_complete` to `completed` at 14 August 2026 05:35:20 UTC with zero
  retry/dead-letter backlog. Its existing finite usage evidence remains four
  successful provider attempts, zero failures/retries, 18,760,190 uploaded
  bytes, 1,172,513 billable milliseconds and ₹11.189394 estimated usage.
- This establishes metadata-author reconciliation only. It is not English
  academic approval, Kannada review, blueprint validation, publication or
  LPL-01 completion. No LPL-02 work is authorized.
- The 19-minute-32.513-second workflow also proved that the existing separate
  five-minute password confirmation for candidate read and submission creates
  repeated same-factor prompts. [DR-0031](DR-0031_LPL01_RECONCILIATION_AUTHENTICATION_SIMPLIFICATION_REQUEST.md)
  recorded the bounded one-login design choice. Opening the request changed no
  behavior; APP-0060 / ADR-0023 subsequently resolved and broadened it as
  recorded above.

## LPL-01 local evidence manifest — 14 August 2026

- The exact four-part media bindings are represented in the local review-only
  manifest at `docs/evidence/lpl-01/level1-source-snapshot-manifest.json` and
  the ordered shell at `docs/evidence/lpl-01/level1-blueprint-draft.json`.
- This is **not** claimed as the typed source snapshot: its rich-text field
  list is empty until an exact read-only source export is supplied. It
  contains no raw HTML, transcript text, Assignment audio or learner data.
- `scripts/validate-lpl01-evidence.mjs` deterministically verifies the four
  source order positions, all four metadata revision/content hashes, the
  blocked publication state and the excluded-media boundary. It passes.
- No legacy-site request, database write, migration, curriculum attachment,
  publication, review approval or LPL-02 work occurred. LPL-01 remains blocked
  on the exact source body and all downstream content/review gates.

## Local administrator setup transition — 13 August 2026

- A fresh gzip-valid PostgreSQL backup was written outside the repository at
  `~/ge-soft-launch/backups/soft-launch-20260813T060939Z.sql.gz`, 138,142
  bytes, SHA-256
  `357714361f38ed162d63bbed53513946fba47af3cb7218a80b54b91d33791b31`.
  Its inherited `0664` mode was corrected to owner-only `0600`; the backup
  command now sets `umask 077` so later snapshots are private at creation.
- Content-free preflight reconfirmed 16 active users, two credential-ready
  users, 14 append-only exclusions and one `waiting_delay` case with two
  approvals. The target remains active, credential-unready and backed by one
  verified recovery contact. Its manifest owner is active, credential-ready,
  was the original case requester and already belonged to the reviewed legacy
  actor set.
- Migration `2026_08_13_000200_create_unified_account_recovery.php` ran as
  batch 8. The old case is now `superseded` with reason
  `unified_account_recovery`; both historical approvals remain. No row was
  deleted or rewritten into a successful recovery.
- Only the exact manifest owner is configured locally through
  `PLATFORM_ACCOUNT_RECOVERY_MANAGER_USER_IDS`; the retired two-actor setting
  was removed. No role, permission, membership, appointment, enrollment or
  content authority changed.
- A loopback-only API/Web ceremony was opened for ten minutes. No manager
  password-confirmed session appeared, so the service correctly created zero
  account-recovery management events and zero `account_setup` challenges. No
  token, username or password was generated, displayed or inspected. The
  temporary target-bound page was removed and the managed soft-launch service
  restored healthy on its existing temporary HTTPS origin.
- A later human retry signed in as the exact designated manager but entered an
  incorrect current password in Account Security. The fresh-confirmation guard
  failed closed with zero recovery evidence, while exposing a misleading HTTP
  500 status. The error contract now returns an explicit safe HTTP 403
  `fresh-password-confirmation-required` problem; SQLite and PostgreSQL feature
  regressions prove denial creates no management event or setup challenge and
  that correct confirmation still permits exactly one protected setup.
- The manager then completed a valid same-password confirmation and authorized
  exactly one setup. The target chose the username/password through the
  protected-local page; one accepted challenge was consumed, the owner-only
  delivery was deleted and normal password sign-in loaded the existing
  operator workspace. Codex never read the token, contact, username or
  password. The before/after tenant-authority SHA-256 is identical at
  `d7ed3f404a5a986f7199882a38454563af6999eee69a0fd3534fe2fabd557cf6`.
- The protected-local metadata GET initially failed 401 because the page's
  `no-referrer` policy removed Sanctum's stateful same-origin signal. The page
  now uses `same-origin` only; public fragments are erased before the request
  and no referrer is sent cross-origin. A browser regression covers the exact
  request and the existing memory-only fragment behavior.
- The migration reconciliation switch is disabled, the empty protected
  delivery directory is removed and the managed soft-launch service is healthy.
  A subsequent managed-HTTPS sign-in and current-password confirmation both
  succeeded with the current credential version before privileged work resumed.
- Final `make verify` passes against this exact state: 177 SQLite tests /
  1,934 assertions, 315 PostgreSQL tests / 4,477 assertions and 285
  JavaScript tests, plus audits, Pint, PHPStan, localization, OpenAPI/generated
  clients, release/runtime contracts, lint and typecheck.

## AUTH-SIMPLE-01 repository closure — 13 August 2026

- One global username/password sign-in now presents the website-inspired split
  login layout without importing the legacy site's phone, Google or remember-
  me credential behavior. Role, organization and enrollment are still resolved
  only after authentication.
- Learners and privileged users share the same enumeration-safe, 256-bit,
  15-minute, single-use verified-contact recovery lifecycle. Successful reset
  globally revokes Web sessions and Android token families.
- One freshly password-confirmed configured actor with exactly
  `platform.account_recovery.manage` can issue a migration-exclusion setup link
  or replace a lost verified contact after bounded external evidence. The actor
  never receives a token or chooses target credentials; contact replacement
  globally revokes credentials and records content-free append-only evidence.
- Migration `2026_08_13_000200_create_unified_account_recovery.php` preserves
  old case/approval rows, supersedes incomplete exceptional cases, adds the new
  management/notification ledgers and fails rollback closed after new evidence.
  A PostgreSQL migration test proves a live waiting case and approval survive
  transition and that the permitted pre-evidence rollback restores the case and
  pending challenge.
- The two-actor/24-hour APIs, services, configuration authority and local
  commissioning page are absent from active runtime. Dedicated setup/recovery
  pages erase URL fragments immediately, retain tokens only in page memory,
  bypass service-worker caching and receive no-store/same-origin referrer
  protections.
- OpenAPI 0.28.1 and generated TypeScript clients match. Focused evidence is 11
  SQLite tests / 109 assertions, 10 PostgreSQL auth/migration tests / 101
  assertions and one PostgreSQL race test / 8 assertions. Complete verification
  passes 177 SQLite tests / 1,934 assertions and 315 PostgreSQL tests / 4,477
  assertions plus audits, formatting, PHPStan, locale/OpenAPI/generated-client,
  release/runtime, lint, type and 285 JavaScript tests. `make e2e` passes 4 API
  tests / 105 assertions and 20/20 Chromium journeys. `make build` passes with
  the retained Vite chunk-size advisory. The `global_english_test` logical
  backup/restore drill passes with matching migration/schema/deletion evidence
  and a measured two-second RTO.
- Production remains fail closed when the reviewed delivery or former-contact
  notification adapter is unavailable. The local administrator transition is
  recorded separately under D-0190; no production deployment/provider, tenant
  authority, learner record, AI/voice state, curriculum publication or Play
  release changed.
- Account-recovery manager actions are currently API/runbook operations; a
  dedicated React support console is not part of AUTH-SIMPLE-01.

## Stable static-IP HTTPS edge — 12 August 2026

- The runtime now supports `GE_SOFT_LAUNCH_STABLE_HOST` with exact host/origin
  validation, Secure-cookie/Sanctum convergence and a generated Caddy edge.
  WAN 80/443 are intentionally mapped to unprivileged host ports 8080/8443;
  the stable edge proxies only to loopback port 9080.
- Stable mode disables rotating Quick-Tunnel startup and changes systemd
  supervision to require only API, worker, scheduler, loopback Caddy and the
  stable public edge. HTTP redirects to the exact HTTPS host; Caddy manages the
  public certificate and emits HSTS, nosniff, frame-denial and strict-origin
  referrer headers.
- `bash -n`, the soft-launch environment contract and Caddy validation pass.
  Complete `make verify` also passes: 177 SQLite tests / 1,928 assertions, 314
  PostgreSQL tests / 4,472 assertions and 285 JavaScript tests plus audits,
  formatting, PHPStan, locale/OpenAPI/generated-client/release/runtime checks,
  lint and typecheck. `make build` passes with the retained measured Vite
  chunk-size advisory. The configured-host contract proves exact APP_URL,
  mode-0600 environment, stable routing, public-certificate policy and
  rejection of mismatched origins.
- Read-only network evidence found Cloudflare authoritative DNS and verified
  the host's expected public IPv4 address, but `ai.globalspokenenglish.com`
  currently has no A record. The live runtime was deliberately not switched,
  avoiding certificate retries and closed-test downtime before the DNS/router
  prerequisites exist.
- Activation is explicitly deferred to 13 August 2026 because authorized
  Cloudflare credentials are unavailable. No credential is to be supplied in
  chat, no DNS bypass is permitted and no completion is claimed.
- No schema, API, credential, tenant authority, provider call, content or
  Android package changed. Stable hosting closes only the rotating-origin gap;
  production signing/Play, independent reviews, reviewed L1 content, provider
  gates and remaining physical-device evidence stay open.

## Levels 1–17 accelerated governed rollout approval — 12 August 2026

- Read-only PostgreSQL and governed-inventory inspection confirms that Levels
  1–17 are not ready or live as a reviewed AI pathway. There are zero governed
  level rows; two duplicate staging-smoke imports each contain only 12 numbered
  video modules plus a scaffold and one generic mission.
- Current numbered media omits Levels 4, 5, 12, 15 and 16. All levels lack the
  complete transcript/objective/quiz/rationale/Talk/Apply/review evidence
  required for a learning loop. AI scenarios are development-only and
  synthetic-unreviewed; live model and ordinary learner Speech remain gated.
- [DR-0029](DR-0029_LEVELS_1_17_LIVE_ACTIVATION_REQUEST.md) Option A is
  accepted exactly under APP-0058 / D-0184. LPL-01 is the only active content
  checkpoint; Level 1, Levels 2–3 and later source-complete levels remain
  ordered reviewed waves rather than a blanket activation.
- Approval changed no publication, enrollment, provider, permission, rights,
  content or learner record. Missing content cannot be fabricated and every
  later provider/publication/device/release gate remains intact.

## Unified production login and recovery decision stop — 13 August 2026

> Resolved by DR-0030 Option A under APP-0059 / D-0186 / ADR-0022.

- The product owner requested the same simple authentication model in
  production: one username/password sign-in for every role, role-based access
  after authentication and student tiers represented by enrollment rather
  than separate login types.
- The live DR-0027 recovery page exposed an environment mismatch before any
  mutation: password confirmation succeeded, but the protected recovery route
  remained restricted to `local`/`testing` while the managed service runs as
  `soft-launch`. The case remains `waiting_delay`; no challenge, username or
  password credential was created.
- [DR-0030](DR-0030_UNIFIED_ROLE_BASED_LOGIN_AND_RECOVERY_REQUEST.md) records
  the requested production simplification. Option A replaces privileged
  recovery actors and the 24-hour delay with the same verified-contact,
  15-minute single-use setup/recovery link for every role while retaining
  secure sessions, hashing, revocation, tenancy and audit controls.
- APP-0059 now authorizes only the bounded AUTH-SIMPLE-01 implementation: the
  same verified-contact setup/recovery for every role, historical preservation
  of old case evidence, one exact platform lost-contact capability and the
  existing independent tenant/permission checks. Production delivery and
  independent review remain gated.

## Closed-group Web and Android test pack — 11 August 2026

- The enabled `global-english-soft-launch.service` foreground user unit owns the API,
  queue worker, scheduler, loopback web proxy, port-8080 redirect and Cloudflare
  Quick Tunnel, has user lingering enabled for reboot startup, monitors all six
  children and restarts after a child or repeated health failure. All six
  processes are running. The external readiness endpoint returns `ready`, and
  the external authentication-mode endpoint returns exactly `password_only`.
- `make android-closed-beta` passed against the current HTTPS origin and the
  existing public Passport PKCE client. The resulting package is
  `com.globalenglish.app`, version `1.0 (1)`, target SDK 36, v2 debug-signed for
  controlled sideload testing. It contains the HTTPS Quick-Tunnel origin and
  contains neither the static HTTP origin nor the demo package identifier.
- The protected artifact is
  `~/ge-soft-launch/artifacts/closed-group/global-spoken-english-closed-group-20260812-r4.apk`,
  8,461,998 bytes, SHA-256
  `d3e55fe4790cb4e7fd7cebf428fb6818f3c419df849f877039e550669366f448`.
  No unauthenticated APK download route is enabled.
- Password-only invited learner self-setup is implemented as one atomic
  invitation + self-chosen global username/password operation. It creates no
  browser-stored credential and leaves no partial user, credential, membership
  or enrollment on failure. The operator never chooses or receives the
  tester's password.
- The complete tester procedure and Learn / Practice / Progress evidence
  checklist are in the root [closed-group runbook](../CLOSED_GROUP_TEST_RUNBOOK.md).
- The DR-0027 case remains `waiting_delay` until 12 August 2026 10:44:26 UTC /
  16:14:26 IST. It was not bypassed. Its impact is limited to the excluded
  organization-administrator account and issuing new invitations through that
  account; the two credential-ready learner accounts can test now.
- The Quick Tunnel has no uptime or stable-hostname guarantee. A hostname
  rotation requires an APK rebuild and redistribution. A named tunnel or
  product HTTPS domain is still recommended once DNS authority is supplied.
- The current 12 August hostname rotation has been closed for the staged artifact:
  JDK 21 `make android-closed-beta` passed 103 Gradle tasks, the package embeds
  only the current HTTPS origin and active public PKCE client, and APK Signature
  Scheme v2 verification passed. Revision 4 installed successfully on the
  attached Realme 8 (Android 13); final authenticated return and DM-01/DM-02
  evidence remain open.
- Complete repository verification passes: 177 SQLite tests / 1,928
  assertions, 314 PostgreSQL tests / 4,473 assertions and 285 JavaScript tests,
  plus dependency audits, Pint, PHPStan, localization/OpenAPI/release/runtime
  contracts, ESLint and TypeScript.
- LPL-01 publication, independent review, physical-device evidence,
  production signing, VPS migration and public/Google Play authorization all
  remain open. No production or Play claim is made.
- The ordered evidence and authority gates are tracked in the
  [production readiness closure matrix](PRODUCTION_READINESS_CLOSURE_MATRIX.md).

## APP-0053 Conversation Learning Flow closure

- The soft-launch Friend turn now uses confirmed learner text for the STT-stage
  contract, the allowlisted Azure OpenAI adapter for model generation when its
  external evidence/secret/kill switch pass, and explicit client text-first /
  on-device speech playback. The cost ledger accepts only those exact
  stage/provider combinations outside local/testing; local/testing remains
  deterministic fake-only.
- Confidence Chat, Skill Focus and Review share the Friend engine but have
  distinct immutable scenario snapshots. Skill Focus defaults to Tutor with a
  five-turn threshold; Confidence Chat uses four turns and Review uses two.
  The existing Observe → Rehearse → Perform → Reflect Role-play remains the
  fourth session type.
- Product copy no longer claims the ordinary soft-launch path is a fake. It
  discloses that the partner is AI, feedback is advisory, marks/mastery are
  unchanged, turn evidence is content-free and optional voice retention is
  controlled by explicit consent. Local/demo copy remains synthetic.
- OpenAPI 0.24.0 and generated clients contain all Friend scenario keys and the
  live text-first speech/cost response contract. Migration `000180` narrowly
  permits the same provider/stage combinations in PostgreSQL.
- A repository-owned persistent user unit is enabled at
  `~/.config/systemd/user/global-english-soft-launch.service`; `Linger=yes`
  provides reboot startup. API, worker, scheduler, loopback Caddy, static-IP
  HTTP listener and Cloudflare tunnel are running. Readiness is 13 pass, six
  expected Azure-configuration warnings and zero failures.
- A pre-migration backup was written to
  `~/ge-soft-launch/backups/soft-launch-20260810T170110Z.sql.gz`; migration,
  idempotent foundation seed and production web build passed before service
  replacement.

The separate Azure OpenAI endpoint, South India regional deployment and
outside-repository secret are now installed. Readiness is 17 pass, one warning
and zero failures. The remaining warning is the intentionally disabled live
kill switch. A forced live-enabled no-call preflight returns exactly
`vendor_evidence_logging_enabled` because Azure has not granted modified abuse
monitoring and the resource does not expose `ContentLogging=false`. No model
request or model-token spend occurred.
ADR-0007/0008 still withhold ordinary real learner-audio Azure STT activation.
The Azure Speech synthetic-benchmark environment and one active credential are
already present outside the repository with mode `0600`. Its completed
5 August run passed all recorded gates; the repaired no-call wrapper now reports
the configured environment as `ready` instead of falsely reporting the
unloaded application defaults as blocked. This does not enable learner audio.
The product name is now **Global spoken English Course**. Caddy binds
`0.0.0.0:8080`; local root/readiness return HTTP 200 and `:8088` is closed.
The router forward must target this host's LAN address `192.168.1.3:8080`.
Public-IP testing from the same LAN times out, which may be missing NAT
loopback; external cellular/VPS verification and Ubuntu firewall inspection
remain required. The existing `https://www.globalspokenenglish.com` site was
not accessed or changed.
Production/Play release and independent review are not authorized.

## DR-0028 ordinary learner Speech activation decision stop — 12 August 2026

- The existing Azure Speech synthetic benchmark, consent ledger, private
  recording lifecycle and deletion reconciliation do not authorize ordinary
  learner audio. Ordinary recorded-speech DI and `VOICE_UPLOAD_ENABLED` remain
  fail closed.
- [DR-0028](DR-0028_ORDINARY_LEARNER_SPEECH_ACTIVATION_REQUEST.md) now proposes
  an exact two-learner Global English Direct pilot: Central India `en-IN` STT,
  English neural reply TTS, separate consent, transcript correction, ADR-0007
  retention/deletion, hard 20-recording/₹1,000 caps, independent reviews and
  distinct implementation versus live-smoke approvals.
- No Speech binding, configuration, schema, provider request, learner content
  processing or spend changed. Phase A implementation requires the exact APO
  statement in DR-0028; live Phase B remains separately gated.
- The Azure OpenAI resource was checked read-only on 12 August. It still
  exposes no `ContentLogging` capability, so modified-abuse-monitoring evidence
  is absent, the model kill switch remains false and no model call is allowed.

## Independent L1 review gate — 12 August 2026

- `make ai-eval-review-export REVIEW_PATH=<absolute-path>` now exports all 15
  hash-locked synthetic cases with the actual English reply, correction, L1
  explanation, safety/category context and a canonical per-row payload hash.
- `make ai-eval-human-review REVIEW_PATH=<absolute-path>` fails closed unless
  every exact output is accepted by an independently attributed, locale-
  matched reviewer with a valid role, instant and evidence reference. Changed
  suite or output hashes, missing/duplicate rows and pending/rejected rows are
  rejected.
- The mechanism is verified, but no bilingual human review is claimed. The
  protected packet must be completed under
  [the review runbook](L1_INDEPENDENT_REVIEW_RUNBOOK.md); the five Indic UI
  catalogues remain pending independent review and production still falls back
  to approved English.
- The byte-verified mode-0640 synthetic packet is staged at
  `~/ge-soft-launch/artifacts/review/friend-l1-review-20260812-47d5f2f1.json`:
  17,364 bytes, SHA-256
  `47d5f2f10fe0d0d6d8cdcb8ea57cf39e6dadf9b67c031aa2ea072cc61341a05d`.

## Audience documentation

The repository now includes three evidence-based audience guides:

- [Learner user guide](guides/LEARNER_USER_GUIDE.md) for sign-in, daily
  missions, offline use, private media, Confidence Conversation, voice/privacy
  choices, results and troubleshooting;
- [Administrator and operator user guide](guides/ADMINISTRATOR_USER_GUIDE.md)
  for privileged access, People, Learners, Content Studio, Media Library,
  operations, safety and governed lifecycle checklists; and
- [Sales feature and solution guide](SALES_FEATURE_GUIDE.md) for positioning,
  buyer use cases, feature status, responsible-AI boundaries, pilot discovery,
  claims guardrails and roadmap communication; and
- [Ubuntu soft-launch runbook](SOFT_LAUNCH_UBUNTU_RUNBOOK.md) for the invite-only
  real-product host under APP-0053.

Each guide distinguishes repository-implemented, controlled/conditional,
demonstration-only, planned and deferred behavior. They introduce no runtime,
API, schema, provider, permission, release or architecture change.

## Active learner-experience plan

[LESSON_PRACTICE_LOOP_PLAN.md](LESSON_PRACTICE_LOOP_PLAN.md) version 2.0
records the future-proof Learn → Talk → Check → Apply → Review platform plan.
It covers dynamic media analysis, governed lesson/assessment generation,
all-level commissioning, Skill Chat, deterministic mastery, personalization,
speech, trainer workflows, accessibility, offline delivery, analytics,
integrations and separately gated platform-evolution horizons. LPL-00–LPL-07
form the launch-critical core; LPL-08+ cannot be pulled forward silently. The
plan has completed LPL-00. [DR-0020](DR-0020_LESSON_PRACTICE_FOUNDATION_REQUEST.md)
Option A and [ADR-0015](adr/0015-lesson-practice-foundation.md) are accepted
under [APP-0046](APPROVALS.md#app-0046) / D-0140. The active bounded
[LPL-00 specification](LPL-00_SPEC.md) and locked
[verification scope](LPL-00_VERIFICATION_SCOPE.md) fix the exact schema,
scoring, speaking-evidence, answer-isolation and unlock policy. LPL-01 is now
active. Its [exact inventory](LPL-01_CONTENT_INVENTORY.md) found no current
governed set of four Level 1 audio lessons. A subsequent authenticated,
read-only [legacy source map](LPL-01_LEGACY_SOURCE_MAP.md) identified the
reported structure: one Level 1 `Can` source lesson with four ordered MP3 parts
(Introduction, Practice, Conversation and Interaction), inside a repeated
17-level × six-lesson architecture. APP-0047 now authorizes those four exact
hashes for bounded private non-production ingestion, transcription and
AI-assisted derivative drafting. DR-0021 Option A is now accepted under
[APP-0048](APPROVALS.md#app-0048) / D-0145 /
[ADR-0016](adr/0016-long-form-lesson-audio-media.md), and its bounded
`lesson_audio` Media Library prerequisite has passed repository verification,
E2E and build gates. The four authorized source files were reacquired into
temporary non-repository storage, matched byte-for-byte and hash-for-hash, and
passed the project-owned real ffprobe and ClamAV adapters. DR-0022 Option A and
APP-0049 bound one normal phone identity to the exact synthetic Direct
administrator. DR-0023 Option A then preserved the locked incident in a
validated snapshot, passed a strict expected-delta audit, restored the exact
pinned baseline, reran migrations/seed and replayed only that identity
binding. Ordinary OTP, exact membership and retained TOTP now work without a
support reset. Through explicit ffprobe/ClamAV adapters and the first-party
HTTP lifecycle, all four APP-0047 hashes are active private `lesson_audio`
assets with matching byte counts, durations and clean scans. Failed adapter
attempts remain failure evidence, and quarantine objects use the normal
reconciliation worker. DR-0024 Option A is accepted and executed under
[APP-0051](APPROVALS.md#app-0051), D-0150/D-0151 and
[ADR-0019](adr/0019-local-lpl-pilot-and-rights-authority-bootstrap.md). One
standalone private LPL pilot program now owns one least-privilege role and one
accepted program-scoped rights-confirmer appointment. Ordinary selected-
membership/fresh-TOTP HTTP commands created exactly four approved APP-0047
rights revisions for later gated web/Android delivery in India, derivatives
allowed, sharing false and no expiry. The persistent bootstrap switch is
absent. No lesson-audio metadata, attachment, transcript, curriculum version,
publication, enrollment or learner delivery was inferred. The next exact stop
was accepted as
[DR-0025](DR-0025_LPL01_TRANSCRIPTION_COMMISSIONING_REQUEST.md) Option A under
[APP-0052](APPROVALS.md#app-0052), D-0153 and
[ADR-0020](adr/0020-governed-curriculum-media-transcription-commissioning.md). It fixes
a separate provider-neutral curriculum-media path using direct exact-MP3 Azure
Fast Transcription, encrypted expiring candidates, immutable content-free
provenance and a first-party human reconciliation command. Its fixed one-
campaign caps are four assets, eight HTTP attempts, 37,520,380 uploaded bytes,
2,345,026 billable input milliseconds and ₹100 estimated Azure usage. The
bounded repository implementation is now complete and verified under D-0154:
it adds exact campaign/attempt/event/reconciliation provenance, an authorized
Azure adapter behind the project port, encrypted expiring candidates,
content-free retention/dead-letter processing, a private fresh-TOTP
reconciliation API and safe external reports. OpenAPI 0.23.0 and generated
clients changed with that API. Ordinary DI remains disabled and no live
credential, provider call, candidate, transcript or metadata revision was
created. A subsequent D-0157 live-preflight audit applied the additive
commissioning migration only after a content-free PostgreSQL backup, captured
same-day official price/FX evidence and projected ₹22.378788 maximum spend.
That preflight dispatch still failed closed: prior evidence identified the contracting-entity
review only by an opaque reference, and Azure security defaults require
interactive reauthentication before the exact billing entity or current key
could be verified. No campaign permit existed and database/provider counts
remained zero at that historical stop. The subsequently authorized exact
campaign completed with four successful attempts, zero failures/retries,
18,760,190 uploaded bytes, 1,172,513 billable input milliseconds and
₹11.189394 estimated usage. Under D-0191 the authorized operator then listened
to every complete exact asset, inspected every returned segment and confirmed
all four candidates into exact English metadata revisions. The retention
worker completed all four encrypted-candidate deletions on their first
attempt, with no retry/dead-letter backlog, and the campaign is now
`completed`.
The current local smoke data still has only one unique Level 1 MP4
with no transcript/topic objective and derivative use set to false. The
[Level 1 review packet](LPL-01_LEVEL1_BLUEPRINT_REVIEW_PACKET.md) therefore
remains blocked and unapproved pending the typed source snapshot, blueprint,
English academic review and Kannada creation/review; no source text was
promoted into an answer key and no LPL practice runtime code exists yet. Under
[APP-0053](APPROVALS.md#app-0053) LPL-01 remains the active critical path while
Ubuntu invite-only soft launch is active. The product owner's live-AI and
repository-media allowance applies to bounded non-production soft-launch work
within the APP-0053 ₹10,000 Azure ceiling and existing privacy, tenancy,
review and release controls; it is not unlimited spend or Play/public release
authority.

D-0158 hardens the APP-0053 launch boundary after an operational review found
that the dedicated conversation env named by the runbook was not loaded by the
runtime/preflight and that syntactically valid setup placeholders could satisfy
parts of provider authorization. Runtime and preflight now share one precedence
contract: provider settings come from the dedicated conversation env while the
main soft-launch env owns `APP_ENV`, driver and kill switch. Setup preserves an
existing conversation env, new evidence scaffolds are unapproved/invalid, and
the provider rejects both old and new scaffold sentinels before HTTP. The live
host remained healthy through its HTTPS tunnel with two active publication
heads and two active enrollments; a fresh mode-`0600` gzip-valid database dump
was taken. Live tutoring remains off because current external vendor evidence,
endpoint, deployment and secret are still placeholders/missing. No provider
request or spend occurred.

## Current governance boundary

The approved ADMIN program is
[ADMIN_REFERENCE_PARITY_PLAN.md](ADMIN_REFERENCE_PARITY_PLAN.md), with the
implementation boundary frozen in
[ADMIN_GOVERNANCE_MATRIX.md](ADMIN_GOVERNANCE_MATRIX.md). ADMIN-SHELL-01
implements only the matrix's derived `O-READ` surface.

Delivered shell:

- `GET /operator/overview` derives organization metadata, navigation and panel
  keys from the current membership's existing content, audit and support read
  permissions without disclosing permission names;
- `GET /operator/search` federates fixed safe descriptor fields across only
  authorized, SQL organization-scoped sources with a bounded encrypted cursor
  tied to tenant, normalized query and source set;
- the existing audit listing now uses encrypted organization-bound
  `(occurred_at, id)` keyset continuation without changing its safe action or
  field allowlist;
- the responsive React workspace hides learner navigation, loads every panel
  independently, retries partial failures, keeps search ephemeral and provides
  keyboard-operable safe audit detail at 320 CSS pixels; and
- OpenAPI 0.17.0, generated clients and all six source-linked UI catalogues
  changed together. No migration, feature schema, permission seed, provider,
  environment variable or mutable domain behavior was added.

Delivered ADMIN-ORG slice:

- the permission-derived People workspace now covers safe current-organization
  metadata, branches, cohorts, staff memberships, code-only invitations,
  custom roles, derived trainer profiles, existing authority appointments and
  count-only membership-selected credential administration;
- mutations are web-only, fresh-TOTP, header-idempotent and version/fingerprint
  guarded, with tenant-scoped transactions, safe audit and content-minimized
  administration events;
- staff suspension strips current authority and only credentials still
  selected for that membership; reactivation restores no role, assignment,
  appointment, credential or authority;
- invitation secrets are show-once, terminal transitions advance version and
  the hourly maintenance path clears terminal digests, retries revoked web
  session storage cleanup and expires/purges bounded operational history; and
- OpenAPI 0.18.0, generated clients, six used permission definitions, all six
  source-linked UI catalogues and the responsive People journey changed
  together. Writable organization settings, learner administration and
  global identity/recovery authority were not added.

Delivered ADMIN-LEARNER slice:

- the manifest-gated Learners workspace provides bounded current-organization
  directory/detail, enrollment history and code-only learner invitation tasks
  with independent loading/retry, cursor pagination and safe server-derived
  actions;
- exact learner-safe invitation roles, attributed append-only current-goal
  correction, one versioned current enrollment, immutable verified curriculum
  pins, cohort-derived trainer summaries and reversible learner suspension are
  enforced transactionally under fresh TOTP and header idempotency;
- suspension revokes only credentials still selected for the target learner
  membership, retains learning/enrollment evidence and restores no credential,
  enrollment or pin on reactivation;
- the fixed privacy inventory is now `learner-privacy-v2`, including the
  current-goal projection, correction history and linked content-minimized
  administration events; and
- OpenAPI 0.19.0, generated clients, three used learner permissions, all six
  source-linked catalogues and the responsive Learners browser journey changed
  together. Support notes, global identity/account controls, learner exports,
  direct trainer assignment and enrollment reactivation were not added.

Delivered ADMIN-CONTENT slice:

- append-only current-organization workspaces now create programs, explicit
  graph-v2 levels through delivery bundles, immutable draft revisions,
  descriptor corrections, abandonment and same-owner successors while every
  existing graph-v1 publication remains byte-stable and immutable;
- typed governed-text and daily-speaking-mission adapters provide bounded
  English/Kannada authoring and private no-store preview without raw JSON,
  markup execution, public URLs or browser persistence;
- exact graph, English, Kannada and readiness queues derive safe blockers and
  semantic actions, retain reviewer/translator separation and appointment
  authority, and expose stable tenant-safe conflict codes;
- publication status and commands cover immediate publish, future scheduling,
  due activation, guarded cancellation, retirement and rollback with exact
  heads, evidence revalidation and idempotency; forced PostgreSQL races cover
  workspace, source, variant, review, lifecycle, permission and appointment
  overlap; and
- OpenAPI 0.20.0, generated clients, all six governed catalogues and the
  manifest-gated responsive Content Studio changed together under D-0137. No
  permission, grant, platform capability or media-management authority was
  added; media-dependent daily missions remain blocked until ADMIN-MEDIA-01.

Support-note writes, notices/messages, moderated group conversation,
provider-backed entitlement/payment work, platform configuration, legacy data
import and production/public release remain blocked by the exact retained
decisions in the matrix.

## Ordered remediation state

| Checkpoint | Repository state | Principal evidence |
|---|---|---|
| LV-01 | Complete | Draft-only, caller-idempotent legacy video import; no fabricated identity, authority, approval, rights or publication |
| PRIV-DEL-01 | Complete | Durable content-free deletion intent, retry-safe object deletion and exact privacy receipt links |
| AND-AUTH-01 | Repository implementation complete | One Android Passport transport, secure native refresh storage and single-use PKCE callback handling; physical device evidence remains external |
| MFA-01 | Repository implementation complete | RFC 6238 TOTP, one-use recovery, fresh scoped assertions and global credential revocation; independent production review remains open |
| PIPE-01 | Complete | Pinned dependency/security updates and green formatting/static/type/localization gates |
| VIDEO-01 | Complete | Verified bounded single-range delivery, constant-query evidence and bounded Android Blob fallback |
| PRIV-DSR-01 | Complete | Full fixed-class privacy inventory, deterministic erasure outcomes and shared voice deletion reconciliation |
| REL-01 | Complete | Fail-closed CI aggregate, bounded outbox dead letters, recovery commands and executable load/restore evidence |
| MAINT-01 | Complete | Shared request/test fixtures, self-bootstrapping isolated test storage, measured diagnostics indexes and current-status reconciliation pass the full gate |

No ADMIN feature schema is part of these remediation checkpoints.

## ADMIN program state

| Checkpoint | Repository state | Principal evidence |
|---|---|---|
| ADMIN-DISC-01 | Complete | Read-only reference capability inventory and approved safe parity specification |
| ADMIN-GOV-01 | Complete | Frozen governance matrix plus executable common operator-route policy; no feature schema or route |
| ADMIN-SHELL-01 | Complete | Permission-derived overview/navigation, independent panels, safe bounded search, encrypted audit continuation and accessible responsive drill-down |
| ADMIN-ORG-01 | Repository implementation complete | Staff-only tenant administration, strict delegation and final-manager boundaries, show-once invitations, reversible authority-stripping suspension, selected-credential revocation, bounded history/cleanup, OpenAPI 0.18.0 and accessible People UI |
| ADMIN-LEARNER-01 | Repository implementation complete | Bounded learner directory/detail, learner-safe invitations, append-only goal correction, versioned enrollment/pins, cohort-derived trainers, selected-credential lifecycle, OpenAPI 0.19.0 and accessible Learners UI; support notes remain excluded |
| ADMIN-CONTENT-01 | Repository implementation complete | Graph-v2 owner workspaces, typed authoring/preview, exact review/readiness queues, guarded publication lifecycle, OpenAPI 0.20.0 and accessible Content Studio under D-0136/D-0137; media management remains excluded |
| ADMIN-MEDIA-01 | Repository implementation and retained gates complete | Exact-asset private quarantine, inspection/scanning, accessibility metadata/review, appointed rights, immutable attachment/replacement, retirement and private preview; OpenAPI 0.21.0, generated contracts and Media Library UI; PostgreSQL races, full verify/E2E/build and backup/restore passed on 10 August 2026 |

## Current product and architecture boundary

### Identity, tenancy and privileged access

- Users and authentication records are platform-global; memberships, roles
  and domain resources remain organization-owned under ADR-0003.
- Web/PWA uses Sanctum cookie sessions. Production Android uses Passport
  Authorization Code with PKCE through the project authentication abstraction.
- Android access tokens remain memory-only; refresh credentials and pending
  PKCE state use the reviewed native secure store. Browser `localStorage`,
  IndexedDB and ordinary Capacitor preferences are not credential stores.
- Mutable operator actions are web-only and require fresh TOTP where specified.
  TOTP is not phishing resistant, and support-assisted reset is not yet an
  implemented product path.

### Learning, content and video

- Learning evidence, mastery, review and progression remain deterministic.
  AI paths cannot write authoritative learning records.
- Published curriculum and submitted evidence remain immutable/versioned.
  The canonical seed is synthetic and unpublished. The local staging-smoke
  legacy publications contain approved generic English/Kannada wrappers and
  learner-delivery rights, but those records do not approve a transcript,
  topic objective or lesson-practice blueprint and explicitly disallow
  derivative use.
- Lesson video delivery is exact-pin and current-rights gated. It supports one
  bounded byte range with a strong governed SHA-256 ETag. Browsers use native
  range loading; Android uses an authenticated, type/length checked Blob no
  larger than 20 MB.

### Conversation, voice and privacy

- Local/testing uses deterministic provider fakes. Production conversation
  and speech bindings remain fail closed unless their separately approved
  environment and evidence gates are satisfied.
- Learner audio upload requires the accepted ADR-0007 consent and retention
  path. Raw sensitive audio, transcripts, object keys and provider payloads
  are excluded from logs and operational health output.
- Voice source deletion records a durable tenant-owned receipt and content-free
  leased outbox event before storage I/O. Five failed attempts dead-letter the
  unpublished work without clearing the object key or completing the receipt.
- Privacy export emits the fixed `learner-privacy-v2` class inventory without
  silent row caps. Erasure records its pre-mutation outcome, removes mutable
  authorization/personalization, ends only the selected membership, revokes
  active credentials and retains explicitly justified immutable authority.

### Release enforcement

- CI has separate `verify`, `android` and `operations` jobs plus an `always()`
  `release-gate` that accepts only exact success from every dependency.
- The release-contract validator checks required commands, dependencies,
  action SHA pins and PostgreSQL digest pinning.
- Voice-deletion status is aggregate/content-free and fails non-zero for stale
  or dead-lettered work. Requeue requires the exact CLI event identifier after
  dependency remediation.
- The load harness fails for any non-200 or p95 threshold breach. The logical
  restore drill compares column schema, migrations and aggregate
  deletion/tombstone state without printing records.

Repository contract and local drill evidence is not a clean remote CI run,
branch-protection configuration, production capacity result or promotion.

## Authorization and next gate

[DR-0015](DR-0015_PRODUCTION_REVIEW_WAIVER_REQUEST.md) Option A is accepted
under [APP-0040](APPROVALS.md#app-0040) / D-0127. Ordered non-production
implementation, testing and staging may continue without repeated requests for
the retained legal or independent reviews. Missing review still cannot be
described as completed, passed or waived, and this authority does not permit
production or public release.

[ADR-0014](adr/0014-secure-reference-admin-functional-parity.md), the
[approved parity plan](ADMIN_REFERENCE_PARITY_PLAN.md) and accepted DR-0016
Option A authorized the now-complete ADMIN-ORG-01 repository slice. The
implementation remains limited to the organization/staff boundary in
[ADMIN-ORG-01_SPEC.md](ADMIN-ORG-01_SPEC.md); it does not authorize reference
code/data/media import, payment/provider spend, deployment or production.

ADMIN-LEARNER-01 has passed its repository exit gates under DR-0017 Option A,
[APP-0042](APPROVALS.md#app-0042) / D-0134 and
[ADMIN-LEARNER-01_SPEC.md](ADMIN-LEARNER-01_SPEC.md). ADMIN-CONTENT-01 has now
passed its repository exit gates under accepted
[DR-0018](DR-0018_ADMIN_CONTENT_AUTHORING_PUBLICATION_REQUEST.md) Option A,
[APP-0043](APPROVALS.md#app-0043), D-0136, D-0137 and the bounded
[specification](ADMIN-CONTENT-01_SPEC.md). The Accountable Product Owner has
now supplied the exact owner-level media-rights statement preserved under
[APP-0044](APPROVALS.md#app-0044), D-0138 and the
[rights attestation](ADMIN_MEDIA_RIGHTS_ATTESTATION.md). That statement
authorizes the next decision/specification step but does not automatically
approve an asset or bypass ADR-0004's exact-hash, use-scope, permission,
appointment and confirmation predicates. [DR-0019](DR-0019_ADMIN_MEDIA_LIBRARY_RIGHTS_LIFECYCLE_REQUEST.md)
is retained as the historical pre-implementation gate record. ADMIN-MEDIA-01
implementation and retained repository gates are now complete within its
accepted specification. Support-note tables, keys, routes and UI remain
blocked. LPL-00 is complete. LPL-01 has identified its legacy source, recorded
exact processing authorization, implemented the accepted long-form lesson-
audio prerequisite, passed the exact four-file real-adapter ingestion and
completed DR-0024's private pilot/runtime-rights authority. DR-0025 Option A is
accepted, and the bounded transcription/candidate/reconciliation repository
slice has passed its required gates under D-0154. LPL-01 is now stopped before
an exact contracting-entity/current-credential verification, campaign permit,
provider run and human transcript reconciliation. Same-day price/FX and the
isolated encrypted candidate-store path passed the D-0157 audit, but neither
can waive the missing gates. LPL-02 remains unauthorized.

## Verification state

Current LPL-01 evidence:

- read-only local PostgreSQL inventory found 3 programs, 27 lessons, 26 media
  rows and 2 local smoke publications, representing one synthetic unpublished
  lesson and two duplicate imports of 12 unique legacy level MP4s plus a
  synthetic scaffold;
- repository and gitignored artifact searches found no Level 1 audio lesson
  files; the retained legacy catalog records 17 Video rows, 12 MP4 downloads
  and missing video files for Levels 4, 5, 12, 15 and 16;
- authenticated read-only legacy inspection then reconciled one course, 19
  level descriptors and 102 numbered-level lesson rows: each Level 1–17 has
  Video, Audio, PDF/Text Book, Workshop, Group Practice and Assignment nodes;
  the Audio pages expose 67 MP3 references across all levels;
- Level 1 is explicitly `Can — I can speak in English`; its Audio node contains
  four ordered teaching MP3s. Ephemeral reads recorded exact SHA-256 and byte
  size for Introduction, Practice, Conversation and Interaction without
  retaining credentials, cookies or media in the repository;
- all 12 local MP4 SHA-256 values match the retained inventory; the exact
  private first-import Level 1 object was present and a fresh object-store
  read produced the governed SHA-256
  `8cc0ab3ceae5247e15b9feb17c2b9836d102a131b42232e6d74627b706edb5c6`;
- every imported level wrapper lacks transcript, can-do, target pattern,
  question, answer-rationale and Apply-rubric content; its only objective is
  the shared generic `legacy.level_video.orientation` objective;
- all local imported video rights rows allow web/Android learner delivery in
  India but disallow sharing and derivative use. APP-0047 separately
  authorizes only the four selected MP3 hashes for bounded private ingestion,
  transcription and derivative drafting;
- DR-0021 Option A / APP-0048 / D-0145 / ADR-0016 added only the governed
  `lesson_audio` kind and retained the current private Media Library lifecycle;
- each exact APP-0047 source was reacquired into temporary non-repository
  storage, matched its recorded SHA-256 and byte size, inspected as MPEG audio
  by the project ffprobe adapter, and returned clean through the project
  ClamAV adapter. The exact operational evidence is recorded in
  [LPL-01_INGESTION_PREFLIGHT.md](LPL-01_INGESTION_PREFLIGHT.md);
- final `make verify`, `make e2e` (16/16 Chromium journeys) and `make build`
  passed for the lesson-audio prerequisite. `make ai-eval` was not run because
  no prompt, model, provider or orchestration behavior changed;
- DR-0024 Option A created one standalone private pilot program, one exact
  two-permission non-system role assigned only to the retained membership and
  one program-scoped appointment through the existing authority service;
- ordinary OTP, exact membership and fresh TOTP accepted that appointment,
  after which four exact HTTP rights confirmations created four approved
  initial rights revisions with APP-0047 evidence, `android`/`web`, `IN`,
  derivatives and later-gated learner delivery allowed, sharing false, no
  expiry and the approved retention/deletion keys; and
- exact bootstrap replay and read-only persisted-state checks found one
  audit/outbox pair, appointment events `appointed` then `accepted`, four and
  only four rights rows and zero pilot curriculum versions. No attachment,
  transcript, provider run, learner record, enrollment or publication was
  created; and
- the final DR-0024 `make verify` passed 144 SQLite tests / 1,815 assertions,
  280 PostgreSQL tests / 4,017 assertions, PHPStan, audits, contracts,
  localization, lint/typecheck and 270 package/web tests; `make e2e` passed all
  16 Chromium journeys. `make ai-eval` and a separate `make build` were not
  required because no provider orchestration or packaging changed (the E2E
  server's production Vite build passed with the retained chunk-size warning);
  and
- accepted DR-0025 Option A now governs the exact-byte curriculum-media campaign,
  additive provenance/reconciliation model, seven-day encrypted candidate
  retention and hard four-asset/eight-attempt/37,520,380-byte/2,345,026-ms/
  ₹100 limits. Current official provider evidence was checked and the APP-0052
  approval-recording `make verify` passed 144 SQLite tests / 1,815 assertions,
  280 PostgreSQL tests / 4,017 assertions and all static, audit, contract,
  localization and 270 package/web tests. The acceptance created no runtime
  artifact and no provider call occurred; and
- the D-0154 implementation adds six tenant-owned commissioning tables, a
  provider-neutral adapter boundary, exact reauthorization/cap reservation,
  PostgreSQL single-run locking, encrypted private candidates, seven-day
  expiry, retry/dead-letter deletion, 365-day unreferenced provenance purge,
  private no-store fresh-TOTP reconciliation, safe external reporting and a
  disabled-default dedicated command. Focused SQLite passed 32 tests / 344
  assertions; focused PostgreSQL passed 16 tests / 305 assertions. Final
  `make verify` passed 160 SQLite tests / 1,876 assertions, 297 PostgreSQL
  tests / 4,344 assertions and all static, audit, contract, localization and
  270 package/web tests. `make e2e` passed the real reconciliation API journey
  (4 tests / 105 assertions) and 16/16 Chromium journeys; deterministic
  `make ai-eval` passed 15/15 with zero estimated cost and correctly skipped
  its separately gated speech half; `make build` passed with the retained Vite
  chunk-size warning. No live transcription provider call occurred; and
- the D-0157 execution audit created a PostgreSQL custom-format pre-migration
  backup (799,372 bytes; SHA-256
  `a1510723c88f350a60d99844ff0ae268d01b482eae4512165d40ef33643370d2`),
  applied only migration `2026_08_10_000170`, and confirmed zero campaigns,
  provider attempts, reconciliations and candidate-deletion rows. Official
  Central India retail pricing was $0.36/hour; the 10 August ECB cross-rate
  derived 95.30315880571182 INR/USD and a conservative 9,531 microunits per
  billable second, making the maximum approved manifest estimate ₹22.378788.
  The external mode-`0600` preflight audit has SHA-256
  `2c56e5a359636dc4da3e55a3917953019dce3738af40816caaf2eb4fe6d63abb`.
  Azure management-plane billing/key verification then failed with the
  allowlisted `interactive_reauthentication_required` condition, so no vendor
  evidence projection or campaign permit was created and no request/spend
  occurred. A mode-`0700` external resume utility (SHA-256
  `281e0cd64ced70652b7d5d1c233666a7f2f63165e31939a1086b5d31cee6276f`)
  now performs the exact billing-account/subscription correlation and
  resource/key comparison after normal Azure reauthentication without
  printing identifiers or credentials; Bash syntax validation passed and it
  cannot create the campaign permit. The documented mono-response
  compatibility correction passed a 17-test/50-assertion focused boundary
  suite, final `make verify` (165 SQLite tests / 1,883 assertions; 297
  PostgreSQL tests / 4,345 assertions; 270
  package/web tests), `make e2e` (4 API tests / 105 assertions and 16/16
  Chromium) and deterministic `make ai-eval` (15/15, zero cost).

LPL-01 remains active rather than complete. Accepted DR-0025 Option A fixes the
finite provider run ceiling, fail-closed kill switch, no-training/minimum-
retention settings and immutable provenance/human-reconciliation path. Its
repository implementation, external campaign and provider-candidate creation
are complete. Four attempts succeeded for four exact assets with zero failures
or retries. D-0191 records four human-confirmed metadata revisions, four
completed encrypted-candidate deletion receipts and terminal campaign state
with no cleanup backlog. The typed source snapshot, deterministic blueprint,
English academic review, Kannada creation/review and one exact
source-supported publishable Level 1 blueprint remain pending.
LPL-02 has not started.

Final ADMIN-MEDIA-01 evidence:

- the implementation follows DR-0019 Option A / D-0139: organization-scoped
  model-audio and lesson-video quarantine, exact hash/inspection/scanner
  evidence, human acceptance, governed accessibility metadata/reviews,
  appointed rights confirmation, attachment, replacement, retirement and
  private preview;
- the focused real-PostgreSQL `ConcurrentMediaLibraryTest` passed 5 tests / 53
  assertions in disposable PHP 8.4 with `pdo_pgsql` and `pcntl`;
- final `make verify` passed using the repository's pinned-compatible PHP
  8.4.24 and Node 24.18.1/pnpm 11.18.0 environment: Composer validation/audit,
  Pint (587 files), PHPStan (565 files), 130 SQLite tests / 1,665 assertions,
  271 PostgreSQL tests / 3,890 assertions, pnpm audit, locale validation,
  OpenAPI/client drift, release contract, ESLint, TypeScript and package/web
  tests all passed;
- final `make e2e` passed 16/16 Chromium journeys, including Media Library;
- final `make build` passed with the retained Vite chunk-size warning; and
- `POSTGRES_DB=global_english_test make backup-restore-drill` passed with a
  measured local RTO of 1 second and artifact
  `artifacts/ops/backup-restore-20260810T050100Z.json`.

The first disposable PHP image lacked `pcntl`; the first full-container static
analysis used a mismatched mount path; and initial E2E/build/restore attempts
met sandbox or temporary-storage setup failures. None reached a failing product
assertion. Each environment issue was corrected and the exact applicable gate
then passed. `make ai-eval` was not required for ADMIN-MEDIA because no prompt,
model, provider or orchestration behavior changed.

APP-0044 remains reusable umbrella ownership evidence only. It does not
automatically approve a file, rights scope, program, appointment or
publication. Independent security/privacy/tenancy/accessibility/language
review, production object-store/scanner validation, deployment and
production/public release remain unclaimed.

Final ADMIN-CONTENT-01 evidence:

- the master plan, ADR-0003/0004/0013/0014, D-0136, APP-0043, the frozen ADMIN
  matrices, existing curriculum/governance/publication code and the bounded
  specification were inspected before runtime edits;
- migration apply/rollback/reapply passed on SQLite and PostgreSQL (1 test / 20
  assertions on each), preserving graph-v1/publication tables while restoring
  only the additive workspace contract;
- focused Content Studio API, publication lifecycle and shell suites passed on
  both databases; focused web component/API-client behavior passed 27 tests;
- forced real PostgreSQL concurrency passed 9 tests / 95 assertions, with both
  workers entering the lock queue for graph, English head, Kannada head,
  structure review/edit, publish, schedule and rollback races, plus permission
  and appointment invalidation overlap;
- final `make verify` passed Composer/pnpm validation and audits, Pint,
  PHPStan, 128 SQLite tests / 1,526 assertions, 257 PostgreSQL tests / 3,541
  assertions, locale governance, OpenAPI/generated-client drift,
  release-contract validation, ESLint, recursive TypeScript and 247
  package/web tests;
- final `make e2e` passed all 15/15 Chromium journeys, including Content
  Studio partial failure, structured authoring, exact review, stale-state
  retention, axe and 320-pixel no-overflow coverage;
- final `make build` passed Laravel cache preparation/cleanup and the
  production web build with the retained Vite chunk-size warning;
- `POSTGRES_DB=global_english_test make backup-restore-drill` passed with exact
  schema/migration/aggregate reconciliation and measured local RTO 1 second;
  and
- `make ai-eval` was not run because no prompt, model, provider or
  orchestration behavior changed. The five non-English catalogues retain their
  declared pending-independent-review warnings and approved-English fallback.

Earlier ADMIN-LEARNER-01 evidence remains recorded below:

Final ADMIN-LEARNER-01 evidence:

- the master plan, accepted tenancy/authentication/content/offline/privacy
  ADRs, privacy inventory, frozen ADMIN matrices and existing learner/profile/
  enrollment code and tests were inspected before any runtime edit;
- DR-0017 Option A is accepted under APP-0042 / D-0134 and translated into
  exact schema, permission, route, lifecycle, retention, UI and test contracts;
- focused learner API/schema suites passed on SQLite and PostgreSQL; focused
  learner web/API-client behavior passed 23 tests;
- the real PostgreSQL administration concurrency class passed 11 tests / 85
  assertions, forcing lock-queue overlap for learner invitation accept/revoke,
  duplicate current-enrollment creation, competing goal corrections, cohort
  change versus withdrawal and verified activation, and membership selection
  versus suspension alongside the retained ADMIN-ORG races;
- OpenAPI 0.19.0 validates, generated TypeScript contracts match, and all six
  catalogues validate with only the five declared independent-review warnings;
- final `make verify`: passed — Composer/pnpm audits; Pint; PHPStan level 8;
  128 SQLite tests / 1,390 assertions; 249 PostgreSQL tests / 3,369 assertions;
  locale governance; OpenAPI/generated-client drift; release-contract
  validation; ESLint; recursive TypeScript; and 227 package/web tests;
- final `make e2e`: passed all 14/14 Chromium journeys, including the Learners
  partial-failure, goal-correction, ephemeral-code, axe and 320-pixel journey;
- final `make build`: passed with the existing measured Vite chunk-size
  warning; and
- `POSTGRES_DB=global_english_test make backup-restore-drill`: passed with an
  exact schema, migration-ledger and deletion/learner-goal authority aggregate
  match; measured local RTO 1 second; and
- `make ai-eval` was not required because no prompt, model, provider or
  orchestration behavior changed.

The final repository-wide result is recorded in the ADMIN-LEARNER-01 section
of [QUALITY_REVIEW.md](QUALITY_REVIEW.md).

The immediately preceding REL-01 final evidence remains:

- `make verify`: 120 SQLite tests / 932 assertions, 203 PostgreSQL tests /
  2,759 assertions and 182 package/web tests passed with audits, formatting,
  PHPStan, localization, OpenAPI/client drift, release-contract validation,
  lint and TypeScript checks;
- `make e2e`: 11/11 Chromium journeys passed;
- `make build`: passed;
- production-path Android `testDebugUnitTest assembleDebug`: passed;
- readiness drill: 100/100 HTTP 200, p95 202 ms under its explicit 1,000 ms
  non-production threshold; and
- logical restore drill: exact schema/migration/deletion-state match, 1 second.

## Known limitations and retained gates

- Remote GitHub-hosted CI has not run on a commit containing the current
  working-tree changes; branch protection must require `release-gate`.
- Physical DM-01/DM-02 device evidence, representative low-memory/network
  video evidence, a signed AAB and Play closed/public release evidence remain
  open.
- The readiness drill is not the authenticated normal-API SLO, a 2× pilot
  capacity result or a production soak. Empty-schema query plans prove index
  eligibility, not production latency/selectivity.
- The logical restore drill is not encrypted production backup, offsite
  retention, continuous WAL/PITR or production RPO evidence.
- Production alert routing, qualified legal review and independent security,
  privacy, tenancy, accessibility, language/content and release review remain
  open.
- Kannada, Malayalam, Tamil, Telugu and Hindi UI wording remains
  pending-independent-review; production resolves visibly to approved English.
- No complete human-reviewed pilot pathway or approved production media exists;
  the media library currently has only non-production fixture evidence.
- The existing Vite chunk-size warning and optional Rolldown WASM peer-version
  mismatch remain measured maintenance limitations, not passing performance
  evidence.
- Synchronous privacy export still needs production-volume measurement and an
  approved encrypted case-delivery mechanism.
- No production object-store load test, trusted media verification cache,
  Android native streaming data source or multipart byte-range response exists.
- The operator workspace includes the bounded ADMIN-ORG People,
  ADMIN-LEARNER Learners, ADMIN-CONTENT Content Studio and ADMIN-MEDIA Media
  Library slices. PostgreSQL race evidence and the retained release gates are
  incomplete; groups, activity review, communications, moderation, CMS,
  entitlement and configuration parity remain incomplete.
- Federated search is a bounded live database projection, not a durable index;
  its production-volume latency and concurrent-update behavior have not been
  measured.

## Professional client path

- Staging Friend: [STAGING_CONFIDENCE_CONVERSATION.md](STAGING_CONFIDENCE_CONVERSATION.md)
- Closed beta: [CLOSED_BETA_PILOT_RUNBOOK.md](CLOSED_BETA_PILOT_RUNBOOK.md)
- Android lifecycle: [ANDROID_AUTHENTICATED_REQUEST_LIFECYCLE.md](ANDROID_AUTHENTICATED_REQUEST_LIFECYCLE.md)
- Password authentication: [ops/PASSWORD_AUTHENTICATION_RUNBOOK.md](ops/PASSWORD_AUTHENTICATION_RUNBOOK.md)
- Historical privileged MFA: [PRIVILEGED_MFA_RUNBOOK.md](PRIVILEGED_MFA_RUNBOOK.md)
- Voice deletion: [VOICE_DELETION_RECONCILIATION.md](VOICE_DELETION_RECONCILIATION.md)
- Privacy inventory: [PRIVACY_DATA_INVENTORY.md](PRIVACY_DATA_INVENTORY.md)
- Release gates: [ops/RELEASE_GATES.md](ops/RELEASE_GATES.md)

## DR-0026 password-only authentication decision stop — 11 August 2026

> Historical stop record. APP-0056 subsequently authorized AUTH-PASSWORD-01;
> the completed repository implementation is recorded below.

- The product owner requested username/password sign-in for every learner and
  privileged user, with no TOTP or other second factor.
- This conflicts with the direct/institutional OTP onboarding contract, the
  master plan's privileged-MFA baseline, ADR-0002 and accepted DR-0013 Option A.
  No authentication code, schema, API, credential or session was changed.
- [DR-0026](DR-0026_PASSWORD_ONLY_AUTHENTICATION_REQUEST.md) Option B is now
  accepted under APP-0055 / D-0163 / ADR-0021 with explicit acceptance of the
  documented single-factor risk. Master-plan version 3.3 records
  username/password-only authentication for every role and fresh same-password
  confirmation for sensitive commands.
- The exact LPL-01 Azure Fast Transcription campaign completed before this
  decision stop: four attempts succeeded for four exact assets, with zero
  failures or retries, 18,760,190 uploaded bytes, 1,172,513 billable input
  milliseconds and ₹11.189394 estimated usage under the ₹100 cap. Provider
  candidates remain non-authoritative and protected pending human
  reconciliation.
- [AUTH-PASSWORD-01](AUTH-PASSWORD-01_SPEC.md) now defines the additive
  credential schema, APIs, migration/cutover, privileged recovery authority,
  OTP/TOTP retirement and verification boundary. Runtime implementation is not
  yet authorized. Human reconciliation, English/Kannada review and a
  publishable Level 1 blueprint remain incomplete; the current endpoint still
  requires fresh TOTP and no bypass is authorized.
- LPL-02 and later LPL checkpoints, remaining ADMIN checkpoints, independent
  reviews, physical-device evidence, production and Google Play authorization
  remain outstanding. Remote CI has not run against the current working tree.

## AUTH-PASSWORD-01 repository implementation — 11 August 2026

- APP-0056 / D-0164 authorized the exact specification. The additive migration
  now supplies platform-global canonical usernames, versioned Argon2id
  credentials, session-bound password freshness, enumeration-safe ordinary
  recovery, delayed two-operator privileged recovery, append-only safe security
  evidence and an idempotent global cutover marker.
- Web and Android use username/password while retaining Sanctum cookie/CSRF and
  system-browser Passport S256 PKCE boundaries. Account Security is available
  to learners and operators. The local web port is 5174 and does not conflict
  with Anamaya's reserved port.
- Every formerly MFA-protected route remains on the exact protected set through
  mode-aware `auth.password-confirmed`: pre-cutover migration can still fail
  closed on legacy evidence, while password-only mode accepts only a current
  five-minute same-password confirmation plus the existing independent tenant,
  permission and appointment checks.
- The final migration was rolled back and reapplied before local credentials or
  a cutover marker existed. PostgreSQL then passed the password/Passport slice
  (14 tests / 605 assertions) and the isolated full integration suite (306 /
  4,454); SQLite passed 172 / 1,904. PHPStan reported zero errors, Pint passed,
  dependency audits found no known vulnerabilities, OpenAPI/generated clients,
  release/soft-launch contracts, locale validation, ESLint, TypeScript and all
  workspace tests passed.
- The exact root `make verify` passed end-to-end. `make e2e` prerequisites
  passed and Chromium passed 16/16 axe/reflow/journey
  tests. `make build` passed. The PostgreSQL backup/restore drill passed with a
  two-second RTO. Production-path Capacitor sync plus Android JDK 21
  `testDebugUnitTest assembleDebug` passed.
- The local password-only cutover completed after two users independently
  established credentials and two ready recovery actors were configured. A
  reviewed manifest accounted for all 16 active users as two credential-ready
  users plus 14 explicit content-free local pre-production exclusions. The
  command retired legacy OTP/TOTP material and globally revoked sessions and
  tokens; its replay reported the already-complete state without mutation.
- Pre-cutover recovery evidence is the readable custom PostgreSQL dump
  `/tmp/gse-pre-password-cutover-20260811.dump` (898,961 bytes; SHA-256
  `de3c59e76ffaa6132cf08fb4cd8bb728691f87070b05a5b8f4fafde4c4680374`).
  Aggregate post-cutover checks found zero active sessions, mobile token
  families, OAuth access tokens, MFA factors/challenges/recovery codes and OTP
  challenges. The live local mode endpoint returned `password_only`; a
  synthetic legacy OTP request returned HTTP 410.
- A credential-setup usability follow-up now makes the exact 4–64-character
  username and 15–128-character non-common-password rules visible and
  browser-validated, adds a show-password control, keeps the save action at a
  full usable width, and explains that successful one-time setup signs the
  current session out before the normal Sign in button appears. Rejections now
  retain generic server handling while giving safe corrective guidance. The
  live Vite service on port 5174 hot-reloaded the fix; no user credential was
  inspected, logged or manufactured.
- After routine password sign-in, **Username and password security** is
  collapsed behind an explicit button and does not load its forms until
  opened. The authenticated learner/operator workspace remains the primary
  view, and a separate Sign out action is always available from the compact
  secure-session summary.
- A live follow-up found that the signed-in learner-only session was correctly
  classified non-privileged but the shared security panel still offered
  privileged confirmation and username-change controls. Those controls are now
  shown only for a privileged current session; ordinary users retain password
  change and session management. Failed privileged confirmation has a specific
  safe corrective message instead of the generic load/update error. Focused
  component coverage passes 6 tests, the complete web suite passes 242 tests,
  ESLint and TypeScript pass, and locale governance revision `.7` validates all
  six catalogues with the five declared independent-review warnings.
- A deeper authority check found that both credential-ready accounts are
  learner-only and that exactly one of the 14 exclusions is an active
  permission-derived organization administrator with a verified contact. The
  configured platform recovery actors were not recognized by the session
  classifier; a composite classifier now recognizes only explicit recovery/
  emergency allowlist entries before falling back to organization permissions.
  Focused unit, real-PostgreSQL and PHPStan evidence passes. This grants no
  organization authority.
- DR-0027 records the remaining fail-closed stop: privileged recovery rejects
  a username-less credential-unready target, so the excluded operator cannot
  yet establish a credential. No role mutation, direct credential write,
  OTP/TOTP restore or recovery case was performed.
- Remote CI, independent security/privacy/tenancy/accessibility/language review,
  production blocklist and recovery-delivery evidence, physical-device
  inspection, production deployment and Google Play authorization remain open.
  LPL-01 human reconciliation/English-Kannada review/blueprint and all later
  LPL/ADMIN checkpoints remain separate and incomplete.

## Android release-signing mechanism — 11 August 2026

- The production Android Gradle tree now has a fail-closed upload-signing
  configuration. It accepts only the four `GE_ANDROID_UPLOAD_*` process
  variables, rejects partial input, requires an absolute readable keystore
  outside the repository and refuses release APK/AAB tasks when the inputs are
  absent. Debug builds remain independent of release keys.
- `make android-release-bundle` requires a deployed HTTPS API origin, builds
  the production web bundle, synchronizes only `com.globalenglish.app`, runs
  native debug unit tests and builds the signed release AAB. The release-gate
  contract now protects this command from accidental removal.
- Negative validation passed: Gradle configuration succeeded without secrets,
  while `bundleRelease` failed before execution with the expected missing-
  signing error. Positive validation with a disposable `/tmp` self-signed test
  key passed 172 Gradle tasks and produced a signed 4,807,426-byte AAB with
  SHA-256
  `096097a1871e24d5f46e0410ace3fb5db18a379bccf3b646d20f97fb2c`.
  `jarsigner -verify` reported `jar verified`. Native `testDebugUnitTest
  assembleDebug` separately remained successful; the debug APK is 6,344,957
  bytes with SHA-256
  `721f21e0e92c22c8254f6b75be257fa384c4cb77841be6e75d4433fd77e14b20`.
- This closes the repository signing-wiring gap, not the operational signing
  gate. A product-owner-controlled upload key, protected backup/custody record,
  production-bound signed AAB, Play App Signing, physical DM-01/DM-02 evidence
  and Google Play upload authorization remain outstanding. GitHub publication
  was still paused at this mechanism-only check; the candidate publication
  recorded below supersedes that transient tooling state.

## Release-candidate remote verification — 11 August 2026

- The accumulated governed implementation was committed to
  `codex/confidence-conversation` and published as draft pull request
  [#3](https://github.com/srbhandary1/GESC/pull/3). Repository-local IDE state,
  runtime output, build artifacts and secrets remain excluded.
- Clean GitHub runners exposed two environment assumptions that local ignored
  files had masked. CI now installs the Android command-line SDK through an
  exact-commit-pinned action, and production session-default coverage runs in
  an isolated process. The PostgreSQL HTTP suite additionally fixes its HTTPS
  cookie contract before middleware resolution so host environment variables
  cannot weaken the assertions.
- The final local `make verify` passed Pint, PHPStan, 175 SQLite tests / 1,919
  assertions, 309 PostgreSQL tests / 4,496 assertions, dependency audits,
  locale/OpenAPI/release contracts, ESLint, TypeScript and all 282 JavaScript
  tests. A hostile-environment reproduction separately passed the affected 25
  PostgreSQL authentication/PKCE tests / 800 assertions.
- GitHub Actions run
  [31487637244](https://github.com/srbhandary1/GESC/actions/runs/31487637244)
  passed Verify, Android, Operations and the fail-closed Release Gate. This
  closes the previously open remote-CI gate for this candidate revision.
- Remote repository verification is not production authorization. Independent
  security, privacy, tenancy, accessibility and language reviews; production
  infrastructure/configuration evidence; product-owner upload-key custody;
  physical-device DM-01/DM-02 evidence; a production-bound AAB; Play App
  Signing/upload authority; and LPL-01 human content reconciliation remain
  open.

## Static-IP port-8080 secure entry — 11 August 2026

- The persistent soft-launch service remains enabled and healthy on the Ubuntu
  host. Caddy listens on `0.0.0.0:8080`, the LAN address remains
  `192.168.1.3`, and an independent internet-side TCP check confirmed that
  `117.254.108.143:8080` is reachable through the router forward.
- When an approved HTTPS origin exists, the port-8080 listener now returns HTTP
  `307` to the same path and query on that origin. This preserves the requested
  static-IP entry while preventing usernames, passwords and Secure session
  cookies from being transported over public plain HTTP. It does not expose or
  weaken localhost-only recovery controls.
- The existing LPL-01 reconciliation aid already uses the authenticated
  operator API with fresh same-password confirmation. Its stale
  `localhost-only` / `fresh-TOTP` labels now describe the controlled HTTPS
  soft-launch and password-confirmed contract accurately; no API,
  authorization, candidate handling or browser-storage behavior changed.
- The managed service was restarted against the changed runtime. Loopback and
  LAN requests both returned the expected redirect, the HTTPS readiness
  endpoint returned `ready`, and soft-launch readiness reported 17 passes, one
  intentional disabled-live-AI warning and zero failures.
- `make verify` passed Pint, PHPStan, 175 SQLite tests / 1,919 assertions, 309
  PostgreSQL tests / 4,496 assertions, audits, contracts, ESLint, TypeScript
  and 282 JavaScript tests. `make build` passed with only the existing Vite
  chunk-size advisory.
- This is invite-only remote commissioning access, not production/public
  release authorization. The HTTPS tunnel hostname is ephemeral and remains
  authoritative from the protected runtime state; a stable named HTTPS tunnel
  and the outstanding human/release gates remain separate.

## Remote password sign-in process ownership correction — 11 August 2026

- A live remote sign-in loop exposed that the managed API PID was stopped while
  port `8000` remained owned by an older manually started `artisan serve`
  parent/child pair. Managed restarts logged `Address in use`, yet readiness
  was falsely satisfied by that stale process with its obsolete tunnel-origin
  environment.
- The soft-launch runner now launches the repository-pinned Laravel PHP server
  router directly from `apps/api/public` and records the actual long-lived
  server PID. Stop/restart therefore owns the process that holds port `8000`
  and cannot silently retain an old stateful-domain/session origin.
- No credential, request body, username or password was inspected. Diagnosis
  used only process identity, bind-error evidence, health responses and
  non-secret origin/session configuration.
- The exact stale parent/child pair was stopped after the managed unit was
  stopped. The corrected unit then started one directly owned API listener and
  reports API, worker, scheduler, both Caddy listeners and tunnel as running.
  A credential-free live protocol probe returned `password_only` / HTTP 200,
  issued the XSRF and `__Host-global_english_session` cookies, and reached the
  password controller with an expected generic HTTP 422 for a synthetic
  invalid account rather than a stateful/CSRF failure.
- The post-correction `make verify` passed Pint, PHPStan, 175 SQLite tests /
  1,919 assertions, 309 PostgreSQL tests / 4,497 assertions, audits, contracts,
  ESLint, TypeScript and 282 JavaScript tests.

The original rich-text body is unavailable. A separately labeled newly authored
Level 1 `can` blueprint now provides an objective, Talk scenario, weighted
Check items and Apply rubric. It remains unpublished pending English academic
review, Kannada creation/review and governed content attachment.

The user approved the authored English draft on 14 August 2026. Kannada
guidance is now drafted separately and remains pending independent Kannada
review; no publication or runtime attachment has occurred.

The user subsequently approved the Kannada guidance. Both language reviews are
now recorded as approved for private draft use; governed content attachment and
publication are still outstanding.

LPL-02 foundation contract is now accepted under ADR-0024 and
`docs/specs/LPL-02_TYPED_LESSON_PRACTICE_ATTACHMENT.md`. Runtime implementation
has not yet begun; LPL-01 remains unpublished until the new schema and API are
implemented and verified.
