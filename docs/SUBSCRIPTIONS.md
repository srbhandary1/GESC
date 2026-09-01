# Direct learner subscriptions

**Status:** Implemented in repository (ADMIN-ENT-01 / ADR-0035 / ADR-0036 /
APP-0072 / APP-0073). Live
payment credentials, production and Google Play remain unauthorized.
**Decision request:** [DR-0045](DR-0045_LEARNER_SUBSCRIPTION_TIERS_REQUEST.md)
Option C; [DR-0046](DR-0046_PREMIUM_COURTESY_GRANT_REQUEST.md) Option A
**Product owner:** S R Bhandary
**Currency:** Indian rupees (INR), **exclusive of GST**
**Audience:** Direct consumer learners on the Global English Direct tenant
**Does not authorize:** production, Google Play, live payment credentials, or
a Play Billing integration

This file is the advertised Direct subscription catalogue and the **rule
sheet** for demo caps, GST and checkout. Prices, GST, durations and feature
gates must stay operator-editable on the website. Do not hard-code them in
application logic. After implementation, the live source of truth is the
organization-owned plan catalogue and tax settings; this file remains the
human-readable advertised default.

Institutional enrollments stay on
[ADMIN-LEARNER-01](ADMIN-LEARNER-01_SPEC.md). They are not these Direct
prices unless a later decision says so.

---

## 1. Current advertised tiers

Listed amounts are **GST exclusive**. Checkout adds GST from the operator
tax settings (§4).

| Code | Display name | Price (ex-GST) | Access window | Content | Role-play |
|---|---|---|---|---|---|
| `demo` | Two-day demo | ₹0 | 48 hours from first sign-in, plus the 5-minute live-AI cap in §2 | Level 1 and Level 2 only | Included for those two levels, inside the 5-minute cap |
| `monthly_modules` | Modules | ₹350 / calendar month | Recurring monthly while the grant is active | All published levels | Not included |
| `monthly_modules_roleplay` | **Premium** (flagship) | ₹500 / calendar month | Recurring monthly while the grant is active | All published levels | Included |

Premium is the recommended paid plan (SuperNova-like one flagship SKU).
Modules is the cheaper downsell: all published levels without Role-play.

₹350 unlocks the full published pathway except Role-play. Everyday Speak talk
with Swathi remains available. The Speak situation picker and Role-play
journeys (Observe / Rehearse / Perform) stay locked.

₹500 Premium is ₹350 plus Role-play.

1:1 waitlist and institute contact links are **not** sold in these tiers
(ADR-0026).

Marks, mastery, delayed review and unlocks stay deterministic. A subscription
never writes those records. It only grants or denies access to content, modes
and live-AI time.

---

## 2. Demo rules (authoritative)

These rules are binding for the `demo` plan. Change them on the operator
plan page later; do not bury them only in client copy.

1. **Price.** The demo is free (₹0). There is no checkout for `demo`.
2. **Modules.** Only **Level 1** and **Level 2** (the first two published
   Learn levels on the Direct pathway). Every other level stays locked.
3. **Clock.** The demo lasts **48 hours from first successful sign-in** after
   the demo grant exists, in `Asia/Kolkata`. It is not two calendar dates.
4. **What “everything” means inside Level 1–2.** Learn sessions
   (Introduction, Practice, Conversation, Interaction), Home, Progress, Speak
   everyday talk, and Role-play tagged to Level 1 or Level 2 — **subject to
   the live-AI cap below**.
5. **Inclusive 5-minute live-AI cap (hard rule).**

   > During the free demo, **speech, Role-play and AI chats share one
   > inclusive budget of 5 minutes (300 seconds)**. That budget is not
   > 5 minutes per feature. When the 300 seconds are used up, every live
   > spoken or AI-generated turn is denied until the learner has a paid
   > plan, even if the 48-hour clock has not ended.

   **Counts toward the 300 seconds**
   - Speak everyday talk with Swathi (STT + model + TTS)
   - Role-play Observe / Rehearse / Perform (and any other Role-play AI)
   - Learn Conversation / Interaction live AI turns
   - Any other live model, STT or TTS call on the demo membership

   **Does not count toward the 300 seconds**
   - Playing published lesson audio or video already on the level
   - Deterministic Practice checks and other non-AI items
   - Reading Home / Progress / locked-upgrade copy

6. **When the cap or clock ends.** The learner may still sign in and open
   Level 1–2 lesson media that does not call live AI. Live speech, Role-play
   and AI chat fail closed with an upgrade path to Modules or Premium. Content
   beyond Level 2 stays locked.
7. **Metering.** The remaining seconds are organization-scoped, stored as
   an authoritative entitlement counter (not inferred from AI). The client
   may show remaining time; the server enforces the cap. Replays of the
   same turn do not add a second grant of time if the first call already
   consumed it.
8. **Operator restart.** Restarting a demo (clock and/or 300-second meter)
   is an attributed operator adjustment, not a learner self-serve, unless a
   later decision says otherwise.

Paid plans (`monthly_modules`, `monthly_modules_roleplay`) do **not** use
this 5-minute cap. Their limits are the plan’s module and Role-play flags
only, until a later usage policy is accepted.

---

## 3. GST and in-app checkout

1. **GST exclusive.** ₹350 and ₹500 are exclusive of GST. The payable
   amount is `listed_inr + GST`. The demo stays ₹0 with GST ₹0.
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

4. **In-app checkout in the same slice as entitlements.** Direct paid plans
   are purchased in the web/PWA (and the same Capacitor shell on Android
   sideload) through a project-owned payment port. Staff may still grant or
   adjust a plan without checkout (support, cash, UPI outside the app).
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
the feature gate changes.

On the admin website an authorized operator may change:

- plan name, INR amount (ex-GST), duration, Level 1–2 allowlist, Role-play
  flag, demo 48-hour clock, demo 300-second cap, active/retired;
- GSTIN, GST rate and GST registered flag;
- enrollments (existing Learners workspace);
- lockout reason, demo clock restart, usage-meter restart, session /
  Android-family revocation.

Historical grants keep the plan and GST snapshot they were granted under.
Changing a plan or GSTIN does not silently rewrite past payments.

Do **not** put prices or GSTIN in client bundles, Play Store copy, or
Android native code as the authority. The API returns the current effective
entitlement, remaining demo seconds and the current payable GST breakdown.

---

## 5. Operator website

A first-party operator page on the website manages Direct learners. Enrollments
reuse ADMIN-LEARNER-01. New in the subscription slice:

1. **Plans and tax** — including dummy GSTIN replacement.
2. **Subscriptions** — list, grant, checkout-reconcile, extend, expire,
   adjust, revoke; immutable event history.
3. **Demo meter** — remaining seconds, cap, clock; restart clock and/or
   meter as separate attributed actions.
4. **Lockouts** — rate limit vs suspended membership vs expired entitlement
   vs exhausted demo AI cap; clear a support lock after confirmation.
5. **Resets** — never one button:
   - password / recovery help (global lost contact stays
     `platform.account_recovery.manage`);
   - demo clock restart;
   - demo 300-second meter restart;
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
| Plan catalogue, demo meter, GST settings, in-app dummy checkout | ADMIN-ENT-01 / ADR-0035 |
| Premium flagship naming and courtesy month for current Direct learners | ADR-0036 / D-0267 |
| Sign-in throttle | Username/password and social sign-in are rate-limited. Operator unlock is on the Subscriptions page. |
| Content gating by paid plan | Server-enforced on Direct. Institutional orgs are not paywalled. |

---

## 7. Explicitly not in this catalogue

- Production or Google Play authorization.
- Selling Role-play as a one-off add-on without a new plan code.
- Using AI scores, streaks or attendance to decide paid access.
- 5-minute live-AI cap on paid monthly plans.
- Storing card numbers or UPI credentials.
- Creating accounts from payment success without username/password
  enrollment (ADR-0034).
- Filing tax with the dummy GSTIN.
- Importing students, passwords or progress from `www.globalspokenenglish.com`.

---

## 8. Implementation status

Implemented as ADMIN-ENT-01 / ADR-0035 / APP-0072, with Premium flagship and
courtesy month as ADR-0036 / APP-0073. OpenAPI 0.39.0. Dummy
checkout completes in-process. Live GSTIN replacement is an operator action
on the Subscriptions page. Production, Play Billing and live payment
credentials remain unauthorized.

## 9. Courtesy Premium month

Operators on Global English Direct may grant one complimentary calendar month
of Premium to current Direct learner memberships (`#operator-entitlements` or
`php artisan entitlements:grant-courtesy-premium`). The grant is idempotent.
`--csv` with a `username` column limits the run to usernames that already
exist in this tenant. This is not an import of the old website.
