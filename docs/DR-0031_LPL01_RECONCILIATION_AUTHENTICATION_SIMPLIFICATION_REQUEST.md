# DR-0031 — LPL-01 reconciliation authentication simplification

**Status:** Resolved and broadened under APP-0060 / ADR-0023 / D-0193

**Needed by:** Future governed transcript-reconciliation campaigns and the
operator content workflow

**Prepared:** 14 August 2026

## 1. Decision needed

Choose whether an already authenticated and authorized administrator may
complete one governed transcript-reconciliation campaign without repeatedly
re-entering the same password.

The completed LPL-01 campaign contains 19 minutes 32.513 seconds of source
audio. Its temporary page required five-minute password freshness for both the
private candidate read and the reconciliation mutation. Successful sign-in
deliberately created no freshness proof, candidate review could outlast the
five-minute window, and confirmation lived in a separate Account Security tab.
The human therefore had to repeat the same one-factor credential ceremony
while completing one continuous four-part task.

This behavior follows ADR-0020's candidate-read/reconciliation requirement and
ADR-0021/ADR-0022's five-minute protected-command baseline. It is not a
technical requirement of Sanctum, tenant authorization, exact-hash binding,
idempotency or immutable reconciliation evidence.

## 2. Invariants under every option

- One platform-global username/password sign-in remains the only routine Web
  authentication factor; no OTP/TOTP or second factor is restored.
- The server retains the Secure, HttpOnly, same-origin Sanctum session, CSRF,
  session expiry/revocation and credential-version checks.
- Every request retains current selected-organization membership,
  `media.metadata.manage`, exact organization/campaign/asset scope, expected
  version, candidate digest and idempotency checks.
- Candidate and transcript content remains private/no-store, absent from logs,
  audit, outbox, analytics and browser persistence.
- Complete-playback, every-segment review and human-attestation requirements
  remain visible workflow gates. Confirmed/corrected outcomes remain atomic
  metadata-authoring operations; English academic and Kannada review remain
  separate.
- Rights approval, publication, account recovery, authority changes and other
  independently classified high-impact commands retain fresh-password
  protection unless a later explicit decision changes them.

## 3. Options

| Option | Decision | Consequence |
|---|---|---|
| **A — One signed-in operator session (recommended)** | Reclassify governed candidate read and transcript reconciliation as ordinary permissioned content operations. Remove `auth.password-confirmed` only from the two exact reconciliation routes and provide one integrated campaign wizard after normal sign-in and organization selection. | Matches the expected one-login workflow and removes a repeated same-factor ceremony. A stolen active authorized session could read or reconcile campaign transcripts until normal session revocation/expiry; exact tenant, permission, hash, version, audit and review gates still apply. |
| **B — One campaign-scoped step-up** | After normal sign-in and organization selection, require the current password once to issue a server-side, maximum-30-minute grant bound to the exact session, organization, campaign and candidate hashes. | Reduces prompts while retaining an unattended-session defense. It remains an additional authentication action and adds a new grant lifecycle, revocation model, schema/API surface and tests. |
| **C — Retain five-minute confirmation** | Keep the current route middleware and separate confirmation flow. UI may place confirmation inline but cannot eliminate repeated prompts for long audio. | Preserves the accepted policy exactly but retains the observed cumbersome journey. |

## 4. Recommendation

Accept Option A for governed curriculum-media reconciliation only.

The transcript is owner-authorized teaching content, not a learner recording,
and the reconciler already needs an active privileged session, selected tenant
membership and the exact metadata permission. Re-entering the same password is
one-factor freshness rather than MFA. It provides some protection against an
unattended or stolen session, but requiring it repeatedly during a continuous
19-minute review adds substantial friction without changing tenant or content
authority. Higher-impact approval, publication, recovery and authority
commands keep the existing step-up boundary.

## 5. Required implementation if Option A is accepted

1. Add a successor ADR that explicitly amends ADR-0020's reconciliation
   command boundary and ADR-0021/ADR-0022's protected-command classification;
   the master plan's general fresh-password rule need not change if these two
   routes are no longer classified as sensitive privileged actions.
2. Remove `auth.password-confirmed` from only the candidate-read and
   reconciliation routes. Retain `auth:web`, `auth.session`, normal throttle,
   organization context and application-command authorization.
3. Replace the temporary separate-tab page with one authenticated campaign
   wizard that loads only the selected exact asset, keeps sensitive state in
   memory, clears it on navigation/submission and never uses browser storage.
4. Preserve playback, segment, attestation, optimistic-concurrency,
   idempotency, metadata-authoring, audit/outbox and candidate-deletion
   behavior.
5. Update OpenAPI/client descriptions and the exact route-policy test so no
   other protected route is weakened accidentally.
6. Add same-tenant, cross-tenant, wrong-permission, revoked-session,
   stale-version/hash, replay, no-store and complete four-part browser tests.
7. Run `make verify` and `make e2e`; run `make build` if the delivered operator
   UI changes. No provider call or `make ai-eval` is required unless provider
   or orchestration behavior changes.

## 6. Non-authorization

This open request changes no route, session, password, role, permission,
candidate, metadata or publication behavior. The product owner's question
about simplifying the completed journey is problem evidence, not exact option
acceptance. Implementation requires an explicit `Approve DR-0031 Option A as
written.` (or another exact option) and the resulting successor ADR.

## 7. Resolution

On 14 August 2026 the product owner first accepted the immediately preceding
one-login proposal and then expressly broadened it to remove the repeated
"enterprise security" ceremony in favor of username/password sign-in and
role-based groups. [APP-0060](APPROVALS.md#app-0060) records the exact human
statement and this request's pre-resolution SHA-256
`da8f04370560bf7c8309a33c04b646e6ca0ef4951fcf3376ba34bdff893b739b`.

[ADR-0023](adr/0023-one-login-role-group-operator-authorization.md) therefore
accepts Option A for reconciliation and broadens its principle to routine
operator work. Current-password confirmation remains only for credential,
recovery-contact and administrator-authority changes. The resolution does not
remove password hashing, secure cookie transport, CSRF, throttling, tenant
isolation, role/permission enforcement, authoritative transactions or audit.
