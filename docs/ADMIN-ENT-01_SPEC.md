# ADMIN-ENT-01 — Direct subscriptions, GST and in-app checkout

**Status:** Repository implementation in this checkpoint

**Checkpoint:** ADMIN-ENT-01 (DR-0045 Option C)

**Approved policy:** APP-0072 / APP-0073 / APP-0074 / D-0266 / D-0267 /
D-0273 / ADR-0035 / ADR-0036 / ADR-0037

**Authority:** `ENTERPRISE_PRODUCT_MASTER_PLAN.md` §§8.2, 16.5, 31;
ADR-0014 (amended for Direct in-app checkout); ADR-0026 (Play Billing still
excluded); [SUBSCRIPTIONS.md](SUBSCRIPTIONS.md)

**Prepared:** 1 September 2026

**Production/public release:** Not authorized

## 1. Objective

Deliver one first-party vertical slice for Global English Direct:

- operator-editable plan catalogue and GST settings (dummy GSTIN until replaced);
- learner entitlements with immutable events;
- 48-hour demo from first sign-in, Level 1–2 only, inclusive 300-second live-AI cap;
- ₹350 / ₹500 GST-exclusive paid plans (Modules / **Premium**) with in-app dummy checkout;
- monthly live-AI pools (40 / 90 minutes) and prepaid Talk 30 / 60 / 180 packs (ADR-0037);
- one complimentary Premium month for current Direct learners (ADR-0036);
- operator grants, adjustments, revocations, demo clock/meter restarts, sign-in throttle clear;
- learner paywall and remaining-seconds display.

Institutional organizations are unchanged: subscription gates apply only to
`platform_direct`. Enrollments stay on ADMIN-LEARNER-01.

## 2. Explicit exclusions

- Google Play Billing, Stripe as a named architecture, live Razorpay keys
- Production tax filing with the dummy GSTIN
- Webhook-driven payment capture (dummy checkout completes in-process)
- Wiping marks, mastery or submitted evidence
- Social/payment account creation
- Per-minute overage billing
- Importing students, passwords or progress from the previous website

## 3. Tenant and transport

Operator routes: `auth.first-party` → `auth.web-only` → `organization.context`.
Learner self-service: `auth.first-party` → `organization.context` (Android allowed).
Mutations require `Idempotency-Key`. Responses `Cache-Control: private, no-store`.
Missing/foreign targets: existence-hidden 404. Entitlement denials for the
acting learner: 409 with `message_key` (`entitlement.level_locked`,
`entitlement.roleplay_locked`, `entitlement.demo_ai_exhausted`,
`entitlement.ai_exhausted`, `entitlement.talk_pack_requires_paid`,
`entitlement.expired`).

## 4. Permissions

Seeded: `entitlement_plans.read`, `entitlement_plans.manage`,
`entitlements.read`, `entitlements.grant`, `entitlements.adjust`,
`entitlements.revoke`. Direct organization-admin pack only.

## 5. Authoritative records

Plans, tax settings, entitlements, checkout GST snapshots and live-AI charge
keys are authoritative. AI never grants, prices or meters access. Historical
grants keep their plan and GST snapshot.

## 6. Payment port

`PaymentProvider` is a project-owned port. Default non-production driver is
`dummy` (completes immediately, stores `dummy_{ulid}`). `disabled` fails closed.
No provider SDK types in domain modules. No card or UPI secrets stored.
