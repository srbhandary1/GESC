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
  - APP-0073
approval_reference: Exact human instruction in the active Cursor conversation to implement the best Direct model and grant existing students one Premium month
---

# ADR-0036: Premium flagship Direct plan and courtesy month

## Status

Accepted on 1 September 2026 under APP-0073. Resolves
[DR-0046](../DR-0046_PREMIUM_COURTESY_GRANT_REQUEST.md) Option A.
Amends ADR-0035 display names (codes unchanged), master plan version 3.13,
and the ADMIN-ENT-01 operator grant surface. Recorded as D-0267.

## Context

Direct already had GST-exclusive ₹350 / ₹500 monthly plans and a free
48-hour demo. SuperNova-style packaging is one recommended paid SKU.
Existing Direct learners would otherwise sit on auto-demo. The previous
website’s student database is not connected to this tenant.

## Decision

1. Keep plan codes `demo`, `monthly_modules`, `monthly_modules_roleplay`.
   Default display names become Two-day demo, Modules, and **Premium**.
   Version-1 catalogue rows are renamed on seed; operator edits (`version`
   greater than 1) are not overwritten.
2. Premium is the recommended checkout SKU. Modules remains the cheaper
   all-levels plan without Role-play. Prices, GST and demo rules are
   unchanged from ADR-0035.
3. Operators with `entitlements.grant` on Global English Direct may grant one
   complimentary calendar month of Premium to every current Direct learner
   membership (`POST /api/v1/operator/courtesy-premium-grants`). The artisan
   command `entitlements:grant-courtesy-premium` does the same. Optional
   `--csv` limits grants to listed usernames that already exist.
4. Courtesy events use `source=operator_grant` and payload
   `reason=courtesy_premium_month`. Replay is skipped. Active Premium that
   already covers the next month is skipped. Demo or Modules is replaced.
5. This does **not** import identities, passwords, progress or media from
   `www.globalspokenenglish.com`. ADR-0014’s legacy-production-data gate
   remains. Email logins from that site cannot become usernames here
   (ADR-0021).
6. Play Billing, live payment credentials and production remain unauthorized.

## Consequences

- OpenAPI **0.39.0**. Learner catalogue includes `recommended`.
- Institutional organizations stay unmetered and receive 404 on the
  courtesy route.

## Master-plan amendment

Amends §§16.5, 31 and 32 (Monetization) as recorded in version 3.13.
