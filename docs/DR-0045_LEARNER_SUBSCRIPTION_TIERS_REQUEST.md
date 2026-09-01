# DR-0045 — Direct learner subscription tiers

**Status:** Implemented — Option C (ADMIN-ENT-01 / ADR-0035 / APP-0072).
Live payment credentials, production and Google Play remain unauthorized.
**Needed by:** Paid Direct access, Role-play packaging, demo metering, GST
and operator subscription support
**Authority affected:** Master plan §§8.2–8.3, 9.2, 16.5, 31; ADR-0014 /
`ADMIN-ENT-01`; ADR-0026 (Stripe / Play Billing exclusion — must be amended
at implementation for in-app checkout, not Play Billing); D-0215;
[SUBSCRIPTIONS.md](SUBSCRIPTIONS.md)
**Opened:** 1 September 2026
**Resolved (catalogue):** 1 September 2026 — Accountable Product Owner
answers in the active Cursor conversation

## 1. Decision needed

The Accountable Product Owner asked for Direct subscription tiers, then
confirmed the remaining commercial rules. The rule sheet is
[SUBSCRIPTIONS.md](SUBSCRIPTIONS.md).

This remains an entitlement, tax, metering and checkout foundation change. No
plan table, paywall, payment adapter or new operator mutation may ship until a
bounded specification is written. Recording Option C does not authorize
production, Google Play, or live payment credentials.

## 2. Current authoritative behavior

- Soft-launch learners get access through invitation + membership + pinned
  curriculum, not a paid plan.
- Role-play is a Speak-room situation picker (ADR-0026), not a sold SKU.
- Operator Learners already cover invite, enrollment, pin, withdraw and
  membership suspend/reactivate (ADMIN-LEARNER-01).
- `ADMIN-ENT-01` is reserved. Checkout, payment providers, webhooks, invoices
  and tax are not built.
- Sign-in is throttled. There is no durable account-lock record.
- AI must not decide entitlements (master plan §0.3).

## 3. Recorded commercial contract

The following is accepted for the catalogue. Implementation still needs a
bounded spec.

1. Plan codes: `demo`, `monthly_modules` (₹350/month ex-GST, all published
   levels, no Role-play), `monthly_modules_roleplay` (₹500/month ex-GST, all
   levels plus Role-play).
2. **Modules.** First 2 modules = **Level 1 and Level 2**.
3. **Demo clock.** **48 hours from first successful sign-in** after the demo
   grant exists, `Asia/Kolkata`.
4. **Demo is free (₹0)** with a hard **inclusive 5-minute (300 second)**
   cap on speech, Role-play and AI chats together. Not 5 minutes per
   feature. Authored lesson media and deterministic checks do not consume
   the cap. Paid monthly plans do not use this cap.
5. **GST exclusive.** ₹350 and ₹500 exclude GST. Checkout adds GST from
   operator tax settings.
6. **Dummy GST now, owner replaces on admin page.** Placeholder GSTIN
   `29AAAAA0000A1Z5`, placeholder rate `18` percent. Not valid for live
   filing. Changing GSTIN/rate on the admin page must not rewrite already
   issued invoices.
7. **In-app checkout in the same slice** as entitlements, plan catalogue,
   demo meter and operator grants. Payment provider SDK types stay behind a
   project-owned port. Live keys never in git. Google Play Billing remains
   unauthorized.
8. Plans, GSTIN, GST rate, demo clock and 300-second cap are operator-editable.
   Historical grants keep their snapshot.
9. Entitlements attach to a Direct learner membership. Social sign-in never
   creates a paid account (ADR-0034).
10. 1:1 stays unbundled (ADR-0026).
11. Operator page: grants, enrollments (reuse ADMIN-LEARNER), lockouts, demo
    clock restart, demo meter restart, session/family revocation. No wipe of
    marks or submitted evidence.

## 4. Decision profiles

| Option | What ships first | Payment | Status |
|---|---|---|---|
| A — Catalogue only | Defaults in SUBSCRIPTIONS.md only | None | Not selected |
| B — Operator-granted entitlements | `ADMIN-ENT-01` without checkout | Staff record payment outside the app | Not selected |
| **C — In-app checkout in the same slice** | Entitlements + paywall + dummy GST + checkout port + operator page | In-app checkout; dummy GSTIN until the owner updates it | **Recorded** |

## 5. Product-owner answers (1 September 2026)

| Item | Answer |
|---|---|
| Module mapping | Level 1 and Level 2 |
| Demo clock | 48 hours from first sign-in |
| ₹350 Role-play | Speak situation picker locked; Learn Interaction and everyday Speak remain (catalogue default, not contradicted) |
| GST | Exclusive. Dummy GSTIN/rate now; owner updates on admin page in a few days |
| Checkout | In-app, same slice as entitlements |
| Demo live AI | Inclusive 5-minute maximum across speech, Role-play and AI chats |
| Renewal | Learner self-serve via in-app checkout; operators may still grant/adjust |
| After demo / cap | Live AI fails closed with upgrade; Level 1–2 non-AI media may remain until the 48-hour clock ends |
| One demo per person | Operator-restartable clock and meter as separate actions (default until a later “one demo forever” rule) |

## 6. Options explicitly not selected

- Hard-coding ₹350 / ₹500 / GSTIN / 5 minutes in the React app or Android APK
  as the authority.
- Letting AI, streaks or attendance grant paid access.
- Wiping learner evidence as a “subscription reset”.
- Creating accounts from payment success without username/password
  enrollment.
- Play Store listing that charges for digital content without Play Billing.
- Filing GST on the dummy GSTIN.

## 7. Implementation gate

Do **not** start code from this request file alone. A later bounded spec
must cover:

- `entitlement_plans`, `learner_entitlements`, `entitlement_events`
- demo usage meter (300 seconds, fail closed)
- organization tax settings (GSTIN, rate, exclusive flag)
- payment port (no provider types in domain modules)
- OpenAPI, tenancy tests, privacy inventory, operator UI

ADR-0026 and D-0215 must be amended in that implementation checkpoint to
allow in-app checkout while still excluding Stripe-as-named-architecture and
Play Billing. The payment adapter identity (for example Razorpay sandbox)
can be chosen in that spec; it is not named in this DR.

## 8. Security, privacy and tenancy

- Entitlements and tax settings are organization-owned. Cross-tenant grant
  or list is forbidden.
- Payment references are evidence identifiers, not card or UPI secrets.
- Demo remaining-seconds is an authoritative counter, not an AI estimate.
- Operator views stay purpose-minimized. No transcripts, audio or recovery
  secrets.
- Privilege to grant paid access is `entitlements.grant` / `adjust` /
  `revoke`, not trainer-by-default.
- Dummy GSTIN must be visually marked as placeholder in operator UI.
