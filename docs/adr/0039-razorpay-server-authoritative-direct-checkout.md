---
status: accepted
date: 2026-09-18
deciders:
  - Accountable Product Owner
  - Technical Decision Owner
consulted:
  - Codex as architecture author and recorder
informed: []
amends_master_plan: false
approval_evidence:
  - APP-0100
approval_reference: Exact human authorization and Razorpay test credential disclosure in the active Codex conversation on 2026-09-18
---

# ADR-0039: Razorpay server-authoritative Direct checkout

## Status

Accepted on 18 September 2026 under
[APP-0100](../APPROVALS.md#app-0100). This decision implements the payment
provider boundary already anticipated by ADR-0035 and ADR-0037. It does not
change plan prices, GST rules, entitlement durations or talk-pack quantities.

## Context

Direct learners already see an authoritative server catalogue and the existing
dummy provider exercises deterministic entitlement grants. A real provider must
not let browser claims, retries, webhook ordering or tenant identifiers create
duplicate orders, grant unpaid access or expose another learner's checkout.
Razorpay exposes a public Checkout key to the browser, while its key secret and
webhook secret must remain server-only.

## Decision

1. `PaymentProvider` remains the project-owned boundary. Razorpay SDK and API
   contracts do not enter entitlement-domain interfaces.
2. The API commits a tenant/learner-bound pending checkout before requesting a
   provider order. Its idempotency key, amount, tax and complete plan snapshot
   are immutable authority. An ambiguous provider response leaves that record
   pending; replay cannot create another order.
3. The web client receives only the public key, exact amount, INR currency and
   provider order ID. It loads Razorpay Checkout only after a deliberate plan
   action and sends the returned payment ID/signature to the API. It sends no
   learner profile, credential or authored learning content to Razorpay.
4. A callback is not proof of payment. The server verifies HMAC-SHA256 over the
   server-stored order ID and submitted payment ID, then reads the Razorpay
   order and payment. Completion requires the expected receipt, amount and INR
   currency, order state `paid`, payment state `captured`, and matching IDs.
5. Webhooks verify HMAC-SHA256 over the exact raw body before parsing. Receipts
   store only provider identifiers, event type and a SHA-256 digest; raw payload
   and payment-instrument details are not retained. Provider event IDs are
   unique. Callback and webhook completion converge on the same locked,
   idempotent entitlement transaction.
6. A failed payment attempt does not fail the reusable order. Pending,
   ambiguous, failed and reconciled UI states remain truthful. Reconciliation
   cannot read or mutate a checkout outside the authenticated organization and
   learner membership.
7. Talk packs are rejected before order creation unless an active non-demo paid
   membership exists, and are checked again under lock before grant. No review,
   quiz, mastery or learning record is changed by checkout.
8. `RAZORPAY_MODE=test` requires an `rzp_test_` key. `live` requires an
   `rzp_live_` key. Production dependency injection refuses test mode. Secrets
   are external owner-only configuration; only the public key reaches clients.

## Consequences

- New checkout reconciliation fields and a content-free webhook receipt table
  require a forward migration. Rollback drops only those additions; it cannot
  undo provider-side orders or already granted entitlements.
- The invite-only soft launch can validate end-to-end test payments without
  representing them as real capture. Live launch additionally needs live
  credentials, Dashboard webhook registration, canary/refund evidence and
  operational reconciliation.
- Checkout depends on Razorpay's hosted script and API availability. Failure is
  visible and recoverable; it never becomes a successful entitlement locally.

## Rejected alternatives

- Trusting the browser success callback: forgeable and not authoritative.
- Granting on webhook payload alone: signed delivery still needs provider-state
  and local amount/tenant reconciliation.
- Creating an order before the local idempotency record: response loss could
  permit duplicate orders on replay.
- Storing raw webhooks: unnecessary payment/personal-data retention.
- Enabling a test key under `APP_ENV=production`: it would grant real product
  access without collecting real money.
