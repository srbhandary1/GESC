# Direct learner subscriptions

**Status:** Advertised catalogue, entitlement engine and server-authoritative Razorpay integration aligned 18 September 2026 (D-0286 / D-0288 / D-0321).
**Product:** Global Spoken English App
**Decision request:** [DR-0045](DR-0045_LEARNER_SUBSCRIPTION_TIERS_REQUEST.md)
Option C; [DR-0046](DR-0046_PREMIUM_COURTESY_GRANT_REQUEST.md) Option A;
superseded advertised defaults in D-0286
**Product owner:** S R Bhandary
**Currency:** Indian rupees (INR), **exclusive of GST**
**Audience:** Direct consumer learners on the Global English Direct tenant
**Payment authority:** APP-0100 authorizes the Razorpay implementation and
sandbox deployment. Real-money capture remains technically gated until live
credentials, webhook registration, canary/refund and reconciliation evidence
exist. Google Play publication and a Play Billing integration remain separate.

This file is the advertised Direct subscription catalogue and the **rule
sheet** for trial caps, GST and checkout. Follow this file for new product
copy, operator defaults and the next entitlement slice. Prices, GST,
durations and feature gates must stay operator-editable on the website. Do
not hard-code them in application logic. After implementation, the live
source of truth is the organization-owned plan catalogue and tax settings;
this file remains the human-readable advertised default.

Institutional enrollments stay on
[ADMIN-LEARNER-01](ADMIN-LEARNER-01_SPEC.md). They are not these Direct
prices unless a later decision says so.

---

## 1. Current advertised tiers

Listed amounts are **GST exclusive**. Checkout adds GST from the operator
tax settings (§4).

| Code | Display name | Price (ex-GST) | Access window | Content | Role-play | Free speech (Speak with Swathi) | Lesson practice speech | Live AI |
|---|---|---|---|---|---|---|---|---|
| `demo` | 5-day start | ₹3 | 5 days from grant start (`Asia/Kolkata`) | Level 1 and Level 2 only. Learn **Introduction** and **Practice** only | Not included | Not included | Included on those two levels | Lesson-practice speech only. No Speak / Role-play / Conversation / Interaction live-AI pool |
| `monthly_modules` | Premium features | ₹300 / calendar month | Recurring monthly while the grant is active | All published levels | Not included | Included | Included | **120 minutes / month** |
| `monthly_modules_roleplay` | **Premium with Role-play** (flagship) | ₹500 / calendar month | Recurring monthly while the grant is active | All published levels | Included | Included | Included | **120 minutes / month** |
| `talk_pack_30` | Talk 30 | ₹199 | Until the current paid month ends, or 30 days, whichever is sooner | Does not change levels | Does not unlock | Does not unlock | Unchanged | **+30 minutes** |
| `talk_pack_60` | Talk 60 | ₹349 | Same as Talk 30 | Does not change levels | Does not unlock | Does not unlock | Unchanged | **+60 minutes** |
| `talk_pack_180` | Talk 180 | ₹899 | Same as Talk 30 | Does not change levels | Does not unlock | Does not unlock | Unchanged | **+180 minutes** |

The product name on every learner surface is **Global Spoken English App**.

Premium with Role-play is the recommended paid plan. Premium features is the
cheaper downsell: all published levels and free speech, without Role-play.

₹300 unlocks the full published pathway except Role-play. Everyday Speak talk
with Swathi is included and shares the 120-minute live-AI pool.

₹500 is ₹300 plus Role-play.

The lowest package is **not ₹0**. The 5-day start is **₹3** and is sold, not
auto-granted on first sign-in.

### 1.1 What “free speech” and “practice speech” mean

- **Lesson practice speech** is the Learn **Practice** session after
  Introduction: listen-and-repeat / recorded practice of the lesson pattern.
  It is allowed on the ₹3 5-day start for Level 1–2.
- **Free speech** is the Speak destination everyday conversation with
  Swathi (open live talk). It is a paid monthly feature. It is **not**
  included in the ₹3 5-day start.
- **Role-play** (Observe / Rehearse / Perform) is included only on Premium
  with Role-play.
- Learn **Conversation** and **Interaction** are live-AI exchanges. They are
  paid monthly features, not part of the ₹3 start.

Paid live-AI time is a **hard monthly pool** of **120 minutes (7200 seconds)**
on both monthly plans (ADR-0037 shape, new default). Speak, Role-play and
Learn Conversation/Interaction share it. Published lesson audio/video and
lesson-practice speech do not. When the pool is empty, live talk fails
closed. Learners may buy Talk 30 / 60 / 180 packs on an **active Premium
features or Premium with Role-play** month. Packs do not unlock Role-play.
The ₹3 start and expired accounts must subscribe first. Packs expire at the
sooner of the paid month’s end and 30 days from purchase. No refunds.

1:1 waitlist and institute contact links are **not** sold in these tiers
(ADR-0026).

Marks, mastery, delayed review and unlocks stay deterministic. A subscription
never writes those records. It only grants or denies access to content, modes
and live-AI time.

### 1.2 Why keep a 120-minute live-AI cap?

**Yes. Keep the cap** at this price.

Global Spoken English App sells live STT + model + TTS at **₹300 / ₹500 per
month**. That is far below Western AI-speaking subscriptions (often
US$8–30/month). Uncapped open talk on Azure Speech plus Azure OpenAI would
let a heavy user consume more provider cost than the subscription collects.

How other apps handle this (public marketing, 2026):

| Product | What they advertise | How they actually control cost |
|---|---|---|
| TalkPal | Paid plans “unlimited”; free tier about **10 minutes/day** | Free users are hard-capped; paid unlimited is a marketing word over a much higher price |
| Praktika | Limited daily AI sessions on free; paid removes the daily ceiling | Avatar/role-play energy or session limits, then a paid subscription |
| Duolingo Max | Roleplay / Video Call on the AI tier | The AI tier is roughly **double** Super (about US$14–30/month). Video calls are still **short sessions**, not hours of open talk |
| ELSA Speak / similar pronunciation apps | Premium unlocks features | Free is tightly limited; paid is feature-gated more than minute-metered |
| ChatGPT / generic AI chat | “Unlimited” on Plus | Higher price, hidden fair-use / rate limits, and they own the model margin |

They do **not** generally sell uncapped live speech at ₹300–500. They either
charge several times more, cap the free tier in minutes or messages, keep AI
calls short, or hide a fair-use brake.

**How this product manages cost**

1. **Hard monthly pool (120 minutes)** on paid plans so COGS is predictable.
2. **₹3 start has no free-speech pool.** Lesson practice after class is
   allowed; open Speak / Role-play / Conversation / Interaction stay locked.
3. **Talk packs** for learners who need more time that month, without
   raising the base price for everyone.
4. **Lesson audio/video and lesson-practice speech stay outside the pool** so
   ordinary classwork is not punished.
5. Fail closed when the pool is empty, with an upgrade/pack path.

Do not remove the cap unless the monthly price rises enough to cover uncapped
STT+LLM+TTS, or a later decision replaces minutes with a documented fair-use
policy plus an operator kill-switch. “Unlimited” at ₹300 is not a sustainable
default.

Talk packs remain in the catalogue until the product owner retires them. D-0286
did not change pack prices.

---

## 2. 5-day start rules (authoritative)

These rules are binding for the `demo` plan code (advertised name **5-day
start**). Change them on the operator plan page later; do not bury them only
in client copy.

1. **Price.** ₹3 exclusive of GST. This is **not** a ₹0 demo. Checkout is
   required (dummy checkout until live payment credentials exist). There is
   no auto-grant on first sign-in.
2. **Modules / chapters.** Only **Level 1** and **Level 2** (the first two
   published Learn levels on the Direct pathway). Every other level stays
   locked.
3. **Clock.** The start lasts **5 days from grant start** after a successful
   ₹3 checkout or operator grant, in `Asia/Kolkata`. It is not five calendar
   dates chosen by the learner.
4. **What is open inside Level 1–2.** Learn **Introduction** and Learn
   **Practice** (practice speech after the lesson). Home, Progress and
   upgrade copy remain readable.
5. **What stays locked.** Speak free speech, Role-play, Learn Conversation,
   Learn Interaction, and every level above 2.
6. **When the 5 days end.** The learner may still sign in. Live speech,
   Role-play and AI chat stay denied with an upgrade path to Premium
   features or Premium with Role-play. Content beyond Level 2 stays locked.
   Introduction media that does not call live AI may remain playable for
   Level 1–2 until a later decision says otherwise.
7. **Metering.** Lesson-practice speech on the ₹3 start is allowed for the
   5-day window and does not use the paid 120-minute pool (that pool exists
   only on monthly plans). The server still records usage. An operator may
   later add a practice-STT cap if cost requires it; do not invent one in
   client copy.
8. **Operator restart.** Restarting a 5-day clock is an attributed operator
   adjustment, not a learner self-serve, unless a later decision says
   otherwise.

Paid plans (`monthly_modules`, `monthly_modules_roleplay`) use the inclusive
live-AI meter with a monthly default of **7200 seconds (120 minutes)**. Talk
packs add seconds to that pool.

Historical grants keep the plan snapshot they were granted under (including
any remaining ₹0 / 48-hour / 5-minute demo or 40/90-minute monthly caps).

---

## 3. GST and in-app checkout

1. **GST exclusive.** ₹3, ₹300 and ₹500 are exclusive of GST. The payable
   amount is `listed_inr + GST`.
2. **Operator-editable tax.** GSTIN, GST rate and whether GST is in force
   are organization tax settings on the admin website. Changing them must
   not rewrite already-issued invoices; new checkouts use the current
   settings.
3. **Dummy tax until the owner updates it.** Until the product owner pastes
   the real GSTIN on that admin page, the Direct tenant uses this
   **placeholder** (invalid for live tax filing; replace before any real
   invoice):

   | Setting | Dummy value now | Who changes it |
   |---|---|---|
   | GST registered? | `true` (placeholder) | Admin tax page |
   | GSTIN | `29AAAAA0000A1Z5` | Admin tax page |
   | GST rate | `18` percent | Admin tax page |
   | Prices | exclusive of GST | Plan catalogue |

4. **In-app checkout in the same slice as entitlements.** Direct paid plans,
   including the ₹3 5-day start, are purchased in the web/PWA (and the same
   Capacitor shell on Android sideload) through a project-owned payment
   port. Staff may still grant or adjust a plan without checkout (support,
   cash, UPI outside the app).
5. **Payment port.** Provider SDK types stay behind that port. Live keys
   stay in environment, never in git. Missing live keys fail closed except
   where a documented sandbox/dummy checkout is enabled for non-production.
6. **Google Play Billing** is still not authorized. Closed-beta sideload
   must not add Play Billing. A Play Store listing that sells digital
   content needs a separate Play-release decision.
7. **Cards / UPI secrets** are never stored in this application. Store only
   provider payment ids, status and GST snapshot used for that checkout.

---

## 4. How to change this catalogue

Edit this file when the advertised **default** changes. Keep plan codes
stable when only a price, GST rate or duration changes; add a new code when
the feature gate changes. D-0286 keeps code `demo` for the 5-day start so
existing operator tooling does not fork, even though the advertised name,
price and gates changed.

On the admin website an authorized operator may change:

- plan name, INR amount (ex-GST), duration, Level 1–2 allowlist, Role-play
  flag, free-speech flag, 5-day clock, live-AI cap, active/retired;
- GSTIN, GST rate and GST registered flag;
- enrollments (existing Learners workspace);
- lockout reason, trial clock restart, usage-meter restart, session /
  Android-family revocation.

Historical grants keep the plan and GST snapshot they were granted under.
Changing a plan or GSTIN does not silently rewrite past payments.

Do **not** put prices or GSTIN in client bundles, Play Store copy, or
Android native code as the authority. The API returns the current effective
entitlement, remaining live-AI seconds and the current payable GST breakdown.

---

## 5. Operator website

A first-party operator page on the website manages Direct learners. Enrollments
reuse ADMIN-LEARNER-01. New in the subscription slice:

1. **Plans and tax** — including dummy GSTIN replacement.
2. **Subscriptions** — list, grant, checkout-reconcile, extend, expire,
   adjust, revoke; immutable event history.
3. **Trial / live-AI meter** — remaining seconds, cap, clock; restart clock
   and/or meter as separate attributed actions.
4. **Lockouts** — rate limit vs suspended membership vs expired entitlement
   vs exhausted AI cap; clear a support lock after confirmation.
5. **Resets** — never one button:
   - password / recovery help (global lost contact stays
     `platform.account_recovery.manage`);
   - 5-day clock restart;
   - live-AI meter restart;
   - session / Android token-family revocation;
   - **not** a wipe of marks, mastery or submitted evidence.

Every mutation stays organization-scoped, existence-hidden, attributed,
append-only for entitlement and tax-setting events, and `private, no-store`.
AI must not grant, deny, price or meter entitlements.

---

## 6. What is already in the product

| Need | Current state |
|---|---|
| Learner directory, invite, enrollment, curriculum pin, suspend/reactivate | ADMIN-LEARNER-01 Learners workspace |
| Staff/org administration | ADMIN-ORG-01 |
| Plan catalogue, demo meter, GST settings, in-app dummy checkout | ADMIN-ENT-01 / ADR-0035 (live defaults still the pre-D-0286 snapshot until the next entitlement slice) |
| Premium flagship naming and courtesy month for current Direct learners | ADR-0036 / D-0267 |
| Sign-in throttle | Username/password and social sign-in are rate-limited. Operator unlock is on the Subscriptions page. |
| Content gating by paid plan | Server-enforced on Direct. Institutional orgs are not paywalled. |
| Advertised D-0286 catalogue on the guest page | UI catalogues and this file. Entitlement engine not yet switched. |

---

## 7. Explicitly not in this catalogue

- Production or Google Play authorization.
- Selling Role-play as a one-off add-on without a new plan code.
- Using AI scores, streaks or attendance to decide paid access.
- Per-minute overage billing (talk packs are prepaid minutes, not overage).
- Storing card numbers or UPI credentials.
- Creating accounts from payment success without username/password
  enrollment (ADR-0034).
- Filing tax with the dummy GSTIN.
- Importing students, passwords or progress from `www.globalspokenenglish.com`.
- Uncapped live speech on a ₹300 / ₹500 month.

---

## 8. Implementation status

Advertised defaults are D-0286 in this file and the guest marketing
catalogues. The running entitlement engine remains ADMIN-ENT-01 / ADR-0035 /
APP-0072, Premium flagship and courtesy month as ADR-0036 / APP-0073, and
paid live-AI caps plus talk packs as ADR-0037 / APP-0074, with the **previous**
₹0 / 48-hour / 5-minute, ₹350 / 40-minute and ₹500 / 90-minute numbers until
the next bounded slice. OpenAPI **0.40.0**. Dummy checkout completes
in-process. Live GSTIN replacement is an operator action on the
Subscriptions page. Production, Play Billing and live payment credentials
remain unauthorized.

## 9. Courtesy Premium month

Operators on Global English Direct may grant one complimentary calendar month
of Premium with Role-play to current Direct learner memberships
(`#operator-entitlements` or
`php artisan entitlements:grant-courtesy-premium`). The grant is idempotent.
`--csv` with a `username` column limits the run to usernames that already
exist in this tenant. This is not an import of the old website.
