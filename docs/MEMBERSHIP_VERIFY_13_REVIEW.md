# MEMBERSHIP-VERIFY-13 review — 26 September 2026

## Reviewed journey

SIGNUP-PLAN-12 and APP-0110 are the supplied membership changes: chosen-plan
routes from guest/home marketing; three signup memberships without talk packs;
live ex-GST/GST/total prices; password confirmation; pending Razorpay confirmation
before browser account/session creation; existing-month replacement disclosure
and disabled current-plan purchase. Android uses its existing separate token
confirmation/secure-storage flow. Human Kannada sign-offs are preserved.

## Findings corrected and files

`apps/api/app/Entitlements/LearnerSubscriptionService.php` now enforces the current
paid-plan exclusion before creating a fresh checkout, for signed-in checkout and
existing-phone registration. Expired plans and different plans remain purchasable;
talk-pack policy is unchanged. Original idempotent retries still recover the
original checkout. Replay also requires the same tenant membership (signed-in)
or registration phone and valid password (guest), preventing a reused key from
recovering another learner's checkout/session. Existing-phone registration also
requires an active Direct learner membership before payment begins.

`WebPasswordSignInPanel.tsx` replaces an effect-based error reset with a guarded
mode transition, clearing payment errors on browser Back as well as explicit
navigation without violating lint. The unchanged error-boundary behavior is tested
against the newly approved Kannada catalogue in `ApplicationErrorBoundary.test.tsx`.

`PaidRegistrationTest.php` and `DirectEntitlementTest.php` add behavior tests for
ownership-bound replay, duplicate current-plan purchase, expiration and preserved
idempotent retry. `WebSessionAuthenticationAdapter.test.ts` adds cancelled checkout,
wrong-order and pending-confirmation coverage. `appRelease.json` and downloads
README identify the verified Android update as 1.0.12/code 13.

No API shape or migration/configuration change. No marks, progress, entitlement
pricing/formulas, retention, credential storage or provider integration change.
The existing generic checkout-unavailable response is reused. Tests use synthetic
identities and provider doubles; no real-money capture or live membership mutation
is performed. Tenant/learner checks are strengthened, not delegated to UI state.

## Verification

Focused web review: 59 tests across seven files passed before the three added
negative-payment tests. Focused PostgreSQL paid-registration/mobile tests: 8 tests,
84 assertions passed. SQLite Direct entitlement tests: 12 tests, 90 assertions
passed. The first duplicate-plan tests exposed a timezone-sensitive start-time
comparison; the check now follows the existing active-status/expiry policy.
Browser E2E (`pnpm run e2e` from the repository root): 47 journeys passed.
Live 390px signup check passed: three plans, Premium total ₹590 (₹500 + ₹90 GST),
Basic total ₹354, mismatched-password rejection with zero registration/payment
requests and no horizontal overflow. Final Android and public web builds passed;
13 native JVM tests and APK signature/metadata checks passed.

The first full check exposed the redundant React effect. A later run encountered
the database guard because `make build` temporarily caches Laravel configuration
while tests were running. No unsafe database test ran; final full verification is
isolated from builds. Final results and device evidence follow below.

## Limits and next checkpoint

Live plan selection and device navigation are read-only. Real payment-provider
capture/cancellation is validated with adapter/provider doubles, not a real-money
purchase. Separate live payment canary/reconciliation authorization remains as
recorded in SUBSCRIPTIONS.md. Next checkpoint: the separately authorized sandbox
payment canary on representative devices, if required before wider rollout.

## Final verification

`make verify` completed PHP style/static/security checks, SQLite 364 tests/3,365
assertions, PostgreSQL 444 tests/6,473 assertions, package audit, locale and API/
release contracts. It then identified two web failures: browser-Back error reset
and a stale pre-sign-off Kannada expectation. After correcting those, the remaining
root commands `pnpm run lint`, `pnpm run typecheck`, `pnpm run test` all passed
(576 web tests, 38 localization, 18 design-system, 4 marketing). The full backend
suite was not repeated for these frontend-only corrections. Final browser rerun:
`pnpm run e2e`, 47 passed. No AI evaluation rerun is required: prompts, models,
speech and orchestration are unchanged.

## Deployment and physical device

Release `membership-verified-20260926-r2` is live. Public readiness, index and APK
hashes passed after transient connection-reset retries during service restart.
The deployed checkout service SHA-256 matches the reviewed local file exactly:
`86560e2e0829149ce48ec6f88f3c90854f7461dc784af960ec52cb8f08316dd7`.

Final APK: `global-spoken-english-1.0.12.apk`, 15,436,810 bytes, SHA-256
`048f9c171f06de0af1bcb9ec375646466e21224c924d5723d36aa9010e8e6e19`.
Final Android build/signature and public web build passed; native JVM suite has
13 tests and zero failures. Install-in-place succeeded; Android reports
1.0.12/code 13. The signed-in phone's Kannada Account settings show this version,
two paid memberships, the current-plan action disabled and the replacement warning,
with no horizontal overflow. No purchase control was activated. Existing account
and app data were preserved. Public mobile homepage also shows v1.0.12 and the
matching filename. `git diff --check` passed. The temporary CDP relay is stopped;
the Mac ADB tunnel remains available.
