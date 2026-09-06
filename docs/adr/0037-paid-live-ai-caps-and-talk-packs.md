---
status: accepted
date: 2026-09-01
deciders:
  - S R Bhandary as Accountable Product Owner and Technical Decision Owner
consulted:
  - Cursor agent as author and evidence recorder, not approver
informed: []
amends_master_plan: true
approval_evidence:
  - APP-0074
approval_reference: Exact human instruction in the active Cursor conversation to add paid live-AI caps and talk-time packs into the app
---

# ADR-0037: Paid live-AI caps and prepaid talk packs

## Status

Accepted on 1 September 2026 under APP-0074. Resolves
[DR-0047](../DR-0047_PAID_LIVE_AI_CAPS_AND_TALK_PACKS_REQUEST.md) Option A.
Amends ADR-0035 (paid plans were uncapped), master plan version 3.14,
and ADMIN-ENT-01. Recorded as D-0273.

## Context

ADR-0035 metered only the demo (300 seconds). Modules and Premium granted
unlimited live STT, chat and TTS. That cannot cover Azure/Sarvam cost on
₹350 / ₹500. The product owner accepted monthly pools plus prepaid hour-class
packs, not per-minute overage.

## Decision

1. Default membership caps: Modules **2400** seconds (40 minutes), Premium
   **5400** seconds (90 minutes). Demo stays 300 seconds. Existing Direct
   grants whose snapshot cap is null use these defaults for the named codes.
2. Prepaid pack codes `talk_pack_30` / `talk_pack_60` / `talk_pack_180`
   (₹199 / ₹349 / ₹899 ex-GST) add 1800 / 3600 / 10800 seconds to the same
   live-AI pool. They do not replace the membership, do not unlock Role-play,
   and are sold only to an active `calendar_month` grant.
3. Pack expiry is `min(membership expires_at, purchase + 30 days)`. Unused
   pack seconds die with the membership. No refunds. Dummy checkout remains
   the payment port; Play Billing stays unauthorized.
4. Operator grants must not use talk-pack plan ids as membership replacements.
5. Institutional organizations stay unmetered.

## Consequences

- OpenAPI **0.40.0**. Privacy inventory `learner-privacy-v8` (`live_ai_talk_packs`).
- Migration `2026_09_01_000310_create_live_ai_talk_packs`.
- Exhausted paid AI returns `entitlement.ai_exhausted`. Talk-pack checkout
  without a paid month returns `entitlement.talk_pack_requires_paid`.
- Live payment credentials and production remain unauthorized.

## Master-plan amendment

Amends §§9.2, 16.5, 31 and 32 (Monetization) as recorded in version 3.14.
