---
status: accepted
date: 2026-09-24
deciders:
  - S R Bhandary as Accountable Product Owner and Technical Decision Owner
consulted:
  - Cursor agent as author and evidence recorder, not approver
informed: []
amends_master_plan: true
approval_evidence:
  - Accountable Product Owner instruction in the active Cursor conversation
approval_reference: Exact human instruction to register the student automatically once payment succeeds, for the plan that was paid
---

# ADR-0041: Paid registration creates the Direct account

## Status

Accepted on 24 September 2026. Amends ADR-0021 enrollment and ADR-0035
checkout so a Direct learner account is created by a captured payment.
Invitations remain the enrollment path for staff and institutions.

## Decision

1. `POST /api/v1/direct/paid-registrations` accepts a phone, a new password,
   and a Direct plan. The password is stored only as an Argon2id hash on a
   pending registration intent.
2. The user row, phone username, and entitlement are written only when
   payment capture completes. A rejected password or failed capture creates
   no account. The purchased plan is the plan granted.
3. An existing phone with a matching password receives that plan on the
   existing account. A second account is not created.
4. Talk packs are not a registration plan. They stay available to a signed-in
   paid learner.
5. The Android app uses the same capture rule. It calls
   `POST /api/v1/auth/mobile/direct/paid-registrations`, opens Razorpay when
   the order is pending, and receives a Passport token family only after
   capture. No web session cookie is created.

## Master-plan amendment

Direct learner enrollment may be a captured in-app payment for the selected
plan. Invitations still authorize staff and institution enrollment.

## Owner amendment — 14 October 2026 (`VERIFY-FIX-16`)

Direct paid registration also supports **Google, Facebook, and Instagram** credentials (`intent=register&plan_id=...`). The verified provider subject is held encrypted on `paid_registration_intents` (`federated_provider`, `federated_subject_ciphertext`) and materialized into `users`, `federated_identities`, `memberships`, and `learner_entitlements` only when payment capture succeeds, then cleared (`federated_subject_ciphertext = null`). Once payment succeeds, the session or Android token family automatically selects the learner membership so the student lands in their appropriate account type.
